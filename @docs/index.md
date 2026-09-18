# Simple Events Docs

This folder now tracks the public-release v1 plugin, which focuses on the portable core events engine only.

- [Roadmap](roadmap.md): the phased plan for upcoming work
- [Foundation review](foundation-review.md): findings and decisions from the pre-work review

## Included In v1

- event and occurrence post types
- category taxonomy with plugin-owned color metadata
- admin occurrence calendar
- month, week, and list archive views
- single event pages and template overrides
- ICS feeds and add-to-calendar links
- read-only REST API at `/wp-json/simple-events/v1/events`
- plugin-owned organizer and location metadata

## Excluded From v1

- WooCommerce ticketing
- member-only dashboards
- reports and exports
- payment tracking
- badges
- reminder automations

## Release Notes

- Styling is self-contained and no longer depends on Bootstrap, Font Awesome, or CDN-hosted admin assets.
- The old Make Santa Fe specific API namespace has been replaced with `simple-events/v1`.
