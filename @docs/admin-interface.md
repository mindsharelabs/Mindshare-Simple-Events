# Admin Interface

The Mindshare Simple Events plugin provides a comprehensive interface within the WordPress admin area for managing events, their occurrences, and attendees. This is primarily achieved through custom meta boxes added to the 'events' post type and related post types.

## Meta Boxes for 'Events' Post Type

When creating or editing an event, administrators will find the following meta boxes:

### Calendar

This meta box is central to managing event occurrences.

- **Occurance Options:** Allows users to set default start times, end times, event colors, and short descriptions for event occurrences.
- **Calendar View:** Displays a calendar interface where users can visually add, edit, or delete specific event dates and times.
- **Navigation:** Buttons to navigate between months in the calendar.
- **Clear All Occurrences:** A button to remove all associated sub-events for the current event.

### Calendar Options

This section provides global settings and event-specific configurations:

- **Event Type:** Choose between `multiple-events` (each occurrence is distinct) or `single-event` (multiple occurrences of the same event).
- **Has Tickets?:** A toggle to enable or disable ticket sales for the event.
- **WooCommerce Integration Settings (if enabled):**
  - **Available Tickets:** Sets the total stock for the event ticket.
  - **Ticket Price:** Sets the default price for the event ticket.
  - **Ticket Button Text:** Custom text for the "Add to Cart" button.
  - **Linked Product:** Allows linking the event to an existing WooCommerce product. If left blank, a new product may be created.
- **Non-WooCommerce Ticket Options:**
  - **Ticket Label:** Text for the ticket button (e.g., "Register").
  - **Price:** The cost of the ticket.
  - **Link:** A URL for purchasing tickets or more information.
- **Calendar Display:** Choose between 'List' or 'Calendar' view for frontend display.
- **Show Past Events?:** Option to display only future events or all events.

### Event Attendees

This meta box displays and manages attendees for events that have tickets enabled.

- **Occurrence Header:** Each occurrence displays the date in the header with facilitator information shown in the top right corner.
- **Facilitator Display:** Shows the facilitator name for each occurrence. If a specific facilitator is assigned to the occurrence (via `instructorEmail` meta field), it displays that facilitator. Otherwise, it falls back to the overall event facilitators (via `instructors` ACF field). If no facilitator is assigned, it shows "Not assigned".
- **Attendee Table:** Lists attendees with details such as Order ID, Status, Attendee Name (linked to user profile), Product (linked to product), and Check-in status.
- **Filtering:** For events with multiple occurrences, attendees are grouped by their specific occurrence date. Past occurrences may have a toggle to expand/collapse the attendee list.
- **Check-in Functionality:** Allows administrators to mark attendees as checked in or undo check-ins, provided the order status is 'completed'.

## Meta Box for 'Product' Post Type

### Event Details

When a WooCommerce product is linked to an event, this meta box appears on the product edit screen:

- **Linked Event:** Displays a link to the parent 'event' post that this product is associated with. This helps in understanding the relationship between products and events.

## Upcoming Events Admin Page

The plugin provides a dedicated "Upcoming Events" admin page accessible from the Events submenu in the WordPress admin. This page displays a comprehensive overview of all upcoming event occurrences in a tabular format, making it easy for administrators to manage multiple events at once.

### Access Location

- **Menu Path:** Events → Upcoming Events
- **Capability Required:** `manage_options`
- **Page Slug:** `upcoming-events`

### Table Columns

The upcoming events table includes the following columns:

1. **Event** - Event title with direct link to edit the main event post
2. **Actions** - Quick action buttons:
   - **Edit Event:** Opens the main event editor
   - **View Event:** Opens the frontend event page
   - **Edit Product:** Opens the linked WooCommerce product editor (if applicable)
3. **Date** - The specific occurrence date formatted as "Month Day, Year"
4. **Attendees** - Total number of registered attendees for this occurrence
5. **Orders** - List of WooCommerce orders with customer names and order numbers (linked to order edit pages)
6. **Product Stock** - Real-time WooCommerce product stock information with color-coded status indicators
7. **Instructor** - Assigned facilitator information with links to user profiles

### Product Stock Column

The Product Stock column displays real-time inventory information for event tickets in a clean, simple format:

- **Stock Numbers**: Shows the actual stock quantity (e.g., "25", "3", "0") when a product is linked and stock management is enabled
- **Dash "-"**: Displayed when:
  - No product is linked to the event
  - Product doesn't exist in WooCommerce
  - Product exists but stock management is disabled
  - WooCommerce plugin is not active

For detailed information about the Product Stock feature, see [`product-stock-column.md`](product-stock-column.md).

### Pagination and Performance

- **Items Per Page:** 30 events per page
- **Pagination:** WordPress-style pagination controls
- **Query Optimization:** Efficiently queries only upcoming events using `event_time_stamp` meta field
- **Date Filtering:** Shows events from yesterday onwards to include events that may span multiple days

### Facilitator Integration

The instructor column integrates with the plugin's facilitator management system:

- **Priority System:** Shows occurrence-specific facilitators first, then falls back to event-level facilitators
- **Multiple Facilitators:** Displays comma-separated list when multiple facilitators are assigned
- **User Links:** Facilitator names link directly to WordPress user edit pages
- **Fallback Display:** Shows "No instructor assigned" when no facilitators are set

### Event Type Compatibility

The page works seamlessly with both event types:

- **Single Events:** Shows the main event with all its occurrences
- **Multiple Events:** Shows each occurrence as a separate row with individual stock and attendee information

## Past Events Admin Page

The plugin also provides a dedicated "Past Events" admin page for reviewing completed events and analyzing their performance.

### Access Location

- **Menu Path:** Events → Past Events
- **Capability Required:** `manage_options`
- **Page Slug:** `past-events`

### Table Columns

The past events table includes the following columns:

1. **Date** - Event occurrence date (most recent first)
2. **Event** - Event title with direct link to edit the main event post
3. **Actions** - Quick action buttons (Edit Event, View Event)
4. **Sold Tickets** - Total number of tickets sold for the occurrence
5. **Checked In** - Number of attendees who were actually checked in
6. **Attendee Names** - Comma-separated list of all attendee names
7. **Instructor** - Assigned facilitator information with links to user profiles

### Key Features

- **Performance Analytics:** Compare sold tickets vs. actual check-ins
- **Historical Review:** Access to all past event data for analysis
- **Attendee Management:** Quick view of who attended each event
- **Instructor Tracking:** Review facilitator assignments and outcomes

For detailed information about the Past Events feature, see [`past-events-admin.md`](past-events-admin.md).

## Event Analytics Admin Page

The plugin provides a comprehensive "Event Analytics" admin page that offers detailed insights and performance metrics for all events within the system.

### Access Location

- **Menu Path:** Events → Event Analytics
- **Capability Required:** `manage_options`
- **Page Slug:** `event-analytics`

### Key Features

The Event Analytics dashboard provides:

1. **Overall Statistics:** Total events, attendees, revenue, and averages
2. **Attendance Performance:** Check-in rates with visual progress indicators
3. **Event Types Analysis:** Breakdown of single vs. multiple event types
4. **Top Performing Events:** Ranked list of most popular events
5. **Instructor Performance:** Comprehensive facilitator effectiveness metrics
6. **Monthly Trends:** 6-month trend analysis for events and attendance
7. **Recent Events Table:** Detailed performance data for recent events

### Analytics Capabilities

- **Date Range Filtering:** Customizable date ranges (default: last 12 months)
- **Revenue Tracking:** WooCommerce integration for financial analytics
- **Attendance Analysis:** Check-in vs. ticket sales comparison
- **Instructor Metrics:** Performance tracking for all facilitators
- **Visual Indicators:** Color-coded performance metrics and progress bars

For comprehensive information about the Event Analytics Dashboard, see [`event-analytics-dashboard.md`](event-analytics-dashboard.md).
