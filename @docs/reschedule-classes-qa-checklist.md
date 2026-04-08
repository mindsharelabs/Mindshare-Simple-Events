# Reschedule Classes QA Checklist

Use this checklist before pushing the WooCommerce class rescheduling feature to production.

## Access And Eligibility

- [ ] Confirm only logged-in customers can access the reschedule interface on the My Account dashboard.
- [ ] Confirm customers only see their own orders and classes.
- [ ] Confirm only `processing` and `completed` orders are eligible.
- [ ] Confirm past classes do not appear.
- [ ] Confirm classes inside the 14-day cutoff show as blocked.
- [ ] Confirm classes more than 14 days out can be rescheduled when alternate dates exist.
- [ ] Confirm the cutoff is correct in the site timezone.
- [ ] Confirm only alternate occurrences from the same parent event are offered.
- [ ] Confirm sold-out replacement classes are not offered.
- [ ] Confirm classes with no alternate dates show `No alternate class dates currently available`.
- [ ] Confirm shared `single-event` ticket products are excluded if that is the intended v1 behavior.

## Dashboard Checks

- [ ] Confirm the WooCommerce My Account dashboard shows the class reschedule interface.
- [ ] Confirm each upcoming class shows the correct date.
- [ ] Confirm each class shows the correct action-area messaging:
  - `Available for reschedule until ...`
  - `Rescheduling closed on ...`
  - `No alternate class dates currently available`

## Reschedule UI

- [ ] Confirm classes are grouped by order.
- [ ] Confirm each row shows class name, date, quantity, and action.
- [ ] Confirm eligible rows show a replacement date dropdown and reschedule button.
- [ ] Confirm blocked rows do not show a submit control.
- [ ] Confirm multi-quantity lines display the full quantity and only move as a whole line.
- [ ] Confirm the class name links to the parent event page.
- [ ] Confirm the displayed class name is the parent event title, not the sub-event title.

## Happy Path Order Update

- [ ] Reschedule a class on a `processing` order and confirm success.
- [ ] Reschedule a class on a `completed` order and confirm success.
- [ ] Confirm the line item product changes to the selected replacement class.
- [ ] Confirm the line item name updates to the new class/date.
- [ ] Confirm the quantity stays the same.
- [ ] Confirm the order still opens normally in WooCommerce admin.
- [ ] Confirm the order totals recalculate correctly.
- [ ] Confirm fees, coupons, shipping, and unrelated line items remain correct.
- [ ] Confirm the order status does not change unexpectedly.
- [ ] Confirm a new order note is added showing the old and new class dates.

## WooCommerce Stock

- [ ] Record stock on the old class product before rescheduling.
- [ ] Record stock on the new class product before rescheduling.
- [ ] Confirm the old class stock increases by the moved quantity.
- [ ] Confirm the new class stock decreases by the moved quantity.
- [ ] Confirm quantity greater than 1 updates stock correctly.
- [ ] Confirm insufficient-stock replacements are blocked.
- [ ] Confirm a duplicate submit does not double-adjust stock.
- [ ] If any event products allow backorders, confirm expected backorder behavior.

## Order Meta And Hooks

- [ ] Confirm order-level event meta updates correctly:
  - `_mindevents_event_start`
  - `_mindevents_event_end`
  - `_mindevents_event_title`
  - `_mindevents_event_schedule`
  - `_mindevents_event_schedule_text`
- [ ] Confirm the swapped line item updates its event meta:
  - `_mindevents_event_start`
  - `_mindevents_event_end`
- [ ] Confirm normal WooCommerce order saves still work after the reschedule.
- [ ] Confirm the order can still be edited manually in WooCommerce admin after the swap.

## Attendees And Event Admin

- [ ] Confirm the attendee is removed from the old occurrence attendee list.
- [ ] Confirm the attendee is added to the new occurrence attendee list.
- [ ] Confirm the event attendee metabox/admin table reflects the move.
- [ ] Confirm there are no duplicate attendee rows left on the old class.
- [ ] Confirm moved attendees are not marked as checked in.
- [ ] Confirm the old occurrence order count/stat fields update.
- [ ] Confirm the new occurrence order count/stat fields update.
- [ ] Confirm revenue/profit/order listing fields still look correct for both occurrences.

## Scheduling And Automations

- [ ] Confirm the old reminder cron hook is removed.
- [ ] Confirm the new reminder cron hook is scheduled for the replacement class.
- [ ] Confirm AutomateWoo event/order variables reflect the new class date.
- [ ] Confirm reminder/follow-up workflows no longer point at the old class.
- [ ] Confirm no unexpected customer/admin emails are triggered by the swap.

## Failure Cases

- [ ] Confirm an invalid nonce shows an error notice and does not change the order.
- [ ] Confirm an invalid order ID shows an error notice and does not change the order.
- [ ] Confirm an invalid line item ID shows an error notice and does not change the order.
- [ ] Confirm an invalid replacement product shows an error notice and does not change the order.
- [ ] Confirm a class inside the cutoff cannot be forced through manually.
- [ ] Confirm a class from another customer cannot be rescheduled.
- [ ] Confirm refunded, cancelled, on-hold, failed, and pending orders are blocked.

## Production Sign-Off

- [ ] Review PHP error logs after several end-to-end test swaps.
- [ ] Review WooCommerce logs if any related entries are written there.
- [ ] Test against realistic staging data, not only hand-built demo orders.
- [ ] Confirm support/admin staff know where to verify attendee moves and stock adjustments.
- [ ] Confirm a rollback path exists before deployment.
- [ ] Confirm one final full end-to-end test passed from customer page to admin verification.
