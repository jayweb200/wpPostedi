<?php
/**
 * AI Powered Post Updater Broken Link Checker
 *
 * @package AIPU
 * @since   0.2.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class AIPU_Broken_Link_Checker {

    const POST_META_KEY_BROKEN_LINKS = '_aipu_broken_links_data'; // Stores array of broken links for a post
    const EXTERNAL_CHECK_HOOK = 'aipu_check_external_links_cron';

    private $dashboard_settings;

    public function __construct() {
        $this->dashboard_settings = get_option( AIPU_Admin_Dashboard::DASHBOARD_SETTINGS_OPTION_KEY, [] );

        // Schedule/unschedule cron based on settings
        // Ensure default values are considered if settings are not fully saved yet
        $defaults = [
            'enable_broken_link_checker' => false,
            'broken_link_check_frequency' => 'daily', // Default WP schedule
        ];
        $current_settings = wp_parse_args($this->dashboard_settings, $defaults);

        if ( !empty($current_settings['enable_broken_link_checker']) &&
             !empty($current_settings['broken_link_check_frequency']) ) {
            AIPU_Scheduler::schedule_event( self::EXTERNAL_CHECK_HOOK, $current_settings['broken_link_check_frequency'] );
        } else {
            AIPU_Scheduler::clear_scheduled_hook( self::EXTERNAL_CHECK_HOOK );
        }

        add_action( self::EXTERNAL_CHECK_HOOK, [ $this, 'run_external_link_check_cron' ] );
        add_action( 'save_post', [ $this, 'clear_broken_link_data_on_save' ] );
    }

    /**
     * Scans a single post's content for internal and external links.
     * @param int $post_id
     * @return array ['internal' => [], 'external' => []]
     */
    public function extract_links_from_post( $post_id ) {
        $post_content = get_post_field( 'post_content', $post_id );
        if ( empty( $post_content ) ) {
            return ['internal' => [], 'external' => []];
        }

        $links = ['internal' => [], 'external' => []];
        $doc = new DOMDocument();
        @$doc->loadHTML( '<?xml encoding="UTF-8">' . $post_content );
        $anchor_tags = $doc->getElementsByTagName('a');
        $site_url = home_url();
        $site_url_parts = parse_url($site_url);
        $site_host = $site_url_parts['host'] ?? '';


        foreach ( $anchor_tags as $tag ) {
            if ( $tag->hasAttribute('href') ) {
                $href = trim( $tag->getAttribute('href') );
                $anchor_text = trim( $tag->nodeValue );

                if ( empty($href) || preg_match('/^(#|mailto:|tel:|javascript:)/i', $href) ) {
                    continue; // Skip empty, fragment, mailto, tel, javascript links
                }

                $link_data = ['url' => $href, 'anchor' => $anchor_text, 'status' => null, 'last_checked' => null];

                // Check if internal or external
                $href_parts = parse_url($href);
                $current_link_host = strtolower($href_parts['host'] ?? '');

                if ( $current_link_host === strtolower($site_host) ) {
                    $links['internal'][$href] = $link_data;
                } elseif (!empty($current_link_host)) { // Has a host different from site_host, so it's external
                    $links['external'][$href] = $link_data;
                } elseif (strpos($href, '/') === 0 || strpos($href, '?') === 0) { // Relative path (e.g. /page, ?query=)
                     $full_url = home_url($href);
                     $link_data['url'] = $full_url;
                     $links['internal'][$full_url] = $link_data;
                }
                // Note: More complex relative URLs (e.g., "another-page.html" without leading slash) are context-dependent
                // and would require knowing the current post's URL structure to resolve accurately.
                // For this iteration, we focus on absolute URLs and root-relative paths.
            }
        }
        $links['internal'] = $this->array_unique_by_key($links['internal'], 'url');
        $links['external'] = $this->array_unique_by_key($links['external'], 'url');
        return $links;
    }

    /**
     * Helper function to make array unique by a specific key.
     * (Ensures each URL is processed once per post)
     */
    private function array_unique_by_key(array $array, $key) {
        $temp_array = [];
        foreach ($array as $item) {
            if (!isset($temp_array[$item[$key]])) {
                $temp_array[$item[$key]] = $item;
            }
        }
        return array_values($temp_array);
    }


    /**
     * Checks a single internal URL.
     * @param string $url
     * @return bool True if valid, false if broken.
     */
    public function check_internal_link( $url ) {
        $post_id = url_to_postid( $url );
        if ( $post_id > 0 ) {
            return get_post_status( $post_id ) === 'publish';
        }
        // Fallback for non-post URLs (e.g. category/tag archives, custom links that don't map to a post ID)
        // A HEAD request can check if the URL is accessible.
        $response = wp_remote_head( $url, [ 'timeout' => 5, 'redirection' => 5 ] );
        if ( !is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) < 400 ) {
            return true;
        }
        AIPU_Logger::log( "Internal link check failed for {$url}. Status: " . (is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_code($response)), 'DEBUG');
        return false;
    }

    /**
     * Checks a single external URL.
     * @param string $url
     * @return int HTTP status code, or 0 for error/unreachable.
     */
    public function check_external_link( $url ) {
        $timeout = !empty($this->dashboard_settings['external_link_timeout']) ? intval($this->dashboard_settings['external_link_timeout']) : 10;
        $response = wp_remote_head( $url, [ 'timeout' => $timeout, 'redirection' => 5, 'user-agent' => 'AIPU Broken Link Checker/' . AIPU_VERSION . '; ' . home_url() ] );

        if ( is_wp_error( $response ) ) {
            AIPU_Logger::log( "HEAD request failed for external link {$url}: " . $response->get_error_message() . ". Trying GET.", 'DEBUG' );
            // If HEAD fails, try GET as some servers don't handle HEAD well or might block it.
            $response = wp_remote_get( $url, [ 'timeout' => $timeout, 'redirection' => 5, 'user-agent' => 'AIPU Broken Link Checker/' . AIPU_VERSION . '; ' . home_url() ] );
            if ( is_wp_error( $response ) ) {
                AIPU_Logger::log( "GET request also failed for external link {$url}: " . $response->get_error_message(), 'WARNING' );
                return 0; // 0 indicates a connection error or timeout
            }
        }
        return wp_remote_retrieve_response_code( $response );
    }

    /**
     * Scans all posts for broken links (both internal and external).
     * This is a heavy operation, intended to be run by WP-Cron.
     */
    public function run_external_link_check_cron() {
        AIPU_Logger::log( 'Starting scheduled external link check cron.' );
        $posts_per_batch = 20;
        $last_processed_post_id = get_option('aipu_blc_last_processed_id', 0);

        $args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $posts_per_batch,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            // Custom query for ID offset to ensure we don't re-process the same posts if cron runs frequently
            // This requires a filter on 'posts_where'
        ];
        if ($last_processed_post_id > 0) {
            $args['offset_query'] = ['ID >' => $last_processed_post_id];
        }

        // Add filter for WP_Query to handle ID offset
        $where_filter = function($where, $query) {
            global $wpdb;
            $offset_id_data = $query->get('offset_query');
            if ($offset_id_data && isset($offset_id_data['ID >'])) {
                $where .= $wpdb->prepare(" AND {$wpdb->posts}.ID > %d", $offset_id_data['ID >']);
            }
            return $where;
        };
        add_filter('posts_where', $where_filter, 10, 2);

        $query = new WP_Query( $args );

        // Remove the filter immediately after WP_Query has constructed its SQL
        remove_filter('posts_where', $where_filter, 10);

        $processed_this_run = 0;
        $current_batch_last_id = $last_processed_post_id;

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $post_id = get_the_ID();
                $this->scan_single_post_and_store_results($post_id);
                $current_batch_last_id = $post_id;
                $processed_this_run++;
            }
            wp_reset_postdata();
            update_option('aipu_blc_last_processed_id', $current_batch_last_id);
            AIPU_Logger::log( "External link check: Processed {$processed_this_run} posts. Last ID in this batch: {$current_batch_last_id}." );
        } else {
            // No more posts in this cycle
            delete_option('aipu_blc_last_processed_id'); // Reset for next full scan from the beginning
            AIPU_Logger::log( 'External link check: All posts processed. Cycle complete. Last processed ID reset.' );
        }
    }

    /**
     * Scans a single post, checks its links, and stores broken link data in post meta.
     * @param int $post_id
     */
    public function scan_single_post_and_store_results($post_id) {
        AIPU_Logger::log( "Scanning post ID: {$post_id} for broken links.", 'DEBUG' );
        $links = $this->extract_links_from_post( $post_id );
        $broken_links_found = [];

        // Check internal links
        foreach ( $links['internal'] as $link_data ) {
            if ( ! $this->check_internal_link( $link_data['url'] ) ) {
                $link_data['status'] = 'Broken (Internal)';
                $link_data['last_checked'] = current_time('timestamp');
                $broken_links_found[] = $link_data;
                AIPU_Logger::log( "Post {$post_id}: Found broken internal link: {$link_data['url']}", 'INFO' );
            }
        }

        // Check external links
        foreach ( $links['external'] as $link_data ) {
            $status_code = $this->check_external_link( $link_data['url'] );
            if ( $status_code >= 400 || $status_code === 0 ) { // 0 for connection errors
                $link_data['status'] = $status_code === 0 ? 'Unreachable' : "HTTP {$status_code}";
                $link_data['last_checked'] = current_time('timestamp');
                $broken_links_found[] = $link_data;
                AIPU_Logger::log( "Post {$post_id}: Found broken/unreachable external link: {$link_data['url']} (Status: {$link_data['status']})", 'INFO' );
            }
             // Optional: Add a small delay to avoid overwhelming external servers
             // sleep(1); // Be mindful of cron execution time limits
        }

        if ( ! empty( $broken_links_found ) ) {
            update_post_meta( $post_id, self::POST_META_KEY_BROKEN_LINKS, $broken_links_found );
        } else {
            delete_post_meta( $post_id, self::POST_META_KEY_BROKEN_LINKS );
        }
    }

    /**
     * Clear broken link data for a post when it's saved, so it gets re-checked.
     */
    public function clear_broken_link_data_on_save($post_id) {
         if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
             return;
         }
         // Check if the current user has permission to edit the post, and it's a post type we care about
         if (current_user_can('edit_post', $post_id) && get_post_type($post_id) === 'post') {
              delete_post_meta( $post_id, self::POST_META_KEY_BROKEN_LINKS );
              AIPU_Logger::log( "Cleared broken link cache for post {$post_id} on save.", 'DEBUG' );
         }
    }

    public function update_link_in_content( $post_content, $old_url, $new_url, $anchor_text ) {
        if ( empty($post_content) || empty($old_url) || empty($new_url) ) {
            return $post_content;
        }
        $doc = new DOMDocument();
        // Suppress errors due to malformed HTML and ensure UTF-8
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $post_content);
        $xpath = new DOMXPath($doc);
        $modified = false;

        // Find <a> tags with the specific old_url
        // Using contains for anchor text can be problematic if anchor itself has HTML.
        // For simplicity, we'll look for exact text match for now.
        // A more robust solution might involve more complex XPath or DOM traversal.
        $links = $xpath->query('//a[@href="' . esc_attr($old_url) . '"]');

        foreach ($links as $link_node) {
            $current_anchor_text = trim($link_node->nodeValue);
            // If anchor_text is provided, we try to match it.
            // If anchor_text is empty, this condition is less strict, matching any link with old_url.
            // This might need refinement if multiple identical URLs have different anchors.
            if(empty($anchor_text) || $current_anchor_text === trim($anchor_text) ) {
                $link_node->setAttribute('href', esc_url_raw($new_url));
                $modified = true;
                // If we only want to update the first match that meets criteria:
                // break;
            }
        }

        if ($modified) {
            $body_node = $xpath->query('//body')->item(0);
            if ($body_node) {
                $new_content = '';
                foreach ($body_node->childNodes as $childNode) {
                    $new_content .= $doc->saveHTML($childNode);
                }
                return $new_content;
            }
        }
        return $post_content;
    }

    public function unlink_in_content( $post_content, $url_to_remove, $anchor_text ) {
        if ( empty($post_content) || empty($url_to_remove) ) {
            return $post_content;
        }
        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $post_content);
        $xpath = new DOMXPath($doc);
        $modified = false;

        $links = $xpath->query('//a[@href="' . esc_attr($url_to_remove) . '"]');
        $nodes_to_replace = [];

        foreach ($links as $link_node) {
            $current_anchor_text = trim($link_node->nodeValue);
            if(empty($anchor_text) || $current_anchor_text === trim($anchor_text) ) {
                $nodes_to_replace[] = $link_node;
                 // If we only want to unlink the first match:
                 // break;
            }
        }

        foreach ($nodes_to_replace as $link_node) {
            // Create a text node from the link's current text content (anchor text)
            // This ensures HTML entities within anchor are preserved as text
            $text_content = $link_node->nodeValue;
            $text_node = $doc->createTextNode($text_content);

            if ($link_node->parentNode) {
                $link_node->parentNode->replaceChild($text_node, $link_node);
                $modified = true;
            }
        }

        if ($modified) {
            $body_node = $xpath->query('//body')->item(0);
            if ($body_node) {
                $new_content = '';
                foreach ($body_node->childNodes as $childNode) {
                    $new_content .= $doc->saveHTML($childNode);
                }
                return $new_content;
            }
        }
        return $post_content;
    }

    public function remove_specific_broken_link_from_meta( $post_id, $broken_url_to_remove ) {
        $broken_links = get_post_meta( $post_id, self::POST_META_KEY_BROKEN_LINKS, true );
        if ( !is_array($broken_links) || empty($broken_links) ) {
            return false;
        }
        $updated_links = [];
        $found = false;
        foreach ( $broken_links as $link_data ) {
            if ( $link_data['url'] === $broken_url_to_remove ) {
                $found = true;
                AIPU_Logger::log("Removing '{$broken_url_to_remove}' from broken link meta for post {$post_id}.", "INFO");
                continue; // Skip adding this link to updated_links
            }
            $updated_links[] = $link_data;
        }

        if ($found) {
            if ( empty( $updated_links ) ) {
                delete_post_meta( $post_id, self::POST_META_KEY_BROKEN_LINKS );
                AIPU_Logger::log("All broken links resolved/removed for post {$post_id}. Meta deleted.", "INFO");
            } else {
                update_post_meta( $post_id, self::POST_META_KEY_BROKEN_LINKS, $updated_links );
            }
            return true;
        }
        return false;
    }
}
?>
