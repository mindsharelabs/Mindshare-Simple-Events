# Instructor Payment Tracking

## Overview

The Instructor Payment Tracking feature allows administrators to mark instructors as paid for past events, track payment amounts, dates, and notes. This feature integrates with the existing analytics settings to auto-calculate payment amounts based on event categories (Badge Classes vs Workshops).

## Features

### Individual Payment Management

- **Mark Individual Events as Paid**: Each past event can be individually marked as paid
- **Payment Form**: Inline forms for unpaid events with fields for:
  - Amount (auto-filled from analytics settings)
  - Date paid (defaults to current date)
  - Notes (optional)
- **Payment Status Display**: Visual indicators showing paid/unpaid status
- **Payment Details**: Shows amount, date paid, and notes for completed payments

### Bulk Payment Management

- **Bulk Selection**: Checkbox system to select multiple events
- **Select All**: Master checkbox to select/deselect all events on current page
- **Bulk Payment Form**: Appears when events are selected with fields for:
  - Amount (optional - uses auto-calculated if empty)
  - Date paid (defaults to current date)
  - Notes (optional for all selected events)
- **Bulk Actions**: Process multiple payments simultaneously

### Auto-Calculation System

- **Badge Classes**: Events with "Badge" in category name pay $160 per instructor (configurable)
- **Workshop Classes**: All other events pay $140 per instructor (configurable)
- **Analytics Settings Integration**: Payment rates pulled from Analytics Settings page
- **Override Capability**: Manual amount entry overrides auto-calculation

### Filtering and Search

- **Payment Status Filter**: Filter events by paid/unpaid status
- **Combined Filtering**: Works with existing date, instructor, and attendee filters
- **Persistent Filters**: All filter combinations maintained across page navigation

## Technical Implementation

### Database Schema

Payment information is stored as post meta fields on sub_event posts:

```php
// Meta fields added to sub_event posts
'instructor_payment_status' => 'paid' | '' (empty = unpaid)
'instructor_payment_amount' => float (payment amount)
'instructor_payment_date' => 'Y-m-d' (date paid)
'instructor_payment_notes' => string (optional notes)
```

### Payment Calculation Logic

```php
// Auto-calculation based on event categories
$analytics_settings = get_option('mindevents_analytics_settings', array(
    'badge_rate' => 160,
    'workshop_rate' => 140
));

$event_categories = wp_get_post_terms($parent_id, 'event_category', array('fields' => 'names'));
$is_badge = false;
if (!empty($event_categories)) {
    foreach ($event_categories as $category) {
        if (stripos($category, 'badge') !== false) {
            $is_badge = true;
            break;
        }
    }
}
$payment_amount = $is_badge ? $analytics_settings['badge_rate'] : $analytics_settings['workshop_rate'];
```

### Form Processing

#### Individual Payment Processing

```php
private function handle_individual_payment_action($event_id, $post_data) {
    $event_id = intval($event_id);
    $amount = isset($post_data['amount']) ? floatval($post_data['amount']) : 0;
    $date_paid = isset($post_data['date_paid']) ? sanitize_text_field($post_data['date_paid']) : date('Y-m-d');
    $notes = isset($post_data['notes']) ? sanitize_text_field($post_data['notes']) : '';

    // Auto-calculate if amount not provided
    if ($amount <= 0) {
        // ... calculation logic
    }

    update_post_meta($event_id, 'instructor_payment_status', 'paid');
    update_post_meta($event_id, 'instructor_payment_amount', $amount);
    update_post_meta($event_id, 'instructor_payment_date', $date_paid);
    if ($notes) {
        update_post_meta($event_id, 'instructor_payment_notes', $notes);
    }
}
```

#### Bulk Payment Processing

```php
private function handle_bulk_payment_action($event_ids, $post_data) {
    if (!is_array($event_ids)) return;

    $amount = isset($post_data['amount']) ? floatval($post_data['amount']) : 0;
    $date_paid = isset($post_data['date_paid']) ? sanitize_text_field($post_data['date_paid']) : date('Y-m-d');
    $notes = isset($post_data['notes']) ? sanitize_text_field($post_data['notes']) : '';

    foreach ($event_ids as $event_id) {
        // Process each event with auto-calculation if needed
        // ... processing logic
    }
}
```

## User Interface

### Past Events Table Enhancements

#### New Columns Added

1. **Checkbox Column**: For bulk selection
2. **Payment Status Column**: Visual paid/unpaid indicators
3. **Payment Details Column**: Shows payment information or payment form

#### Visual Indicators

- **✓ Paid**: Green checkmark for paid events
- **✗ Unpaid**: Red X for unpaid events
- **Payment Forms**: Inline forms for unpaid events
- **Payment Details**: Amount, date, and notes display for paid events

### Bulk Actions Interface

```html
<!-- Bulk Payment Form (hidden by default) -->
<div class="bulk-payment-form" id="bulk-payment-form">
  <h4>Bulk Mark as Paid</h4>
  <form method="post">
    <!-- Hidden inputs for selected events -->
    <div class="form-row">
      <label>Amount ($):</label>
      <input type="number" name="amount" placeholder="Auto-calculated" />
      <label>Date Paid:</label>
      <input type="date" name="date_paid" value="current_date" />
      <label>Notes:</label>
      <input type="text" name="notes" placeholder="Optional notes" />
    </div>
    <input type="submit" value="Mark Selected as Paid" />
    <button type="button" onclick="hideBulkForm()">Cancel</button>
  </form>
</div>

<!-- Bulk Actions Controls -->
<div class="bulk-actions">
  <button onclick="showBulkForm()" id="bulk-action-btn" disabled>
    Mark Selected as Paid
  </button>
  <span id="selected-count"></span>
</div>
```

### JavaScript Functionality

```javascript
// Checkbox management
document
  .getElementById("select-all-events")
  .addEventListener("change", function () {
    document.querySelectorAll(".event-checkbox").forEach((checkbox) => {
      checkbox.checked = this.checked;
    });
    updateBulkActions();
  });

// Update bulk action button state
function updateBulkActions() {
  const checkedBoxes = document.querySelectorAll(".event-checkbox:checked");
  const count = checkedBoxes.length;

  if (count > 0) {
    document.getElementById("bulk-action-btn").disabled = false;
    document.getElementById("selected-count").textContent =
      count + " event(s) selected";
  } else {
    document.getElementById("bulk-action-btn").disabled = true;
    document.getElementById("selected-count").textContent = "";
  }
}

// Show/hide bulk payment form
function showBulkForm() {
  // Add hidden inputs for selected events
  // Show bulk payment form
}

function hideBulkForm() {
  // Hide bulk payment form
}
```

## Security Features

### Nonce Protection

- **Individual Payments**: `wp_nonce_field('individual_payment_action', 'payment_nonce')`
- **Bulk Payments**: `wp_nonce_field('bulk_payment_action', 'payment_nonce')`
- **Verification**: All form submissions verified with `wp_verify_nonce()`

### Data Sanitization

- **Amount**: `floatval()` for numeric validation
- **Date**: `sanitize_text_field()` with date format validation
- **Notes**: `sanitize_text_field()` for safe text input
- **Event IDs**: `intval()` for integer validation

### Permission Checks

- **Capability Required**: `manage_options` for accessing past events page
- **Form Processing**: Only processes forms with valid nonces
- **Data Validation**: All inputs validated before database updates

## Integration Points

### Analytics Settings Integration

- **Payment Rates**: Pulls badge and workshop rates from analytics settings
- **Auto-Calculation**: Uses rates to calculate default payment amounts
- **Settings Page**: Rates configurable via Events → Analytics Settings

### Event Categories Integration

- **Badge Detection**: Automatically detects "Badge" in category names
- **Rate Selection**: Applies appropriate payment rate based on category
- **Fallback**: Defaults to workshop rate for uncategorized events

### Existing Filter System

- **Filter Compatibility**: Works with all existing filters
- **URL Parameters**: Maintains filter state across page navigation
- **Sort Integration**: Payment status included in sort URL generation

## Usage Workflow

### Individual Payment Process

1. Navigate to Events → Past Events
2. Locate unpaid event in table
3. Fill in payment form fields (amount auto-filled)
4. Click "Mark Paid" button
5. Event status updates to paid with details displayed

### Bulk Payment Process

1. Navigate to Events → Past Events
2. Select events using checkboxes
3. Click "Mark Selected as Paid" button
4. Fill in bulk payment form
5. Submit form to process all selected events

### Filtering by Payment Status

1. Use "Payment" dropdown in filter section
2. Select "Paid" or "Unpaid" to filter events
3. Combine with other filters as needed
4. Click "Filter" to apply

## File Structure

```
inc/
├── events-admin-overview.php     # Main admin page with payment handling
├── payment-table-body.php        # Table body with payment columns
@docs/
├── instructor-payment-tracking.md # This documentation file
```

### Key Methods Added

- `handle_individual_payment_action()` - Processes single event payments
- `handle_bulk_payment_action()` - Processes multiple event payments
- Enhanced `display_past_events_page()` - Includes payment functionality

## Future Enhancements

### Reporting Features

- **Payment Reports**: Generate payment summaries by date range
- **Instructor Reports**: Payment totals per instructor
- **Export Functionality**: CSV export of payment data

### Advanced Features

- **Payment History**: Track payment modifications and history
- **Partial Payments**: Support for partial payment tracking
- **Payment Methods**: Track payment method (check, transfer, etc.)
- **Approval Workflow**: Multi-step payment approval process

### Integration Opportunities

- **Accounting Software**: Export to QuickBooks, Xero, etc.
- **Email Notifications**: Automatic payment confirmations
- **Calendar Integration**: Payment due date tracking

## Troubleshooting

### Common Issues

1. **Auto-calculation not working**

   - Check Analytics Settings for proper rate configuration
   - Verify event categories are properly assigned

2. **Bulk actions not appearing**

   - Ensure JavaScript is enabled
   - Check for console errors

3. **Payment status not saving**
   - Verify nonce fields are present
   - Check user permissions

### Debug Information

- **Payment Meta Fields**: Check post meta for `instructor_payment_*` fields
- **Analytics Settings**: Verify `mindevents_analytics_settings` option
- **Event Categories**: Check event taxonomy assignments

This instructor payment tracking system provides comprehensive payment management while maintaining integration with existing plugin functionality and following WordPress security best practices.
