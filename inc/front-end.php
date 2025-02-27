<?php

add_filter('template_include', function ( $template ) {
  if ( is_post_type_archive('events') ) {
    $theme_files = array('archive-events.php', 'templates/archive-events.php');
    $exists_in_theme = locate_template($theme_files, false);
    if ( $exists_in_theme != '' ) {
      return $exists_in_theme;
    } else {
      return MINDEVENTS_ABSPATH . 'templates/archive-events.php';
    }
  }
  if ( is_singular('events') ) {
    $theme_files = array('single-events.php', 'templates/single-events.php');
    $exists_in_theme = locate_template($theme_files, false);
    if ( $exists_in_theme != '' ) {
      return $exists_in_theme;
    } else {
      return MINDEVENTS_ABSPATH . 'templates/single-events.php';
    }
  }

  if ( is_tax( 'event_category') ) {
    $theme_files = array('taxonomy-event-category.php', 'templates/taxonomy-event-category.php');
    $exists_in_theme = locate_template($theme_files, false);
    if ( $exists_in_theme != '' ) {
      return $exists_in_theme;
    } else {
      return MINDEVENTS_ABSPATH . 'templates/taxonomy-event-category.php';
    }
  }
  return $template;
});


add_action(MINDEVENTS_PREPEND . 'single_title', 'mind_events_single_title', 10, 1);
function mind_events_single_title($id) {
    echo '<h1>' . get_the_title($id) . '</h1>';
}

add_action(MINDEVENTS_PREPEND . 'single_title', MINDEVENTS_PREPEND . 'single_datespan', 20, 1);
function mindevents_single_datespan($id) {
    $first_event = new DateTime(get_post_meta($id, 'first_event_date', true));
    $startdate = $first_event->format('F j, Y');

    $last_event = new DateTime(get_post_meta($id, 'last_event_date', true));
    $enddate = $last_event->format('F j, Y');

    echo '<div class="event-datespan">';
      echo apply_filters(MINDEVENTS_PREPEND . 'single_datespan', '<span class="start-date">' . $startdate . '</span> - <span class="end-date">' . $enddate . '</span>', $startdate, $enddate);
    echo '</div>';
}


//
add_action(MINDEVENTS_PREPEND . 'single_content', function ($id) {
  $excerpt = get_the_excerpt($id);
  if($excerpt) :
    echo '<div class="content-wrap excerpt">';
      echo $excerpt;
    echo '</div>';
  endif;
}, 10, 1);



add_action( MINDEVENTS_PREPEND . 'single_before_events', function() {
  echo '<div class="content">';
    the_content();
  echo '</div>';
}, 1);
