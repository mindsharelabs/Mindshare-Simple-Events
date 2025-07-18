# WooCommerce Integration

The Mindshare Simple Events plugin offers robust integration with WooCommerce, enabling event ticket sales directly through the platform. This integration allows events to be treated as products, managing inventory, pricing, and attendee information seamlessly.

## Core Functionality

The integration is managed by the `mindEventsWooCommerce` class, which hooks into various WooCommerce actions and WordPress save events.

### Event-to-Product Linking

- **Automatic Product Creation:** When an event is saved, if ticket functionality is enabled, the plugin automatically creates or updates associated WooCommerce products.
  - For **single-event** types, one WooCommerce product is created or updated to represent the event across all its occurrences.
  - For **multiple-events** types, a separate WooCommerce product is created or updated for each individual event occurrence (sub-event).
- **Product Details:** The plugin populates product details such as:
  - **SKU:** Generated based on the event ID and date.
  - **Name:** Event title, potentially including date/time information.
  - **Description:** Inherited from the event's excerpt.
  - **Price:** Set from the event's 'ticket_price' or 'offerprice' meta.
  - **Stock:** Managed based on the event's 'ticket_stock' meta.
  - **Visibility:** Products are typically set to 'hidden' in the catalog, accessible only via direct links.
- **Meta Synchronization:** Key event meta fields (like `linked_product`, `event_start_time_stamp`, `event_end_time_stamp`, `linked_event`, `linked_occurance`) are synchronized to the WooCommerce product meta to maintain relationships.

### Ticket Sales and Attendees

- **Order Status Management:** The plugin monitors WooCommerce order statuses.
  - When an order is marked as 'completed' or 'processing', attendee information is recorded for the relevant event occurrence.
  - When an order status changes to 'refunded', 'cancelled', 'failed', 'on-hold', or 'pending', attendee information is removed.
- **Attendee Data:** Attendee details (Order ID, User ID, check-in status) are stored in the `attendees` meta field of the parent 'event' post, organized by the specific occurrence ID.
- **Check-in Functionality:** In the admin area, event organizers can mark attendees as checked in directly from the 'Event Attendees' meta box, provided the order status is 'completed'.

### Scheduled Notifications

- The plugin can schedule notifications (e.g., "three days before") related to event start dates using WordPress's cron system (`wp_schedule_single_event`). These are managed via the `schedule_hook` and `clear_schedule_hook` methods.

## Key Meta Fields for Integration

- **On Events/Sub-Events:**
  - `has_tickets`: Enables ticket functionality.
  - `ticket_stock`: Sets the stock quantity for the associated product.
  - `ticket_price`: Sets the base price for the ticket.
  - `linked_product`: The ID of the associated WooCommerce product.
  - `linked_occurance`: The ID of the specific sub-event occurrence linked to a product.
  - `attendees`: Stores attendee data for each occurrence.
- **On WooCommerce Products (created/updated by the plugin):**
  - `linked_event`: The ID of the parent event.
  - `linked_occurance`: The ID of the specific sub-event occurrence.
  - `_has_event`: A flag indicating this product is tied to an event.
  - `linkedEventStartDate`, `linkedEventEndDate`: Store the start and end dates of the event occurrence.
