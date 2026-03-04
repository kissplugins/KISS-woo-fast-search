#!/usr/bin/env python3
"""
Extract coupon data from MySQL dump and query with SQLite
"""

import sqlite3
import re
import csv
from datetime import datetime, timedelta

print("=" * 70)
print("COUPON EXTRACTION FROM MYSQL DUMP TO SQLITE")
print("=" * 70)
print()

# Create SQLite database
db_file = 'coupons.db'
print(f"Step 1: Creating SQLite database: {db_file}")
conn = sqlite3.connect(db_file)
cursor = conn.cursor()

# Create tables
print("Step 2: Creating tables...")
cursor.execute("""
    CREATE TABLE IF NOT EXISTS wp_posts (
        ID INTEGER PRIMARY KEY,
        post_title TEXT,
        post_date TEXT,
        post_type TEXT,
        post_status TEXT
    )
""")

cursor.execute("""
    CREATE TABLE IF NOT EXISTS wp_postmeta (
        meta_id INTEGER PRIMARY KEY,
        post_id INTEGER,
        meta_key TEXT,
        meta_value TEXT
    )
""")

cursor.execute("CREATE INDEX IF NOT EXISTS idx_post_type ON wp_posts(post_type)")
cursor.execute("CREATE INDEX IF NOT EXISTS idx_post_date ON wp_posts(post_date)")
cursor.execute("CREATE INDEX IF NOT EXISTS idx_meta_post_id ON wp_postmeta(post_id)")
cursor.execute("CREATE INDEX IF NOT EXISTS idx_meta_key ON wp_postmeta(meta_key)")

conn.commit()

# Parse MySQL dump and extract INSERT statements
sql_file = '/Users/noelsaw/Local Sites/sql-shell/binoid-coupons.sql'
print(f"Step 3: Parsing MySQL dump: {sql_file}")
print("         (This will take several minutes for a 16GB file...)")
print()

posts_count = 0
meta_count = 0
line_count = 0
in_posts_insert = False
in_postmeta_insert = False

try:
    with open(sql_file, 'r', encoding='utf-8', errors='ignore') as f:
        current_table = None
        buffer = ""
        
        for line in f:
            line_count += 1
            
            if line_count % 100000 == 0:
                print(f"         Processed {line_count:,} lines... (Posts: {posts_count:,}, Meta: {meta_count:,})", end='\r')
            
            # Detect table context
            if 'INSERT INTO `wp_posts`' in line or 'INSERT INTO wp_posts' in line:
                current_table = 'wp_posts'
                buffer = line
                continue
            elif 'INSERT INTO `wp_postmeta`' in line or 'INSERT INTO wp_postmeta' in line:
                current_table = 'wp_postmeta'
                buffer = line
                continue
            
            # Accumulate multi-line INSERT statements
            if current_table:
                buffer += line
                
                # Check if statement is complete (ends with semicolon)
                if ';' in line:
                    # Extract VALUES portion
                    values_match = re.search(r'VALUES\s+(.+);', buffer, re.DOTALL | re.IGNORECASE)
                    
                    if values_match:
                        values_str = values_match.group(1)
                        
                        # Parse individual value tuples
                        # This is a simplified parser - may not handle all edge cases
                        tuples = re.findall(r'\(([^)]+)\)', values_str)
                        
                        for tuple_str in tuples:
                            if current_table == 'wp_posts':
                                # Parse wp_posts row
                                # Expected format: (ID, post_author, post_date, ..., post_type, ...)
                                # We need: ID, post_title, post_date, post_type, post_status
                                parts = [p.strip().strip("'\"") for p in tuple_str.split(',')]
                                
                                if len(parts) > 20:  # wp_posts has many columns
                                    try:
                                        post_id = int(parts[0])
                                        post_date = parts[2]
                                        post_title = parts[4]
                                        post_status = parts[7]
                                        post_type = parts[20] if len(parts) > 20 else ''
                                        
                                        # Only insert shop_coupon posts
                                        if 'shop_coupon' in post_type:
                                            cursor.execute(
                                                "INSERT OR IGNORE INTO wp_posts (ID, post_title, post_date, post_type, post_status) VALUES (?, ?, ?, ?, ?)",
                                                (post_id, post_title, post_date, post_type, post_status)
                                            )
                                            posts_count += 1
                                    except (ValueError, IndexError):
                                        pass
                            
                            elif current_table == 'wp_postmeta':
                                # Parse wp_postmeta row
                                # Expected format: (meta_id, post_id, meta_key, meta_value)
                                parts = [p.strip().strip("'\"") for p in tuple_str.split(',', 3)]
                                
                                if len(parts) >= 4:
                                    try:
                                        meta_id = int(parts[0])
                                        post_id = int(parts[1])
                                        meta_key = parts[2]
                                        meta_value = parts[3]
                                        
                                        # Only insert usage_count meta
                                        if 'usage_count' in meta_key:
                                            cursor.execute(
                                                "INSERT OR IGNORE INTO wp_postmeta (meta_id, post_id, meta_key, meta_value) VALUES (?, ?, ?, ?)",
                                                (meta_id, post_id, meta_key, meta_value)
                                            )
                                            meta_count += 1
                                    except (ValueError, IndexError):
                                        pass
                    
                    # Commit every 10000 rows
                    if (posts_count + meta_count) % 10000 == 0:
                        conn.commit()
                    
                    # Reset
                    current_table = None
                    buffer = ""

        print(f"\n         Processed {line_count:,} total lines")
        print(f"         Inserted {posts_count:,} coupon posts")
        print(f"         Inserted {meta_count:,} usage_count meta rows")

except FileNotFoundError:
    print(f"ERROR: File not found: {sql_file}")
    conn.close()
    exit(1)
except Exception as e:
    print(f"ERROR: {e}")
    import traceback
    traceback.print_exc()
    conn.close()
    exit(1)

conn.commit()

print()
print("Step 4: Running analysis query...")

# Calculate cutoff date (2 years ago)
cutoff_date = (datetime.now() - timedelta(days=730)).strftime('%Y-%m-%d %H:%M:%S')

# Query for old + rarely used coupons
query = f"""
SELECT 
    p.post_title as coupon_code,
    p.post_date as creation_date,
    COALESCE(CAST(m.meta_value AS INTEGER), 0) as use_count
FROM wp_posts p
LEFT JOIN wp_postmeta m ON p.ID = m.post_id AND m.meta_key = 'usage_count'
WHERE p.post_type = 'shop_coupon'
  AND p.post_date < '{cutoff_date}'
  AND p.post_status NOT IN ('trash', 'auto-draft')
  AND COALESCE(CAST(m.meta_value AS INTEGER), 0) < 3
ORDER BY p.post_date ASC
"""

results = cursor.execute(query).fetchall()

print(f"Found {len(results):,} matching coupons")
print()

# Export to CSV
csv_file = 'old-coupons-export.csv'
print(f"Step 5: Exporting to CSV: {csv_file}")

with open(csv_file, 'w', newline='') as f:
    writer = csv.writer(f)
    writer.writerow(['Coupon Code', 'Creation Date', 'Use Count'])
    writer.writerows(results)

print(f"✅ Export complete! ({len(results):,} rows)")
print()

# Show statistics
stats = {'never_used': 0, 'used_once': 0, 'used_twice': 0}
for row in results:
    if row[2] == 0: stats['never_used'] += 1
    if row[2] == 1: stats['used_once'] += 1
    if row[2] == 2: stats['used_twice'] += 1

print("=" * 70)
print("SUMMARY STATISTICS")
print("=" * 70)
print(f"Total exported:      {len(results):,}")
print(f"Never used (0):      {stats['never_used']:,} ({round((stats['never_used'] / len(results)) * 100, 1) if results else 0}%)")
print(f"Used once (1):       {stats['used_once']:,} ({round((stats['used_once'] / len(results)) * 100, 1) if results else 0}%)")
print(f"Used twice (2):      {stats['used_twice']:,} ({round((stats['used_twice'] / len(results)) * 100, 1) if results else 0}%)")
print()

# Show preview
print("=" * 70)
print("PREVIEW (First 10 rows)")
print("=" * 70)
print(f"{'Coupon Code':<50} {'Creation Date':<20} {'Use Count':<10}")
print("-" * 85)

for row in results[:10]:
    print(f"{row[0][:50]:<50} {row[1]:<20} {row[2]:<10}")

print()
print("=" * 70)
print(f"✅ DONE! CSV file ready at: {csv_file}")
print("=" * 70)

conn.close()

