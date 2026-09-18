<?php

/**
 * The organizer image is chosen from the media library rather than typed
 * in as an attachment ID.
 */
class OrganizerImageFieldTest extends Mindshare_Events_TestCase {
    private $attachment_id;

    protected function setUp(): void {
        parent::setUp();
        $this->actAs('administrator');
        $this->attachment_id = wp_insert_attachment(array(
            'post_mime_type' => 'image/png',
            'post_title'     => 'Ana',
            'post_status'    => 'inherit',
        ), 'ana.png');
    }

    private function dom(string $html): DOMXPath {
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>');

        return new DOMXPath($dom);
    }

    private function assertIsImagePicker(DOMXPath $xpath, string $name, string $value): void {
        $this->assertSame(0, $xpath->query("//input[@type='number'][@name='$name']")->length, 'No raw ID field');

        $input = $xpath->query("//*[contains(@class,'mindevents-image-field')]//input[@type='hidden'][@name='$name']")->item(0);
        $this->assertNotNull($input);
        $this->assertSame($value, $input->getAttribute('value'));
        $this->assertSame(1, $xpath->query("//*[contains(@class,'mindevents-image-field')]//button[contains(@class,'mindevents-image-choose')]")->length);
    }

    public function test_event_settings_use_the_media_library(): void {
        $event_id = $this->createEvent();
        update_post_meta($event_id, 'mindevents_organizer_image_id', $this->attachment_id);
        $GLOBALS['post'] = get_post($event_id);

        ob_start();
        (new mindeventsAdmin())->display_event_options_metabox();
        $xpath = $this->dom(ob_get_clean());

        $this->assertIsImagePicker($xpath, 'event_meta[mindevents_organizer_image_id]', (string) $this->attachment_id);
        $remove = $xpath->query("//button[contains(@class,'mindevents-image-remove')]")->item(0);
        $this->assertFalse($remove->hasAttribute('hidden'), 'Remove is offered when an image is set');
    }

    public function test_the_occurrence_dialog_uses_the_media_library(): void {
        $occurrence_id = $this->createOccurrence($this->createEvent(), '2030-05-01');

        $html  = $this->ajax('mindevents_editevent', array('nonce' => wp_create_nonce('mindevents_ajax'), 'eventid' => $occurrence_id))['data']['html'];
        $xpath = $this->dom($html);

        $this->assertIsImagePicker($xpath, 'mindevents_organizer_image_id', '');
        $remove = $xpath->query("//button[contains(@class,'mindevents-image-remove')]")->item(0);
        $this->assertTrue($remove->hasAttribute('hidden'), 'Nothing to remove when no image is set');
    }
}
