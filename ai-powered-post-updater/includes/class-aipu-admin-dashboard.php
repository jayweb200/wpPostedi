<?php
/**
 * AI Powered Post Updater Admin Dashboard
 *
 * @package AIPU
 * @since   0.2.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class AIPU_Admin_Dashboard {

    const DASHBOARD_SETTINGS_GROUP = 'aipu_dashboard_settings_group';
    const DASHBOARD_SETTINGS_OPTION_KEY = 'aipu_dashboard_settings';
    const LOGS_ACTION_NONCE = 'aipu_logs_action_nonce';
    const BLC_RESCAN_NONCE = 'aipu_trigger_blc_rescan_nonce';


    private $dashboard_options;

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_dashboard_page' ] );
        add_action( 'admin_init', [ $this, 'register_dashboard_settings' ] );
        add_action( 'admin_post_aipu_clear_logs', [ $this, 'handle_clear_logs_action' ] );
        add_action( 'admin_post_aipu_trigger_blc_rescan', [ $this, 'handle_trigger_blc_rescan_action' ] );
        add_action( 'wp_ajax_aipu_edit_broken_link', [ $this, 'handle_ajax_edit_broken_link' ] );
        add_action( 'wp_ajax_aipu_remove_broken_link', [ $this, 'handle_ajax_remove_broken_link' ] );
        // add_action( 'wp_ajax_aipu_find_replacement_link', [ $this, 'handle_ajax_find_replacement_link' ] );
    }

    public function add_dashboard_page() {
        add_menu_page(
            __( 'AI Post Updater Dashboard', 'ai-powered-post-updater' ), // Page Title
            __( 'AI Updater', 'ai-powered-post-updater' ),               // Menu Title
            'manage_options',                                             // Capability
            'aipu-dashboard',                                             // Menu Slug
            [ $this, 'render_dashboard_page' ],                           // Callback function
            'dashicons-analytics',                                        // Icon URL
            75                                                            // Position
        );
    }

    public function render_dashboard_page() {
        $this->dashboard_options = get_option( self::DASHBOARD_SETTINGS_OPTION_KEY );
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'status';
        ?>
        <div class="wrap aipu-dashboard-wrap">
            <h1><?php _e( 'AI Powered Post Updater Dashboard', 'ai-powered-post-updater' ); ?></h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=aipu-dashboard&tab=status" class="nav-tab <?php echo $active_tab == 'status' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Status & Info', 'ai-powered-post-updater' ); ?></a>
                <a href="?page=aipu-dashboard&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Automation Settings', 'ai-powered-post-updater' ); ?></a>
                <a href="?page=aipu-dashboard&tab=broken_links" class="nav-tab <?php echo $active_tab == 'broken_links' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Broken Links', 'ai-powered-post-updater' ); ?></a>
                <a href="?page=aipu-dashboard&tab=logs" class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Logs', 'ai-powered-post-updater' ); ?></a>
            </h2>

            <?php if ( $active_tab == 'status' ) : ?>
                <div id="aipu-status" class="aipu-tab-content">
                    <h3><?php _e( 'Plugin Status', 'ai-powered-post-updater' ); ?></h3>
                    <p><?php _e( 'Plugin Version:', 'ai-powered-post-updater' ); ?> <?php echo AIPU_VERSION; ?></p>
                    <p><?php _e( 'This dashboard provides an overview of automated tasks, logs, and specific settings for automation features.', 'ai-powered-post-updater' ); ?></p>
                    <?php
                    // Display scheduled hooks (basic info)
                    echo '<h4>' . __('Currently Scheduled Core AIPU Tasks (via WP-Cron)', 'ai-powered-post-updater') . '</h4>';
                    $cron_jobs = _get_cron_array();
                    $aipu_hooks_found = false;
                    if (empty($cron_jobs)) {
                        echo '<p>' . __('No cron jobs scheduled by WordPress.', 'ai-powered-post-updater') . '</p>';
                    } else {
                        echo '<ul>';
                        foreach ($cron_jobs as $timestamp => $hooks) {
                            foreach ($hooks as $hook_name => $events) {
                                if (strpos($hook_name, 'aipu_') === 0) { // Check for plugin's hooks
                                    $aipu_hooks_found = true;
                                    foreach ($events as $event_details) {
                                        echo '<li><strong>' . esc_html($hook_name) . '</strong>: ' . __('Next run at', 'ai-powered-post-updater') . ' ' . get_date_from_gmt(date('Y-m-d H:i:s', $timestamp), 'Y-m-d H:i:s') . ' (' . esc_html($event_details['schedule']) . ')</li>';
                                    }
                                }
                            }
                        }
                        if (!$aipu_hooks_found) {
                            echo '<li>' . __('No specific AIPU tasks currently scheduled.', 'ai-powered-post-updater') . '</li>';
                        }
                        echo '</ul>';
                    }
                    ?>
                </div>
            <?php elseif ( $active_tab == 'settings' ) : ?>
                <div id="aipu-automation-settings" class="aipu-tab-content">
                    <form method="post" action="options.php">
                        <?php
                            settings_fields( self::DASHBOARD_SETTINGS_GROUP );
                            do_settings_sections( 'aipu-dashboard-settings-section' );
                            submit_button();
                        ?>
                    </form>
                </div>
            <?php elseif ( $active_tab == 'logs' ) : ?>
                <div id="aipu-logs" class="aipu-tab-content">
                    <h3><?php _e( 'Plugin Activity Logs', 'ai-powered-post-updater' ); ?></h3>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="aipu_clear_logs">
                        <?php wp_nonce_field( self::LOGS_ACTION_NONCE, '_aipu_nonce' ); ?>
                        <?php submit_button( __( 'Clear All Logs', 'ai-powered-post-updater' ), 'delete', 'clear_logs_button', false ); ?>
                    </form>
                    <div class="aipu-logs-container" style="max-height: 500px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; background: #fff; margin-top:10px;">
                        <?php
                        $logs = AIPU_Logger::get_logs(100); // Get latest 100 logs
                        if ( ! empty( $logs ) ) {
                            echo '<ul>';
                            foreach ( $logs as $log_entry ) {
                                $date = get_date_from_gmt( date( 'Y-m-d H:i:s', $log_entry['timestamp'] ), 'Y-m-d H:i:s' );
                                echo '<li><strong>[' . esc_html( $date ) . '] [' . esc_html( $log_entry['level'] ) . ']</strong>: ' . esc_html( $log_entry['message'] ) . '</li>';
                            }
                            echo '</ul>';
                        } else {
                            echo '<p>' . __( 'No log entries found.', 'ai-powered-post-updater' ) . '</p>';
                        }
                        ?>
                    </div>
                </div>
            <?php elseif ( $active_tab == 'broken_links' ) : ?>
                <div id="aipu-broken-links-report" class="aipu-tab-content">
                    <h3><?php _e( 'Broken Links Report', 'ai-powered-post-updater' ); ?></h3>

                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom: 20px;">
                        <input type="hidden" name="action" value="aipu_trigger_blc_rescan">
                        <?php wp_nonce_field( self::BLC_RESCAN_NONCE, '_aipu_blc_nonce' ); ?>
                        <?php submit_button( __( 'Manually Trigger Full Rescan', 'ai-powered-post-updater' ), 'secondary', 'trigger_rescan_button', false ); ?>
                        <p class="description"><?php _e('This will reset the scan progress and check all posts again during the next scheduled runs (or sooner if triggered).', 'ai-powered-post-updater'); ?></p>
                    </form>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th scope="col"><?php _e( 'Broken URL', 'ai-powered-post-updater' ); ?></th>
                                <th scope="col"><?php _e( 'Anchor Text', 'ai-powered-post-updater' ); ?></th>
                                <th scope="col"><?php _e( 'Status', 'ai-powered-post-updater' ); ?></th>
                                <th scope="col"><?php _e( 'Found In Post', 'ai-powered-post-updater' ); ?></th>
                                <th scope="col"><?php _e( 'Last Checked', 'ai-powered-post-updater' ); ?></th>
                                <th scope="col"><?php _e( 'Actions', 'ai-powered-post-updater' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Ensure AIPU_Broken_Link_Checker class is available
                            // This might be better handled by passing the constant from the BLC class if it's always loaded
                            // For now, assuming the constant is defined or we use the string directly.
                            $broken_links_meta_key = defined('AIPU_Broken_Link_Checker::POST_META_KEY_BROKEN_LINKS') ? AIPU_Broken_Link_Checker::POST_META_KEY_BROKEN_LINKS : '_aipu_broken_links_data';

                            $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
                            $args = [
                                'post_type'      => 'post',
                                'post_status'    => 'publish',
                                'posts_per_page' => 20,
                                'paged'          => $paged,
                                'meta_query'     => [
                                    [
                                        'key'     => $broken_links_meta_key,
                                        'compare' => 'EXISTS',
                                    ],
                                ],
                            ];
                            $query = new WP_Query( $args );
                            $found_broken_links = false;

                            if ( $query->have_posts() ) {
                                while ( $query->have_posts() ) {
                                    $query->the_post();
                                    $post_id = get_the_ID();
                                    $post_title = get_the_title();
                                    $edit_link = get_edit_post_link( $post_id );
                                    $broken_links_data = get_post_meta( $post_id, $broken_links_meta_key, true );

                                    if ( is_array( $broken_links_data ) && ! empty( $broken_links_data ) ) {
                                        $found_broken_links = true;
                                        foreach ( $broken_links_data as $link_data ) {
                                            ?>
                                            <tr>
                                                <td>
                                                    <a href="<?php echo esc_url( $link_data['url'] ); ?>" target="_blank" title="<?php echo esc_attr( $link_data['url'] ); ?>">
                                                        <?php echo esc_html( wp_trim_words( $link_data['url'], 10, '...' ) ); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo esc_html( wp_trim_words( $link_data['anchor'], 10, '...' ) ); ?></td>
                                                <td><?php echo esc_html( $link_data['status'] ); ?></td>
                                                <td><a href="<?php echo esc_url( $edit_link ); ?>"><?php echo esc_html( $post_title ); ?></a></td>
                                                <td><?php echo esc_html( get_date_from_gmt( date( 'Y-m-d H:i:s', $link_data['last_checked'] ), 'Y-m-d H:i:s' ) ); ?></td>
                                                <td>
                                                    <button type="button" class="button button-small button-edit-link"
                                                            data-postid="<?php echo esc_attr($post_id); ?>"
                                                            data-brokenurl="<?php echo esc_attr(base64_encode($link_data['url'])); ?>"
                                                            data-anchor="<?php echo esc_attr(base64_encode($link_data['anchor'])); ?>">
                                                        <?php _e('Edit', 'ai-powered-post-updater'); ?>
                                                    </button>
                                                    <button type="button" class="button button-small button-remove-link"
                                                            data-postid="<?php echo esc_attr($post_id); ?>"
                                                            data-brokenurl="<?php echo esc_attr(base64_encode($link_data['url'])); ?>"
                                                            data-anchor="<?php echo esc_attr(base64_encode($link_data['anchor'])); ?>">
                                                        <?php _e('Unlink', 'ai-powered-post-updater'); ?>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php
                                        }
                                    }
                                }
                                wp_reset_postdata();
                            }

                            if (!$found_broken_links) {
                                ?>
                                <tr>
                                    <td colspan="6"><?php _e( 'No broken links found in any posts yet, or all posts are pending scan.', 'ai-powered-post-updater' ); ?></td>
                                </tr>
                                <?php
                            }
                            ?>
                        </tbody>
                    </table>
                    <?php
                    $total_pages = $query->max_num_pages;
                    if ($total_pages > 1) {
                        $current_page = $paged;
                        echo '<div class="tablenav"><div class="tablenav-pages" style="margin: 1em 0;">' . paginate_links([
                            'base' => add_query_arg('paged', '%#%'), // admin_url('admin.php?page=aipu-dashboard&tab=broken_links&paged=%#%')
                            'format' => '',
                            'current' => $current_page,
                            'total' => $total_pages,
                            'prev_text' => '&laquo; ' . __('Previous'),
                            'next_text' => __('Next') . ' &raquo;',
                        ]) . '</div></div>';
                    }
                    ?>
                    <div id="aipu-edit-link-form-container" style="display:none; margin-top:20px; padding:15px; border:1px solid #ccc; background-color:#f9f9f9;">
                        <h4><?php _e('Edit Link', 'ai-powered-post-updater'); ?></h4>
                        <input type="hidden" id="edit-link-postid" value="">
                        <input type="hidden" id="edit-link-brokenurl-b64" value="">
                        <input type="hidden" id="edit-link-anchor-b64" value="">
                        <p>
                            <label for="edit-link-current-url"><?php _e('Current URL:', 'ai-powered-post-updater'); ?></label><br>
                            <input type="text" id="edit-link-current-url" readonly class="widefat">
                        </p>
                        <p>
                            <label for="edit-link-anchor-text"><?php _e('Anchor Text:', 'ai-powered-post-updater'); ?></label><br>
                            <input type="text" id="edit-link-anchor-text" readonly class="widefat">
                        </p>
                        <p>
                            <label for="edit-link-new-url"><?php _e('New URL:', 'ai-powered-post-updater'); ?></label><br>
                            <input type="url" id="edit-link-new-url" class="widefat">
                        </p>
                        <button type="button" id="aipu-save-edited-link" class="button button-primary"><?php _e('Save Changes', 'ai-powered-post-updater'); ?></button>
                        <button type="button" id="aipu-cancel-edit-link" class="button"><?php _e('Cancel', 'ai-powered-post-updater'); ?></button>
                        <div id="aipu-edit-link-feedback" style="margin-top:10px;"></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function register_dashboard_settings() {
        register_setting(
            self::DASHBOARD_SETTINGS_GROUP,
            self::DASHBOARD_SETTINGS_OPTION_KEY,
            [ $this, 'sanitize_dashboard_settings' ]
        );

        add_settings_section(
            'aipu_broken_link_checker_section',
            __( 'Broken Link Checker Settings', 'ai-powered-post-updater' ),
            null, // [ $this, 'print_section_info_callback' ],
            'aipu-dashboard-settings-section' // Page slug used in do_settings_sections
        );

        add_settings_field(
            'enable_broken_link_checker',
            __( 'Enable Broken Link Checker', 'ai-powered-post-updater' ),
            [ $this, 'checkbox_callback' ],
            'aipu-dashboard-settings-section',
            'aipu_broken_link_checker_section',
            [ 'id' => 'enable_broken_link_checker', 'label_for' => 'enable_broken_link_checker', 'option_key' => self::DASHBOARD_SETTINGS_OPTION_KEY ]
        );
         add_settings_field(
            'broken_link_check_frequency',
            __( 'Check Frequency', 'ai-powered-post-updater' ),
            [ $this, 'select_callback' ],
            'aipu-dashboard-settings-section',
            'aipu_broken_link_checker_section',
            [
                'id' => 'broken_link_check_frequency',
                'option_key' => self::DASHBOARD_SETTINGS_OPTION_KEY,
                'options' => $this->get_cron_schedules_for_select()
            ]
        );
        add_settings_field(
            'external_link_timeout',
            __( 'External Link Check Timeout (seconds)', 'ai-powered-post-updater' ),
            [ $this, 'number_callback' ],
            'aipu-dashboard-settings-section',
            'aipu_broken_link_checker_section',
            [ 'id' => 'external_link_timeout', 'option_key' => self::DASHBOARD_SETTINGS_OPTION_KEY, 'min' => 5, 'max' => 30, 'default' => 10 ]
        );

         // Placeholder for Automated Internal Linking settings section
         add_settings_section(
            'aipu_auto_internal_linking_section',
            __( 'Automated Internal Linking Settings', 'ai-powered-post-updater' ),
            null,
            'aipu-dashboard-settings-section'
        );
         add_settings_field(
            'enable_auto_internal_linking',
            __( 'Enable Automated Internal Linking', 'ai-powered-post-updater' ),
            [ $this, 'checkbox_callback' ],
            'aipu-dashboard-settings-section',
            'aipu_auto_internal_linking_section',
            [ 'id' => 'enable_auto_internal_linking', 'label_for' => 'enable_auto_internal_linking', 'option_key' => self::DASHBOARD_SETTINGS_OPTION_KEY ]
        );
        add_settings_field(
            'max_links_per_post',
            __( 'Max Links Per Post', 'ai-powered-post-updater' ),
            [ $this, 'number_callback' ],
            'aipu-dashboard-settings-section',
            'aipu_auto_internal_linking_section',
            [ 'id' => 'max_links_per_post', 'option_key' => self::DASHBOARD_SETTINGS_OPTION_KEY, 'min' => 0, 'max' => 10, 'default' => 3 ]
        );
        add_settings_field(
            'link_to_categories',
            __( 'Link to Posts in Categories (slugs, comma-sep)', 'ai-powered-post-updater' ),
            [ $this, 'text_callback' ], // Using a generic text_callback for now
            'aipu-dashboard-settings-section',
            'aipu_auto_internal_linking_section',
            [ 'id' => 'link_to_categories', 'option_key' => self::DASHBOARD_SETTINGS_OPTION_KEY, 'class' => 'regular-text' ]
        );
        add_settings_field(
            'link_to_tags',
            __( 'Link to Posts with Tags (slugs, comma-sep)', 'ai-powered-post-updater' ),
            [ $this, 'text_callback' ],
            'aipu-dashboard-settings-section',
            'aipu_auto_internal_linking_section',
            [ 'id' => 'link_to_tags', 'option_key' => self::DASHBOARD_SETTINGS_OPTION_KEY, 'class' => 'regular-text' ]
        );
        add_settings_field(
            'avoid_relinking_phrases',
            __( 'Avoid Relinking Already Linked Phrases', 'ai-powered-post-updater' ),
            [ $this, 'checkbox_callback' ],
            'aipu-dashboard-settings-section',
            'aipu_auto_internal_linking_section',
            [ 'id' => 'avoid_relinking_phrases', 'label_for' => 'avoid_relinking_phrases', 'option_key' => self::DASHBOARD_SETTINGS_OPTION_KEY ]
        );
        add_settings_field(
            'only_link_once_per_target_url',
            __( 'Only Link Once Per Target URL (in a single post)', 'ai-powered-post-updater' ),
            [ $this, 'checkbox_callback' ],
            'aipu-dashboard-settings-section',
            'aipu_auto_internal_linking_section',
            [ 'id' => 'only_link_once_per_target_url', 'label_for' => 'only_link_once_per_target_url', 'option_key' => self::DASHBOARD_SETTINGS_OPTION_KEY ]
        );

    }

    public function sanitize_dashboard_settings( $input ) {
        $sanitized_input = [];
        $defaults = $this->get_default_dashboard_settings();

        // Broken Link Checker
        $sanitized_input['enable_broken_link_checker'] = isset( $input['enable_broken_link_checker'] ) ? true : false;
        $schedules = array_keys($this->get_cron_schedules_for_select());
        $sanitized_input['broken_link_check_frequency'] = isset( $input['broken_link_check_frequency'] ) && in_array($input['broken_link_check_frequency'], $schedules) ? sanitize_key( $input['broken_link_check_frequency'] ) : $defaults['broken_link_check_frequency'];
        $sanitized_input['external_link_timeout'] = isset( $input['external_link_timeout'] ) ? intval( $input['external_link_timeout'] ) : $defaults['external_link_timeout'];
         if ($sanitized_input['external_link_timeout'] < 5 || $sanitized_input['external_link_timeout'] > 30) {
             $sanitized_input['external_link_timeout'] = $defaults['external_link_timeout'];
         }

        // Automated Internal Linking
        $sanitized_input['enable_auto_internal_linking'] = isset( $input['enable_auto_internal_linking'] ) ? true : false;
        $sanitized_input['max_links_per_post'] = isset( $input['max_links_per_post'] ) ? intval( $input['max_links_per_post'] ) : $defaults['max_links_per_post'];
        if ($sanitized_input['max_links_per_post'] < 0 || $sanitized_input['max_links_per_post'] > 10) { // Max 10 seems reasonable
            $sanitized_input['max_links_per_post'] = $defaults['max_links_per_post'];
        }
        $sanitized_input['link_to_categories'] = isset( $input['link_to_categories'] ) ? sanitize_text_field( $input['link_to_categories'] ) : '';
        $sanitized_input['link_to_tags'] = isset( $input['link_to_tags'] ) ? sanitize_text_field( $input['link_to_tags'] ) : '';
        $sanitized_input['avoid_relinking_phrases'] = isset( $input['avoid_relinking_phrases'] ) ? true : false;
        $sanitized_input['only_link_once_per_target_url'] = isset( $input['only_link_once_per_target_url'] ) ? true : false;


        return $sanitized_input;
    }

    private function get_default_dashboard_settings() {
         return [
             'enable_broken_link_checker' => false,
             'broken_link_check_frequency' => 'daily',
             'external_link_timeout' => 10,
             'enable_auto_internal_linking' => false,
             'max_links_per_post' => 3,
             'link_to_categories' => '',
             'link_to_tags' => '',
             'avoid_relinking_phrases' => true,
             'only_link_once_per_target_url' => true,
         ];
    }

    public function checkbox_callback( $args ) {
        // Ensure $this->dashboard_options is loaded if not already by render_dashboard_page
        if (null === $this->dashboard_options && function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen && $screen->id === 'toplevel_page_aipu-dashboard') { // Only load if on our dashboard page
                 $this->dashboard_options = get_option( self::DASHBOARD_SETTINGS_OPTION_KEY );
            }
        }
        $options = $this->dashboard_options ?: $this->get_default_dashboard_settings();
        $value = isset( $options[$args['id']] ) ? $options[$args['id']] : ($args['id'] === 'avoid_relinking_phrases' || $args['id'] === 'only_link_once_per_target_url' ? true : false); // Defaults for new checkboxes
        echo '<input type="checkbox" id="' . esc_attr( $args['id'] ) . '" name="' . esc_attr( $args['option_key'] ) . '[' . esc_attr( $args['id'] ) . ']" value="1" ' . checked( 1, $value, false ) . ' />';
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    public function number_callback( $args ) {
        if (null === $this->dashboard_options && function_exists('get_current_screen')) {
            $screen = get_current_screen();
             if ($screen && $screen->id === 'toplevel_page_aipu-dashboard') {
                $this->dashboard_options = get_option( self::DASHBOARD_SETTINGS_OPTION_KEY );
            }
        }
        $options = $this->dashboard_options ?: $this->get_default_dashboard_settings();
        $value = isset( $options[$args['id']] ) ? intval( $options[$args['id']] ) : intval($args['default']);
        $min = isset($args['min']) ? 'min="'.esc_attr($args['min']).'"' : '';
        $max = isset($args['max']) ? 'max="'.esc_attr($args['max']).'"' : '';
        echo '<input type="number" id="' . esc_attr( $args['id'] ) . '" name="' . esc_attr( $args['option_key'] ) . '[' . esc_attr( $args['id'] ) . ']" value="' . esc_attr( $value ) . '" class="small-text" '.$min.' '.$max.' />';
         if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    public function text_callback( $args ) {
        if (null === $this->dashboard_options && function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen && $screen->id === 'toplevel_page_aipu-dashboard') {
                $this->dashboard_options = get_option( self::DASHBOARD_SETTINGS_OPTION_KEY );
            }
        }
        $options = $this->dashboard_options ?: $this->get_default_dashboard_settings();
        $value = isset( $options[$args['id']] ) ? $options[$args['id']] : '';
        $class = isset($args['class']) ? esc_attr($args['class']) : 'regular-text';
        echo '<input type="text" id="' . esc_attr( $args['id'] ) . '" name="' . esc_attr( $args['option_key'] ) . '[' . esc_attr( $args['id'] ) . ']" value="' . esc_attr( $value ) . '" class="'.$class.'" />';
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    public function select_callback( $args ) {
        if (null === $this->dashboard_options && function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen && $screen->id === 'toplevel_page_aipu-dashboard') {
                $this->dashboard_options = get_option( self::DASHBOARD_SETTINGS_OPTION_KEY );
            }
        }
        $options = $this->dashboard_options ?: $this->get_default_dashboard_settings();
        $value = isset( $options[$args['id']] ) ? $options[$args['id']] : '';
        echo '<select id="' . esc_attr( $args['id'] ) . '" name="' . esc_attr( $args['option_key'] ) . '[' . esc_attr( $args['id'] ) . ']">';
        foreach ( $args['options'] as $val => $label ) {
            echo '<option value="' . esc_attr( $val ) . '" ' . selected( $value, $val, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
    }

    private function get_cron_schedules_for_select() {
        $schedules = wp_get_schedules(); // This gets all registered schedules
        $aipu_schedules = [];
        foreach ($schedules as $key => $details) {
            // Filter for AIPU or common schedules
            if (strpos($key, 'aipu_') === 0 || in_array($key, ['hourly', 'twicedaily', 'daily'])) {
                 $aipu_schedules[$key] = $details['display'];
            }
        }
        asort($aipu_schedules); // Sort by display name or key
        return $aipu_schedules;
    }

    public function handle_clear_logs_action() {
         if ( ! isset( $_POST['_aipu_nonce'] ) || ! wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['_aipu_nonce'])), self::LOGS_ACTION_NONCE ) ) {
             wp_die( __( 'Security check failed!', 'ai-powered-post-updater' ) );
         }
         if ( ! current_user_can( 'manage_options' ) ) {
             wp_die( __( 'You do not have permission to clear logs.', 'ai-powered-post-updater' ) );
         }
         AIPU_Logger::clear_logs();
         wp_redirect( admin_url( 'admin.php?page=aipu-dashboard&tab=logs&logs_cleared=true' ) );
         exit;
    }

       public function handle_trigger_blc_rescan_action() {
            if ( ! isset( $_POST['_aipu_blc_nonce'] ) || ! wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['_aipu_blc_nonce'])), self::BLC_RESCAN_NONCE ) ) {
                wp_die( __( 'Security check failed!', 'ai-powered-post-updater' ) );
            }
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( __( 'You do not have permission to trigger a rescan.', 'ai-powered-post-updater' ) );
            }

            delete_option('aipu_blc_last_processed_id'); // Reset progress

            // Clear existing scheduled hook to avoid duplicates if one is already there for "soon"
            // Ensure AIPU_Broken_Link_Checker class is available for the constant
            $blc_hook = defined('AIPU_Broken_Link_Checker::EXTERNAL_CHECK_HOOK') ? AIPU_Broken_Link_Checker::EXTERNAL_CHECK_HOOK : 'aipu_check_external_links_cron';
            wp_clear_scheduled_hook($blc_hook);

            // Schedule it to run soon (e.g. in 1 minute)
            wp_schedule_single_event( time() + 60, $blc_hook );

            AIPU_Logger::log( 'Manual full broken link rescan triggered by user.' );

            wp_redirect( admin_url( 'admin.php?page=aipu-dashboard&tab=broken_links&rescan_triggered=true' ) );
            exit;
        }

    public function handle_ajax_edit_broken_link() {
        check_ajax_referer( 'aipu_dashboard_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) { // Or a more specific capability
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ai-powered-post-updater' ) ] );
        }

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $broken_url_b64 = isset( $_POST['broken_url_b64'] ) ? sanitize_text_field( wp_unslash( $_POST['broken_url_b64'] ) ) : '';
        $new_url = isset( $_POST['new_url'] ) ? sanitize_url( wp_unslash( $_POST['new_url'] ) ) : '';
        $anchor_text_b64 = isset( $_POST['anchor_text_b64'] ) ? sanitize_text_field( wp_unslash( $_POST['anchor_text_b64'] ) ) : '';

        if ( !$post_id || empty($broken_url_b64) || empty($new_url) ) {
            wp_send_json_error( [ 'message' => __( 'Missing required data.', 'ai-powered-post-updater' ) ] );
        }
        if ( !filter_var($new_url, FILTER_VALIDATE_URL) ) {
            wp_send_json_error( [ 'message' => __( 'New URL is not valid.', 'ai-powered-post-updater' ) ] );
        }

        $broken_url = base64_decode($broken_url_b64);
        $anchor_text = base64_decode($anchor_text_b64);

        $post = get_post( $post_id );
        if ( !$post ) {
            wp_send_json_error( [ 'message' => __( 'Post not found.', 'ai-powered-post-updater' ) ] );
        }

        $blc = new AIPU_Broken_Link_Checker(); // Assumes BLC class is loaded
        $new_content = $blc->update_link_in_content( $post->post_content, $broken_url, $new_url, $anchor_text );

        if ( $new_content !== $post->post_content ) {
            $updated = wp_update_post( [ 'ID' => $post_id, 'post_content' => $new_content ], true );
            if ( is_wp_error( $updated ) ) {
                wp_send_json_error( [ 'message' => __( 'Failed to update post content: ', 'ai-powered-post-updater' ) . $updated->get_error_message() ] );
            }
            $blc->remove_specific_broken_link_from_meta( $post_id, $broken_url );
            // Check if the new URL is also broken immediately? Maybe not, let cron handle it.
            AIPU_Logger::log("User edited broken link '{$broken_url}' to '{$new_url}' in post {$post_id}.", "INFO");
            wp_send_json_success( [ 'message' => __( 'Link updated successfully.', 'ai-powered-post-updater' ) ] );
        } else {
            // If content didn't change, it means the link wasn't found as expected.
            // It might have been changed by another process, or anchor text mismatch if strict.
            $blc->remove_specific_broken_link_from_meta( $post_id, $broken_url ); // Still remove from meta as it might be fixed or gone
            wp_send_json_error( [ 'message' => __( 'Link not found in content or no change made. It has been removed from the broken list.', 'ai-powered-post-updater' ) ] );
        }
    }

    public function handle_ajax_remove_broken_link() {
        check_ajax_referer( 'aipu_dashboard_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ai-powered-post-updater' ) ] );
        }

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $broken_url_b64 = isset( $_POST['broken_url_b64'] ) ? sanitize_text_field( wp_unslash( $_POST['broken_url_b64'] ) ) : '';
        $anchor_text_b64 = isset( $_POST['anchor_text_b64'] ) ? sanitize_text_field( wp_unslash( $_POST['anchor_text_b64'] ) ) : '';


        if ( !$post_id || empty($broken_url_b64) ) {
            wp_send_json_error( [ 'message' => __( 'Missing required data.', 'ai-powered-post-updater' ) ] );
        }

        $broken_url = base64_decode($broken_url_b64);
        $anchor_text = base64_decode($anchor_text_b64);

        $post = get_post( $post_id );
        if ( !$post ) {
            wp_send_json_error( [ 'message' => __( 'Post not found.', 'ai-powered-post-updater' ) ] );
        }

        $blc = new AIPU_Broken_Link_Checker();
        $new_content = $blc->unlink_in_content( $post->post_content, $broken_url, $anchor_text );

        if ( $new_content !== $post->post_content ) {
            $updated = wp_update_post( [ 'ID' => $post_id, 'post_content' => $new_content ], true );
            if ( is_wp_error( $updated ) ) {
                wp_send_json_error( [ 'message' => __( 'Failed to update post content: ', 'ai-powered-post-updater' ) . $updated->get_error_message() ] );
            }
            $blc->remove_specific_broken_link_from_meta( $post_id, $broken_url );
            AIPU_Logger::log("User unlinked broken link '{$broken_url}' (Anchor: '{$anchor_text}') in post {$post_id}.", "INFO");
            wp_send_json_success( [ 'message' => __( 'Link removed successfully.', 'ai-powered-post-updater' ) ] );
        } else {
            $blc->remove_specific_broken_link_from_meta( $post_id, $broken_url );
            wp_send_json_error( [ 'message' => __( 'Link not found in content or no change made. It has been removed from the broken list.', 'ai-powered-post-updater' ) ] );
        }
    }

}
?>
