<?php
/**
 * Plugin Name:       AI Powered Post Updater
 * Plugin URI:        https://example.com/plugins/ai-powered-post-updater/
 * Description:       Enhances blog posts with updated information, trending keywords, and SEO improvements.
 * Version:           0.2.0
 * Author:            Jules AI Agent
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-powered-post-updater
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Define global constants.
 */
define( 'AIPU_VERSION', '0.1.0' );
define( 'AIPU_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIPU_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 */
function activate_ai_powered_post_updater() {
    // Add any activation specific code here.
    // For example, setting default options.
    if ( ! get_option( 'aipu_settings' ) ) {
        $default_settings = [
            'google_trends_api_key' => '',
            'google_search_console_api_key' => '',
            'enable_web_research' => true,
            'enable_trending_keywords' => true,
            'enable_content_refinement' => true,
            'enable_seo_enhancements' => true,
        ];
        update_option( 'aipu_settings', $default_settings );
    }
}
register_activation_hook( __FILE__, 'activate_ai_powered_post_updater' );

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_ai_powered_post_updater() {
    // Add any deactivation specific code here.
}
register_deactivation_hook( __FILE__, 'deactivate_ai_powered_post_updater' );

/**
 * Include core plugin classes.
 */
require_once AIPU_PLUGIN_DIR . 'includes/class-aipu-settings.php';
require_once AIPU_PLUGIN_DIR . 'includes/class-aipu-content-analyzer.php';
require_once AIPU_PLUGIN_DIR . 'includes/class-aipu-external-retriever.php';
require_once AIPU_PLUGIN_DIR . 'includes/class-aipu-trending-keywords.php';
require_once AIPU_PLUGIN_DIR . 'includes/class-aipu-content-refiner.php';
require_once AIPU_PLUGIN_DIR . 'includes/class-aipu-indexing-service.php';
require_once AIPU_PLUGIN_DIR . 'includes/class-aipu-seo-enhancements.php';
require_once AIPU_PLUGIN_DIR . 'includes/class-aipu-meta-box.php';
require_once AIPU_PLUGIN_DIR . 'includes/class-aipu-scheduler.php';
require_once AIPU_PLUGIN_DIR . 'includes/class-aipu-logger.php';
require_once AIPU_PLUGIN_DIR . 'includes/class-aipu-admin-dashboard.php';
require_once AIPU_PLUGIN_DIR . 'includes/class-aipu-broken-link-checker.php';
require_once AIPU_PLUGIN_DIR . 'includes/class-aipu-automated-internal-linking.php';

// Initialize the settings page
if ( is_admin() ) {
    new AIPU_Settings();
}
// Initialize SEO Enhancements (for schema markup hook)
new AIPU_SEO_Enhancements();

// Initialize Meta Box (admin only)
if ( is_admin() ) {
    new AIPU_Meta_Box();
}

// Initialize Scheduler (for custom schedules filter)
new AIPU_Scheduler();
// AIPU_Logger is static, no instantiation needed for basic logging.

if ( is_admin() ) {
    new AIPU_Admin_Dashboard();
}

// Initialize BLC if enabled in dashboard settings
$dashboard_settings_for_blc = get_option( AIPU_Admin_Dashboard::DASHBOARD_SETTINGS_OPTION_KEY, [] );
$blc_defaults = [ // Default values for BLC settings
    'enable_broken_link_checker' => false,
];
$blc_current_settings = wp_parse_args($dashboard_settings_for_blc, $blc_defaults);

if ( !empty($blc_current_settings['enable_broken_link_checker']) ) {
    new AIPU_Broken_Link_Checker();
}

// Initialize Automated Internal Linking if enabled
if ( !empty($blc_current_settings['enable_auto_internal_linking']) ) { // Assuming $blc_current_settings includes all dashboard options
    new AIPU_Automated_Internal_Linking();
}


function aipu_admin_enqueue_assets($hook_suffix) {
    // Check if current page is our dashboard
    if ($hook_suffix === 'toplevel_page_aipu-dashboard') {
         wp_enqueue_style(
           'aipu-admin-dashboard-style',
           AIPU_PLUGIN_URL . 'assets/css/admin-dashboard.css',
           [],
           AIPU_VERSION
       );
       wp_enqueue_script(
            'aipu-admin-dashboard-script', // New script
            AIPU_PLUGIN_URL . 'assets/js/admin-dashboard.js',
            [ 'jquery' ],
            AIPU_VERSION,
            true // In footer
       );
       wp_localize_script('aipu-admin-dashboard-script', 'aipuDashboard', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('aipu_dashboard_ajax_nonce') // A general nonce for dashboard actions
       ]);
    }
    // Meta box assets are enqueued in AIPU_Meta_Box class's enqueue_scripts method
}
add_action('admin_enqueue_scripts', 'aipu_admin_enqueue_assets');

?>
