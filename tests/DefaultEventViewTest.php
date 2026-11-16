<?php

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;

/**
 * An archive opens in the view the theme asks for, until the visitor picks
 * another. Separate processes, because the filter state is cached for the
 * whole request once it is first read.
 */
class DefaultEventViewTest extends Mindshare_Events_TestCase {
    private function viewArchive(array $get): void {
        $GLOBALS['wp_query']     = new WP_Query(array('post_type' => 'mind_events'));
        $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
        $_GET = $get;
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_an_archive_opens_in_month_by_default(): void {
        $this->viewArchive(array());

        $this->assertSame('month', mindevents_get_frontend_filters()['event_view']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_an_archive_opens_in_the_view_the_theme_asks_for(): void {
        add_filter('mindevents_default_event_view', function () {
            return 'list';
        });
        $this->viewArchive(array());

        $this->assertSame('list', mindevents_get_frontend_filters()['event_view']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_the_visitors_choice_wins(): void {
        add_filter('mindevents_default_event_view', function () {
            return 'list';
        });
        $this->viewArchive(array('event_view' => 'week'));

        $this->assertSame('week', mindevents_get_frontend_filters()['event_view']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_an_unknown_view_falls_back_to_month(): void {
        add_filter('mindevents_default_event_view', function () {
            return 'poster';
        });
        $this->viewArchive(array());

        $this->assertSame('month', mindevents_get_frontend_filters()['event_view']);
    }
}
