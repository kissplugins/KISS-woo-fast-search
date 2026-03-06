<?php
/**
 * Coupon Analysis Report
 * Find coupons with usage_count <= 2 AND created more than 2 years ago
 */

// Load WordPress
require_once('/Users/noelsaw/Local Sites/bloomz-prod-08-15/app/public/wp-load.php');

global $wpdb;

$cutoff_date = date("Y-m-d H:i:s", strtotime("-2 years"));

echo "\n";
echo "===================================================================\n";
echo "COUPON ANALYSIS REPORT\n";
echo "Criteria: Usage count <= 2 AND Created > 2 years ago\n";
echo "===================================================================\n\n";

// Get summary statistics
$summary = $wpdb->get_row("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN usage_count = 0 THEN 1 ELSE 0 END) as never_used,
        SUM(CASE WHEN usage_count = 1 THEN 1 ELSE 0 END) as used_once,
        SUM(CASE WHEN usage_count = 2 THEN 1 ELSE 0 END) as used_twice,
        MIN(post_date) as oldest_date,
        MAX(post_date) as newest_date
    FROM (
        SELECT 
            p.post_date,
            COALESCE(
                (SELECT CAST(meta_value AS UNSIGNED)
                 FROM {$wpdb->postmeta} 
                 WHERE post_id = p.ID 
                 AND meta_key = 'usage_count'
                 LIMIT 1), 
                0
            ) as usage_count
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
          ) <= 2
    ) as summary
");

echo "SUMMARY STATISTICS:\n";
echo "-------------------\n";
echo "Total matching coupons: " . number_format($summary->total) . "\n";
echo "Never used (0 uses):    " . number_format($summary->never_used) . " (" . round(($summary->never_used / $summary->total) * 100, 1) . "%)\n";
echo "Used once (1 use):      " . number_format($summary->used_once) . " (" . round(($summary->used_once / $summary->total) * 100, 1) . "%)\n";
echo "Used twice (2 uses):    " . number_format($summary->used_twice) . " (" . round(($summary->used_twice / $summary->total) * 100, 1) . "%)\n";
echo "Oldest coupon date:     " . $summary->oldest_date . "\n";
echo "Newest coupon date:     " . $summary->newest_date . "\n";
echo "\n";

// Get first 20 coupons as examples
echo "===================================================================\n";
echo "SAMPLE COUPONS (First 20)\n";
echo "===================================================================\n\n";

$coupons = $wpdb->get_results("
    SELECT 
        p.ID,
        p.post_title as code,
        DATE_FORMAT(p.post_date, '%Y-%m-%d') as created,
        TIMESTAMPDIFF(YEAR, p.post_date, NOW()) as age_years,
        COALESCE(
            (SELECT meta_value 
             FROM {$wpdb->postmeta} 
             WHERE post_id = p.ID 
             AND meta_key = 'usage_count' 
             LIMIT 1), 
            '0'
        ) as usage_count,
        (SELECT meta_value 
         FROM {$wpdb->postmeta} 
         WHERE post_id = p.ID 
         AND meta_key = 'discount_type' 
         LIMIT 1) as discount_type,
        (SELECT meta_value 
         FROM {$wpdb->postmeta} 
         WHERE post_id = p.ID 
         AND meta_key = 'coupon_amount' 
         LIMIT 1) as amount
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
      ) <= 2
    ORDER BY p.post_date ASC
    LIMIT 20
");

printf("%-8s %-30s %-12s %-5s %-6s %-15s %-10s\n", 
    "ID", "Code", "Created", "Age", "Uses", "Type", "Amount");
echo str_repeat("-", 100) . "\n";

foreach ($coupons as $coupon) {
    printf("%-8s %-30s %-12s %-5s %-6s %-15s %-10s\n",
        $coupon->ID,
        substr($coupon->code, 0, 30),
        $coupon->created,
        $coupon->age_years . "y",
        $coupon->usage_count,
        $coupon->discount_type ?: 'N/A',
        $coupon->amount ?: '0'
    );
}

echo "\n";
echo "Note: Showing first 20 of " . number_format($summary->total) . " total coupons.\n";
echo "===================================================================\n\n";

