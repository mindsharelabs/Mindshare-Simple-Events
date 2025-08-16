<?php



class mindEventsWooCommerce {


    public $event = '';
    public $event_type = '';



    public function __construct($event = false) {
        add_action('woocommerce_init', array($this, 'add_event_options'));
        add_action('save_post_events', array($this, 'save_event_options'), 500, 3);


        add_action('save_post_events', array($this, 'create_woocommerce_event_product'), 999, 3);
 

        //attendee management
        add_action('woocommerce_order_status_changed', array($this, 'order_status_change'), 10, 3);

        // Initialize order stats for existing sub events (run once, but more efficiently)
        add_action('wp_loaded', array($this, 'maybe_initialize_order_stats'));

        $this->event = ($event ? $event : false);
        $this->event_type = get_post_meta($this->event, 'event_type', true);
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
                $event_start_date = get_post_meta($product_id, 'linkedEventStartDate', true);
                wp_schedule_single_event(strtotime($event_start_date) - (DAY_IN_SECONDS * 3), 'woo_event_three_days_before', array(
                    'order_id' => $order_id,
                    'product_id' => $product_id,
                ), true);
            endforeach;
        endif;
    }

    private function clear_schedule_hook($order_id) {
       
        $order = wc_get_order( $order_id );
        if($order->get_items()) :
            foreach($order->get_items() as $line_item) :
                $product_id = $line_item->get_product_id();
                wp_clear_scheduled_hook( 'woo_event_three_days_before', array(
                    'order_id' => $order_id,
                    'product_id' => $product_id,
                ) );
            endforeach;
        endif;
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
                endif;
            endforeach;
        endif;
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


        
        $event_type = get_post_meta($post_id, 'event_type', true);
        $has_tickets = get_post_meta($post_id, 'has_tickets', true);
        if(!$has_tickets) :
            return;
        endif;
       
        
        //a Single event means this event will only have one ticket, but span multiple dayes. 
        if($event_type == 'single-event') :
            $meta = get_post_meta($post_id);

            $product_id = $this->build_product($post_id, $meta);
            
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
        $post = get_post($post_id);

        $start_date = (isset($meta['event_start_time_stamp'][0]) ? $meta['event_start_time_stamp'][0] : $meta['first_event_date'][0]);
        $end_date = (isset($meta['event_end_time_stamp'][0]) ? $meta['event_end_time_stamp'][0] : $meta['last_event_date'][0]);
       
        $start_date = new DateTime($start_date);
        $end_date = new DateTime($end_date);

        $sku = $this->build_sku($post_id, $start_date->format('m-d-Y'));
        
        $sku_id = wc_get_product_id_by_sku($sku);
        $product_id = (isset($meta['linked_product'][0]) ? $meta['linked_product'][0] : false);

        //defauly to the product that already exists by sku because skus are unique
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
        $post = get_post($sub_event_id);
        $meta = get_post_meta($sub_event_id);
        $parent_id = $post->post_parent;
        $event_type = get_post_meta($parent_id, 'event_type', true);
       
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
    }

    public function get_sub_events($post_id) {
        $defaults = array(
          'meta_query' => array(
            // 'relation' => 'AND',
            'start_clause' => array(
              'key' => 'starttime',
              'compare' => 'EXISTS',
            ),
            'date_clause' => array(
              'key' => 'event_date',
              'compare' => 'EXISTS',
            ),
          ),
          'orderby'          => 'meta_value',
          'meta_key'         => 'event_time_stamp',
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

}



// Register the background processing hook
add_action('mindevents_process_remaining_stats', function($offset) {
    $woo_events = new mindEventsWooCommerce();
    $woo_events->process_remaining_sub_event_stats($offset);
});

add_action('init', function() {
    new mindEventsWooCommerce();
});
