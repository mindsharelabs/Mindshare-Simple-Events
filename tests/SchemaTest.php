<?php

class SchemaTest extends Mindshare_Events_TestCase {
    public function test_schema_cannot_close_its_script_block(): void {
        $this->actAs('administrator');
        $event_id = $this->createEvent(array('post_title' => 'Pottery </script><script>alert(1)</script>'));
        $this->createOccurrence($event_id, '2030-05-01');

        $json = (new mindEventCalendar($event_id))->generate_schema();

        $this->assertStringNotContainsStringIgnoringCase('</script', $json);
        $this->assertStringNotContainsString('<script', $json);
    }

    public function test_schema_is_still_valid_json_with_the_original_text(): void {
        $this->actAs('administrator');
        $event_id = $this->createEvent(array('post_title' => 'Pottery </script> Night'));
        $this->createOccurrence($event_id, '2030-05-01');

        $schema = json_decode((new mindEventCalendar($event_id))->generate_schema(), true);

        $this->assertSame('Pottery </script> Night', $schema['name']);
    }
}
