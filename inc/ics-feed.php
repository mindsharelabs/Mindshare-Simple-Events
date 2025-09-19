<?php
ob_start();
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="events.ics"');

$args = array(
		'meta_query' => array(
		  // 'relation' => 'AND',
		  'date_clause' => array(
			'key' => 'event_start_time_stamp',
			'compare' => 'EXISTS',
		  ),
		  array(
			'key' => 'event_start_time_stamp', // Check the start date field
			'value' => date('Y-m-d H:i:s'), // Set today's date (note the similar format)
			'compare' => '>=', // Return the ones greater than today's date
			'type' => 'DATETIME' // Let WordPress know we're working with date
		  )
		),
		'orderby' => 'meta_value',
		'meta_key' => 'event_start_time_stamp',
		'meta_type' => 'DATETIME',
		'order'            => 'ASC',
		'post_type'        => 'sub_event',
		'suppress_filters' => true,
		'posts_per_page'   => 100
	);
	$events = new WP_Query($args);
	    

	$output = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Make Santa Fe//Events Calendar//EN\r\n";
	if($events->have_posts()) {
		
		while($events->have_posts()) {
			
			$events->the_post();
			$event = get_post();
			$parent_event = get_post($event->post_parent);
			
			$created_at = get_post_datetime( $event->ID, 'created_at' )->format('Y-m-d H:i:s');
	
			$event_start = get_post_meta($event->ID, 'event_start_time_stamp', true);
			$event_end = get_post_meta($event->ID, 'event_end_time_stamp', true);
			$event_title = get_the_title($parent_event->ID);
			$event_description = get_the_excerpt($parent_event->ID);
            //limit to 70 characters
            $event_description = wp_trim_words( $event_description, 70, '...' );

			$output .= "BEGIN:VEVENT\r\n";
			$output .= "UID:" . uniqid() . "@makesantafe.org\r\n";
			$output .= "DTSTAMP:" . date('Ymd\THis', strtotime($created_at)) . "\r\n";
			$output .= "DTSTART:" . date('Ymd\THis', strtotime($event_start)) . "\r\n";
			$output .= "DTEND:" . date('Ymd\THis', strtotime($event_end)) . "\r\n";
			$output .= "SUMMARY:" . esc_html($event_title) . "\r\n";
			$output .= "DESCRIPTION:" . esc_html($event_description) . "\r\n";
			// $output .= "LOCATION:" . esc_html($event->location) . "\r\n";
			$output .= "END:VEVENT\r\n";


		}
	}
	$output .= "END:VCALENDAR";
    $output .= "\r\n";
    ob_end_clean();
echo $output;
