#!/usr/bin/env python3
"""
Analyze coupons from SQL dump using pandas + sqlite3
"""

import sqlite3
import pandas as pd
import re
from datetime import datetime, timedelta
import sys

print("=" * 70)
print("COUPON ANALYSIS FROM SQL DUMP")
print("=" * 70)
print()

# Create in-memory SQLite database
print("Step 1: Creating SQLite database...")
conn = sqlite3.connect(':memory:')
cursor = conn.cursor()

# Read and parse SQL dump
sql_file = '/Users/noelsaw/Local Sites/bloomz-prod-08-15/app/sql/bloomz-coupons.sql'
print(f"Step 2: Reading SQL dump: {sql_file}")
print("         (This may take a while for a 16GB file...)")

# We'll process the file in chunks to avoid memory issues
chunk_size = 1024 * 1024 * 10  # 10MB chunks

try:
    with open(sql_file, 'r', encoding='utf-8', errors='ignore') as f:
        sql_buffer = ""
        line_count = 0
        
        for line in f:
            line_count += 1
            if line_count % 100000 == 0:
                print(f"         Processed {line_count:,} lines...", end='\r')
            
            # Skip comments and empty lines
            if line.startswith('--') or line.startswith('/*') or line.strip() == '':
                continue
            
            sql_buffer += line
            
            # Execute when we hit a semicolon (end of statement)
            if ';' in line:
                try:
                    cursor.executescript(sql_buffer)
                    sql_buffer = ""
                except sqlite3.Error as e:
                    # Skip errors (SQLite may not support all MySQL syntax)
                    sql_buffer = ""
                    pass
        
        print(f"\n         Processed {line_count:,} total lines")

except FileNotFoundError:
    print(f"ERROR: File not found: {sql_file}")
    sys.exit(1)
except Exception as e:
    print(f"ERROR: {e}")
    sys.exit(1)

print()
print("Step 3: Analyzing data...")

# Get table names
tables = pd.read_sql_query(
    "SELECT name FROM sqlite_master WHERE type='table'",
    conn
)
print(f"         Found {len(tables)} tables: {', '.join(tables['name'].tolist())}")
print()

# Check if wp_posts exists
if 'wp_posts' not in tables['name'].values:
    print("ERROR: wp_posts table not found in SQL dump")
    conn.close()
    sys.exit(1)

# Get total coupon count
total_coupons = pd.read_sql_query(
    """
    SELECT COUNT(*) as total
    FROM wp_posts
    WHERE post_type = 'shop_coupon'
    AND post_status NOT IN ('trash', 'auto-draft')
    """,
    conn
)

print("=" * 70)
print("TOTAL COUPON COUNT")
print("=" * 70)
print(f"Total coupons: {total_coupons['total'].iloc[0]:,}")
print()

# Calculate cutoff date (2 years ago)
cutoff_date = (datetime.now() - timedelta(days=730)).strftime('%Y-%m-%d %H:%M:%S')

# Get old + rarely used coupons
query = f"""
SELECT 
    p.ID,
    p.post_title as code,
    p.post_date as created,
    p.post_status as status
FROM wp_posts p
WHERE p.post_type = 'shop_coupon'
  AND p.post_date < '{cutoff_date}'
  AND p.post_status NOT IN ('trash', 'auto-draft')
"""

old_coupons = pd.read_sql_query(query, conn)

# Try to get usage_count from wp_postmeta if it exists
if 'wp_postmeta' in tables['name'].values:
    usage_query = """
    SELECT 
        post_id,
        CAST(meta_value AS INTEGER) as usage_count
    FROM wp_postmeta
    WHERE meta_key = 'usage_count'
    """
    usage_data = pd.read_sql_query(usage_query, conn)
    
    # Merge with old_coupons
    old_coupons = old_coupons.merge(
        usage_data, 
        left_on='ID', 
        right_on='post_id', 
        how='left'
    )
    old_coupons['usage_count'] = old_coupons['usage_count'].fillna(0).astype(int)
    
    # Filter to usage <= 2
    old_rarely_used = old_coupons[old_coupons['usage_count'] <= 2]
else:
    print("WARNING: wp_postmeta table not found, cannot check usage_count")
    old_rarely_used = old_coupons

print("=" * 70)
print("OLD + RARELY USED COUPONS (>2 years, ≤2 uses)")
print("=" * 70)
print(f"Total matching: {len(old_rarely_used):,}")

if 'usage_count' in old_rarely_used.columns:
    print(f"Never used (0):  {len(old_rarely_used[old_rarely_used['usage_count'] == 0]):,}")
    print(f"Used once (1):   {len(old_rarely_used[old_rarely_used['usage_count'] == 1]):,}")
    print(f"Used twice (2):  {len(old_rarely_used[old_rarely_used['usage_count'] == 2]):,}")

print()
print("Sample (first 20):")
print(old_rarely_used.head(20).to_string(index=False))
print()

conn.close()
print("=" * 70)
print("Analysis complete!")
print("=" * 70)

