<?php
/**
 * Export Coupons to CSV
 * Query: Coupons with usage < 3 AND older than 2 years
 * Output: CSV with Coupon Code | Creation Date | Use Count
 */

// Load WordPress from bloomz-prod-08-15 site
require_once('/Users/noelsaw/Local Sites/bloomz-prod-08-15/app/public/wp-load.php');

global $wpdb;

$cutoff_date = date("Y-m-d H:i:s", strtotime("-2 years"));

echo "\n";
echo "===================================================================\n";
echo "COUPON EXPORT TO CSV\n";
echo "===================================================================\n";
echo "Criteria: Usage count < 3 AND Created > 2 years ago\n";
echo "Cutoff date: $cutoff_date\n\n";

// Get matching coupons
$query = "
    SELECT 
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

$total = count($results);
echo "Found " . number_format($total) . " matching coupons\n\n";

// Export to CSV
$csv_file = __DIR__ . '/old-coupons-export.csv';
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
echo "✅ Export complete!\n";
echo "   File size: " . number_format($file_size) . " bytes (" . round($file_size / 1024, 1) . " KB)\n";
echo "   Location: $csv_file\n";
echo "\n";

// Show statistics
$stats = [
    'never_used' => 0,
    'used_once' => 0,
    'used_twice' => 0
];

foreach ($results as $row) {
    if ($row['use_count'] == 0) $stats['never_used']++;
    if ($row['use_count'] == 1) $stats['used_once']++;
    if ($row['use_count'] == 2) $stats['used_twice']++;
}

echo "===================================================================\n";
echo "SUMMARY STATISTICS\n";
echo "===================================================================\n";
echo "Total exported:      " . number_format($total) . "\n";
echo "Never used (0):      " . number_format($stats['never_used']) . " (" . round(($stats['never_used'] / $total) * 100, 1) . "%)\n";
echo "Used once (1):       " . number_format($stats['used_once']) . " (" . round(($stats['used_once'] / $total) * 100, 1) . "%)\n";
echo "Used twice (2):      " . number_format($stats['used_twice']) . " (" . round(($stats['used_twice'] / $total) * 100, 1) . "%)\n";
echo "\n";

// Show preview
echo "===================================================================\n";
echo "PREVIEW (First 10 rows)\n";
echo "===================================================================\n";
printf("%-50s %-20s %-10s\n", "Coupon Code", "Creation Date", "Use Count");
echo str_repeat("-", 85) . "\n";

for ($i = 0; $i < min(10, count($results)); $i++) {
    printf("%-50s %-20s %-10s\n",
        substr($results[$i]['coupon_code'], 0, 50),
        $results[$i]['creation_date'],
        $results[$i]['use_count']
    );
}

echo "\n";
echo "===================================================================\n";
echo "✅ DONE! CSV file ready at:\n";
echo "   $csv_file\n";
echo "===================================================================\n";

