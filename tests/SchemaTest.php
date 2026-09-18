<?php

class SchemaTest extends Mindshare_Events_TestCase {
    /**
     * An author writes &lt;/script&gt; to show the literal text. Schema
     * values are plain text, so that decodes to a real </script>, which the
     * encoding must still keep inside the block.
     */
    public function test_schema_cannot_close_its_script_block(): void {
        $this->actAs('administrator');
        $event_id = $this->createEvent(array('post_title' => 'Pottery &lt;/script&gt;&lt;script&gt;alert(1)&lt;/script&gt;'));
        $this->createOccurrence($event_id, '2030-05-01');

        $json = (new mindEventCalendar($event_id))->generate_schema();

        $this->assertStringNotContainsStringIgnoringCase('</script', $json);
        $this->assertStringNotContainsStringIgnoringCase('<script', $json);
    }

    public function test_schema_decodes_to_the_text_the_author_wrote(): void {
        $this->actAs('administrator');
        $event_id = $this->createEvent(array('post_title' => 'Pottery &lt;/script&gt; Night'));
        $this->createOccurrence($event_id, '2030-05-01');

        $schema = json_decode((new mindEventCalendar($event_id))->generate_schema(), true);

        $this->assertSame('Pottery </script> Night', $schema['name']);
    }
}
