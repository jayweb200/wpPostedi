<?php
/**
 * Plugin Name:       AI Powered Post Updater
 * Plugin URI:        https://example.com/plugins/ai-powered-post-updater/
 * Description:       Enhances blog posts with updated information, trending keywords, and SEO improvements.
 * Version:           0.1.0
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
require_once AIPU_PLUGIN_DIR . 'includes/class-aipu-meta-box.php'; // Add this line

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
?>
