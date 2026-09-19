# Foundation Review

A read of the whole plugin, done before any new features are built on it.
It covers security, the data model, capabilities, correctness and the
constraints the roadmap will run into.

Each finding has an ID, which the commits that fix it reference. The
**Status** column gives the commit that resolved it.

Every finding marked *verified* has a test in `tests/` that failed against
the code as reviewed and passes now. Findings marked *new* were found while
the fixes were being made, after the first version of this review.

## Summary

The plugin is in better shape than its size suggests. There is no direct
SQL, occurrence meta is sanitized through an allowlist, the REST endpoint
filters by status and visibility correctly, and calendar queries are
bounded by the visible period.

The serious problems were concentrated in two places:

- **Public read paths that skipped the visibility rules the rest of the
  plugin follows.** The event detail endpoint, the single-occurrence ICS
  download and the list view each served data that should be hidden. New
  occurrences of unpublished events were also published immediately.
- **Time handling.** Times were stored as site-local strings with no
  offset, in five redundant forms. That was the root of several display
  bugs, and it would have undermined RSVP reminders and ticket validity.

All findings are resolved.

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
- Settings are gated on a capability with a sanitize callback.

Checked and ruled out: `capability_type => 'page'` without `map_meta_cap`
did not break permission checks, because WordPress turns `map_meta_cap`
on automatically for the `post` and `page` capability types.

## Findings

Severity: **High** means exploitable by an anonymous visitor.
**Medium** means exploitable by a logged-in user beyond their role.
**Latent** means safe at the time but would have become exploitable under
planned work. **Bug** covers incorrect behavior with no security impact.

### Security

| ID | Severity | Finding | Status |
|----|----------|---------|--------|
| S1 | High | The event detail AJAX endpoint (`nopriv`) returned the title, excerpt, location, organizer and image for **any post ID**. That included drafts, private posts, internal occurrences and other post types. *Verified.* | Fixed `3703eb8` |
| S2 | High | `/event-ics/{id}/` served occurrences of draft events, and trashed occurrences. *Verified.* | Fixed `3703eb8` |
| S3 | Medium | List views did not apply the visibility filter, so internal occurrences were listed publicly. The "next occurrence" subtitle could show an internal date. *Verified.* | Fixed `3703eb8` |
| S4 | Medium | `moveevent`, `updatesubevent` and `editevent` checked permission on a client-supplied parent ID, then acted on a client-supplied occurrence ID without checking the two were related. This became exploitable once a role could edit only its own events. *Verified with such a role.* | Fixed `9223c9b` |
| S5 | Medium | Handlers that take an event ID accepted any post the user could edit, so occurrences could be attached to pages or posts. The delete handler permanently deleted, past the trash, **any** post the user could delete. *Verified.* | Fixed `9223c9b` |
| S6 | Latent | JSON-LD was encoded with `JSON_UNESCAPED_SLASHES` and printed unescaped, so a `</script>` in any schema string would break out of the block. *Verified.* | Fixed `d613218` |
| S7 | Low | The admin script inserted server error messages as HTML, and some messages were PHP exception text echoing request input: self-XSS. *Verified.* | Fixed `e38dbde` |
| S8 | Medium | Internal events were hidden from the plugin's own listings, feeds and API, but still reachable by URL, and still appeared in **site search, the core REST API (`/wp/v2/events`) and the XML sitemap**. | Fixed `7a862f7` |
| S9 | Medium | *New.* Occurrences were always created as published, whatever their event's status, so a draft event's title and dates appeared in public calendars, the REST API and the ICS feed. Scheduling an event published its occurrences immediately. *Verified.* | Fixed `74fde00` |

### Capabilities

| ID | Severity | Finding | Status |
|----|----------|---------|--------|
| C1 | Design | `capability_type => 'page'` made "can manage events" the same thing as "can edit pages". | Fixed `3d6769c` |

### Data model and time

| ID | Severity | Finding | Status |
|----|----------|---------|--------|
| D1 | Design | Times were stored as site-local strings with no offset, five ways over. Nothing declared which was authoritative. | Fixed `1534ee6` |
| D2 | Bug | An occurrence running past midnight was stored with its end before its start. | Fixed `1534ee6` |
| D3 | Bug | The visible period was queried by start time only, dropping occurrences that began before it. | Fixed `1534ee6` |
| D4 | Bug | Month and list views placed a multi-day occurrence only on its first day. | Fixed `1534ee6` |
| D5 | Bug | The Yahoo calendar link was off by the site's UTC offset. | Fixed `1534ee6` |
| D6 | Bug | The REST API and JSON-LD emitted times without a UTC offset. | Fixed `1534ee6` |
| D7 | Bug | The duplicate-occurrence key was built differently on add and update. | Fixed `1534ee6` |
| D8 | Bug | The occurrence color was used only when an event had no category, and could not be left empty, so every occurrence saved `#000000`. | Fixed `008a17e` |
| D9 | Bug | *New.* An invalid `?calendar_date=` in the URL caused a fatal error on every events page, triggerable by any visitor. *Verified.* | Fixed `7880c1d` |

### Correctness and hygiene

| ID | Severity | Finding | Status |
|----|----------|---------|--------|
| H1 | Bug | Internationalization was inert: the text domain was never loaded, dates were always English, and script strings were hardcoded. | Fixed `ddaff59` |
| H2 | Bug | There was no uninstall routine. | Fixed `c32df47` |
| H3 | Bug | `sub_event` was publicly queryable, so occurrences were URL-addressable with no template. | Fixed `882f3a9` |
| H4 | Bug | Registered meta had no `sanitize_callback`. **Correction:** the review said REST writes were stored unsanitized. That was true for the category color, which REST exposes. Event and occurrence meta are not in REST at all, because events do not support `custom-fields`. They got sanitizers anyway, since those run on every write. | Fixed `5b90023` |
| H5 | Bug | The plugin header had no `Requires at least`, `Requires PHP`, `License` or `Domain Path`, and the repository's license declarations disagreed. | Fixed `ddaff59`, `d887b1c` |
| H6 | Bug | Dead code: six calendar methods, an unused AJAX helper, and an **Event Type** setting that was saved but never read. | Fixed `0bf9395` |
| H7 | Cleanup | Legacy compatibility reads for data the unshipped plugin never had. | Fixed `1441fec` |
| H8 | Bug | Every save of an event re-saved every one of its occurrences. | Fixed `2afb8d6` |
| H9 | Bug | ICS output was not escaped or line-folded per RFC 5545. | Fixed `bbaa0cf` |
| H10 | Bug | The public event detail endpoint required a nonce, which broke on pages served from a full-page cache. | Fixed `a6c8a1d` |
| H11 | Bug | *New.* JSON-LD, ICS, the REST API and calendar links carried HTML entities (`Clay &#038; Glass`). Calendar links also cut titles at the first `&`. *Verified.* | Fixed `93f13cd` |
| H12 | Bug | *New.* Calendar navigation links doubled the path on sites installed in a subdirectory. *Verified.* | Fixed `4a872e0` |
| H13 | Bug | *New.* Backslashes were stripped from saved event and occurrence text. *Verified.* | Fixed `a284351` |
| H14 | Bug | *New.* Weekday headings sat one column off from their dates in the month, week and admin calendars. Found by looking at the rendered calendar. *Verified.* | Fixed `6853aac` |

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
does not observe daylight saving, so the settings page now warns when one
is in use (`b800588`).

### Capabilities are namespaced and occurrences inherit from their event (C1)

- Events use their own capability type, `mindevents_event` /
  `mindevents_events`, with `map_meta_cap`. The capabilities are
  namespaced so they cannot collide with another events plugin's
  `edit_events`.
- Occurrences have no permissions of their own. A capability check on an
  occurrence is answered by checking the same capability on its parent
  event.
- Administrators and Editors receive the event capabilities, which keeps
  their access unchanged. A new **Event Manager** role can manage events,
  event categories and plugin settings, and nothing else.
- Capabilities are installed on activation and again whenever the
  plugin's schema version changes, since activation hooks do not run on
  updates. Uninstall removes them.

### The public event detail endpoint takes no nonce (H10)

Nonces protect state-changing requests from forgery. This endpoint only
reads data that is already public, and its nonce was printed into every
page for anonymous visitors, so the check protected nothing and broke
cached pages. Every endpoint that changes data keeps its nonce and
capability check, which a test confirms.

### Uninstall keeps content unless told otherwise (H2)

Settings, roles and capabilities are always removed. Events, occurrences
and categories are deleted only when `MINDEVENTS_REMOVE_ALL_DATA` is
defined, the usual WordPress practice.

### Private events use WordPress's Private status (S8)

"Internal" was plugin meta, which WordPress knew nothing about, so core
surfaces kept exposing internal events. It is replaced by WordPress's own
Private status, which core hides from everyone without permission on every
surface, including ones added later. Occurrences take their event's
status, and Event Managers, Editors and Administrators still see private
events. Visibility is now per event; an individual date can no longer be
hidden on its own.

### License: GPL-3.0-or-later

The header, `package.json` and `composer.json` all declare
GPL-3.0-or-later, with the full text in `LICENSE.txt`. "Or later" follows
the practice of the plugin this one replaced (`d887b1c`).

### Prefixed names (P2)

The post types are `mind_events` and `mind_sub_event` (`7671563`), and the
category taxonomy is `mind_event_category` (`f7adda7`), so they cannot
collide with another plugin's or theme's. Public URLs are unchanged:
`/events/`, `/events/{slug}/` and `/event_category/{slug}/`.

## Constraints for the roadmap

| ID | Affects | Constraint |
|----|---------|------------|
| P1 | Phase 3 | Resolved: the Events Calendar block places a list, calendar or mini calendar on any page, and loads the front-end assets wherever it is used. |
| P2 | All | Resolved: the post types and taxonomy are prefixed. |

## Carried into Phase 1

Found during this work, and left for the production-readiness phase since
they are UI behavior rather than defects in scope here:

- A single event's calendar opens on the month of its **first** date. With
  "Show upcoming only" set, and that first date in the past, visitors land
  on an empty month. It should open on the next upcoming occurrence.
- Occurrences are listed under Events > Occurrences in the admin, but
  their edit screen is empty, since the post type supports no fields.
  They are managed from the event's calendar, so the list and its edit
  links are either worth hiding or worth making useful.
- The organizer image is entered as a raw attachment ID rather than
  picked from the media library.
