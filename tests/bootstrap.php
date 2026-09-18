<?php
/**
 * Loads the WordPress install this plugin lives in.
 *
 * Tests run against that site's database. Each test is wrapped in a
 * transaction that is rolled back afterwards, so nothing a test writes is
 * kept. See Mindshare_Events_TestCase.
 *
 * Set WP_LOAD_PATH to point at a different wp-load.php.
 */

$wp_load = getenv('WP_LOAD_PATH') ?: dirname(__DIR__, 4) . '/wp-load.php';

if (!file_exists($wp_load)) {
    fwrite(STDERR, "Could not find wp-load.php at {$wp_load}. Set WP_LOAD_PATH.\n");
    exit(1);
}

define('WP_USE_THEMES', false);
$_SERVER['HTTP_HOST']   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';

require $wp_load;

if (!class_exists('mindEvents')) {
    fwrite(STDERR, "Mindshare Simple Events is not active on the site at {$wp_load}.\n");
    exit(1);
}

require __DIR__ . '/TestCase.php';
