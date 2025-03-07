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

        $this->event = ($event ? $event : false);
        $this->event_type = get_post_meta($this->event, 'event_type', true);
    }

    public function add_event_options() {
        
    }

    public function order_status_change($order, $from, $to) {
        if( $to == 'refunded' || 
            $to == 'cancelled' || 
            $to == 'failed' || 
            $to == 'on-hold' || 
            $to == 'pending'
            ) :
            $this->remove_attendee($order);
        endif;

        if( $to == 'completed' || $to == 'processing') :
            if($from == 'completed' && $to == 'processing') :
                return;
            endif;
            
            $this->add_attendee($order);
            $this->schedule_hook($order);
        endif;

    }
   


    private function schedule_hook($order_id) {
        $order = wc_get_order( $order_id );
        if($order->get_items()) :
            foreach($order->get_items() as $line_item) :
                $product_id = $line_item->get_product_id();
                $event_start_date = get_post_meta($product_id, 'linkedEventStartDate', true);

                wp_schedule_single_event(strtotime($event_start_date) - DAY_IN_SECONDS * 3, 'woo_event_three_days_before', array(
                    'order_id' => $order_id,
                    'product_id' => $product_id,
                ));
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

            $attendees = get_post_meta($event_id, 'attendees', true);
            if(!$attendees) :
                $attendees = array();
            endif;

            foreach($sub_event_ids as $sub_event_id) :
                if(!array_key_exists($sub_event_id, $attendees)) :
                    $attendees[$sub_event_id] = array();
                endif;
            endforeach;

            $attendees_ordered = array() ;

            foreach (array_keys($adding) as $key) {
                $attendees_ordered[$key] = $attendees[$key];
            }


            update_post_meta($event_id, 'attendees', $attendees_ordered);
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


        $product_id = (isset($meta['linked_product'][0]) ? $meta['linked_product'][0] : false);
        
        if(!$product_id) :
            $parent_event = get_post_parent( $post);
            $product_id = get_post_meta($parent_event, 'linked_product', true);
        endif;


        $start_date = new DateTimeImmutable($start_date);
        $start_date = $start_date->setTimezone(new DateTimeZone(wp_timezone_string()));


        $end_date = new DateTimeImmutable($end_date);
        $end_date = $end_date->setTimezone(new DateTimeZone(wp_timezone_string()));

        $sku = $this->build_sku($post_id, $start_date->format('m-d-Y'));

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


        $title = get_the_title($post_id) . ' - ' . $start_date->format('D, M j') . ' - ' . $end_date->format('D, M j');
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
        else :
            $this->maybe_decrease_stock($product, $meta['ticket_stock'][0]);
            
            //get all orders for product
            $orders = $this->get_orders_ids_by_product_id($product->get_id(), $this->get_order_statuses());
            //add attendees
            if($orders) :
                foreach($orders as $order) :
                    mapi_write_log('Adding attendee for order: ' . $order);
                    $this->add_attendee($order);
                endforeach;
            endif;

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


    private function maybe_decrease_stock($product, $stock) {
        if($stock) :
            //get all orders for this product
            $orders = $this->get_orders_ids_by_product_id($product->get_id(), $this->get_order_statuses());
            $total_sold = 0;
            foreach($orders as $order) :
                $order = wc_get_order($order);

                //foreach items in order
                foreach($order->get_items() as $item) :
                    $product_id = $item->get_product_id();
                    if($product_id != $product->get_id()) :
                        continue;
                    endif;
                    $total_sold += $item->get_quantity();
                endforeach;
            endforeach;

            $stock = $stock - $total_sold;
            if($stock < 0) :
                $stock = 0;
            endif;

            $product->set_stock_quantity($stock);
        endif;
    }

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

}



add_action('init', function() {
    new mindEventsWooCommerce();
});
