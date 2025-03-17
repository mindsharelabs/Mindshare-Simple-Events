<?php

if (function_exists('add_theme_support')) {
    add_image_size('cal-thumb', 100, 100, array('center', 'center'));
}


add_filter( 'get_the_archive_title', function ( $title ) {
	
    //if is events post type
	if ( is_post_type_archive( 'events' ) ) {
		$title = 'Events @ Make Santa Fe';
	}

    return $title;

});



add_action( 'acf/include_fields', function() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group( array(
	'key' => 'group_67c0a96120be0',
	'title' => 'TAX: Event Category Options',
	'fields' => array(
		array(
			'key' => 'field_67c0a9612c97f',
			'label' => 'Event Color',
			'name' => 'event_color',
			'aria-label' => '',
			'type' => 'color_picker',
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => array(
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'default_value' => '#23B38C',
			'enable_opacity' => 0,
			'return_format' => 'string',
			'allow_in_bindings' => 0,
		),
	),
	'location' => array(
		array(
			array(
				'param' => 'taxonomy',
				'operator' => '==',
				'value' => 'event_category',
			),
		),
	),
	'menu_order' => 0,
	'position' => 'normal',
	'style' => 'default',
	'label_placement' => 'top',
	'instruction_placement' => 'label',
	'hide_on_screen' => '',
	'active' => true,
	'description' => '',
	'show_in_rest' => 0,
) );
} );

