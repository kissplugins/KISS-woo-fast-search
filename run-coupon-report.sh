#!/bin/bash
# Coupon Analysis Report Runner
# Finds coupons with usage_count <= 2 AND created more than 2 years ago

echo "==================================================================="
echo "COUPON ANALYSIS REPORT"
echo "Criteria: Usage count <= 2 AND Created > 2 years ago"
echo "==================================================================="
echo ""

# Navigate to WordPress root
cd /Users/noelsaw/Local\ Sites/bloomz-prod-08-15/app/public

# Run the analysis query
wp db query "
SET @cutoff_date = DATE_SUB(NOW(), INTERVAL 2 YEAR);

SELECT 
    p.ID as coupon_id,
    p.post_title as coupon_code,
    DATE_FORMAT(p.post_date, '%Y-%m-%d') as created_date,
    p.post_status as status,
    TIMESTAMPDIFF(YEAR, p.post_date, NOW()) as age_years,
    COALESCE(
        (SELECT meta_value 
         FROM wp_postmeta 
         WHERE post_id = p.ID 
         AND meta_key = 'usage_count' 
         LIMIT 1), 
        '0'
    ) as usage_count,
    (SELECT meta_value 
     FROM wp_postmeta 
     WHERE post_id = p.ID 
     AND meta_key = 'discount_type' 
     LIMIT 1) as discount_type,
    (SELECT meta_value 
     FROM wp_postmeta 
     WHERE post_id = p.ID 
     AND meta_key = 'coupon_amount' 
     LIMIT 1) as amount
FROM wp_posts p
WHERE p.post_type = 'shop_coupon'
  AND p.post_date < @cutoff_date
  AND p.post_status NOT IN ('trash', 'auto-draft')
  AND COALESCE(
      (SELECT CAST(meta_value AS UNSIGNED)
       FROM wp_postmeta 
       WHERE post_id = p.ID 
       AND meta_key = 'usage_count' 
       LIMIT 1), 
      0
  ) <= 2
ORDER BY p.post_date ASC
LIMIT 100;
" --skip-column-names

echo ""
echo "==================================================================="
echo "SUMMARY STATISTICS"
echo "==================================================================="

wp db query "
SET @cutoff_date = DATE_SUB(NOW(), INTERVAL 2 YEAR);

SELECT 
    COUNT(*) as total_matching_coupons,
    SUM(CASE WHEN usage_count = 0 THEN 1 ELSE 0 END) as never_used,
    SUM(CASE WHEN usage_count = 1 THEN 1 ELSE 0 END) as used_once,
    SUM(CASE WHEN usage_count = 2 THEN 1 ELSE 0 END) as used_twice
FROM (
    SELECT 
        COALESCE(
            (SELECT CAST(meta_value AS UNSIGNED)
             FROM wp_postmeta 
             WHERE post_id = p.ID 
             AND meta_key = 'usage_count' 
             LIMIT 1), 
            0
        ) as usage_count
    FROM wp_posts p
    WHERE p.post_type = 'shop_coupon'
      AND p.post_date < @cutoff_date
      AND p.post_status NOT IN ('trash', 'auto-draft')
      AND COALESCE(
          (SELECT CAST(meta_value AS UNSIGNED)
           FROM wp_postmeta 
           WHERE post_id = p.ID 
           AND meta_key = 'usage_count' 
           LIMIT 1), 
          0
      ) <= 2
) as summary;
"

echo ""
echo "Note: Showing first 100 results. Remove LIMIT clause to see all."
echo "==================================================================="

