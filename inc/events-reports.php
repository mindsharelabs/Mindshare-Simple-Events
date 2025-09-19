<?php

class MindEventsReports {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_reports_submenu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_reports_scripts'));
        add_action('admin_init', array($this, 'handle_csv_export'));
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
        
        // Enqueue our custom CSS for sortable columns
        wp_enqueue_style('events-reports-style', plugin_dir_url(dirname(__FILE__)) . 'css/style.css', array(), '1.0');
        
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
        
        // Add CSS for hiding sections initially and styling date picker
        wp_add_inline_style('jquery-ui-datepicker', '
            .comparison-date-range, .time-period-selector {
                display: none;
            }
            .export-button {
                margin-left: 10px;
            }
            .ui-datepicker {
                background: #fff;
                border: 1px solid #ddd;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                border-radius: 4px;
                z-index: 100 !important;
            }
            .ui-datepicker-header {
                background: #f8f9fa;
                border-bottom: 1px solid #ddd;
                border-radius: 4px 4px 0 0;
                padding: 10px;
            }
            .ui-datepicker-title {
                color: #23282d;
                font-weight: 600;
            }
            .ui-datepicker-calendar {
                margin: 10px;
            }
            .ui-datepicker th {
                color: #23282d;
                font-weight: 600;
                padding: 5px;
            }
            .ui-datepicker td {
                padding: 2px;
            }
            .ui-datepicker td a, .ui-datepicker td span {
                display: block;
                padding: 5px;
                text-align: center;
                text-decoration: none;
                color: #0073aa;
                border-radius: 3px;
            }
            .ui-datepicker td a:hover {
                background: #f8f9fa;
            }
            .ui-datepicker td .ui-state-active {
                background: #0073aa;
                color: #fff;
            }
            .ui-datepicker td .ui-state-highlight {
                background: #fff3cd;
            }
            .ui-datepicker-prev, .ui-datepicker-next {
                cursor: pointer;
            }
            .ui-datepicker-prev-hover, .ui-datepicker-next-hover {
                background: #f8f9fa;
                border-radius: 3px;
            }
            .ui-icon-circle-triangle-w {
                background: url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'16\' height=\'16\' viewBox=\'0 0 24 24\' fill=\'%2323282d\'%3E%3Cpath d=\'M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z\'/%3E%3C/svg%3E") no-repeat center;
            }
            .ui-icon-circle-triangle-e {
                background: url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'16\' height=\'16\' viewBox=\'0 0 24 24\' fill=\'%2323282d\'%3E%3Cpath d=\'M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z\'/%3E%3C/svg%3E") no-repeat center;
            }
        ');
        
        // Add CSS for sortable columns to the events-reports-style
        wp_add_inline_style('events-reports-style', '
            /* Styles for sortable columns in reports tables */
            .sortable-column {
                cursor: pointer;
                position: relative;
                padding-right: 20px !important;
            }

            .sortable-column:hover {
                background-color: rgba(0, 0, 0, 0.05);
            }

            .sortable-column::after {
                content: "";
                position: absolute;
                right: 8px;
                top: 50%;
                transform: translateY(-50%);
                width: 0;
                height: 0;
                border-left: 4px solid transparent;
                border-right: 4px solid transparent;
                opacity: 0.3;
            }

            .sort-asc::after {
                border-bottom: 6px solid #333;
            }

            .sort-desc::after {
                border-top: 6px solid #333;
            }

            .sortable-column.sort-asc::after,
            .sortable-column.sort-desc::after {
                opacity: 1;
            }
        ');
    }

    /**
     * Handle CSV export requests
     */
    public function handle_csv_export() {
        if (!isset($_GET['export_csv']) || !isset($_GET['report_type'])) {
            return;
        }

        // Verify nonce for security
        if (!isset($_GET['csv_export_nonce']) || !wp_verify_nonce($_GET['csv_export_nonce'], 'csv_export_nonce')) {
            wp_die('Security check failed');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }

        // Get report parameters
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
        $parent_event = isset($_GET['parent_event']) ? intval($_GET['parent_event']) : 0;
        $event_category = isset($_GET['event_category']) ? intval($_GET['event_category']) : 0;
        $include_non_ticketed = isset($_GET['ticketed_only']) && $_GET['ticketed_only'] == '1' ? 'ticketed_only' : 'all';
        $instructor = isset($_GET['instructor']) ? sanitize_text_field($_GET['instructor']) : '';
        $report_type = sanitize_text_field($_GET['report_type']);
        
        // Additional parameters for specific report types
        $start_date_2 = isset($_GET['start_date_2']) ? sanitize_text_field($_GET['start_date_2']) : '';
        $end_date_2 = isset($_GET['end_date_2']) ? sanitize_text_field($_GET['end_date_2']) : '';
        $time_period = isset($_GET['time_period']) ? sanitize_text_field($_GET['time_period']) : 'monthly';

        // Validate dates
        if (!strtotime($start_date) || !strtotime($end_date)) {
            wp_die('Invalid date range');
        }

        // Get report data based on type
        $report_data = null;
        switch ($report_type) {
            case 'summary':
                $report_data = $this->get_summary_report($start_date, $end_date, $parent_event, $event_category, $include_non_ticketed, $instructor);
                break;
            case 'detailed':
                $report_data = $this->get_detailed_report($start_date, $end_date, $parent_event, $event_category, $include_non_ticketed, $instructor);
                break;
            case 'parent':
                $report_data = $this->get_parent_report($start_date, $end_date, $parent_event, $event_category, $include_non_ticketed, $instructor);
                break;
            case 'category':
                $report_data = $this->get_category_report($start_date, $end_date, $event_category, $include_non_ticketed, $instructor);
                break;
            case 'comparison':
                if (!strtotime($start_date_2) || !strtotime($end_date_2)) {
                    wp_die('Invalid comparison date range');
                }
                $report_data = $this->get_comparison_report($start_date, $end_date, $start_date_2, $end_date_2, $event_category, $include_non_ticketed, $instructor);
                break;
            case 'time_based':
                $report_data = $this->get_time_based_report($start_date, $end_date, $time_period, $event_category, $include_non_ticketed, $instructor);
                break;
            default:
                wp_die('Invalid report type');
        }

        if (!$report_data) {
            wp_die('No data available for export');
        }

        // Generate CSV based on report type
        $csv_content = '';
        switch ($report_type) {
            case 'summary':
                $csv_content = $this->generate_summary_csv($report_data);
                $filename = 'events_summary_report_' . $start_date . '_to_' . $end_date . '.csv';
                break;
            case 'detailed':
                $csv_content = $this->generate_detailed_csv($report_data);
                $filename = 'events_detailed_report_' . $start_date . '_to_' . $end_date . '.csv';
                break;
            case 'parent':
                $csv_content = $this->generate_parent_csv($report_data);
                $filename = 'events_parent_report_' . $start_date . '_to_' . $end_date . '.csv';
                break;
            case 'category':
                $csv_content = $this->generate_category_csv($report_data);
                $filename = 'events_category_report_' . $start_date . '_to_' . $end_date . '.csv';
                break;
            case 'comparison':
                $csv_content = $this->generate_comparison_csv($report_data);
                $filename = 'events_comparison_report_' . $start_date . '_to_' . $end_date . '.csv';
                break;
            case 'time_based':
                $csv_content = $this->generate_time_based_csv($report_data);
                $filename = 'events_time_based_report_' . $start_date . '_to_' . $end_date . '.csv';
                break;
        }

        // Output CSV headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        // Output CSV content
        echo $csv_content;
        exit;
    }

    /**
     * Generate CSV content for summary report
     */
    private function generate_summary_csv($report_data) {
        $csv = "Parent Event,Sub Events,Attendees,Revenue,Instructor Expense,Materials Expense,Total Expenses,Profit\n";
        
        foreach ($report_data['parents'] as $parent) {
            $csv .= '"' . $this->escape_csv_field($parent['title']) . '",';
            $csv .= $parent['sub_events_count'] . ',';
            $csv .= $parent['attendees'] . ',';
            $csv .= $parent['revenue'] . ',';
            $csv .= $parent['instructor_expense'] . ',';
            $csv .= $parent['materials_expense'] . ',';
            $csv .= $parent['total_expenses'] . ',';
            $csv .= ($parent['attendees'] > 0 ? $parent['profit'] : '-') . "\n";
        }
        
        // Add totals row
        $csv .= '"TOTALS",,';
        $csv .= $report_data['total_attendees'] . ',';
        $csv .= $report_data['total_revenue'] . ',';
        $csv .= $report_data['total_instructor_expense'] . ',';
        $csv .= $report_data['total_materials_expense'] . ',';
        $csv .= $report_data['total_expenses'] . ',';
        $csv .= $report_data['total_profit'] . "\n";
        
        return $csv;
    }

    /**
     * Generate CSV content for detailed report
     */
    private function generate_detailed_csv($report_data) {
        $csv = "Event,Parent Event,Instructor,Date,Attendees,Revenue,Instructor Expense,Materials/Attendee,Total Materials,Total Expenses,Profit\n";
        
        foreach ($report_data['events'] as $event) {
            $csv .= '"' . $this->escape_csv_field($event['title']) . '",';
            $csv .= '"' . $this->escape_csv_field($event['parent_title']) . '",';
            $csv .= '"' . $this->escape_csv_field($event['instructor']) . '",';
            $csv .= $event['date'] . ',';
            $csv .= $event['attendees'] . ',';
            $csv .= $event['revenue'] . ',';
            $csv .= $event['instructor_expense'] . ',';
            $csv .= $event['materials_expense_per_attendee'] . ',';
            $csv .= $event['total_materials_expense'] . ',';
            $csv .= $event['total_expenses'] . ',';
            $csv .= ($event['attendees'] > 0 ? $event['profit'] : '-') . "\n";
        }
        
        // Add totals row
        $csv .= '"TOTALS",,,,,,';
        $csv .= $report_data['total_instructor_expense'] . ',,';
        $csv .= $report_data['total_materials_expense'] . ',';
        $csv .= $report_data['total_expenses'] . ',';
        $csv .= $report_data['total_profit'] . "\n";
        
        return $csv;
    }

    /**
     * Generate CSV content for parent report
     */
    private function generate_parent_csv($report_data) {
        $csv = "Parent Event,Sub Events,Attendees,Revenue,Instructor Expense,Materials Expense,Total Expenses,Profit\n";
        
        foreach ($report_data['parents'] as $parent) {
            $csv .= '"' . $this->escape_csv_field($parent['title']) . '",';
            $csv .= $parent['sub_events_count'] . ',';
            $csv .= $parent['attendees'] . ',';
            $csv .= $parent['revenue'] . ',';
            $csv .= $parent['instructor_expense'] . ',';
            $csv .= $parent['materials_expense'] . ',';
            $csv .= $parent['total_expenses'] . ',';
            $csv .= ($parent['attendees'] > 0 ? $parent['profit'] : '-') . "\n";
        }
        
        // Add totals row
        $csv .= '"TOTALS",,';
        $csv .= $report_data['total_attendees'] . ',';
        $csv .= $report_data['total_revenue'] . ',';
        $csv .= $report_data['total_instructor_expense'] . ',';
        $csv .= $report_data['total_materials_expense'] . ',';
        $csv .= $report_data['total_expenses'] . ',';
        $csv .= $report_data['total_profit'] . "\n";
        
        return $csv;
    }

    /**
     * Generate CSV content for category report
     */
    private function generate_category_csv($report_data) {
        $csv = "Category,Parent Events,Sub Events,Attendees,Revenue,Instructor Expense,Materials Expense,Total Expenses,Profit\n";
        
        foreach ($report_data['categories'] as $category) {
            $csv .= '"' . $this->escape_csv_field($category['name']) . '",';
            $csv .= $category['parent_events_count'] . ',';
            $csv .= $category['sub_events_count'] . ',';
            $csv .= $category['attendees'] . ',';
            $csv .= $category['revenue'] . ',';
            $csv .= $category['instructor_expense'] . ',';
            $csv .= $category['materials_expense'] . ',';
            $csv .= $category['total_expenses'] . ',';
            $csv .= ($category['attendees'] > 0 ? $category['profit'] : '-') . "\n";
        }
        
        // Add totals row
        $csv .= '"TOTALS",,';
        $csv .= $report_data['total_attendees'] . ',';
        $csv .= $report_data['total_revenue'] . ',';
        $csv .= $report_data['total_instructor_expense'] . ',';
        $csv .= $report_data['total_materials_expense'] . ',';
        $csv .= $report_data['total_expenses'] . ',';
        $csv .= $report_data['total_profit'] . "\n";
        
        return $csv;
    }

    /**
     * Generate CSV content for comparison report
     */
    private function generate_comparison_csv($report_data) {
        $csv = "Metric,Period 1,Period 2,Difference,Change %\n";
        
        $metrics = array(
            'total_parents' => 'Parent Events',
            'total_events' => 'Sub Events',
            'total_attendees' => 'Attendees',
            'total_revenue' => 'Revenue',
            'total_profit' => 'Profit'
        );
        
        foreach ($metrics as $key => $label) {
            $value_1 = isset($report_data['period_1'][$key]) ? floatval($report_data['period_1'][$key]) : 0;
            $value_2 = isset($report_data['period_2'][$key]) ? floatval($report_data['period_2'][$key]) : 0;
            $difference = isset($report_data['differences'][$key]) ? floatval($report_data['differences'][$key]) : 0;
            $percentage = isset($report_data['percentages'][$key]) ? floatval($report_data['percentages'][$key]) : 0;
            
            $csv .= '"' . $label . '",';
            if ($key === 'total_revenue' || $key === 'total_profit') {
                $csv .= '$' . number_format($value_1, 2) . ',';
                $csv .= '$' . number_format($value_2, 2) . ',';
                $csv .= '$' . number_format($difference, 2) . ',';
            } else {
                $csv .= $value_1 . ',';
                $csv .= $value_2 . ',';
                $csv .= $difference . ',';
            }
            $csv .= number_format($percentage, 1) . "%\n";
        }
        
        return $csv;
    }

    /**
     * Generate CSV content for time-based report
     */
    private function generate_time_based_csv($report_data) {
        $csv = "Period,Date Range,Sub Events,Attendees,Revenue,Profit,Avg. Revenue/Event,Avg. Profit/Event\n";
        
        foreach ($report_data['periods'] as $period) {
            $avg_revenue = $period['events_count'] > 0 ? $period['revenue'] / $period['events_count'] : 0;
            $avg_profit = $period['events_count'] > 0 ? $period['profit'] / $period['events_count'] : 0;
            
            $csv .= '"' . $this->escape_csv_field($period['label']) . '",';
            $csv .= $period['start_date'] . ' to ' . $period['end_date'] . ',';
            $csv .= $period['events_count'] . ',';
            $csv .= $period['attendees'] . ',';
            $csv .= $period['revenue'] . ',';
            $csv .= ($period['attendees'] > 0 ? $period['profit'] : '-') . ',';
            $csv .= $avg_revenue . ',';
            $csv .= $avg_profit . "\n";
        }
        
        // Add totals row
        $csv .= '"TOTALS",,';
        $csv .= $report_data['total_events'] . ',';
        $csv .= $report_data['total_attendees'] . ',';
        $csv .= $report_data['total_revenue'] . ',';
        $csv .= $report_data['total_profit'] . ',';
        $csv .= ($report_data['total_events'] > 0 ? $report_data['total_revenue'] / $report_data['total_events'] : 0) . ',';
        $csv .= ($report_data['total_events'] > 0 ? $report_data['total_profit'] / $report_data['total_events'] : 0) . "\n";
        
        return $csv;
    }

    /**
     * Escape CSV field to handle commas and quotes
     */
    private function escape_csv_field($field) {
        $field = str_replace('"', '""', $field);
        return $field;
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
        
        // Get all instructors
        $instructors = get_posts(array(
            'post_type' => 'instructor',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        // Also get users with instructor role
        $instructor_users = get_users(array(
            'role' => 'instructor',
            'orderby' => 'display_name',
            'order' => 'ASC'
        ));
        
        echo '<div class="report-form-container" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">';
        echo '<h2>Report Filters</h2>';
        echo '<form method="get" action="" class="events-reports-form">';
        echo '<input type="hidden" name="post_type" value="events">';
        echo '<input type="hidden" name="page" value="events-reports">';
        
        // Basic filters section
        echo '<div class="filters-section" style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee;">';
        echo '<h3 style="margin-top: 0; margin-bottom: 15px;">Basic Filters</h3>';
        
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
        echo '<div class="form-group" style="flex: 1;">';
        echo '<label for="report_type">Report Type:</label>';
        echo '<select id="report_type" name="report_type" class="report-type-selector regular-text">';
        echo '<option value="summary" ' . (isset($_GET['report_type']) && $_GET['report_type'] == 'summary' ? 'selected' : '') . '>Summary Report</option>';
        echo '<option value="detailed" ' . (isset($_GET['report_type']) && $_GET['report_type'] == 'detailed' ? 'selected' : '') . '>Detailed Report</option>';
        echo '<option value="parent" ' . (isset($_GET['report_type']) && $_GET['report_type'] == 'parent' ? 'selected' : '') . '>Parent Event Summary</option>';
        echo '<option value="category" ' . (isset($_GET['report_type']) && $_GET['report_type'] == 'category' ? 'selected' : '') . '>Category Report</option>';
        echo '<option value="comparison" ' . (isset($_GET['report_type']) && $_GET['report_type'] == 'comparison' ? 'selected' : '') . '>Comparison Report</option>';
        echo '<option value="time_based" ' . (isset($_GET['report_type']) && $_GET['report_type'] == 'time_based' ? 'selected' : '') . '>Time-based Report</option>';
        echo '</select>';
        echo '</div>';
        
        echo '<div class="form-group" style="flex: 1;">';
        echo '<label for="instructor">Instructor:</label>';
        echo '<select id="instructor" name="instructor" class="regular-text">';
        echo '<option value="">All Instructors</option>';
        
        // Add instructor posts
        foreach ($instructors as $instructor) {
            $selected = (isset($_GET['instructor']) && $_GET['instructor'] == 'post_' . $instructor->ID) ? 'selected' : '';
            echo '<option value="post_' . esc_attr($instructor->ID) . '" ' . $selected . '>' . esc_html($instructor->post_title) . '</option>';
        }
        
        // Add instructor users
        foreach ($instructor_users as $instructor_user) {
            $selected = (isset($_GET['instructor']) && $_GET['instructor'] == 'user_' . $instructor_user->ID) ? 'selected' : '';
            echo '<option value="user_' . esc_attr($instructor_user->ID) . '" ' . $selected . '>' . esc_html($instructor_user->display_name) . '</option>';
        }
        
        echo '</select>';
        echo '</div>';
        echo '</div>';
        
        // Ticketed events filter on a new line
        echo '<div class="form-row" style="margin-bottom: 15px;">';
        echo '<div class="form-group">';
        echo '<label for="ticketed_only">';
        $is_checked = (isset($_GET['ticketed_only']) && $_GET['ticketed_only'] == '1') ? 'checked' : '';
        echo '<input type="checkbox" id="ticketed_only" name="ticketed_only" value="1" ' . $is_checked . '> ';
        echo 'Show Ticketed Events Only';
        echo '</label>';
        echo '<p class="description">Check this box to include only events with tickets in the report.</p>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Date range section
        echo '<div class="date-range-section" style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee;">';
        echo '<h3 style="margin-top: 0; margin-bottom: 15px;">Date Range</h3>';
        
        echo '<div class="form-row" style="display: flex; gap: 20px; margin-bottom: 15px;">';
        echo '<div class="form-group" style="flex: 1;">';
        echo '<label for="start_date">Start Date:</label>';
        echo '<input type="text" id="start_date" name="start_date" class="datepicker start-date regular-text" value="' . esc_attr(isset($_GET['start_date']) ? $_GET['start_date'] : $start_date) . '">';
        echo '</div>';
        
        echo '<div class="form-group" style="flex: 1;">';
        echo '<label for="end_date">End Date:</label>';
        echo '<input type="text" id="end_date" name="end_date" class="datepicker end-date regular-text" value="' . esc_attr(isset($_GET['end_date']) ? $_GET['end_date'] : $end_date) . '">';
        echo '</div>';
        echo '</div>';
        
        // Comparison date range for comparison report
        echo '<div id="comparison-date-range" class="comparison-date-range" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">';
        echo '<h4 style="margin-top: 0; margin-bottom: 15px;">Comparison Period</h4>';
        echo '<div class="form-row" style="display: flex; gap: 20px; margin-bottom: 15px;">';
        echo '<div class="form-group" style="flex: 1;">';
        echo '<label for="start_date_2">Start Date:</label>';
        echo '<input type="text" id="start_date_2" name="start_date_2" class="datepicker regular-text" value="' . esc_attr(isset($_GET['start_date_2']) ? $_GET['start_date_2'] : date('Y-m-d', strtotime('-60 days'))) . '">';
        echo '</div>';
        
        echo '<div class="form-group" style="flex: 1;">';
        echo '<label for="end_date_2">End Date:</label>';
        echo '<input type="text" id="end_date_2" name="end_date_2" class="datepicker regular-text" value="' . esc_attr(isset($_GET['end_date_2']) ? $_GET['end_date_2'] : date('Y-m-d', strtotime('-31 days'))) . '">';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Time period selector for time-based report
        echo '<div id="time-period-selector" class="time-period-selector" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">';
        echo '<h4 style="margin-top: 0; margin-bottom: 15px;">Time Period Cadence</h4>';
        echo '<div class="form-row" style="display: flex; gap: 20px; margin-bottom: 15px;">';
        echo '<div class="form-group" style="flex: 1;">';
        echo '<label for="time_period">Group Results By:</label>';
        echo '<select id="time_period" name="time_period" class="regular-text">';
        echo '<option value="weekly" ' . (isset($_GET['time_period']) && $_GET['time_period'] == 'weekly' ? 'selected' : '') . '>Weekly</option>';
        echo '<option value="monthly" ' . (isset($_GET['time_period']) && $_GET['time_period'] == 'monthly' ? 'selected' : '') . '>Monthly</option>';
        echo '<option value="quarterly" ' . (isset($_GET['time_period']) && $_GET['time_period'] == 'quarterly' ? 'selected' : '') . '>Quarterly</option>';
        echo '<option value="yearly" ' . (isset($_GET['time_period']) && $_GET['time_period'] == 'yearly' ? 'selected' : '') . '>Yearly</option>';
        echo '</select>';
        echo '</div>';
        echo '</div>';
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
        $include_non_ticketed = isset($_GET['ticketed_only']) && $_GET['ticketed_only'] == '1' ? 'ticketed_only' : 'all';
        $instructor = isset($_GET['instructor']) ? sanitize_text_field($_GET['instructor']) : '';
        $report_type = isset($_GET['report_type']) ? sanitize_text_field($_GET['report_type']) : 'summary';
        
        if (!strtotime($start_date) || !strtotime($end_date)) {
            return null;
        }
        
        // Get report data based on type
        switch ($report_type) {
            case 'summary':
                return $this->get_summary_report($start_date, $end_date, $parent_event, $event_category, $include_non_ticketed, $instructor);
            case 'detailed':
                return $this->get_detailed_report($start_date, $end_date, $parent_event, $event_category, $include_non_ticketed, $instructor);
            case 'parent':
                return $this->get_parent_report($start_date, $end_date, $parent_event, $event_category, $include_non_ticketed, $instructor);
            case 'category':
                return $this->get_category_report($start_date, $end_date, $event_category, $include_non_ticketed, $instructor);
            case 'comparison':
                $start_date_2 = isset($_GET['start_date_2']) ? sanitize_text_field($_GET['start_date_2']) : date('Y-m-d', strtotime('-60 days'));
                $end_date_2 = isset($_GET['end_date_2']) ? sanitize_text_field($_GET['end_date_2']) : date('Y-m-d', strtotime('-31 days'));
                return $this->get_comparison_report($start_date, $end_date, $start_date_2, $end_date_2, $event_category, $include_non_ticketed, $instructor);
            case 'time_based':
                $time_period = isset($_GET['time_period']) ? sanitize_text_field($_GET['time_period']) : 'monthly';
                return $this->get_time_based_report($start_date, $end_date, $time_period, $event_category, $include_non_ticketed, $instructor);
            default:
                return $this->get_summary_report($start_date, $end_date, $parent_event, $event_category, $include_non_ticketed, $instructor);
        }
    }

    private function get_summary_report($start_date, $end_date, $parent_event = 0, $event_category = 0, $include_non_ticketed = 'all', $instructor = '') {
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
        
        // Filter out non-ticketed events if requested
        if ($include_non_ticketed === 'ticketed_only') {
            $ticketed_parent_events = array();
            foreach ($parent_events as $parent) {
                $has_tickets = get_post_meta($parent->ID, 'has_tickets', true);
                if ($has_tickets) {
                    $ticketed_parent_events[] = $parent;
                }
            }
            $parent_events = $ticketed_parent_events;
        }
        
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
                        'key' => 'event_start_time_stamp',
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
                // Get instructor information for filtering
                $instructor_match = false;
                $instructor_name = '';
                
                // Try instructorID first
                $instructor_id = get_post_meta($sub_event->ID, 'instructorID', true);
                if ($instructor_id) {
                    $instructor_post = get_post($instructor_id);
                    if ($instructor_post && $instructor_post->post_type === 'instructor') {
                        $instructor_name = $instructor_post->post_title;
                        if ($instructor === 'post_' . $instructor_id) {
                            $instructor_match = true;
                        }
                    } else {
                        // Try to get user by ID
                        $instructor_user = get_user_by('id', $instructor_id);
                        if ($instructor_user) {
                            $instructor_name = $instructor_user->display_name;
                            if ($instructor === 'user_' . $instructor_id) {
                                $instructor_match = true;
                            }
                        }
                    }
                }
                
                // If instructorID didn't work, try instructorEmail
                if (empty($instructor_name)) {
                    $instructor_email = get_post_meta($sub_event->ID, 'instructorEmail', true);
                    if ($instructor_email) {
                        $instructor_user = get_user_by('email', $instructor_email);
                        if ($instructor_user) {
                            $instructor_name = $instructor_user->display_name;
                            if ($instructor === 'user_' . $instructor_user->ID) {
                                $instructor_match = true;
                            }
                        }
                    }
                }
                
                // If still empty, try alternative field names
                if (empty($instructor_name)) {
                    $instructor_name = get_post_meta($sub_event->ID, 'instructor', true);
                }
                if (empty($instructor_name)) {
                    $instructor_name = get_post_meta($sub_event->ID, 'event_instructor', true);
                }
                
                // If still empty, try to get instructors from parent event
                if (empty($instructor_name)) {
                    $parent_instructors = get_field('instructors', $parent->ID);
                    if ($parent_instructors && is_array($parent_instructors)) {
                        $instructor_names = array();
                        foreach ($parent_instructors as $instructor_user) {
                            if (is_object($instructor_user) && isset($instructor_user->display_name)) {
                                $instructor_names[] = $instructor_user->display_name;
                                if ($instructor === 'user_' . $instructor_user->ID) {
                                    $instructor_match = true;
                                }
                            }
                        }
                        if (!empty($instructor_names)) {
                            $instructor_name = implode(', ', $instructor_names);
                        }
                    }
                }
                
                // Skip if instructor filter is set and doesn't match
                if (!empty($instructor) && !$instructor_match) {
                    continue;
                }
                
                $attendees_count = get_post_meta($sub_event->ID, 'related_orders_count', true);
                $revenue = get_post_meta($sub_event->ID, 'total_revenue', true);
                $profit = get_post_meta($sub_event->ID, 'sub_event_profit', true);
                
                // If no attendees, set profit to 0 (no expenses without students)
                if (intval($attendees_count) === 0) {
                    $profit = 0;
                }
                
                $parent_data['attendees'] += intval($attendees_count);
                $parent_data['revenue'] += floatval($revenue);
                $parent_data['profit'] += floatval($profit);
                
                $instructor_expense = get_post_meta($parent->ID, 'instructor_expense', true);
                $materials_expense = get_post_meta($parent->ID, 'materials_expense', true);
                $total_materials_expense = floatval($materials_expense) * intval($attendees_count);
                $total_expenses = floatval($instructor_expense) + $total_materials_expense;
                
                // Only add expenses if there are attendees
                if (intval($attendees_count) > 0) {
                    $parent_data['instructor_expense'] += floatval($instructor_expense);
                    $parent_data['materials_expense'] += $total_materials_expense;
                    $parent_data['total_expenses'] += $total_expenses;
                }
                
                $parent_data['sub_events'][] = array(
                    'id' => $sub_event->ID,
                    'title' => $sub_event->post_title,
                    'date' => get_post_meta($sub_event->ID, 'event_start_time_stamp', true),
                    'attendees' => $attendees_count,
                    'revenue' => $revenue,
                    'profit' => $profit,
                    'instructor' => $instructor_name
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

    private function get_detailed_report($start_date, $end_date, $parent_event = 0, $event_category = 0, $include_non_ticketed = 'all', $instructor = '') {
        global $wpdb;
        
        // Get sub events within date range
        $args = array(
            'post_type' => 'sub_event',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'event_start_time_stamp',
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
        
        // Filter out non-ticketed events if requested
        if ($include_non_ticketed === 'ticketed_only') {
            $ticketed_sub_events = array();
            foreach ($sub_events as $sub_event) {
                $parent_id = wp_get_post_parent_id($sub_event->ID);
                if ($parent_id) {
                    $has_tickets = get_post_meta($parent_id, 'has_tickets', true);
                    if ($has_tickets) {
                        $ticketed_sub_events[] = $sub_event;
                    }
                }
            }
            $sub_events = $ticketed_sub_events;
        }
        
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
            // Get instructor information for filtering
            $instructor_match = false;
            $instructor_name = '';
            
            // Try instructorID first
            $instructor_id = get_post_meta($sub_event->ID, 'instructorID', true);
            if ($instructor_id) {
                $instructor_post = get_post($instructor_id);
                if ($instructor_post && $instructor_post->post_type === 'instructor') {
                    $instructor_name = $instructor_post->post_title;
                    if ($instructor === 'post_' . $instructor_id) {
                        $instructor_match = true;
                    }
                } else {
                    // Try to get user by ID
                    $instructor_user = get_user_by('id', $instructor_id);
                    if ($instructor_user) {
                        $instructor_name = $instructor_user->display_name;
                        if ($instructor === 'user_' . $instructor_id) {
                            $instructor_match = true;
                        }
                    }
                }
            }
            
            // If instructorID didn't work, try instructorEmail
            if (empty($instructor_name)) {
                $instructor_email = get_post_meta($sub_event->ID, 'instructorEmail', true);
                if ($instructor_email) {
                    $instructor_user = get_user_by('email', $instructor_email);
                    if ($instructor_user) {
                        $instructor_name = $instructor_user->display_name;
                        if ($instructor === 'user_' . $instructor_user->ID) {
                            $instructor_match = true;
                        }
                    }
                }
            }
            
            // If still empty, try alternative field names
            if (empty($instructor_name)) {
                $instructor_name = get_post_meta($sub_event->ID, 'instructor', true);
            }
            if (empty($instructor_name)) {
                $instructor_name = get_post_meta($sub_event->ID, 'event_instructor', true);
            }
            
            // If still empty, try to get instructors from parent event
            if (empty($instructor_name)) {
                $parent_id = wp_get_post_parent_id($sub_event->ID);
                if ($parent_id) {
                    $parent_instructors = get_field('instructors', $parent_id);
                    if ($parent_instructors && is_array($parent_instructors)) {
                        $instructor_names = array();
                        foreach ($parent_instructors as $instructor_user) {
                            if (is_object($instructor_user) && isset($instructor_user->display_name)) {
                                $instructor_names[] = $instructor_user->display_name;
                                if ($instructor === 'user_' . $instructor_user->ID) {
                                    $instructor_match = true;
                                }
                            }
                        }
                        if (!empty($instructor_names)) {
                            $instructor_name = implode(', ', $instructor_names);
                        }
                    }
                }
            }
            
            // Skip if instructor filter is set and doesn't match
            if (!empty($instructor) && !$instructor_match) {
                continue;
            }
            
            $attendees_count = get_post_meta($sub_event->ID, 'related_orders_count', true);
            $revenue = get_post_meta($sub_event->ID, 'total_revenue', true);
            $profit = get_post_meta($sub_event->ID, 'sub_event_profit', true);
            
            // If no attendees, set profit to 0 (no expenses without students)
            if (intval($attendees_count) === 0) {
                $profit = 0;
            }
            
            $report_data['total_attendees'] += intval($attendees_count);
            $report_data['total_revenue'] += floatval($revenue);
            $report_data['total_profit'] += floatval($profit);
            
            $parent_id = wp_get_post_parent_id($sub_event->ID);
            $parent_title = $parent_id ? get_the_title($parent_id) : 'No Parent';
            
            // Get instructor information
            $instructor_name = '';
            
            // Try instructorID first
            $instructor_id = get_post_meta($sub_event->ID, 'instructorID', true);
            if ($instructor_id) {
                $instructor_post = get_post($instructor_id);
                if ($instructor_post && $instructor_post->post_type === 'instructor') {
                    $instructor_name = $instructor_post->post_title;
                } else {
                    // Try to get user by ID
                    $instructor_user = get_user_by('id', $instructor_id);
                    if ($instructor_user) {
                        $instructor_name = $instructor_user->display_name;
                    }
                }
            }
            
            // If instructorID didn't work, try instructorEmail
            if (empty($instructor_name)) {
                $instructor_email = get_post_meta($sub_event->ID, 'instructorEmail', true);
                if ($instructor_email) {
                    $instructor_user = get_user_by('email', $instructor_email);
                    if ($instructor_user) {
                        $instructor_name = $instructor_user->display_name;
                    }
                }
            }
            
            // If still empty, try alternative field names
            if (empty($instructor_name)) {
                $instructor_name = get_post_meta($sub_event->ID, 'instructor', true);
            }
            if (empty($instructor_name)) {
                $instructor_name = get_post_meta($sub_event->ID, 'event_instructor', true);
            }
            
            // If still empty, try to get instructors from parent event
            if (empty($instructor_name) && $parent_id) {
                $parent_instructors = get_field('instructors', $parent_id);
                if ($parent_instructors && is_array($parent_instructors)) {
                    $instructor_names = array();
                    foreach ($parent_instructors as $instructor_user) {
                        if (is_object($instructor_user) && isset($instructor_user->display_name)) {
                            $instructor_names[] = $instructor_user->display_name;
                        }
                    }
                    if (!empty($instructor_names)) {
                        $instructor_name = implode(', ', $instructor_names);
                    }
                }
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
                
                // Only add expenses if there are attendees
                if (intval($attendees_count) > 0) {
                    $report_data['total_instructor_expense'] += floatval($instructor_expense);
                    $report_data['total_materials_expense'] += $total_materials_expense;
                    $report_data['total_expenses'] += $total_expenses;
                }
            }
            
            // Get customer orders list
            $customer_orders = get_post_meta($sub_event->ID, 'customer_orders_list', true);
            $customer_orders = is_array($customer_orders) ? $customer_orders : array();
            
            $report_data['events'][] = array(
                'id' => $sub_event->ID,
                'title' => $sub_event->post_title,
                'parent_id' => $parent_id,
                'parent_title' => $parent_title,
                'instructor' => $instructor_name,
                'date' => get_post_meta($sub_event->ID, 'event_start_time_stamp', true),
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

    private function get_parent_report($start_date, $end_date, $parent_event = 0, $event_category = 0, $include_non_ticketed = 'all', $instructor = '') {
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
        
        // Filter out non-ticketed events if requested
        if ($include_non_ticketed === 'ticketed_only') {
            $ticketed_parent_events = array();
            foreach ($parent_events as $parent) {
                $has_tickets = get_post_meta($parent->ID, 'has_tickets', true);
                if ($has_tickets) {
                    $ticketed_parent_events[] = $parent;
                }
            }
            $parent_events = $ticketed_parent_events;
        }
        
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
                        'key' => 'event_start_time_stamp',
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
                // Get instructor information for filtering
                $instructor_match = false;
                $instructor_name = '';
                
                // Try instructorID first
                $instructor_id = get_post_meta($sub_event->ID, 'instructorID', true);
                if ($instructor_id) {
                    $instructor_post = get_post($instructor_id);
                    if ($instructor_post && $instructor_post->post_type === 'instructor') {
                        $instructor_name = $instructor_post->post_title;
                        if ($instructor === 'post_' . $instructor_id) {
                            $instructor_match = true;
                        }
                    } else {
                        // Try to get user by ID
                        $instructor_user = get_user_by('id', $instructor_id);
                        if ($instructor_user) {
                            $instructor_name = $instructor_user->display_name;
                            if ($instructor === 'user_' . $instructor_id) {
                                $instructor_match = true;
                            }
                        }
                    }
                }
                
                // If instructorID didn't work, try instructorEmail
                if (empty($instructor_name)) {
                    $instructor_email = get_post_meta($sub_event->ID, 'instructorEmail', true);
                    if ($instructor_email) {
                        $instructor_user = get_user_by('email', $instructor_email);
                        if ($instructor_user) {
                            $instructor_name = $instructor_user->display_name;
                            if ($instructor === 'user_' . $instructor_user->ID) {
                                $instructor_match = true;
                            }
                        }
                    }
                }
                
                // If still empty, try alternative field names
                if (empty($instructor_name)) {
                    $instructor_name = get_post_meta($sub_event->ID, 'instructor', true);
                }
                if (empty($instructor_name)) {
                    $instructor_name = get_post_meta($sub_event->ID, 'event_instructor', true);
                }
                
                // If still empty, try to get instructors from parent event
                if (empty($instructor_name)) {
                    $parent_instructors = get_field('instructors', $parent->ID);
                    if ($parent_instructors && is_array($parent_instructors)) {
                        $instructor_names = array();
                        foreach ($parent_instructors as $instructor_user) {
                            if (is_object($instructor_user) && isset($instructor_user->display_name)) {
                                $instructor_names[] = $instructor_user->display_name;
                                if ($instructor === 'user_' . $instructor_user->ID) {
                                    $instructor_match = true;
                                }
                            }
                        }
                        if (!empty($instructor_names)) {
                            $instructor_name = implode(', ', $instructor_names);
                        }
                    }
                }
                
                // Skip if instructor filter is set and doesn't match
                if (!empty($instructor) && !$instructor_match) {
                    continue;
                }
                
                $attendees_count = get_post_meta($sub_event->ID, 'related_orders_count', true);
                $revenue = get_post_meta($sub_event->ID, 'total_revenue', true);
                $profit = get_post_meta($sub_event->ID, 'sub_event_profit', true);
                
                // If no attendees, set profit to 0 (no expenses without students)
                if (intval($attendees_count) === 0) {
                    $profit = 0;
                }
                
                $parent_data['attendees'] += intval($attendees_count);
                $parent_data['revenue'] += floatval($revenue);
                $parent_data['profit'] += floatval($profit);
                
                $instructor_expense = get_post_meta($parent->ID, 'instructor_expense', true);
                $materials_expense = get_post_meta($parent->ID, 'materials_expense', true);
                $total_materials_expense = floatval($materials_expense) * intval($attendees_count);
                $total_expenses = floatval($instructor_expense) + $total_materials_expense;
                
                // Only add expenses if there are attendees
                if (intval($attendees_count) > 0) {
                    $parent_data['instructor_expense'] += floatval($instructor_expense);
                    $parent_data['materials_expense'] += $total_materials_expense;
                    $parent_data['total_expenses'] += $total_expenses;
                }
                
                $parent_data['sub_events'][] = array(
                    'id' => $sub_event->ID,
                    'title' => $sub_event->post_title,
                    'date' => get_post_meta($sub_event->ID, 'event_start_time_stamp', true),
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

    /**
     * Get comparison report data
     *
     * @param string $start_date_1 Period 1 start date
     * @param string $end_date_1 Period 1 end date
     * @param string $start_date_2 Period 2 start date
     * @param string $end_date_2 Period 2 end date
     * @param int $event_category Event category ID
     * @param string $include_non_ticketed Filter for ticketed events
     * @return array Report data
     */
    private function get_comparison_report($start_date_1, $end_date_1, $start_date_2, $end_date_2, $event_category = 0, $include_non_ticketed = 'all', $instructor = '') {
        // Get data for period 1
        $period_1_data = $this->get_summary_report($start_date_1, $end_date_1, 0, $event_category, $include_non_ticketed, $instructor);
        
        // Get data for period 2
        $period_2_data = $this->get_summary_report($start_date_2, $end_date_2, 0, $event_category, $include_non_ticketed, $instructor);
        
        // Calculate differences and percentages
        $comparison_data = array(
            'period_1' => $period_1_data,
            'period_2' => $period_2_data,
            'differences' => array(),
            'percentages' => array()
        );
        
        // Calculate differences and percentages for each metric
        $metrics = array('total_parents', 'total_events', 'total_attendees', 'total_revenue', 'total_profit');
        
        foreach ($metrics as $metric) {
            $value_1 = isset($period_1_data[$metric]) ? floatval($period_1_data[$metric]) : 0;
            $value_2 = isset($period_2_data[$metric]) ? floatval($period_2_data[$metric]) : 0;
            
            $difference = $value_2 - $value_1;
            $percentage = $value_1 > 0 ? ($difference / $value_1) * 100 : 0;
            
            $comparison_data['differences'][$metric] = $difference;
            $comparison_data['percentages'][$metric] = $percentage;
        }
        
        return $comparison_data;
    }

    private function get_category_report($start_date, $end_date, $event_category = 0, $include_non_ticketed = 'all', $instructor = '') {
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
            
            // Filter out non-ticketed events if requested
            if ($include_non_ticketed === 'ticketed_only') {
                $ticketed_parent_events = array();
                foreach ($parent_events as $parent) {
                    $has_tickets = get_post_meta($parent->ID, 'has_tickets', true);
                    if ($has_tickets) {
                        $ticketed_parent_events[] = $parent;
                    }
                }
                $parent_events = $ticketed_parent_events;
            }
            
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
                            'key' => 'event_start_time_stamp',
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
                    // Get instructor information for filtering
                    $instructor_match = false;
                    $instructor_name = '';
                    
                    // Try instructorID first
                    $instructor_id = get_post_meta($sub_event->ID, 'instructorID', true);
                    if ($instructor_id) {
                        $instructor_post = get_post($instructor_id);
                        if ($instructor_post && $instructor_post->post_type === 'instructor') {
                            $instructor_name = $instructor_post->post_title;
                            if ($instructor === 'post_' . $instructor_id) {
                                $instructor_match = true;
                            }
                        } else {
                            // Try to get user by ID
                            $instructor_user = get_user_by('id', $instructor_id);
                            if ($instructor_user) {
                                $instructor_name = $instructor_user->display_name;
                                if ($instructor === 'user_' . $instructor_id) {
                                    $instructor_match = true;
                                }
                            }
                        }
                    }
                    
                    // If instructorID didn't work, try instructorEmail
                    if (empty($instructor_name)) {
                        $instructor_email = get_post_meta($sub_event->ID, 'instructorEmail', true);
                        if ($instructor_email) {
                            $instructor_user = get_user_by('email', $instructor_email);
                            if ($instructor_user) {
                                $instructor_name = $instructor_user->display_name;
                                if ($instructor === 'user_' . $instructor_user->ID) {
                                    $instructor_match = true;
                                }
                            }
                        }
                    }
                    
                    // If still empty, try alternative field names
                    if (empty($instructor_name)) {
                        $instructor_name = get_post_meta($sub_event->ID, 'instructor', true);
                    }
                    if (empty($instructor_name)) {
                        $instructor_name = get_post_meta($sub_event->ID, 'event_instructor', true);
                    }
                    
                    // If still empty, try to get instructors from parent event
                    if (empty($instructor_name)) {
                        $parent_instructors = get_field('instructors', $parent->ID);
                        if ($parent_instructors && is_array($parent_instructors)) {
                            $instructor_names = array();
                            foreach ($parent_instructors as $instructor_user) {
                                if (is_object($instructor_user) && isset($instructor_user->display_name)) {
                                    $instructor_names[] = $instructor_user->display_name;
                                    if ($instructor === 'user_' . $instructor_user->ID) {
                                        $instructor_match = true;
                                    }
                                }
                            }
                            if (!empty($instructor_names)) {
                                $instructor_name = implode(', ', $instructor_names);
                            }
                        }
                    }
                    
                    // Skip if instructor filter is set and doesn't match
                    if (!empty($instructor) && !$instructor_match) {
                        continue;
                    }
                    
                    $attendees_count = get_post_meta($sub_event->ID, 'related_orders_count', true);
                    $revenue = get_post_meta($sub_event->ID, 'total_revenue', true);
                    $profit = get_post_meta($sub_event->ID, 'sub_event_profit', true);
                    
                    // If no attendees, set profit to 0 (no expenses without students)
                    if (intval($attendees_count) === 0) {
                        $profit = 0;
                    }
                    
                    $parent_data['attendees'] += intval($attendees_count);
                    $parent_data['revenue'] += floatval($revenue);
                    $parent_data['profit'] += floatval($profit);
                    
                    $instructor_expense = get_post_meta($parent->ID, 'instructor_expense', true);
                    $materials_expense = get_post_meta($parent->ID, 'materials_expense', true);
                    $total_materials_expense = floatval($materials_expense) * intval($attendees_count);
                    $total_expenses = floatval($instructor_expense) + $total_materials_expense;
                    
                    // Only add expenses if there are attendees
                    if (intval($attendees_count) > 0) {
                        $parent_data['instructor_expense'] += floatval($instructor_expense);
                        $parent_data['materials_expense'] += $total_materials_expense;
                        $parent_data['total_expenses'] += $total_expenses;
                    }
                    
                    $parent_data['sub_events'][] = array(
                        'id' => $sub_event->ID,
                        'title' => $sub_event->post_title,
                        'date' => get_post_meta($sub_event->ID, 'event_start_time_stamp', true),
                        'attendees' => $attendees_count,
                        'revenue' => $revenue,
                        'profit' => $profit,
                        'instructor' => $instructor_name
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

    /**
     * Get time-based report data
     *
     * @param string $start_date Start date
     * @param string $end_date End date
     * @param string $time_period Time period (weekly, monthly, quarterly, yearly)
     * @param int $event_category Event category ID
     * @param string $include_non_ticketed Filter for ticketed events
     * @return array Report data
     */
    private function get_time_based_report($start_date, $end_date, $time_period = 'monthly', $event_category = 0, $include_non_ticketed = 'all', $instructor = '') {
        global $wpdb;
        
        // Get all sub events within date range
        $args = array(
            'post_type' => 'sub_event',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'event_start_time_stamp',
                    'value' => array($start_date, $end_date),
                    'compare' => 'BETWEEN',
                    'type' => 'DATE'
                )
            )
        );
        
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
                    'total_profit' => 0,
                    'time_period' => $time_period,
                    'periods' => array()
                );
            }
        }
        
        $sub_events = get_posts($args);
        
        // Filter out non-ticketed events if requested
        if ($include_non_ticketed === 'ticketed_only') {
            $ticketed_sub_events = array();
            foreach ($sub_events as $sub_event) {
                $parent_id = wp_get_post_parent_id($sub_event->ID);
                if ($parent_id) {
                    $has_tickets = get_post_meta($parent_id, 'has_tickets', true);
                    if ($has_tickets) {
                        $ticketed_sub_events[] = $sub_event;
                    }
                }
            }
            $sub_events = $ticketed_sub_events;
        }
        
        // Initialize time periods
        $periods = array();
        $current_date = new DateTime($start_date);
        $end_datetime = new DateTime($end_date);
        
        while ($current_date <= $end_datetime) {
            $period_key = '';
            $period_label = '';
            $period_start = clone $current_date;
            $period_end = clone $current_date;
            
            switch ($time_period) {
                case 'weekly':
                    // Set to Monday of the week
                    $current_date->modify('Monday this week');
                    $period_key = $current_date->format('Y-m-d');
                    $period_label = 'Week of ' . $current_date->format('M j, Y');
                    $period_start = clone $current_date;
                    $period_end = clone $current_date;
                    $period_end->modify('+6 days');
                    $current_date->modify('+1 week');
                    break;
                    
                case 'monthly':
                    $period_key = $current_date->format('Y-m');
                    $period_label = $current_date->format('F Y');
                    $period_start = clone $current_date;
                    $period_start->modify('first day of this month');
                    $period_end = clone $current_date;
                    $period_end->modify('last day of this month');
                    $current_date->modify('+1 month');
                    break;
                    
                case 'quarterly':
                    $quarter = ceil($current_date->format('n') / 3);
                    $period_key = $current_date->format('Y') . '-Q' . $quarter;
                    $period_label = 'Q' . $quarter . ' ' . $current_date->format('Y');
                    $period_start = clone $current_date;
                    $period_start->modify('first day of January')->modify('+' . (($quarter - 1) * 3) . ' months');
                    $period_end = clone $period_start;
                    $period_end->modify('+2 months')->modify('last day of this month');
                    $current_date->modify('+3 months');
                    break;
                    
                case 'yearly':
                    $period_key = $current_date->format('Y');
                    $period_label = $current_date->format('Y');
                    $period_start = clone $current_date;
                    $period_start->modify('January 1st');
                    $period_end = clone $current_date;
                    $period_end->modify('December 31st');
                    $current_date->modify('+1 year');
                    break;
            }
            
            $periods[$period_key] = array(
                'label' => $period_label,
                'start_date' => $period_start->format('Y-m-d'),
                'end_date' => $period_end->format('Y-m-d'),
                'events_count' => 0,
                'attendees' => 0,
                'revenue' => 0,
                'profit' => 0,
                'events' => array()
            );
        }
        
        // Process each sub event and assign to appropriate period
        foreach ($sub_events as $sub_event) {
            // Get instructor information for filtering
            $instructor_match = false;
            $instructor_name = '';
            
            // Try instructorID first
            $instructor_id = get_post_meta($sub_event->ID, 'instructorID', true);
            if ($instructor_id) {
                $instructor_post = get_post($instructor_id);
                if ($instructor_post && $instructor_post->post_type === 'instructor') {
                    $instructor_name = $instructor_post->post_title;
                    if ($instructor === 'post_' . $instructor_id) {
                        $instructor_match = true;
                    }
                } else {
                    // Try to get user by ID
                    $instructor_user = get_user_by('id', $instructor_id);
                    if ($instructor_user) {
                        $instructor_name = $instructor_user->display_name;
                        if ($instructor === 'user_' . $instructor_id) {
                            $instructor_match = true;
                        }
                    }
                }
            }
            
            // If instructorID didn't work, try instructorEmail
            if (empty($instructor_name)) {
                $instructor_email = get_post_meta($sub_event->ID, 'instructorEmail', true);
                if ($instructor_email) {
                    $instructor_user = get_user_by('email', $instructor_email);
                    if ($instructor_user) {
                        $instructor_name = $instructor_user->display_name;
                        if ($instructor === 'user_' . $instructor_user->ID) {
                            $instructor_match = true;
                        }
                    }
                }
            }
            
            // If still empty, try alternative field names
            if (empty($instructor_name)) {
                $instructor_name = get_post_meta($sub_event->ID, 'instructor', true);
            }
            if (empty($instructor_name)) {
                $instructor_name = get_post_meta($sub_event->ID, 'event_instructor', true);
            }
            
            // If still empty, try to get instructors from parent event
            if (empty($instructor_name)) {
                $parent_id = wp_get_post_parent_id($sub_event->ID);
                if ($parent_id) {
                    $parent_instructors = get_field('instructors', $parent_id);
                    if ($parent_instructors && is_array($parent_instructors)) {
                        $instructor_names = array();
                        foreach ($parent_instructors as $instructor_user) {
                            if (is_object($instructor_user) && isset($instructor_user->display_name)) {
                                $instructor_names[] = $instructor_user->display_name;
                                if ($instructor === 'user_' . $instructor_user->ID) {
                                    $instructor_match = true;
                                }
                            }
                        }
                        if (!empty($instructor_names)) {
                            $instructor_name = implode(', ', $instructor_names);
                        }
                    }
                }
            }
            
            // Skip if instructor filter is set and doesn't match
            if (!empty($instructor) && !$instructor_match) {
                continue;
            }
            
            $event_date = get_post_meta($sub_event->ID, 'event_start_time_stamp', true);
            $event_datetime = new DateTime($event_date);
            
            // Find the appropriate period for this event
            foreach ($periods as $period_key => $period) {
                $period_start = new DateTime($period['start_date']);
                $period_end = new DateTime($period['end_date']);
                
                if ($event_datetime >= $period_start && $event_datetime <= $period_end) {
                    $attendees_count = get_post_meta($sub_event->ID, 'related_orders_count', true);
                    $revenue = get_post_meta($sub_event->ID, 'total_revenue', true);
                    $profit = get_post_meta($sub_event->ID, 'sub_event_profit', true);
                    
                    // If no attendees, set profit to 0 (no expenses without students)
                    if (intval($attendees_count) === 0) {
                        $profit = 0;
                    }
                    
                    $periods[$period_key]['events_count']++;
                    $periods[$period_key]['attendees'] += intval($attendees_count);
                    $periods[$period_key]['revenue'] += floatval($revenue);
                    $periods[$period_key]['profit'] += floatval($profit);
                    
                    $parent_id = wp_get_post_parent_id($sub_event->ID);
                    $parent_title = $parent_id ? get_the_title($parent_id) : 'No Parent';
                    
                    $periods[$period_key]['events'][] = array(
                        'id' => $sub_event->ID,
                        'title' => $sub_event->post_title,
                        'parent_id' => $parent_id,
                        'parent_title' => $parent_title,
                        'instructor' => $instructor_name,
                        'date' => $event_date,
                        'attendees' => $attendees_count,
                        'revenue' => $revenue,
                        'profit' => $profit
                    );
                    
                    break;
                }
            }
        }
        
        // Calculate totals
        $total_events = 0;
        $total_attendees = 0;
        $total_revenue = 0;
        $total_profit = 0;
        
        foreach ($periods as $period) {
            $total_events += $period['events_count'];
            $total_attendees += $period['attendees'];
            $total_revenue += $period['revenue'];
            $total_profit += $period['profit'];
        }
        
        return array(
            'total_events' => $total_events,
            'total_attendees' => $total_attendees,
            'total_revenue' => $total_revenue,
            'total_profit' => $total_profit,
            'time_period' => $time_period,
            'periods' => $periods
        );
    }

    private function display_report_results($report_data) {
        $report_type = isset($_GET['report_type']) ? sanitize_text_field($_GET['report_type']) : 'summary';
        
        echo '<div class="report-results-container" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">';
        
        // Display export button
        $this->display_export_button($report_type);
        
        // Display summary cards
        if ($report_type === 'comparison') {
            $this->display_comparison_summary_cards($report_data);
        } else {
            $this->display_summary_cards($report_data);
        }
        
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
            case 'comparison':
                $this->display_comparison_table($report_data);
                break;
            case 'time_based':
                $this->display_time_based_table($report_data);
                break;
        }
        
        echo '</div>';
    }

    /**
     * Display export button for reports
     */
    private function display_export_button($report_type) {
        // Get current URL parameters
        $params = $_GET;
        $params['export_csv'] = '1';
        $params['csv_export_nonce'] = wp_create_nonce('csv_export_nonce');
        
        // Build export URL
        $export_url = add_query_arg($params, admin_url('edit.php?post_type=events&page=events-reports'));
        
        echo '<div style="margin-bottom: 20px;">';
        echo '<a href="' . esc_url($export_url) . '" class="button button-secondary export-button">';
        echo '<span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 5px;"></span>';
        echo 'Export ' . ucfirst(esc_html($report_type)) . ' Report to CSV';
        echo '</a>';
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

    /**
     * Display comparison summary cards
     *
     * @param array $report_data Comparison report data
     */
    private function display_comparison_summary_cards($report_data) {
        echo '<div class="summary-cards" style="display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap;">';
        
        $metrics = array(
            'total_parents' => array('label' => 'Parent Events', 'color' => '#0073aa'),
            'total_events' => array('label' => 'Sub Events', 'color' => '#7c7c7c'),
            'total_attendees' => array('label' => 'Attendees', 'color' => '#46b450'),
            'total_revenue' => array('label' => 'Revenue', 'color' => '#ffb900'),
            'total_profit' => array('label' => 'Profit', 'color' => '#46b450')
        );
        
        foreach ($metrics as $key => $metric) {
            $value_1 = isset($report_data['period_1'][$key]) ? floatval($report_data['period_1'][$key]) : 0;
            $value_2 = isset($report_data['period_2'][$key]) ? floatval($report_data['period_2'][$key]) : 0;
            $difference = isset($report_data['differences'][$key]) ? floatval($report_data['differences'][$key]) : 0;
            $percentage = isset($report_data['percentages'][$key]) ? floatval($report_data['percentages'][$key]) : 0;
            
            $profit_color = ($key === 'total_profit' && $value_2 < 0) ? '#dc3232' : $metric['color'];
            
            echo '<div class="card" style="flex: 1; min-width: 200px; background: #f8f9fa; padding: 20px; border-radius: 4px; border-left: 4px solid ' . $profit_color . ';">';
            echo '<h3 style="margin: 0 0 10px 0; color: #23282d;">' . esc_html($metric['label']) . '</h3>';
            echo '<div style="display: flex; justify-content: space-between; align-items: center;">';
            echo '<div>';
            echo '<p style="margin: 0; font-size: 14px; color: #666;">Period 1</p>';
            if ($key === 'total_revenue' || $key === 'total_profit') {
                echo '<p style="margin: 0; font-size: 18px; font-weight: bold;">$' . number_format($value_1, 2) . '</p>';
            } else {
                echo '<p style="margin: 0; font-size: 18px; font-weight: bold;">' . number_format($value_1) . '</p>';
            }
            echo '</div>';
            echo '<div style="text-align: right;">';
            echo '<p style="margin: 0; font-size: 14px; color: #666;">Period 2</p>';
            if ($key === 'total_revenue' || $key === 'total_profit') {
                echo '<p style="margin: 0; font-size: 18px; font-weight: bold;">$' . number_format($value_2, 2) . '</p>';
            } else {
                echo '<p style="margin: 0; font-size: 18px; font-weight: bold;">' . number_format($value_2) . '</p>';
            }
            echo '</div>';
            echo '</div>';
            echo '<div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">';
            echo '<div style="display: flex; justify-content: space-between; align-items: center;">';
            echo '<span style="font-size: 14px; color: ' . ($difference >= 0 ? '#46b450' : '#dc3232') . ';">';
            if ($key === 'total_revenue' || $key === 'total_profit') {
                echo '$' . number_format($difference, 2);
            } else {
                echo number_format($difference);
            }
            echo '</span>';
            echo '<span style="font-size: 14px; color: ' . ($percentage >= 0 ? '#46b450' : '#dc3232') . ';">' . number_format($percentage, 1) . '%</span>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
    }

    private function display_summary_table($report_data) {
        echo '<h2>Parent Event Summary</h2>';
        echo '<table class="wp-list-table widefat fixed striped sortable-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th class="sortable-column" data-column="0" data-type="text">Parent Event</th>';
        echo '<th class="sortable-column" data-column="1" data-type="numeric">Sub Events</th>';
        echo '<th class="sortable-column" data-column="2" data-type="numeric">Attendees</th>';
        echo '<th class="sortable-column" data-column="3" data-type="numeric">Revenue</th>';
        echo '<th class="sortable-column" data-column="4" data-type="numeric">Instructor Expense</th>';
        echo '<th class="sortable-column" data-column="5" data-type="numeric">Materials Expense</th>';
        echo '<th class="sortable-column" data-column="6" data-type="numeric">Total Expenses</th>';
        echo '<th class="sortable-column" data-column="7" data-type="numeric">Profit</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($report_data['parents'] as $parent) {
            echo '<tr>';
            echo '<td><strong><a href="' . get_edit_post_link($parent['id']) . '" target="_blank">' . esc_html($parent['title']) . '</a></strong></td>';
            echo '<td>' . esc_html($parent['sub_events_count']) . '</td>';
            echo '<td>' . esc_html($parent['attendees']) . '</td>';
            echo '<td>$' . number_format($parent['revenue'], 2) . '</td>';
            echo '<td>$' . number_format($parent['instructor_expense'], 2) . '</td>';
            echo '<td>$' . number_format($parent['materials_expense'], 2) . '</td>';
            echo '<td>$' . number_format($parent['total_expenses'], 2) . '</td>';
            echo '<td style="color: ' . ($parent['profit'] >= 0 ? '#46b450' : '#dc3232') . ';">' . ($parent['attendees'] > 0 ? '$' . number_format($parent['profit'], 2) : '-') . '</td>';
            echo '</tr>';
            
            // Show sub events as child rows
            if (!empty($parent['sub_events'])) {
                foreach ($parent['sub_events'] as $sub_event) {
                    echo '<tr class="child-row">';
                    $formatted_date = date('l - F j, Y', strtotime($sub_event['date']));
                    echo '<td style="padding-left: 30px;" data-date="' . esc_attr($sub_event['date']) . '">&nbsp;&nbsp;&nbsp;→ ' . esc_html($formatted_date) . ' - ' . esc_html($sub_event['title']) . '</td>';
                    echo '<td>-</td>';
                    echo '<td>' . esc_html($sub_event['attendees']) . '</td>';
                    echo '<td>$' . number_format($sub_event['revenue'], 2) . '</td>';
                    echo '<td>-</td>';
                    echo '<td>-</td>';
                    echo '<td>-</td>';
                    echo '<td style="color: ' . ($sub_event['profit'] >= 0 ? '#46b450' : '#dc3232') . ';">' . ($sub_event['attendees'] > 0 ? '$' . number_format($sub_event['profit'], 2) : '-') . '</td>';
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
        echo '<table class="wp-list-table widefat fixed striped sortable-table">';
        echo '<thead><tr>
            <th class="sortable-column" data-column="0" data-type="text">Event</th>
            <th class="sortable-column" data-column="1" data-type="text">Parent Event</th>
            <th class="sortable-column" data-column="2" data-type="text">Instructor</th>
            <th class="sortable-column" data-column="3" data-type="date">Date</th>
            <th class="sortable-column" data-column="4" data-type="numeric">Attendees</th>
            <th class="sortable-column" data-column="5" data-type="numeric">Revenue</th>
            <th class="sortable-column" data-column="6" data-type="numeric">Instructor Expense</th>
            <th class="sortable-column" data-column="7" data-type="numeric">Materials/Attendee</th>
            <th class="sortable-column" data-column="8" data-type="numeric">Total Materials</th>
            <th class="sortable-column" data-column="9" data-type="numeric">Total Expenses</th>
            <th class="sortable-column" data-column="10" data-type="numeric">Profit</th>
            <th class="sortable-column" data-column="11" data-type="text">Customer Orders</th>
            </tr></thead>';
        echo '<tbody>';
        
        foreach ($report_data['events'] as $event) {
            echo '<tr>';
            echo '<td><a href="' . get_edit_post_link($event['id']) . '" target="_blank">' . esc_html($event['title']) . '</a></td>';
            echo '<td><a href="' . get_edit_post_link($event['parent_id']) . '" target="_blank">' . esc_html($event['parent_title']) . '</a></td>';
            echo '<td>' . esc_html($event['instructor']) . '</td>';
            echo '<td data-date="' . esc_attr($event['date']) . '">' . esc_html(date('F j, Y', strtotime($event['date']))) . '</td>';
            echo '<td>' . esc_html($event['attendees']) . '</td>';
            echo '<td>$' . number_format(floatval($event['revenue']), 2) . '</td>';
            echo '<td>$' . number_format(floatval($event['instructor_expense']), 2) . '</td>';
            echo '<td>$' . number_format(floatval($event['materials_expense_per_attendee']), 2) . '</td>';
            echo '<td>$' . number_format(floatval($event['total_materials_expense']), 2) . '</td>';
            echo '<td>$' . number_format(floatval($event['total_expenses']), 2) . '</td>';
            echo '<td style="color: ' . (floatval($event['profit']) >= 0 ? '#46b450' : '#dc3232') . ';">' . ($event['attendees'] > 0 ? '$' . number_format(floatval($event['profit']), 2) : '-') . '</td>';
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
            echo '<h3><a href="' . get_edit_post_link($parent['id']) . '" target="_blank">' . esc_html($parent['title']) . '</a></h3>';
            echo '<p><strong>Sub Events:</strong> ' . esc_html($parent['sub_events_count']) . ' |
                   <strong>Total Attendees:</strong> ' . esc_html($parent['attendees']) . ' |
                   <strong>Total Revenue:</strong> $' . number_format(floatval($parent['revenue']), 2) . ' |
                   <strong>Instructor Cost:</strong> $' . number_format(floatval($parent['instructor_expense']), 2) . ' |
                   <strong>Materials Cost:</strong> $' . number_format(floatval($parent['materials_expense']), 2) . ' |
                   <strong>Total Expenses:</strong> $' . number_format(floatval($parent['total_expenses']), 2) . ' |
                   <strong>Total Profit:</strong> <span style="color: ' . (floatval($parent['profit']) >= 0 ? '#46b450' : '#dc3232') . ';">' . ($parent['attendees'] > 0 ? '$' . number_format(floatval($parent['profit']), 2) : '-') . '</span></p>';
            
            if (!empty($parent['sub_events'])) {
                echo '<table class="wp-list-table widefat fixed striped sortable-table" style="margin-top: 15px;">';
                echo '<thead><tr>
                    <th class="sortable-column" data-column="0" data-type="text">Sub Event</th>
                    <th class="sortable-column" data-column="1" data-type="text">Instructor</th>
                    <th class="sortable-column" data-column="2" data-type="date">Date</th>
                    <th class="sortable-column" data-column="3" data-type="numeric">Attendees</th>
                    <th class="sortable-column" data-column="4" data-type="numeric">Revenue</th>
                    <th class="sortable-column" data-column="5" data-type="numeric">Instructor Cost</th>
                    <th class="sortable-column" data-column="6" data-type="numeric">Materials/Attendee</th>
                    <th class="sortable-column" data-column="7" data-type="numeric">Total Materials</th>
                    <th class="sortable-column" data-column="8" data-type="numeric">Total Expenses</th>
                    <th class="sortable-column" data-column="9" data-type="numeric">Profit</th>
                    </tr></thead>';
                echo '<tbody>';
                
                foreach ($parent['sub_events'] as $sub_event) {
                    echo '<tr>';
                    echo '<td><a href="' . get_edit_post_link($sub_event['id']) . '" target="_blank">' . esc_html($sub_event['title']) . '</a></td>';
                    echo '<td>' . esc_html($sub_event['instructor']) . '</td>';
                    echo '<td data-date="' . esc_attr($sub_event['date']) . '">' . esc_html(date('F j, Y', strtotime($sub_event['date']))) . '</td>';
                    echo '<td>' . esc_html($sub_event['attendees']) . '</td>';
                    echo '<td>$' . number_format(floatval($sub_event['revenue']), 2) . '</td>';
                    echo '<td>$' . number_format(floatval($sub_event['instructor_expense']), 2) . '</td>';
                    echo '<td>$' . number_format(floatval($sub_event['materials_expense_per_attendee']), 2) . '</td>';
                    echo '<td>$' . number_format(floatval($sub_event['total_materials_expense']), 2) . '</td>';
                    echo '<td>$' . number_format(floatval($sub_event['total_expenses']), 2) . '</td>';
                    echo '<td style="color: ' . (floatval($sub_event['profit']) >= 0 ? '#46b450' : '#dc3232') . ';">' . ($sub_event['attendees'] > 0 ? '$' . number_format(floatval($sub_event['profit']), 2) : '-') . '</td>';
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
        echo '<table class="wp-list-table widefat fixed striped sortable-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th class="sortable-column" data-column="0" data-type="text">Category</th>';
        echo '<th class="sortable-column" data-column="1" data-type="numeric">Parent Events</th>';
        echo '<th class="sortable-column" data-column="2" data-type="numeric">Sub Events</th>';
        echo '<th class="sortable-column" data-column="3" data-type="numeric">Attendees</th>';
        echo '<th class="sortable-column" data-column="4" data-type="numeric">Revenue</th>';
        echo '<th class="sortable-column" data-column="5" data-type="numeric">Instructor Expense</th>';
        echo '<th class="sortable-column" data-column="6" data-type="numeric">Materials Expense</th>';
        echo '<th class="sortable-column" data-column="7" data-type="numeric">Total Expenses</th>';
        echo '<th class="sortable-column" data-column="8" data-type="numeric">Profit</th>';
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
            echo '<td style="color: ' . ($category['profit'] >= 0 ? '#46b450' : '#dc3232') . ';">' . ($category['attendees'] > 0 ? '$' . number_format($category['profit'], 2) : '-') . '</td>';
            echo '</tr>';
            
            // Show parent events as child rows
            if (!empty($category['parent_events'])) {
                foreach ($category['parent_events'] as $parent) {
                    echo '<tr class="child-row">';
                    echo '<td style="padding-left: 30px;">&nbsp;&nbsp;&nbsp;→ <a href="' . get_edit_post_link($parent['id']) . '" target="_blank">' . esc_html($parent['title']) . '</a></td>';
                    echo '<td>-</td>';
                    echo '<td>' . esc_html($parent['sub_events_count']) . '</td>';
                    echo '<td>' . esc_html($parent['attendees']) . '</td>';
                    echo '<td>$' . number_format($parent['revenue'], 2) . '</td>';
                    echo '<td>$' . number_format($parent['instructor_expense'], 2) . '</td>';
                    echo '<td>$' . number_format($parent['materials_expense'], 2) . '</td>';
                    echo '<td>$' . number_format($parent['total_expenses'], 2) . '</td>';
                    echo '<td style="color: ' . ($parent['profit'] >= 0 ? '#46b450' : '#dc3232') . ';">' . ($parent['attendees'] > 0 ? '$' . number_format($parent['profit'], 2) : '-') . '</td>';
                    echo '</tr>';
                    
                    // Show sub events as grandchild rows
                    if (!empty($parent['sub_events'])) {
                        foreach ($parent['sub_events'] as $sub_event) {
                            echo '<tr class="grandchild-row">';
                            $formatted_date = date('l - F j, Y', strtotime($sub_event['date']));
                            echo '<td style="padding-left: 60px;" data-date="' . esc_attr($sub_event['date']) . '">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;→ ' . esc_html($formatted_date) . '</td>';
                            echo '<td>-</td>';
                            echo '<td>-</td>';
                            echo '<td>' . esc_html($sub_event['attendees']) . '</td>';
                            echo '<td>$' . number_format($sub_event['revenue'], 2) . '</td>';
                            echo '<td>-</td>';
                            echo '<td>-</td>';
                            echo '<td>-</td>';
                            echo '<td style="color: ' . ($sub_event['profit'] >= 0 ? '#46b450' : '#dc3232') . ';">' . ($sub_event['attendees'] > 0 ? '$' . number_format($sub_event['profit'], 2) : '-') . '</td>';
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

    /**
     * Display comparison table
     *
     * @param array $report_data Comparison report data
     */
    private function display_comparison_table($report_data) {
        $start_date_1 = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $end_date_1 = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
        $start_date_2 = isset($_GET['start_date_2']) ? sanitize_text_field($_GET['start_date_2']) : '';
        $end_date_2 = isset($_GET['end_date_2']) ? sanitize_text_field($_GET['end_date_2']) : '';
        
        echo '<h2>Comparison Report</h2>';
        echo '<p><strong>Period 1:</strong> ' . esc_html($start_date_1) . ' to ' . esc_html($end_date_1) . '</p>';
        echo '<p><strong>Period 2:</strong> ' . esc_html($start_date_2) . ' to ' . esc_html($end_date_2) . '</p>';
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Metric</th>';
        echo '<th>Period 1</th>';
        echo '<th>Period 2</th>';
        echo '<th>Difference</th>';
        echo '<th>Change %</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        $metrics = array(
            'total_parents' => 'Parent Events',
            'total_events' => 'Sub Events',
            'total_attendees' => 'Attendees',
            'total_revenue' => 'Revenue',
            'total_profit' => 'Profit'
        );
        
        foreach ($metrics as $key => $label) {
            $value_1 = isset($report_data['period_1'][$key]) ? floatval($report_data['period_1'][$key]) : 0;
            $value_2 = isset($report_data['period_2'][$key]) ? floatval($report_data['period_2'][$key]) : 0;
            $difference = isset($report_data['differences'][$key]) ? floatval($report_data['differences'][$key]) : 0;
            $percentage = isset($report_data['percentages'][$key]) ? floatval($report_data['percentages'][$key]) : 0;
            
            echo '<tr>';
            echo '<td><strong>' . esc_html($label) . '</strong></td>';
            
            if ($key === 'total_revenue' || $key === 'total_profit') {
                echo '<td>$' . number_format($value_1, 2) . '</td>';
                echo '<td>$' . number_format($value_2, 2) . '</td>';
                echo '<td style="color: ' . ($difference >= 0 ? '#46b450' : '#dc3232') . ';">' . ($value_2 > 0 ? '$' . number_format($difference, 2) : '-') . '</td>';
            } else {
                echo '<td>' . number_format($value_1) . '</td>';
                echo '<td>' . number_format($value_2) . '</td>';
                echo '<td style="color: ' . ($difference >= 0 ? '#46b450' : '#dc3232') . ';">' . number_format($difference) . '</td>';
            }
            
            echo '<td style="color: ' . ($percentage >= 0 ? '#46b450' : '#dc3232') . ';">' . number_format($percentage, 1) . '%</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }

    /**
     * Display time-based table with graphs
     *
     * @param array $report_data Time-based report data
     */
    private function display_time_based_table($report_data) {
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
        $time_period = isset($report_data['time_period']) ? $report_data['time_period'] : 'monthly';
        
        echo '<h2>Time-based Report</h2>';
        echo '<p><strong>Date Range:</strong> ' . esc_html($start_date) . ' to ' . esc_html($end_date) . '</p>';
        echo '<p><strong>Time Period:</strong> ' . ucfirst(esc_html($time_period)) . '</p>';
        
        // Display summary cards
        echo '<div class="summary-cards" style="display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap;">';
        
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
        echo '<p style="margin: 0; font-size: 24px; font-weight: bold;">' . ($report_data['total_attendees'] > 0 ? '$' . number_format(floatval($report_data['total_profit']), 2) : '-') . '</p>';
        echo '</div>';
        
        echo '</div>';
        
        // Display graphs
        echo '<div class="graphs-container" style="margin-bottom: 30px;">';
        echo '<h3>Performance Over Time</h3>';
        
        // Prepare data for charts
        $period_labels = array();
        $events_data = array();
        $attendees_data = array();
        $revenue_data = array();
        $profit_data = array();
        
        foreach ($report_data['periods'] as $period) {
            $period_labels[] = $period['label'];
            $events_data[] = $period['events_count'];
            $attendees_data[] = $period['attendees'];
            $revenue_data[] = $period['revenue'];
            $profit_data[] = $period['profit'];
        }
        
        // Enqueue Chart.js
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.7.1', true);
        
        // Add inline script for charts
        wp_add_inline_script('chartjs', '
            jQuery(document).ready(function($) {
                // Revenue and Profit Chart
                var revenueProfitCtx = document.getElementById("revenue-profit-chart").getContext("2d");
                var revenueProfitChart = new Chart(revenueProfitCtx, {
                    type: "line",
                    data: {
                        labels: ' . json_encode($period_labels) . ',
                        datasets: [{
                            label: "Revenue",
                            data: ' . json_encode($revenue_data) . ',
                            borderColor: "#ffb900",
                            backgroundColor: "rgba(255, 185, 0, 0.1)",
                            fill: true,
                            tension: 0.1,
                            yAxisID: "y"
                        }, {
                            label: "Profit",
                            data: ' . json_encode($profit_data) . ',
                            borderColor: "' . (floatval($report_data['total_profit']) >= 0 ? '#46b450' : '#dc3232') . '",
                            backgroundColor: "rgba(70, 180, 80, 0.1)",
                            fill: true,
                            tension: 0.1,
                            yAxisID: "y"
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: "Revenue and Profit Over Time"
                            },
                            legend: {
                                position: "top"
                            }
                        },
                        scales: {
                            y: {
                                type: "linear",
                                display: true,
                                position: "left",
                                ticks: {
                                    callback: function(value) {
                                        return "$" + value.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });
                
                // Events and Attendees Chart
                var eventsAttendeesCtx = document.getElementById("events-attendees-chart").getContext("2d");
                var eventsAttendeesChart = new Chart(eventsAttendeesCtx, {
                    type: "bar",
                    data: {
                        labels: ' . json_encode($period_labels) . ',
                        datasets: [{
                            label: "Sub Events",
                            data: ' . json_encode($events_data) . ',
                            backgroundColor: "#7c7c7c",
                            borderColor: "#7c7c7c",
                            borderWidth: 1,
                            yAxisID: "y"
                        }, {
                            label: "Attendees",
                            data: ' . json_encode($attendees_data) . ',
                            backgroundColor: "#46b450",
                            borderColor: "#46b450",
                            borderWidth: 1,
                            yAxisID: "y1"
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: "Events and Attendees Over Time"
                            },
                            legend: {
                                position: "top"
                            }
                        },
                        scales: {
                            y: {
                                type: "linear",
                                display: true,
                                position: "left",
                                title: {
                                    display: true,
                                    text: "Sub Events"
                                }
                            },
                            y1: {
                                type: "linear",
                                display: true,
                                position: "right",
                                title: {
                                    display: true,
                                    text: "Attendees"
                                },
                                grid: {
                                    drawOnChartArea: false
                                }
                            }
                        }
                    }
                });
            });
        ');
        
        // Display chart containers
        echo '<div style="margin-bottom: 30px;">';
        echo '<div style="height: 400px; margin-bottom: 20px;">';
        echo '<canvas id="revenue-profit-chart"></canvas>';
        echo '</div>';
        echo '<div style="height: 400px;">';
        echo '<canvas id="events-attendees-chart"></canvas>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Display detailed table
        echo '<h3>Detailed ' . ucfirst(esc_html($time_period)) . ' Breakdown</h3>';
        echo '<table class="wp-list-table widefat fixed striped sortable-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th class="sortable-column" data-column="0" data-type="text">Period</th>';
        echo '<th class="sortable-column" data-column="1" data-type="date">Date Range</th>';
        echo '<th class="sortable-column" data-column="2" data-type="numeric">Sub Events</th>';
        echo '<th class="sortable-column" data-column="3" data-type="numeric">Attendees</th>';
        echo '<th class="sortable-column" data-column="4" data-type="numeric">Revenue</th>';
        echo '<th class="sortable-column" data-column="5" data-type="numeric">Profit</th>';
        echo '<th class="sortable-column" data-column="6" data-type="numeric">Avg. Revenue/Event</th>';
        echo '<th class="sortable-column" data-column="7" data-type="numeric">Avg. Profit/Event</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($report_data['periods'] as $period) {
            $avg_revenue = $period['events_count'] > 0 ? $period['revenue'] / $period['events_count'] : 0;
            $avg_profit = $period['events_count'] > 0 ? $period['profit'] / $period['events_count'] : 0;
            
            echo '<tr>';
            echo '<td><strong>' . esc_html($period['label']) . '</strong></td>';
            echo '<td data-date="' . esc_attr($period['start_date']) . '">' . esc_html($period['start_date']) . ' to ' . esc_html($period['end_date']) . '</td>';
            echo '<td>' . esc_html($period['events_count']) . '</td>';
            echo '<td>' . esc_html($period['attendees']) . '</td>';
            echo '<td>$' . number_format($period['revenue'], 2) . '</td>';
            echo '<td style="color: ' . ($period['profit'] >= 0 ? '#46b450' : '#dc3232') . ';">' . ($period['attendees'] > 0 ? '$' . number_format($period['profit'], 2) : '-') . '</td>';
            echo '<td>$' . number_format($avg_revenue, 2) . '</td>';
            echo '<td style="color: ' . ($avg_profit >= 0 ? '#46b450' : '#dc3232') . ';">$' . number_format($avg_profit, 2) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '<tfoot>';
        echo '<tr>';
        echo '<th colspan="2">Totals</th>';
        echo '<th>' . esc_html($report_data['total_events']) . '</th>';
        echo '<th>' . esc_html($report_data['total_attendees']) . '</th>';
        echo '<th>$' . number_format($report_data['total_revenue'], 2) . '</th>';
        echo '<th style="color: ' . ($report_data['total_profit'] >= 0 ? '#46b450' : '#dc3232') . ';">$' . number_format($report_data['total_profit'], 2) . '</th>';
        echo '<th>$' . number_format($report_data['total_events'] > 0 ? $report_data['total_revenue'] / $report_data['total_events'] : 0, 2) . '</th>';
        echo '<th style="color: ' . ($report_data['total_profit'] >= 0 ? '#46b450' : '#dc3232') . ';">' . ($report_data['total_attendees'] > 0 ? '$' . number_format($report_data['total_events'] > 0 ? $report_data['total_profit'] / $report_data['total_events'] : 0, 2) : '-') . '</th>';
        echo '</tr>';
        echo '</tfoot>';
        echo '</table>';
    }
}

new MindEventsReports();