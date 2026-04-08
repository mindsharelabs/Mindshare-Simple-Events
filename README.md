# Mindshare Simple Events

Mindshare Simple Events is a self-contained WordPress events plugin for sites that need a clean events archive, a flexible occurrence editor, and portable event data without WooCommerce or membership dependencies.

## v1 Scope

- `events` and `sub_event` post types with the `event_category` taxonomy
- admin occurrence calendar for adding, editing, moving, and deleting dates
- month, week, and list views for archives and single events
- category filtering and title search on the public archive
- ICS feed subscription and single-occurrence downloads
- read-only REST API at `/wp-json/simple-events/v1/events`
- plugin-owned organizer, location, visibility, and category color data
- self-contained frontend and admin styling with no Bootstrap or Font Awesome requirement

## Not Included In v1

- WooCommerce ticketing
- attendee check-in
- member dashboards
- reporting and CSV exports
- payment tracking
- badges
- reminder automations

## Data Model

Event and occurrence data is stored in these plugin-owned meta keys:

- `mindevents_organizer_name`
- `mindevents_organizer_title`
- `mindevents_organizer_image_id`
- `mindevents_location`
- `mindevents_visibility`

Category color is stored in:

- `mindevents_category_color`

For migration safety, legacy `_members_only` and ACF-backed organizer/category-color data are still read as compatibility fallbacks when the new fields are empty.

## Installation

1. Copy the plugin into `wp-content/plugins/Mindshare-Simple-Events`.
2. Activate it from the WordPress Plugins screen.
3. Visit `Settings > Simple Events` to set the week start day and default occurrence times.
4. Create events under `Events > Add New`.

## Usage

Create an event post, then use the `Occurrences` metabox to add one or more dates. Each occurrence can store its own date, time, short description, color override, organizer details, location, and visibility.

Theme overrides are supported with these template files:

- `archive-events.php`
- `single-events.php`
- `taxonomy-event-category.php`

## REST API

Endpoint:

- `GET /wp-json/simple-events/v1/events`

Supported filters:

- `page`
- `per_page`
- `status`
- `after`
- `before`
- `orderby`
- `order`
- `categories`
- `search`
- `parent`
- `include`
- `exclude`

Response fields:

- `id`
- `parent_id`
- `title`
- `permalink`
- `excerpt`
- `image`
- `start`
- `end`
- `location`
- `organizer`
- `categories`

Internal events are excluded from public API results.

## Calendar Feeds

- Site feed: `/events-feed.ics`
- Single occurrence: `/event-ics/{occurrence_id}/`

## Development

Install build dependencies and compile styles:

```bash
npm install
npm run build
```

Available scripts:

- `npm run build`
- `npm run watch`
