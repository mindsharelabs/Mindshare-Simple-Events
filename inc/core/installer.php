<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Roles and capabilities.
 *
 * Activation hooks do not run when a plugin is updated, so the installer
 * also runs whenever the stored schema version differs from VERSION. Bump
 * VERSION whenever the roles or capabilities below change.
 */
class mindEventsInstaller {
    const VERSION = 1;
    const VERSION_OPTION = 'mindevents_schema_version';
    const MANAGER_ROLE = 'mindevents_manager';

    public static function maybe_install() {
        if ((int) get_option(self::VERSION_OPTION) !== self::VERSION) {
            self::install();
        }
    }

    public static function install() {
        $event_caps    = self::event_capabilities();
        $category_caps = array('manage_mindevents_categories');
        $settings_caps = array('manage_mindevents_settings');

        self::grant('administrator', array_merge($event_caps, $category_caps, $settings_caps));
        self::grant('editor', array_merge($event_caps, $category_caps));

        remove_role(self::MANAGER_ROLE);
        add_role(
            self::MANAGER_ROLE,
            __('Event Manager', 'simple-events'),
            array_fill_keys(array_merge(array('read', 'upload_files'), $event_caps, $category_caps, $settings_caps), true)
        );

        update_option(self::VERSION_OPTION, self::VERSION);
    }

    /**
     * Remove everything install() added. Called on uninstall.
     */
    public static function uninstall() {
        $caps = array_merge(self::event_capabilities(), array('manage_mindevents_categories', 'manage_mindevents_settings'));

        foreach (wp_roles()->role_objects as $role) {
            foreach ($caps as $cap) {
                $role->remove_cap($cap);
            }
        }

        remove_role(self::MANAGER_ROLE);
        delete_option(self::VERSION_OPTION);
    }

    /**
     * The primitive capabilities of the mindevents_event capability type,
     * which events and occurrences share.
     */
    public static function event_capabilities() {
        return array(
            'edit_mindevents_events',
            'edit_others_mindevents_events',
            'edit_published_mindevents_events',
            'edit_private_mindevents_events',
            'publish_mindevents_events',
            'read_private_mindevents_events',
            'delete_mindevents_events',
            'delete_others_mindevents_events',
            'delete_published_mindevents_events',
            'delete_private_mindevents_events',
        );
    }

    private static function grant($role_name, $caps) {
        $role = get_role($role_name);
        if (!$role) {
            return;
        }

        foreach ($caps as $cap) {
            $role->add_cap($cap);
        }
    }
}
