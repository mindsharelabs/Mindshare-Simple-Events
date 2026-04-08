<?php

if (!defined('ABSPATH')) {
    exit;
}

class mindEventsAjax {
    public function __construct() {
        add_action('wp_ajax_' . MINDEVENTS_PREPEND . 'selectday', array($this, 'selectday'));
        add_action('wp_ajax_' . MINDEVENTS_PREPEND . 'clearevents', array($this, 'clearevents'));
        add_action('wp_ajax_' . MINDEVENTS_PREPEND . 'deleteevent', array($this, 'deleteevent'));
        add_action('wp_ajax_' . MINDEVENTS_PREPEND . 'editevent', array($this, 'editevent'));
        add_action('wp_ajax_' . MINDEVENTS_PREPEND . 'moveevent', array($this, 'moveevent'));
        add_action('wp_ajax_' . MINDEVENTS_PREPEND . 'updatesubevent', array($this, 'updatesubevent'));
        add_action('wp_ajax_' . MINDEVENTS_PREPEND . 'movecalendar', array($this, 'movecalendar'));

        add_action('wp_ajax_nopriv_' . MINDEVENTS_PREPEND . 'get_event_meta_html', array($this, 'get_event_meta_html'));
        add_action('wp_ajax_' . MINDEVENTS_PREPEND . 'get_event_meta_html', array($this, 'get_event_meta_html'));
    }

    public function get_event_meta_html() {
        $this->verify_nonce();

        $id    = absint($_POST['eventid'] ?? 0);
        $event = new mindEventCalendar();

        wp_send_json_success(array(
            'html' => $event->get_cal_meta_html($id),
        ));
    }

    public function deleteevent() {
        $this->verify_nonce();

        $event_id = absint($_POST['eventid'] ?? 0);
        if (!$event_id || !current_user_can('delete_post', $event_id)) {
            wp_send_json_error(__('You cannot delete this occurrence.', 'simple-events'));
        }

        $parent_id = (int) wp_get_post_parent_id($event_id);
        wp_delete_post($event_id, true);
        if ($parent_id) {
            mindevents_sync_event_date_range($parent_id);
        }

        wp_send_json_success(array(
            'parent_id' => $parent_id,
        ));
    }

    public function selectday() {
        $this->verify_nonce();

        $date    = sanitize_text_field(wp_unslash($_POST['date'] ?? ''));
        $event_id = absint($_POST['eventid'] ?? 0);
        $meta    = isset($_POST['meta']['event']) ? wp_unslash($_POST['meta']['event']) : array();

        if (!$event_id || !current_user_can('edit_post', $event_id)) {
            wp_send_json_error(__('You cannot edit this event.', 'simple-events'));
        }

        if ($date === '') {
            wp_send_json_error(__('A valid date is required.', 'simple-events'));
        }

        try {
            $calendar      = new mindEventCalendar($event_id);
            $added_event_id = $calendar->add_sub_event($date, $meta, $event_id);
        } catch (Throwable $exception) {
            wp_send_json(array(
                'html'   => '',
                'events' => array(),
                'errors' => array($exception->getMessage()),
            ));
        }

        if ($added_event_id === false) {
            wp_send_json(array(
                'html'   => '',
                'events' => array(),
                'errors' => array(__('An occurrence at that time already exists.', 'simple-events')),
            ));
        }

        if (is_wp_error($added_event_id)) {
            wp_send_json(array(
                'html'   => '',
                'events' => array(),
                'errors' => array($added_event_id->get_error_message()),
            ));
        }

        $calendar = new mindEventCalendar($event_id, $date);

        wp_send_json(array(
            'html'   => $calendar->get_calendar(),
            'events' => array($added_event_id),
            'errors' => array(),
        ));
    }

    public function clearevents() {
        $this->verify_nonce();

        $event_id = absint($_POST['eventid'] ?? 0);
        if (!$event_id || !current_user_can('edit_post', $event_id)) {
            wp_send_json_error(__('You cannot edit this event.', 'simple-events'));
        }

        $calendar = new mindEventCalendar($event_id);
        $success  = $calendar->delete_sub_events($event_id);

        wp_send_json(array(
            'html'    => $calendar->get_calendar(),
            'success' => $success,
        ));
    }

    public function updatesubevent() {
        $this->verify_nonce();

        $id       = absint($_POST['eventid'] ?? 0);
        $parent_id = absint($_POST['parentid'] ?? 0);
        $meta     = isset($_POST['meta']) ? wp_unslash($_POST['meta']) : array();

        if (!$id || !$parent_id || !current_user_can('edit_post', $parent_id)) {
            wp_send_json_error(__('You cannot edit this occurrence.', 'simple-events'));
        }

        try {
            $calendar = new mindEventCalendar($parent_id, $meta['event_date'] ?? '');
            $calendar->update_sub_event($id, $meta, $parent_id);
        } catch (Throwable $exception) {
            wp_send_json_error($exception->getMessage());
        }

        wp_send_json_success(array(
            'html' => $calendar->get_calendar(),
        ));
    }

    public function moveevent() {
        $this->verify_nonce();

        $event_id  = absint($_POST['eventid'] ?? 0);
        $parent_id = absint($_POST['parentid'] ?? 0);
        $new_date  = sanitize_text_field(wp_unslash($_POST['new_date'] ?? ''));
        $start_raw = sanitize_text_field(wp_unslash($_POST['start_date'] ?? ''));
        $end_raw   = sanitize_text_field(wp_unslash($_POST['end_date'] ?? ''));

        if (!$event_id || !$parent_id || !current_user_can('edit_post', $parent_id)) {
            wp_send_json_error(__('You cannot move this occurrence.', 'simple-events'));
        }

        try {
            $timezone  = mindevents_wp_timezone();
            $start_dt  = new DateTimeImmutable($start_raw, $timezone);
            $end_dt    = new DateTimeImmutable($end_raw, $timezone);
            $new_start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $new_date . ' ' . $start_dt->format('H:i:s'), $timezone);
            $new_end   = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $new_date . ' ' . $end_dt->format('H:i:s'), $timezone);
        } catch (Throwable $exception) {
            wp_send_json_error(__('The new occurrence date is invalid.', 'simple-events'));
        }

        if (!($new_start instanceof DateTimeImmutable) || !($new_end instanceof DateTimeImmutable)) {
            wp_send_json_error(__('The new occurrence date is invalid.', 'simple-events'));
        }

        update_post_meta($event_id, 'event_date', $new_date);
        update_post_meta($event_id, 'starttime', $new_start->format('H:i'));
        update_post_meta($event_id, 'endtime', $new_end->format('H:i'));
        update_post_meta($event_id, 'event_start_time_stamp', $new_start->format('Y-m-d H:i:s'));
        update_post_meta($event_id, 'event_end_time_stamp', $new_end->format('Y-m-d H:i:s'));

        wp_update_post(array(
            'ID'         => $event_id,
            'post_title' => get_the_title($parent_id) . ' | ' . $new_date . ' | ' . $new_start->format('H:i') . '-' . $new_end->format('H:i'),
        ));

        mindevents_sync_event_date_range($parent_id);

        wp_send_json_success();
    }

    public function movecalendar() {
        $this->verify_nonce();

        $direction = sanitize_key(wp_unslash($_POST['direction'] ?? ''));
        $month     = absint($_POST['month'] ?? 0);
        $year      = absint($_POST['year'] ?? 0);
        $event_id  = absint($_POST['eventid'] ?? 0);

        if (!$event_id || !current_user_can('edit_post', $event_id)) {
            wp_send_json_error(__('You cannot edit this event.', 'simple-events'));
        }

        $date = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), mindevents_wp_timezone());

        if ($direction === 'prev') {
            $date = $date->modify('first day of previous month');
        } elseif ($direction === 'next') {
            $date = $date->modify('first day of next month');
        }

        $calendar = new mindEventCalendar($event_id, $date->format('Y-m-d'));

        wp_send_json(array(
            'new_date' => $date->format('Y-m-d'),
            'html'     => $calendar->get_calendar(),
        ));
    }

    public function editevent() {
        $this->verify_nonce();

        $event_id  = absint($_POST['eventid'] ?? 0);
        $parent_id = absint($_POST['parentid'] ?? 0);

        if (!$event_id || !$parent_id || !current_user_can('edit_post', $parent_id)) {
            wp_send_json_error(__('You cannot edit this occurrence.', 'simple-events'));
        }

        wp_send_json_success(array(
            'html' => $this->get_meta_form($event_id, $parent_id),
        ));
    }

    private function get_meta_form($sub_event_id, $parentID) {
        $values     = get_post_meta($sub_event_id);
        $timezone   = mindevents_wp_timezone();
        $start_ts   = $values['event_start_time_stamp'][0] ?? '';
        $end_ts     = $values['event_end_time_stamp'][0] ?? '';
        $start_date = new DateTimeImmutable($start_ts, $timezone);
        $end_date   = new DateTimeImmutable($end_ts, $timezone);

        $html  = '<fieldset id="subEventEdit" class="mindevents-admin-form-grid">';
        $html .= '<h3 class="mindevents-admin-heading">' . esc_html__('Edit Occurrence', 'simple-events') . '</h3>';
        $html .= $this->render_modal_field('event_date', __('Occurrence Date', 'simple-events'), $start_date->format('Y-m-d'), 'date');
        $html .= $this->render_modal_field('starttime', __('Occurrence Start', 'simple-events'), $start_date->format('H:i'), 'time');
        $html .= $this->render_modal_field('endtime', __('Occurrence End', 'simple-events'), $end_date->format('H:i'), 'time');
        $html .= $this->render_modal_field('eventColor', __('Occurrence Color', 'simple-events'), $values['eventColor'][0] ?? '', 'color');
        $html .= $this->render_modal_textarea('eventDescription', __('Short Description', 'simple-events'), $values['eventDescription'][0] ?? '');
        $html .= $this->render_modal_field('mindevents_location', __('Location', 'simple-events'), $values['mindevents_location'][0] ?? '');
        $html .= $this->render_modal_select('mindevents_visibility', __('Visibility', 'simple-events'), array(
            'public'   => __('Public', 'simple-events'),
            'internal' => __('Internal', 'simple-events'),
        ), $values['mindevents_visibility'][0] ?? mindevents_get_post_visibility($sub_event_id));
        $html .= $this->render_modal_field('mindevents_organizer_name', __('Organizer Name', 'simple-events'), $values['mindevents_organizer_name'][0] ?? '');
        $html .= $this->render_modal_field('mindevents_organizer_title', __('Organizer Title', 'simple-events'), $values['mindevents_organizer_title'][0] ?? '');
        $html .= $this->render_modal_field('mindevents_organizer_image_id', __('Organizer Image ID', 'simple-events'), $values['mindevents_organizer_image_id'][0] ?? '', 'number');
        $html .= '<input type="hidden" name="parentID" value="' . esc_attr($parentID) . '">';
        $html .= '<div class="mindevents-admin-modal-actions">';
        $html .= '<button type="button" class="mindevents-button edit-button update-event" data-subid="' . esc_attr($sub_event_id) . '">' . esc_html__('Update Occurrence', 'simple-events') . '</button>';
        $html .= '<button type="button" class="mindevents-button mindevents-button--secondary edit-button cancel">' . esc_html__('Cancel', 'simple-events') . '</button>';
        $html .= '</div>';
        $html .= '</fieldset>';

        return $html;
    }

    private function render_modal_field($name, $label, $value, $type = 'text', $disabled = false) {
        $html  = '<div class="mindevents-admin-field">';
        $html .= '<label for="' . esc_attr($name) . '">' . esc_html($label) . '</label>';
        $html .= '<input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '"' . ($disabled ? ' disabled' : '') . '>';
        $html .= '</div>';

        return $html;
    }

    private function render_modal_textarea($name, $label, $value) {
        $html  = '<div class="mindevents-admin-field mindevents-admin-field--full">';
        $html .= '<label for="' . esc_attr($name) . '">' . esc_html($label) . '</label>';
        $html .= '<textarea name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" rows="4">' . esc_textarea((string) $value) . '</textarea>';
        $html .= '</div>';

        return $html;
    }

    private function render_modal_select($name, $label, $options, $value) {
        $html  = '<div class="mindevents-admin-field">';
        $html .= '<label for="' . esc_attr($name) . '">' . esc_html($label) . '</label>';
        $html .= '<select name="' . esc_attr($name) . '" id="' . esc_attr($name) . '">';
        foreach ($options as $option_value => $option_label) {
            $html .= '<option value="' . esc_attr($option_value) . '" ' . selected($value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
        }
        $html .= '</select></div>';

        return $html;
    }

    private function verify_nonce() {
        check_ajax_referer('mindevents_ajax', 'nonce');
    }

    private function format_admin_time_range($start, $end) {
        $timezone = mindevents_wp_timezone();
        $start_dt = new DateTimeImmutable($start, $timezone);
        $end_dt   = new DateTimeImmutable($end, $timezone);
        $format   = get_option('time_format') ?: 'g:i a';

        return $start_dt->format($format) . ' - ' . $end_dt->format($format);
    }
}
