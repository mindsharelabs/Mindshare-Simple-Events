<?php

if (!defined('ABSPATH')) {
    exit;
}

class mindEventsCPTS {
    public function __construct() {
        add_action('init', array($this, 'create_post_types'));
        add_action('event_category_add_form_fields', array($this, 'render_add_category_fields'));
        add_action('event_category_edit_form_fields', array($this, 'render_edit_category_fields'));
        add_action('created_event_category', array($this, 'save_category_fields'));
        add_action('edited_event_category', array($this, 'save_category_fields'));
    }

    public function create_post_types() {
        register_post_type('events', array(
            'label'               => __('Events', 'simple-events'),
            'description'         => __('Events', 'simple-events'),
            'labels'              => array(
                'name'          => _x('Events', 'Post Type General Name', 'simple-events'),
                'singular_name' => _x('Event', 'Post Type Singular Name', 'simple-events'),
                'menu_name'     => __('Events', 'simple-events'),
                'add_new_item'  => __('Add New Event', 'simple-events'),
                'edit_item'     => __('Edit Event', 'simple-events'),
                'new_item'      => __('New Event', 'simple-events'),
                'view_item'     => __('View Event', 'simple-events'),
                'search_items'  => __('Search Events', 'simple-events'),
                'all_items'     => __('All Events', 'simple-events'),
            ),
            'supports'            => array('title', 'editor', 'thumbnail', 'excerpt'),
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_position'       => 6,
            'menu_icon'           => 'dashicons-calendar',
            'show_in_rest'        => true,
            'has_archive'         => true,
            'publicly_queryable'  => true,
            'exclude_from_search' => false,
            'can_export'          => true,
            'capability_type'     => 'page',
        ));

        register_post_type('sub_event', array(
            'label'               => __('Occurrences', 'simple-events'),
            'description'         => __('Event occurrences', 'simple-events'),
            'labels'              => array(
                'name'          => _x('Occurrences', 'Post Type General Name', 'simple-events'),
                'singular_name' => _x('Occurrence', 'Post Type Singular Name', 'simple-events'),
                'menu_name'     => __('Occurrences', 'simple-events'),
                'add_new_item'  => __('Add New Occurrence', 'simple-events'),
                'edit_item'     => __('Edit Occurrence', 'simple-events'),
                'new_item'      => __('New Occurrence', 'simple-events'),
                'view_item'     => __('View Occurrence', 'simple-events'),
                'search_items'  => __('Search Occurrences', 'simple-events'),
                'all_items'     => __('All Occurrences', 'simple-events'),
            ),
            'supports'            => false,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => 'edit.php?post_type=events',
            'show_in_rest'        => false,
            'has_archive'         => false,
            'exclude_from_search' => true,
            'publicly_queryable'  => true,
            'can_export'          => true,
            'capability_type'     => 'page',
        ));

        register_taxonomy('event_category', array('events', 'sub_event'), array(
            'labels'            => array(
                'name'          => _x('Event Categories', 'Taxonomy General Name', 'simple-events'),
                'singular_name' => _x('Event Category', 'Taxonomy Singular Name', 'simple-events'),
                'menu_name'     => __('Event Categories', 'simple-events'),
                'all_items'     => __('All Categories', 'simple-events'),
                'add_new_item'  => __('Add New Category', 'simple-events'),
                'edit_item'     => __('Edit Category', 'simple-events'),
                'view_item'     => __('View Category', 'simple-events'),
            ),
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'show_in_nav_menus' => true,
            'show_tagcloud'     => true,
        ));

        $shared_string_meta = array(
            'mindevents_location',
            'mindevents_visibility',
            'mindevents_organizer_name',
            'mindevents_organizer_title',
        );

        foreach (array('events', 'sub_event') as $post_type) {
            foreach ($shared_string_meta as $meta_key) {
                register_post_meta($post_type, $meta_key, array(
                    'show_in_rest' => true,
                    'single'       => true,
                    'type'         => 'string',
                ));
            }

            register_post_meta($post_type, 'mindevents_organizer_image_id', array(
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'integer',
            ));
        }

        register_term_meta('event_category', 'mindevents_category_color', array(
            'show_in_rest' => true,
            'single'       => true,
            'type'         => 'string',
        ));
    }

    public function render_add_category_fields() {
        ?>
        <div class="form-field term-mindevents-category-color-wrap">
            <label for="mindevents_category_color"><?php esc_html_e('Category Color', 'simple-events'); ?></label>
            <input type="color" name="mindevents_category_color" id="mindevents_category_color" value="#2d7ff9">
            <p><?php esc_html_e('Used for calendar and list color accents.', 'simple-events'); ?></p>
        </div>
        <?php
    }

    public function render_edit_category_fields($term) {
        $color = get_term_meta($term->term_id, 'mindevents_category_color', true);
        if (!$color) {
            $color = '#2d7ff9';
        }
        ?>
        <tr class="form-field term-mindevents-category-color-wrap">
            <th scope="row">
                <label for="mindevents_category_color"><?php esc_html_e('Category Color', 'simple-events'); ?></label>
            </th>
            <td>
                <input type="color" name="mindevents_category_color" id="mindevents_category_color" value="<?php echo esc_attr($color); ?>">
                <p class="description"><?php esc_html_e('Used for calendar and list color accents.', 'simple-events'); ?></p>
            </td>
        </tr>
        <?php
    }

    public function save_category_fields($term_id) {
        if (!current_user_can('manage_categories')) {
            return;
        }

        $color = isset($_POST['mindevents_category_color']) ? sanitize_hex_color(wp_unslash($_POST['mindevents_category_color'])) : '';

        if ($color) {
            update_term_meta($term_id, 'mindevents_category_color', $color);
        } else {
            delete_term_meta($term_id, 'mindevents_category_color');
        }
    }
}
