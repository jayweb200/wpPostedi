<?php
/**
 * AI Powered Post Updater Trending Keywords
 *
 * @package AIPU
 * @since   0.1.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class AIPU_Trending_Keywords {

    private $api_key;

    public function __construct() {
        $options = get_option( 'aipu_settings' );
        $this->api_key = isset( $options['google_trends_api_key'] ) ? $options['google_trends_api_key'] : '';
    }

    /**
     * Fetches trending keywords related to a given set of primary keywords or topics.
     *
     * NOTE: This is a placeholder. In a real plugin, this would integrate
     * with a Google Trends API or a similar service using the provided API key.
     * For now, it will return mock data if no API key is present, or mock data
     * pretending it used an API key.
     *
     * @param array $base_keywords An array of keywords/topics from the post.
     * @param string $region The region for trends (e.g., 'US'). Defaults to 'US'.
     * @param int $count Number of trending keywords to aim for.
     * @return array An array of trending keyword strings, or an error/info array.
     */
    public function get_trending_keywords( $base_keywords, $region = 'US', $count = 5 ) {
        if ( empty( $this->api_key ) && false ) { // Set to true to enforce API key for mock, false to always return mock
            // In a real scenario, you might want to disable this if no API key is set.
            // return ['info' => 'Google Trends API key is not set. Cannot fetch trending keywords.'];
        }

        if ( empty( $base_keywords ) ) {
            return ['info' => 'No base keywords provided to find related trends.'];
        }

        // MOCK RESULTS (replace with actual API call in a real scenario)
        $mock_trending_keywords = [];
        $base_topic_str = esc_html( implode(', ', $base_keywords) );

        for ( $i = 1; $i <= $count; $i++ ) {
            $mock_trending_keywords[] = "Trending Keyword " . $i . " related to " . $base_topic_str;
        }

        if ( !empty( $this->api_key ) ) {
             // Simulate using the API key
             // error_log("AIPU: Simulated Google Trends API call with key for topics: " . $base_topic_str . " in region " . $region);
        } else {
             // error_log("AIPU: Simulated Google Trends (no API key) for topics: " . $base_topic_str . " in region " . $region);
        }

        // Simulate a delay
        // sleep(1);

        return $mock_trending_keywords;
    }

    /**
     * Formats trending keywords for display or use as tags.
     *
     * @param array $keywords Array of keyword strings.
     * @return array Array of formatted keywords (e.g., trimmed, lowercased).
     */
    public function format_keywords_for_tags( $keywords ) {
        if ( ! is_array( $keywords ) || empty( $keywords ) ) {
            return [];
        }
        $formatted = [];
        foreach ( $keywords as $keyword ) {
            if (is_string($keyword)) {
                $kw = strtolower( trim( $keyword ) );
                // Basic sanitization, WordPress handles more on tag insertion
                $kw = sanitize_text_field( $kw );
                if ( !empty($kw) ) {
                    $formatted[] = $kw;
                }
            }
        }
        return array_unique( $formatted );
    }
}
?>
