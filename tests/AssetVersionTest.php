<?php

/**
 * Asset URLs change whenever the file does, so browsers and caches never
 * keep serving an old script or stylesheet after an update.
 */
class AssetVersionTest extends Mindshare_Events_TestCase {
    public function test_each_asset_is_versioned_by_its_file(): void {
        foreach (array('js/mindevents.js', 'js/admin.js', 'css/style.css', 'css/admin.css') as $file) {
            $this->assertSame(
                (string) filemtime(MINDEVENTS_ABSPATH . $file),
                mindevents_asset_version($file),
                $file
            );
        }
    }

    public function test_a_missing_file_falls_back_to_the_plugin_version(): void {
        $this->assertSame(MINDEVENTS_PLUGIN_VERSION, mindevents_asset_version('js/does-not-exist.js'));
    }
}
