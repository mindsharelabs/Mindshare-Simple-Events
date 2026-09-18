<?php

class TranslationTest extends Mindshare_Events_TestCase {
    public function test_the_text_domain_is_loaded_before_anything_is_translated(): void {
        $this->assertSame(0, has_action('init', array(mindEvents::get_instance(), 'load_textdomain')));
    }

    public function test_the_plugin_header_declares_where_translations_live(): void {
        $header = get_file_data(MINDEVENTS_PLUGIN_FILE, array('domain' => 'Text Domain', 'path' => 'Domain Path'));

        $this->assertSame('simple-events', $header['domain']);
        $this->assertSame('/languages', $header['path']);
        $this->assertFileExists(MINDEVENTS_ABSPATH . 'languages/simple-events.pot');
    }

    /**
     * Every i18n.key a script reads must be provided, or it shows "undefined".
     */
    public function test_scripts_only_use_strings_that_are_provided(): void {
        $scripts = array(
            'js/admin.js'      => mindEvents::admin_script_strings(),
            'js/mindevents.js' => mindEvents::front_script_strings(),
        );

        foreach ($scripts as $file => $strings) {
            preg_match_all('/\bi18n\.(\w+)/', file_get_contents(MINDEVENTS_ABSPATH . $file), $used);

            $this->assertNotEmpty($used[1], "$file uses no translated strings");
            foreach (array_unique($used[1]) as $key) {
                $this->assertArrayHasKey($key, $strings, "$file reads i18n.$key");
            }
        }
    }

    public function test_scripts_contain_no_hardcoded_messages(): void {
        foreach (array('js/admin.js', 'js/mindevents.js') as $file) {
            $source = file_get_contents(MINDEVENTS_ABSPATH . $file);

            $this->assertDoesNotMatchRegularExpression('/[\'"`](Unable|Loading|Delete|Clear|Categories)\b/', $source, $file);
        }
    }
}
