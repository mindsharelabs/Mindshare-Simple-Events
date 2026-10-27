<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Moves events from the plugin's 1.x data model to this one.
 *
 * 1.x stored events as `events`, their dates as `sub_event` with local
 * times in `event_start_time_stamp` and `event_end_time_stamp`, and
 * categories as `event_category`. The post types and taxonomy are renamed
 * in place, so IDs, slugs, URLs and other plugins' meta carry over, and
 * each date's times are converted to UTC. The 1.x meta is left in place.
 *
 * Run it from Tools > Events Migration, which a notice links to while 1.x
 * data is found, or with `wp mindevents migrate`.
 */
class mindEventsMigration {
    const DONE_OPTION = 'mindevents_migration_completed';
    const REPORT_KEY  = 'mindevents_migration_report';
    const PAGE        = 'mindevents-migration';

    const OLD_EVENT      = 'events';
    const OLD_OCCURRENCE = 'sub_event';
    const OLD_TAXONOMY   = 'event_category';

    public function __construct() {
        add_action('admin_menu', array($this, 'register_page'));
        add_action('admin_notices', array($this, 'render_notice'));
        add_action('admin_post_mindevents_migrate', array($this, 'handle_run'));
    }

    /**
     * Whether 1.x data is waiting to be migrated.
     */
    public static function has_legacy_data() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the old types are not registered, so no API can find their posts.
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$wpdb->posts} WHERE post_type IN (%s, %s) AND post_status <> 'trash' LIMIT 1",
            self::OLD_EVENT,
            self::OLD_OCCURRENCE
        ));
    }

    /**
     * Why the migration cannot run, or an empty string if it can.
     *
     * While anything still registers the old names, the posts are not
     * abandoned 1.x data: the old plugin is still active, or another plugin
     * uses the same names for its own events.
     */
    public static function blocker() {
        foreach (array(self::OLD_EVENT, self::OLD_OCCURRENCE) as $post_type) {
            if (post_type_exists($post_type)) {
                /* translators: %s: post type name */
                return sprintf(__('Something on this site still registers the "%s" post type. Deactivate the old events plugin, or whatever else registers it, before migrating.', 'simple-events'), $post_type);
            }
        }

        if (taxonomy_exists(self::OLD_TAXONOMY)) {
            /* translators: %s: taxonomy name */
            return sprintf(__('Something on this site still registers the "%s" taxonomy. Deactivate the old events plugin, or whatever else registers it, before migrating.', 'simple-events'), self::OLD_TAXONOMY);
        }

        return '';
    }

    /**
     * What is left to migrate, and what migrating would do. Reads only.
     */
    public static function survey() {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery -- the old types are not registered, so no API can count them.
        $survey = self::empty_report();
        $survey['events']     = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", self::OLD_EVENT));
        $survey['categories'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", self::OLD_TAXONOMY));
        $survey['trashed']    = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'trash'", self::OLD_OCCURRENCE));
        // phpcs:enable
        $survey['acf'] = self::update_acf(false);

        foreach (self::old_occurrence_ids() as $id) {
            self::tally($survey, $id, self::convert_times($id, array(self::OLD_EVENT, 'mind_events')));
        }

        return $survey;
    }

    /**
     * Migrate everything. Safe to run again: it only finds what is left.
     */
    public static function run() {
        global $wpdb;

        if (function_exists('set_time_limit')) {
            set_time_limit(0); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- hundreds of occurrences can outlast the default limit.
        }

        $report = self::empty_report();

        // phpcs:disable WordPress.DB.DirectDatabaseQuery -- renaming in place is the point: IDs, and so every reference to them, are kept.
        $report['categories'] = (int) $wpdb->update($wpdb->term_taxonomy, array('taxonomy' => 'mind_event_category'), array('taxonomy' => self::OLD_TAXONOMY));
        $report['events']     = (int) $wpdb->update($wpdb->posts, array('post_type' => 'mind_events'), array('post_type' => self::OLD_EVENT));
        // Yoast SEO keys an event's primary category by taxonomy name.
        $wpdb->update($wpdb->postmeta, array('meta_key' => '_yoast_wpseo_primary_mind_event_category'), array('meta_key' => '_yoast_wpseo_primary_' . self::OLD_TAXONOMY));
        // phpcs:enable
        wp_cache_flush();

        $event_ids = array();
        foreach (self::old_occurrence_ids() as $id) {
            $times = self::convert_times($id, array('mind_events'));

            if (!is_wp_error($times)) {
                self::save_occurrence($id, $times);
                $event_ids[$times['event_id']] = true;
            }

            self::tally($report, $id, $times);
        }

        foreach (array_keys($event_ids) as $event_id) {
            mindevents_sync_event_taxonomies_to_children($event_id);
            mindevents_sync_event_date_range($event_id);
        }

        // Term counts were kept for the old post types.
        $term_taxonomy_ids = get_terms(array('taxonomy' => 'mind_event_category', 'hide_empty' => false, 'fields' => 'tt_ids'));
        if (!is_wp_error($term_taxonomy_ids)) {
            wp_update_term_count_now($term_taxonomy_ids, 'mind_event_category');
        }

        $report['acf'] = self::update_acf(true);

        flush_rewrite_rules();
        update_option(self::DONE_OPTION, time(), false);

        return $report;
    }

    private static function empty_report() {
        return array(
            'events'      => 0,
            'occurrences' => 0,
            'categories'  => 0,
            'acf'         => 0,
            'trashed'     => 0,
            'clamped'     => array(),
            'skipped'     => array(),
        );
    }

    private static function tally(array &$report, $id, $times) {
        if (is_wp_error($times)) {
            $report['skipped'][$id] = $times->get_error_message();
            return;
        }

        $report['occurrences']++;
        if ($times['clamped']) {
            $report['clamped'][] = $id;
        }
    }

    /**
     * Old occurrences still to migrate. Trashed ones are left in the trash,
     * which removes them when it is emptied.
     */
    private static function old_occurrence_ids() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the old type is not registered, so no API can find its posts.
        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status <> 'trash' ORDER BY ID",
            self::OLD_OCCURRENCE
        )));
    }

    /**
     * An old occurrence's start and end, from its local-time stamps.
     *
     * 1.x saved a missing end time, or one before the start, as given. This
     * plugin reads an earlier end as the next day, which would stretch a
     * 4 PM date with no end time to midnight, so those end when they start.
     *
     * @param string[] $event_types Post types its event may have.
     * @return array|WP_Error
     */
    private static function convert_times($id, array $event_types) {
        $event_id = (int) wp_get_post_parent_id($id);

        if (!$event_id || !in_array(get_post_type($event_id), $event_types, true)) {
            return new WP_Error('mindevents_no_event', __('Its event no longer exists.', 'simple-events'));
        }

        $start = self::local_time(get_post_meta($id, 'event_start_time_stamp', true));
        $end   = self::local_time(get_post_meta($id, 'event_end_time_stamp', true));

        if (!$start) {
            return new WP_Error('mindevents_no_start', __('It has no valid start time.', 'simple-events'));
        }

        $clamped = !$end || $end < $start;

        return array(
            'event_id' => $event_id,
            'start'    => $start,
            'end'      => $clamped ? $start : $end,
            'clamped'  => $clamped,
        );
    }

    private static function local_time($value) {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $time = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, wp_timezone());

        return ($time && $time->format('Y-m-d H:i:s') === $value) ? $time : null;
    }

    /**
     * Store the times, and the title and status the occurrence would have
     * been created with. eventColor and eventDescription are already under
     * the keys this plugin reads.
     */
    private static function save_occurrence($id, array $times) {
        global $wpdb;

        update_post_meta($id, 'mindevents_start_utc', mindevents_to_utc($times['start']));
        update_post_meta($id, 'mindevents_end_utc', mindevents_to_utc($times['end']));

        $title = mindevents_get_plain_title($times['event_id']) . ' | ' . $times['start']->format('Y-m-d') . ' | ' . $times['start']->format('H:i') . '-' . $times['end']->format('H:i');

        // Written directly, as wp_update_post() would run the save hooks
        // against an occurrence that is only half converted.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- see above; the cache is cleaned below.
        $wpdb->update($wpdb->posts, array(
            'post_type'   => 'mind_sub_event',
            'post_status' => mindevents_occurrence_status(get_post_status($times['event_id'])),
            'post_title'  => apply_filters('mindevents_occurrence_title', $title, $times['start'], $times['end'], $times['event_id']),
        ), array('ID' => $id));

        clean_post_cache($id);
    }

    /**
     * Point Advanced Custom Fields location rules and taxonomy fields at
     * the new names. ACF stores both in post_content.
     *
     * @return int How many field groups and fields need, or got, the change.
     */
    private static function update_acf($write) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- ACF may not be active; its settings are read raw either way.
        $rows = $wpdb->get_results("SELECT ID, post_content FROM {$wpdb->posts} WHERE post_type IN ('acf-field-group', 'acf-field') AND post_status <> 'trash'");

        $changed = 0;
        foreach ($rows as $row) {
            $settings = maybe_unserialize($row->post_content);
            if (!is_array($settings)) {
                continue;
            }

            $updated = self::rename_in_acf_settings($settings);
            if ($updated === $settings) {
                continue;
            }

            $changed++;
            if ($write) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- as above; the cache is cleaned below.
                $wpdb->update($wpdb->posts, array('post_content' => maybe_serialize($updated)), array('ID' => $row->ID));
                clean_post_cache((int) $row->ID);
            }
        }

        return $changed;
    }

    private static function rename_in_acf_settings(array $settings) {
        if (($settings['taxonomy'] ?? '') === self::OLD_TAXONOMY) {
            $settings['taxonomy'] = 'mind_event_category';
        }

        if (empty($settings['location']) || !is_array($settings['location'])) {
            return $settings;
        }

        foreach ($settings['location'] as $g => $group) {
            foreach ((array) $group as $r => $rule) {
                $param = $rule['param'] ?? '';
                $value = (string) ($rule['value'] ?? '');

                if ($param === 'post_type' && $value === self::OLD_EVENT) {
                    $settings['location'][$g][$r]['value'] = 'mind_events';
                } elseif ($param === 'taxonomy' && $value === self::OLD_TAXONOMY) {
                    $settings['location'][$g][$r]['value'] = 'mind_event_category';
                } elseif ($param === 'post_taxonomy' && strpos($value, self::OLD_TAXONOMY . ':') === 0) {
                    $settings['location'][$g][$r]['value'] = 'mind_event_category' . substr($value, strlen(self::OLD_TAXONOMY));
                }
            }
        }

        return $settings;
    }

    /**
     * The page is listed while there is something to migrate, or a report
     * of the migration just run to show.
     */
    public function register_page() {
        if (!self::has_legacy_data() && !get_transient(self::REPORT_KEY)) {
            return;
        }

        add_management_page(
            __('Events Migration', 'simple-events'),
            __('Events Migration', 'simple-events'),
            'manage_options',
            self::PAGE,
            array($this, 'render_page')
        );
    }

    public function render_notice() {
        if (!current_user_can('manage_options') || self::blocker() || !self::has_legacy_data()) {
            return;
        }

        $screen = get_current_screen();
        if ($screen && $screen->id === 'tools_page_' . self::PAGE) {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
            esc_html__('Events from an earlier version of Mindshare Simple Events were found. They will not show until they are migrated.', 'simple-events'),
            esc_url(admin_url('tools.php?page=' . self::PAGE)),
            esc_html__('Review and migrate them', 'simple-events')
        );
    }

    public function handle_run() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to do this.', 'simple-events'));
        }

        check_admin_referer('mindevents_migrate');

        if (!self::blocker()) {
            set_transient(self::REPORT_KEY, self::run(), HOUR_IN_SECONDS);
        }

        wp_safe_redirect(admin_url('tools.php?page=' . self::PAGE));
        exit;
    }

    public function render_page() {
        $report = get_transient(self::REPORT_KEY);
        delete_transient(self::REPORT_KEY);

        echo '<div class="wrap"><h1>' . esc_html__('Events Migration', 'simple-events') . '</h1>';

        if ($report) {
            echo '<div class="notice notice-success"><p><strong>' . esc_html__('Migration finished.', 'simple-events') . '</strong></p>';
            self::render_counts($report);
            echo '</div>';
        }

        if (!self::has_legacy_data()) {
            echo '<p>' . esc_html__('Nothing is left to migrate.', 'simple-events') . '</p></div>';
            return;
        }

        $blocker = self::blocker();
        if ($blocker) {
            echo '<div class="notice notice-error"><p>' . esc_html($blocker) . '</p></div></div>';
            return;
        }

        echo '<p>' . esc_html__('These events, dates and categories are from an earlier version of Mindshare Simple Events. Migrating them makes them show on the site again, at the same URLs.', 'simple-events') . '</p>';
        echo '<h2>' . esc_html__('To migrate', 'simple-events') . '</h2>';
        self::render_counts(self::survey());

        echo '<p><strong>' . esc_html__('Back up the database first.', 'simple-events') . '</strong> ';
        echo esc_html__('The post types and the category taxonomy are renamed in place. Theme or plugin code that queries the old names (events, sub_event, event_category, first_event_date, last_event_date) will need updating.', 'simple-events') . '</p>';
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="mindevents_migrate">
            <?php wp_nonce_field('mindevents_migrate'); ?>
            <?php submit_button(__('Migrate Events', 'simple-events'), 'primary', 'submit', false); ?>
        </form>
        </div>
        <?php
    }

    private static function render_counts(array $counts) {
        $lines = array(
            /* translators: %d: number of events */
            sprintf(_n('%d event', '%d events', $counts['events'], 'simple-events'), $counts['events']),
            /* translators: %d: number of occurrences */
            sprintf(_n('%d occurrence', '%d occurrences', $counts['occurrences'], 'simple-events'), $counts['occurrences']),
            /* translators: %d: number of categories */
            sprintf(_n('%d event category', '%d event categories', $counts['categories'], 'simple-events'), $counts['categories']),
            /* translators: %d: number of Advanced Custom Fields field groups and fields */
            sprintf(_n('%d ACF field group or field that uses the old names', '%d ACF field groups or fields that use the old names', $counts['acf'], 'simple-events'), $counts['acf']),
        );

        if ($counts['trashed']) {
            /* translators: %d: number of occurrences */
            $lines[] = sprintf(_n('%d trashed occurrence, left in the trash', '%d trashed occurrences, left in the trash', $counts['trashed'], 'simple-events'), $counts['trashed']);
        }

        echo '<ul class="ul-disc">';
        foreach ($lines as $line) {
            echo '<li>' . esc_html($line) . '</li>';
        }
        echo '</ul>';

        if ($counts['clamped']) {
            echo '<p>' . esc_html(sprintf(
                /* translators: 1: number of occurrences, 2: comma-separated occurrence IDs */
                _n(
                    '%1$d occurrence has no end time, or one before its start, so it ends when it starts: %2$s',
                    '%1$d occurrences have no end time, or one before their start, so they end when they start: %2$s',
                    count($counts['clamped']),
                    'simple-events'
                ),
                count($counts['clamped']),
                implode(', ', $counts['clamped'])
            )) . '</p>';
        }

        if ($counts['skipped']) {
            echo '<p>' . esc_html__('These occurrences cannot be migrated and are left as they are:', 'simple-events') . '</p><ul class="ul-disc">';
            foreach ($counts['skipped'] as $id => $reason) {
                echo '<li>' . esc_html('#' . $id . ': ' . $reason) . '</li>';
            }
            echo '</ul>';
        }
    }

    /**
     * Migrate events from an earlier version of Mindshare Simple Events.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Show what would be migrated, without changing anything.
     *
     * @when after_wp_load
     */
    public static function cli($args, $assoc_args) {
        $blocker = self::blocker();
        if ($blocker) {
            WP_CLI::error($blocker);
        }

        $dry_run = !empty($assoc_args['dry-run']);
        $counts  = $dry_run ? self::survey() : self::run();

        foreach (array('events', 'occurrences', 'categories', 'acf', 'trashed') as $key) {
            WP_CLI::log(sprintf('%-12s %d', $key, $counts[$key]));
        }
        if ($counts['clamped']) {
            WP_CLI::log('Ends when it starts: ' . implode(', ', $counts['clamped']));
        }
        foreach ($counts['skipped'] as $id => $reason) {
            WP_CLI::warning("Skipped #{$id}: {$reason}");
        }

        WP_CLI::success($dry_run ? 'Dry run: nothing was changed.' : 'Migration finished.');
    }
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('mindevents migrate', array('mindEventsMigration', 'cli'));
}
