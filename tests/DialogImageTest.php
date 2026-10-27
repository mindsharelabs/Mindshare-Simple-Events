<?php

/**
 * The image at the top of a date's dialog, and the close button over it.
 */
class DialogImageTest extends Mindshare_Events_TestCase {
    protected function tearDown(): void {
        remove_all_filters('mindevents_dialog_image_id');
        parent::tearDown();
    }

    private function createImage(string $name): int {
        $image_id = wp_insert_attachment(array(
            'post_mime_type' => 'image/jpeg',
            'post_title'     => $name,
            'post_status'    => 'inherit',
        ), $name . '.jpg');

        $this->assertIsInt($image_id);

        return $image_id;
    }

    public function test_the_dialog_shows_the_events_featured_image(): void {
        $event_id = $this->createEvent();
        set_post_thumbnail($event_id, $this->createImage('featured'));
        $occurrence_id = $this->createOccurrence($event_id, '2030-05-01');

        $dialog = (new mindEventCalendar())->get_cal_meta_html($occurrence_id, 0);

        $this->assertStringContainsString('featured.jpg', $dialog);
        $this->assertStringContainsString('mindevents-event-meta has-image', $dialog);
    }

    public function test_the_image_can_be_swapped_with_a_filter(): void {
        $event_id = $this->createEvent();
        set_post_thumbnail($event_id, $this->createImage('featured'));
        $banner_id     = $this->createImage('banner');
        $occurrence_id = $this->createOccurrence($event_id, '2030-05-01');

        add_filter('mindevents_dialog_image_id', function ($image_id, $id, $parent) use ($banner_id, $occurrence_id, $event_id) {
            return ($id === $occurrence_id && $parent === $event_id) ? $banner_id : $image_id;
        }, 10, 3);

        $dialog = (new mindEventCalendar())->get_cal_meta_html($occurrence_id, 0);

        $this->assertStringContainsString('banner.jpg', $dialog);
        $this->assertStringNotContainsString('featured.jpg', $dialog);
    }

    public function test_without_an_image_the_dialog_still_has_its_close_button(): void {
        $occurrence_id = $this->createOccurrence($this->createEvent(), '2030-05-01');

        $dialog = (new mindEventCalendar())->get_cal_meta_html($occurrence_id, 0);

        $this->assertStringNotContainsString('has-image', $dialog);
        $this->assertStringNotContainsString('<img', $dialog);
        $this->assertStringContainsString('class="event-meta-close"', $dialog);
    }

    public function test_the_close_button_comes_first_in_the_tab_order(): void {
        $occurrence_id = $this->createOccurrence($this->createEvent(), '2030-05-01');

        $dialog = (new mindEventCalendar())->get_cal_meta_html($occurrence_id, 0);

        $this->assertMatchesRegularExpression('/^<article[^>]*><button type="button" class="event-meta-close"/', $dialog);
    }
}
