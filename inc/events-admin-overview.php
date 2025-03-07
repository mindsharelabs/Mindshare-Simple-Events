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

        // Query to get upcoming events
        $events = new WP_Query(array(
           'meta_query' => array(
                array(
                    'key' => 'event_time_stamp', // Check the start date field
                    'value' => date('Y-m-d H:i:s'), // Set today's date (note the similar format)
                    'compare' => '>=', // Return the ones greater than today's date
                    'type' => 'DATETIME' // Let WordPress know we're working with date
                )
            ),
            'orderby' => 'meta_value',
            'meta_key' => 'event_time_stamp',
            'meta_type' => 'DATETIME',
            'order'            => 'ASC',
            'post_type'        => 'sub_event',
            'suppress_filters' => true,
            'posts_per_page'   => 30
        ));

        echo '<div class="wrap">';
            echo '<h1>Upcoming Events</h1>';
            echo '<table class="wp-list-table widefat fixed striped event-attendees">';
            echo '<thead><tr><th>Event</th><th>Actions</th><th>Date</th><th>Attendees</th></tr></thead>';
                echo '<tbody>';

                if ($events->have_posts()) :
                    while ($events->have_posts()) :
                        $events->the_post();
                        $parent_id = wp_get_post_parent_id(get_the_id());
                        $attendees = get_post_meta($parent_id, 'attendees', true);
                        $attendees = $attendees[get_the_id()];
                        
                    


                        $date = get_post_meta(get_the_id(), 'event_date', true);
                        $attendee_count = is_array($attendees) ? count($attendees) : 0;


                        echo '<tr>';
                        echo '<td>';
                            echo '<strong><a href="' . get_edit_post_link($parent_id) . '" target="_blank">' . esc_html(get_the_title($parent_id)) . '</a></strong>';
                            
                        echo '</td>';
                        echo '<td>';
                            echo '<div class="button-group">';
                                echo '<a href="' . get_edit_post_link($parent_id) . '" target="_blank" class="button button-small button-secondary">Edit Event</a>';
                                echo '<a href="' . get_permalink($parent_id) . '" target="_blank" class="button button-small button-secondary">View Event</a>';
                            echo '</div>';
                        
                        echo '</td>';
                        echo '<td>' . esc_html(date('F j, Y', strtotime($date))) . '</td>';
                        echo '<td data-count="' . esc_html($attendee_count) . '">' .  esc_html($attendee_count) . '</td>';
                        echo '</tr>';
                    endwhile;
                else :
                    echo '<tr><td colspan="3">No upcoming events found.</td></tr>';
                endif;

                echo '</tbody>';
            echo '</table>';
        echo '</div>';
    }
}

new MindEventsAdminOverview();