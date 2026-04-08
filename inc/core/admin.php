<?php

if (!defined('ABSPATH')) {
    exit;
}

class mindeventsAdmin {
    private $options = array();
    private $default_start_time = '19:00';
    private $default_end_time = '21:00';

    public function __construct() {
        $this->options = get_option(MINDEVENTS_PREPEND . 'support_settings', array());
        $this->default_start_time = $this->options[MINDEVENTS_PREPEND . 'start_time'] ?? '19:00';
        $this->default_end_time   = $this->options[MINDEVENTS_PREPEND . 'end_time'] ?? '21:00';

        add_action('add_meta_boxes', array($this, 'add_events_metaboxes'));
        add_action('save_post_events', array($this, 'save_meta_info'), 10, 2);
    }

    public function add_events_metaboxes() {
        add_meta_box(
            MINDEVENTS_PREPEND . 'calendar',
            __('Occurrences', 'simple-events'),
            array($this, 'display_calendar_metabox'),
            'events',
            'normal',
            'default'
        );

        add_meta_box(
            MINDEVENTS_PREPEND . 'event_options',
            __('Event Settings', 'simple-events'),
            array($this, 'display_event_options_metabox'),
            'events',
            'side',
            'default'
        );
    }

    public function display_event_options_metabox() {
        $post_id          = get_the_ID();
        $event_type       = get_post_meta($post_id, 'event_type', true);
        $cal_display      = get_post_meta($post_id, 'cal_display', true);
        $show_past_events = get_post_meta($post_id, 'show_past_events', true);
        $location         = get_post_meta($post_id, 'mindevents_location', true);
        $visibility       = mindevents_get_post_visibility($post_id);
        $organizer_name   = get_post_meta($post_id, 'mindevents_organizer_name', true);
        $organizer_title  = get_post_meta($post_id, 'mindevents_organizer_title', true);
        $organizer_image  = get_post_meta($post_id, 'mindevents_organizer_image_id', true);

        wp_nonce_field('mindevents_event_meta', MINDEVENTS_PREPEND . 'event_meta_nonce');

        echo '<div class="mindevents-admin-panel">';
        $this->render_select_control(
            'event_meta[event_type]',
            'event_meta_event_type',
            __('Event Type', 'simple-events'),
            array(
                'multiple-events' => __('Multiple Unique Events', 'simple-events'),
                'single-event'    => __('One Event, Multiple Dates', 'simple-events'),
            ),
            $event_type ?: 'multiple-events'
        );
        $this->render_select_control(
            'event_meta[cal_display]',
            'event_meta_cal_display',
            __('Display Type', 'simple-events'),
            array(
                'list'     => __('List', 'simple-events'),
                'calendar' => __('Calendar', 'simple-events'),
            ),
            $cal_display ?: 'calendar'
        );
        $this->render_select_control(
            'event_meta[show_past_events]',
            'event_meta_show_past_events',
            __('Past Events', 'simple-events'),
            array(
                '0' => __('Show upcoming only', 'simple-events'),
                '1' => __('Show past and upcoming', 'simple-events'),
            ),
            ($show_past_events === '1') ? '1' : '0'
        );
        $this->render_select_control(
            'event_meta[mindevents_visibility]',
            'event_meta_mindevents_visibility',
            __('Visibility', 'simple-events'),
            array(
                'public'   => __('Public', 'simple-events'),
                'internal' => __('Internal', 'simple-events'),
            ),
            $visibility
        );
        $this->render_text_control('event_meta[mindevents_location]', 'event_meta_mindevents_location', __('Location', 'simple-events'), $location, __('Optional venue or room name.', 'simple-events'));
        $this->render_text_control('event_meta[mindevents_organizer_name]', 'event_meta_mindevents_organizer_name', __('Organizer Name', 'simple-events'), $organizer_name);
        $this->render_text_control('event_meta[mindevents_organizer_title]', 'event_meta_mindevents_organizer_title', __('Organizer Title', 'simple-events'), $organizer_title);
        $this->render_text_control('event_meta[mindevents_organizer_image_id]', 'event_meta_mindevents_organizer_image_id', __('Organizer Image ID', 'simple-events'), $organizer_image, __('Media Library attachment ID for the organizer photo.', 'simple-events'), 'number');
        echo '</div>';
    }

    public function display_calendar_metabox($post) {
        $calendar = new mindEventCalendar($post->ID, time(), true);

        echo '<div class="mindevents-admin-panel">';
        echo '<h3 class="mindevents-admin-heading">' . esc_html__('Default Occurrence Fields', 'simple-events') . '</h3>';
        $this->get_time_form();
        echo '<div class="mindevents-admin-calendar-nav">';
        echo '<button type="button" data-dir="prev" class="mindevents-button mindevents-button--secondary mindevents-admin-nav">' . esc_html__('Previous Month', 'simple-events') . '</button>';
        echo '<button type="button" data-dir="next" class="mindevents-button mindevents-button--secondary mindevents-admin-nav">' . esc_html__('Next Month', 'simple-events') . '</button>';
        echo '</div>';
        echo '<div id="eventsCalendar" class="mindevents-admin-calendar">';
        echo $calendar->get_calendar();
        echo '</div>';
        echo '<div id="errorBox" class="mindevents-admin-messages"></div>';
        echo '<button type="button" class="mindevents-button mindevents-button--danger clear-occurances">' . esc_html__('Clear All Occurrences', 'simple-events') . '</button>';
        echo '</div>';
    }

    public function save_meta_info($post_id, $post) {
        if (!($post instanceof WP_Post) || $post->post_type !== 'events') {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $nonce = $_POST[MINDEVENTS_PREPEND . 'event_meta_nonce'] ?? '';
        if (!$nonce || !wp_verify_nonce($nonce, 'mindevents_event_meta')) {
            return;
        }

        $event_meta = isset($_POST['event_meta']) ? wp_unslash($_POST['event_meta']) : array();
        $defaults   = isset($_POST['event']) ? wp_unslash($_POST['event']) : array();

        $event_meta = $this->sanitize_event_meta($event_meta);
        $defaults   = $this->sanitize_occurrence_defaults($defaults);

        foreach ($event_meta as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }

        update_post_meta($post_id, 'event_defaults', $defaults);
        update_post_meta($post_id, 'defaults', $defaults);

        mindevents_apply_visibility_meta($post_id, $event_meta['mindevents_visibility'] ?? 'public');
        mindevents_sync_event_visibility_to_children($post_id);
    }

    private function sanitize_event_meta($event_meta) {
        $event_meta = is_array($event_meta) ? $event_meta : array();

        return array(
            'event_type'                   => in_array(($event_meta['event_type'] ?? 'multiple-events'), array('multiple-events', 'single-event'), true) ? $event_meta['event_type'] : 'multiple-events',
            'cal_display'                  => in_array(($event_meta['cal_display'] ?? 'calendar'), array('calendar', 'list'), true) ? $event_meta['cal_display'] : 'calendar',
            'show_past_events'             => (($event_meta['show_past_events'] ?? '0') === '1') ? '1' : '0',
            'mindevents_visibility'        => mindevents_sanitize_visibility($event_meta['mindevents_visibility'] ?? 'public'),
            'mindevents_location'          => sanitize_text_field((string) ($event_meta['mindevents_location'] ?? '')),
            'mindevents_organizer_name'    => sanitize_text_field((string) ($event_meta['mindevents_organizer_name'] ?? '')),
            'mindevents_organizer_title'   => sanitize_text_field((string) ($event_meta['mindevents_organizer_title'] ?? '')),
            'mindevents_organizer_image_id'=> absint($event_meta['mindevents_organizer_image_id'] ?? 0),
        );
    }

    private function sanitize_occurrence_defaults($defaults) {
        $defaults = is_array($defaults) ? $defaults : array();

        $event_color = sanitize_hex_color($defaults['eventColor'] ?? '');

        return array(
            'starttime'                    => mindevents_normalize_time_value($defaults['starttime'] ?? '', $this->default_start_time),
            'endtime'                      => mindevents_normalize_time_value($defaults['endtime'] ?? '', $this->default_end_time),
            'eventColor'                   => $event_color ? $event_color : '',
            'eventDescription'             => wp_kses_post($defaults['eventDescription'] ?? ''),
            'mindevents_location'          => sanitize_text_field((string) ($defaults['mindevents_location'] ?? '')),
            'mindevents_visibility'        => mindevents_sanitize_visibility($defaults['mindevents_visibility'] ?? 'public'),
            'mindevents_organizer_name'    => sanitize_text_field((string) ($defaults['mindevents_organizer_name'] ?? '')),
            'mindevents_organizer_title'   => sanitize_text_field((string) ($defaults['mindevents_organizer_title'] ?? '')),
            'mindevents_organizer_image_id'=> absint($defaults['mindevents_organizer_image_id'] ?? 0),
        );
    }

    private function get_time_form() {
        $defaults = get_post_meta(get_the_ID(), 'event_defaults', true);
        if (!is_array($defaults) || empty($defaults)) {
            $defaults = get_post_meta(get_the_ID(), 'defaults', true);
        }
        $defaults = is_array($defaults) ? $defaults : array();

        echo '<fieldset id="defaultEventMeta" class="mindevents-admin-form-grid">';
        $this->render_text_control('event[starttime]', 'starttime', __('Occurrence Start', 'simple-events'), mindevents_normalize_time_value($defaults['starttime'] ?? '', $this->default_start_time), '', 'time');
        $this->render_text_control('event[endtime]', 'endtime', __('Occurrence End', 'simple-events'), mindevents_normalize_time_value($defaults['endtime'] ?? '', $this->default_end_time), '', 'time');
        $this->render_text_control('event[eventColor]', 'eventColor', __('Occurrence Color', 'simple-events'), $defaults['eventColor'] ?? '', __('Optional override for the category color.', 'simple-events'), 'color');
        $this->render_textarea_control('event[eventDescription]', 'eventDescription', __('Short Description', 'simple-events'), $defaults['eventDescription'] ?? '');
        $this->render_text_control('event[mindevents_location]', 'mindevents_location', __('Location', 'simple-events'), $defaults['mindevents_location'] ?? '');
        $this->render_select_control(
            'event[mindevents_visibility]',
            'mindevents_visibility',
            __('Visibility', 'simple-events'),
            array(
                'public'   => __('Public', 'simple-events'),
                'internal' => __('Internal', 'simple-events'),
            ),
            $defaults['mindevents_visibility'] ?? mindevents_get_post_visibility(get_the_ID())
        );
        $this->render_text_control('event[mindevents_organizer_name]', 'mindevents_organizer_name', __('Organizer Name', 'simple-events'), $defaults['mindevents_organizer_name'] ?? '');
        $this->render_text_control('event[mindevents_organizer_title]', 'mindevents_organizer_title', __('Organizer Title', 'simple-events'), $defaults['mindevents_organizer_title'] ?? '');
        $this->render_text_control('event[mindevents_organizer_image_id]', 'mindevents_organizer_image_id', __('Organizer Image ID', 'simple-events'), $defaults['mindevents_organizer_image_id'] ?? '', __('Media Library attachment ID for the organizer photo.', 'simple-events'), 'number');
        echo '</fieldset>';
    }

    private function render_text_control($name, $id, $label, $value = '', $description = '', $type = 'text') {
        echo '<div class="mindevents-admin-field">';
        echo '<label for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
        echo '<input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" id="' . esc_attr($id) . '" value="' . esc_attr((string) $value) . '">';
        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
        echo '</div>';
    }

    private function render_textarea_control($name, $id, $label, $value = '', $description = '') {
        echo '<div class="mindevents-admin-field mindevents-admin-field--full">';
        echo '<label for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
        echo '<textarea name="' . esc_attr($name) . '" id="' . esc_attr($id) . '" rows="4">' . esc_textarea((string) $value) . '</textarea>';
        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
        echo '</div>';
    }

    private function render_select_control($name, $id, $label, $options, $value) {
        echo '<div class="mindevents-admin-field">';
        echo '<label for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
        echo '<select name="' . esc_attr($name) . '" id="' . esc_attr($id) . '">';
        foreach ($options as $option_value => $option_label) {
            echo '<option value="' . esc_attr($option_value) . '" ' . selected($value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
        }
        echo '</select>';
        echo '</div>';
    }
}

