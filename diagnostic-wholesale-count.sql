-- Diagnostic SQL to verify wholesale order count
-- Run this in your database to check actual wholesale orders

-- 1. Total orders in wp_posts (legacy mode)
SELECT COUNT(*) as total_orders
FROM wp_posts
WHERE post_type = 'shop_order';

-- 2. Orders with wholesale meta keys
SELECT 
    pm.meta_key,
    pm.meta_value,
    COUNT(DISTINCT p.ID) as order_count
FROM wp_posts p
INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
WHERE p.post_type = 'shop_order'
AND pm.meta_key IN ('_wwpp_order_type', '_wholesale_order', '_is_wholesale_order', '_wwp_wholesale_order')
GROUP BY pm.meta_key, pm.meta_value
ORDER BY pm.meta_key, pm.meta_value;

-- 3. Count orders with ANY wholesale meta key matching wholesale values
SELECT COUNT(DISTINCT p.ID) as wholesale_orders_count
FROM wp_posts p
INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
WHERE p.post_type = 'shop_order'
AND pm.meta_key IN ('_wwpp_order_type', '_wholesale_order', '_is_wholesale_order', '_wwp_wholesale_order')
AND pm.meta_value IN ('wholesale', 'yes', '1');

-- 4. Sample wholesale orders (first 10)
SELECT 
    p.ID,
    p.post_status,
    p.post_date,
    pm.meta_key,
    pm.meta_value
FROM wp_posts p
INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
WHERE p.post_type = 'shop_order'
AND pm.meta_key IN ('_wwpp_order_type', '_wholesale_order', '_is_wholesale_order', '_wwp_wholesale_order')
AND pm.meta_value IN ('wholesale', 'yes', '1')
ORDER BY p.post_date DESC
LIMIT 10;

-- 5. Check for customer_user meta (wholesale user IDs)
SELECT COUNT(DISTINCT p.ID) as orders_by_wholesale_users
FROM wp_posts p
INNER JOIN wp_postmeta pm_customer ON p.ID = pm_customer.post_id AND pm_customer.meta_key = '_customer_user'
INNER JOIN wp_usermeta um ON pm_customer.meta_value = um.user_id
WHERE p.post_type = 'shop_order'
AND um.meta_key = 'wp_capabilities'
AND (
    um.meta_value LIKE '%wholesale_customer%'
    OR um.meta_value LIKE '%wholesale_lead%'
    OR um.meta_value LIKE '%wwpp_wholesale_customer%'
    OR um.meta_value LIKE '%wws_wholesale_customer%'
);

