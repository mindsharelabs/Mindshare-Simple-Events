# Badge Column Conflict Resolution

## Issue Identified

The **Make Member Plugin** (`make-member-plugin-main/inc/woocommerce.php`) is adding a badge column to the attendees table using the same filter hook as our Events plugin, causing duplicate badge columns.

## Conflicting Code

### Make Member Plugin (Line 3-9)

```php
add_filter('mindevents_attendee_columns', function($columns) {
    $columns['is-member'] = 'Membership';
    $columns['make-badges'] = 'Badges';  // ← This creates the duplicate
    $columns['safety-waiver'] = 'Safety Waiver';
    return $columns;
});
```

### Our Events Plugin

```php
// In inc/admin.class.php
$columns['badges'] = 'Badges';  // ← Our badge management column
```

## Resolution Options

### Option 1: Modify Make Member Plugin (Recommended)

Remove the badge column from the Make Member Plugin since our Events plugin provides comprehensive badge management.

**File to modify:** `make-member-plugin-main/inc/woocommerce.php`

**Change lines 3-9 from:**

```php
add_filter('mindevents_attendee_columns', function($columns) {
    $columns['is-member'] = 'Membership';
    $columns['make-badges'] = 'Badges';
    $columns['safety-waiver'] = 'Safety Waiver';
    return $columns;
});
```

**To:**

```php
add_filter('mindevents_attendee_columns', function($columns) {
    $columns['is-member'] = 'Membership';
    // Removed 'make-badges' column - handled by Events plugin
    $columns['safety-waiver'] = 'Safety Waiver';
    return $columns;
});
```

**Also remove the corresponding data handler (lines 30-41):**

```php
// Remove this entire section:
$user_badges = get_field('certifications', 'user_' . $data['user_id']);
if($user_badges) :
    foreach($user_badges as $badge) :
        $badges .= '<small class="small">' . get_the_title($badge) . '</small>';
        if(next($user_badges)) :
            $badges .= ', ';
        endif;
    endforeach;
else :
    $badges = '<small class="badge badge-danger">No Badges</small>';
endif;
$data['make-badges'] = $badges;
```

### Option 2: Modify Our Events Plugin

Add detection for the Make Member Plugin and use a different approach.

**File to modify:** `inc/admin.class.php`

Add this check in the `display_attendees_metabox()` method:

```php
// Check if Make Member Plugin is adding badge column
$make_member_active = class_exists('makeMember');
if (!$make_member_active) {
    $columns['badges'] = 'Badges';
}
```

## Why Option 1 is Recommended

1. **Comprehensive Functionality**: Our Events plugin provides full badge management (view, award, remove)
2. **Direct Integration**: Works directly with the existing certificate system
3. **Better UX**: Single badge column with actionable buttons vs. read-only display
4. **Unified System**: Maintains consistency with the certificate management approach

## Implementation Status

- ✅ Issue identified and documented
- ⏳ Awaiting decision on which option to implement
- ⏳ Code changes to be applied
- ⏳ Testing required after implementation

## Files Involved

- `make-member-plugin-main/inc/woocommerce.php` (Make Member Plugin)
- `inc/admin.class.php` (Our Events Plugin)
- `@docs/badge-management.md` (Documentation)
