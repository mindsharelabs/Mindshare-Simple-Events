<?php

class RegistrationTest extends Mindshare_Events_TestCase {
    public function test_occurrences_are_not_addressable_on_the_front_end(): void {
        $type = get_post_type_object('mind_sub_event');

        $this->assertFalse($type->publicly_queryable);
        $this->assertFalse($type->rewrite);
        $this->assertFalse($type->query_var);
    }

    public function test_an_occurrence_url_does_not_resolve_to_the_occurrence(): void {
        $occurrence_id = $this->createOccurrence($this->createEvent(), '2030-05-01');

        // As a visitor's ?post_type=sub_event&p=ID would arrive.
        $_GET = array('post_type' => 'mind_sub_event', 'p' => $occurrence_id);
        $wp   = new WP();
        $wp->parse_request();

        $this->assertArrayNotHasKey('post_type', $wp->query_vars, 'post_type=sub_event should not be accepted from a URL');
    }
}
