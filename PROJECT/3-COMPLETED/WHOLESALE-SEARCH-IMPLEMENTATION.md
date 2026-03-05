# Wholesale Orders Search - Implementation Plan

## Overview

Add "Search Wholesale Orders Only" button to sticky toolbar that filters search results to show only wholesale orders.

---

## Requirements

1. ✅ **Add button to sticky persistent toolbar** (toolbar.php)
2. ✅ **Reuse existing results page** (admin/class-kiss-woo-admin-page.php)
3. ✅ **Add pagination** (currently missing - need to implement)
4. ✅ **Follow SOLID principles** (Open/Closed, Single Responsibility)
5. ✅ **Minimal new code** (~150 lines total)

---

## Architecture (SOLID Principles)

### **Single Responsibility Principle (SRP)**
- `KISS_Woo_Search` - Handles search logic only
- `KISS_Woo_Ajax_Handler` - Handles AJAX requests only
- `KISS_Woo_Order_Filter` - NEW: Handles order filtering logic (wholesale, retail, etc.)
- Admin page - Handles UI rendering only

### **Open/Closed Principle (OCP)**
- Extend search functionality WITHOUT modifying existing search_customers() method
- Add new `apply_order_filters()` method that can be extended for future filter types
- Use filter parameter pattern (wholesale_only, retail_only, etc.)

### **Dependency Inversion Principle (DIP)**
- Search class depends on abstract filter interface, not concrete implementations
- Filter detection logic is pluggable (supports multiple wholesale plugins)

---

## Implementation Steps

### **Step 1: Add Wholesale Filter Interface (SOLID - OCP)**

Create `includes/interface-kiss-woo-order-filter.php`:
```php
interface KISS_Woo_Order_Filter {
    public function apply( array $order_ids ): array;
    public function get_filter_name(): string;
}
```

### **Step 2: Create Wholesale Filter Class (SOLID - SRP)**

Create `includes/filters/class-kiss-woo-wholesale-filter.php`:
```php
class KISS_Woo_Wholesale_Filter implements KISS_Woo_Order_Filter {
    public function apply( array $order_ids ): array {
        // Filter order_ids to only wholesale orders
        // Check user roles AND order meta
    }
}
```

### **Step 3: Extend Search Class (SOLID - OCP)**

Modify `includes/class-kiss-woo-search.php`:
```php
public function search_customers( $term, $filters = array() ) {
    // Existing search logic...
    
    // NEW: Apply filters if provided
    if ( ! empty( $filters ) ) {
        $results = $this->apply_filters_to_results( $results, $filters );
    }
    
    return $results;
}

private function apply_filters_to_results( $results, $filters ) {
    // Apply each filter to results
    foreach ( $filters as $filter ) {
        if ( $filter instanceof KISS_Woo_Order_Filter ) {
            $results = $filter->apply( $results );
        }
    }
    return $results;
}
```

### **Step 4: Update AJAX Handler (SOLID - SRP)**

Modify `includes/class-kiss-woo-ajax-handler.php`:
```php
private function perform_search( string $term ): array {
    $search = new KISS_Woo_COS_Search();
    
    // NEW: Check for filter parameters
    $filters = $this->get_filters_from_request();
    
    // Pass filters to search
    $customers = $search->search_customers( $term, $filters );
    
    // ... rest of existing logic
}

private function get_filters_from_request(): array {
    $filters = array();
    
    if ( isset( $_POST['wholesale_only'] ) && $_POST['wholesale_only'] === '1' ) {
        $filters[] = new KISS_Woo_Wholesale_Filter();
    }
    
    return $filters;
}
```

### **Step 5: Add Wholesale Button to Toolbar**

Modify `toolbar.php`:
```php
<button type="button" id="floating-search-wholesale" class="floating-search-wholesale">
    <?php esc_html_e( 'Search Wholesale Orders Only', 'kiss-woo-customer-order-search' ); ?>
</button>
```

### **Step 6: Update Toolbar JavaScript**

Modify `admin/js/kiss-woo-toolbar.js`:
```javascript
$('#floating-search-wholesale').on('click', function() {
    var searchTerm = $('#floating-search-input').val().trim();
    if (searchTerm.length < 2) {
        alert('Please enter at least 2 characters');
        return;
    }
    
    // Redirect to admin page with wholesale filter
    var url = floatingSearchBar.searchUrl + '&q=' + encodeURIComponent(searchTerm) + '&wholesale_only=1';
    window.location.href = url;
});
```

### **Step 7: Update Admin Page to Handle Filter Parameter**

Modify `admin/class-kiss-woo-admin-page.php`:
```php
public function render_page() {
    // Check for wholesale_only parameter
    $wholesale_only = isset( $_GET['wholesale_only'] ) && $_GET['wholesale_only'] === '1';
    $search_term = isset( $_GET['q'] ) ? sanitize_text_field( $_GET['q'] ) : '';
    
    // ... existing HTML ...
    
    // Pass wholesale_only to JavaScript
    wp_localize_script(
        'kiss-woo-cos-admin',
        'KISSCOS',
        array(
            // ... existing localization ...
            'wholesale_only' => $wholesale_only,
            'initial_search' => $search_term,
        )
    );
}
```

### **Step 8: Update Admin JavaScript to Auto-Search**

Modify `admin/kiss-woo-admin.js`:
```javascript
// Auto-run search if initial_search is provided
if (KISSCOS.initial_search && KISSCOS.initial_search.length >= 2) {
    $input.val(KISSCOS.initial_search);
    $form.trigger('submit');
}

// Include wholesale_only in AJAX request
data: {
    action: 'kiss_woo_customer_search',
    nonce: KISSCOS.nonce,
    q: q,
    wholesale_only: KISSCOS.wholesale_only ? '1' : '0'
}
```

### **Step 9: Add Pagination (NEW)**

Add to `admin/kiss-woo-admin.js`:
```javascript
function renderPagination(totalResults, currentPage, perPage) {
    var totalPages = Math.ceil(totalResults / perPage);
    if (totalPages <= 1) return '';
    
    var html = '<div class="kiss-pagination">';
    for (var i = 1; i <= totalPages; i++) {
        html += '<button class="kiss-page-btn' + (i === currentPage ? ' active' : '') + '" data-page="' + i + '">' + i + '</button>';
    }
    html += '</div>';
    return html;
}
```

---

## Wholesale Detection Logic

### **User Role Detection**
Check if customer has wholesale role:
- `wholesale_customer`
- `wholesale_lead`
- `wwpp_wholesale_customer`

### **Order Meta Detection**
Check order meta keys:
- `_wwpp_order_type` = 'wholesale'
- `_wholesale_order` = 'yes'
- `_is_wholesale_order` = '1'

### **Fallback Detection**
If no meta found, check if customer user has wholesale role

---

## File Changes Summary

| File | Lines Changed | Type | Purpose |
|------|---------------|------|---------|
| `includes/interface-kiss-woo-order-filter.php` | +15 | NEW | Filter interface (SOLID - DIP) |
| `includes/filters/class-kiss-woo-wholesale-filter.php` | +80 | NEW | Wholesale filter implementation |
| `includes/class-kiss-woo-search.php` | +25 | MODIFY | Add filter support (SOLID - OCP) |
| `includes/class-kiss-woo-ajax-handler.php` | +20 | MODIFY | Pass filters to search |
| `admin/class-kiss-woo-admin-page.php` | +10 | MODIFY | Handle wholesale_only parameter |
| `admin/kiss-woo-admin.js` | +40 | MODIFY | Auto-search + pagination |
| `toolbar.php` | +5 | MODIFY | Add wholesale button |
| `admin/js/kiss-woo-toolbar.js` | +15 | MODIFY | Handle wholesale button click |
| `admin/css/kiss-woo-toolbar.css` | +20 | MODIFY | Style wholesale button |
| **TOTAL** | **+230** | | |

---

## Testing Checklist

- [ ] Wholesale button appears in toolbar
- [ ] Clicking wholesale button redirects to admin page with filter
- [ ] Admin page auto-searches with wholesale filter
- [ ] Results show only wholesale orders
- [ ] Pagination works correctly
- [ ] Regular search still works (no regression)
- [ ] Performance is comparable to regular search
- [ ] Works with HPOS enabled
- [ ] Works with HPOS disabled

---

## Performance Expectations

- **Regular search**: 100-200ms
- **Wholesale search**: 120-250ms (+20-50ms for filtering)
- **WooCommerce native**: 5-10 seconds

**Expected improvement**: 20-40x faster than WooCommerce native wholesale search

---

## Next Steps

1. Implement interface and wholesale filter class
2. Extend search class with filter support
3. Update AJAX handler
4. Add toolbar button
5. Update admin page
6. Add pagination
7. Test thoroughly
8. Update changelog
9. Increment version to 1.2.4

