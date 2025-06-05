<?php
/**
 * AI Powered Post Updater Settings
 *
 * @package AIPU
 * @since   0.1.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class AIPU_Settings {

    private $options;

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_plugin_page' ] );
        add_action( 'admin_init', [ $this, 'page_init' ] );
    }

    public function add_plugin_page() {
        add_options_page(
            'AI Post Updater Settings',
            'AI Post Updater',
            'manage_options',
            'aipu-settings-admin',
            [ $this, 'create_admin_page' ]
        );
    }

    public function create_admin_page() {
        $this->options = get_option( 'aipu_settings' );
        ?>
        <div class="wrap">
            <h1>AI Powered Post Updater Settings</h1>
            <form method="post" action="options.php">
                <?php
                    settings_fields( 'aipu_option_group' );
                    do_settings_sections( 'aipu-setting-admin' );
                    submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function page_init() {
        register_setting(
            'aipu_option_group',
            'aipu_settings',
            [ $this, 'sanitize' ]
        );

        add_settings_section(
            'aipu_api_keys_section',
            'API Keys',
            [ $this, 'print_api_keys_section_info' ],
            'aipu-setting-admin'
        );

        add_settings_field(
            'google_trends_api_key',
            'Google Trends API Key',
            [ $this, 'google_trends_api_key_callback' ],
            'aipu-setting-admin',
            'aipu_api_keys_section'
        );

        add_settings_field(
            'google_search_console_api_key',
            'Google Search Console API Key',
            [ $this, 'google_search_console_api_key_callback' ],
            'aipu-setting-admin',
            'aipu_api_keys_section'
        );

        add_settings_section(
            'aipu_features_section',
            'Enable/Disable Features',
            [ $this, 'print_features_section_info' ],
            'aipu-setting-admin'
        );

        add_settings_field(
            'enable_web_research',
            'Enable Web Research',
            [ $this, 'enable_web_research_callback' ],
            'aipu-setting-admin',
            'aipu_features_section'
        );
         add_settings_field(
            'enable_trending_keywords',
            'Enable Trending Keywords',
            [ $this, 'enable_trending_keywords_callback' ],
            'aipu-setting-admin',
            'aipu_features_section'
        );
         add_settings_field(
            'enable_content_refinement',
            'Enable Content Refinement',
            [ $this, 'enable_content_refinement_callback' ],
            'aipu-setting-admin',
            'aipu_features_section'
        );
         add_settings_field(
            'enable_seo_enhancements',
            'Enable SEO Enhancements',
            [ $this, 'enable_seo_enhancements_callback' ],
            'aipu-setting-admin',
            'aipu_features_section'
        );
    }

    public function sanitize( $input ) {
        $sanitized_input = [];
        if ( isset( $input['google_trends_api_key'] ) ) {
            $sanitized_input['google_trends_api_key'] = sanitize_text_field( $input['google_trends_api_key'] );
        }
        if ( isset( $input['google_search_console_api_key'] ) ) {
            $sanitized_input['google_search_console_api_key'] = sanitize_text_field( $input['google_search_console_api_key'] );
        }
        $sanitized_input['enable_web_research'] = isset( $input['enable_web_research'] ) ? true : false;
        $sanitized_input['enable_trending_keywords'] = isset( $input['enable_trending_keywords'] ) ? true : false;
        $sanitized_input['enable_content_refinement'] = isset( $input['enable_content_refinement'] ) ? true : false;
        $sanitized_input['enable_seo_enhancements'] = isset( $input['enable_seo_enhancements'] ) ? true : false;

        return $sanitized_input;
    }

    public function print_api_keys_section_info() {
        print 'Enter your API keys below. These are required for certain features to function correctly.';
    }

    public function print_features_section_info() {
        print 'Configure which features of the plugin are active:';
    }

    public function google_trends_api_key_callback() {
         if (null === $this->options) { $this->options = get_option('aipu_settings'); }
        printf(
            '<input type="text" id="google_trends_api_key" name="aipu_settings[google_trends_api_key]" value="%s" class="regular-text" />',
            isset( $this->options['google_trends_api_key'] ) ? esc_attr( $this->options['google_trends_api_key']) : ''
        );
    }

    public function google_search_console_api_key_callback() {
         if (null === $this->options) { $this->options = get_option('aipu_settings'); }
        printf(
            '<input type="text" id="google_search_console_api_key" name="aipu_settings[google_search_console_api_key]" value="%s" class="regular-text" />',
            isset( $this->options['google_search_console_api_key'] ) ? esc_attr( $this->options['google_search_console_api_key']) : ''
        );
    }

    public function enable_web_research_callback() {
         if (null === $this->options) { $this->options = get_option('aipu_settings'); }
         printf(
             '<input type="checkbox" id="enable_web_research" name="aipu_settings[enable_web_research]" value="1" %s /> <label for="enable_web_research">Search the web for updated information related to the post.</label>',
             checked( 1, isset($this->options['enable_web_research']) ? $this->options['enable_web_research'] : false, false )
         );
    }
     public function enable_trending_keywords_callback() {
         if (null === $this->options) { $this->options = get_option('aipu_settings'); }
         printf(
             '<input type="checkbox" id="enable_trending_keywords" name="aipu_settings[enable_trending_keywords]" value="1" %s /> <label for="enable_trending_keywords">Fetch and suggest trending keywords (requires Google Trends API key).</label>',
             checked( 1, isset($this->options['enable_trending_keywords']) ? $this->options['enable_trending_keywords'] : false, false )
         );
    }
    public function enable_content_refinement_callback() {
         if (null === $this->options) { $this->options = get_option('aipu_settings'); }
         printf(
             '<input type="checkbox" id="enable_content_refinement" name="aipu_settings[enable_content_refinement]" value="1" %s /> <label for="enable_content_refinement">Provide suggestions for improving content readability and style.</label>',
             checked( 1, isset($this->options['enable_content_refinement']) ? $this->options['enable_content_refinement'] : false, false )
         );
    }
    public function enable_seo_enhancements_callback() {
         if (null === $this->options) { $this->options = get_option('aipu_settings'); }
         printf(
             '<input type="checkbox" id="enable_seo_enhancements" name="aipu_settings[enable_seo_enhancements]" value="1" %s /> <label for="enable_seo_enhancements">Suggest basic SEO improvements like internal links and image alt text.</label>',
             checked( 1, isset($this->options['enable_seo_enhancements']) ? $this->options['enable_seo_enhancements'] : false, false )
         );
    }
}
?>
