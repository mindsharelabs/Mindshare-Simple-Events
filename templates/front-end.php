<?php

add_filter('the_content', function ($content) {
  if( is_singular('events') && is_main_query() ) {
    $calendar = new mindEvent(get_the_ID());
    $new_content = '<div class="calendar-nav">';
      $new_content .= '<button data-dir="prev" class="calnav prev">PREV MONTH</button>';
      $new_content .= '<button data-dir="next" class="calnav next">NEXT MONTH</button>';
    $new_content .= '</div>';
    $new_content .= '<div id="publicCalendar">';
      $new_content .= $calendar->get_front_calendar();
    $new_content .= '</div>';
    $new_content = apply_filters(MINDRETURNS_PREPEND . 'public_calendar', $new_content, $calendar);
    $content .= $new_content;
  }
  return $content;
});
