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
  return $template;
});
