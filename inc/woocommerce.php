<?php



class mindEventsWooCommerce {
    public function __construct() {
        add_action('woocommerce_init', array($this, 'add_event_options'));
        add_action('save_post_events', array($this, 'save_event_options'), 999, 3);
        add_action('save_post_events', array($this, 'create_woocommerce_event_product'), 1, 3);
        
        add_action('save_post_events', array($this, 'create_woocommerce_event_product'), 999, 3);


        // add_action('mindevents_before_add_sub_event', array($this, 'create_woo_product'), 10, 3);

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


        $product_name = 'Product for ' . $post->post_title;
        $description = $post->post_excerpt; 
        $price = '';
        $dates = array();
        
        $parent_meta = get_post_meta($post_id);
        $parent_product_sku = $this->build_unique_key($post_id, $parent_meta['first_event_date'][0]);
        $product_id = wc_get_product_id_by_sku($parent_product_sku);

        $sub_events = $this->get_sub_events($post_id);

       

        if($sub_events) :
            $sub_event_data = array();
            foreach($sub_events as $sub_event) :

                
                $meta = update_post_meta($sub_event->ID, 'wooLinkedProduct', $product_id);
                

                $meta = get_post_meta($sub_event->ID);
                $event_start_date = new DateTimeImmutable($meta['event_start_time_stamp'][0]);
                $dates[] = $event_start_date->format('D, M d Y @ H:i');
                $price = $meta['wooPrice'][0];
                $sub_event_data[] = array(
                    'event_start_date' => $event_start_date,
                    'event_start_date_readable' => $event_start_date->format('D, M d Y @ H:i'),
                    'event_end_date' => new DateTimeImmutable($meta['event_end_time_stamp'][0]),
                    'price' => $meta['wooPrice'][0],
                    'stock' => $meta['wooStock'][0],
                    'sub_event_id' => $sub_event->ID,
                    'sku' => $this->build_unique_key($sub_event->ID, $event_start_date->format('D, M d Y @ H:i'))
                );
            endforeach;
        endif;


        if($product_id) :
            $product = wc_get_product($product_id);
        else :
            // Create a new product
            $product = new WC_Product_Variable();
            $product->set_name($product_name);
            $product->set_sku($this->build_unique_key($post_id, $parent_meta['first_event_date'][0])); 
            $product->set_description($description);
            $product->set_regular_price($price); // Default price for variations
            $product->set_manage_stock(false); // No stock management for variations
            $product->set_status('publish');
            $product_id = $product->save();
        endif;

        //Add product ID to event post meta
        update_post_meta($post_id, 'wooLinkedProduct', $product_id);

        //Add event ID to event product meta
        update_post_meta($product_id, 'wooLinkedEvent', $post_id);


        // Add an attribute for the variations (dates in this case)
        $attribute = new WC_Product_Attribute();
        $attribute->set_name('Event Date'); // Name of the attribute
        $attribute->set_options($dates); // Set the recurring dates as options
        $attribute->set_visible(true); // Visible on the product page
        $attribute->set_variation(true); // Used for variations
    
        $product->set_attributes([$attribute]); // Add the attribute to the product
        $product_id = $product->save();
        
        


        if($product_id) :
            $get_variations = $product->get_available_variations();
            // Create variations for each date
            foreach ($sub_event_data as $key => $sub_event) :
                $unique_key = $this->build_unique_key($sub_event['sub_event_id'], $sub_event['event_start_date_readable']);
                $variation_skus = array_column($get_variations, 'sku');
                $sub_event_skus = array_column($sub_event_data, 'sku');

                $extra_variations = array_diff($variation_skus, $sub_event_skus);
                
                $exist = array_search($unique_key, array_column($get_variations, 'sku'));

                //Create a new variation if it does not exist
                if($exist === false) :
                    $variation_id = wc_get_product_id_by_sku( $unique_key );
                    if(!$variation_id) :
                        $variation = new WC_Product_Variation();
                    else :
                        $variation = wc_get_product_object( 'variation', $variation_id );
                    endif;  

                    $variation->set_parent_id($product_id);
                    $variation->set_attributes(['Event Date' => $sub_event['event_start_date_readable']]); // Set date as the variation attribute
                    $variation->set_regular_price($sub_event['price']); // Price for this variation
                    $variation->set_sku($unique_key); // Price for this variation
                    $variation->set_manage_stock(true); // No stock management
                    $variation->set_stock_status( 'instock' ); // outofstock, onbackorder
                    $variation->set_stock_quantity( (int)$sub_event['stock']  ); 
                    $variation->set_status('publish');
                    $variation->save();

                endif;

                //delete variations that are not in the sub events
                
                foreach($extra_variations as $extra_variation) :
                    $variation_id = wc_get_product_id_by_sku( $extra_variation );
                    $variation = wc_get_product_object( 'variation', $variation_id );
                    $variation->delete(true);
                endforeach;


            endforeach;
            return $product_id; // Return the ID of the created product
        else :
            return false;    
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
