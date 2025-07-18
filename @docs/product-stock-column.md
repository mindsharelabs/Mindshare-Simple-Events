# Product Stock Column Feature

## Overview

The Product Stock Column feature adds real-time WooCommerce product stock information to the "Upcoming Events" admin page. This enhancement allows event administrators to quickly monitor ticket availability across all upcoming events without needing to navigate to individual product pages.

## Implementation Details

### Location

- **File**: [`inc/events-admin-overview.php`](../inc/events-admin-overview.php)
- **Class**: `MindEventsAdminOverview`
- **Method**: `display_upcoming_events_page()`

### Column Position

The "Product Stock" column is positioned between the "Orders" and "Instructor" columns in the upcoming events table, providing a logical flow of information from attendee data to stock availability to facilitator information.

### Stock Display Logic

The feature implements intelligent stock status display with color-coded indicators:

#### Stock Status Categories

1. **Out of Stock**

   - **Display**: "Out of stock" in red text
   - **Condition**: Stock quantity is 0 or less
   - **Color**: `#d63638` (WordPress error red)
   - **Styling**: Bold text for emphasis

2. **Low Stock Warning**

   - **Display**: "[X] remaining" in amber/yellow text
   - **Condition**: Stock quantity is 5 or fewer items
   - **Color**: `#dba617` (WordPress warning yellow)
   - **Styling**: Bold text for attention

3. **In Stock**

   - **Display**: "[X] available" in green text
   - **Condition**: Stock quantity is greater than 5
   - **Color**: `#00a32a` (WordPress success green)
   - **Styling**: Normal weight text

4. **Stock Not Managed**

   - **Display**: "Stock not managed"
   - **Condition**: Product exists but stock management is disabled
   - **Styling**: Normal text

5. **No Product Linked**

   - **Display**: "No product linked"
   - **Condition**: Event has no associated WooCommerce product
   - **Styling**: Normal text

6. **Product Not Found**
   - **Display**: "Product not found"
   - **Condition**: Linked product ID exists but product doesn't exist in WooCommerce
   - **Styling**: Normal text (indicates data integrity issue)

### Technical Implementation

#### Stock Retrieval Process

```php
// Get linked product ID from sub-event meta
$linked_product = get_post_meta(get_the_id(), 'linked_product', true);

// Check if WooCommerce is available and product exists
if ($linked_product && class_exists('WooCommerce')) {
    $product = wc_get_product($linked_product);

    if ($product && $product->managing_stock()) {
        $stock_quantity = $product->get_stock_quantity();
        if ($stock_quantity !== null) {
            $product_stock_info = $stock_quantity;
        }
    }
}
// Default to "-" for all other cases
```

#### Integration Points

The feature integrates seamlessly with existing plugin architecture:

- **Event-Product Linking**: Uses existing `linked_product` meta field from WooCommerce integration
- **Sub-Event System**: Works with both `single-event` and `multiple-events` event types
- **WooCommerce Compatibility**: Includes proper checks for WooCommerce availability
- **Error Handling**: Gracefully handles missing products or disabled stock management

## User Interface

### Table Structure

The updated table now includes 7 columns:

1. **Event** - Event title with edit link
2. **Actions** - Edit Event, View Event, Edit Product buttons
3. **Date** - Event occurrence date
4. **Attendees** - Number of registered attendees
5. **Orders** - List of associated orders with customer names
6. **Product Stock** - ⭐ **NEW** - Real-time stock information
7. **Instructor** - Assigned facilitator information

### Visual Design

The stock column uses WordPress admin color scheme for consistency:

- **Success Green** (`#00a32a`): Adequate stock levels
- **Warning Yellow** (`#dba617`): Low stock alerts
- **Error Red** (`#d63638`): Out of stock warnings

## Benefits

### For Event Administrators

- **Quick Stock Overview**: See all event stock levels at a glance
- **Proactive Management**: Identify low stock situations before they become problems
- **Efficient Workflow**: No need to navigate to individual product pages
- **Visual Alerts**: Color-coded system for immediate status recognition

### For Event Planning

- **Capacity Planning**: Understand ticket availability across all events
- **Revenue Optimization**: Identify events that may need stock adjustments
- **Customer Service**: Quickly answer stock-related inquiries

## Usage Examples

### Typical Display Scenarios

1. **Popular Workshop with Low Stock**

   ```
   Event: "Advanced WordPress Development"
   Product Stock: "3 remaining" (yellow warning)
   ```

2. **New Event with Full Availability**

   ```
   Event: "Beginner's Guide to React"
   Product Stock: "25 available" (green)
   ```

3. **Sold Out Event**

   ```
   Event: "Exclusive Masterclass"
   Product Stock: "Out of stock" (red)
   ```

4. **Event Without Tickets**
   ```
   Event: "Free Community Meetup"
   Product Stock: "No product linked"
   ```

## Compatibility

### Requirements

- **WordPress**: Compatible with current WordPress admin styling
- **WooCommerce**: Requires WooCommerce plugin for stock data
- **Plugin Version**: Available in Mindshare Simple Events v1.3.4+

### Event Types

- **Single Events**: Shows stock for the single linked product
- **Multiple Events**: Shows stock for each occurrence's individual product

### Fallback Behavior

- Gracefully handles missing WooCommerce plugin
- Displays appropriate messages for various error states
- Maintains table structure even when stock data is unavailable

## Future Enhancements

Potential improvements for future versions:

1. **Stock Alerts**: Email notifications when stock falls below threshold
2. **Bulk Stock Management**: Quick actions to adjust stock levels
3. **Stock History**: Track stock changes over time
4. **Export Functionality**: Include stock data in event reports
5. **Custom Thresholds**: Allow administrators to set custom low-stock warning levels

## Troubleshooting

### Common Issues

1. **"Product not found" Message**

   - **Cause**: Linked product has been deleted from WooCommerce
   - **Solution**: Edit the event and link to a valid product or create a new product

2. **"Stock not managed" Message**

   - **Cause**: WooCommerce product has stock management disabled
   - **Solution**: Enable stock management in the product settings

3. **"No product linked" Message**
   - **Cause**: Event doesn't have tickets enabled or no product is associated
   - **Solution**: Enable tickets in event settings and link to a WooCommerce product

### Debug Information

To troubleshoot stock display issues:

1. Verify the `linked_product` meta field exists on the sub-event
2. Confirm the product exists in WooCommerce
3. Check that stock management is enabled on the product
4. Ensure WooCommerce plugin is active and functioning

## Integration with Existing Features

### Admin Interface

The stock column integrates with existing admin interface features:

- Maintains consistent styling with other admin tables
- Preserves existing pagination and filtering functionality
- Works alongside existing event management workflows

### WooCommerce Integration

Leverages existing WooCommerce integration:

- Uses established product linking system
- Respects WooCommerce stock management settings
- Compatible with WooCommerce stock status options

### Event Management

Enhances existing event management capabilities:

- Provides additional context for event planning
- Supports both single and multiple event types
- Maintains compatibility with facilitator management system
