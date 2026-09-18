<?php

/**
 * JSON-LD, ICS, calendar links and the REST API are data, not HTML. They
 * must carry text as written, not display entities such as &#038;.
 */
class PlainTextOutputTest extends Mindshare_Events_TestCase {
    private $event_id;
    private $occurrence_id;

    protected function setUp(): void {
        parent::setUp();
        $this->event_id      = $this->createEvent(array('post_title' => 'Clay & Glass', 'post_excerpt' => "Bring an apron & some old clothes"));
        $this->occurrence_id = $this->createOccurrence($this->event_id, '2030-05-01');
    }

    public function test_schema_uses_plain_text(): void {
        $schema = json_decode((new mindEventCalendar($this->event_id))->generate_schema(), true);

        $this->assertSame('Clay & Glass', $schema['name']);
        $this->assertSame('Clay & Glass', $schema['subEvent'][0]['name']);
        $this->assertSame('Bring an apron & some old clothes', $schema['description']);
    }

    public function test_ics_uses_plain_text(): void {
        $ics = mindevents_generate_single_event_ics($this->occurrence_id);

        $this->assertStringContainsString('SUMMARY:Clay & Glass', $ics);
        $this->assertStringNotContainsString('&#', $ics);
    }

    public function test_calendar_links_carry_the_whole_title(): void {
        $html = mindevents_get_event_add_to_calendar_links($this->occurrence_id);

        preg_match('#href="(https://calendar\.google\.com[^"]+)"#', $html, $match);
        parse_str((string) wp_parse_url(html_entity_decode($match[1]), PHP_URL_QUERY), $query);

        $this->assertSame('Clay & Glass', $query['text']);
        $this->assertSame('Bring an apron & some old clothes', $query['details']);
    }

    public function test_rest_api_uses_plain_text(): void {
        $payload = mindevents_rest_event_payload($this->occurrence_id);

        $this->assertSame('Clay & Glass', $payload['title']);
    }
}
