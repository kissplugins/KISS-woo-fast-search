# State Machine Implementation

## Overview

The KISS Fast Order Search plugin now uses **explicit finite state machines (FSM)** for both the admin search page and the floating toolbar. This prevents impossible UI states and ensures consistent behavior across all search scenarios.

## Why State Machines?

### Problems Solved

1. **Impossible States**: Previously, the UI could be left in inconsistent states (e.g., "Searching..." text remaining after an error)
2. **Double Submissions**: Users could trigger multiple simultaneous searches
3. **Race Conditions**: Responses from old searches could overwrite newer results
4. **Unclear Error Recovery**: No clear path back to a working state after errors
5. **Debugging Difficulty**: Hard to understand what state the UI was in when bugs occurred

### Benefits

- ✅ **Predictable Behavior**: Only valid state transitions are allowed
- ✅ **Better Error Handling**: Clear recovery paths from error states
- ✅ **Prevents Double Submission**: Ignores duplicate requests while searching
- ✅ **Request Cancellation**: Aborts old requests when new ones start
- ✅ **Better Debugging**: State transitions are logged in debug mode
- ✅ **Improved UX**: Clear visual feedback for each state

## Admin Search Page State Machine

### States

| State | Description | UI State |
|-------|-------------|----------|
| `IDLE` | Ready for user input | Input enabled, button enabled, status empty |
| `SEARCHING` | AJAX request in progress | Input disabled, button disabled, status "Searching..." |
| `SUCCESS` | Results received and displayed | Input enabled, button enabled, results shown |
| `ERROR` | Request failed | Input enabled, button enabled, error message shown |
| `REDIRECTING` | Navigating to order page | Input disabled, button disabled, status "Redirecting..." |

### Valid Transitions

```
IDLE → SEARCHING
SEARCHING → SUCCESS | ERROR | REDIRECTING | IDLE (abort)
SUCCESS → IDLE | SEARCHING
ERROR → IDLE | SEARCHING
REDIRECTING → (page navigation)
```

### Implementation

Location: `admin/kiss-woo-admin.js`

Key features:
- Validates all state transitions before allowing them
- Logs invalid transitions in console (debug mode)
- Centralizes UI updates in `updateUIForState()`
- Prevents processing responses if state has changed
- Aborts old XHR requests when starting new searches

## Toolbar State Machine

### States

| State | Description | UI State |
|-------|-------------|----------|
| `IDLE` | Ready for user input | Input enabled, button shows original text |
| `SEARCHING` | AJAX request in progress (3s timeout) | Input disabled, button shows "Searching..." |
| `REDIRECTING_ORDER` | Navigating to order page | Input disabled, button shows "Opening order..." |
| `REDIRECTING_SEARCH` | Navigating to search page | Input disabled, button shows "Loading results..." |

### Valid Transitions

```
IDLE → SEARCHING
SEARCHING → REDIRECTING_ORDER | REDIRECTING_SEARCH | IDLE (abort)
REDIRECTING_ORDER → IDLE (timeout fallback) | (page navigation)
REDIRECTING_SEARCH → IDLE (timeout fallback) | (page navigation)
```

**Note**: Redirect states have a 5-second safety timeout that resets to IDLE if navigation is blocked (e.g., popup blocker).

### Implementation

Location: `admin/js/kiss-woo-toolbar.js`

Key features:
- Simpler state machine (only 4 states)
- 3-second timeout for AJAX requests
- Automatic fallback to search page on timeout/error
- Different button text for order vs search redirects
- Request abortion on duplicate submissions
- **5-second safety timeout** for redirect states (prevents stuck UI if navigation blocked)
- Automatic cleanup on successful page navigation

## Debugging

### Enable Debug Mode

Add to `wp-config.php`:
```php
define('KISS_WOO_FAST_SEARCH_DEBUG', true);
```

### Debug Output

With debug mode enabled, you'll see:
- State transitions logged to console: `🔄 State transition: idle → searching`
- Invalid transitions warned: `⚠️ Invalid state transition: searching → searching`
- Duplicate submission warnings: `⚠️ Search already in progress, ignoring duplicate submission`
- Response timing and state validation

## Testing

### Manual Testing Scenarios

1. **Normal Search Flow**
   - Enter search term → Submit → Verify "Searching..." → Verify results displayed
   - Expected: IDLE → SEARCHING → SUCCESS

2. **Order Number Redirect**
   - Enter order number → Submit → Verify "Redirecting to order..."
   - Expected: IDLE → SEARCHING → REDIRECTING

3. **Double Submission Prevention**
   - Submit search → Quickly submit again → Verify second request ignored
   - Expected: Warning in console, no duplicate request

4. **Error Recovery**
   - Trigger error (disconnect network) → Submit → Verify error message → Submit again
   - Expected: IDLE → SEARCHING → ERROR → SEARCHING → SUCCESS

5. **Request Abortion**
   - Submit slow search → Submit new search → Verify old request aborted
   - Expected: Old XHR aborted, new request starts

6. **Timeout Fallback (Toolbar Only)**
   - Block popups in browser → Submit search → Wait 5 seconds
   - Expected: UI resets to IDLE state after 5 seconds if navigation blocked

## Future Enhancements

Potential improvements:
- Add `TIMEOUT` state for explicit timeout handling
- Add retry logic with exponential backoff
- Add state persistence to localStorage for crash recovery
- Add telemetry for state transition analytics
- Add visual state indicator in debug mode

## Related Files

- `admin/kiss-woo-admin.js` - Admin page state machine
- `admin/js/kiss-woo-toolbar.js` - Toolbar state machine
- `includes/class-kiss-woo-ajax-handler.php` - Server-side AJAX handler
- `PROJECT/1-INBOX/AUDIT-SYSTEMATIC.md` - Original audit item 4.1

