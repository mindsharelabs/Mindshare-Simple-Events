# Event Management

The Mindshare Simple Events plugin utilizes WordPress's Custom Post Types (CPTs) and taxonomies to manage event data.

## Custom Post Types

- **Events (`events`):** This is the primary post type for creating individual events. Each 'event' post can have multiple occurrences or sub-events.
- **Sub-Event (`sub_event`):** Represents a specific occurrence or date/time slot for a main 'event'. These are linked to a parent 'event' post.

## Taxonomies

- **Event Category (`event_category`):** A taxonomy used to categorize events. Colors can be assigned to categories, which can then be applied to events belonging to that category.

## Key Meta Fields

The plugin stores event-specific data using post meta fields. These are typically managed through meta boxes in the WordPress admin.

### Main Event Meta Fields (Post Type: `events`)

- `event_type`: Determines how occurrences are handled. Can be `multiple-events` (each sub-event is distinct) or `single-event` (multiple occurrences of the same event).
- `has_tickets`: A flag (`1` or `0`) indicating if tickets are available for the event.
- `ticket_stock`: The total number of tickets available for the event (used when WooCommerce is enabled).
- `ticket_price`: The default price for event tickets (used when WooCommerce is enabled).
- `cal_display`: Controls how the event is displayed on the frontend, either as a 'list' or 'calendar'.
- `show_past_events`: A flag (`1` or `0`) to control whether past event occurrences are displayed.
- `event_defaults`: Stores default values for sub-event occurrences (e.g., default start/end times, color, description).
- `linked_product`: The ID of a WooCommerce product linked to the event for ticket sales.
- `wooLabel`: Custom text for the "Add to Cart" button when using WooCommerce.
- `offers`: Stores an array of custom offers (label, price, link) if WooCommerce is not enabled or if custom offers are preferred.

### Sub-Event Meta Fields (Post Type: `sub_event`)

- `event_date`: The specific date of the event occurrence.
- `starttime`: The start time for the occurrence.
- `endtime`: The end time for the occurrence.
- `eventColor`: A custom color assigned to this specific occurrence, overriding category colors.
- `eventDescription`: A short description for this particular occurrence.
- `instructorEmail`: Email address of the facilitator/instructor specific to this occurrence. Takes precedence over parent event facilitators.
- `unique_event_key`: A generated key to uniquely identify an event occurrence, often used for de-duplication.
- `event_time_stamp`: A timestamp representing the start time of the occurrence, used for sorting and filtering.
- `event_start_time_stamp`: Formatted start date and time.
- `event_end_time_stamp`: Formatted end date and time.
- `wooLabel`: (Inherited from parent event if applicable)
- `linked_product`: (Inherited from parent event if applicable)
- `eventCost`: (Inherited from parent event if applicable)
- `ticket_stock`: (Inherited from parent event if applicable)

### Facilitator/Instructor Management

The plugin supports facilitator assignment at both the event and occurrence level:

- **Event Level:** Uses the `instructors` ACF (Advanced Custom Fields) field on the main event post to assign overall facilitators.
- **Occurrence Level:** Uses the `instructorEmail` meta field on individual sub-events to assign specific facilitators for particular dates.
- **Priority:** If both are set, the occurrence-level facilitator takes precedence over the event-level facilitators for that specific date.
- **Display:** Facilitator information is displayed in the admin Event Attendees meta box in the top right corner of each occurrence section.

## Event Occurrences

The plugin distinguishes between a main 'event' and its 'sub_event' occurrences. A single 'event' can have multiple 'sub_event' entries, each representing a distinct date and time. This allows for events that span multiple days or have recurring schedules. The `event_type` meta field on the main 'event' post dictates how these occurrences are managed and displayed.
