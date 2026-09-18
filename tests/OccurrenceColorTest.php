<?php

class OccurrenceColorTest extends Mindshare_Events_TestCase {
    private $event_id;

    protected function setUp(): void {
        parent::setUp();

        $term = wp_insert_term('Ceramics', 'event_category');
        update_term_meta($term['term_id'], 'mindevents_category_color', '#aa0000');

        $this->event_id = $this->createEvent();
        wp_set_post_terms($this->event_id, array($term['term_id']), 'event_category');
    }

    public function test_an_occurrence_without_its_own_color_uses_the_category_color(): void {
        $occurrence_id = $this->createOccurrence($this->event_id, '2030-05-01');

        $this->assertSame(array('#aa0000'), mindevents_get_occurrence_colors($occurrence_id));
    }

    public function test_an_occurrence_color_overrides_the_category_color(): void {
        $occurrence_id = $this->createOccurrence($this->event_id, '2030-05-01', '19:00', '21:00', array('eventColor' => '#0000bb'));

        $this->assertSame(array('#0000bb'), mindevents_get_occurrence_colors($occurrence_id));
    }

    public function test_the_color_field_can_be_left_empty(): void {
        $this->actAs('administrator');

        $calendar_metabox = $this->captureMetabox();

        $this->assertStringNotContainsString('type="color"', $calendar_metabox, 'A native color input cannot submit an empty value.');
        $this->assertStringContainsString('mindevents-color-field', $calendar_metabox);
    }

    private function captureMetabox(): string {
        $GLOBALS['post'] = get_post($this->event_id);
        ob_start();
        (new mindeventsAdmin())->display_calendar_metabox(get_post($this->event_id));
        return ob_get_clean();
    }
}
