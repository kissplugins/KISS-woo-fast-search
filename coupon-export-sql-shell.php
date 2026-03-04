<?php
/**
 * Coupon Export to CSV
 * Query: Coupons with usage < 3 AND older than 2 years
 * Output: CSV with Coupon Code | Creation Date | Use Count
 */

// Determine WordPress root
$wp_root = '/Users/noelsaw/Local Sites/sql-shell/app/public';
if (!file_exists($wp_root . '/wp-load.php')) {
    echo "ERROR: WordPress not found at $wp_root\n";
    exit(1);
}

// Load WordPress
require_once($wp_root . '/wp-load.php');

global $wpdb;

$cutoff_date = date("Y-m-d H:i:s", strtotime("-2 years"));

echo "\n";
echo "===================================================================\n";
echo "COUPON EXPORT - Starting Analysis\n";
echo "===================================================================\n";
echo "Criteria: Usage count < 3 AND Created > 2 years ago\n";
echo "Cutoff date: $cutoff_date\n\n";

// Get total coupon count first
$total_all = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM {$wpdb->posts} 
    WHERE post_type = 'shop_coupon'
    AND post_status NOT IN ('trash', 'auto-draft')
");

echo "Total coupons in database: " . number_format($total_all) . "\n\n";

// Get matching coupons with usage count
$query = "
    SELECT 
        p.ID,
        p.post_title as coupon_code,
        DATE_FORMAT(p.post_date, '%Y-%m-%d %H:%i:%s') as creation_date,
        COALESCE(
            (SELECT CAST(meta_value AS UNSIGNED)
             FROM {$wpdb->postmeta} 
             WHERE post_id = p.ID 
             AND meta_key = 'usage_count'
             LIMIT 1), 
            0
        ) as use_count
    FROM {$wpdb->posts} p
    WHERE p.post_type = 'shop_coupon'
      AND p.post_date < '$cutoff_date'
      AND p.post_status NOT IN ('trash', 'auto-draft')
      AND COALESCE(
          (SELECT CAST(meta_value AS UNSIGNED)
           FROM {$wpdb->postmeta} 
           WHERE post_id = p.ID 
           AND meta_key = 'usage_count'
           LIMIT 1), 
          0
      ) < 3
    ORDER BY p.post_date ASC
";

echo "Running query...\n";
$results = $wpdb->get_results($query, ARRAY_A);

if (!$results) {
    echo "No matching coupons found.\n";
    exit(1);
}

$total_matching = count($results);
echo "Found " . number_format($total_matching) . " matching coupons\n\n";

// Calculate statistics
$never_used = 0;
$used_once = 0;
$used_twice = 0;

foreach ($results as $row) {
    if ($row['use_count'] == 0) $never_used++;
    if ($row['use_count'] == 1) $used_once++;
    if ($row['use_count'] == 2) $used_twice++;
}

echo "===================================================================\n";
echo "SUMMARY STATISTICS\n";
echo "===================================================================\n";
echo "Total matching:      " . number_format($total_matching) . "\n";
echo "Never used (0):      " . number_format($never_used) . " (" . round(($never_used / $total_matching) * 100, 1) . "%)\n";
echo "Used once (1):       " . number_format($used_once) . " (" . round(($used_once / $total_matching) * 100, 1) . "%)\n";
echo "Used twice (2):      " . number_format($used_twice) . " (" . round(($used_twice / $total_matching) * 100, 1) . "%)\n";
echo "\n";

// Export to CSV
$csv_file = __DIR__ . '/coupon-export.csv';
echo "Exporting to CSV: $csv_file\n";

$fp = fopen($csv_file, 'w');

if (!$fp) {
    echo "ERROR: Could not create CSV file\n";
    exit(1);
}

// Write header
fputcsv($fp, ['Coupon Code', 'Creation Date', 'Use Count']);

// Write data
foreach ($results as $row) {
    fputcsv($fp, [
        $row['coupon_code'],
        $row['creation_date'],
        $row['use_count']
    ]);
}

fclose($fp);

$file_size = filesize($csv_file);
echo "Export complete!\n";
echo "File size: " . number_format($file_size) . " bytes\n";
echo "Location: $csv_file\n";
echo "\n";

// Show first 20 rows as preview
echo "===================================================================\n";
echo "PREVIEW (First 20 rows)\n";
echo "===================================================================\n";
printf("%-50s %-20s %-10s\n", "Coupon Code", "Creation Date", "Use Count");
echo str_repeat("-", 85) . "\n";

for ($i = 0; $i < min(20, count($results)); $i++) {
    printf("%-50s %-20s %-10s\n",
        substr($results[$i]['coupon_code'], 0, 50),
        $results[$i]['creation_date'],
        $results[$i]['use_count']
    );
}

echo "\n";
echo "===================================================================\n";
echo "DONE! CSV file ready at: $csv_file\n";
echo "===================================================================\n";

