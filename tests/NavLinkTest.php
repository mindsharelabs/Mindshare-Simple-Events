<?php

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * Separate process: the archive filter state is cached per request.
 */
class NavLinkTest extends Mindshare_Events_TestCase {
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_calendar_nav_links_work_when_wordpress_is_in_a_subdirectory(): void {
        update_option('home', 'http://example.test/site');
        update_option('siteurl', 'http://example.test/site');

        $GLOBALS['wp_query']     = new WP_Query(array('post_type' => 'mind_events'));
        $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
        $_SERVER['REQUEST_URI']  = '/site/events/?event_view=month';
        $_GET                    = array('event_view' => 'month');

        $html = (new mindEventCalendar('', '2030-07-01'))->get_calendar_nav_links('month');

        preg_match_all('/href="([^"]+)"/', $html, $links);
        $this->assertCount(2, $links[1]);
        foreach ($links[1] as $link) {
            $this->assertStringStartsWith(get_post_type_archive_link('mind_events'), html_entity_decode($link));
            $this->assertStringNotContainsString('/site/site/', $link);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_single_event_nav_links_point_at_the_event(): void {
        update_option('home', 'http://example.test/site');
        update_option('siteurl', 'http://example.test/site');

        $event_id = $this->createEvent(array('post_name' => 'open-studio'));
        $GLOBALS['wp_query']     = new WP_Query(array('post_type' => 'mind_events', 'p' => $event_id));
        $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
        $_SERVER['REQUEST_URI']  = '/site/events/open-studio/';

        $html = (new mindEventCalendar($event_id, '2030-07-01'))->get_calendar_nav_links('month');

        preg_match('/href="([^"]+)"/', $html, $link);
        $this->assertStringStartsWith(get_permalink($event_id), html_entity_decode($link[1]));
        $this->assertStringNotContainsString('/site/site/', $link[1]);
    }
}
