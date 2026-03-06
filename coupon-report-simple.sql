-- Simple Coupon Analysis Report
-- Run with: mysql -u root -p wp_shop2binoidcbd < coupon-report-simple.sql

-- Set output format
SET @cutoff_date = DATE_SUB(NOW(), INTERVAL 2 YEAR);

-- Show report header
SELECT '===================================================================' as '';
SELECT 'COUPON ANALYSIS REPORT' as '';
SELECT 'Criteria: Usage count <= 2 AND Created > 2 years ago' as '';
SELECT '===================================================================' as '';
SELECT '' as '';

-- Main results (first 50)
SELECT 
    p.ID,
    p.post_title as coupon_code,
    DATE_FORMAT(p.post_date, '%Y-%m-%d') as created,
    TIMESTAMPDIFF(YEAR, p.post_date, NOW()) as age_yrs,
    COALESCE((SELECT meta_value FROM wp_postmeta WHERE post_id = p.ID AND meta_key = 'usage_count' LIMIT 1), '0') as uses,
    COALESCE((SELECT meta_value FROM wp_postmeta WHERE post_id = p.ID AND meta_key = 'discount_type' LIMIT 1), 'N/A') as type,
    COALESCE((SELECT meta_value FROM wp_postmeta WHERE post_id = p.ID AND meta_key = 'coupon_amount' LIMIT 1), '0') as amount
FROM wp_posts p
WHERE p.post_type = 'shop_coupon'
  AND p.post_date < @cutoff_date
  AND p.post_status NOT IN ('trash', 'auto-draft')
  AND COALESCE((SELECT CAST(meta_value AS UNSIGNED) FROM wp_postmeta WHERE post_id = p.ID AND meta_key = 'usage_count' LIMIT 1), 0) <= 2
ORDER BY p.post_date ASC
LIMIT 50;

SELECT '' as '';
SELECT '===================================================================' as '';
SELECT 'SUMMARY STATISTICS' as '';
SELECT '===================================================================' as '';

-- Summary
SELECT 
    COUNT(*) as total_coupons,
    SUM(CASE WHEN uses = 0 THEN 1 ELSE 0 END) as never_used,
    SUM(CASE WHEN uses = 1 THEN 1 ELSE 0 END) as used_once,
    SUM(CASE WHEN uses = 2 THEN 1 ELSE 0 END) as used_twice,
    MIN(created) as oldest_date,
    MAX(created) as newest_date
FROM (
    SELECT 
        p.post_date as created,
        COALESCE((SELECT CAST(meta_value AS UNSIGNED) FROM wp_postmeta WHERE post_id = p.ID AND meta_key = 'usage_count' LIMIT 1), 0) as uses
    FROM wp_posts p
    WHERE p.post_type = 'shop_coupon'
      AND p.post_date < @cutoff_date
      AND p.post_status NOT IN ('trash', 'auto-draft')
      AND COALESCE((SELECT CAST(meta_value AS UNSIGNED) FROM wp_postmeta WHERE post_id = p.ID AND meta_key = 'usage_count' LIMIT 1), 0) <= 2
) as summary;

SELECT '' as '';
SELECT 'Note: Showing first 50 coupons. Total count shown in summary.' as '';
SELECT '===================================================================' as '';

