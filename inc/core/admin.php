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
        add_action('save_post_mind_events', array($this, 'save_meta_info'), 10, 2);
    }

    public function add_events_metaboxes() {
        add_meta_box(
            MINDEVENTS_PREPEND . 'calendar',
            __('Occurrences', 'simple-events'),
            array($this, 'display_calendar_metabox'),
            'mind_events',
            'normal',
            'default'
        );

        add_meta_box(
            MINDEVENTS_PREPEND . 'event_options',
            __('Event Settings', 'simple-events'),
            array($this, 'display_event_options_metabox'),
            'mind_events',
            'side',
            'default'
        );
    }

    public function display_event_options_metabox() {
        $post_id          = get_the_ID();
        $cal_display      = get_post_meta($post_id, 'cal_display', true);
        $show_past_events = get_post_meta($post_id, 'show_past_events', true);
        $location         = get_post_meta($post_id, 'mindevents_location', true);
        $organizer_name   = get_post_meta($post_id, 'mindevents_organizer_name', true);
        $organizer_title  = get_post_meta($post_id, 'mindevents_organizer_title', true);
        $organizer_image  = get_post_meta($post_id, 'mindevents_organizer_image_id', true);

        wp_nonce_field('mindevents_event_meta', MINDEVENTS_PREPEND . 'event_meta_nonce');

        echo '<div class="mindevents-admin-panel">';
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
        $this->render_text_control('event_meta[mindevents_location]', 'event_meta_mindevents_location', __('Location', 'simple-events'), $location, __('Optional venue or room name.', 'simple-events'));
        $this->render_text_control('event_meta[mindevents_organizer_name]', 'event_meta_mindevents_organizer_name', __('Organizer Name', 'simple-events'), $organizer_name);
        $this->render_text_control('event_meta[mindevents_organizer_title]', 'event_meta_mindevents_organizer_title', __('Organizer Title', 'simple-events'), $organizer_title);
        echo mindevents_image_field('event_meta[mindevents_organizer_image_id]', 'event_meta_mindevents_organizer_image_id', __('Organizer Image', 'simple-events'), $organizer_image); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped as it is built.
        echo '</div>';
    }

    public function display_calendar_metabox($post) {
        $calendar = new mindEventCalendar($post->ID, time(), true);

        echo '<div class="mindevents-admin-panel">';
        $this->get_time_form();
        echo '<p class="description">' . esc_html__('Click a day to add a date. Click a date to edit it, or drag it to another day.', 'simple-events') . '</p>';
        echo '<div class="mindevents-admin-calendar-nav">';
        echo '<button type="button" data-dir="prev" class="mindevents-button mindevents-button--secondary mindevents-admin-nav">' . esc_html__('Previous Month', 'simple-events') . '</button>';
        echo '<button type="button" data-dir="next" class="mindevents-button mindevents-button--secondary mindevents-admin-nav">' . esc_html__('Next Month', 'simple-events') . '</button>';
        echo '</div>';
        echo '<div id="eventsCalendar" class="mindevents-admin-calendar">';
        echo $calendar->get_calendar(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped as it is built.
        echo '</div>';
        echo '<div id="errorBox" class="mindevents-admin-messages"></div>';
        echo '<button type="button" class="mindevents-button mindevents-button--danger clear-occurances">' . esc_html__('Clear All Occurrences', 'simple-events') . '</button>';
        echo '</div>';
    }

    public function save_meta_info($post_id, $post) {
        if (!($post instanceof WP_Post) || $post->post_type !== 'mind_events') {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST[MINDEVENTS_PREPEND . 'event_meta_nonce'] ?? ''));
        if (!$nonce || !wp_verify_nonce($nonce, 'mindevents_event_meta')) {
            return;
        }

        $event_meta = isset($_POST['event_meta']) ? wp_unslash($_POST['event_meta']) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized key by key below.
        $defaults   = isset($_POST['event']) ? wp_unslash($_POST['event']) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized key by key below.

        $event_meta = $this->sanitize_event_meta($event_meta);
        $defaults   = $this->sanitize_occurrence_defaults($defaults);

        // Already unslashed above; update_post_meta() would unslash again.
        foreach ($event_meta as $key => $value) {
            update_post_meta($post_id, $key, wp_slash($value));
        }

        update_post_meta($post_id, 'event_defaults', wp_slash($defaults));

    }

    private function sanitize_event_meta($event_meta) {
        $event_meta  = is_array($event_meta) ? $event_meta : array();
        $cal_display = $event_meta['cal_display'] ?? 'calendar';

        return array(
            'cal_display'                  => in_array($cal_display, array('calendar', 'list'), true) ? $cal_display : 'calendar',
            'show_past_events'             => (($event_meta['show_past_events'] ?? '0') === '1') ? '1' : '0',
            'mindevents_location'          => sanitize_text_field((string) ($event_meta['mindevents_location'] ?? '')),
            'mindevents_organizer_name'    => sanitize_text_field((string) ($event_meta['mindevents_organizer_name'] ?? '')),
            'mindevents_organizer_title'   => sanitize_text_field((string) ($event_meta['mindevents_organizer_title'] ?? '')),
            'mindevents_organizer_image_id'=> absint($event_meta['mindevents_organizer_image_id'] ?? 0),
        );
    }

    /**
     * Defaults hold only the times new dates start with. Location,
     * organizer and the rest fall back to the event at display time, so
     * changing the event later reaches every date; copying them into each
     * new date froze them there.
     */
    private function sanitize_occurrence_defaults($defaults) {
        $defaults = is_array($defaults) ? $defaults : array();

        return array(
            'starttime' => mindevents_normalize_time_value($defaults['starttime'] ?? '', $this->default_start_time),
            'endtime'   => mindevents_normalize_time_value($defaults['endtime'] ?? '', $this->default_end_time),
        );
    }

    private function get_time_form() {
        $defaults = get_post_meta(get_the_ID(), 'event_defaults', true);
        $defaults = is_array($defaults) ? $defaults : array();

        echo '<fieldset id="defaultEventMeta" class="mindevents-admin-new-dates">';
        echo '<legend>' . esc_html__('New dates', 'simple-events') . '</legend>';
        $this->render_text_control('event[starttime]', 'starttime', __('Start', 'simple-events'), mindevents_normalize_time_value($defaults['starttime'] ?? '', $this->default_start_time), '', 'time');
        $this->render_text_control('event[endtime]', 'endtime', __('End', 'simple-events'), mindevents_normalize_time_value($defaults['endtime'] ?? '', $this->default_end_time), '', 'time');
        echo '</fieldset>';
    }

    private function render_text_control($name, $id, $label, $value = '', $description = '', $type = 'text') {
        echo '<div class="mindevents-admin-field">';
        echo '<label for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
        if ($type === 'color') {
            // A text field for wp-color-picker: a native color input cannot be left empty.
            echo '<input type="text" class="mindevents-color-field" name="' . esc_attr($name) . '" id="' . esc_attr($id) . '" value="' . esc_attr((string) $value) . '">';
        } else {
            echo '<input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" id="' . esc_attr($id) . '" value="' . esc_attr((string) $value) . '">';
        }
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

/**
 * An image chosen from the media library, stored as an attachment ID in a
 * hidden field. js/admin.js opens the library and fills it in.
 */
function mindevents_image_field($name, $id, $label, $attachment_id) {
    $attachment_id = absint($attachment_id);
    $preview       = $attachment_id ? wp_get_attachment_image($attachment_id, 'thumbnail', false, array('alt' => '')) : '';

    $html  = '<div class="mindevents-admin-field mindevents-image-field">';
    $html .= '<label for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
    $html .= '<input type="hidden" class="mindevents-image-id" name="' . esc_attr($name) . '" value="' . esc_attr($attachment_id ? (string) $attachment_id : '') . '">';
    $html .= '<div class="mindevents-image-preview">' . $preview . '</div>';
    $html .= '<div class="mindevents-image-actions">';
    $html .= '<button type="button" class="button mindevents-image-choose" id="' . esc_attr($id) . '">' . esc_html__('Choose image', 'simple-events') . '</button>';
    $html .= '<button type="button" class="button-link mindevents-image-remove"' . ($attachment_id ? '' : ' hidden') . '>' . esc_html__('Remove', 'simple-events') . '</button>';
    $html .= '</div></div>';

    return $html;
}
