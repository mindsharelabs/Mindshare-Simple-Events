<?php



class mindEventsWooCommerce {


    public $event = '';
    public $event_type = '';
    private $reschedule_cutoff_days = 14;



    public function __construct($event = false) {
        add_action('woocommerce_init', array($this, 'add_event_options'));
        add_action('save_post_events', array($this, 'save_event_options'), 500, 3);
        
        // Hook into meta field updates for automatic profit recalculation
        add_action('updated_post_meta', array($this, 'handle_expense_meta_update'), 10, 4);
        add_action('added_post_meta', array($this, 'handle_expense_meta_update'), 10, 4);


        add_action('save_post_events', array($this, 'create_woocommerce_event_product'), 999, 3);


        //prerequisite notice
        add_action( 'woocommerce_before_cart', array($this, 'display_prerequisite_cart_notice') );

        //attendee management
        add_action('woocommerce_order_status_changed', array($this, 'order_status_change'), 10, 3);
        add_action('woocommerce_checkout_order_processed', array($this, 'capture_order_event_meta'), 20, 3);
        add_action('woocommerce_new_order', array($this, 'capture_order_event_meta'), 20, 2);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'attach_line_item_event_meta'), 20, 4);

        // Initialize order stats for existing sub events (run once, but more efficiently)
        add_action('wp_loaded', array($this, 'maybe_initialize_order_stats'));
        add_action('template_redirect', array($this, 'handle_reschedule_submission'));
        add_action('woocommerce_account_dashboard', array($this, 'render_dashboard_reschedule_notices'), 40);

        $this->event = ($event ? $event : false);
        $this->event_type = get_post_meta($this->event, 'event_type', true);
    }




    public function display_prerequisite_cart_notice() {
        // Check if on a specific page, e.g., the cart page, before displaying.
        if ( is_cart() && ! defined( 'DOING_AJAX' ) ) {


            //loop through items in the cart
            $cart = WC()->cart;
            $not_completed = array();
            // Gather all product IDs in the cart for quick lookup
            $cart_product_ids = array();
            foreach ( $cart->get_cart() as $cart_item ) {
                $cart_product_ids[] = $cart_item['product_id'];
            }

            foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
                $product_id = $cart_item['product_id'];
                //find the attached event for each item
                $linked_event_id = get_post_meta($product_id, 'linked_event', true);
                //check if it has a prerequisite event
                $prerequisite = get_post_meta($linked_event_id, 'prerequisite', true);
                if($prerequisite) :
                    // Check if the prerequisite event is already in the cart
                    // Find the product(s) linked to the prerequisite event
                    $prereq_product_ids = array();
                    // For single-event, the parent event's linked_product
                    $prereq_product_id = get_post_meta($prerequisite, 'linked_product', true);
                    if ($prereq_product_id) {
                        $prereq_product_ids[] = $prereq_product_id;
                    }
                    // For multiple-events, check sub-events
                    $sub_events = get_posts(array(
                        'post_type' => 'sub_event',
                        'post_parent' => $prerequisite,
                        'fields' => 'ids',
                        'posts_per_page' => -1,
                    ));
                    foreach ($sub_events as $sub_event_id) {
                        $sub_product_id = get_post_meta($sub_event_id, 'linked_product', true);
                        if ($sub_product_id) {
                            $prereq_product_ids[] = $sub_product_id;
                        }
                    }
                    // If any prerequisite product is in the cart, skip the notice for this prerequisite
                    $prereq_in_cart = false;
                    foreach ($prereq_product_ids as $pid) {
                        if (in_array($pid, $cart_product_ids)) {
                            $prereq_in_cart = true;
                            break;
                        }
                    }
                    if ($prereq_in_cart) {
                        continue;
                    }

                    //check if user has completed the prerequisite event by gathering attendees data
                    $attendees = get_post_meta($prerequisite, 'attendees', true);
                    $completed = false;
                    if($attendees) :
                        foreach($attendees as $occurrence_id => $attendee_list) :
                            foreach($attendee_list as $attendee) :
                                if($attendee['user_id'] == get_current_user_id()) :
                                    $order = wc_get_order($attendee['order_id']);
                                    $checked_in = $attendee['checked_in'];
                                    if($checked_in && $order && in_array($order->get_status(), array('completed', 'processing'))) :
                                        $completed = true;
                                    endif;
                                endif;
                            endforeach;
                        endforeach;
                    endif;

                    if(!$completed) :
                        $not_completed[$prerequisite] = array(
                            'title' => get_the_title($prerequisite),
                            'link' => get_permalink($prerequisite),
                            'event_id' => $linked_event_id,
                        );
                    endif;
                endif;
            }

            //display a notice for all prerequisite events not yet completed
            if(count($not_completed) > 0) :
                $message = '<strong>Some items in your cart require prerequisites. Please complete the following before proceeding:</strong>';
                $message .= '<ul>';
                foreach($not_completed as $prerequisite_event) :
                    $message .= '<li>Required by ' .get_the_title($prerequisite_event['event_id']) . ': <a href="' . esc_url($prerequisite_event['link']) . '" target="_blank">' . esc_html($prerequisite_event['title']) . '</a></li>';
                endforeach;
                $message .= '</ul>';
                wc_print_notice( $message, 'notice' ); // 'notice', 'success', or 'error'
            endif;

            
        }
    }

    /**
     * Initialize order stats for existing sub events if not already done
     * This ensures the fields show up in Admin Columns
     */
    public function maybe_initialize_order_stats() {
        // Only run in admin and for users who can manage options
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }
        
        // Check if we've already initialized or if manually disabled
        $initialized = get_option('mindevents_order_stats_initialized', false);
        $disabled = get_option('mindevents_disable_auto_init', false);
        
        if (!$initialized && !$disabled) {
            // Set a flag to prevent multiple simultaneous runs
            $running = get_transient('mindevents_initializing_stats');
            if ($running) {
                return;
            }
            
            // Set a 5-minute lock
            set_transient('mindevents_initializing_stats', true, 300);
            
            try {
                // Process in smaller batches to prevent timeout
                $this->recalculate_all_sub_event_stats_batched();
                update_option('mindevents_order_stats_initialized', true);
            } catch (Exception $e) {
                // Log error but don't crash the site
                error_log('MinEvents: Error initializing order stats: ' . $e->getMessage());
            }
            
            // Clear the lock
            delete_transient('mindevents_initializing_stats');
        }
    }

    public function add_event_options() {
        
    }

    public function render_dashboard_reschedule_notices() {
        if (!is_user_logged_in()) :
            return;
        endif;

        $items = $this->get_customer_reschedule_items(get_current_user_id());
        if (!$items) :
            return;
        endif;

        $this->render_reschedule_interface($items, false);
    }

    private function render_reschedule_interface($items, $show_empty_message = true) {
        $grouped_items = $this->group_reschedule_items_by_order($items);

        echo '<section class="mindevents-account-reschedule-page my-4">';
            echo '<div class="mb-4">';
                echo '<h3 class="h4 mb-1">Upcoming Classes</h3>';
                echo '<p class="text-muted mb-0 small">Classes can only be rescheduled more than ' . esc_html($this->reschedule_cutoff_days) . ' days before the class date.</p>';
            echo '</div>';

            if (!$grouped_items) :
                if ($show_empty_message) :
                    echo '<div class="alert alert-light border mb-0">You do not have any upcoming classes available for rescheduling.</div>';
                endif;
                echo '</section>';
                return;
            endif;

            foreach ($grouped_items as $group) :
                $order = $group['order'];

                echo '<div class="card border-0 shadow-sm mb-4">';
                    echo '<div class="card-body p-0">';
                        echo '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 px-lg-4 py-3 border-bottom">';
                            echo '<div>';
                                echo '<h4 class="h6 mb-1">Order #' . esc_html($order->get_order_number()) . '</h4>';
                                echo '<p class="text-muted small mb-0">Choose a new date for any eligible class below.</p>';
                            echo '</div>';
                            echo '<a class="btn btn-outline-secondary btn-sm" href="' . esc_url($order->get_view_order_url()) . '">View Order</a>';
                        echo '</div>';
                        echo '<div class="table-responsive">';
                            echo '<table class="table align-middle mb-0">';
                                echo '<thead>';
                                    echo '<tr>';
                                        echo '<th class="px-3 px-lg-4">Class</th>';
                                        echo '<th>Date</th>';
                                        echo '<th>Qty</th>';
                                        echo '<th class="px-3 px-lg-4">Reschedule</th>';
                                    echo '</tr>';
                                echo '</thead>';
                                echo '<tbody>';
                                    foreach ($group['items'] as $entry) :
                                        $field_id = 'mindevents-new-product-' . absint($entry['item_id']);

                                        echo '<tr>';
                                            echo '<td class="px-3 px-lg-4">';
                                                if (!empty($entry['event_url'])) :
                                                    echo '<a class="fw-semibold text-decoration-none" href="' . esc_url($entry['event_url']) . '">' . esc_html($entry['title']) . '</a>';
                                                else :
                                                    echo '<span class="fw-semibold">' . esc_html($entry['title']) . '</span>';
                                                endif;
                                            echo '</td>';
                                            echo '<td>' . esc_html($this->format_date_range($entry['start_date'], $entry['end_date'])) . '</td>';
                                            echo '<td>' . esc_html($entry['quantity']) . '</td>';
                                            echo '<td class="px-3 px-lg-4">';
                                                if ($entry['can_reschedule']) :
                                                    echo '<form method="post" class="mb-0">';
                                                        wp_nonce_field('mindevents_reschedule_action', 'mindevents_reschedule_nonce');
                                                        echo '<input type="hidden" name="mindevents_reschedule_submit" value="1">';
                                                        echo '<input type="hidden" name="order_id" value="' . esc_attr($entry['order_id']) . '">';
                                                        echo '<input type="hidden" name="item_id" value="' . esc_attr($entry['item_id']) . '">';
                                                        echo '<div class="small text-muted mb-2">Available for reschedule until <strong>' . esc_html($this->format_display_datetime($entry['deadline_datetime'])) . '</strong></div>';
                                                        echo '<div class="row g-2 align-items-end">';
                                                            echo '<div class="col-12 col-xl">';
                                                                echo '<div class="form-group mb-0">';
                                                                    echo '<select class="form-select" id="' . esc_attr($field_id) . '" name="new_product_id">';
                                                                        foreach ($entry['replacements'] as $replacement) :
                                                                            echo '<option value="' . esc_attr($replacement['product_id']) . '">' . esc_html($replacement['label']) . '</option>';
                                                                        endforeach;
                                                                    echo '</select>';
                                                                echo '</div>';
                                                            echo '</div>';
                                                            echo '<div class="col-12 col-xl-auto">';
                                                                echo '<div class="form-group mb-0">';
                                                                    echo '<button type="submit" class="btn btn-primary w-100">Reschedule</button>';
                                                                echo '</div>';
                                                            echo '</div>';
                                                        echo '</div>';
                                                    echo '</form>';
                                                else :
                                                    $notice_class = ('closed' === $entry['status_type']) ? 'alert-warning' : 'alert-secondary';
                                                    echo '<div class="alert ' . esc_attr($notice_class) . ' mb-0 py-2 px-3 small">' . esc_html($entry['status_message']) . '</div>';
                                                endif;
                                            echo '</td>';
                                        echo '</tr>';
                                    endforeach;
                                echo '</tbody>';
                            echo '</table>';
                        echo '</div>';
                    echo '</div>';
                echo '</div>';
            endforeach;
        echo '</section>';
    }

    public function handle_reschedule_submission() {
        if (!isset($_POST['mindevents_reschedule_submit'])) :
            return;
        endif;

        if (!is_user_logged_in()) :
            return;
        endif;

        $redirect_url = wc_get_page_permalink('myaccount');
        $nonce = isset($_POST['mindevents_reschedule_nonce']) ? sanitize_text_field(wp_unslash($_POST['mindevents_reschedule_nonce'])) : '';

        if (!$nonce || !wp_verify_nonce($nonce, 'mindevents_reschedule_action')) :
            wc_add_notice('We could not verify your reschedule request. Please try again.', 'error');
            wp_safe_redirect($redirect_url);
            exit;
        endif;

        $order_id = isset($_POST['order_id']) ? absint(wp_unslash($_POST['order_id'])) : 0;
        $item_id = isset($_POST['item_id']) ? absint(wp_unslash($_POST['item_id'])) : 0;
        $new_product_id = isset($_POST['new_product_id']) ? absint(wp_unslash($_POST['new_product_id'])) : 0;

        if (!$order_id || !$item_id || !$new_product_id) :
            wc_add_notice('We could not find the class you wanted to reschedule.', 'error');
            wp_safe_redirect($redirect_url);
            exit;
        endif;

        $order = wc_get_order($order_id);
        if (!$order || (int) $order->get_customer_id() !== (int) get_current_user_id()) :
            wc_add_notice('That order is not available for rescheduling.', 'error');
            wp_safe_redirect($redirect_url);
            exit;
        endif;

        $item = $order->get_item($item_id);
        if (!$item || !($item instanceof WC_Order_Item_Product)) :
            wc_add_notice('That class could not be found in your order.', 'error');
            wp_safe_redirect($redirect_url);
            exit;
        endif;

        $entry = $this->build_customer_reschedule_item($order, $item);
        if (!$entry || !$entry['can_reschedule']) :
            $message = $entry && !empty($entry['status_message']) ? $entry['status_message'] : 'This class can no longer be rescheduled.';
            wc_add_notice($message, 'error');
            wp_safe_redirect($redirect_url);
            exit;
        endif;

        $valid_replacement_ids = wp_list_pluck($entry['replacements'], 'product_id');
        if (!in_array($new_product_id, $valid_replacement_ids, true)) :
            wc_add_notice('The class date you selected is not available.', 'error');
            wp_safe_redirect($redirect_url);
            exit;
        endif;

        $result = $this->reschedule_order_item($order, $item, $new_product_id, $entry);
        if (is_wp_error($result)) :
            wc_add_notice($result->get_error_message(), 'error');
            wp_safe_redirect($redirect_url);
            exit;
        endif;

        wc_add_notice('Your class has been rescheduled to ' . $result['new_label'] . '.', 'success');
        wp_safe_redirect($redirect_url);
        exit;
    }

    private function get_customer_reschedule_items($user_id) {
        if (!$user_id || !function_exists('wc_get_orders')) :
            return array();
        endif;

        $items = array();
        $orders = wc_get_orders(array(
            'customer_id' => $user_id,
            'status' => array('processing', 'completed'),
            'type' => 'shop_order',
            'orderby' => 'date',
            'order' => 'DESC',
            'limit' => -1,
        ));

        foreach ($orders as $order) :
            foreach ($order->get_items('line_item') as $item) :
                $entry = $this->build_customer_reschedule_item($order, $item);
                if ($entry) :
                    $items[] = $entry;
                endif;
            endforeach;
        endforeach;

        if ($items) :
            usort($items, function($a, $b) {
                return $a['start_datetime']->getTimestamp() <=> $b['start_datetime']->getTimestamp();
            });
        endif;

        return $items;
    }

    private function build_customer_reschedule_item($order, $item) {
        if (!$order || !($item instanceof WC_Order_Item_Product)) :
            return false;
        endif;

        if (!in_array($order->get_status(), array('processing', 'completed'), true)) :
            return false;
        endif;

        $product_id = $item->get_product_id();
        $parent_event_id = absint(get_post_meta($product_id, 'linked_event', true));
        $occurrence_id = absint(get_post_meta($product_id, 'linked_occurance', true));

        if (!$product_id || !$parent_event_id || !$occurrence_id) :
            return false;
        endif;

        if ('sub_event' !== get_post_type($occurrence_id)) :
            return false;
        endif;

        if (get_post_meta($parent_event_id, 'event_type', true) !== 'multiple-events') :
            return false;
        endif;

        if ((int) get_post_meta($occurrence_id, 'linked_product', true) !== (int) $product_id) :
            return false;
        endif;

        $start_date = get_post_meta($product_id, 'linkedEventStartDate', true);
        if (!$start_date) :
            $start_date = get_post_meta($occurrence_id, 'event_start_time_stamp', true);
        endif;

        if (!$start_date) :
            return false;
        endif;

        $end_date = get_post_meta($product_id, 'linkedEventEndDate', true);
        if (!$end_date) :
            $end_date = get_post_meta($occurrence_id, 'event_end_time_stamp', true);
        endif;

        $start_datetime = $this->get_event_datetime($start_date);
        $now = $this->get_current_datetime();
        if (!$start_datetime || $start_datetime->getTimestamp() <= $now->getTimestamp()) :
            return false;
        endif;

        $deadline_datetime = $this->get_reschedule_deadline($start_datetime);
        $replacements = array();
        $status_message = '';
        $status_type = 'closed';
        $can_reschedule = false;
        $quantity = max(1, absint($item->get_quantity()));

        if ($deadline_datetime && $now->getTimestamp() >= $deadline_datetime->getTimestamp()) :
            $status_message = 'Rescheduling closed on ' . $this->format_display_datetime($deadline_datetime);
        else :
            $replacements = $this->get_available_reschedule_options($parent_event_id, $occurrence_id, $product_id, $quantity);
            if ($replacements) :
                $can_reschedule = true;
                $status_type = 'eligible';
                $status_message = 'Available for reschedule until ' . $this->format_display_datetime($deadline_datetime);
            else :
                $status_type = 'no-options';
                $status_message = 'No alternate class dates currently available';
            endif;
        endif;

        return array(
            'order_id' => $order->get_id(),
            'order' => $order,
            'order_number' => $order->get_order_number(),
            'item_id' => $item->get_id(),
            'item' => $item,
            'title' => (get_the_title($parent_event_id) ? get_the_title($parent_event_id) : $item->get_name()),
            'event_url' => get_permalink($parent_event_id),
            'quantity' => $quantity,
            'product_id' => $product_id,
            'parent_event_id' => $parent_event_id,
            'occurrence_id' => $occurrence_id,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'start_datetime' => $start_datetime,
            'deadline_datetime' => $deadline_datetime,
            'status_type' => $status_type,
            'status_message' => $status_message,
            'can_reschedule' => $can_reschedule,
            'replacements' => $replacements,
        );
    }

    private function get_available_reschedule_options($parent_event_id, $current_occurrence_id, $current_product_id, $quantity) {
        $options = array();
        $seen_products = array();
        $sub_events = $this->get_sub_events($parent_event_id);
        $now = $this->get_current_datetime();

        foreach ($sub_events as $sub_event) :
            if ((int) $sub_event->ID === (int) $current_occurrence_id) :
                continue;
            endif;

            $replacement_product_id = absint(get_post_meta($sub_event->ID, 'linked_product', true));
            if (!$replacement_product_id || $replacement_product_id === (int) $current_product_id) :
                continue;
            endif;

            if (isset($seen_products[$replacement_product_id])) :
                continue;
            endif;

            $start_date = get_post_meta($replacement_product_id, 'linkedEventStartDate', true);
            if (!$start_date) :
                $start_date = get_post_meta($sub_event->ID, 'event_start_time_stamp', true);
            endif;

            if (!$start_date) :
                continue;
            endif;

            $start_datetime = $this->get_event_datetime($start_date);
            if (!$start_datetime || $start_datetime->getTimestamp() <= $now->getTimestamp()) :
                continue;
            endif;

            $deadline_datetime = $this->get_reschedule_deadline($start_datetime);
            if ($deadline_datetime && $now->getTimestamp() >= $deadline_datetime->getTimestamp()) :
                continue;
            endif;

            $product = wc_get_product($replacement_product_id);
            if (!$this->product_has_available_quantity($product, $quantity)) :
                continue;
            endif;

            $end_date = get_post_meta($replacement_product_id, 'linkedEventEndDate', true);
            if (!$end_date) :
                $end_date = get_post_meta($sub_event->ID, 'event_end_time_stamp', true);
            endif;

            $seen_products[$replacement_product_id] = true;
            $options[] = array(
                'product_id' => $replacement_product_id,
                'occurrence_id' => $sub_event->ID,
                'label' => $this->format_date_range($start_date, $end_date),
                'start_timestamp' => $start_datetime->getTimestamp(),
            );
        endforeach;

        if ($options) :
            usort($options, function($a, $b) {
                return $a['start_timestamp'] <=> $b['start_timestamp'];
            });
        endif;

        return $options;
    }

    private function group_reschedule_items_by_order($items) {
        $grouped = array();

        foreach ($items as $entry) :
            $order_id = $entry['order_id'];
            if (!isset($grouped[$order_id])) :
                $grouped[$order_id] = array(
                    'order' => $entry['order'],
                    'items' => array(),
                );
            endif;

            $grouped[$order_id]['items'][] = $entry;
        endforeach;

        foreach ($grouped as $order_id => $group) :
            usort($grouped[$order_id]['items'], function($a, $b) {
                return $a['start_datetime']->getTimestamp() <=> $b['start_datetime']->getTimestamp();
            });
        endforeach;

        return $grouped;
    }

    private function get_current_datetime() {
        if (function_exists('current_datetime')) :
            return current_datetime();
        endif;

        return new DateTime(current_time('mysql'), wp_timezone());
    }

    private function get_event_datetime($date_string) {
        if (!$date_string) :
            return false;
        endif;

        try {
            return new DateTime($date_string, wp_timezone());
        } catch (Exception $e) {
            return false;
        }
    }

    private function get_reschedule_deadline($start_datetime) {
        if (!($start_datetime instanceof DateTimeInterface)) :
            return false;
        endif;

        $deadline = new DateTime($start_datetime->format('Y-m-d H:i:s'), wp_timezone());
        $deadline->modify('-' . absint($this->reschedule_cutoff_days) . ' days');

        return $deadline;
    }

    private function format_display_datetime($value) {
        $datetime = $value;

        if (!($datetime instanceof DateTimeInterface)) :
            $datetime = $this->get_event_datetime($value);
        endif;

        if (!$datetime) :
            return '';
        endif;

        return wp_date(get_option('date_format') . ' ' . get_option('time_format'), $datetime->getTimestamp(), wp_timezone());
    }

    private function format_date_range($start_date, $end_date = '') {
        $start_datetime = $this->get_event_datetime($start_date);
        if (!$start_datetime) :
            return '';
        endif;

        $end_datetime = $this->get_event_datetime($end_date);
        $date_format = get_option('date_format');
        $time_format = get_option('time_format');

        if (!$end_datetime) :
            return wp_date($date_format . ' ' . $time_format, $start_datetime->getTimestamp(), wp_timezone());
        endif;

        if ($start_datetime->format('Ymd') === $end_datetime->format('Ymd')) :
            return wp_date($date_format . ' ' . $time_format, $start_datetime->getTimestamp(), wp_timezone()) . ' - ' . wp_date($time_format, $end_datetime->getTimestamp(), wp_timezone());
        endif;

        return wp_date($date_format . ' ' . $time_format, $start_datetime->getTimestamp(), wp_timezone()) . ' - ' . wp_date($date_format . ' ' . $time_format, $end_datetime->getTimestamp(), wp_timezone());
    }

    private function product_has_available_quantity($product, $quantity) {
        if (!$product) :
            return false;
        endif;

        if (!$product->is_in_stock() && !$product->backorders_allowed()) :
            return false;
        endif;

        if (!$product->managing_stock()) :
            return true;
        endif;

        if ($product->backorders_allowed()) :
            return true;
        endif;

        return (int) $product->get_stock_quantity() >= (int) $quantity;
    }

    public function order_status_change($order, $from, $to) {
        if($from == $to) :
            return;
        endif;

        // Get affected sub events for order stats update
        $affected_sub_events = array();
        $order_obj = wc_get_order($order);
        if($order_obj && $order_obj->get_items()) :
            foreach($order_obj->get_items() as $line_item) :
                $product_id = $line_item->get_product_id();
                $sub_event_id = $this->get_sub_event_by_product_id($product_id);
                if($sub_event_id) :
                    $affected_sub_events[] = $sub_event_id;
                endif;
            endforeach;
        endif;

        if( $to == 'refunded' ||
            $to == 'cancelled' ||
            $to == 'failed' ||
            $to == 'on-hold' ||
            $to == 'pending'
            ) :
            $this->remove_attendee($order);
            $this->clear_schedule_hook($order);

        endif;

        if( $to == 'completed' || $to == 'processing') :
            if($from == 'completed' && $to == 'processing') :
                return;
            endif;
            if($from == 'processing' && $to == 'completed') :
                return;
            endif;
            
            $this->add_attendee($order);
            $this->schedule_hook($order);
            if ($order_obj) :
                $this->update_order_event_meta($order_obj);
            endif;
        endif;

        // Update order stats for affected sub events
        foreach($affected_sub_events as $sub_event_id) :
            $this->update_sub_event_order_stats($sub_event_id);
        endforeach;

    }
   
    

    private function schedule_hook($order_id) {
       
        $order = wc_get_order( $order_id );
        if($order->get_items()) :
            foreach($order->get_items() as $line_item) :
                $product_id = $line_item->get_product_id();
                $this->schedule_single_order_item_hook($order_id, $product_id);
            endforeach;
        endif;
    }

    private function clear_schedule_hook($order_id) {
       
        $order = wc_get_order( $order_id );
        if($order->get_items()) :
            foreach($order->get_items() as $line_item) :
                $product_id = $line_item->get_product_id();
                $this->clear_single_order_item_hook($order_id, $product_id);
            endforeach;
        endif;
    }

    /**
     * Ensure upcoming event details are mirrored onto the order meta so AutomateWoo
     * can use them for triggers and email variables.
     *
     * @param int            $order_id    Order ID.
     * @param array|null     $posted_data Raw checkout data (unused).
     * @param WC_Order|null  $order_obj   WC_Order instance when available.
     */
    public function capture_order_event_meta( $order_id, $posted_data = null, $order_obj = null ) {
        if ( $order_obj instanceof WC_Order ) {
            $this->update_order_event_meta( $order_obj );
            return;
        }

        $order = wc_get_order( $order_id );
        if ( $order instanceof WC_Order ) {
            $this->update_order_event_meta( $order );
        }
    }

    /**
     * Persist event start/end data on the order itself.
     *
     * @param int|WC_Order $order Order object or ID.
     */
    private function update_order_event_meta( $order ) {
        if ( ! $order ) {
            return;
        }

        if ( is_numeric( $order ) ) {
            $order = wc_get_order( $order );
        }

        if ( ! $order instanceof WC_Order ) {
            return;
        }

        $events = array();

        foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
            $product_id = $item->get_product_id();
            if ( ! $product_id ) {
                continue;
            }

            $start = get_post_meta( $product_id, 'linkedEventStartDate', true );
            $end   = get_post_meta( $product_id, 'linkedEventEndDate', true );

            if ( ! $start && ! $end ) {
                continue;
            }

            $sort_value = $start ? strtotime( $start ) : ( $end ? strtotime( $end ) : PHP_INT_MAX );

            $events[] = array(
                'item_id'    => $item_id,
                'product_id' => $product_id,
                'title'      => $item->get_name(),
                'start'      => $start,
                'end'        => $end,
                'sort'       => $sort_value,
            );
        }

        $order_id = $order->get_id();

        if ( empty( $events ) ) {
            delete_post_meta( $order_id, '_mindevents_event_start' );
            delete_post_meta( $order_id, '_mindevents_event_end' );
            delete_post_meta( $order_id, '_mindevents_event_title' );
            delete_post_meta( $order_id, '_mindevents_event_schedule' );
            delete_post_meta( $order_id, '_mindevents_event_schedule_text' );
            return;
        }

        usort(
            $events,
            function ( $a, $b ) {
                return $a['sort'] <=> $b['sort'];
            }
        );

        $primary       = $events[0];
        $primary_start = $primary['start'] ? $primary['start'] : $primary['end'];
        $primary_end   = $primary['end'];

        if ( $primary_start ) {
            update_post_meta( $order_id, '_mindevents_event_start', $primary_start );
        } else {
            delete_post_meta( $order_id, '_mindevents_event_start' );
        }

        if ( $primary_end ) {
            update_post_meta( $order_id, '_mindevents_event_end', $primary_end );
        } else {
            delete_post_meta( $order_id, '_mindevents_event_end' );
        }

        if ( $primary['title'] ) {
            update_post_meta( $order_id, '_mindevents_event_title', $primary['title'] );
        } else {
            delete_post_meta( $order_id, '_mindevents_event_title' );
        }

        $schedule_payload = array();
        $schedule_text    = array();

        foreach ( $events as $event ) {
            $schedule_payload[] = array(
                'item_id'    => $event['item_id'],
                'product_id' => $event['product_id'],
                'title'      => $event['title'],
                'start'      => $event['start'],
                'end'        => $event['end'],
            );

            $this->sync_line_item_meta(
                $order->get_item( $event['item_id'] ),
                $event['start'],
                $event['end']
            );

            $label_parts = array();
            if ( $event['title'] ) {
                $label_parts[] = $event['title'];
            }
            if ( $event['start'] ) {
                $label_parts[] = sprintf( 'Start: %s', $event['start'] );
            }
            if ( $event['end'] ) {
                $label_parts[] = sprintf( 'End: %s', $event['end'] );
            }
            if ( ! empty( $label_parts ) ) {
                $schedule_text[] = implode( ' — ', $label_parts );
            }
        }

        $encoded_schedule = wp_json_encode( $schedule_payload );
        if ( $encoded_schedule ) {
            update_post_meta( $order_id, '_mindevents_event_schedule', $encoded_schedule );
        } else {
            delete_post_meta( $order_id, '_mindevents_event_schedule' );
        }

        if ( ! empty( $schedule_text ) ) {
            update_post_meta( $order_id, '_mindevents_event_schedule_text', implode( "\n", $schedule_text ) );
        } else {
            delete_post_meta( $order_id, '_mindevents_event_schedule_text' );
        }
    }

    /**
     * Hooked into checkout line item creation so each product in the order retains
     * its event start/end data even when multiple events are purchased together.
     *
     * @param WC_Order_Item_Product $item
     * @param string                $cart_item_key
     * @param array                 $values
     * @param WC_Order              $order
     */
    public function attach_line_item_event_meta( $item, $cart_item_key, $values, $order ) {
        $product_id = $item->get_product_id();
        if ( ! $product_id ) {
            return;
        }

        $start = get_post_meta( $product_id, 'linkedEventStartDate', true );
        $end   = get_post_meta( $product_id, 'linkedEventEndDate', true );

        if ( ! $start && ! $end ) {
            return;
        }

        $this->sync_line_item_meta( $item, $start, $end );
    }

    /**
     * Store event timing metadata directly on the order item so AutomateWoo
     * product/order item variables can reference it per ticket.
     *
     * @param WC_Order_Item_Product|null $item
     * @param string                     $start
     * @param string                     $end
     */
    private function sync_line_item_meta( $item, $start, $end ) {
        if ( ! $item instanceof WC_Order_Item_Product ) {
            return;
        }

        $this->set_line_item_event_meta( $item, $start, $end );
        $item->save();
    }

    private function set_line_item_event_meta( $item, $start, $end ) {
        if ( ! $item instanceof WC_Order_Item_Product ) {
            return;
        }

        if ( $start ) {
            $item->update_meta_data( '_mindevents_event_start', $start );
        } else {
            $item->delete_meta_data( '_mindevents_event_start' );
        }

        if ( $end ) {
            $item->update_meta_data( '_mindevents_event_end', $end );
        } else {
            $item->delete_meta_data( '_mindevents_event_end' );
        }
    }
    
    public function add_attendee($order_id) { 
        $order = wc_get_order( $order_id );

        if($order->get_items()) :
            foreach($order->get_items() as $line_item) :
                $product_id = $line_item->get_product_id();
                
                
                $get_linked_event = get_post_meta($product_id, 'linked_event', true);
                $get_linked_occurance = get_post_meta($product_id, 'linked_occurance', true);


                if($get_linked_event && $get_linked_occurance) :
                    $quantity = $line_item->get_quantity();
                    $attendees = get_post_meta($get_linked_event, 'attendees', true);
                    if(!$attendees) :
                        $attendees = array();
                    endif;

                    
                    for($i = 0; $i < $quantity; $i++) :
                        $attendees[$get_linked_occurance][] = array(
                            'order_id' => $order->get_id(),
                            'user_id' => $order->get_user_id(),
                            'checked_in' => false,
                        );
                    endfor;

                    update_post_meta($get_linked_event, 'attendees', $attendees);
                    if ( function_exists( 'mindshare_automatewoo_touch_occurrence' ) ) {
                        mindshare_automatewoo_touch_occurrence( $get_linked_occurance );
                    }
                endif;

                
            
            endforeach;
        endif;
    }
    
    public function remove_attendee($order_id) {
        $order = wc_get_order( $order_id );
        if($order->get_items()) :
            foreach($order->get_items() as $line_item) :
                $product_id = $line_item->get_product_id();
                $get_linked_event = get_post_meta($product_id, 'linked_event', true);
                $get_linked_occurance = get_post_meta($product_id, 'linked_occurance', true);

         
                if($get_linked_event && $get_linked_occurance) :
                    $attendees = get_post_meta($get_linked_event, 'attendees', true);
                    if(!$attendees) :
                        $attendees = array();
                    endif;
                    
                    $quantity = $line_item->get_quantity();
                    for($i = 0; $i < $quantity; $i++) :
                        if($attendees[$get_linked_occurance]) :
                            $attendees[$get_linked_occurance] = array_filter($attendees[$get_linked_occurance], function($attendee) use ($order) {
                                return $attendee['order_id'] != $order->get_id();
                            });
                        endif;
                    endfor;

                    update_post_meta($get_linked_event, 'attendees', $attendees);
                    if ( function_exists( 'mindshare_automatewoo_touch_occurrence' ) ) {
                        mindshare_automatewoo_touch_occurrence( $get_linked_occurance );
                    }
                endif;
            endforeach;
        endif;
    }

    private function reschedule_order_item($order, $item, $new_product_id, $current_entry) {
        if (!$order || !($item instanceof WC_Order_Item_Product)) :
            return new WP_Error('mindevents_invalid_order_item', 'We could not update that class.');
        endif;

        $replacement = false;
        foreach ($current_entry['replacements'] as $option) :
            if ((int) $option['product_id'] === (int) $new_product_id) :
                $replacement = $option;
                break;
            endif;
        endforeach;

        if (!$replacement) :
            return new WP_Error('mindevents_invalid_replacement', 'The class date you selected is no longer available.');
        endif;

        $old_product_id = (int) $item->get_product_id();
        $old_product = wc_get_product($old_product_id);
        $new_product = wc_get_product($new_product_id);

        if (!$old_product || !$new_product) :
            return new WP_Error('mindevents_missing_product', 'We could not load the class product for this change.');
        endif;

        require_once WC_ABSPATH . 'includes/admin/wc-admin-functions.php';

        $quantity = max(1, (int) $item->get_quantity());
        $old_total = $item->get_total();
        $old_subtotal = $item->get_subtotal();
        $old_start_date = $current_entry['start_date'];
        $old_end_date = $current_entry['end_date'];
        $new_start_date = get_post_meta($new_product_id, 'linkedEventStartDate', true);
        $new_end_date = get_post_meta($new_product_id, 'linkedEventEndDate', true);

        if (!$new_start_date) :
            $new_start_date = get_post_meta($replacement['occurrence_id'], 'event_start_time_stamp', true);
        endif;

        if (!$new_end_date) :
            $new_end_date = get_post_meta($replacement['occurrence_id'], 'event_end_time_stamp', true);
        endif;

        $new_line_total = wc_get_price_excluding_tax($new_product, array(
            'qty' => $quantity,
            'order' => $order,
        ));

        $restocked_old = wc_maybe_adjust_line_item_product_stock($item, 0);
        if (is_wp_error($restocked_old)) :
            return $restocked_old;
        endif;

        $item->set_product($new_product);
        $item->set_quantity($quantity);
        $item->set_total($new_line_total);
        $item->set_subtotal($new_line_total);
        $item->set_taxes(array(
            'total' => array(),
            'subtotal' => array(),
        ));
        $this->set_line_item_event_meta($item, $new_start_date, $new_end_date);

        do_action('woocommerce_before_save_order_item', $item);
        $item->save();

        $reduced_new = wc_maybe_adjust_line_item_product_stock($item);
        if (is_wp_error($reduced_new)) :
            $item->set_product($old_product);
            $item->set_quantity($quantity);
            $item->set_total($old_total);
            $item->set_subtotal($old_subtotal);
            $item->set_taxes(array(
                'total' => array(),
                'subtotal' => array(),
            ));
            $this->set_line_item_event_meta($item, $old_start_date, $old_end_date);

            do_action('woocommerce_before_save_order_item', $item);
            $item->save();
            wc_maybe_adjust_line_item_product_stock($item);

            return $reduced_new;
        endif;

        if ($order->get_items('coupon')) :
            $order->recalculate_coupons();
        else :
            $order->calculate_totals();
            $order->save();
        endif;

        $this->update_order_event_meta($order);

        $this->move_order_item_attendees(
            $order->get_id(),
            $current_entry['parent_event_id'],
            $current_entry['occurrence_id'],
            $replacement['occurrence_id'],
            $quantity
        );

        $this->clear_single_order_item_hook($order->get_id(), $old_product_id);
        $this->schedule_single_order_item_hook($order->get_id(), $new_product_id);
        $this->update_sub_event_order_stats($current_entry['occurrence_id']);
        $this->update_sub_event_order_stats($replacement['occurrence_id']);

        if (function_exists('mindshare_automatewoo_touch_occurrence')) :
            mindshare_automatewoo_touch_occurrence($current_entry['occurrence_id']);
            mindshare_automatewoo_touch_occurrence($replacement['occurrence_id']);
        endif;

        $order->add_order_note(
            'Class rescheduled from ' . $this->format_date_range($old_start_date, $old_end_date) . ' to ' . $this->format_date_range($new_start_date, $new_end_date) . '.'
        );

        return array(
            'new_label' => $this->format_date_range($new_start_date, $new_end_date),
        );
    }

    private function move_order_item_attendees($order_id, $parent_event_id, $from_occurrence_id, $to_occurrence_id, $quantity) {
        $order = wc_get_order($order_id);
        if (!$order || !$parent_event_id || !$from_occurrence_id || !$to_occurrence_id) :
            return;
        endif;

        $attendees = get_post_meta($parent_event_id, 'attendees', true);
        if (!$attendees) :
            $attendees = array();
        endif;

        if (!isset($attendees[$from_occurrence_id]) || !is_array($attendees[$from_occurrence_id])) :
            $attendees[$from_occurrence_id] = array();
        endif;

        if (!isset($attendees[$to_occurrence_id]) || !is_array($attendees[$to_occurrence_id])) :
            $attendees[$to_occurrence_id] = array();
        endif;

        $moved = 0;
        $remaining_attendees = array();

        foreach ($attendees[$from_occurrence_id] as $attendee) :
            if ($moved < $quantity && isset($attendee['order_id']) && (int) $attendee['order_id'] === (int) $order_id) :
                $attendee['checked_in'] = false;
                $attendees[$to_occurrence_id][] = $attendee;
                $moved++;
            else :
                $remaining_attendees[] = $attendee;
            endif;
        endforeach;

        while ($moved < $quantity) :
            $attendees[$to_occurrence_id][] = array(
                'order_id' => $order_id,
                'user_id' => $order->get_user_id(),
                'checked_in' => false,
            );
            $moved++;
        endwhile;

        $attendees[$from_occurrence_id] = array_values($remaining_attendees);

        update_post_meta($parent_event_id, 'attendees', $attendees);
    }

    private function schedule_single_order_item_hook($order_id, $product_id) {
        $event_start_date = get_post_meta($product_id, 'linkedEventStartDate', true);
        $event_start = $this->get_event_datetime($event_start_date);

        if (!$event_start) :
            return;
        endif;

        $schedule_timestamp = $event_start->getTimestamp() - (DAY_IN_SECONDS * 3);
        if ($schedule_timestamp <= $this->get_current_datetime()->getTimestamp()) :
            return;
        endif;

        wp_schedule_single_event($schedule_timestamp, 'woo_event_three_days_before', array(
            'order_id' => $order_id,
            'product_id' => $product_id,
        ), true);
    }

    private function clear_single_order_item_hook($order_id, $product_id) {
        wp_clear_scheduled_hook('woo_event_three_days_before', array(
            'order_id' => $order_id,
            'product_id' => $product_id,
        ));
    }



    /**
     * Save event options when the event post is saved.
     *
     * This function is hooked to the 'save_post_events' action and is responsible for saving
     * the event options when an event post is saved. It ensures that the event options are
     * only saved when certain conditions are met, such as the post type being 'events' and
     * the current user having the necessary permissions.
     *
     * @param int     $post_id The ID of the post being saved.
     * @param WP_Post $post    The post object being saved.
     * @param bool    $update  Whether this is an existing post being updated or not.
     */
    public function save_event_options($event_id, $post, $update) {
        if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if($post->post_type != 'events') return;
        if(!current_user_can('edit_post', $event_id)) return;
        if($post->post_status == 'auto-draft') return;
        if(defined( 'REST_REQUEST' ) && REST_REQUEST ) return;
        if(wp_is_post_autosave( $event_id )) return;
        if(wp_is_post_revision( $event_id )) return;

        $sub_events = $this->get_sub_events($event_id);


        if($sub_events) :
            $adding = array();
            $sub_event_ids = array();
            foreach($sub_events as $sub_event) :
                $sub_event_ids[] = $sub_event->ID;
                $adding[$sub_event->ID] = $sub_event->ID;
            endforeach;

            update_post_meta($event_id, 'sub_events', $adding);
        endif;

    }

    public function get_attendees($event_id) {
        $attendees = get_post_meta($event_id, 'attendees', true);
        if(!$attendees) :
            $sub_events = $this->get_sub_events($event_id);
            foreach($sub_events as $sub_event) :
                //get linked product
                $product_id = get_post_meta($sub_event->ID, 'linked_product', true);
                //get all orders for this product
                $orders = $this->get_orders_ids_by_product_id($product_id);
                //foreach order
                foreach($orders as $order) :
                    add_attendee($order->ID);
                endforeach;

                $attendees[$sub_event->ID] = array();
            endforeach;
        endif;
        return $attendees;
    }

    public function create_woocommerce_event_product($post_id, $post, $update) {
        if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if($post->post_type != 'events') return;
        if(!current_user_can('edit_post', $post_id)) return;
        if($post->post_status == 'auto-draft') return;
        if(defined( 'REST_REQUEST' ) && REST_REQUEST ) return;
        if(wp_is_post_autosave( $post_id )) return;
        if(wp_is_post_revision( $post_id )) return;

        // mapi_write_log('Creating WooCommerce Product for Event: ' . $post_id);
        
        $event_type = get_post_meta($post_id, 'event_type', true);
        $has_tickets = get_post_meta($post_id, 'has_tickets', true);
        if(!$has_tickets) :
            return;
        endif;
        
        mapi_write_log('Event Type: ' . $event_type);
        
        //a Single event means this event will only have one ticket, but span multiple days. 
        if($event_type == 'single-event') :
            $meta = get_post_meta($post_id);

            $product_id = $this->build_product($post_id, $meta);
            // mapi_write_log('Created/Updated Product ID: ' . $product_id);
            
            $this->sync_meta($post_id, $product_id);


        //Multiple event means this event will have multiple tickets, each with their own date and time.
        elseif($event_type == 'multiple-events') :

            $sub_events = $this->get_sub_events($post_id);

            if($sub_events) :
                foreach($sub_events as $key => $sub_event) :
                    $meta = get_post_meta($sub_event->ID);
                    $product_id = $this->build_product($post_id, $meta);
            
                    $this->sync_meta($sub_event->ID, $product_id);

                endforeach;
            endif;
        endif;

    }


    private function build_product($post_id, $meta) {
        // mapi_write_log('Building Product for Event/Post ID: ' . $post_id);
        // mapi_write_log($meta);
        $post = get_post($post_id);

        $start_date = (isset($meta['event_start_time_stamp'][0]) ? $meta['event_start_time_stamp'][0] : $meta['first_event_date'][0]);
        $end_date = (isset($meta['event_end_time_stamp'][0]) ? $meta['event_end_time_stamp'][0] : $meta['last_event_date'][0]);
       
        $start_date = new DateTime($start_date);
        $end_date = new DateTime($end_date);

        $sku = $this->build_sku($post_id, $start_date->format('m-d-Y'));
        
        $sku_id = wc_get_product_id_by_sku($sku);
        $product_id = (isset($meta['linked_product'][0]) ? $meta['linked_product'][0] : false);

        //mapi_write_log('SKU: ' . $sku . ' | SKU Product ID: ' . $sku_id . ' | Linked Product ID: ' . $product_id);

        //default to the product that already exists by sku because skus are unique
        if($sku_id) :
            $product_id = $sku_id;
        endif;

        //if no product id is set, check if this is a sub event and get the parent event
        if(!$product_id) :
            $parent_event = get_post_parent( $post);
            $product_id = get_post_meta($parent_event, 'linked_product', true);
        endif;
  

        $new_product = false;
        if($product_id) :
            $product = wc_get_product($product_id);
            
            if(!$product) :
                $product = new WC_Product_Simple();
                $new_product = true;
            endif;
        else :
            // Create a new product
            $product = new WC_Product_Simple();
            $new_product = true;
        endif;

        //if end date is different from start date, adjust title to ionclude end time not date
        if($start_date->format('m-d-Y') != $end_date->format('m-d-Y')) :
            $title = get_the_title($post_id) . ' | ' . $start_date->format('D, M j g:i a') . ' - ' . $end_date->format('D, M j g:i a');
        else :
            $title = get_the_title($post_id) . ' | ' . $start_date->format('D, M j g:i a') . ' to ' . $end_date->format('g:i a');
        endif;

        $price = $product->get_regular_price();
     
        //mapi_write_log('Building Product: ' . $title . ' | Price: ' . $price . ' | New Product: ' . ($new_product ? 'Yes' : 'No'));
        if($new_product) :
            $product->set_sku($sku);
            $product->set_name($title);
            if($meta['ticket_stock'][0]) :
                $product->set_manage_stock(true); 
                $product->set_stock_quantity($meta['ticket_stock'][0]);
            else :
                $product->set_manage_stock(false);
            endif; 
            $product->set_description($post->post_excerpt);
            $product->set_short_description($post->post_excerpt);

        endif;

        if(!$price) :
            $product->set_regular_price(($meta['ticket_price'][0] ? $meta['ticket_price'][0] : 100));
        endif;
        $product->set_catalog_visibility('hidden');
        $product->set_virtual(true);
        $product->set_status('publish');

        $product_id = $product->save();

        return $product_id;
    }


    /**
     * Sync the event meta with the product meta.
     *
     * @param int $sub_event_id The ID of the sub event.
     * @param int $product_id The ID of the product.
     */

    private function sync_meta($sub_event_id, $product_id) {
        // mapi_write_log('Syncing Meta for Sub Event ID: ' . $sub_event_id . ' with Product ID: ' . $product_id);
        $post = get_post($sub_event_id);
        $meta = get_post_meta($sub_event_id);
        $parent_id = ($post->post_parent == 0 ? $sub_event_id : $post->post_parent);
        $event_type = get_post_meta($parent_id, 'event_type', true);
        // mapi_write_log('Parent Event ID: ' . $parent_id . ' | Event Type: ' . $event_type);
       
        $start_date = (isset($meta['event_start_time_stamp'][0]) ? $meta['event_start_time_stamp'][0] : $meta['first_event_date'][0]);
        $end_date = (isset($meta['event_end_time_stamp'][0]) ? $meta['event_end_time_stamp'][0] : $meta['last_event_date'][0]);

        //Add product ID to event post meta
        update_post_meta($sub_event_id, 'linked_product', $product_id);

        //if this is a series, get all sub events and link the product them
        if($event_type == 'single-event') :
            $sub_events = $this->get_sub_events($parent_id);
            if($sub_events) :
                foreach($sub_events as $sub_event) :
                    update_post_meta($sub_event->ID, 'linked_product', $product_id);
                endforeach;
            endif;
        endif;
        

       
        update_post_meta($product_id, 'linkedEventStartDate', $start_date );
        update_post_meta($product_id, 'linkedEventEndDate', $end_date );
   

        update_post_meta($product_id, 'linked_event', ($post->post_parent ? $post->post_parent : $sub_event_id));
        update_post_meta($product_id, 'linked_occurance', $sub_event_id);
        update_post_meta($product_id, '_has_event', true);
        if ( function_exists( 'mindshare_automatewoo_touch_occurrence' ) ) {
            mindshare_automatewoo_touch_occurrence( $sub_event_id );
        }
    }

    public function get_sub_events($post_id) {
        $defaults = array(
          'meta_query' => array(
            // 'relation' => 'AND',
            'start_clause' => array(
              'key' => 'event_start_time_stamp',
              'compare' => 'EXISTS',
            ),
          ),
          'orderby'          => 'meta_value',
          'meta_key'         => 'event_start_time_stamp',
          'meta_type'        => 'DATETIME',
          'order'            => 'ASC',
          'post_type'        => 'sub_event',
          'post_parent'      => $post_id,
          'suppress_filters' => true,
          'posts_per_page'   => -1,
        );
    
        return get_posts($defaults);
    
    }


    private function build_sku($eventID, $start_date = '') {
        return sanitize_title($eventID . '_' . $start_date);
    }

    /**
     * Get All orders IDs for a given product ID.
     *
     * @param  integer  $product_id (required)
     * @param  array    $order_status (optional) Default is 'wc-completed'
     *
     * @return array
     */
    private function get_orders_ids_by_product_id( $product_id, $order_status = array( 'wc-completed' ) ){
        global $wpdb;

        $results =$wpdb->get_col("
            SELECT order_items.order_id
            FROM {$wpdb->prefix}woocommerce_order_items as order_items
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
            LEFT JOIN {$wpdb->posts} AS posts ON order_items.order_id = posts.ID
            WHERE posts.post_type = 'shop_order'
            AND posts.post_status IN ( '" . implode( "','", $order_status ) . "' )
            AND order_items.order_item_type = 'line_item'
            AND order_item_meta.meta_key = '_product_id'
            AND order_item_meta.meta_value = '".$product_id."'
            ORDER BY order_items.order_id DESC
        ");

        return $results;
    }


    private function get_order_statuses() {
        $statuses = array(
            'wc-pending',
            'wc-processing',
            'wc-on-hold',
            'wc-completed',
            'wc-cancelled',
            'wc-refunded',
            'wc-failed'
        );
        return $statuses;
    }

    /**
     * Calculate and update related orders count for a sub event
     * Uses the same data source as the admin attendee display
     *
     * @param int $sub_event_id The ID of the sub event
     * @return int Number of related orders
     */
    public function calculate_related_orders_count($sub_event_id) {
        // Get the parent event ID
        $parent_id = wp_get_post_parent_id($sub_event_id);
        if (!$parent_id) {
            update_post_meta($sub_event_id, 'related_orders_count', 0);
            return 0;
        }

        // Get attendees data from parent event (same as admin interface)
        $attendees = get_post_meta($parent_id, 'attendees', true);
        $attendees_for_sub = isset($attendees[$sub_event_id]) ? $attendees[$sub_event_id] : array();
        
        // Count total attendees (spots purchased) from valid orders
        $valid_attendees = 0;
        foreach ($attendees_for_sub as $attendee) {
            if (isset($attendee['order_id'])) {
                $order = wc_get_order($attendee['order_id']);
                if ($order && in_array($order->get_status(), array('completed', 'processing'))) {
                    $valid_attendees++;
                }
            }
        }
        
        $valid_orders = $valid_attendees;
        
        update_post_meta($sub_event_id, 'related_orders_count', $valid_orders);
        return $valid_orders;
    }

    /**
     * Calculate and update total revenue for a sub event
     * Uses the same data source as the admin attendee display
     * Calculates revenue from product line items only, not entire order totals
     * Accounts for refunds by subtracting refunded amounts
     *
     * @param int $sub_event_id The ID of the sub event
     * @return float Total revenue from orders
     */
    public function calculate_total_revenue($sub_event_id) {
        // Get the parent event ID
        $parent_id = wp_get_post_parent_id($sub_event_id);
        if (!$parent_id) {
            update_post_meta($sub_event_id, 'total_revenue', 0);
            return 0;
        }

        // Get attendees data from parent event (same as admin interface)
        $attendees = get_post_meta($parent_id, 'attendees', true);
        $attendees_for_sub = isset($attendees[$sub_event_id]) ? $attendees[$sub_event_id] : array();
        
        // Collect unique order IDs to avoid counting revenue multiple times
        $unique_order_ids = array();
        foreach ($attendees_for_sub as $attendee) {
            if (isset($attendee['order_id']) && !in_array($attendee['order_id'], $unique_order_ids)) {
                $order = wc_get_order($attendee['order_id']);
                if ($order && in_array($order->get_status(), array('completed', 'processing'))) {
                    $unique_order_ids[] = $attendee['order_id'];
                }
            }
        }
        
        // Calculate revenue from product line items only, accounting for refunds
        $total_revenue = 0;
        foreach ($unique_order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                // Get the linked product ID for this sub event
                $linked_product_id = get_post_meta($sub_event_id, 'linked_product', true);
                
                if ($linked_product_id) {
                    // Get all line items from the order
                    foreach ($order->get_items() as $line_item) {
                        // Check if this line item is for our linked product
                        if ($line_item->get_product_id() == $linked_product_id) {
                            // Add the line item total (price × quantity)
                            $line_item_total = floatval($line_item->get_total());
                            
                            // Subtract refunds for this line item
                            $refunded_amount = 0;
                            foreach ($order->get_refunds() as $refund) {
                                foreach ($refund->get_items() as $refund_item) {
                                    if ($refund_item->get_product_id() == $linked_product_id) {
                                        $refunded_amount += floatval($refund_item->get_total());
                                    }
                                }
                            }
                            
                            // Net revenue for this line item (original - refunds)
                            $net_revenue = $line_item_total + $refunded_amount; // refunded amounts are negative
                            $total_revenue += $net_revenue;
                        }
                    }
                }
            }
        }
        
        update_post_meta($sub_event_id, 'total_revenue', $total_revenue);
        return $total_revenue;
    }

    /**
     * Calculate and update customer orders list for a sub event
     * Creates an array of Order ID: Customer Name with clickable links
     *
     * @param int $sub_event_id The ID of the sub event
     * @return array Formatted customer orders array
     */
    public function calculate_customer_orders_list($sub_event_id) {
        // Get the parent event ID
        $parent_id = wp_get_post_parent_id($sub_event_id);
        if (!$parent_id) {
            update_post_meta($sub_event_id, 'customer_orders_list', array());
            return array();
        }

        // Get attendees data from parent event
        $attendees = get_post_meta($parent_id, 'attendees', true);
        $attendees_for_sub = isset($attendees[$sub_event_id]) ? $attendees[$sub_event_id] : array();
        
        // Collect unique orders with customer info and clickable links
        $customer_orders = array();
        $processed_orders = array();
        
        foreach ($attendees_for_sub as $attendee) {
            if (isset($attendee['order_id']) && !in_array($attendee['order_id'], $processed_orders)) {
                $order = wc_get_order($attendee['order_id']);
                if ($order && in_array($order->get_status(), array('completed', 'processing'))) {
                    $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                    if (empty(trim($customer_name))) {
                        $customer_name = $order->get_billing_email();
                    }
                    
                    // Create clickable order link with line break (no colon or space)
                    $order_link = '<a href="' . admin_url('post.php?post=' . $attendee['order_id'] . '&action=edit') . '" target="_blank">Order #' . $attendee['order_id'] . '</a>';
                    
                    $customer_orders[] = $order_link . $customer_name . '<br>';
                    $processed_orders[] = $attendee['order_id'];
                }
            }
        }
        
        update_post_meta($sub_event_id, 'customer_orders_list', $customer_orders);
        return $customer_orders;
    }

    /**
     * Calculate profit for a sub event
     * Profit = Total Revenue - (Instructor Expense + (Materials Expense × Attendee Count))
     *
     * @param int $sub_event_id The ID of the sub event
     * @return float The calculated profit
     */
    public function calculate_profit($sub_event_id) {
        // Get total revenue
        $total_revenue = $this->calculate_total_revenue($sub_event_id);
        
        // Get parent event ID
        $parent_id = wp_get_post_parent_id($sub_event_id);
        if (!$parent_id) {
            return $total_revenue; // No parent, so profit is just revenue
        }
        
        // Get expense values from parent event
        $instructor_expense = (float) get_post_meta($parent_id, 'instructor_expense', true);
        $materials_expense_per_attendee = (float) get_post_meta($parent_id, 'materials_expense', true);
        
        // Get attendee count for this sub event
        $attendee_count = $this->calculate_related_orders_count($sub_event_id);
        
        // Calculate total materials expense (per attendee × number of attendees)
        $total_materials_expense = $materials_expense_per_attendee * $attendee_count;
        
        // Calculate total expenses
        $total_expenses = $instructor_expense + $total_materials_expense;
        
        // Calculate and return profit (can be negative)
        $profit = $total_revenue - $total_expenses;
        
        return $profit;
    }

    /**
     * Update profit meta field for a sub event
     *
     * @param int $sub_event_id The ID of the sub event
     * @return void
     */
    public function update_profit_meta($sub_event_id) {
        $profit = $this->calculate_profit($sub_event_id);
        update_post_meta($sub_event_id, 'sub_event_profit', $profit);
    }

    /**
     * Update order stats for a sub event when order status changes
     *
     * @param int $sub_event_id The ID of the sub event
     */
    public function update_sub_event_order_stats($sub_event_id) {
        $this->calculate_related_orders_count($sub_event_id);
        $this->calculate_total_revenue($sub_event_id);
        $this->calculate_customer_orders_list($sub_event_id);
        $this->update_profit_meta($sub_event_id);
    }

    /**
     * Get sub event ID from product ID
     *
     * @param int $product_id The WooCommerce product ID
     * @return int|false Sub event ID or false if not found
     */
    public function get_sub_event_by_product_id($product_id) {
        $args = array(
            'post_type' => 'sub_event',
            'meta_query' => array(
                array(
                    'key' => 'linked_product',
                    'value' => $product_id,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1,
            'fields' => 'ids'
        );
        
        $results = get_posts($args);
        return !empty($results) ? $results[0] : false;
    }

    /**
     * Recalculate order stats for all sub events
     * Useful for initial setup or data migration
     */
    public function recalculate_all_sub_event_stats() {
        $args = array(
            'post_type' => 'sub_event',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => 'linked_product',
                    'compare' => 'EXISTS'
                )
            )
        );
        
        $sub_events = get_posts($args);
        
        foreach ($sub_events as $sub_event_id) {
            $this->update_sub_event_order_stats($sub_event_id);
        }
        
        return count($sub_events);
    }

    /**
     * Recalculate order stats in batches to prevent timeouts
     */
    public function recalculate_all_sub_event_stats_batched() {
        // First, get ALL sub events with linked products
        $args = array(
            'post_type' => 'sub_event',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => 'linked_product',
                    'compare' => 'EXISTS'
                )
            )
        );
        
        $all_sub_events = get_posts($args);
        
        // Initialize ALL with zero values first (fast operation)
        foreach ($all_sub_events as $sub_event_id) {
            if (!get_post_meta($sub_event_id, 'related_orders_count', true)) {
                update_post_meta($sub_event_id, 'related_orders_count', 0);
            }
            if (!get_post_meta($sub_event_id, 'total_revenue', true)) {
                update_post_meta($sub_event_id, 'total_revenue', 0);
            }
        }
        
        // Then calculate actual values for first batch only (slow operation)
        $first_batch = array_slice($all_sub_events, 0, 20);
        foreach ($first_batch as $sub_event_id) {
            $this->update_sub_event_order_stats($sub_event_id);
        }
        
        // Schedule the rest to be processed later
        if (count($all_sub_events) > 20) {
            wp_schedule_single_event(time() + 30, 'mindevents_process_remaining_stats', array(20));
        }
        
        return count($all_sub_events);
    }

    /**
     * Process remaining sub events in background
     */
    public function process_remaining_sub_event_stats($offset = 0) {
        $args = array(
            'post_type' => 'sub_event',
            'posts_per_page' => 20,
            'offset' => $offset,
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => 'linked_product',
                    'compare' => 'EXISTS'
                )
            )
        );
        
        $sub_events = get_posts($args);
        
        foreach ($sub_events as $sub_event_id) {
            $this->update_sub_event_order_stats($sub_event_id);
        }
        
        // Schedule next batch if there are more
        if (count($sub_events) == 20) {
            wp_schedule_single_event(time() + 30, 'mindevents_process_remaining_stats', array($offset + 20));
        }
    }

    /**
     * Force recalculation of ALL sub event stats (for manual use)
     * You can call this function to ensure all sub events are processed
     */
    public function force_recalculate_all_stats() {
        // Reset the initialization flag to allow re-processing
        delete_option('mindevents_order_stats_initialized');
        delete_transient('mindevents_initializing_stats');
        
        // Run the full recalculation
        return $this->recalculate_all_sub_event_stats();
    }

    /**
     * Handle expense meta field updates and trigger profit recalculation
     * This function is hooked to 'updated_post_meta' and triggers when
     * instructor_expense or materials_expense meta fields are updated
     *
     * @param int    $meta_id    ID of updated metadata entry
     * @param int    $object_id  Post ID
     * @param string $meta_key   Meta key
     * @param mixed  $meta_value Meta value
     */
    public function handle_expense_meta_update($meta_id, $object_id, $meta_key, $meta_value) {
        // Only proceed if this is an events post type
        if (get_post_type($object_id) !== 'events') {
            return;
        }
        
        // Only proceed if the updated meta key is one of our expense fields
        if ($meta_key !== 'instructor_expense' && $meta_key !== 'materials_expense') {
            return;
        }
        
        // Get all sub events for this parent event
        $sub_events = $this->get_sub_events($object_id);
        
        if ($sub_events) {
            // Recalculate profit for each sub event
            foreach ($sub_events as $sub_event) {
                $this->update_profit_meta($sub_event->ID);
            }
        }
    }

}



// Register the background processing hook
add_action('mindevents_process_remaining_stats', function($offset) {
    $woo_events = new mindEventsWooCommerce();
    $woo_events->process_remaining_sub_event_stats($offset);
});

new mindEventsWooCommerce();
