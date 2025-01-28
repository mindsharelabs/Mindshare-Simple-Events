<?php



class mindEventsWooCommerce {
    public function __construct() {
        add_action('woocommerce_init', array($this, 'add_event_options'));
        add_action('save_post_events', array($this, 'save_event_options'), 500, 3);


        add_action('save_post_events', array($this, 'create_woocommerce_event_product'), 999, 3);


        //attendee management
        add_action('woocommerce_order_status_changed', array($this, 'order_status_change'), 10, 3);


    }

    public function add_event_options() {
        
    }

    public function order_status_change($order, $from, $to) {
        
        if($to == 'refunded' || $to == 'cancelled' || $to == 'failed' || $to == 'on-hold') :
            $this->remove_attendee($order);
        endif;

        if($to == 'completed') :
            $this->add_attendee($order);
        endif;

    }
    private function add_attendee($order_id) { 
        $order = wc_get_order( $order_id );
        if($order->get_items()) :
            foreach($order->get_items() as $line_item) :
                $product_id = $line_item->get_product_id();
                $get_linked_event = get_post_meta($product_id, 'wooLinkedEvent', true);
                $get_linked_occurance = get_post_meta($product_id, 'wooLinkedOccurance', true);

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

    private function remove_attendee($order_id) {
        $order = wc_get_order( $order_id );
        if($order->get_items()) :
            foreach($order->get_items() as $line_item) :
                $product_id = $line_item->get_product_id();
                $get_linked_event = get_post_meta($product_id, 'wooLinkedEvent', true);
                $get_linked_occurance = get_post_meta($product_id, 'wooLinkedOccurance', true);
                if($get_linked_event && $get_linked_occurance) :
                    $attendees = get_post_meta($get_linked_event, 'attendees', true);
                    if(!$attendees) :
                        $attendees = array();
                    endif;
                    
                    $quantity = $line_item->get_quantity();
                    for($i = 0; $i < $quantity; $i++) :
                        $attendees[$get_linked_occurance] = array_filter($attendees[$get_linked_occurance], function($attendee) use ($order) {
                            return $attendee['order_id'] != $order->get_id();
                        });
                    endfor;

                    update_post_meta($get_linked_event, 'attendees', $attendees);
                endif;
            endforeach;
        endif;
    }

    public function save_event_options($post_id, $post, $update) {
        if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if($post->post_type != 'events') return;
        if(!current_user_can('edit_post', $post_id)) return;
        if($post->post_status == 'auto-draft') return;
        if(defined( 'REST_REQUEST' ) && REST_REQUEST ) return;
        if(wp_is_post_autosave( $post_id )) return;
        if(wp_is_post_revision( $post_id )) return;

        $sub_events = $this->get_sub_events($post_id);


        if($sub_events) :
            $adding = array();
            $sub_event_ids = array();
            foreach($sub_events as $sub_event) :
                $sub_event_ids[] = $sub_event->ID;
                $adding[$sub_event->ID] = $sub_event->ID;
            endforeach;

            $attendees = get_post_meta($post_id, 'attendees', true);
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


            update_post_meta($post_id, 'attendees', $attendees_ordered);
            update_post_meta($post_id, 'sub_events', $adding);
        endif;

    }

    public function get_attendees($event_id) {
        $attendees = get_post_meta($event_id, 'attendees', true);
        if(!$attendees) :
            $sub_events = $this->get_sub_events($event_id);
            foreach($sub_events as $sub_event) :
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


        $unique_keys = (get_post_meta($post_id, 'wooUniqueKey', true) ? get_post_meta($post_id, 'wooUniqueKey', true) : array());

        $sub_events = $this->get_sub_events($post_id);

        if($sub_events) :
            foreach($sub_events as $key => $sub_event) :
                
                $meta = get_post_meta($sub_event->ID);
                $unique_key = $this->build_unique_key($sub_event->ID, $meta['event_start_time_stamp'][0]);
                $product_id = $meta['wooLinkedProduct'][0];


                
                //if the unique key already exists, skip this iteration
                if(in_array($unique_key, $unique_keys)) :
                    unset($unique_keys[array_search($unique_key, $unique_keys)]);
                    continue;
                endif;

                
                if($product_id) :
                    $product = wc_get_product($product_id);
                    if(!$product) :
                        $product = new WC_Product_Simple();
                    endif;
                else :
                    $product = new WC_Product_Simple();
                endif;

              
                $event_start_date = new DateTimeImmutable($meta['event_start_time_stamp'][0]);

                // Create a new product
                $product->set_name($post->post_title . ' - ' . $event_start_date->format('D, M d Y @ H:i'));
                $product->set_sku($unique_key); 
                $product->set_description($post->post_excerpt);
                $product->set_short_description($post->post_excerpt);
                $product->set_regular_price($meta['wooPrice'][0]); 

                if($meta['wooStock'][0]) :
                    $product->set_manage_stock(true); 
                    $product->set_stock_quantity($meta['wooStock'][0]);
                else :
                    $product->set_manage_stock(false);
                endif; 
                $product->set_catalog_visibility('hidden');
                $product->set_virtual(true);
                $product->set_status('publish');
                    
                $product_id = $product->save();
                
                //Add product ID to event post meta
                update_post_meta($sub_event->ID, 'wooLinkedProduct', $product_id);

                //Add unique key to event post meta, this matches the SKU of the product
                update_post_meta($sub_event->ID, 'wooUniqueKey', $product_id);
               
                //Add event ID to event product meta
                update_post_meta($product_id, 'wooLinkedEvent', $post_id);
                update_post_meta($product_id, 'wooLinkedOccurance', $sub_event->ID);
                update_post_meta($product_id, '_has_event', true);

            endforeach;
        endif;


        //Delete any products that are no longer needed
        if(!empty($unique_keys)) :
            foreach($unique_keys as $key => $unique_key) :
                $product_id = wc_get_product_id_by_sku($unique_key);
                $product = wc_get_product($product_id);
                if($product) :
                    $product->delete(false);
                endif;
            endforeach;
        endif;

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


    private function build_unique_key($eventID, $start_date = '') {
        return sanitize_title($eventID . '_' . $start_date);
    }

}



add_action('init', function() {
    new mindEventsWooCommerce();
});
