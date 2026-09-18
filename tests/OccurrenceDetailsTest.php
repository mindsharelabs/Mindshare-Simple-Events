<?php

/**
 * Occurrence details fall back to the event's when the occurrence has none.
 */
class OccurrenceDetailsTest extends Mindshare_Events_TestCase {
    public function test_location_falls_back_to_the_event(): void {
        $event_id = $this->createEvent();
        update_post_meta($event_id, 'mindevents_location', 'Main Hall');
        $plain    = $this->createOccurrence($event_id, '2030-05-01');
        $override = $this->createOccurrence($event_id, '2030-05-02', '19:00', '21:00', array('mindevents_location' => 'Room 2'));

        $this->assertSame('Main Hall', mindevents_get_occurrence_location($plain));
        $this->assertSame('Room 2', mindevents_get_occurrence_location($override));
    }

    public function test_organizer_falls_back_to_the_event(): void {
        $event_id = $this->createEvent();
        update_post_meta($event_id, 'mindevents_organizer_name', 'Ana Ruiz');
        update_post_meta($event_id, 'mindevents_organizer_title', 'Studio Lead');
        $plain    = $this->createOccurrence($event_id, '2030-05-01');
        $override = $this->createOccurrence($event_id, '2030-05-02', '19:00', '21:00', array('mindevents_organizer_name' => 'Guest Artist'));

        $this->assertSame('Ana Ruiz', mindevents_get_organizer_data($plain)['name']);
        $this->assertSame('Studio Lead', mindevents_get_organizer_data($plain)['title']);
        $this->assertSame('Guest Artist', mindevents_get_organizer_data($override)['name']);
    }
}
