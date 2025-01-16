<?php



class mindEventsWooCommerce {
    public function __construct() {
        add_action('woocommerce_init', array($this, 'add_event_options'));
        add_action('save_post_events', array($this, 'save_event_options'), 999, 3);



        add_action('save_post_events', array($this, 'create_woocommerce_event_product'), 1, 3);

        add_action('save_post_events', array($this, 'create_woocommerce_event_product'), 999, 3);



    }

    public function add_event_options() {
        
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
        $update = false;
        if($sub_events) :
            $adding = array();
            foreach($sub_events as $sub_event) :
                $adding[] = $sub_event->ID;
            endforeach;
            $update = update_post_meta($post_id, 'sub_events', $adding);
        endif;

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
