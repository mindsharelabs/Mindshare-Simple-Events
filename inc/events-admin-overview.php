<?php

class MindEventsAdminOverview {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_events_submenu_page'));
    }

    public function add_events_submenu_page() {
        add_submenu_page(
            'edit.php?post_type=events', // Parent slug
            'Upcoming Events', // Page title
            'Upcoming Events', // Menu title
            'manage_options', // Capability
            'upcoming-events', // Menu slug
            array($this, 'display_upcoming_events_page') // Callback function
        );
    }

    public function display_upcoming_events_page() {
        global $wpdb;

        // Pagination setup
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 30;
        $offset = ($paged - 1) * $per_page;

        // Query to get upcoming events
        $events_query = new WP_Query(array(
            'meta_query' => array(
                array(
                    'key' => 'event_start_time_stamp', // Check the start date field
                    'value' => date('Y-m-d H:i:s', strtotime('-1 days')), // Set today's date
                    'compare' => '>=', // Return the ones greater than today's date
                    'type' => 'DATETIME' // Let WordPress know we're working with date
                ),
            ),
            'orderby' => 'meta_value',
            'meta_key' => 'event_start_time_stamp',
            'meta_type' => 'DATETIME',
            'order' => 'ASC',
            'post_type' => 'sub_event',
            'suppress_filters' => true,
            'posts_per_page' => $per_page,
            'paged' => $paged
        ));

        $total_items = $events_query->found_posts;
        $total_pages = $events_query->max_num_pages;

        echo '<div class="wrap">';
        echo '<h1>Upcoming Events</h1>';
        echo '<table class="wp-list-table widefat striped event-attendees">';
        echo '<thead><tr>
            <th>Event</th>
            <th>Actions</th>
            <th>Date</th>
            <th>Attendees</th>
            <th>Orders</th>
            <th>Instructor</th>
            <th>Estimated Profit</th>
            </tr></thead>';
        echo '<tbody>';

        if ($events_query->have_posts()) :
            while ($events_query->have_posts()) :
                $events_query->the_post();
                $parent_id = wp_get_post_parent_id(get_the_id());

                $event_type = get_post_meta($parent_id, 'event_type', true);
                $attendees = get_post_meta($parent_id, 'attendees', true);

                if ($event_type == 'single-event') :
                    $attendees = (isset($attendees[$parent_id]) && is_array($attendees[$parent_id])) ? $attendees[$parent_id] : array();
                else :
                    $attendees = (isset($attendees[get_the_id()]) && is_array($attendees[get_the_id()])) ? $attendees[get_the_id()] : array();
                endif;

                $linked_product = get_post_meta(get_the_id(), 'linked_product', true);
                $date = get_post_meta(get_the_id(), 'event_date', true);
                $attendee_count = is_array($attendees) ? count($attendees) : 0;

                echo '<tr>';
                echo '<td class="event-title">';
                echo '<strong><a href="' . get_edit_post_link($parent_id) . '" target="_blank">' . esc_html(get_the_title($parent_id)) . '</a></strong>';
                echo '</td>';
                echo '<td class="event-actions">';
                echo '<div class="button-group">';
                echo '<a href="' . get_edit_post_link($parent_id) . '" target="_blank" class="button button-small button-secondary">Edit Event</a>';
                echo '<a href="' . get_permalink($parent_id) . '" target="_blank" class="button button-small button-secondary">View Event</a>';
                if ($linked_product) :
                    echo '<a href="' . get_edit_post_link($linked_product) . '" target="_blank" class="button button-small button-secondary">Edit Product</a>';
                endif;
                echo '</div>';
                echo '</td>';
                echo '<td class="event-date">' . esc_html(date('F j, Y', strtotime($date))) . '</td>';
                echo '<td class="attendee-count" data-count="' . esc_html($attendee_count) . '">' . esc_html($attendee_count) . '</td>';
                echo '<td class="event-orders">';
                if ($attendee_count > 0) :
                    foreach ($attendees as $attendee) :
                        $order = wc_get_order($attendee['order_id']);
                        if(!$order) continue;
                        $user = get_userdata($order->get_customer_id());
                        echo '<a href="' . get_edit_post_link($attendee['order_id']) . '" target="_blank">#' . $order->get_order_number() . ' - ' . esc_html($user->display_name) . '</a>';
                        if (next($attendees)) echo '<br>';
                    endforeach;
                else :
                    echo 'No orders';
                endif;
                echo '</td>';
                echo '<td class="event-instructor">';
                $instructor = get_post_meta(get_the_id(), 'instructorID', true);
                $parent_id = wp_get_post_parent_id(get_the_id());
                if($instructor) :
                    //get user object
                    $instructor_user = get_user_by('id', $instructor);
                    echo '<a href="' . get_edit_user_link($instructor_user->ID) . '" target="_blank">' . esc_html($instructor_user->display_name) . '</a>';
                else :
                    echo 'No instructor assigned';
                endif;
                echo '</td>';
                
                // Calculate and display estimated profit
                echo '<td class="estimated-profit">';
                $revenue = get_post_meta(get_the_id(), 'total_revenue', true);
                $profit = get_post_meta(get_the_id(), 'sub_event_profit', true);
                
                // Check if instructor cost or materials cost is empty (not set or empty string, but not 0)
                $parent_id = wp_get_post_parent_id(get_the_id());
                $instructor_cost = get_post_meta($parent_id, 'instructor_expense', true);
                $materials_cost = get_post_meta($parent_id, 'materials_expense', true);
                $costs_empty = ($instructor_cost === '' || $materials_cost === '');
                
                // If no attendees, set profit to 0 (no expenses without students)
                if ($attendee_count === 0) {
                    $profit = 0;
                    echo '-';
                } else {
                    $profit_color = floatval($profit) >= 0 ? '#46b450' : '#dc3232';
                    
                    // Display warning if costs are empty
                    if ($costs_empty) {
                        echo '<span style="color: #ffb900; margin-right: 5px;" title="Instructor cost or materials cost not set">⚠️</span>';
                    }
                    
                    echo '<span style="color: ' . $profit_color . ';">$' . number_format(floatval($profit), 2) . '</span>';
                }
                echo '</td>';
                echo '</tr>';
            endwhile;
        else :
            echo '<tr><td colspan="6">No upcoming events found.</td></tr>';
        endif;

        echo '</tbody>';
        echo '</table>';

        // Pagination links
        echo '<div class="tablenav"><div class="tablenav-pages">';
        echo paginate_links(array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => __('&laquo;'),
            'next_text' => __('&raquo;'),
            'total' => $total_pages,
            'current' => $paged,
        ));
        echo '</div></div>';

        echo '</div>';

        // Reset post data
        wp_reset_postdata();
    }
}

new MindEventsAdminOverview();