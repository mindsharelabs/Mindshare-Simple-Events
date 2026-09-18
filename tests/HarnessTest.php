<?php

use PHPUnit\Framework\Attributes\Depends;

/**
 * Guards the harness itself: if rollback stops working, every other test
 * would silently start writing to the site's database.
 */
class HarnessTest extends Mindshare_Events_TestCase {
    private static $created_id = 0;

    public function test_writes_inside_a_test_are_visible_to_that_test(): void {
        self::$created_id = $this->createEvent(array('post_title' => 'Harness probe'));

        $this->assertSame('Harness probe', get_the_title(self::$created_id));
    }

    #[Depends('test_writes_inside_a_test_are_visible_to_that_test')]
    public function test_writes_are_rolled_back_after_each_test(): void {
        $this->assertNull(get_post(self::$created_id));
    }
}
