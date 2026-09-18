<?php

class SettingsPageTest extends Mindshare_Events_TestCase {
    private function renderSettingsPage(): string {
        // The settings API lives in admin-only includes.
        require_once ABSPATH . 'wp-admin/includes/template.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        ob_start();
        (new mindEventsOptions())->render_settings_page();
        return ob_get_clean();
    }

    public function test_a_fixed_utc_offset_is_flagged(): void {
        update_option('timezone_string', '');
        update_option('gmt_offset', -7);

        $this->assertStringContainsString('mindevents-timezone-notice', $this->renderSettingsPage());
    }

    public function test_a_city_timezone_is_not_flagged(): void {
        update_option('timezone_string', 'America/Denver');

        $this->assertStringNotContainsString('mindevents-timezone-notice', $this->renderSettingsPage());
    }

    public function test_utc_itself_is_not_flagged(): void {
        update_option('timezone_string', 'UTC');

        $this->assertStringNotContainsString('mindevents-timezone-notice', $this->renderSettingsPage());
    }
}
