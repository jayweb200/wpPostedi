<?php
/**
 * AI Powered Post Updater Automated Internal Linking
 *
 * @package AIPU
 * @since   0.2.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class AIPU_Automated_Internal_Linking {

    private $dashboard_settings;
    private $content_analyzer_class = 'AIPU_Content_Analyzer'; // Allow easier mocking/DI if needed

    public function __construct() {
        $this->dashboard_settings = get_option( AIPU_Admin_Dashboard::DASHBOARD_SETTINGS_OPTION_KEY, [] );

        if ( !empty($this->dashboard_settings['enable_auto_internal_linking']) ) {
            add_action( 'save_post_post', [ $this, 'process_post_for_linking' ], 20, 2 ); // 'save_post_{post_type}'
        }
    }

    private function get_setting($key, $default = null) {
        $defaults = [ // Keep defaults aligned with AIPU_Admin_Dashboard
            'enable_auto_internal_linking' => false,
            'max_links_per_post' => 3,
            'link_to_categories' => '', // Comma-separated IDs or slugs
            'link_to_tags' => '',       // Comma-separated IDs or slugs
            'avoid_relinking_phrases' => true,
            'only_link_once_per_target_url' => true,
        ];
        // Ensure $this->dashboard_settings is an array before parsing
        $current_settings = is_array($this->dashboard_settings) ? $this->dashboard_settings : [];
        $settings = wp_parse_args($current_settings, $defaults);
        return isset($settings[$key]) ? $settings[$key] : $default;
    }

    /**
     * Processes a post on save to add internal links.
     * @param int $post_id The ID of the post being saved.
     * @param WP_Post $post The post object.
     */
    public function process_post_for_linking( $post_id, $post ) {
        // Prevent processing on revisions or autosaves
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        // Check if this action has already run for this save operation to prevent loops
        // Using a static flag per request to avoid issues with multiple save_post firings for same actual save.
        static $processed_posts_this_request = [];
        if (isset($processed_posts_this_request[$post_id])) {
            return;
        }

        AIPU_Logger::log( "Auto Internal Linking: Starting process for post ID {$post_id}.", 'DEBUG' );

        $post_content = $post->post_content; // Content from the $post object passed by the hook

        if ( empty( $post_content ) ) {
            AIPU_Logger::log( "Auto Internal Linking: Post ID {$post_id} has empty content. Skipping.", 'DEBUG' );
            return;
        }

        $max_links_to_add = (int) $this->get_setting('max_links_per_post', 3);
        $links_added_count = 0;
        $linked_target_urls = [];

        if (!class_exists($this->content_analyzer_class)) {
            AIPU_Logger::log("Auto Internal Linking: Content Analyzer class '{$this->content_analyzer_class}' not found.", 'ERROR');
            return;
        }
        $analyzer = new $this->content_analyzer_class( $post_id );
        $keywords = $analyzer->extract_keywords(10);

        if ( empty( $keywords ) ) {
            AIPU_Logger::log( "Auto Internal Linking: No keywords extracted for post ID {$post_id}. Skipping.", 'DEBUG' );
            return;
        }

        shuffle($keywords);
        $modified_content = $post_content;

        foreach ( $keywords as $keyword ) {
            if ( $links_added_count >= $max_links_to_add ) {
                break;
            }
            if (strlen($keyword) < 4) continue;

            $target_post = $this->find_link_target_for_keyword( $keyword, $post_id );

            if ( $target_post ) {
                $target_url = get_permalink( $target_post->ID );

                if ($this->get_setting('only_link_once_per_target_url', true) && in_array($target_url, $linked_target_urls)) {
                    continue;
                }

                $new_content_with_link = $this->insert_link_into_content(
                    $modified_content,
                    $keyword,
                    $target_url,
                    (bool) $this->get_setting('avoid_relinking_phrases', true)
                );

                if ( $new_content_with_link !== $modified_content ) {
                    $modified_content = $new_content_with_link;
                    $links_added_count++;
                    $linked_target_urls[] = $target_url;
                    AIPU_Logger::log( "Auto Internal Linking: Added link for '{$keyword}' to {$target_url} in post ID {$post_id}." );
                }
            }
        }

        if ( $post_content !== $modified_content ) {
            $processed_posts_this_request[$post_id] = true; // Mark as processed for this request

            remove_action( 'save_post_post', [ $this, 'process_post_for_linking' ], 20 );
            wp_update_post( [ 'ID' => $post_id, 'post_content' => $modified_content ] );
            add_action( 'save_post_post', [ $this, 'process_post_for_linking' ], 20, 2 );

            AIPU_Logger::log( "Auto Internal Linking: Updated post ID {$post_id} with {$links_added_count} internal links." );
        }
    }

    /**
     * Finds a suitable target post for a given keyword.
     * @param string $keyword
     * @param int $current_post_id Post ID to exclude from results.
     * @return WP_Post|null Target post object or null.
     */
    private function find_link_target_for_keyword( $keyword, $current_post_id ) {
        $query_args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            's'              => $keyword,
            'post__not_in'   => [ $current_post_id ],
            'orderby'        => 'relevance',
        ];

        $cat_setting = $this->get_setting('link_to_categories');
        if (!empty($cat_setting)) {
            // Assuming slugs for simplicity. Could be IDs if input is numeric.
            $query_args['category_name'] = sanitize_text_field($cat_setting);
        }

        $tag_setting = $this->get_setting('link_to_tags');
        if (!empty($tag_setting)) {
            // Assuming slugs.
            $query_args['tag'] = sanitize_text_field($tag_setting);
        }

        $target_query = new WP_Query( $query_args );
        if ( $target_query->have_posts() ) {
            return $target_query->posts[0];
        }
        return null;
    }

    /**
     * Inserts a link into content for the first suitable occurrence of a phrase.
     * @param string $content HTML content.
     * @param string $phrase The phrase to link.
     * @param string $url The URL to link to.
     * @param bool $avoid_relinking If true, won't link phrase if already in an <a> tag or other non-linkable contexts.
     * @return string Modified content.
     */
    private function insert_link_into_content( $content, $phrase, $url, $avoid_relinking = true ) {
        if (empty($phrase) || empty($url) || empty($content)) return $content;

        $doc = new DOMDocument();
        libxml_use_internal_errors(true); // Suppress HTML5 parsing errors
        $doc->loadHTML('<?xml encoding="UTF-8">' . $content); // Ensure UTF-8
        libxml_clear_errors();
        $xpath = new DOMXPath($doc);

        // Check if the exact phrase is already linked to this specific URL
        $existing_links_to_url = $xpath->query('//a[@href="' . esc_attr($url) . '"]');
        foreach ($existing_links_to_url as $existing_link) {
            if (trim($existing_link->nodeValue) === trim($phrase)) {
                return $content;
            }
        }

        $text_nodes = $xpath->query('//text()');
        $modified_in_dom = false;

        foreach ($text_nodes as $text_node) {
            if ($avoid_relinking) {
                $parent = $text_node->parentNode;
                $is_linkable_context = true;
                while($parent && strtolower($parent->nodeName) !== 'body' && $parent->nodeType === XML_ELEMENT_NODE) { // Check up to body
                    if (in_array(strtolower($parent->nodeName), ['a', 'script', 'style', 'noscript', 'textarea', 'button', 'pre', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
                        $is_linkable_context = false;
                        break;
                    }
                    $parent = $parent->parentNode;
                }
                if (!$is_linkable_context) continue;
            }

            $node_text = $text_node->nodeValue;
            // Case-insensitive search for the phrase within the text node.
            // We need to find the phrase but preserve its original casing for the link text.
            // preg_quote is important for $phrase if it contains regex special characters.
            $regex_phrase = preg_quote($phrase, '/');
            if (preg_match('/(' . $regex_phrase . ')/i', $node_text, $matches, PREG_OFFSET_CAPTURE)) {
                $pos = $matches[0][1];
                $actual_phrase_in_text = $matches[0][0]; // This is the phrase with original casing

                $before_text = substr($node_text, 0, $pos);
                $after_text = substr($node_text, $pos + strlen($actual_phrase_in_text));

                $link_element = $doc->createElement('a', $actual_phrase_in_text); // Use original casing
                $link_element->setAttribute('href', esc_url($url));
                $link_element->setAttribute('class', 'aipu-internal-link'); // Optional class

                $new_nodes = [];
                if (!empty($before_text)) $new_nodes[] = $doc->createTextNode($before_text);
                $new_nodes[] = $link_element;
                if (!empty($after_text)) $new_nodes[] = $doc->createTextNode($after_text);

                $parent_node = $text_node->parentNode;
                if ($parent_node) {
                    foreach($new_nodes as $new_node) {
                        $parent_node->insertBefore($new_node, $text_node);
                    }
                    $parent_node->removeChild($text_node);
                    $modified_in_dom = true;
                    break;
                }
            }
        }

        if ($modified_in_dom) {
            $body_node = $xpath->query('//body/node()'); // Get all child nodes of body
            if ($body_node && $body_node->length > 0) {
                $new_content = '';
                foreach ($body_node as $childNode) {
                    $new_content .= $doc->saveHTML($childNode);
                }
                return $new_content;
            }
        }
        return $content;
    }
}
?>
