<?php

class CategoryColorTest extends Mindshare_Events_TestCase {
    private $term_id;

    protected function setUp(): void {
        parent::setUp();
        $this->actAs('administrator');
        $this->term_id = wp_insert_term('Ceramics', 'mind_event_category')['term_id'];
        update_term_meta($this->term_id, 'mindevents_category_color', '#aa0000');
    }

    public function test_renaming_a_category_elsewhere_keeps_its_color(): void {
        // Quick Edit, the REST API and code all save terms without the color field.
        wp_update_term($this->term_id, 'mind_event_category', array('name' => 'Pottery'));

        $this->assertSame('#aa0000', get_term_meta($this->term_id, 'mindevents_category_color', true));
    }

    public function test_the_category_form_saves_the_color(): void {
        $_POST = wp_slash(array(
            'mindevents_category_color'       => '#00bb00',
            'mindevents_category_color_nonce' => wp_create_nonce('mindevents_category_color'),
        ));

        wp_update_term($this->term_id, 'mind_event_category', array('name' => 'Pottery'));

        $this->assertSame('#00bb00', get_term_meta($this->term_id, 'mindevents_category_color', true));
    }

    public function test_a_forged_request_cannot_change_the_color(): void {
        $_POST = wp_slash(array('mindevents_category_color' => '#00bb00'));

        wp_update_term($this->term_id, 'mind_event_category', array('name' => 'Pottery'));

        $this->assertSame('#aa0000', get_term_meta($this->term_id, 'mindevents_category_color', true));
    }
}
