# Foundation Review

A read of the whole plugin, done before any new features are built on it.
It covers security, the data model, capabilities, correctness and the
constraints the roadmap will run into.

Each finding has an ID, which the commits that fix it reference. The
**Status** column is updated as work lands.

Security findings marked *verified* have a test in `tests/` that failed
against the code as reviewed.

## Summary

The plugin is in better shape than its size suggests. There is no direct
SQL, occurrence meta is sanitized through an allowlist, the REST endpoint
filters by status and visibility correctly, and calendar queries are
bounded by the visible period.

The serious problems are concentrated in two places:

- **Public read paths that skip the visibility rules the rest of the
  plugin follows.** The event detail endpoint, the single-occurrence ICS
  download and the list view each serve data that should be hidden.
- **Time handling.** Times are stored as site-local strings with no
  offset, in five redundant forms. That is the root of several display
  bugs, and it would undermine RSVP reminders and ticket validity.

## What is solid

- No `$wpdb` usage anywhere, so no SQL injection surface.
- `sanitize_sub_event_meta()` returns only known keys, each with its own
  sanitizer, so AJAX requests cannot write arbitrary meta.
- `/wp-json/simple-events/v1/events` restricts to published, public
  occurrences.
- Month and week queries are bounded to the visible period before they
  run, so the `posts_per_page => -1` calls are not unbounded in practice.
- `get_posts()` primes the meta cache, so per-occurrence `get_post_meta()`
  calls in loops are cache hits, not extra queries.
- Settings are gated on `manage_options` with a sanitize callback.

Checked and ruled out: `capability_type => 'page'` without `map_meta_cap`
does not break permission checks, because WordPress turns `map_meta_cap`
on automatically for the `post` and `page` capability types.

## Findings

Severity: **High** means exploitable now by an anonymous visitor.
**Medium** means exploitable by a logged-in user beyond their role.
**Latent** means safe today but would become exploitable under planned
work. **Bug** covers incorrect behavior with no security impact.

### Security

| ID | Severity | Finding | Status |
|----|----------|---------|--------|
| S1 | High | The event detail AJAX endpoint (`nopriv`) returns the title, excerpt, location, organizer and image for **any post ID**. That includes drafts, private posts, internal occurrences and other post types. *Verified.* | Open |
| S2 | High | `/event-ics/{id}/` serves occurrences of draft events, and trashed occurrences. *Verified.* | Open |
| S3 | Medium | List views (archive list and single event list) do not apply the visibility filter, so internal occurrences are listed publicly. *Verified.* | Open |
| S4 | Medium | `moveevent`, `updatesubevent` and `editevent` check permission on a client-supplied parent ID, then act on a client-supplied occurrence ID without checking the two are related. Not exploitable with default roles, since anyone who can edit one event can edit all of them. It becomes exploitable once a role can edit only its own events. *Verified with such a role.* | Open |
| S5 | Medium | `selectday`, `clearevents` and `movecalendar` accept any post the user can edit as the parent event, so occurrences can be attached to pages or posts. *Verified.* | Open |
| S6 | Latent | JSON-LD is encoded with `JSON_UNESCAPED_SLASHES` and printed unescaped, so a `</script>` in any schema string breaks out of the block. Today every schema string is either sanitized or comes from roles that already hold `unfiltered_html`. RSVP and ticketing will add user-supplied strings. | Open |
| S7 | Low | The admin script inserts server error messages as HTML. Some of those messages are PHP exception text that echoes request input, so this is self-XSS. | Open |
| S8 | Decision | Internal events are hidden from every public listing, feed and API, but their single page is still reachable by URL. | Open |

### Capabilities

| ID | Severity | Finding | Status |
|----|----------|---------|--------|
| C1 | Design | `capability_type => 'page'` makes "can manage events" the same thing as "can edit pages". An event manager role that sees attendees or payments cannot exist without full page editing. | Open |

### Data model and time

| ID | Severity | Finding | Status |
|----|----------|---------|--------|
| D1 | Design | Times are stored as site-local `Y-m-d H:i:s` strings with no offset, five ways over: `event_date`, `starttime`, `endtime`, `event_start_time_stamp` and `event_end_time_stamp`. Nothing declares which is authoritative. Local wall-clock values are ambiguous across DST and shift if the site timezone changes. | Open |
| D2 | Bug | An occurrence running past midnight (22:00 to 01:00) is stored with its end before its start. | Open |
| D3 | Bug | The visible period is queried by start time only, so a multi-day occurrence that starts before the period is dropped from it. | Open |
| D4 | Bug | Month and list views place a multi-day occurrence only on its first day. | Open |
| D5 | Bug | The Yahoo calendar link parses local times as UTC, so it is off by the site's UTC offset. | Open |
| D6 | Bug | The REST API and JSON-LD emit times without a UTC offset. | Open |
| D7 | Bug | The duplicate-occurrence key is built from the date when adding and from the timestamp when updating, so duplicate detection is inconsistent. | Open |
| D8 | Bug | The occurrence color field is labeled as an override but is only used when an event has no category. A color input cannot be empty, so every occurrence saves `#000000`. | Open |

### Correctness and hygiene

| ID | Severity | Finding | Status |
|----|----------|---------|--------|
| H1 | Bug | Internationalization is inert. The text domain is never loaded, dates are formatted with `DateTime::format()` (always English), and admin and front-end script strings are hardcoded. | Open |
| H2 | Bug | There is no uninstall routine, so options, meta, roles and capabilities are left behind. | Open |
| H3 | Bug | `sub_event` is `public => false` but `publicly_queryable => true`, so occurrences are URL-addressable with no template. | Open |
| H4 | Bug | Registered meta has no `sanitize_callback`, so REST writes are stored unsanitized. | Open |
| H5 | Bug | The plugin header has no `Requires at least`, `Requires PHP`, `License` or `Domain Path`. | Open |
| H6 | Bug | Dead code: six calendar methods with no callers, an unused AJAX helper, and an **Event Type** setting that is saved but never read. | Open |
| H7 | Cleanup | Legacy compatibility reads (`_members_only`, ACF fields, `instructorID`/`instructorEmail`, `event_location`, duplicate `defaults` meta). The plugin is unshipped and needs none of them. | Open |
| H8 | Bug | `transition_post_status` fires on every save, not only on status changes, so every save of an event re-saves every one of its occurrences. | Open |
| H9 | Bug | ICS output is not escaped or line-folded per RFC 5545, so commas, semicolons and long descriptions produce malformed calendars. | Open |
| H10 | Bug | The public event detail endpoint requires a nonce. Nonces expire, so pages served from a full-page cache stop opening event details after a day. A read-only public endpoint gains nothing from a nonce. | Open |

## Decisions

### Time is stored as UTC instants (D1)

Each occurrence stores exactly two time values: `mindevents_start_utc` and
`mindevents_end_utc`, as `Y-m-d H:i:s` in UTC. This mirrors WordPress's own
`post_date_gmt`. Everything else is derived from them.

- **Input** from the admin is read as wall-clock time in the site
  timezone and converted to UTC once, on save.
- **Display** converts to the site timezone and formats with `wp_date()`,
  which also localizes month and day names.
- **Machine output** (REST, JSON-LD, ICS) carries explicit offsets or `Z`.

One display timezone, the site's, is used everywhere. Calendar grids,
lists, labels and admin input therefore always agree. A per-event timezone
was considered and rejected. It would add a zone to every display call,
and a month grid needs one zone to bucket days by, so it would only help
events held somewhere other than where the site is.

Consequence: if the site timezone is changed, existing occurrences keep
their absolute instant and show at the new local time. A manual UTC offset
(such as `UTC+0`) does not observe daylight saving, so the settings page
recommends a city-based timezone.

### Capabilities are namespaced and occurrences inherit from their event (C1)

- Events use their own capability type, `mindevents_event` /
  `mindevents_events`, with `map_meta_cap`. The capabilities are
  namespaced so they cannot collide with another events plugin's
  `edit_events`.
- Occurrences have no permissions of their own. A capability check on an
  occurrence is answered by checking the same capability on its parent
  event.
- Administrators and Editors receive the event capabilities, which keeps
  today's access unchanged. A new **Event Manager** role can manage events
  and nothing else.
- Capabilities are installed on activation and again whenever the
  plugin's schema version changes, since activation hooks do not run on
  updates. Uninstall removes them.

### Internal means not public, everywhere (S8)

"Internal" is treated as "not visible to anyone who cannot edit the
event". That applies to listings, feeds and the API as before, and now
also to the single event page, which returns a 404. This matches every
other surface. Unlisted-but-shareable would be a different, separate
option.

### The public event detail endpoint takes no nonce (H10)

Nonces protect state-changing requests from forgery. This endpoint only
reads data that is already public, and its nonce is printed into every
page for anonymous visitors, so the check protected nothing and broke
cached pages. Every endpoint that changes data keeps its nonce and
capability check.

## Constraints for the roadmap

These are not bugs and are not changed by this review. They need deciding
before the phase that depends on them.

| ID | Affects | Constraint |
|----|---------|------------|
| P1 | Phase 2 | The plugin registers no shortcode or block, and front-end assets load only on event archives, single events and category pages. A mini calendar placed anywhere else needs both. |
| P2 | All | The post types are named `events` and `sub_event`. Generic names can collide with other plugins and themes, and renaming them after launch means migrating data and URLs. If they are going to change, now is the cheapest time. |
