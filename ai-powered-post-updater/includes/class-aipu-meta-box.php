<?php
/**
 * AI Powered Post Updater Meta Box
 *
 * @package AIPU
 * @since   0.1.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class AIPU_Meta_Box {

    private $plugin_settings;

    public function __construct() {
        $this->plugin_settings = get_option( 'aipu_settings' );
        add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        // Action for handling the indexing request
        add_action( 'wp_ajax_aipu_request_indexing', [ $this, 'handle_ajax_request_indexing' ] );
        add_action( 'wp_ajax_aipu_recheck_post_links_action', [ $this, 'handle_ajax_recheck_post_links' ] );
    }

    public function enqueue_scripts( $hook ) {
        // Only load on post edit screens
        if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
            return;
        }
        global $post;
        if ( !$post || $post->post_type !== 'post') {
            return;
        }

        wp_enqueue_style(
            'aipu-meta-box-style',
            AIPU_PLUGIN_URL . 'assets/css/meta-box.css',
            [],
            AIPU_VERSION
        );

        wp_enqueue_script(
            'aipu-meta-box-script',
            AIPU_PLUGIN_URL . 'assets/js/meta-box.js',
            [ 'jquery' ],
            AIPU_VERSION,
            true // In footer
        );
         wp_localize_script('aipu-meta-box-script', 'aipuMetaBox', [
             'ajax_url' => admin_url('admin-ajax.php'),
             'nonce'    => wp_create_nonce('aipu_meta_box_nonce'),
             'post_id'  => get_the_ID(),
         ]);
    }

    public function add_meta_box( $post_type ) {
        // Limit meta box to specific post types (e.g., 'post')
        $post_types = ['post'];
        if ( in_array( $post_type, $post_types ) ) {
            add_meta_box(
                'aipu_main_meta_box',
                __( 'AI Powered Post Updater', 'ai-powered-post-updater' ),
                [ $this, 'render_meta_box_content' ],
                $post_type,
                'advanced', // Position
                'high'       // Priority
            );
        }
    }

    public function render_meta_box_content( $post ) {
        // Add a nonce field for security
        wp_nonce_field( 'aipu_meta_box_action', 'aipu_meta_box_nonce' );

        // Instantiate service classes
        $content_analyzer = new AIPU_Content_Analyzer( $post->ID );
        $external_retriever = new AIPU_External_Retriever();
        $trending_keywords_service = new AIPU_Trending_Keywords();
        $content_refiner = new AIPU_Content_Refiner();
        $indexing_service = new AIPU_Indexing_Service();
        $seo_enhancer = new AIPU_SEO_Enhancements(); // Already adds schema via its constructor hook

        echo '<div class="aipu-meta-box-container">';

        // --- Section: Content Analysis ---
        if ($this->is_feature_enabled('enable_content_refinement')) { // Grouped with refinement for now
            echo '<h3><span class="dashicons dashicons-analytics"></span> Content Analysis</h3>';
            $keywords = $content_analyzer->extract_keywords(5);
            $topics = $content_analyzer->extract_topics(3);
            echo '<p><strong>Keywords:</strong> ' . (empty($keywords) ? 'N/A' : esc_html(implode(', ', $keywords))) . '</p>';
            echo '<p><strong>Main Topics:</strong> ' . (empty($topics) ? 'N/A' : esc_html(implode(', ', $topics))) . '</p>';
        }

        // --- Section: External Research ---
        if ($this->is_feature_enabled('enable_web_research')) {
            echo '<h3><span class="dashicons dashicons-search"></span> External Research & Updates</h3>';
            $web_results = $external_retriever->search_web_for_updates( $keywords, 3 );
            $this->display_results_list($web_results, "Recent Web Mentions (Mocked)");

            $outbound_links = $external_retriever->extract_outbound_links( $post->post_content );
            if (!empty($outbound_links)) {
                echo '<p><strong>Scan linked sites for updates:</strong></p><ul>';
                foreach(array_slice($outbound_links, 0, 3) as $link) { // Limit to first 3 links
                    echo '<li>' . esc_url($link) . ' <button type="button" class="button button-secondary button-small aipu-scan-link-btn" data-url="'.esc_url($link).'">Scan Site</button></li>';
                }
                echo '</ul><div id="aipu-scan-link-results"></div>';
            } else {
                echo '<p>No outbound links found in the post to scan.</p>';
            }
        }

        // --- Section: Trending Keywords ---
        if ($this->is_feature_enabled('enable_trending_keywords')) {
            echo '<h3><span class="dashicons dashicons-chart-line"></span> Trending Keywords (Mocked)</h3>';
            $trends = $trending_keywords_service->get_trending_keywords( $keywords, 'US', 5 );
            $this->display_results_list($trends, "Related Trends", true);
        }

        // --- Section: Content Refinements ---
        if ($this->is_feature_enabled('enable_content_refinement')) {
            echo '<h3><span class="dashicons dashicons-editor-spellcheck"></span> Content Refinement Suggestions</h3>';
            $refinement_suggestions = $content_refiner->get_all_suggestions( $post->ID );
            $this->display_suggestions($refinement_suggestions);
        }

        // --- Section: SEO Enhancements ---
        if ($this->is_feature_enabled('enable_seo_enhancements')) {
            echo '<h3><span class="dashicons dashicons-awards"></span> SEO Enhancement Suggestions</h3>';
            $seo_suggestions = $seo_enhancer->get_all_seo_suggestions( $post->ID );
            $this->display_suggestions($seo_suggestions);
        }

        // --- Section: Indexing ---
        echo '<h3><span class="dashicons dashicons-cloud-upload"></span> Search Engine Indexing</h3>';
        echo '<p>Request indexing for this post (ensure content is final):</p>';
        echo '<button type="button" id="aipu-request-google-indexing" class="button button-primary">Request Google Indexing</button>';
        echo '<p id="aipu-indexing-status" style="margin-top:10px;"></p>';

        // --- Section: Link Health ---
        echo '<h3><span class="dashicons dashicons-search"></span> Link Health</h3>';
        $broken_links_data = get_post_meta( $post->ID, AIPU_Broken_Link_Checker::POST_META_KEY_BROKEN_LINKS, true );
        if ( !empty($broken_links_data) && is_array($broken_links_data) ) {
            echo '<h4>' . __('Broken Links Found in this Post:', 'ai-powered-post-updater') . '</h4>';
            echo '<ul>';
            foreach (array_slice($broken_links_data, 0, 5) as $link) { // Show first 5
                echo '<li><a href="' . esc_url($link['url']) . '" target="_blank">' . esc_html(wp_trim_words($link['url'], 8)) . '</a> (' . esc_html($link['status']) . ') - Anchor: ' . esc_html(wp_trim_words($link['anchor'], 5)) . '</li>';
            }
            echo '</ul>';
            if (count($broken_links_data) > 5) {
                echo '<p>' . sprintf(__('And %d more...', 'ai-powered-post-updater'), count($broken_links_data) - 5) . '</p>';
            }
            echo '<p><a href="' . esc_url(admin_url('admin.php?page=aipu-dashboard&tab=broken_links')) . '">' . __('View full report & manage links', 'ai-powered-post-updater') . '</a></p>';
        } else {
            $dashboard_settings = get_option(AIPU_Admin_Dashboard::DASHBOARD_SETTINGS_OPTION_KEY, []);
            $blc_enabled = !empty($dashboard_settings['enable_broken_link_checker']);
            if ($blc_enabled) {
               echo '<p>' . __('No broken links detected in this post by the last scan.', 'ai-powered-post-updater') . '</p>';
            } else {
               echo '<p>' . __('Broken Link Checker feature is currently disabled in global settings.', 'ai-powered-post-updater') . '</p>';
            }
        }
        echo '<button type="button" id="aipu-recheck-post-links" class="button button-secondary" data-postid="' . esc_attr($post->ID) . '">' . __('Re-scan Links in this Post', 'ai-powered-post-updater') . '</button>';
        echo '<span id="aipu-recheck-status" style="margin-left:10px;"></span>';


        // --- Section: Automated Internal Linking Info ---
        $dashboard_settings_ail = get_option(AIPU_Admin_Dashboard::DASHBOARD_SETTINGS_OPTION_KEY, []);
        $auto_linking_enabled = !empty($dashboard_settings_ail['enable_auto_internal_linking']);
        echo '<h3><span class="dashicons dashicons-admin-links"></span> Automated Internal Linking</h3>';
        if ($auto_linking_enabled) {
            $max_links = isset($dashboard_settings_ail['max_links_per_post']) ? intval($dashboard_settings_ail['max_links_per_post']) : 3;
            echo '<p>' . sprintf(__('Automated internal linking is enabled. Up to %d relevant links may be added when this post is saved.', 'ai-powered-post-updater'), $max_links) . '</p>';
        } else {
            echo '<p>' . __('Automated internal linking is currently disabled in global settings.', 'ai-powered-post-updater') . '</p>';
        }

        echo '</div>'; // .aipu-meta-box-container
    }

    private function is_feature_enabled($feature_key) {
        return isset($this->plugin_settings[$feature_key]) && $this->plugin_settings[$feature_key];
    }

    private function display_results_list($results, $title, $is_simple_list = false) {
        if (is_array($results) && isset($results['info'])) {
            echo '<p><em>' . esc_html($results['info']) . '</em></p>';
            return;
        }
         if (is_array($results) && isset($results['error'])) {
            echo '<p><em>Error: ' . esc_html($results['error']) . '</em></p>';
            return;
        }
        if (!empty($results) && is_array($results)) {
            echo "<p><strong>{$title}:</strong></p><ul>";
            foreach ($results as $item) {
                if ($is_simple_list && is_string($item)) {
                    echo '<li>' . esc_html($item) . '</li>';
                } elseif (is_array($item)) {
                    $line = '<strong>' . esc_html($item['title'] ?? '') . '</strong>';
                    if (!empty($item['link']) && $item['link'] !== '#') {
                         $line .= ' (<a href="' . esc_url($item['link']) . '" target="_blank">Visit</a>)';
                    }
                    if (!empty($item['snippet'])) {
                         $line .= '<br><small>' . esc_html($item['snippet']) . '</small>';
                    }
                     if (!empty($item['summary'])) {
                         $line .= '<br><small>' . esc_html($item['summary']) . '</small>';
                    }
                    echo '<li>' . $line . '</li>';
                }
            }
            echo '</ul>';
        } else {
            echo "<p>No {$title} found or feature disabled.</p>";
        }
    }

    private function display_suggestions($suggestions_array) {
        if (is_array($suggestions_array) && isset($suggestions_array['info'])) {
            echo '<p><em>' . esc_html($suggestions_array['info']) . '</em></p>';
            return;
        }
        if (is_array($suggestions_array) && isset($suggestions_array['error'])) {
            echo '<p><em>Error: ' . esc_html($suggestions_array['error']) . '</em></p>';
            return;
        }
        if (!empty($suggestions_array) && is_array($suggestions_array)) {
            echo '<ul>';
            foreach($suggestions_array as $suggestion) {
                if (is_array($suggestion) && !empty($suggestion['message'])) {
                    echo '<li>[' . esc_html(str_replace('_', ' ', ucwords($suggestion['type'],'_'))) . '] ' . wp_kses_post($suggestion['message']); // wp_kses_post for safety if message contains HTML
                    if(!empty($suggestion['sentence'])) echo ' <small><em>(Sentence: ' . esc_html(substr($suggestion['sentence'], 0, 70)) . '...)</em></small>';
                    if(!empty($suggestion['image_src'])) echo ' <small><em>(Image: ' . esc_html(basename($suggestion['image_src'])) . ')</em></small>';
                    echo '</li>';
                }
            }
            echo '</ul>';
        } else {
            echo '<p>No suggestions at the moment, or feature disabled.</p>';
        }
    }

    public function handle_ajax_request_indexing() {
         check_ajax_referer('aipu_meta_box_nonce', 'nonce');

         $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
         if (!$post_id) {
             wp_send_json_error(['message' => 'Error: Post ID missing.']);
             return;
         }
         $post_url = get_permalink($post_id);
         if (!$post_url) {
             wp_send_json_error(['message' => 'Error: Could not retrieve post URL.']);
             return;
         }

         $indexing_service = new AIPU_Indexing_Service();
         $result = $indexing_service->request_google_indexing($post_url);

         if (isset($result['success']) && $result['success']) {
             wp_send_json_success(['message' => $result['message']]);
         } else {
             wp_send_json_error(['message' => $result['message'] ?? 'An unknown error occurred during indexing request.']);
         }
     }

    public function handle_ajax_recheck_post_links() {
        check_ajax_referer('aipu_meta_box_nonce', 'nonce'); // Use existing meta box nonce

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied.', 'ai-powered-post-updater')]);
            return;
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error(['message' => __('Error: Post ID missing.', 'ai-powered-post-updater')]);
            return;
        }

        // Ensure BLC class is available
        if (!class_exists('AIPU_Broken_Link_Checker')) {
            wp_send_json_error(['message' => __('Error: Broken Link Checker module not available.', 'ai-powered-post-updater')]);
            return;
        }

        $blc = new AIPU_Broken_Link_Checker();
        $blc->scan_single_post_and_store_results($post_id); // This function logs internally

        wp_send_json_success(['message' => __('Link scan for this post complete. Refresh page to see updated status if changes were made.', 'ai-powered-post-updater')]);
    }
}
?>
