-- Coupon Analysis Report
-- Find coupons with usage_count <= 2 AND created more than 2 years ago
-- 
-- Run this query against your WordPress database after importing bloomz-coupons.sql
-- Usage: wp db query < coupon-analysis.sql

-- Set the cutoff date (2 years ago from today)
SET @cutoff_date = DATE_SUB(NOW(), INTERVAL 2 YEAR);

-- Main query: Find low-usage old coupons
SELECT 
    p.ID as coupon_id,
    p.post_title as coupon_code,
    p.post_date as created_date,
    p.post_status as status,
    TIMESTAMPDIFF(YEAR, p.post_date, NOW()) as age_years,
    TIMESTAMPDIFF(MONTH, p.post_date, NOW()) as age_months,
    
    -- Get usage count from postmeta
    COALESCE(
        (SELECT meta_value 
         FROM wp_postmeta 
         WHERE post_id = p.ID 
         AND meta_key = 'usage_count' 
         LIMIT 1), 
        '0'
    ) as usage_count,
    
    -- Get discount type
    (SELECT meta_value 
     FROM wp_postmeta 
     WHERE post_id = p.ID 
     AND meta_key = 'discount_type' 
     LIMIT 1) as discount_type,
    
    -- Get coupon amount
    (SELECT meta_value 
     FROM wp_postmeta 
     WHERE post_id = p.ID 
     AND meta_key = 'coupon_amount' 
     LIMIT 1) as coupon_amount,
    
    -- Get expiry date
    (SELECT FROM_UNIXTIME(meta_value)
     FROM wp_postmeta 
     WHERE post_id = p.ID 
     AND meta_key = 'date_expires' 
     AND meta_value != ''
     LIMIT 1) as expiry_date,
    
    -- Get usage limit
    (SELECT meta_value 
     FROM wp_postmeta 
     WHERE post_id = p.ID 
     AND meta_key = 'usage_limit' 
     LIMIT 1) as usage_limit

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
ORDER BY p.post_date ASC;

-- Summary statistics
SELECT 
    '=== SUMMARY STATISTICS ===' as report_section;

SELECT 
    COUNT(*) as total_low_usage_old_coupons,
    SUM(CASE WHEN usage_count = 0 THEN 1 ELSE 0 END) as never_used,
    SUM(CASE WHEN usage_count = 1 THEN 1 ELSE 0 END) as used_once,
    SUM(CASE WHEN usage_count = 2 THEN 1 ELSE 0 END) as used_twice,
    MIN(created_date) as oldest_coupon_date,
    MAX(created_date) as newest_in_set_date
FROM (
    SELECT 
        p.ID,
        p.post_date as created_date,
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

-- Breakdown by year
SELECT 
    '=== BREAKDOWN BY YEAR ===' as report_section;

SELECT 
    YEAR(p.post_date) as coupon_year,
    COUNT(*) as count,
    SUM(CASE WHEN usage_count = 0 THEN 1 ELSE 0 END) as never_used,
    SUM(CASE WHEN usage_count = 1 THEN 1 ELSE 0 END) as used_once,
    SUM(CASE WHEN usage_count = 2 THEN 1 ELSE 0 END) as used_twice
FROM (
    SELECT 
        p.ID,
        p.post_date,
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
) as yearly
GROUP BY YEAR(p.post_date)
ORDER BY coupon_year ASC;

