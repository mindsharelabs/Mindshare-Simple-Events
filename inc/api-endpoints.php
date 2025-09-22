<?php

/**
 * Make Santa Fe Events REST API v2
 * Public endpoint with rich filtering for advertising, apps, etc.
 * Route: /wp-json/make/events
 */

add_action( 'rest_api_init', 'make_events_routes_v2' );
function make_events_routes_v2() {
  register_rest_route(
    'make',
    '/events',
    array(
      'methods'  => array( WP_REST_Server::READABLE ),
      'callback' => 'make_events',
      'permission_callback' => '__return_true', // All info is public
      'args' => array(
        'per_page' => array(
          'description' => 'Number of events to return per page (1–100).',
          'type'        => 'integer',
          'default'     => 20,
          'minimum'     => 1,
          'maximum'     => 100,
        ),
        'page' => array(
          'description' => 'Results page (1-indexed).',
          'type'        => 'integer',
          'default'     => 1,
          'minimum'     => 1,
        ),
        'status' => array(
          'description' => 'Event time window filter: upcoming | past | all',
          'type'        => 'string',
          'default'     => 'upcoming',
          'enum'        => array( 'upcoming', 'past', 'all' ),
        ),
        'after' => array(
          'description' => 'ISO8601 datetime. Return events starting on/after this time.',
          'type'        => 'string',
        ),
        'before' => array(
          'description' => 'ISO8601 datetime. Return events starting on/before this time.',
          'type'        => 'string',
        ),
        'orderby' => array(
          'description' => 'Sort key: start_time | end_time | title | date',
          'type'        => 'string',
          'default'     => 'start_time',
          'enum'        => array( 'start_time', 'end_time', 'title', 'date' ),
        ),
        'order' => array(
          'description' => 'Sort order ASC | DESC',
          'type'        => 'string',
          'default'     => 'ASC',
          'enum'        => array( 'ASC', 'DESC' ),
        ),
        'categories' => array(
          'description' => 'Comma-separated event_category slugs applied to sub_events.',
          'type'        => 'string',
        ),
        'search' => array(
          'description' => 'Search term applied to the parent event title/content/excerpt.',
          'type'        => 'string',
        ),
        'parent' => array(
          'description' => 'Limit to a specific parent event ID (or comma-separated IDs).',
          'type'        => 'string',
        ),
        'include' => array(
          'description' => 'Comma-separated sub_event IDs to include (overrides other filters except order/paging).',
          'type'        => 'string',
        ),
        'exclude' => array(
          'description' => 'Comma-separated sub_event IDs to exclude.',
          'type'        => 'string',
        ),
      ),
    )
  );
}

/**
 * REST callback: /make/events
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function make_events( $request ) {
  // Helper: parse CSV of ints
  $csv_to_ints = function( $val ) {
    if ( empty( $val ) ) return array();
    $parts = array_map( 'trim', explode( ',', (string) $val ) );
    $ints  = array();
    foreach ( $parts as $p ) {
      $n = absint( $p );
      if ( $n ) $ints[] = $n;
    }
    return array_values( array_unique( $ints ) );
  };

  // Helper: ISO8601 -> MySQL DATETIME (site tz)
  $iso_to_mysql = function( $val ) {
    if ( empty( $val ) ) return '';
    $ts = strtotime( (string) $val );
    if ( ! $ts ) return '';
    return gmdate( 'Y-m-d H:i:s', $ts + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
  };

  $per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
  $page     = max( 1, (int) $request->get_param( 'page' ) );
  $status   = $request->get_param( 'status' ) ?: 'upcoming';
  $after    = $iso_to_mysql( $request->get_param( 'after' ) );
  $before   = $iso_to_mysql( $request->get_param( 'before' ) );
  $orderby  = $request->get_param( 'orderby' ) ?: 'start_time';
  $order    = ( strtoupper( (string) $request->get_param( 'order' ) ) === 'DESC' ) ? 'DESC' : 'ASC';
  $cats     = sanitize_text_field( (string) $request->get_param( 'categories' ) );
  $search   = sanitize_text_field( (string) $request->get_param( 'search' ) );
  $parent   = $csv_to_ints( $request->get_param( 'parent' ) );
  $include  = $csv_to_ints( $request->get_param( 'include' ) );
  $exclude  = $csv_to_ints( $request->get_param( 'exclude' ) );

  // Determine base meta_query based on status/after/before
  $now_mysql = current_time( 'mysql' );
  $meta_query = array( 'relation' => 'AND' );

  // Start bound
  if ( $after ) {
    $meta_query[] = array(
      'key' => 'event_start_time_stamp',
      'value' => $after,
      'compare' => '>=',
      'type' => 'DATETIME',
    );
  } elseif ( $status === 'upcoming' ) {
    $meta_query[] = array(
      'key' => 'event_start_time_stamp',
      'value' => $now_mysql,
      'compare' => '>=',
      'type' => 'DATETIME',
    );
  } elseif ( $status === 'past' ) {
    $meta_query[] = array(
      'key' => 'event_start_time_stamp',
      'value' => $now_mysql,
      'compare' => '<',
      'type' => 'DATETIME',
    );
  }

  // End bound
  if ( $before ) {
    $meta_query[] = array(
      'key' => 'event_start_time_stamp',
      'value' => $before,
      'compare' => '<=',
      'type' => 'DATETIME',
    );
  }

  // Build orderby
  $orderby_map = array(
    'start_time' => array( 'meta_key' => 'event_start_time_stamp', 'orderby' => 'meta_value' ),
    'end_time'   => array( 'meta_key' => 'event_end_time_stamp',   'orderby' => 'meta_value' ),
    'title'      => array( 'orderby' => 'title' ),
    'date'       => array( 'orderby' => 'date' ),
  );
  $ob = isset( $orderby_map[ $orderby ] ) ? $orderby_map[ $orderby ] : $orderby_map['start_time'];

  // If categories or search apply to PARENT posts, find parent IDs first
  $parent_ids = $parent;
  $need_parent_query = ( ! empty( $search ) );
  if ( $need_parent_query ) {
    $parent_q = array(
      'post_type'      => 'post', // Adjust if your parent event post_type differs
      'posts_per_page' => -1,
      'fields'         => 'ids',
      's'              => $search ?: '',
    );
    
    $parent_ids_found = get_posts( $parent_q );
    if ( is_wp_error( $parent_ids_found ) ) {
      return new WP_REST_Response( array( 'success' => false, 'message' => 'Parent query failed.' ), 500 );
    }

    if ( empty( $parent ) ) {
      $parent_ids = $parent_ids_found;
    } else {
      // If parent param also provided, intersect with found
      $parent_ids = array_values( array_intersect( $parent, $parent_ids_found ) );
    }

    // If we applied filters that yield no parents, return empty early
    if ( $need_parent_query && empty( $parent_ids ) ) {
      return wp_send_json_success( array( 'events' => array(), 'total' => 0, 'total_pages' => 0, 'page' => $page, 'per_page' => $per_page ) );
    }
  }
  
  // Build main query args for sub_event
  $args = array(
    'post_type'      => 'sub_event',
    'posts_per_page' => $per_page,
    'paged'          => $page,
    'post__in'       => ! empty( $include ) ? $include : null,
    'post__not_in'   => ! empty( $exclude ) ? $exclude : array(),
    'meta_key'       => isset( $ob['meta_key'] ) ? $ob['meta_key'] : '',
    'orderby'        => $ob['orderby'],
    'order'          => $order,
    'meta_query'     => $meta_query,
  );

  // Apply category filter directly to sub_event taxonomy (event_category)
  if ( ! empty( $cats ) ) {
    $cat_slugs = array_filter( array_map( 'sanitize_title', array_map( 'trim', explode( ',', $cats ) ) ) );
    if ( ! empty( $cat_slugs ) ) {
      $args['tax_query'] = array(
        array(
          'taxonomy'         => 'event_category',
          'field'            => 'slug',
          'terms'            => $cat_slugs,
          'include_children' => true,
          'operator'         => 'IN',
        ),
      );
    }
  }

  if ( ! empty( $parent_ids ) ) {
    $args['post_parent__in'] = $parent_ids;
  }

  // If include is present, WP_Query ignores paging unless we sort by post__in
  if ( ! empty( $include ) ) {
    $args['orderby'] = 'post__in';
    unset( $args['meta_key'] );
  }

  // Execute the query
  $events_q = new WP_Query( $args );

  $events_array = array();

  if ( $events_q->have_posts() ) {
    while ( $events_q->have_posts() ) {
      $events_q->the_post();
      $sub_id    = get_the_ID();
      $parent_id = wp_get_post_parent_id( $sub_id );

      // Parent details
      $title   = $parent_id ? get_the_title( $parent_id ) : get_the_title( $sub_id );
      $link    = $parent_id ? get_permalink( $parent_id ) : get_permalink( $sub_id );
      $excerpt = $parent_id ? get_the_excerpt( $parent_id ) : get_the_excerpt( $sub_id );
      $image   = $parent_id ? get_the_post_thumbnail_url( $parent_id, 'full' ) : get_the_post_thumbnail_url( $sub_id, 'full' );

      $event_data = array(
        'sub_event_id' => $sub_id,
        'parent_id'    => $parent_id,
        'title'        => $title,
        'link'         => $link,
        'excerpt'      => $excerpt,
        'image'        => $image ?: '',
        'start_time'   => get_post_meta( $sub_id, 'event_start_time_stamp', true ),
        'end_time'     => get_post_meta( $sub_id, 'event_end_time_stamp', true ),
      );

      $events_array[] = $event_data;
    }
    wp_reset_postdata();
  }

  // Build response with pagination meta
  $total       = (int) $events_q->found_posts;
  $total_pages = (int) ( $per_page > 0 ? ceil( $total / $per_page ) : 1 );

  $response = array(
    'events'      => $events_array,
    'total'       => $total,
    'total_pages' => $total_pages,
    'page'        => $page,
    'per_page'    => $per_page,
    'order'       => $order,
    'orderby'     => $orderby,
    'status'      => $status,
  );

  return wp_send_json_success( $response );
}