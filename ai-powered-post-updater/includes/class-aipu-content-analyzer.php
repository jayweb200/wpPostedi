<?php
/**
 * AI Powered Post Updater Content Analyzer
 *
 * @package AIPU
 * @since   0.1.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class AIPU_Content_Analyzer {

    private $post_id;
    private $post_content;

    public function __construct( $post_id ) {
        $this->post_id = $post_id;
        $this->post_content = $this->get_post_content();
    }

    /**
     * Retrieves the raw content of the post.
     *
     * @return string The post content.
     */
    private function get_post_content() {
        $post = get_post( $this->post_id );
        if ( $post && ! is_wp_error( $post ) ) {
            return $post->post_content;
        }
        return '';
    }

    /**
     * Gets the processed (cleaned) text content of the post.
     * Strips HTML tags and shortcodes.
     *
     * @return string Cleaned text content.
     */
    public function get_cleaned_content() {
        $content = $this->post_content;
        $content = wp_strip_all_tags( $content ); // Strips HTML and PHP tags
        $content = strip_shortcodes( $content );   // Strips shortcodes
        $content = preg_replace( '/\s+/', ' ', $content ); // Replace multiple spaces with single
        $content = trim( $content );
        return $content;
    }

    /**
     * Extracts keywords from the post content.
     *
     * @param int $count The number of top keywords to return.
     * @return array An array of keywords.
     */
    public function extract_keywords( $count = 10 ) {
        $text_content = strtolower( $this->get_cleaned_content() );

        if ( empty( $text_content ) ) {
            return [];
        }

        // Basic stop words list (can be expanded)
        $stop_words = [
            'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from', 'has', 'he',
            'in', 'is', 'it', 'its', 'of', 'on', 'that', 'the', 'to', 'was', 'were',
            'will', 'with', 'i', 'you', 'your', 'me', 'my', 'mine', 'we', 'our', 'us',
            'they', 'them', 'their', 'this', 'these', 'those', 'then', 'than', 'so',
            'if', 'or', 'but', 'about', 'above', 'after', 'again', 'against', 'all',
            'am', 'any', 'because', 'been', 'before', 'being', 'below', 'between',
            'both', 'can', 'cannot', 'could', 'did', 'do', 'does', 'doing', 'down',
            'during', 'each', 'few', 'further', 'had', 'having', 'here', 'how',
            'into', 'just', 'more', 'most', 'no', 'nor', 'not', 'now', 'once',
            'only', 'other', 'ought', 'our', 'ours', 'ourselves', 'out', 'over',
            'own', 'same', 'she', 'should', 'some', 'such', 'than', 'that', 'their',
            'theirs', 'them', 'themselves', 'then', 'there', 'these', 'they', 'this',
            'those', 'through', 'too', 'under', 'until', 'up', 'very', 'was', 'we',
            'were', 'what', 'when', 'where', 'which', 'while', 'who', 'whom', 'why',
            'would', 'yet', 'also', 'able', 'com', 'www'
            // Add more common words as needed
        ];

        // Remove punctuation and split into words
        $words = str_word_count( $text_content, 1 );

        // Filter out stop words and short words (less than 3 chars)
        $filtered_words = [];
        foreach ( $words as $word ) {
            if ( !in_array( $word, $stop_words ) && strlen( $word ) > 2 ) {
                $filtered_words[] = $word;
            }
        }

        if ( empty( $filtered_words ) ) {
            return [];
        }

        // Count word frequencies
        $word_counts = array_count_values( $filtered_words );
        arsort( $word_counts ); // Sort by frequency, descending

        // Get the top N keywords
        return array_slice( array_keys( $word_counts ), 0, $count );
    }

    /**
     * Extracts the main topics or themes from the post.
     * For this basic version, it might be similar to keywords or could involve
     * looking for noun phrases if a more advanced NLP library were available.
     * For now, let's return the top 3 keywords as "topics".
     *
     * @return array An array of topics.
     */
    public function extract_topics( $count = 3 ) {
        // For a more sophisticated approach, one might use sentence analysis,
        // named entity recognition, or TF-IDF across a corpus of documents.
        // Here, we'll just use the top keywords as a proxy for topics.
        return $this->extract_keywords( $count );
    }
}
?>
