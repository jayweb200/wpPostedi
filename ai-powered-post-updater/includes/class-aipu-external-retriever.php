<?php
/**
 * AI Powered Post Updater External Information Retriever
 *
 * @package AIPU
 * @since   0.1.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class AIPU_External_Retriever {

    public function __construct() {
        // Constructor, if needed for API keys or settings in the future
    }

    /**
     * Performs a web search for given keywords to find recent, relevant information.
     *
     * NOTE: This is a placeholder. In a real plugin, this would integrate
     * with a search engine API (e.g., Google Custom Search API, Bing Search API)
     * or a dedicated scraping service.
     * For now, it will return a mock result.
     *
     * @param array $keywords Keywords to search for.
     * @param int $count Number of results to aim for.
     * @return array An array of search result items (e.g., ['title' => ..., 'link' => ..., 'snippet' => ...]).
     */
    public function search_web_for_updates( $keywords, $count = 3 ) {
        if ( empty( $keywords ) ) {
            return [];
        }

        $search_query = implode( ' ', $keywords );
        // Example: Constructing a Google search URL (for demonstration, not for actual scraping)
        // $google_search_url = 'https://www.google.com/search?q=' . urlencode( $search_query . ' recent updates' );

        // MOCK RESULTS (replace with actual API call in a real scenario)
        $mock_results = [];
        for ( $i = 1; $i <= $count; $i++ ) {
            $mock_results[] = [
                'title' => 'Mock Result ' . $i . ' for "' . esc_html( $search_query ) . '"',
                'link' => '#mock-link-' . $i,
                'snippet' => 'This is a mock snippet for the search query: ' . esc_html( $search_query ) . '. It represents a recent update found on the web. In a real plugin, this would come from a search engine API.',
                'source' => 'Mock Source ' . $i,
            ];
        }

        // Simulate a delay as if an API call was made
        // sleep(1);
        // In a real WordPress context, avoid long sleeps directly in request handling.
        // Use background processing for long tasks.

        // Log the attempt (optional)
        // error_log("AIPU: Simulated web search for keywords: " . $search_query);

        return $mock_results;
    }

    /**
     * Scans a specific URL for new articles or updates.
     *
     * @param string $url The URL to scan.
     * @return array An array of found items (e.g., ['title' => ..., 'link' => ..., 'summary' => ...]) or an empty array.
     */
    public function scan_linked_website( $url ) {
        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return ['error' => 'Invalid URL provided.'];
        }

        $response = wp_remote_get( $url, [ 'timeout' => 15 ] ); // 15 second timeout

        if ( is_wp_error( $response ) ) {
            return ['error' => 'Failed to fetch URL: ' . $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            return ['error' => 'Fetched URL returned empty content.'];
        }

        // Basic HTML Parsing (very fragile and site-dependent)
        // This is a simplified example. Robust parsing requires a proper DOM parser
        // and more sophisticated logic to identify relevant content.
        $found_items = [];
        $doc = new DOMDocument();
        @$doc->loadHTML( $body ); // Suppress errors from malformed HTML

        $xpath = new DOMXPath( $doc );

        // Try to find <article> tags - common for blog posts / news
        $articles = $xpath->query( '//article' );
        $item_limit = 3; // Limit the number of items to extract

        if ( $articles && $articles->length > 0 ) {
            foreach ( $articles as $article_node ) {
                if ( count( $found_items ) >= $item_limit ) break;

                $title_node = $xpath->query( './/h1 | .//h2 | .//h3', $article_node )->item(0);
                $title = $title_node ? trim( $title_node->nodeValue ) : 'Untitled Article';

                $link_node = $xpath->query( './/a[h1 or h2 or h3]', $article_node)->item(0); // Link wrapping a heading
                if (!$link_node) {
                     $link_node = $xpath->query( './/h1/a | .//h2/a | .//h3/a', $article_node)->item(0); // Link inside a heading
                }
                $link = $link_node ? $link_node->getAttribute('href') : $url;
                // Ensure link is absolute
                if ( $link && !preg_match('/^https?:\/\//i', $link) ) {
                    $url_parts = parse_url($url);
                    $base_url = (isset($url_parts['scheme']) ? $url_parts['scheme'] : 'http') . '://' . $url_parts['host'];
                    $link = $base_url . (strpos($link, '/') === 0 ? $link : '/' . $link);
                }


                // Try to get a summary - first <p> tag or a meta description
                $summary_node = $xpath->query( './/p', $article_node )->item(0);
                $summary = $summary_node ? trim( $summary_node->nodeValue ) : '';
                if ( strlen( $summary ) > 150 ) {
                    $summary = substr( $summary, 0, 147 ) . '...';
                }

                if ($title !== 'Untitled Article' || $summary !== '') {
                    $found_items[] = [
                        'title' => $title,
                        'link' => $link,
                        'summary' => $summary,
                        'source' => parse_url($url, PHP_URL_HOST) // Domain name as source
                    ];
                }
            }
        } else {
            // Fallback: Try to get page title if no articles found
            $title_nodes = $doc->getElementsByTagName('title');
            $page_title = $title_nodes->length > 0 ? trim($title_nodes->item(0)->nodeValue) : 'Page from ' . parse_url($url, PHP_URL_HOST);
            $found_items[] = [
                'title' => $page_title,
                'link' => $url,
                'summary' => 'Content fetched from the site. Further analysis might be needed.',
                'source' => parse_url($url, PHP_URL_HOST)
            ];
        }

        if (empty($found_items)) {
            return ['info' => 'Could not extract distinct articles using basic parsing. The page content was fetched.'];
        }

        return $found_items;
    }

     /**
      * Extracts outbound links from post content.
      * @param string $post_content The HTML content of the post.
      * @return array An array of unique URLs.
      */
     public function extract_outbound_links( $post_content ) {
         if ( empty( $post_content ) ) {
             return [];
         }

         $links = [];
         $doc = new DOMDocument();
         @$doc->loadHTML( '<?xml encoding="UTF-8">' . $post_content ); // Add encoding to handle special chars better

         $anchor_tags = $doc->getElementsByTagName('a');

         foreach ( $anchor_tags as $tag ) {
             if ( $tag->hasAttribute('href') ) {
                 $href = $tag->getAttribute('href');
                 // Basic validation and filtering (e.g., ignore internal links, mailto, javascript)
                 if ( filter_var( $href, FILTER_VALIDATE_URL ) &&
                      strpos( $href, home_url() ) === false && // Not an internal link
                      !preg_match('/^(mailto|javascript|tel):/i', $href) ) {
                     $links[] = $href;
                 }
             }
         }
         return array_unique( $links );
     }
}
?>
