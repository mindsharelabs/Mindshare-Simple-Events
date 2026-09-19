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

- [ ] Front-end assets that load wherever a calendar is shown
- [ ] **Display type** setting: list, calendar or mini calendar
- [ ] **Category** setting: show only events in the chosen categories
- [ ] **Event** setting: show one event and its dates only
- [ ] Live preview in the editor

## Phase 4: RSVP and accounts

Needs sub-phases:

1. **Data model.** Where RSVPs live, what they reference (an occurrence
   or a whole series), capacity, and retention.
2. **RSVP flow.** Guest or account-required, confirmation, cancellation.
3. **Account creation and information collection.**
4. **Notifications.** Confirmations and reminders, which depend on the UTC
   time model from the foundation review.

Guest versus required-account is the first decision, since it shapes
everything after it.

## Phase 5: CRM integration

A simple integration with the Mindshare CRM. It can't be sized until the
CRM's API surface has been reviewed.

## Phase 6: Stripe ticketing (separate plugin)

Needs sub-phases:

1. **Payment intents and checkout.** Stripe-hosted fields only, so card
   data never touches the site and PCI scope stays minimal.
2. **Webhooks.** Idempotent handling, since Stripe retries.
3. **Refunds and cancellations.**

## Phase 7: Theater seating charts (separate plugin)

Its own phase plan, to be written when the phase begins.
