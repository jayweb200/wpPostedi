<?php
/**
 * AI Powered Post Updater Content Refiner
 *
 * @package AIPU
 * @since   0.1.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class AIPU_Content_Refiner {

    // Basic thesaurus for demonstration
    private $thesaurus = [
        'good' => ['great', 'excellent', 'positive', 'beneficial'],
        'bad' => ['poor', 'negative', 'detrimental', 'harmful'],
        'important' => ['significant', 'crucial', 'vital', 'essential'],
        'very' => ['extremely', 'highly', 'remarkably', 'exceedingly'],
        'use' => ['utilize', 'employ', 'apply', 'leverage'],
        'help' => ['assist', 'support', 'aid', 'facilitate'],
        'show' => ['demonstrate', 'illustrate', 'reveal', 'indicate'],
    ];

    // Common English auxiliary verbs (forms of 'to be', 'to have', 'to do')
    // and modals used in passive voice detection.
    private $auxiliary_verbs = [
         'am', 'is', 'are', 'was', 'were', 'be', 'being', 'been',
         'has', 'have', 'had',
         'do', 'does', 'did',
         'will', 'would', 'shall', 'should', 'may', 'might', 'must', 'can', 'could'
    ];


    public function __construct() {
        // Future: Load settings if needed
    }

    /**
     * Analyzes text for readability issues (long sentences, long paragraphs).
     *
     * @param string $text_content The plain text content.
     * @return array An array of suggestions.
     *               Example: [['type' => 'long_sentence', 'sentence' => '...', 'word_count' => ...], ...]
     */
    public function analyze_readability( $text_content ) {
        $suggestions = [];
        if ( empty( $text_content ) ) return $suggestions;

        // Sentence Segmentation (basic: using '.', '!', '?')
        // More robust sentence tokenization is complex.
        $sentences = preg_split('/[.!?]+/', $text_content, -1, PREG_SPLIT_NO_EMPTY);
        $max_sentence_words = 25; // Configurable threshold

        foreach ( $sentences as $sentence ) {
            $sentence = trim($sentence);
            if (empty($sentence)) continue;

            $words = preg_split('/\s+/', $sentence, -1, PREG_SPLIT_NO_EMPTY);
            $word_count = count( $words );
            if ( $word_count > $max_sentence_words ) {
                $suggestions[] = [
                    'type' => 'long_sentence',
                    'sentence' => $sentence,
                    'message' => "This sentence has {$word_count} words. Consider shortening it for better readability (recommended max: {$max_sentence_words})."
                ];
            }
        }

        // Paragraph Segmentation (basic: using double line breaks)
        $paragraphs = preg_split( '/
\s*
/', $text_content, -1, PREG_SPLIT_NO_EMPTY );
        $max_paragraph_sentences = 5; // Configurable threshold

        foreach ( $paragraphs as $paragraph ) {
            $paragraph = trim($paragraph);
            if (empty($paragraph)) continue;

            $paragraph_sentences = preg_split('/[.!?]+/', $paragraph, -1, PREG_SPLIT_NO_EMPTY);
            $sentence_count = count( array_filter($paragraph_sentences, 'trim') ); // count non-empty sentences
            if ( $sentence_count > $max_paragraph_sentences ) {
                $suggestions[] = [
                    'type' => 'long_paragraph',
                    'paragraph_excerpt' => substr( $paragraph, 0, 100 ) . '...',
                    'message' => "This paragraph has {$sentence_count} sentences. Consider breaking it into smaller paragraphs (recommended max: {$max_paragraph_sentences})."
                ];
            }
        }
        return $suggestions;
    }

    /**
     * Detects potential passive voice usage.
     * This is a simplified check and may not be 100% accurate.
     *
     * @param string $text_content The plain text content.
     * @return array An array of sentences suspected of using passive voice.
     *               Example: [['type' => 'passive_voice', 'sentence' => '...'], ...]
     */
    public function detect_passive_voice( $text_content ) {
        $suggestions = [];
        if ( empty( $text_content ) ) return $suggestions;

        $sentences = preg_split('/[.!?]+/', $text_content, -1, PREG_SPLIT_NO_EMPTY);

        foreach ( $sentences as $sentence ) {
            $sentence = trim($sentence);
            if (empty($sentence)) continue;

            // Regex: (form of 'to be') followed by optional adverbs then a word ending in 'ed' or common irregular past participles.
            // This is a common, but not foolproof, pattern for passive voice.
            // (?:...) is a non-capturing group. \b is a word boundary.
            // Common irregular past participles need to be listed or handled by a more advanced library.
            // For simplicity, we'll mainly rely on "be + *ed".
            $passive_pattern = '/\b(?:' . implode('|', $this->auxiliary_verbs) . ')\b\s+(?:\w+\s+)?\w+ed\b/i';
            // A more specific pattern for common irregulars (very limited list)
            $passive_pattern_irregular = '/\b(?:' . implode('|', $this->auxiliary_verbs) . ')\b\s+(?:\w+\s+)?(?:given|made|seen|taken|written|broken|chosen|driven|eaten|spoken)\b/i';


            if ( preg_match( $passive_pattern, $sentence ) || preg_match( $passive_pattern_irregular, $sentence ) ) {
                // Further check: ensure it's not an adjective (e.g., "the cat was tired")
                // This is hard without POS tagging. For now, we accept some false positives.
                $suggestions[] = [
                    'type' => 'passive_voice',
                    'sentence' => $sentence,
                    'message' => "This sentence might be using passive voice: '{$sentence}'. Consider rephrasing in active voice for more directness."
                ];
            }
        }
        return $suggestions;
    }

    /**
     * Suggests synonyms for frequently used or simple words.
     * Uses a very basic internal thesaurus.
     *
     * @param string $text_content The plain text content.
     * @param int $min_frequency Minimum frequency for a word to be considered for synonym suggestion.
     * @return array An array of synonym suggestions.
     *               Example: [['type' => 'synonym', 'word' => 'good', 'synonyms' => ['great', ...]], ...]
     */
    public function suggest_synonyms( $text_content, $min_frequency = 3 ) {
        $suggestions = [];
        if ( empty( $text_content ) ) return $suggestions;

        $words = str_word_count( strtolower( $text_content ), 1 );
        $word_counts = array_count_values( $words );

        $suggested_for_word = []; // To avoid suggesting for the same word multiple times from different parts of text

        foreach ( $word_counts as $word => $count ) {
            if ( $count >= $min_frequency && isset( $this->thesaurus[$word] ) && !isset($suggested_for_word[$word]) ) {
                $suggestions[] = [
                    'type' => 'synonym_suggestion',
                    'word' => $word,
                    'count' => $count,
                    'synonyms' => $this->thesaurus[$word],
                    'message' => "The word '{$word}' appears {$count} times. Consider using synonyms like: " . implode(', ', $this->thesaurus[$word]) . "."
                ];
                $suggested_for_word[$word] = true;
            }
        }
        return $suggestions;
    }

    /**
     * Combines all content refinement suggestions.
     * @param string $post_id The ID of the post to analyze.
     * @return array All suggestions.
     */
     public function get_all_suggestions( $post_id ) {
         $analyzer = new AIPU_Content_Analyzer( $post_id ); // Assumes this class is available
         $cleaned_content = $analyzer->get_cleaned_content();

         if (empty($cleaned_content)) {
             return ['info' => 'Content is empty or could not be retrieved.'];
         }

         $suggestions = [];
         $suggestions = array_merge($suggestions, $this->analyze_readability( $cleaned_content ));
         $suggestions = array_merge($suggestions, $this->detect_passive_voice( $cleaned_content ));
         $suggestions = array_merge($suggestions, $this->suggest_synonyms( $cleaned_content ));

         return $suggestions;
     }
}
?>
