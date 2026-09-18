# Mindshare Simple Events

Mindshare Simple Events is a self-contained WordPress events plugin for sites that need a clean events archive, a flexible occurrence editor, and portable event data without WooCommerce or membership dependencies.

## v1 Scope

- `events` and `sub_event` post types with the `event_category` taxonomy
- admin occurrence calendar for adding, editing, moving, and deleting dates
- month, week, and list views for archives and single events
- category filtering and title search on the public archive
- ICS feed subscription and single-occurrence downloads
- read-only REST API at `/wp-json/simple-events/v1/events`
- plugin-owned organizer, location, and category color data
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

### Times

Each occurrence stores its time in exactly two meta keys, as `Y-m-d H:i:s`
in UTC, the same way WordPress stores `post_date_gmt`:

- `mindevents_start_utc`
- `mindevents_end_utc`

Each event keeps its range across all its occurrences, for sorting and
structured data:

- `mindevents_first_start_utc`
- `mindevents_last_end_utc`

Times are entered and displayed in the site timezone (Settings > General).
An end time earlier than the start time is read as the following day, so
22:00 to 01:00 runs overnight. If the site timezone is changed, existing
occurrences keep their moment in time and show at the new local time.
Choose a city rather than a fixed UTC offset, since offsets do not observe
daylight saving.

Read times through `mindevents_get_occurrence_times()`, which returns the
start and end in the site timezone, rather than reading the meta directly.

### Details

Event and occurrence details are stored in these plugin-owned meta keys:

- `mindevents_organizer_name`
- `mindevents_organizer_title`
- `mindevents_organizer_image_id`
- `mindevents_location`

Category color is stored in:

- `mindevents_category_color`

## Installation

1. Copy the plugin into `wp-content/plugins/Mindshare-Simple-Events`.
2. Activate it from the WordPress Plugins screen.
3. Visit `Settings > Simple Events` to set the week start day and default occurrence times.
4. Create events under `Events > Add New`.

## Uninstalling

Deleting the plugin from the Plugins screen removes its settings, the
Event Manager role and the event capabilities it added to other roles.

Events, occurrences and event categories are kept, since they are the
site's content. To delete those too, add this to `wp-config.php` before
deleting the plugin:

```php
define('MINDEVENTS_REMOVE_ALL_DATA', true);
```

## Usage

Create an event post, then use the `Occurrences` metabox to add one or more dates. Each occurrence can store its own date, time, short description, color override, organizer details, and location.

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
- `after`: a date or date-time, read in the site timezone unless it carries an offset
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
- `start`: ISO 8601 with the site's UTC offset, e.g. `2030-07-01T19:00:00-06:00`
- `end`
- `location`
- `organizer`
- `categories`

Only published events appear in public results. To hide an event from the public, set its visibility to Private in the Publish box: WordPress then hides it, and its occurrences, from the site, search, feeds, sitemaps and every API.

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

## Translations

Strings use the `simple-events` text domain. Translations are loaded from
`languages/`, and from `wp-content/languages/plugins/` as usual.

Regenerate the template after changing any translatable string (needs GNU
gettext's `xgettext`):

```bash
composer make-pot
```

## Tests

Tests run against the WordPress install the plugin sits in, with the plugin
active. Each test runs inside a database transaction that is rolled back
afterwards, so the site's data is never changed.

```bash
composer install
composer test
```

Machine-specific settings go in a local `phpunit.xml` (gitignored), copied
from `phpunit.xml.dist`. A MAMP install, for example, needs its MySQL socket:

```xml
<php>
    <ini name="mysqli.default_socket" value="/Applications/MAMP/tmp/mysql/mysql.sock"/>
</php>
```

Set `WP_LOAD_PATH` to test against a different install.

## License

GPL-3.0-or-later. See [LICENSE.txt](LICENSE.txt).
