<?php

/**
 * iCalendar output follows RFC 5545: TEXT values are escaped and content
 * lines are folded at 75 octets.
 */
class IcsFormatTest extends Mindshare_Events_TestCase {
    private $ics;

    protected function setUp(): void {
        parent::setUp();

        $event_id = $this->createEvent(array(
            'post_title'   => 'Clay, Glass; and \\ Wood',
            'post_excerpt' => str_repeat('Céramique et verre — ', 8),
        ));
        $occurrence_id = $this->createOccurrence($event_id, '2030-05-01', '19:00', '21:00', array('mindevents_location' => 'Studio A, Room 2'));

        $this->ics = mindevents_generate_single_event_ics($occurrence_id);
    }

    /**
     * Undo line folding and return each property's raw value.
     */
    private function properties(): array {
        $unfolded   = preg_replace("/\r\n[ \t]/", '', $this->ics);
        $properties = array();

        foreach (explode("\r\n", trim($unfolded)) as $line) {
            list($name, $value) = explode(':', $line, 2);
            $properties[$name] = $value;
        }

        return $properties;
    }

    private function unescape(string $value): string {
        return strtr($value, array('\\\\' => '\\', '\;' => ';', '\\,' => ',', '\\n' => "\n"));
    }

    public function test_text_values_are_escaped(): void {
        $properties = $this->properties();

        $this->assertSame('Clay\\, Glass\; and \\\\ Wood', $properties['SUMMARY']);
        $this->assertSame('Studio A\\, Room 2', $properties['LOCATION']);
        $this->assertSame('Clay, Glass; and \\ Wood', $this->unescape($properties['SUMMARY']));
    }

    public function test_no_line_exceeds_75_octets(): void {
        foreach (explode("\r\n", $this->ics) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line), "Too long: $line");
        }
    }

    public function test_folding_never_splits_a_multibyte_character(): void {
        foreach (explode("\r\n", $this->ics) as $line) {
            $this->assertTrue(mb_check_encoding($line, 'UTF-8'), "Split character in: $line");
        }

        $this->assertSame(trim(str_repeat('Céramique et verre — ', 8)), $this->unescape($this->properties()['DESCRIPTION']));
    }

    public function test_every_line_ends_with_crlf(): void {
        $this->assertStringEndsWith("\r\n", $this->ics);
        $this->assertSame(0, preg_match("/(?<!\r)\n/", $this->ics));
    }
}
