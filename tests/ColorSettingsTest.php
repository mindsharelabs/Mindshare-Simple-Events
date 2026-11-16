<?php

/**
 * The colors set on the settings page replace the stylesheet's own, and
 * anything that isn't a color is left out.
 */
class ColorSettingsTest extends Mindshare_Events_TestCase {
    public function test_no_colors_set_means_no_css(): void {
        delete_option('mindevents_support_settings');

        $this->assertSame('', mindevents_color_css());
    }

    public function test_set_colors_become_css_variables(): void {
        update_option('mindevents_support_settings', array(
            'mindevents_color_accent' => '#f36e29',
            'mindevents_color_ink'    => '#24242c',
        ));

        $css = mindevents_color_css();

        $this->assertStringContainsString('--mindevents-accent: #f36e29;', $css);
        $this->assertStringContainsString('--mindevents-ink: #24242c;', $css);
        $this->assertStringNotContainsString('--mindevents-border', $css);
        $this->assertStringStartsWith('.mindevents-shell, .mindevents-modal,', $css);
    }

    public function test_saving_keeps_colors_and_drops_anything_else(): void {
        $saved = (new mindEventsOptions())->sanitize_settings(array(
            'mindevents_color_accent'    => ' #F36E29 ',
            'mindevents_color_highlight' => 'orange; } body { display: none',
            'mindevents_color_ink'       => '',
        ));

        $this->assertSame('#F36E29', $saved['mindevents_color_accent']);
        $this->assertSame('', $saved['mindevents_color_highlight']);
        $this->assertSame('', $saved['mindevents_color_ink']);
        $this->assertSame('Monday', $saved['mindevents_start_day']);
    }

    public function test_a_bad_stored_color_is_left_out(): void {
        update_option('mindevents_support_settings', array(
            'mindevents_color_accent' => 'red; } body { display: none',
        ));

        $this->assertSame('', mindevents_color_css());
    }
}
