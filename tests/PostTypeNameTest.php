<?php

/**
 * The post types are prefixed so they cannot collide with another plugin's
 * or theme's, while their public URLs stay as they were.
 */
class PostTypeNameTest extends Mindshare_Events_TestCase {
    public function test_post_types_are_prefixed(): void {
        $this->assertTrue(post_type_exists('mind_events'));
        $this->assertTrue(post_type_exists('mind_sub_event'));
        $this->assertFalse(post_type_exists('events'));
        $this->assertFalse(post_type_exists('sub_event'));
    }

    public function test_the_category_taxonomy_is_prefixed_and_keeps_its_urls(): void {
        $this->assertTrue(taxonomy_exists('mind_event_category'));
        $this->assertFalse(taxonomy_exists('event_category'));

        if (get_option('permalink_structure')) {
            $term = wp_insert_term('Ceramics', 'mind_event_category');
            $this->assertStringEndsWith('/event_category/ceramics/', get_term_link($term['term_id']));
        }
    }

    public function test_event_urls_still_use_the_events_slug(): void {
        global $wp_rewrite;
        $structure = get_option('permalink_structure');
        if (!$structure) {
            $this->markTestSkipped('The site uses plain permalinks.');
        }

        $event_id = $this->createEvent(array('post_name' => 'glaze-night'));

        $this->assertStringEndsWith('/events/', get_post_type_archive_link('mind_events'));
        $this->assertStringEndsWith('/events/glaze-night/', get_permalink($event_id));
        $this->assertSame('events', $wp_rewrite->get_extra_permastruct('mind_events') ? explode('/', trim($wp_rewrite->get_extra_permastruct('mind_events'), '/'))[0] : '');
    }
}
