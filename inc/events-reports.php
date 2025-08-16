<?php

class MindEventsReports {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_reports_submenu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_reports_scripts'));
    }

    public function add_reports_submenu_page() {
        add_submenu_page(
            'edit.php?post_type=events', // Parent slug
            'Events Reports', // Page title
            'Reports', // Menu title
            'manage_options', // Capability
            'events-reports', // Menu slug
            array($this, 'display_reports_page') // Callback function
        );
    }

    public function enqueue_reports_scripts($hook) {
        // Only load on our reports page
        if ($hook !== 'events_page_events-reports') {
            return;
        }

        // Enqueue date picker CSS and JS
        wp_enqueue_style('jquery-ui-datepicker');
        wp_enqueue_script('jquery-ui-datepicker');
        
        // Enqueue our custom script
        wp_enqueue_script('events-reports', plugin_dir_url(dirname(__FILE__)) . 'js/events-reports.js', array('jquery', 'jquery-ui-datepicker'), '1.0', true);
        
        // Add inline script for date picker initialization
        wp_add_inline_script('events-reports', '
            jQuery(document).ready(function($) {
                $(".datepicker").datepicker({
                    dateFormat: "yy-mm-dd",
                    changeMonth: true,
                    changeYear: true
                });
            });
        ');
    }

    public function display_reports_page() {
        // Process form submission
        $report_data = $this->process_report_form();
        
        echo '<div class="wrap">';
        echo '<h1>Events Reports</h1>';
        
        // Display report form
        $this->display_report_form();
        
        // Display report results if available
        if ($report_data) {
            $this->display_report_results($report_data);
        }
        
        echo '</div>';
    }

    private function display_report_form() {
        // Get default date range (last 30 days)
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-30 days'));
        
        // Get all parent events
        $parent_events = get_posts(array(
            'post_type' => 'events',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        echo '<div class="report-form-container" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">';
        echo '<h2>Report Filters</h2>';
        echo '<form method="get" action="">';
        echo '<input type="hidden" name="post_type" value="events">';
        echo '<input type="hidden" name="page" value="events-reports">';
        
        echo '<div class="form-row" style="display: flex; gap: 20px; margin-bottom: 15px;">';
        echo '<div class="form-group" style="flex: 1;">';
        echo '<label for="start_date">Start Date:</label>';
        echo '<input type="text" id="start_date" name="start_date" class="datepicker regular-text" value="' . esc_attr(isset($_GET['start_date']) ? $_GET['start_date'] : $start_date) . '">';
        echo '</div>';
        
        echo '<div class="form-group" style="flex: 1;">';
        echo '<label for="end_date">End Date:</label>';
        echo '<input type="text" id="end_date" name="end_date" class="datepicker regular-text" value="' . esc_attr(isset($_GET['end_date']) ? $_GET['end_date'] : $end_date) . '">';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="form-row" style="display: flex; gap: 20px; margin-bottom: 15px;">';
        echo '<div class="form-group" style="flex: 1;">';
        echo '<label for="parent_event">Parent Event:</label>';
        echo '<select id="parent_event" name="parent_event" class="regular-text">';
        echo '<option value="">All Parent Events</option>';
        foreach ($parent_events as $event) {
            $selected = (isset($_GET['parent_event']) && $_GET['parent_event'] == $event->ID) ? 'selected' : '';
            echo '<option value="' . esc_attr($event->ID) . '" ' . $selected . '>' . esc_html($event->post_title) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        echo '<div class="form-group" style="flex: 1;">';
        echo '<label for="event_category">Event Category:</label>';
        $categories = get_terms(array(
            'taxonomy' => 'event_category',
            'hide_empty' => true,
        ));
        echo '<select id="event_category" name="event_category" class="regular-text">';
        echo '<option value="">All Categories</option>';
        foreach ($categories as $category) {
            $selected = (isset($_GET['event_category']) && $_GET['event_category'] == $category->term_id) ? 'selected' : '';
            echo '<option value="' . esc_attr($category->term_id) . '" ' . $selected . '>' . esc_html($category->name) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="form-row" style="display: flex; gap: 20px; margin-bottom: 15px;">';
        echo '<div class="form-group" style="flex: 1;">';
        echo '<label for="report_type">Report Type:</label>';
        echo '<select id="report_type" name="report_type" class="regular-text">';
        echo '<option value="summary" ' . (isset($_GET['report_type']) && $_GET['report_type'] == 'summary' ? 'selected' : '') . '>Summary Report</option>';
        echo '<option value="detailed" ' . (isset($_GET['report_type']) && $_GET['report_type'] == 'detailed' ? 'selected' : '') . '>Detailed Report</option>';
        echo '<option value="parent" ' . (isset($_GET['report_type']) && $_GET['report_type'] == 'parent' ? 'selected' : '') . '>Parent Event Summary</option>';
        echo '<option value="category" ' . (isset($_GET['report_type']) && $_GET['report_type'] == 'category' ? 'selected' : '') . '>Category Report</option>';
        echo '</select>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="form-row">';
        echo '<input type="submit" class="button button-primary" value="Generate Report">';
        echo '<a href="' . esc_url(remove_query_arg(array('start_date', 'end_date', 'parent_event', 'report_type'))) . '" class="button">Reset Filters</a>';
        echo '</div>';
        
        echo '</form>';
        echo '</div>';
    }

    private function process_report_form() {
        // Check if form was submitted
        if (!isset($_GET['start_date']) || !isset($_GET['end_date'])) {
            return null;
        }
        
        // Validate dates
        $start_date = sanitize_text_field($_GET['start_date']);
        $end_date = sanitize_text_field($_GET['end_date']);
        $parent_event = isset($_GET['parent_event']) ? intval($_GET['parent_event']) : 0;
        $event_category = isset($_GET['event_category']) ? intval($_GET['event_category']) : 0;
        $report_type = isset($_GET['report_type']) ? sanitize_text_field($_GET['report_type']) : 'summary';
        
        if (!strtotime($start_date) || !strtotime($end_date)) {
            return null;
        }
        
        // Get report data based on type
        switch ($report_type) {
            case 'summary':
                return $this->get_summary_report($start_date, $end_date, $parent_event, $event_category);
            case 'detailed':
                return $this->get_detailed_report($start_date, $end_date, $parent_event, $event_category);
            case 'parent':
                return $this->get_parent_report($start_date, $end_date, $parent_event, $event_category);
            case 'category':
                return $this->get_category_report($start_date, $end_date, $event_category);
            default:
                return $this->get_summary_report($start_date, $end_date, $parent_event, $event_category);
        }
    }

    private function get_summary_report($start_date, $end_date, $parent_event = 0, $event_category = 0) {
        global $wpdb;
        
        // Get parent events within date range
        $args = array(
            'post_type' => 'events',
            'posts_per_page' => -1
        );
        
        if ($parent_event > 0) {
            $args['include'] = array($parent_event);
        }
        
        if ($event_category > 0) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'event_category',
                    'field' => 'term_id',
                    'terms' => $event_category,
                )
            );
        }
        
        $parent_events = get_posts($args);
        
        $report_data = array(
            'total_parents' => 0,
            'total_events' => 0,
            'total_attendees' => 0,
            'total_revenue' => 0,
            'total_instructor_expense' => 0,
            'total_materials_expense' => 0,
            'total_expenses' => 0,
            'total_profit' => 0,
            'parents' => array()
        );
        
        foreach ($parent_events as $parent) {
            // Get all sub events for this parent within date range
            $sub_args = array(
                'post_type' => 'sub_event',
                'post_parent' => $parent->ID,
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => 'event_date',
                        'value' => array($start_date, $end_date),
                        'compare' => 'BETWEEN',
                        'type' => 'DATE'
                    )
                )
            );
            
            $sub_events = get_posts($sub_args);
            
            $parent_data = array(
                'id' => $parent->ID,
                'title' => $parent->post_title,
                'sub_events_count' => count($sub_events),
                'attendees' => 0,
                'revenue' => 0,
                'instructor_expense' => 0,
                'materials_expense' => 0,
                'total_expenses' => 0,
                'profit' => 0,
                'sub_events' => array()
            );
            
            foreach ($sub_events as $sub_event) {
                $attendees_count = get_post_meta($sub_event->ID, 'related_orders_count', true);
                $revenue = get_post_meta($sub_event->ID, 'total_revenue', true);
                $profit = get_post_meta($sub_event->ID, 'sub_event_profit', true);
                
                $parent_data['attendees'] += intval($attendees_count);
                $parent_data['revenue'] += floatval($revenue);
                $parent_data['profit'] += floatval($profit);
                
                $instructor_expense = get_post_meta($parent->ID, 'instructor_expense', true);
                $materials_expense = get_post_meta($parent->ID, 'materials_expense', true);
                $total_materials_expense = floatval($materials_expense) * intval($attendees_count);
                $total_expenses = floatval($instructor_expense) + $total_materials_expense;
                
                $parent_data['instructor_expense'] += floatval($instructor_expense);
                $parent_data['materials_expense'] += $total_materials_expense;
                $parent_data['total_expenses'] += $total_expenses;
                
                $parent_data['sub_events'][] = array(
                    'id' => $sub_event->ID,
                    'title' => $sub_event->post_title,
                    'date' => get_post_meta($sub_event->ID, 'event_date', true),
                    'attendees' => $attendees_count,
                    'revenue' => $revenue,
                    'profit' => $profit
                );
            }
            
            // Only include parent events that have sub events in the time period
            if ($parent_data['sub_events_count'] > 0) {
                $report_data['total_parents']++;
                $report_data['total_events'] += $parent_data['sub_events_count'];
                $report_data['total_attendees'] += $parent_data['attendees'];
                $report_data['total_revenue'] += $parent_data['revenue'];
                $report_data['total_instructor_expense'] += $parent_data['instructor_expense'];
                $report_data['total_materials_expense'] += $parent_data['materials_expense'];
                $report_data['total_expenses'] += $parent_data['total_expenses'];
                $report_data['total_profit'] += $parent_data['profit'];
                
                $report_data['parents'][] = $parent_data;
            }
        }
        
        return $report_data;
    }

    private function get_detailed_report($start_date, $end_date, $parent_event = 0, $event_category = 0) {
        global $wpdb;
        
        // Get sub events within date range
        $args = array(
            'post_type' => 'sub_event',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'event_date',
                    'value' => array($start_date, $end_date),
                    'compare' => 'BETWEEN',
                    'type' => 'DATE'
                )
            )
        );
        
        if ($parent_event > 0) {
            $args['post_parent'] = $parent_event;
        }
        
        if ($event_category > 0) {
            // Get parent events in this category first
            $parent_args = array(
                'post_type' => 'events',
                'posts_per_page' => -1,
                'tax_query' => array(
                    array(
                        'taxonomy' => 'event_category',
                        'field' => 'term_id',
                        'terms' => $event_category,
                    )
                )
            );
            
            $parent_events = get_posts($parent_args);
            $parent_ids = wp_list_pluck($parent_events, 'ID');
            
            if (!empty($parent_ids)) {
                $args['post_parent__in'] = $parent_ids;
            } else {
                // No parent events in this category, return empty report
                return array(
                    'total_events' => 0,
                    'total_attendees' => 0,
                    'total_revenue' => 0,
                    'total_instructor_expense' => 0,
                    'total_materials_expense' => 0,
                    'total_expenses' => 0,
                    'total_profit' => 0,
                    'events' => array()
                );
            }
        }
        
        $sub_events = get_posts($args);
        
        $report_data = array(
            'total_events' => count($sub_events),
            'total_attendees' => 0,
            'total_revenue' => 0,
            'total_instructor_expense' => 0,
            'total_materials_expense' => 0,
            'total_expenses' => 0,
            'total_profit' => 0,
            'events' => array()
        );
        
        foreach ($sub_events as $sub_event) {
            $attendees_count = get_post_meta($sub_event->ID, 'related_orders_count', true);
            $revenue = get_post_meta($sub_event->ID, 'total_revenue', true);
            $profit = get_post_meta($sub_event->ID, 'sub_event_profit', true);
            
            $report_data['total_attendees'] += intval($attendees_count);
            $report_data['total_revenue'] += floatval($revenue);
            $report_data['total_profit'] += floatval($profit);
            
            $parent_id = wp_get_post_parent_id($sub_event->ID);
            $parent_title = $parent_id ? get_the_title($parent_id) : 'No Parent';
            
            // Get instructor information
            $instructor_id = get_post_meta($sub_event->ID, 'instructorID', true);
            $instructor_name = '';
            if ($instructor_id) {
                $instructor_post = get_post($instructor_id);
                if ($instructor_post && $instructor_post->post_type === 'instructor') {
                    $instructor_name = $instructor_post->post_title;
                }
            }
            
            // If instructorID didn't work, try alternative field names
            if (empty($instructor_name)) {
                $instructor_name = get_post_meta($sub_event->ID, 'instructor', true);
            }
            if (empty($instructor_name)) {
                $instructor_name = get_post_meta($sub_event->ID, 'event_instructor', true);
            }
            
            $instructor_expense = 0;
            $materials_expense = 0;
            $total_materials_expense = 0;
            $total_expenses = 0;
            
            if ($parent_id) {
                $instructor_expense = get_post_meta($parent_id, 'instructor_expense', true);
                $materials_expense = get_post_meta($parent_id, 'materials_expense', true);
                $total_materials_expense = floatval($materials_expense) * intval($attendees_count);
                $total_expenses = floatval($instructor_expense) + $total_materials_expense;
                
                $report_data['total_instructor_expense'] += floatval($instructor_expense);
                $report_data['total_materials_expense'] += $total_materials_expense;
                $report_data['total_expenses'] += $total_expenses;
            }
            
            // Get customer orders list
            $customer_orders = get_post_meta($sub_event->ID, 'customer_orders_list', true);
            $customer_orders = is_array($customer_orders) ? $customer_orders : array();
            
            $report_data['events'][] = array(
                'id' => $sub_event->ID,
                'title' => $sub_event->post_title,
                'parent_title' => $parent_title,
                'instructor' => $instructor_name,
                'date' => get_post_meta($sub_event->ID, 'event_date', true),
                'attendees' => $attendees_count,
                'revenue' => $revenue,
                'instructor_expense' => $instructor_expense,
                'materials_expense_per_attendee' => $materials_expense,
                'total_materials_expense' => $total_materials_expense,
                'total_expenses' => $total_expenses,
                'profit' => $profit,
                'customer_orders' => $customer_orders
            );
        }
        
        return $report_data;
    }

    private function get_parent_report($start_date, $end_date, $parent_event = 0, $event_category = 0) {
        global $wpdb;
        
        // Get all parent events or specific one
        $args = array(
            'post_type' => 'events',
            'posts_per_page' => -1
        );
        
        if ($parent_event > 0) {
            $args['include'] = array($parent_event);
        }
        
        if ($event_category > 0) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'event_category',
                    'field' => 'term_id',
                    'terms' => $event_category,
                )
            );
        }
        
        $parent_events = get_posts($args);
        
        $report_data = array(
            'total_parents' => 0,
            'total_attendees' => 0,
            'total_revenue' => 0,
            'total_profit' => 0,
            'parents' => array()
        );
        
        foreach ($parent_events as $parent) {
            // Get all sub events for this parent within date range
            $sub_args = array(
                'post_type' => 'sub_event',
                'post_parent' => $parent->ID,
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => 'event_date',
                        'value' => array($start_date, $end_date),
                        'compare' => 'BETWEEN',
                        'type' => 'DATE'
                    )
                )
            );
            
            $sub_events = get_posts($sub_args);
            
            $parent_data = array(
                'id' => $parent->ID,
                'title' => $parent->post_title,
                'sub_events_count' => count($sub_events),
                'attendees' => 0,
                'revenue' => 0,
                'instructor_expense' => 0,
                'materials_expense' => 0,
                'total_expenses' => 0,
                'profit' => 0,
                'sub_events' => array()
            );
            
            foreach ($sub_events as $sub_event) {
                $attendees_count = get_post_meta($sub_event->ID, 'related_orders_count', true);
                $revenue = get_post_meta($sub_event->ID, 'total_revenue', true);
                $profit = get_post_meta($sub_event->ID, 'sub_event_profit', true);
                
                $parent_data['attendees'] += intval($attendees_count);
                $parent_data['revenue'] += floatval($revenue);
                $parent_data['profit'] += floatval($profit);
                
                $instructor_expense = get_post_meta($parent->ID, 'instructor_expense', true);
                $materials_expense = get_post_meta($parent->ID, 'materials_expense', true);
                $total_materials_expense = floatval($materials_expense) * intval($attendees_count);
                $total_expenses = floatval($instructor_expense) + $total_materials_expense;
                
                $parent_data['instructor_expense'] += floatval($instructor_expense);
                $parent_data['materials_expense'] += $total_materials_expense;
                $parent_data['total_expenses'] += $total_expenses;
                
                $instructor_id = get_post_meta($sub_event->ID, 'instructorID', true);
                $instructor_name = '';
                if ($instructor_id) {
                    $instructor_post = get_post($instructor_id);
                    if ($instructor_post && $instructor_post->post_type === 'instructor') {
                        $instructor_name = $instructor_post->post_title;
                    }
                }
                
                // If instructorID didn't work, try alternative field names
                if (empty($instructor_name)) {
                    $instructor_name = get_post_meta($sub_event->ID, 'instructor', true);
                }
                if (empty($instructor_name)) {
                    $instructor_name = get_post_meta($sub_event->ID, 'event_instructor', true);
                }
                
                $parent_data['sub_events'][] = array(
                    'id' => $sub_event->ID,
                    'title' => $sub_event->post_title,
                    'date' => get_post_meta($sub_event->ID, 'event_date', true),
                    'attendees' => $attendees_count,
                    'revenue' => $revenue,
                    'instructor' => $instructor_name,
                    'instructor_expense' => $instructor_expense,
                    'materials_expense_per_attendee' => $materials_expense,
                    'total_materials_expense' => $total_materials_expense,
                    'total_expenses' => $total_expenses,
                    'profit' => $profit
                );
            }
            
            // Only include parent events that have sub events in the time period
            if ($parent_data['sub_events_count'] > 0) {
                $report_data['total_parents']++;
                $report_data['total_attendees'] += $parent_data['attendees'];
                $report_data['total_revenue'] += $parent_data['revenue'];
                $report_data['total_profit'] += $parent_data['profit'];
                
                $report_data['parents'][] = $parent_data;
            }
        }
        
        return $report_data;
    }

    private function get_category_report($start_date, $end_date, $event_category = 0) {
        global $wpdb;
        
        // Get all event categories
        $categories = get_terms(array(
            'taxonomy' => 'event_category',
            'hide_empty' => true,
        ));
        
        // If a specific category is requested, only use that one
        if ($event_category > 0) {
            $categories = array(get_term($event_category, 'event_category'));
        }
        
        $report_data = array(
            'total_categories' => 0,
            'total_events' => 0,
            'total_attendees' => 0,
            'total_revenue' => 0,
            'total_instructor_expense' => 0,
            'total_materials_expense' => 0,
            'total_expenses' => 0,
            'total_profit' => 0,
            'categories' => array()
        );
        
        foreach ($categories as $category) {
            // Get all parent events in this category
            $parent_args = array(
                'post_type' => 'events',
                'posts_per_page' => -1,
                'tax_query' => array(
                    array(
                        'taxonomy' => 'event_category',
                        'field' => 'term_id',
                        'terms' => $category->term_id,
                    )
                )
            );
            
            $parent_events = get_posts($parent_args);
            
            $category_data = array(
                'id' => $category->term_id,
                'name' => $category->name,
                'parent_events_count' => count($parent_events),
                'sub_events_count' => 0,
                'attendees' => 0,
                'revenue' => 0,
                'instructor_expense' => 0,
                'materials_expense' => 0,
                'total_expenses' => 0,
                'profit' => 0,
                'parent_events' => array()
            );
            
            foreach ($parent_events as $parent) {
                // Get all sub events for this parent within date range
                $sub_args = array(
                    'post_type' => 'sub_event',
                    'post_parent' => $parent->ID,
                    'posts_per_page' => -1,
                    'meta_query' => array(
                        array(
                            'key' => 'event_date',
                            'value' => array($start_date, $end_date),
                            'compare' => 'BETWEEN',
                            'type' => 'DATE'
                        )
                    )
                );
                
                $sub_events = get_posts($sub_args);
                
                $parent_data = array(
                    'id' => $parent->ID,
                    'title' => $parent->post_title,
                    'sub_events_count' => count($sub_events),
                    'attendees' => 0,
                    'revenue' => 0,
                    'instructor_expense' => 0,
                    'materials_expense' => 0,
                    'total_expenses' => 0,
                    'profit' => 0,
                    'sub_events' => array()
                );
                
                foreach ($sub_events as $sub_event) {
                    $attendees_count = get_post_meta($sub_event->ID, 'related_orders_count', true);
                    $revenue = get_post_meta($sub_event->ID, 'total_revenue', true);
                    $profit = get_post_meta($sub_event->ID, 'sub_event_profit', true);
                    
                    $parent_data['attendees'] += intval($attendees_count);
                    $parent_data['revenue'] += floatval($revenue);
                    $parent_data['profit'] += floatval($profit);
                    
                    $instructor_expense = get_post_meta($parent->ID, 'instructor_expense', true);
                    $materials_expense = get_post_meta($parent->ID, 'materials_expense', true);
                    $total_materials_expense = floatval($materials_expense) * intval($attendees_count);
                    $total_expenses = floatval($instructor_expense) + $total_materials_expense;
                    
                    $parent_data['instructor_expense'] += floatval($instructor_expense);
                    $parent_data['materials_expense'] += $total_materials_expense;
                    $parent_data['total_expenses'] += $total_expenses;
                    
                    $parent_data['sub_events'][] = array(
                        'id' => $sub_event->ID,
                        'title' => $sub_event->post_title,
                        'date' => get_post_meta($sub_event->ID, 'event_date', true),
                        'attendees' => $attendees_count,
                        'revenue' => $revenue,
                        'profit' => $profit
                    );
                }
                
                // Only include parent events that have sub events in the time period
                if ($parent_data['sub_events_count'] > 0) {
                    $category_data['parent_events_count']++;
                    $category_data['sub_events_count'] += $parent_data['sub_events_count'];
                    $category_data['attendees'] += $parent_data['attendees'];
                    $category_data['revenue'] += $parent_data['revenue'];
                    $category_data['instructor_expense'] += $parent_data['instructor_expense'];
                    $category_data['materials_expense'] += $parent_data['materials_expense'];
                    $category_data['total_expenses'] += $parent_data['total_expenses'];
                    $category_data['profit'] += $parent_data['profit'];
                    
                    $category_data['parent_events'][] = $parent_data;
                }
            }
            
            // Only include categories that have parent events with sub events in the time period
            if ($category_data['sub_events_count'] > 0) {
                $report_data['total_categories']++;
                $report_data['total_events'] += $category_data['sub_events_count'];
                $report_data['total_attendees'] += $category_data['attendees'];
                $report_data['total_revenue'] += $category_data['revenue'];
                $report_data['total_instructor_expense'] += $category_data['instructor_expense'];
                $report_data['total_materials_expense'] += $category_data['materials_expense'];
                $report_data['total_expenses'] += $category_data['total_expenses'];
                $report_data['total_profit'] += $category_data['profit'];
                
                $report_data['categories'][] = $category_data;
            }
        }
        
        return $report_data;
    }

    private function display_report_results($report_data) {
        $report_type = isset($_GET['report_type']) ? sanitize_text_field($_GET['report_type']) : 'summary';
        
        echo '<div class="report-results-container" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">';
        
        // Display summary cards
        $this->display_summary_cards($report_data);
        
        // Display detailed results based on report type
        switch ($report_type) {
            case 'summary':
                $this->display_summary_table($report_data);
                break;
            case 'detailed':
                $this->display_detailed_table($report_data);
                break;
            case 'parent':
                $this->display_parent_table($report_data);
                break;
            case 'category':
                $this->display_category_table($report_data);
                break;
        }
        
        echo '</div>';
    }

    private function display_summary_cards($report_data) {
        echo '<div class="summary-cards" style="display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap;">';
        
        echo '<div class="card" style="flex: 1; min-width: 200px; background: #f8f9fa; padding: 20px; border-radius: 4px; border-left: 4px solid #0073aa;">';
        echo '<h3 style="margin: 0 0 10px 0; color: #23282d;">Total Parent Events</h3>';
        echo '<p style="margin: 0; font-size: 24px; font-weight: bold;">' . esc_html($report_data['total_parents']) . '</p>';
        echo '</div>';
        
        echo '<div class="card" style="flex: 1; min-width: 200px; background: #f8f9fa; padding: 20px; border-radius: 4px; border-left: 4px solid #7c7c7c;">';
        echo '<h3 style="margin: 0 0 10px 0; color: #23282d;">Total Sub Events</h3>';
        echo '<p style="margin: 0; font-size: 24px; font-weight: bold;">' . esc_html($report_data['total_events']) . '</p>';
        echo '</div>';
        
        echo '<div class="card" style="flex: 1; min-width: 200px; background: #f8f9fa; padding: 20px; border-radius: 4px; border-left: 4px solid #46b450;">';
        echo '<h3 style="margin: 0 0 10px 0; color: #23282d;">Total Attendees</h3>';
        echo '<p style="margin: 0; font-size: 24px; font-weight: bold;">' . esc_html($report_data['total_attendees']) . '</p>';
        echo '</div>';
        
        echo '<div class="card" style="flex: 1; min-width: 200px; background: #f8f9fa; padding: 20px; border-radius: 4px; border-left: 4px solid #ffb900;">';
        echo '<h3 style="margin: 0 0 10px 0; color: #23282d;">Total Revenue</h3>';
        echo '<p style="margin: 0; font-size: 24px; font-weight: bold;">$' . number_format(floatval($report_data['total_revenue']), 2) . '</p>';
        echo '</div>';
        
        echo '<div class="card" style="flex: 1; min-width: 200px; background: #f8f9fa; padding: 20px; border-radius: 4px; border-left: 4px solid ' . (floatval($report_data['total_profit']) >= 0 ? '#46b450' : '#dc3232') . ';">';
        echo '<h3 style="margin: 0 0 10px 0; color: #23282d;">Total Profit</h3>';
        echo '<p style="margin: 0; font-size: 24px; font-weight: bold;">$' . number_format(floatval($report_data['total_profit']), 2) . '</p>';
        echo '</div>';
        
        echo '</div>';
    }

    private function display_summary_table($report_data) {
        echo '<h2>Parent Event Summary</h2>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Parent Event</th>';
        echo '<th>Sub Events</th>';
        echo '<th>Attendees</th>';
        echo '<th>Revenue</th>';
        echo '<th>Instructor Expense</th>';
        echo '<th>Materials Expense</th>';
        echo '<th>Total Expenses</th>';
        echo '<th>Profit</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($report_data['parents'] as $parent) {
            echo '<tr>';
            echo '<td><strong>' . esc_html($parent['title']) . '</strong></td>';
            echo '<td>' . esc_html($parent['sub_events_count']) . '</td>';
            echo '<td>' . esc_html($parent['attendees']) . '</td>';
            echo '<td>$' . number_format($parent['revenue'], 2) . '</td>';
            echo '<td>$' . number_format($parent['instructor_expense'], 2) . '</td>';
            echo '<td>$' . number_format($parent['materials_expense'], 2) . '</td>';
            echo '<td>$' . number_format($parent['total_expenses'], 2) . '</td>';
            echo '<td style="color: ' . ($parent['profit'] >= 0 ? '#46b450' : '#dc3232') . ';">$' . number_format($parent['profit'], 2) . '</td>';
            echo '</tr>';
            
            // Show sub events as child rows
            if (!empty($parent['sub_events'])) {
                foreach ($parent['sub_events'] as $sub_event) {
                    echo '<tr class="child-row">';
                    echo '<td style="padding-left: 30px;">&nbsp;&nbsp;&nbsp;→ ' . esc_html($sub_event['title']) . ' (' . esc_html($sub_event['date']) . ')</td>';
                    echo '<td>-</td>';
                    echo '<td>' . esc_html($sub_event['attendees']) . '</td>';
                    echo '<td>$' . number_format($sub_event['revenue'], 2) . '</td>';
                    echo '<td>-</td>';
                    echo '<td>-</td>';
                    echo '<td>-</td>';
                    echo '<td style="color: ' . ($sub_event['profit'] >= 0 ? '#46b450' : '#dc3232') . ';">$' . number_format($sub_event['profit'], 2) . '</td>';
                    echo '</tr>';
                }
            }
        }
        
        echo '</tbody>';
        echo '<tfoot>';
        echo '<tr>';
        echo '<th colspan="2">Totals</th>';
        echo '<th>' . esc_html($report_data['total_attendees']) . '</th>';
        echo '<th>$' . number_format($report_data['total_revenue'], 2) . '</th>';
        echo '<th>$' . number_format($report_data['total_instructor_expense'], 2) . '</th>';
        echo '<th>$' . number_format($report_data['total_materials_expense'], 2) . '</th>';
        echo '<th>$' . number_format($report_data['total_expenses'], 2) . '</th>';
        echo '<th style="color: ' . ($report_data['total_profit'] >= 0 ? '#46b450' : '#dc3232') . ';">$' . number_format($report_data['total_profit'], 2) . '</th>';
        echo '</tr>';
        echo '</tfoot>';
        echo '</table>';
    }

    private function display_detailed_table($report_data) {
        echo '<h2>Detailed Event Information</h2>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>
            <th>Event</th>
            <th>Parent Event</th>
            <th>Instructor</th>
            <th>Date</th>
            <th>Attendees</th>
            <th>Revenue</th>
            <th>Instructor Expense</th>
            <th>Materials/Attendee</th>
            <th>Total Materials</th>
            <th>Total Expenses</th>
            <th>Profit</th>
            <th>Customer Orders</th>
            </tr></thead>';
        echo '<tbody>';
        
        foreach ($report_data['events'] as $event) {
            echo '<tr>';
            echo '<td><a href="' . get_edit_post_link($event['id']) . '" target="_blank">' . esc_html($event['title']) . '</a></td>';
            echo '<td>' . esc_html($event['parent_title']) . '</td>';
            echo '<td>' . esc_html($event['instructor']) . '</td>';
            echo '<td>' . esc_html(date('F j, Y', strtotime($event['date']))) . '</td>';
            echo '<td>' . esc_html($event['attendees']) . '</td>';
            echo '<td>$' . number_format(floatval($event['revenue']), 2) . '</td>';
            echo '<td>$' . number_format(floatval($event['instructor_expense']), 2) . '</td>';
            echo '<td>$' . number_format(floatval($event['materials_expense_per_attendee']), 2) . '</td>';
            echo '<td>$' . number_format(floatval($event['total_materials_expense']), 2) . '</td>';
            echo '<td>$' . number_format(floatval($event['total_expenses']), 2) . '</td>';
            echo '<td style="color: ' . (floatval($event['profit']) >= 0 ? '#46b450' : '#dc3232') . ';">$' . number_format(floatval($event['profit']), 2) . '</td>';
            echo '<td style="max-width: 300px;">';
            
            if (!empty($event['customer_orders'])) {
                foreach ($event['customer_orders'] as $order) {
                    echo '<div style="margin-bottom: 5px;">' . $order . '</div>';
                }
            } else {
                echo 'No orders';
            }
            
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '<tfoot>';
        echo '<tr>';
        echo '<th colspan="5">Totals</th>';
        echo '<th>$' . number_format($report_data['total_revenue'], 2) . '</th>';
        echo '<th>$' . number_format($report_data['total_instructor_expense'], 2) . '</th>';
        echo '<th>-</th>';
        echo '<th>$' . number_format($report_data['total_materials_expense'], 2) . '</th>';
        echo '<th>$' . number_format($report_data['total_expenses'], 2) . '</th>';
        echo '<th style="color: ' . ($report_data['total_profit'] >= 0 ? '#46b450' : '#dc3232') . ';">$' . number_format($report_data['total_profit'], 2) . '</th>';
        echo '<th>-</th>';
        echo '</tr>';
        echo '</tfoot>';
        echo '</table>';
    }

    private function display_parent_table($report_data) {
        echo '<h2>Parent Event Summary</h2>';
        
        foreach ($report_data['parents'] as $parent) {
            echo '<div class="parent-section" style="margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 4px;">';
            echo '<h3>' . esc_html($parent['title']) . '</h3>';
            echo '<p><strong>Sub Events:</strong> ' . esc_html($parent['sub_events_count']) . ' |
                   <strong>Total Attendees:</strong> ' . esc_html($parent['attendees']) . ' |
                   <strong>Total Revenue:</strong> $' . number_format(floatval($parent['revenue']), 2) . ' |
                   <strong>Instructor Cost:</strong> $' . number_format(floatval($parent['instructor_expense']), 2) . ' |
                   <strong>Materials Cost:</strong> $' . number_format(floatval($parent['materials_expense']), 2) . ' |
                   <strong>Total Expenses:</strong> $' . number_format(floatval($parent['total_expenses']), 2) . ' |
                   <strong>Total Profit:</strong> <span style="color: ' . (floatval($parent['profit']) >= 0 ? '#46b450' : '#dc3232') . ';">$' . number_format(floatval($parent['profit']), 2) . '</span></p>';
            
            if (!empty($parent['sub_events'])) {
                echo '<table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">';
                echo '<thead><tr>
                    <th>Sub Event</th>
                    <th>Instructor</th>
                    <th>Date</th>
                    <th>Attendees</th>
                    <th>Revenue</th>
                    <th>Instructor Cost</th>
                    <th>Materials/Attendee</th>
                    <th>Total Materials</th>
                    <th>Total Expenses</th>
                    <th>Profit</th>
                    </tr></thead>';
                echo '<tbody>';
                
                foreach ($parent['sub_events'] as $sub_event) {
                    echo '<tr>';
                    echo '<td><a href="' . get_edit_post_link($sub_event['id']) . '" target="_blank">' . esc_html($sub_event['title']) . '</a></td>';
                    echo '<td>' . esc_html($sub_event['instructor']) . '</td>';
                    echo '<td>' . esc_html(date('F j, Y', strtotime($sub_event['date']))) . '</td>';
                    echo '<td>' . esc_html($sub_event['attendees']) . '</td>';
                    echo '<td>$' . number_format(floatval($sub_event['revenue']), 2) . '</td>';
                    echo '<td>$' . number_format(floatval($sub_event['instructor_expense']), 2) . '</td>';
                    echo '<td>$' . number_format(floatval($sub_event['materials_expense_per_attendee']), 2) . '</td>';
                    echo '<td>$' . number_format(floatval($sub_event['total_materials_expense']), 2) . '</td>';
                    echo '<td>$' . number_format(floatval($sub_event['total_expenses']), 2) . '</td>';
                    echo '<td style="color: ' . (floatval($sub_event['profit']) >= 0 ? '#46b450' : '#dc3232') . ';">$' . number_format(floatval($sub_event['profit']), 2) . '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody>';
                echo '</table>';
            }
            
            echo '</div>';
        }
    }

    private function display_category_table($report_data) {
        echo '<h2>Category Report</h2>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Category</th>';
        echo '<th>Parent Events</th>';
        echo '<th>Sub Events</th>';
        echo '<th>Attendees</th>';
        echo '<th>Revenue</th>';
        echo '<th>Instructor Expense</th>';
        echo '<th>Materials Expense</th>';
        echo '<th>Total Expenses</th>';
        echo '<th>Profit</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($report_data['categories'] as $category) {
            echo '<tr>';
            echo '<td><strong>' . esc_html($category['name']) . '</strong></td>';
            echo '<td>' . esc_html($category['parent_events_count']) . '</td>';
            echo '<td>' . esc_html($category['sub_events_count']) . '</td>';
            echo '<td>' . esc_html($category['attendees']) . '</td>';
            echo '<td>$' . number_format($category['revenue'], 2) . '</td>';
            echo '<td>$' . number_format($category['instructor_expense'], 2) . '</td>';
            echo '<td>$' . number_format($category['materials_expense'], 2) . '</td>';
            echo '<td>$' . number_format($category['total_expenses'], 2) . '</td>';
            echo '<td style="color: ' . ($category['profit'] >= 0 ? '#46b450' : '#dc3232') . ';">$' . number_format($category['profit'], 2) . '</td>';
            echo '</tr>';
            
            // Show parent events as child rows
            if (!empty($category['parent_events'])) {
                foreach ($category['parent_events'] as $parent) {
                    echo '<tr class="child-row">';
                    echo '<td style="padding-left: 30px;">&nbsp;&nbsp;&nbsp;→ ' . esc_html($parent['title']) . '</td>';
                    echo '<td>-</td>';
                    echo '<td>' . esc_html($parent['sub_events_count']) . '</td>';
                    echo '<td>' . esc_html($parent['attendees']) . '</td>';
                    echo '<td>$' . number_format($parent['revenue'], 2) . '</td>';
                    echo '<td>$' . number_format($parent['instructor_expense'], 2) . '</td>';
                    echo '<td>$' . number_format($parent['materials_expense'], 2) . '</td>';
                    echo '<td>$' . number_format($parent['total_expenses'], 2) . '</td>';
                    echo '<td style="color: ' . ($parent['profit'] >= 0 ? '#46b450' : '#dc3232') . ';">$' . number_format($parent['profit'], 2) . '</td>';
                    echo '</tr>';
                    
                    // Show sub events as grandchild rows
                    if (!empty($parent['sub_events'])) {
                        foreach ($parent['sub_events'] as $sub_event) {
                            echo '<tr class="grandchild-row">';
                            echo '<td style="padding-left: 60px;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;→ ' . esc_html($sub_event['title']) . ' (' . esc_html($sub_event['date']) . ')</td>';
                            echo '<td>-</td>';
                            echo '<td>-</td>';
                            echo '<td>' . esc_html($sub_event['attendees']) . '</td>';
                            echo '<td>$' . number_format($sub_event['revenue'], 2) . '</td>';
                            echo '<td>-</td>';
                            echo '<td>-</td>';
                            echo '<td>-</td>';
                            echo '<td style="color: ' . ($sub_event['profit'] >= 0 ? '#46b450' : '#dc3232') . ';">$' . number_format($sub_event['profit'], 2) . '</td>';
                            echo '</tr>';
                        }
                    }
                }
            }
        }
        
        echo '</tbody>';
        echo '<tfoot>';
        echo '<tr>';
        echo '<th colspan="3">Totals</th>';
        echo '<th>' . esc_html($report_data['total_attendees']) . '</th>';
        echo '<th>$' . number_format($report_data['total_revenue'], 2) . '</th>';
        echo '<th>$' . number_format($report_data['total_instructor_expense'], 2) . '</th>';
        echo '<th>$' . number_format($report_data['total_materials_expense'], 2) . '</th>';
        echo '<th>$' . number_format($report_data['total_expenses'], 2) . '</th>';
        echo '<th style="color: ' . ($report_data['total_profit'] >= 0 ? '#46b450' : '#dc3232') . ';">$' . number_format($report_data['total_profit'], 2) . '</th>';
        echo '</tr>';
        echo '</tfoot>';
        echo '</table>';
    }
}

new MindEventsReports();