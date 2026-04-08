<?php

if (!defined('ABSPATH')) {
    exit;
}

class mindEventsOptions {
    public function __construct() {
        add_action('admin_menu', array($this, 'register_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_settings_page() {
        add_options_page(
            __('Simple Events Settings', 'simple-events'),
            __('Simple Events', 'simple-events'),
            'manage_options',
            'mindevents-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting(
            'mindeventsPlugin',
            MINDEVENTS_PREPEND . 'support_settings',
            array($this, 'sanitize_settings')
        );

        add_settings_section(
            MINDEVENTS_PREPEND . 'general_settings',
            __('Calendar Defaults', 'simple-events'),
            '__return_false',
            'mindeventsPlugin'
        );

        add_settings_field(
            MINDEVENTS_PREPEND . 'start_day',
            __('Week Start Day', 'simple-events'),
            array($this, 'render_select_field'),
            'mindeventsPlugin',
            MINDEVENTS_PREPEND . 'general_settings',
            array(
                'field'   => MINDEVENTS_PREPEND . 'start_day',
                'options' => array(
                    'Sunday' => __('Sunday', 'simple-events'),
                    'Monday' => __('Monday', 'simple-events'),
                ),
                'default' => 'Monday',
                'help'    => __('Choose the first day shown in month and week views.', 'simple-events'),
            )
        );

        add_settings_field(
            MINDEVENTS_PREPEND . 'start_time',
            __('Default Start Time', 'simple-events'),
            array($this, 'render_time_field'),
            'mindeventsPlugin',
            MINDEVENTS_PREPEND . 'general_settings',
            array(
                'field'   => MINDEVENTS_PREPEND . 'start_time',
                'default' => '19:00',
                'help'    => __('Used when creating new occurrences in the admin calendar.', 'simple-events'),
            )
        );

        add_settings_field(
            MINDEVENTS_PREPEND . 'end_time',
            __('Default End Time', 'simple-events'),
            array($this, 'render_time_field'),
            'mindeventsPlugin',
            MINDEVENTS_PREPEND . 'general_settings',
            array(
                'field'   => MINDEVENTS_PREPEND . 'end_time',
                'default' => '21:00',
                'help'    => __('Used when creating new occurrences in the admin calendar.', 'simple-events'),
            )
        );
    }

    public function sanitize_settings($settings) {
        $settings = is_array($settings) ? $settings : array();

        return array(
            MINDEVENTS_PREPEND . 'start_day'  => in_array(($settings[MINDEVENTS_PREPEND . 'start_day'] ?? 'Monday'), array('Sunday', 'Monday'), true) ? $settings[MINDEVENTS_PREPEND . 'start_day'] : 'Monday',
            MINDEVENTS_PREPEND . 'start_time' => mindevents_normalize_time_value($settings[MINDEVENTS_PREPEND . 'start_time'] ?? '', '19:00'),
            MINDEVENTS_PREPEND . 'end_time'   => mindevents_normalize_time_value($settings[MINDEVENTS_PREPEND . 'end_time'] ?? '', '21:00'),
        );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Simple Events Settings', 'simple-events'); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('mindeventsPlugin');
                do_settings_sections('mindeventsPlugin');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function render_select_field($args) {
        $options = get_option(MINDEVENTS_PREPEND . 'support_settings', array());
        $field   = $args['field'];
        $value   = $options[$field] ?? $args['default'];

        echo '<select name="mindevents_support_settings[' . esc_attr($field) . ']" id="' . esc_attr($field) . '">';
        foreach ($args['options'] as $option_value => $label) {
            echo '<option value="' . esc_attr($option_value) . '" ' . selected($value, $option_value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';

        if (!empty($args['help'])) {
            echo '<p class="description">' . esc_html($args['help']) . '</p>';
        }
    }

    public function render_time_field($args) {
        $options = get_option(MINDEVENTS_PREPEND . 'support_settings', array());
        $field   = $args['field'];
        $value   = $options[$field] ?? $args['default'];

        echo '<input type="time" name="mindevents_support_settings[' . esc_attr($field) . ']" id="' . esc_attr($field) . '" value="' . esc_attr($value) . '">';

        if (!empty($args['help'])) {
            echo '<p class="description">' . esc_html($args['help']) . '</p>';
        }
    }
}

