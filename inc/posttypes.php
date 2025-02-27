<?php
/*------------------------------------*\
Custom Post Types
\*------------------------------------*/

class mindEventsCPTS {
  public function __construct() {
    add_action('init', array($this,'create_post_types')); // Add our mind Blank Custom Post Type
  }

  static function create_post_types() {
  	$event_args = array(
  		'label'                 => __( 'Event', 'text_domain' ),
  		'description'           => __( 'Events', 'text_domain' ),
  		'labels'                => array(
    		'name'                  => _x( 'Events', 'Post Type General Name', 'text_domain' ),
    		'singular_name'         => _x( 'Event', 'Post Type Singular Name', 'text_domain' ),
    		'menu_name'             => __( 'Events', 'text_domain' ),
    		'name_admin_bar'        => __( 'Event', 'text_domain' ),
    		'archives'              => __( 'Event Archives', 'text_domain' ),
    		'attributes'            => __( 'Event Attributes', 'text_domain' ),
    		'parent_item_colon'     => __( 'Parent Event:', 'text_domain' ),
    		'all_items'             => __( 'All Events', 'text_domain' ),
    		'add_new_item'          => __( 'Add New Event', 'text_domain' ),
    		'add_new'               => __( 'Add New', 'text_domain' ),
    		'new_item'              => __( 'New Event', 'text_domain' ),
    		'edit_item'             => __( 'Edit Event', 'text_domain' ),
    		'update_item'           => __( 'Update Event', 'text_domain' ),
    		'view_item'             => __( 'View Event', 'text_domain' ),
    		'view_items'            => __( 'View Events', 'text_domain' ),
    		'search_items'          => __( 'Search Event', 'text_domain' ),
    		'not_found'             => __( 'Not found', 'text_domain' ),
    		'not_found_in_trash'    => __( 'Not found in Trash', 'text_domain' ),
    		'featured_image'        => __( 'Featured Image', 'text_domain' ),
    		'set_featured_image'    => __( 'Set featured image', 'text_domain' ),
    		'remove_featured_image' => __( 'Remove featured image', 'text_domain' ),
    		'use_featured_image'    => __( 'Use as featured image', 'text_domain' ),
    		'insert_into_item'      => __( 'Insert into event', 'text_domain' ),
    		'uploaded_to_this_item' => __( 'Uploaded to this event', 'text_domain' ),
    		'items_list'            => __( 'Events list', 'text_domain' ),
    		'items_list_navigation' => __( 'Events list navigation', 'text_domain' ),
    		'filter_items_list'     => __( 'Filter items list', 'text_domain' ),
    	),
  		'supports'              => array( 'title', 'editor', 'thumbnail', 'excerpt'),
  		'hierarchical'          => false,
  		'public'                => true,
  		'show_ui'               => true,
  		'show_in_menu'          => true,
  		'menu_position'         => 5,
  		'menu_icon'             => 'dashicons-calendar',
  		'show_in_admin_bar'     => true,
  		'show_in_nav_menus'     => true,
  		'can_export'            => true,
  		'has_archive'           => true,
  		'exclude_from_search'   => false,
  		'publicly_queryable'    => true,
  		'capability_type'       => 'page',
  		'show_in_rest'          => true,
  		// 'rest_base'             => 'events',
  	);
    $sub_event_args = array(
      'label'                 => __( 'Sub Event', 'text_domain' ),
      'description'           => __( 'Sub Events', 'text_domain' ),
      'labels'                => array(
        'name'                  => _x( 'Sub Events', 'Post Type General Name', 'text_domain' ),
        'singular_name'         => _x( 'Sub Event', 'Post Type Singular Name', 'text_domain' ),
        'menu_name'             => __( 'Sub Events', 'text_domain' ),
        'name_admin_bar'        => __( 'Sub Events', 'text_domain' ),
        'archives'              => __( 'Sub Event Archives', 'text_domain' ),
        'attributes'            => __( 'Sub Event Attributes', 'text_domain' ),
        'parent_item_colon'     => __( 'Parent Sub Event:', 'text_domain' ),
        'all_items'             => __( 'All Sub Events', 'text_domain' ),
        'add_new_item'          => __( 'Add New Sub Event', 'text_domain' ),
        'add_new'               => __( 'Add New', 'text_domain' ),
        'new_item'              => __( 'New Sub Event', 'text_domain' ),
        'edit_item'             => __( 'Edit Sub Event', 'text_domain' ),
        'update_item'           => __( 'Update Sub Event', 'text_domain' ),
        'view_item'             => __( 'View Sub Event', 'text_domain' ),
        'view_items'            => __( 'View Sub Events', 'text_domain' ),
        'search_items'          => __( 'Search Sub Event', 'text_domain' ),
        'not_found'             => __( 'Not found', 'text_domain' ),
        'not_found_in_trash'    => __( 'Not found in Trash', 'text_domain' ),
        'featured_image'        => __( 'Featured Image', 'text_domain' ),
        'set_featured_image'    => __( 'Set featured image', 'text_domain' ),
        'remove_featured_image' => __( 'Remove featured image', 'text_domain' ),
        'use_featured_image'    => __( 'Use as featured image', 'text_domain' ),
        'insert_into_item'      => __( 'Insert into event', 'text_domain' ),
        'uploaded_to_this_item' => __( 'Uploaded to this event', 'text_domain' ),
        'items_list'            => __( 'Sub Events list', 'text_domain' ),
        'items_list_navigation' => __( 'Sub Events list navigation', 'text_domain' ),
        'filter_items_list'     => __( 'Filter items list', 'text_domain' ),
      ),
      'supports'              => array(),
      'hierarchical'          => false,
      'public'                => false,
      // 'show_ui'               => true,
      // 'show_in_menu'          => true,
      'show_ui'               => false,
      'show_in_menu'          => false,
      'menu_position'         => 5,
      'menu_icon'             => 'dashicons-calendar',
      'show_in_admin_bar'     => false,
      'show_in_nav_menus'     => false,
      'can_export'            => true,
      'has_archive'           => false,
      'exclude_from_search'   => true,
      'publicly_queryable'    => true,
      'capability_type'       => 'page',
      'show_in_rest'          => false,
      // 'rest_base'             => 'events',
  	);
    register_post_type( 'sub_event', $sub_event_args );
  	register_post_type( 'events', $event_args );




    $cat_labels = array(
      'name'                       => _x( 'Event Categories', 'Taxonomy General Name', 'text_domain' ),
      'singular_name'              => _x( 'Event Category', 'Taxonomy Singular Name', 'text_domain' ),
      'menu_name'                  => __( 'Event Categories', 'text_domain' ),
      'all_items'                  => __( 'All Categories', 'text_domain' ),
      'parent_item'                => __( 'Parent Category', 'text_domain' ),
      'parent_item_colon'          => __( 'Parent Category:', 'text_domain' ),
      'new_item_name'              => __( 'New Category Name', 'text_domain' ),
      'add_new_item'               => __( 'Add New Category', 'text_domain' ),
      'edit_item'                  => __( 'Edit Category', 'text_domain' ),
      'update_item'                => __( 'Update Category', 'text_domain' ),
      'view_item'                  => __( 'View Category', 'text_domain' ),
      'separate_items_with_commas' => __( 'Separate categories with commas', 'text_domain' ),
      'add_or_remove_items'        => __( 'Add or remove categories', 'text_domain' ),
      'choose_from_most_used'      => __( 'Choose from the most used', 'text_domain' ),
      'popular_items'              => __( 'Popular Categories', 'text_domain' ),
      'search_items'               => __( 'Search Categories', 'text_domain' ),
      'not_found'                  => __( 'Not Found', 'text_domain' ),
      'no_terms'                   => __( 'No categories', 'text_domain' ),
      'items_list'                 => __( 'Categories list', 'text_domain' ),
      'items_list_navigation'      => __( 'Categories list navigation', 'text_domain' ),
    );
    $cat_args = array(
      'labels'                     => $cat_labels,
      'hierarchical'               => true,
      'public'                     => true,
      'show_ui'                    => true,
      'show_in_rest'               => true,
      'show_admin_column'          => true,
      'show_in_nav_menus'          => true,
      'show_tagcloud'              => true,
    );
    register_taxonomy( 'event_category', array( 'events', 'sub_event' ), $cat_args );




  }
}

new mindEventsCPTS();
