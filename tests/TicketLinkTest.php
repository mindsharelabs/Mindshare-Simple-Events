<?php

/**
 * A date's Buy Tickets link: its own ticket link, or its event's.
 */
class TicketLinkTest extends Mindshare_Events_TestCase {
    protected function tearDown(): void {
        remove_all_filters('mindevents_ticket_url');
        $GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];
        parent::tearDown();
    }

    /**
     * Make the request look like a visit to the event's page.
     */
    private function viewEventPage(int $event_id): void {
        $GLOBALS['wp_query'] = new WP_Query(array('p' => $event_id, 'post_type' => 'mind_events'));
    }

    public function test_a_date_uses_its_own_link_or_else_its_events(): void {
        $event_id = $this->createEvent();
        update_post_meta($event_id, 'mindevents_ticket_url', 'https://tickets.example.com/show');
        $plain    = $this->createOccurrence($event_id, '2030-05-01');
        $override = $this->createOccurrence($event_id, '2030-05-02', '19:00', '21:00', array('mindevents_ticket_url' => 'https://tickets.example.com/gala'));

        $this->assertSame('https://tickets.example.com/show', mindevents_get_occurrence_ticket_url($plain));
        $this->assertSame('https://tickets.example.com/gala', mindevents_get_occurrence_ticket_url($override));
    }

    public function test_there_is_no_link_when_neither_has_one(): void {
        $occurrence_id = $this->createOccurrence($this->createEvent(), '2030-05-01');

        $this->assertSame('', mindevents_get_occurrence_ticket_url($occurrence_id));
        $this->assertSame('', mindevents_ticket_button($occurrence_id));
        $this->assertStringNotContainsString('mindevents-ticket-button', (new mindEventCalendar())->get_cal_meta_html($occurrence_id, 0));
    }

    public function test_the_link_can_be_filtered(): void {
        $event_id      = $this->createEvent();
        $occurrence_id = $this->createOccurrence($event_id, '2030-05-01');

        add_filter('mindevents_ticket_url', function ($url, $id, $parent) use ($occurrence_id, $event_id) {
            return ($id === $occurrence_id && $parent === $event_id) ? 'https://box-office.example.com/' . $id : $url;
        }, 10, 3);

        $this->assertSame('https://box-office.example.com/' . $occurrence_id, mindevents_get_occurrence_ticket_url($occurrence_id));
    }

    public function test_links_that_are_not_web_addresses_are_not_saved(): void {
        $occurrence_id = $this->createOccurrence($this->createEvent(), '2030-05-01', '19:00', '21:00', array('mindevents_ticket_url' => 'javascript:alert(1)'));

        $this->assertSame('', get_post_meta($occurrence_id, 'mindevents_ticket_url', true));
    }

    public function test_the_event_settings_save_the_ticket_link(): void {
        $this->actAs('administrator');
        $event_id = $this->createEvent();

        $_POST = wp_slash(array(
            'mindevents_event_meta_nonce' => wp_create_nonce('mindevents_event_meta'),
            'event_meta'                  => array('mindevents_ticket_url' => 'https://tickets.example.com/show?id=1&date=2'),
        ));
        do_action('save_post_mind_events', $event_id, get_post($event_id), true);

        $this->assertSame('https://tickets.example.com/show?id=1&date=2', get_post_meta($event_id, 'mindevents_ticket_url', true));
    }

    public function test_the_occurrence_form_has_a_ticket_link_field(): void {
        $this->actAs('administrator');
        $occurrence_id = $this->createOccurrence($this->createEvent(), '2030-05-01', '19:00', '21:00', array('mindevents_ticket_url' => 'https://tickets.example.com/gala'));

        $form = $this->ajax('mindevents_editevent', array(
            'nonce'   => wp_create_nonce('mindevents_ajax'),
            'eventid' => $occurrence_id,
        ))['data']['html'];

        $this->assertStringContainsString('name="mindevents_ticket_url"', $form);
        $this->assertStringContainsString('value="https://tickets.example.com/gala"', $form);
    }

    public function test_on_the_events_page_the_dialog_offers_tickets_instead_of_the_page(): void {
        $event_id = $this->createEvent();
        update_post_meta($event_id, 'mindevents_ticket_url', 'https://tickets.example.com/show');
        $occurrence_id = $this->createOccurrence($event_id, '2030-05-01');

        $response = $this->ajax('mindevents_get_event_meta_html', array('eventid' => $occurrence_id, 'current_event' => $event_id));
        $dialog   = $response['data']['html'];

        $this->assertStringContainsString('href="https://tickets.example.com/show"', $dialog);
        $this->assertStringContainsString('Buy Tickets', $dialog);
        $this->assertStringNotContainsString(get_permalink($event_id), $dialog);
    }

    public function test_elsewhere_the_dialog_also_links_to_the_events_page(): void {
        $event_id = $this->createEvent();
        update_post_meta($event_id, 'mindevents_ticket_url', 'https://tickets.example.com/show');
        $occurrence_id = $this->createOccurrence($event_id, '2030-05-01');

        $dialog = $this->ajax('mindevents_get_event_meta_html', array('eventid' => $occurrence_id))['data']['html'];

        $this->assertStringContainsString('Buy Tickets', $dialog);
        $this->assertStringContainsString('href="' . get_permalink($event_id) . '"', $dialog);
    }

    public function test_list_cards_on_the_events_page_do_not_link_to_it(): void {
        $event_id = $this->createEvent();
        update_post_meta($event_id, 'mindevents_ticket_url', 'https://tickets.example.com/show');
        $occurrence_id = $this->createOccurrence($event_id, '2030-05-01');
        $calendar      = new mindEventCalendar($event_id);

        $this->assertStringContainsString(get_permalink($event_id), $calendar->get_list_item_html($occurrence_id));

        $this->viewEventPage($event_id);
        $card = $calendar->get_list_item_html($occurrence_id);

        $this->assertStringContainsString('Buy Tickets', $card);
        $this->assertStringNotContainsString(get_permalink($event_id), $card);
    }
}
