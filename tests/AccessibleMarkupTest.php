<?php

class AccessibleMarkupTest extends Mindshare_Events_TestCase {
    private function dom(string $html): DOMXPath {
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>');

        return new DOMXPath($dom);
    }

    public function test_day_numbers_are_dates_not_buttons_that_do_nothing(): void {
        $html  = (new mindEventCalendar($this->createEvent(), '2030-07-01'))->render();
        $xpath = $this->dom($html);

        $this->assertSame(0, $xpath->query("//button[contains(@class,'mindevents-calendar-day-number')]")->length);
        $this->assertSame(31, $xpath->query("//time[contains(@class,'mindevents-calendar-day-number')][@datetime]")->length);
        $this->assertSame('2030-07-04', $xpath->query("//time[contains(@class,'mindevents-calendar-day-number')]")->item(3)->getAttribute('datetime'));
    }

    public function test_add_to_calendar_button_names_the_menu_it_controls(): void {
        $occurrence_id = $this->createOccurrence($this->createEvent(), '2030-05-01');
        $xpath         = $this->dom(mindevents_get_event_add_to_calendar_links($occurrence_id));

        $button = $xpath->query("//button[contains(@class,'add-to-calendar-button')]")->item(0);
        $menu   = $xpath->query("//*[@id='" . $button->getAttribute('aria-controls') . "']");

        $this->assertNotSame('', $button->getAttribute('aria-controls'));
        $this->assertSame(1, $menu->length);
        $this->assertSame('false', $button->getAttribute('aria-expanded'));
    }

    public function test_event_detail_has_a_heading_to_label_the_dialog(): void {
        $occurrence_id = $this->createOccurrence($this->createEvent(array('post_title' => 'Glaze Night')), '2030-05-01');
        $xpath         = $this->dom((new mindEventCalendar())->get_cal_meta_html($occurrence_id));

        $this->assertSame('Glaze Night', trim($xpath->query("//*[contains(@class,'mindevents-event-meta__title')]")->item(0)->textContent));
    }

    public function test_each_day_carries_its_weekday_name_and_whether_it_has_events(): void {
        $event_id = $this->createEvent();
        $this->createOccurrence($event_id, '2030-07-04');

        $html  = (new mindEventCalendar($event_id, '2030-07-01'))->get_calendar();
        $xpath = $this->dom($html);

        $fourth = $xpath->query("//*[@data-date='2030-07-04']")->item(0);
        $fifth  = $xpath->query("//*[@data-date='2030-07-05']")->item(0);

        $this->assertStringContainsString('has-events', $fourth->getAttribute('class'));
        $this->assertStringNotContainsString('has-events', $fifth->getAttribute('class'));
        $this->assertSame(wp_date('l', strtotime('2030-07-04')), trim($xpath->query(".//*[contains(@class,'mindevents-calendar-day-name')]", $fourth)->item(0)->textContent));
    }
}
