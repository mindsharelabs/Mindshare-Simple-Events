# Column Alignment Fix

## Issue

The attendee list table headers were not aligned properly with their corresponding data columns. The user reported that "all the headers are not aligned properly or over the correct column."

## Required Column Order

The user specified the correct column order should be:

1. Order ID
2. Status
3. Attendee
4. Product
5. Check In
6. Membership
7. Safety Waiver
8. Badges
9. Badge Attendee

## Root Cause

There were three critical issues causing the column misalignment:

1. **Column Order**: The Make Member Plugin was adding columns to the attendee table through the `mindevents_attendee_columns` filter, but the columns were being appended in the wrong order.

2. **Duplicate Badge Columns**: The Events plugin was adding its own `'badges' => 'Badges'` column (line 324 in admin.class.php) when it detected a badge class event, while the Make Member Plugin was also adding badge-related columns. This created duplicate "Badges" headers and misaligned data.

3. **Data Key Mismatch**: The Events plugin was creating data with keys that didn't match the column keys. For example, the column was named `check_in` but the data key was `checked_in`. Additionally, extra data keys (`event_id`, `akey`, `sub_event`) were being included in the table output, causing columns to shift.

## Solution

Fixed the column alignment by:

1. **Modified Column Filter** - Updated the `mindevents_attendee_columns` filter to insert columns in the correct order after the 'check_in' column:

   ```php
   add_filter('mindevents_attendee_columns', function($columns) {
       // Insert columns in the correct order after 'check_in'
       $new_columns = array();

       foreach ($columns as $key => $value) {
           $new_columns[$key] = $value;

           // After 'check_in', add our custom columns in the correct order
           if ($key === 'check_in') {
               $new_columns['is-member'] = 'Membership';
               $new_columns['safety-waiver'] = 'Safety Waiver';
               $new_columns['make-badges'] = 'Badges';
               $new_columns['badge-attendee'] = 'Badge Attendee';
           }
       }

       return $new_columns;
   });
   ```

2. **Removed Duplicate Badge Column** - Removed the Events plugin's own badge column that was being added on line 324 of `admin.class.php` to prevent duplicate "Badges" headers.

3. **Fixed Data Key Alignment** - Updated the data structure to match column keys:

   - Changed `checked_in` data key to `check_in` to match the column header
   - Moved context data (`event_id`, `akey`, `sub_event`) to the end so they don't interfere with column alignment
   - Modified the table output loop to only process columns that match the defined headers

4. **Separated Badge Display from Action Button** - Split the badge management functionality into two separate columns:

   - `make-badges`: Shows user's current badges (read-only display)
   - `badge-attendee`: Shows the action button for awarding/removing badges

5. **Created New Functions**:
   - `make_get_user_badges_display()`: Returns formatted list of user's current badges
   - `make_get_badge_action_button()`: Returns the badge management action button

## Files Modified

- `inc/woocommerce.php`: Updated column filters and separated badge display logic
- `../Mindshare-Simple-Events/inc/admin.class.php`: Removed duplicate badge column creation

## Result

The attendee table now displays columns in the correct order:

- Order ID, Status, Attendee, Product, Check In, Membership, Safety Waiver, Badges, Badge Attendee

The badge awarding functionality continues to work as expected, but now the table headers are properly aligned with their data columns.

## Testing

- ✅ Column headers align with data
- ✅ Badge awarding functionality still works
- ✅ Proper separation between badge display and action button
- ✅ Maintains all existing functionality while fixing alignment
