# Roadmap

Work happens one phase at a time, and each phase is reviewed before the
next begins. Each finished item is its own commit.

## Pre-work

- [x] Week view: overlapping occurrences, extension hook, radius token,
      build config
- [x] Foundation review and fixes. See [foundation-review.md](foundation-review.md).

## Phase 1: Production readiness

Bug fixes, UI improvements and testing, so the plugin is ready to run on a
real site.

**Quality gates**

- [x] WordPress coding-standards security checks and a PHP 7.4
      compatibility check, with their findings fixed
- [x] Continuous integration: tests and checks on every push

**Front end**

- [x] A single event's calendar opens on its next upcoming occurrence
- [x] Accessible event detail dialog and dropdowns: keyboard, focus,
      screen-reader labels
- [x] No plugin branding in the archive header
- [x] Views and dialog work at phone width

**Admin**

- [x] Hide the empty occurrence screens
- [x] Media-library picker for the organizer image
- [x] Accessible occurrence edit dialog
- [x] Visual pass over the admin screens

**Packaging**

- [x] Build script for a clean distributable zip

**Found and fixed along the way**

- Quick Edit, the REST API and code erased a category's color
- Asset URLs never changed within a version, so updates were served stale
- The admin calendar's delete button covered the date, so clicking a date
  to edit it asked to delete it
- Occurrence defaults were copied into every new date, so later changes to
  the event did not reach them; defaults are now start and end times only
- Closed dialogs stayed in the tab order, invisible

## Phase 2: Mini monthly calendar

A third display type for an event, beside List and Calendar.

- [x] **Mini calendar** display type: every month the event has dates in,
      as small month grids, up to three across. Days with events are filled
      circles; clicking one opens a dialog listing that day's dates.

It lives on the event's own page, so it does not need review item **P1**
(a shortcode or block). Phase 3 adds that.

## Phase 3: Calendar block

A block that places an events calendar on any page. This resolves review
item **P1**.

- [x] Front-end assets that load wherever a calendar is shown
- [x] **Display type** setting: list, calendar or mini calendar
- [x] **Category** setting: show only events in the chosen categories
- [x] **Event** setting: show one event and its dates only
- [x] Live preview in the editor

**The core plugin is complete with Phase 3.** Later phases build on it
from separate plugins, and add hooks to it only as they need them.

## Phase 4: Tickets and RSVP (separate plugin)

**Mindshare Simple Tickets**, in its own repository, with its own roadmap.
It adds RSVP with account creation or guest RSVP, paid tickets through
Stripe, and the screens to manage both.

Decided:

- An RSVP is a free ticket type, so RSVPs and paid tickets share one
  model, one attendee list and one set of screens.
- Tickets are for a single date, and capacity is per date.
- Accounts are WordPress users; guests RSVP or buy with a name and email.
- Payment goes through Stripe's hosted checkout page.
- Emails are sent with `wp_mail()`.
- Check-in at the door comes later. Each ticket gets a unique code now,
  so it can be added without changes.

## Phase 5: CRM integration

A simple integration with the Mindshare CRM. It can't be sized until the
CRM's API surface has been reviewed.

## Phase 6: Theater seating charts (separate plugin)

Its own phase plan, to be written when the phase begins.
