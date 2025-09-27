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



add_action( 'template_redirect', 'redirect_child_cpt_to_parent' );

function redirect_child_cpt_to_parent() {
    // Replace 'your_custom_post_type' with the actual slug of your custom post type
    $target_post_type = 'sub_event'; 

    if ( is_singular( $target_post_type ) ) {
        global $post;

        // Check if the current post has a parent
        if ( $post->post_parent ) {
            // Get the permalink of the parent post
            $parent_permalink = get_permalink( $post->post_parent );

            // If a parent permalink exists, redirect to it
            if ( $parent_permalink ) {
                wp_redirect( $parent_permalink, 301 ); // 301 for permanent redirect
                exit;
            }
        }
    }
}



add_action('add_meta_boxes', function() {
    add_meta_box(
        'sub_event_post_info',
        'Sub Event Post Information',
        'output_sub_event_post_info',
        'sub_event',
        'normal',
        'default'
    );
	add_meta_box(
        'sub_event_custom_meta',
        'Sub Event Custom Meta',
        'output_sub_event_custom_meta_table',
        'sub_event',
        'normal',
        'default'
    );
	
});

function output_sub_event_custom_meta_table($post) {
    $meta = get_post_meta($post->ID);
    if (empty($meta)) {
        echo '<p>No custom meta found for this sub_event.</p>';
        return;
    }
    echo '<table class="widefat striped">';
    echo '<thead><tr><th>Meta Key</th><th>Meta Value</th></tr></thead><tbody>';
    foreach ($meta as $key => $values) {
        // Only show custom meta (skip WordPress internals)
        if (strpos($key, '_') === 0) continue;
        foreach ($values as $value) {
            echo '<tr>';
            echo '<td>' . esc_html($key) . '</td>';
            echo '<td>' . esc_html($value) . '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table>';
}


function output_sub_event_post_info($post) {
    echo '<table class="widefat striped">';
    echo '<thead><tr><th>Info</th><th>Value</th></tr></thead><tbody>';

    // Parent post
    if ($post->post_parent) {
        $parent = get_post($post->post_parent);
        echo '<tr><td>Parent Post</td><td><a href="' . esc_url(get_edit_post_link($parent->ID)) . '">' . esc_html($parent->post_title) . ' (ID: ' . $parent->ID . ')</a></td></tr>';
    } else {
        echo '<tr><td>Parent Post</td><td>None</td></tr>';
    }

    // Date published
    echo '<tr><td>Date Published</td><td>' . esc_html(get_the_date('Y-m-d H:i:s', $post)) . '</td></tr>';

    // Author
    $author = get_userdata($post->post_author);
	if($author) :
    echo '<tr><td>Author</td><td>' . esc_html($author ? $author->display_name : 'Unknown') . '</td></tr>';
	else : 
		$parent_author = get_userdata($parent->post_author);
		echo '<tr><td>Author</td><td>Inherited from Parent: ' . esc_html($parent_author ? $parent_author->display_name : 'Unknown') . '</td></tr>';
	endif;

    // Post status
    echo '<tr><td>Status</td><td>' . esc_html($post->post_status) . '</td></tr>';

    // Post ID
    echo '<tr><td>Post ID</td><td>' . esc_html($post->ID) . '</td></tr>';

    echo '</tbody></table>';
}