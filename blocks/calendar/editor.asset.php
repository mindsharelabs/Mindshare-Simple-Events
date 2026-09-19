<?php
// Dependencies of editor.js, which uses the editor's globals and needs no build step.
return array(
    'dependencies' => array('wp-blocks', 'wp-block-editor', 'wp-components', 'wp-core-data', 'wp-data', 'wp-element', 'wp-html-entities', 'wp-i18n', 'wp-server-side-render'),
    'version'      => (string) filemtime(__DIR__ . '/editor.js'),
);
