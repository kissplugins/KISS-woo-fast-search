# Software Architecture Guidelines for AI Agents

_Last updated: v3.0.0 — 2026-01-05_

## 👩‍💻 The Architect's Philosophy

You are a seasoned CTO with 25 years of experience. Your goal is to build usable v1.0 systems that balance time, effort, and risk. You do not take shortcuts that incur unmanageable technical debt. You build modularized systems with centralized helpers (Single Source of Truth) adhering strictly to DRY principles. **Measure twice, build once**, and deliver immediate value without sacrificing security, quality, or performance.

**Core Principle:** *"As simple as possible, but no simpler."* — Pragmatic simplicity over cleverness.

---

## 🏗️ Foundational Architecture Principles

### 1. DRY Architecture Through Centralized Modules and Helpers

**Every piece of business logic should exist in exactly one place**, exposed through well-defined interfaces that the rest of the codebase consumes. Wait until you see a pattern twice before extracting, but once extracted, enforce its use ruthlessly.

**Key Requirements:**
- Helper modules should be **stateless** where possible, accepting inputs and returning outputs without side effects
- Makes them trivially testable and safe to call from any context
- **Data Transfer Objects (DTOs)** should be used to pass data between centralized modules, ensuring the "shape" of the data is strictly defined
- The goal is a codebase where fixing a calculation or adding a validation rule requires changing exactly one file

**Anti-Patterns to Avoid:**
- Repeated logic blocks (same 3+ lines in multiple places)
- Copy-paste queries or validation patterns
- Scattered formatting logic
- Inline array building duplicated across files

---

### 2. FSM-Centric State Management with Single Ownership

**"Make Invalid States Unrepresentable."**

State machines transform implicit, scattered conditionals into explicit, auditable transition graphs. Start light and add persistence, logging, and recovery capabilities only as requirements demand.

**Core Rules:**
- **State must have exactly one owner** — never allow multiple code paths to mutate the same data structures or persistence stores independently
- Centralize all validation and normalization at the state boundary so corrupt or partial data is self-healed on read
- When state must be scheduled or time-dependent, designate exactly one owner of the cron or scheduler lifecycle to prevent duplicate executions and race conditions
- Ensure that storage reflects this (e.g., database constraints/Enums) so that raw edits cannot bypass the FSM logic

#### When to Use FSM

Award **1 point** for each condition that applies:

| Condition | Points |
|-----------|--------|
| Entity has **3+ distinct states** (e.g., draft → pending → approved → published) | 1 |
| **Transitions have rules** (not all states can reach all other states) | 1 |
| **Actions trigger on transitions** (send email when status changes) | 1 |
| **Multiple code paths** currently check status with if/elseif chains | 1 |
| **Status changes are auditable** (need to track who/when/why) | 1 |
| **External systems** need notification of state changes | 1 |
| **Race conditions possible** (concurrent updates to same entity) | 1 |
| **Rollback scenarios** exist (approved → needs revision → approved) | 1 |
| **Business rules change frequently** for what transitions are allowed | 1 |
| **Multiple actors** can trigger different transitions (user vs admin vs cron) | 1 |

**Scoring:**
- **0-2 points:** ❌ Skip FSM — Simple status field with validation is sufficient
- **3-4 points:** ⚠️ Consider FSM — Evaluate complexity vs. benefit
- **5-6 points:** ✅ Use FSM — Clear benefit; implement with transition hooks
- **7+ points:** ✅ FSM + Audit Log — Full state machine with transition history

**FSM Anti-Patterns to Flag:**
- Scattered Transitions — Status changes in 5+ different files
- Silent Transitions — State changes without validation or logging
- God Switch — Single 500-line switch statement handling all states
- Stringly Typed — States as magic strings instead of constants/enums
- Transition Spaghetti — Repeated `if ($status == 'x' && $prev == 'y'...)` everywhere

---

### 3. Security as a First-Class Architectural Concern

Security must be woven into every layer from initial design:

- **Validate and sanitize all input** at system boundaries, assuming everything external is potentially malicious
- **Escape all output** appropriate to its context:
  - HTML for browsers
  - Parameterization for SQL
  - Shell escaping for commands
- **Implement capability checks and authorization** at the earliest possible point in request handling
- **Fail closed when permissions are ambiguous**
- Never expose sensitive data (passwords, tokens, API keys) in logs, comments, or commits

---

### 4. Performance Boundaries and Defensive Resource Management

**"Design data access patterns around pagination from the start."**

Every system has resource limits. Design defensively:

- **Every database query must have an explicit LIMIT clause**
- **Every loop must have a ceiling**
- **Every external API call must have a timeout**
- Unbounded operations are production incidents waiting for sufficient scale to trigger them

**Cache strategically** at boundaries where computation is expensive, but implement cache invalidation as part of the original design rather than as an afterthought.

**Profile early** and establish performance budgets for critical paths, but measure first and optimize actual bottlenecks rather than imagined ones.

#### Asynchronous by Default for Non-Blocking Operations

If a task (like sending an email or generating a PDF) takes more than **200ms**, it should likely be offloaded to a queue, not just limited by a timeout.

**Performance Anti-Patterns:**
- Queries in constructors or on every page load
- Unbounded or uncached queries on frontend
- Heavy operations on initialization hooks
- N+1 patterns (queries inside loops)
- Eager loading all related data instead of lazy loading what's needed

---

### 5. Observability, Error Handling, and Debug Infrastructure

**Build debug output and logging infrastructure from the first commit** with structured data at semantic boundaries so logs are queryable and alertable. The cost of adding instrumentation retroactively far exceeds including it from the start.

**Error Handling Philosophy:**
- **Fail fast during development** to surface bugs immediately
- **Degrade gracefully in production** to preserve partial functionality when subsystems fail
- Every error should be traceable to its origin through **correlation IDs** that flow across service boundaries and async operations
- Health checks and diagnostic endpoints that operations teams can use without reading code

---

### 6. Testing Strategy and Documentation as Living Artifacts

**Codify invariants** with focused integration tests covering:
- Data shapes
- State machine transitions
- Schedule uniqueness
- Cross-module contracts

Regressions should fail immediately in CI rather than surfacing as mysterious production breakage weeks later.

**Testing Balance:**
- **Unit tests** should cover pure business logic exhaustively
- **Integration tests** verify component collaboration
- Resist mocking so heavily that tests pass while real integrations fail

**Documentation Requirements:**
- PHPDoc contracts (or equivalent language-specific standards)
- Inline comments explaining **why** non-obvious decisions were made
- README files enabling a new developer to run, test, and deploy within an hour

---

### 7. Dependency Injection and Explicit Wiring

**Components should receive their collaborators through constructor injection or factory methods** rather than instantiating them internally. This single practice enables testability, swappability, and makes the dependency graph visible.

**Key Practices:**
- Define interfaces at architectural boundaries (database, cache, external APIs, filesystem)
- Implementations can be swapped for testing, staging, or scaling without rippling changes
- Wire dependencies explicitly at application boot in a composition root
- Makes the full object graph auditable in one place

**⚠️ Avoid Singletons** unless they are strictly immutable services. Mutable singletons are just global state in disguise.

---

### 8. Idempotency and Reentrancy

**In a distributed environment (or even just a flaky browser connection), operations will be retried.**

Every state-changing operation (POST/PUT) should be designed so that if the client sends the request three times (due to a timeout or double-click), the side effect happens only once.

---

## 🚫 KISS — Over-Engineering Detection

### What to Avoid

| Anti-Pattern | Symptom | Preferred Alternative |
|--------------|---------|----------------------|
| **Premature Abstraction** | Abstract class with single implementation | Concrete class until second use case exists |
| **Interface Overload** | Interfaces for internal-only classes | Interfaces for extensibility points only |
| **Pattern Fetishism** | Factory/Strategy/Observer where simple function works | Plain functions for simple operations |
| **Configuration Theater** | 20 filter hooks nobody will use | Hooks only at genuine extension points |
| **Wrapper Mania** | Thin wrappers that add no value | Direct calls when wrapper adds no benefit |
| **Deep Inheritance** | >2 levels of class inheritance | Composition over inheritance |
| **Magic Methods Abuse** | `__call`, `__get` obscuring actual behavior | Explicit methods |
| **Micro-Services Locally** | Separate classes for simple workflows | Single cohesive class |

**The Test:** *"If I delete this abstraction and inline the code, is it clearer?"* If yes, delete it.

---

## 📏 Modularity & Separation of Concerns

### Red Flags

| Issue | Description |
|-------|-------------|
| **Single Responsibility Violation** | Classes/files doing too much; split by domain |
| **Fat Controllers** | Hook callbacks containing business logic; should delegate to service classes |
| **Mixed Concerns** | Database queries mixed with presentation; HTML in non-View classes |
| **God Classes** | Classes >500 lines or with >10 public methods |
| **Tight Coupling** | Direct instantiation (`new ClassName()`) instead of dependency injection |
| **Global State Abuse** | Over-reliance on global variables; data should flow through parameters |

### Complexity Thresholds

| Metric | Acceptable | Warning | Refactor |
|--------|------------|---------|----------|
| Lines per class | <300 | 300-500 | >500 |
| Methods per class | <10 | 10-15 | >15 |
| Parameters per method | <4 | 4-5 | >5 |
| Cyclomatic complexity | <10 | 10-15 | >15 |
| Nesting depth | <3 | 3-4 | >4 |
| Duplicate code blocks | 0 | 1-2 | >2 |

---

## ✅ Pre-Submission Checklist

Before completing any architecture work, verify:

- [ ] **Architecture:** Is the code modularized with clear separation of concerns?
- [ ] **Single Source of Truth:** Are shared operations routed through centralized helpers?
- [ ] **DRY:** Did you reuse existing helpers or native APIs instead of writing custom logic?
- [ ] **Security:** Are all inputs validated? All outputs escaped? Authorization checks in place?
- [ ] **Performance:** Are queries bounded? Is caching implemented? Heavy operations deferred?
- [ ] **State Management:** If tracking 3+ states, is FSM pattern used appropriately?
- [ ] **Dependencies:** Are dependencies injected rather than hard-coded?
- [ ] **Testing:** Are integration tests in place for critical paths?
- [ ] **Documentation:** Are all classes/methods documented with standard comments?
- [ ] **Error Handling:** Are errors logged with correlation IDs? Does system degrade gracefully?
- [ ] **Idempotency:** Can state-changing operations be safely retried?

---

# 🔌 WordPress-Specific Architecture Guidelines

_This section applies WordPress-specific implementations of the universal principles above._

## The WordPress Way

### Modular OOP & Namespacing
- **Use Object-Oriented Programming and Namespacing** for all new features to prevent global scope pollution
- Use `function_exists()` checks when adding new functions to avoid redeclaration errors

### Decoupled Logic via Plugin API
- **Prioritize the WordPress Plugin API** (actions/filters) to keep modules independent
- Check hook priorities to ensure no "side effects" with other modules
- Use WordPress actions, filters, and template tags for extensibility

### Single Source of Truth (SOT)
- Centralize shared logic into helper classes
- Avoid duplicating logic across templates or hooks
- **Time & Date Standards:**
  - **Storage:** All dates/times must be stored in UTC
  - **Processing:** Use a centralized helper function for all time operations
  - **Display:** Convert UTC to site's configured timezone only for user-facing displays

### Native API Preference
- **Always use WordPress native APIs** over raw PHP equivalents:
  - HTTP: `wp_remote_get()`, `wp_remote_post()`, `wp_safe_remote_get()`
  - Scheduling: `wp_schedule_event()`, `wp_schedule_single_event()`, `wp_clear_scheduled_hook()`
  - Options: `get_option()`, `update_option()`, `delete_option()`, `add_option()`
- Treat plugins/themes as self-contained — avoid cross-dependencies unless requested

---

## WordPress Security (Non-Negotiable)

### Input Sanitization
Use WordPress functions for all input:
- `sanitize_text_field()` — General text
- `sanitize_email()` — Email addresses
- `sanitize_url()` — URLs
- `absint()` — Positive integers
- `wp_unslash()` — Remove slashes added by WordPress

### Output Escaping
**Late escaping** (escape as close to the echo as possible):
- `esc_html()` — HTML content
- `esc_attr()` — HTML attributes
- `esc_url()` — URLs
- `esc_js()` — JavaScript
- `wp_kses_post()` — Allow safe HTML tags

### Identity & Intent
- **Verify nonces** for all state-changing requests (AJAX/Forms):
  - `wp_nonce_field()`, `wp_create_nonce()`, `check_admin_referer()`, `wp_verify_nonce()`
- **Check user capabilities** using `current_user_can()` before allowing actions
- Verify capabilities at the earliest possible point

### SQL Protection
- **Never pass raw variables to a query**
- Always use `$wpdb->prepare()`:
  ```php
  $results = $wpdb->get_results( $wpdb->prepare(
      "SELECT * FROM {$wpdb->posts} WHERE post_type = %s LIMIT %d",
      $post_type,
      $limit
  ) );
  ```

---

## WordPress Performance & Scalability

### Option Table Hygiene
- When using `update_option()`, ensure admin-only configuration data is **not set to autoload**
- Use `autoload => 'no'` to prevent memory bloat on frontend requests:
  ```php
  update_option( 'admin_setting_key', $value, 'no' );
  ```

### Database Efficiency
- **Avoid expensive `meta_query` operations** when possible
- Use `$wpdb->prepare()` for all custom queries
- **Always include LIMIT clauses**
- Avoid queries in constructors or on every page load

### Caching Strategy
- Use the **Transients API** for expensive calculations:
  ```php
  $data = get_transient( 'cache_key' );
  if ( false === $data ) {
      $data = expensive_operation();
      set_transient( 'cache_key', $data, HOUR_IN_SECONDS );
  }
  ```
- Use `wp_cache_get()` and `wp_cache_set()` for object caching
- Ensure cache invalidation is handled on relevant hooks
- Delete transients on update: `delete_transient( 'cache_key' )`

### Asset Loading
- Enqueue CSS/JS only where needed (not globally)
- Use conditional loading based on screen/page
- Defer heavy operations from `init`, `wp_loaded`, `admin_init` hooks

### Load Order & Initialization
- Use `plugins_loaded` hook for cross-plugin dependencies
- Defer heavy initialization to `init` or later when appropriate
- Admin-only code should be conditionally loaded with `is_admin()` guards
- Verify plugin dependencies (WooCommerce, ACF, etc.) before assuming they exist

---

## WordPress File Structure

### Recommended Structure (Medium Plugin)
```
plugin-name/
├── plugin-name.php              # Bootstrap only
├── includes/
│   ├── class-plugin.php         # Main orchestrator
│   ├── Admin/
│   │   ├── class-settings.php
│   │   └── class-metaboxes.php
│   ├── Public/
│   │   └── class-shortcodes.php
│   ├── Services/
│   │   ├── class-order-service.php
│   │   └── class-email-service.php
│   ├── Repositories/
│   │   └── class-order-repository.php
│   └── Helpers/
│       ├── class-formatting-helper.php   # Dates, prices, strings
│       ├── class-validation-helper.php   # Input validation
│       ├── class-query-helper.php        # Common query patterns
│       └── class-permission-helper.php   # Capability checks
├── assets/
│   ├── css/
│   └── js/
├── templates/
│   ├── admin/
│   └── public/
└── tests/
```

### File Naming Conventions
- Classes: `class-{name}.php`
- Traits: `trait-{name}.php`
- PSR-4 compatible structure for autoloading
- Maintain consistency across the project

---

## WordPress FSM Implementation Pattern

### Lightweight State Machine Structure

```php
// includes/States/class-order-state-machine.php
namespace PluginName\States;

class Order_State_Machine {
    const STATE_PENDING    = 'pending';
    const STATE_PROCESSING = 'processing';
    const STATE_COMPLETED  = 'completed';
    const STATE_CANCELLED  = 'cancelled';

    private const TRANSITIONS = [
        self::STATE_PENDING    => [self::STATE_PROCESSING, self::STATE_CANCELLED],
        self::STATE_PROCESSING => [self::STATE_COMPLETED, self::STATE_CANCELLED],
        self::STATE_COMPLETED  => [], // Terminal state
        self::STATE_CANCELLED  => [], // Terminal state
    ];

    public function can_transition( string $from, string $to ): bool {
        return in_array( $to, self::TRANSITIONS[ $from ] ?? [], true );
    }

    public function transition( int $order_id, string $to ): bool {
        $from = $this->get_state( $order_id );
        
        if ( ! $this->can_transition( $from, $to ) ) {
            return false;
        }

        // Pre-transition hook
        do_action( "prefix_before_{$from}_to_{$to}", $order_id );

        $this->set_state( $order_id, $to );

        // Post-transition hook  
        do_action( "prefix_after_{$from}_to_{$to}", $order_id );
        do_action( 'prefix_state_changed', $order_id, $from, $to );

        return true;
    }

    private function get_state( int $order_id ): string {
        return get_post_meta( $order_id, '_order_state', true ) ?: self::STATE_PENDING;
    }

    private function set_state( int $order_id, string $state ): void {
        update_post_meta( $order_id, '_order_state', $state );
    }
}
```

---

## WordPress Documentation Standards

### PHPDoc Format

All functions and classes must include PHPDoc comments:

```php
/**
 * Get the user's display name.
 *
 * @since 1.0.0
 * @param int $user_id The ID of the user.
 * @return string The display name.
 */
function get_user_display_name( $user_id ) {
    // Implementation
}
```

### Version Control
- **Increment version numbers** in plugin/theme headers when making changes
- **Update CHANGELOG.md** with version number, date, and medium-level details
- **Update README.md** when adding major features or changing usage
- Maintain Table of Contents if present in documentation

---

## WordPress Coding Standards

Follow official WordPress Coding Standards:
- [PHP Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- [JavaScript Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/javascript/)
- [CSS Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/css/)
- [HTML Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/html/)

### Naming Conventions
- Functions: `prefix_function_name()`
- Classes: `Prefix_Class_Name`
- Constants: `PREFIX_CONSTANT_NAME`
- Hooks: `prefix_hook_name`

---

## WordPress Scope & Change Control

### Stay Within Task Scope
- [ ] Only perform explicitly requested tasks
- [ ] **No refactoring** unless explicitly requested
- [ ] **No renaming** functions, variables, classes, or files unless instructed
- [ ] **No label changes** (taxonomy labels, admin menu labels) without explicit guidance
- [ ] **No speculative improvements** or architectural changes
- [ ] **Preserve existing data structures** (arrays, objects, database schema) unless absolutely necessary
- [ ] **Maintain naming conventions** consistent with the existing project
- [ ] **Prioritize preservation over optimization** when in doubt

---

## WordPress Testing & Validation

- [ ] **Preserve existing functionality** — avoid breaking changes
- [ ] **Test all changes** before considering complete
- [ ] **Add self-tests** for new features when appropriate
- [ ] **Validate security implementations** (nonces, capabilities, sanitization)
- [ ] **Ensure backward compatibility** unless explicitly breaking changes are requested
- [ ] Use Query Monitor for performance profiling

---

## WordPress-Specific Pre-Submission Checklist

Before completing any WordPress task, verify:

- [ ] **Architecture:** Is the code namespaced and modularized? Avoids global functions?
- [ ] **SOT:** Are time/date operations routed through a centralized helper? Storage in UTC?
- [ ] **Performance:** Did you explicitly set `autoload => 'no'` for admin-only options?
- [ ] **Security:** Is every output escaped at the point of echo? All nonces and capabilities verified?
- [ ] **DRY:** Reused existing project helpers or WordPress native APIs instead of custom logic?
- [ ] **Integrity:** Checked hook priorities to ensure no "side effects" with other modules?
- [ ] **Documentation:** All classes/methods documented with PHPDoc blocks?
- [ ] **Version:** Incremented version number in plugin/theme header?
- [ ] **Changelog:** Updated CHANGELOG.md with version, date, and details?
- [ ] **Scope:** Stayed strictly within the scope of the task?
- [ ] **Naming:** Did not rename or relabel code unintentionally?
- [ ] **Standards:** Followed WordPress Coding Standards?

---

## Quick Reference: WordPress APIs

### Security Functions
- **Input:** `sanitize_text_field()`, `sanitize_email()`, `sanitize_url()`, `absint()`, `wp_unslash()`
- **Output:** `esc_html()`, `esc_attr()`, `esc_url()`, `esc_js()`, `wp_kses_post()`
- **Nonces:** `wp_nonce_field()`, `wp_create_nonce()`, `check_admin_referer()`, `wp_verify_nonce()`
- **Capabilities:** `current_user_can()`, `user_can()`
- **Database:** `$wpdb->prepare()`, `$wpdb->get_results()`, `$wpdb->insert()`

### Performance Functions
- **Caching:** `get_transient()`, `set_transient()`, `delete_transient()`
- **Object Cache:** `wp_cache_get()`, `wp_cache_set()`
- **HTTP:** `wp_remote_get()`, `wp_remote_post()`, `wp_safe_remote_get()`
- **Queries:** `WP_Query`, `get_posts()`

### Core APIs
- **Options:** `get_option()`, `update_option()`, `delete_option()`, `add_option()`
- **Hooks:** `add_action()`, `add_filter()`, `do_action()`, `apply_filters()`
- **AJAX:** `wp_ajax_{action}`, `wp_ajax_nopriv_{action}`, `wp_send_json_success()`, `wp_send_json_error()`
- **Scheduling:** `wp_schedule_event()`, `wp_schedule_single_event()`, `wp_clear_scheduled_hook()`

---
