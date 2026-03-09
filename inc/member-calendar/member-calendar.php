<?php
// Hook to add a new section to WooCommerce My Account dashboard
add_filter('woocommerce_account_menu_items', function ($items){
    // Add "Member Calendar" before "Logout"
    $items['member-calendar'] = __('Member Calendar', 'mindshare-simple-events');

    return $items;
});


// Register endpoint for Member Calendar
add_action('init', function(){
    add_rewrite_endpoint('member-calendar', EP_ROOT | EP_PAGES);
});

// Content for the Member Calendar section
add_action('woocommerce_account_member-calendar_endpoint', function (){
    if ( ! function_exists('wc_memberships_is_user_active_member') || ! wc_memberships_is_user_active_member(get_current_user_id()) ) {
        //display message that notifies non members that only members can see the calendar
        echo '<h2 class="text-center">The member calendar is only available to active members.</h2>';
    }
    $calendar = new mindEventCalendar('');
    $args = array(
        'post_type' => 'sub_event',
        'posts_per_page' => -1,
        'meta_key' => 'event_start_time_stamp',
        'orderby' => 'meta_value',
        'order' => 'ASC',
        'meta_query' => array(
            array(
                'key' => 'event_start_time_stamp',
                'compare' => 'EXISTS',
            ),
            array(
                'key'     => '_members_only',
                'value'   => '1',
                'compare' => '=',
            ),
        ),
    );
    echo '<h2>Member Calendar</h2>';
        echo '<p>This calendar displays member-specific events @ Make. Please continue to check the <a href="/events"> class calendar</a> to ensure your studio time does not conflict with any classes.</p>';
    echo '<div id="publicCalendar">';
         echo $calendar->get_front_calendar($args);
    echo '</div>';
});


// Flush rewrite rules on plugin activation
register_activation_hook(__FILE__, function (){
    add_rewrite_endpoint('member-calendar', EP_ROOT | EP_PAGES);
    flush_rewrite_rules();
});




add_filter('mindevents_calendar_daily_html', function($dailyHtml, $calendar) {
    static $injected = false; // prevent double-injection if filter runs multiple times
    $calendar_start_date = $calendar->getDate();
    if ( $injected ) {
        return $dailyHtml;
    }
    // Only inject on My Account → Member Calendar to keep public calendars clean
    $is_member_calendar = function_exists('is_account_page') && is_account_page() && ( get_query_var('member-calendar', false) !== false );
    if ( ! $is_member_calendar ) {
        return $dailyHtml;
    }
    if ( ! post_type_exists('wc_booking') ) {
        return $dailyHtml;
    }
    if ( ! ($calendar_start_date instanceof DateTimeInterface) ) {
        return $dailyHtml;
    }

    $visible_year  = (int) $calendar_start_date->format('Y');
    $visible_month = (int) $calendar_start_date->format('n');

 
    $days_in_month = (int) cal_days_in_month( CAL_GREGORIAN, $visible_month, $visible_year );

    // Clone to avoid mutating the original DateTime object
    $first_day_dt = (clone $calendar_start_date)->setDate($visible_year, $visible_month, 1)->setTime(0, 0, 0);
    $last_day_dt  = (clone $calendar_start_date)->setDate($visible_year, $visible_month, $days_in_month)->setTime(23, 59, 59);

    $range_start   = (int) $first_day_dt->format('YmdHis');
    $range_end     = (int) $last_day_dt->format('YmdHis');


    // Query bookings that overlap the calendar's date range
    $booking_query = new WP_Query( [
        'post_type'      => 'wc_booking',
        'posts_per_page' => -1,
        'post_status' => array('confirmed', 'paid', 'complete', 'pending-confirmation', 'unpaid', 'pending'),
        'orderby'        => 'meta_value_num',
        'meta_key'       => '_booking_start',
        'order'          => 'ASC',
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'     => '_booking_start',        // booking starts before the range ends
                'value'   => $range_end,
                'compare' => '<=',
                'type'    => 'NUMERIC',
            ],
            [
                'key'     => '_booking_end',          // booking ends after the range starts
                'value'   => $range_start,
                'compare' => '>=',
                'type'    => 'NUMERIC',
            ],
        ],
    ] );


    if ( $booking_query->have_posts() ) {
        while ( $booking_query->have_posts() ) {
            $booking_query->the_post();
            $booking_id   = get_the_ID();
 
            $start_ts     = (int) get_post_meta( $booking_id, '_booking_start', true );
            $end_ts       = (int) get_post_meta( $booking_id, '_booking_end', true );
            // $product_id   = (int) get_post_meta( $booking_id, '_booking_product_id', true );
            $resource_id  = (int) get_post_meta( $booking_id, '_booking_resource_id', true );
            $customer_id  = (int) get_post_meta( $booking_id, '_booking_customer_id', true );
            $customer_link = get_author_posts_url($customer_id);
            $customer_name = $customer_id ? get_the_author_meta( 'display_name', $customer_id ) : '';
            $all_day_booking = get_post_meta( $booking_id, '_booking_all_day', true );

    
            // $product_title  = $product_id ? get_the_title( $product_id ) : __( 'Booking', 'mindshare-simple-events' );
            $resource_title = $resource_id ? get_the_title( $resource_id ) : '';


        
            if (!empty($start_ts) && strlen($start_ts) == 14) {
                $year = substr($start_ts, 0, 4);
                $month = substr($start_ts, 4, 2);
                $day = substr($start_ts, 6, 2);
                $hour = substr($start_ts, 8, 2);
                $minute = substr($start_ts, 10, 2);
                $start_time = strtotime("{$year}-{$month}-{$day} {$hour}:{$minute}:00");
                $booking_start_date = date('Y-m-d', $start_time);
               
            }

            if (!empty($end_ts) && strlen($end_ts) == 14) {
                $year = substr($end_ts, 0, 4);
                $month = substr($end_ts, 4, 2);
                $day = substr($end_ts, 6, 2);
                $hour = substr($end_ts, 8, 2);
                $minute = substr($end_ts, 10, 2);
                $start_time = strtotime("{$year}-{$month}-{$day} {$hour}:{$minute}:00");
                $booking_end_date = date('Y-m-d', $start_time);
                
            }


            if (isset($booking_start_date) && isset($booking_end_date)) {
                $current_date = $booking_start_date;
                while (strtotime($current_date) <= strtotime($booking_end_date)) {
                    list($Y, $M, $D) = explode('-', $current_date);
                    $Y = (int)$Y;
                    $M = (int)$M;
                    $D = (int)$D;

               
                    if($all_day_booking) {
                        // For all-day bookings, we won't show specific times
                        $label_parts = ['All Day'];
                    } else {
                        // Build label depending on whether this is the start/end/middle day
                        $label_parts = [];
                        if ($current_date === $booking_start_date) {
                            $label_parts[] = ' from ' . date_i18n('g:ia', strtotime($start_ts));
                        }
                        if ($current_date === $booking_end_date) {
                            $label_parts[] = '– ' . date_i18n('g:ia', strtotime($end_ts));
                        }
                    }
                    $label_time = !empty($label_parts) ? ' <span class="time">' . esc_html(implode(' ', $label_parts)) . '</span>' : '';
                    $resource   = $resource_title ? ' <span class="resource">' . esc_html($resource_title) . '</span>' : '';

                    $html = '<div class="event booking-event">';
                        $html .= '<span class="customer"><a href="' . esc_url($customer_link) . '" target="_blank">' . esc_html($customer_name) . '</a></span>';
                        $html .= ' will be using the <span class="title">' . $resource . '</span>' . $label_time;
                    $html .= '</div>';


                    // Ensure arrays exist, then append
                    if (!isset($dailyHtml[$Y]))         { $dailyHtml[$Y] = []; }
                    if (!isset($dailyHtml[$Y][$M]))     { $dailyHtml[$Y][$M] = []; }
                    if (!isset($dailyHtml[$Y][$M][$D])) { $dailyHtml[$Y][$M][$D] = []; }

                    $dailyHtml[$Y][$M][$D][] = $html;

                    // advance one day
                    $current_date = date('Y-m-d', strtotime('+1 day', strtotime($current_date)));
                }
            }
        }
        wp_reset_postdata();
    }

    $injected = true;
    return $dailyHtml;
}, 10, 2);


