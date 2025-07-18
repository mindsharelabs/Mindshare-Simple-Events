# Frontend Display

The Mindshare Simple Events plugin customizes the frontend display of events by leveraging WordPress's template hierarchy and action hooks.

## Template Handling

The plugin uses the `template_include` filter to determine which template file to load for event-related pages:

- **Event Archive (`is_post_type_archive('events')`):**
  - It first checks if a theme has a custom `archive-events.php` or `templates/archive-events.php` file.
  - If found, it uses the theme's template.
  - Otherwise, it falls back to the plugin's default `templates/archive-events.php`.
- **Single Event (`is_singular('events')`):**
  - It checks for `single-events.php` or `templates/single-events.php` in the theme.
  - If not found, it uses the plugin's `templates/single-events.php`.
- **Event Category Archive (`is_tax('event_category')`):**
  - It checks for `taxonomy-event-category.php` or `templates/taxonomy-event-category.php` in the theme.
  - If not found, it uses the plugin's `templates/taxonomy-event-category.php`.

## Frontend Actions and Hooks

The plugin defines several actions that can be used to customize the display of single event pages:

- `mindshare_events_single_title`: Hooks into the main event title display.
- `mindshare_events_single_datespan`: Hooks into the display of the event's date range.
- `mindshare_events_single_content`: Hooks into the display of the event's excerpt and main content.

These actions allow developers to easily add or modify content on the single event page.

## Frontend Structure

The plugin's default templates (located in the `templates/` directory) structure the event display, including:

- Event titles and date spans.
- Event descriptions and excerpts.
- Calendar or list views of event occurrences.
- Ticket purchasing options (if integrated with WooCommerce).
- Event details such as time, location, and cost.
