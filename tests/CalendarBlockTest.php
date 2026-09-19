<?php

class CalendarBlockTest extends Mindshare_Events_TestCase {
    protected function tearDown(): void {
        unset($_GET['calendar_date']);
        parent::tearDown();
    }

    private function renderBlock(array $attrs): string {
        return render_block(array(
            'blockName'    => 'simple-events/calendar',
            'attrs'        => $attrs,
            'innerBlocks'  => array(),
            'innerHTML'    => '',
            'innerContent' => array(),
        ));
    }

    private function eventInCategory(string $title, string $category): int {
        $event_id = $this->createEvent(array('post_title' => $title));
        $term     = term_exists($category, 'mind_event_category') ?: wp_insert_term($category, 'mind_event_category');
        wp_set_post_terms($event_id, array((int) $term['term_id']), 'mind_event_category');

        return $event_id;
    }

    public function test_the_block_is_registered_with_its_settings(): void {
        $block = WP_Block_Type_Registry::get_instance()->get_registered('simple-events/calendar');

        $this->assertNotNull($block);
        $this->assertSame(array('display', 'categories', 'event'), array_keys(array_intersect_key($block->attributes, array_flip(array('display', 'categories', 'event')))));
        $this->assertContains('mindevents-frontend', $block->view_script_handles);
        $this->assertContains('mindevents-frontend', $block->style_handles);
    }

    public function test_each_display_type_renders_its_view(): void {
        $event_id = $this->createEvent();
        $this->createOccurrence($event_id, '2040-07-20');
        $_GET['calendar_date'] = '2040-07-01';

        $this->assertStringContainsString('mindevents-calendar-day', $this->renderBlock(array('display' => 'calendar')));
        $this->assertStringContainsString('mindevents-list-card', $this->renderBlock(array('display' => 'list')));
        $this->assertStringContainsString('mindevents-mini-calendar', $this->renderBlock(array('display' => 'mini')));
    }

    public function test_the_output_carries_the_plugin_styles_scope(): void {
        $this->assertStringContainsString('mindevents-shell mindevents-shell--block', $this->renderBlock(array()));
    }

    public function test_categories_narrow_the_events_shown(): void {
        $this->createOccurrence($this->eventInCategory('Pottery Night', 'Clay'), '2040-07-20');
        $this->createOccurrence($this->eventInCategory('Welding Basics', 'Metal'), '2040-07-21');
        $clay = term_exists('Clay', 'mind_event_category');

        $html = $this->renderBlock(array('display' => 'list', 'categories' => array((int) $clay['term_id'])));

        $this->assertStringContainsString('Pottery Night', $html);
        $this->assertStringNotContainsString('Welding Basics', $html);
    }

    public function test_a_category_with_no_events_shows_nothing_rather_than_everything(): void {
        $this->createOccurrence($this->createEvent(array('post_title' => 'Pottery Night')), '2040-07-20');
        $empty = wp_insert_term('Glass', 'mind_event_category');

        $html = $this->renderBlock(array('display' => 'list', 'categories' => array((int) $empty['term_id'])));

        $this->assertStringNotContainsString('Pottery Night', $html);
        $this->assertStringContainsString('There are no upcoming events.', $html);
    }

    public function test_one_event_shows_only_its_own_dates(): void {
        $pottery = $this->createEvent(array('post_title' => 'Pottery Night'));
        $this->createOccurrence($pottery, '2040-07-20');
        $this->createOccurrence($this->createEvent(array('post_title' => 'Welding Basics')), '2040-07-21');

        $html = $this->renderBlock(array('display' => 'mini', 'event' => $pottery));

        $this->assertStringContainsString('Pottery Night', $html);
        $this->assertStringNotContainsString('Welding Basics', $html);
    }

    public function test_an_unpublished_event_renders_nothing(): void {
        $draft = $this->createEvent(array('post_status' => 'draft'));
        $this->createOccurrence($draft, '2040-07-20');

        $this->assertSame('', $this->renderBlock(array('event' => $draft)));
    }

    public function test_lists_of_all_events_show_upcoming_dates_only(): void {
        $event_id = $this->createEvent(array('post_title' => 'Pottery Night'));
        $this->createOccurrence($event_id, '2020-07-20');
        $this->createOccurrence($event_id, '2040-07-20');

        foreach (array('list', 'mini') as $display) {
            $html = $this->renderBlock(array('display' => $display));
            $this->assertStringContainsString('2040', $html, $display);
            $this->assertStringNotContainsString('2020', $html, $display);
        }
    }

    public function test_mini_calendar_days_link_to_their_event(): void {
        $event_id = $this->createEvent();
        $this->createOccurrence($event_id, '2040-07-20');

        $this->assertStringContainsString(get_permalink($event_id), $this->renderBlock(array('display' => 'mini')));
    }
}
