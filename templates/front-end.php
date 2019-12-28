<?php

add_filter('the_content', function ($content) {
  if( is_singular('events') && is_main_query() ) {
    $calendar = new mindEvent(get_the_ID());
    $display_type = get_post_meta(get_the_ID(), 'cal_display', true);
    $show_all = get_post_meta(get_the_ID(), 'show_past_events', true);
    $calendar->set_past_events_display($show_all);
    mapi_var_dump($show_all);

    if($display_type == 'calendar') :
      $new_content = '<div class="calendar-nav">';
        $new_content .= '<button data-dir="prev" class="calnav prev">PREV MONTH</button>';
        $new_content .= '<button data-dir="next" class="calnav next">NEXT MONTH</button>';
      $new_content .= '</div>';

      $new_content .= '<div id="publicCalendar">';
        $new_content .= $calendar->get_front_calendar();
      $new_content .= '</div>';

      $new_content = apply_filters(MINDRETURNS_PREPEND . 'mindevents_calendar', $new_content, $calendar);

    elseif('list') :
      $new_content = '<div id="mindEventList" class="mindevents-list">';
        $new_content .= $calendar->get_front_list();
      $new_content .= '</div>';
    endif;


    $content .= $new_content;
  }
  return $content;
});
