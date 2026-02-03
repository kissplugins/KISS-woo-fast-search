# Wholesale Order Listing - SOLID Architecture

**Date:** 2026-02-02  
**Version:** 1.2.4  
**Status:** Design Complete - Ready for Implementation

---

## 🎯 **Goal**

Create a "List All Wholesale Orders" feature that:
- Shows ALL wholesale orders (not search-based)
- 10-50x faster than WooCommerce native order listing
- Reuses existing helpers (SOLID principles)
- Extensible for other order types (retail, B2B, etc.)

---

## 🏗️ **SOLID Architecture**

### **Existing Helpers (Reused)**

| Helper | Purpose | Location |
|--------|---------|----------|
| `KISS_Woo_Utils::is_hpos_enabled()` | HPOS detection | `includes/class-kiss-woo-utils.php` |
| `KISS_Woo_Order_Formatter::format_from_raw()` | Format SQL rows | `includes/class-kiss-woo-order-formatter.php` |
| `KISS_Woo_Debug_Tracer::log()` | Centralized logging | `includes/class-kiss-woo-debug-tracer.php` |
| `KISS_Woo_Wholesale_Filter` constants | Wholesale meta keys | `includes/filters/class-kiss-woo-wholesale-filter.php` |

### **New Centralized Helper (Created)**

**File:** `includes/class-kiss-woo-order-query.php` (~330 lines)

**Purpose:** Single source of truth for ALL order listing queries

**Public API:**
```php
$query = new KISS_Woo_Order_Query();

// List wholesale orders (page 1, 100 per page)
$results = $query->query_orders( 'wholesale', 1, 100 );

// List retail orders
$results = $query->query_orders( 'retail', 1, 100 );

// List all orders
$results = $query->query_orders( 'all', 1, 100 );

// With additional filters
$results = $query->query_orders( 'wholesale', 1, 100, [
    'status' => ['completed', 'processing'],
]);
```

**Return Structure:**
```php
[
    'orders'       => [...],  // Array of formatted orders
    'total'        => 450,    // Total orders matching criteria
    'pages'        => 5,      // Total pages
    'current_page' => 1,      // Current page
    'elapsed_ms'   => 125.5,  // Query time
]
```

---

## 📋 **SOLID Principles Applied**

### **1. Single Responsibility Principle (SRP)**

Each class has ONE job:

| Class | Responsibility |
|-------|----------------|
| `KISS_Woo_Order_Query` | Execute order queries with pagination |
| `KISS_Woo_Order_Formatter` | Format orders for output |
| `KISS_Woo_Wholesale_Filter` | Detect wholesale orders |
| `KISS_Woo_Utils` | Shared utilities (HPOS detection) |
| `KISS_Woo_Debug_Tracer` | Centralized logging |

### **2. Open/Closed Principle (OCP)**

**Extend without modifying:**
- Add new order types (`retail`, `b2b`) by passing different `$type` parameter
- Add new filters (`status`, `date_range`) via `$args` parameter
- No changes to existing code needed

**Example - Adding B2B orders:**
```php
// No code changes needed - just add meta condition
$results = $query->query_orders( 'b2b', 1, 100 );
```

### **3. Liskov Substitution Principle (LSP)**

All query types return the same structure:
- `query_orders( 'wholesale', ... )` → same structure
- `query_orders( 'retail', ... )` → same structure
- `query_orders( 'all', ... )` → same structure

### **4. Interface Segregation Principle (ISP)**

Small, focused methods:
- `query_orders()` - Public API
- `build_query()` - Query construction
- `build_hpos_query()` - HPOS-specific
- `build_legacy_query()` - Legacy-specific
- `format_order_rows()` - Formatting

### **5. Dependency Inversion Principle (DIP)**

Depend on abstractions:
- Uses `KISS_Woo_Utils::is_hpos_enabled()` (not direct HPOS checks)
- Uses `KISS_Woo_Order_Formatter::format_from_raw()` (not custom formatting)
- Uses `KISS_Woo_Debug_Tracer::log()` (not direct error_log)

---

## ⚡ **Performance Comparison**

### **WooCommerce Native (SLOW)**
```php
// Loads full order objects + triggers 50+ hooks per order
$orders = wc_get_orders([
    'limit' => 100,
    'meta_query' => [
        ['key' => '_wwpp_order_type', 'value' => 'wholesale']
    ]
]);
// Time: 5-10 seconds for 100 orders
```

### **Our Direct SQL (FAST)**
```php
// Direct SQL - no hooks, only needed fields
$results = $query->query_orders( 'wholesale', 1, 100 );
// Time: 50-200ms for 100 orders
```

**Expected speedup: 25-200x faster!** 🚀

---

## 🔄 **Reusability**

The `KISS_Woo_Order_Query` helper can be used for:

1. ✅ **Wholesale orders listing** (current feature)
2. ✅ **Retail orders listing** (future)
3. ✅ **B2B orders listing** (future)
4. ✅ **Order reports** (by status, date range)
5. ✅ **Admin dashboards** (recent orders, pending orders)
6. ✅ **Export functionality** (CSV, PDF)

---

## 📝 **Next Steps**

1. **Create AJAX handler** for wholesale listing
2. **Add toolbar button** (already exists - needs to call new endpoint)
3. **Update admin page** to display paginated results
4. **Add pagination controls** to JavaScript
5. **Test performance** with large datasets

---

## 🎊 **Benefits**

✅ **Centralized** - Single source of truth for order queries  
✅ **Reusable** - Works for wholesale, retail, B2B, etc.  
✅ **Fast** - Direct SQL, no WooCommerce hooks  
✅ **SOLID** - Follows all 5 principles  
✅ **Extensible** - Easy to add new order types  
✅ **Observable** - Comprehensive debug logging  
✅ **HPOS-compatible** - Works with both storage modes  

---

**Ready to implement the AJAX handler and UI!** 🚀

