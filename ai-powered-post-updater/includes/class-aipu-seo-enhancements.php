<?php
/**
 * AI Powered Post Updater SEO Enhancements
 *
 * @package AIPU
 * @since   0.1.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class AIPU_SEO_Enhancements {

    public function __construct() {
        // Add hook for schema markup if the feature is enabled
        $options = get_option('aipu_settings');
        if (isset($options['enable_seo_enhancements']) && $options['enable_seo_enhancements']) {
             add_action( 'wp_head', [ $this, 'add_article_schema_markup' ] );
        }
    }

    /**
     * Suggests internal links based on post keywords.
     *
     * @param int $current_post_id The ID of the current post.
     * @param array $keywords Keywords from the current post.
     * @param int $limit Max number of suggestions.
     * @return array Array of suggested posts [['id' => ..., 'title' => ..., 'link' => ...], ...] or error/info.
     */
    public function suggest_internal_links( $current_post_id, $keywords, $limit = 5 ) {
        if ( empty( $keywords ) ) {
            return ['info' => 'No keywords provided for internal link suggestions.'];
        }

        $suggestions = [];
        $query_args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'post__not_in'   => [ $current_post_id ], // Exclude current post
            's'              => implode( ' ', $keywords ), // Search by keywords
        ];

        $related_posts_query = new WP_Query( $query_args );

        if ( $related_posts_query->have_posts() ) {
            while ( $related_posts_query->have_posts() ) {
                $related_posts_query->the_post();
                $suggestions[] = [
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                    'link' => get_permalink(),
                    'message' => 'Consider linking to this relevant post: "' . get_the_title() . '"'
                ];
            }
            wp_reset_postdata();
        } else {
            return ['info' => 'No relevant internal links found for the given keywords.'];
        }
        return $suggestions;
    }

    /**
     * Checks for images in post content without alt text.
     *
     * @param string $post_content HTML content of the post.
     * @param string $post_title The title of the post (for default alt text).
     * @return array Array of suggestions for missing alt text.
     *               Example: [['type' => 'missing_alt_text', 'image_src' => '...', 'suggested_alt' => '...'], ...]
     */
    public function check_image_alt_text( $post_content, $post_title = '' ) {
        $suggestions = [];
        if ( empty( $post_content ) ) return $suggestions;

        $doc = new DOMDocument();
        @$doc->loadHTML( '<?xml encoding="UTF-8">' . $post_content ); // Ensure proper encoding
        $images = $doc->getElementsByTagName('img');

        foreach ( $images as $image ) {
            $alt_text = $image->getAttribute('alt');
            $src = $image->getAttribute('src');

            if ( trim( $alt_text ) === '' ) {
                // Suggest alt text based on filename or post title
                $suggested_alt = $post_title; // Default to post title
                $filename = basename( $src );
                // Try to make a slightly better suggestion from filename
                if ( $filename && strpos($filename, '.') !== false ) {
                    $filename_parts = explode('.', $filename);
                    array_pop($filename_parts); // remove extension
                    $cleaned_filename = str_replace(['-', '_'], ' ', implode(' ', $filename_parts));
                    $suggested_alt = ucwords(trim($cleaned_filename));
                    if (empty($suggested_alt)) $suggested_alt = $post_title; // fallback
                }

                $suggestions[] = [
                    'type' => 'missing_alt_text',
                    'image_src' => $src,
                    'suggested_alt' => $suggested_alt,
                    'message' => "Image '{$src}' is missing alt text. Suggested: '{$suggested_alt}'."
                ];
            }
        }
        return $suggestions;
    }

    /**
     * Generates and outputs basic Article schema.org markup in JSON-LD format.
     * Hooked to wp_head for single posts.
     */
    public function add_article_schema_markup() {
        if ( is_single() && get_post_type() === 'post' ) { // Only for single blog posts
            global $post;
            if (!$post) return;

            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'Article', // Or BlogPosting
                'mainEntityOfPage' => [
                    '@type' => 'WebPage',
                    '@id' => get_permalink( $post->ID ),
                ],
                'headline' => get_the_title( $post->ID ),
                'datePublished' => get_the_date( 'c', $post->ID ), // ISO 8601 format
                'dateModified' => get_the_modified_date( 'c', $post->ID ), // ISO 8601 format
                'author' => [
                    '@type' => 'Person', // Or Organization
                    'name' => get_the_author_meta( 'display_name', $post->post_author ),
                ],
                // Publisher (can be Organization or Person)
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => get_bloginfo( 'name' ),
                    'logo' => [ // Optional: Add if logo URL is available
                        '@type' => 'ImageObject',
                        'url' => function_exists('get_custom_logo') && has_custom_logo() ? wp_get_attachment_image_src(get_theme_mod('custom_logo'), 'full')[0] : '',
                    ],
                ],
                'description' => get_the_excerpt( $post->ID ) ? strip_tags(get_the_excerpt( $post->ID )) : wp_trim_words(strip_tags($post->post_content), 55, '...'),
            ];

            // Add image if post has a thumbnail
            if ( has_post_thumbnail( $post->ID ) ) {
                $thumbnail_id = get_post_thumbnail_id( $post->ID );
                $image_url = wp_get_attachment_image_url( $thumbnail_id, 'full' );
                if ($image_url) {
                    $schema['image'] = [
                        '@type' => 'ImageObject',
                        'url' => $image_url,
                        // 'width' => ..., // Optional
                        // 'height' => ..., // Optional
                    ];
                }
            }

            // Remove empty values from schema
            $schema['publisher']['logo']['url'] = $schema['publisher']['logo']['url'] ?: null;
            if (empty($schema['publisher']['logo']['url'])) {
                 unset($schema['publisher']['logo']);
            }
            if (empty($schema['publisher']['logo'])) {
                // If no logo, ensure publisher name is still there
                $schema['publisher'] = ['@type' => 'Organization', 'name' => get_bloginfo('name')];
            }


            echo "
" . '<script type="application/ld+json">' . "
";
            echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
            echo "
" . '</script>' . "
";
        }
    }

    /**
     * Gathers all SEO suggestions for a given post.
     * @param int $post_id
     * @return array
     */
    public function get_all_seo_suggestions($post_id) {
         $suggestions = [];
         $post_obj = get_post($post_id);
         if (!$post_obj) return ['error' => 'Invalid post ID.'];

         // Content Analyzer for keywords
         $content_analyzer = new AIPU_Content_Analyzer($post_id);
         $keywords = $content_analyzer->extract_keywords(5); // Get top 5 keywords

         // Internal Links
         if (!empty($keywords)) {
             $internal_link_suggestions = $this->suggest_internal_links($post_id, $keywords);
             if (!isset($internal_link_suggestions['info']) && !isset($internal_link_suggestions['error'])) {
                 $suggestions = array_merge($suggestions, $internal_link_suggestions);
             } else if (isset($internal_link_suggestions['info'])) {
                 $suggestions[] = ['type' => 'internal_links_info', 'message' => $internal_link_suggestions['info']];
             }
         } else {
             $suggestions[] = ['type' => 'internal_links_info', 'message' => 'Could not extract keywords to suggest internal links.'];
         }

         // Image Alt Text
         $alt_text_suggestions = $this->check_image_alt_text($post_obj->post_content, $post_obj->post_title);
         if (!empty($alt_text_suggestions)) {
             $suggestions = array_merge($suggestions, $alt_text_suggestions);
         } else {
              $suggestions[] = ['type' => 'alt_text_info', 'message' => 'All images seem to have alt text or no images found.'];
         }

         // Schema is automatically added, but we can confirm it's active
         $options = get_option('aipu_settings');
         if (isset($options['enable_seo_enhancements']) && $options['enable_seo_enhancements']) {
             $suggestions[] = ['type' => 'schema_info', 'message' => 'Article schema markup is active and will be added to the post head.'];
         }


         return $suggestions;
    }

}
?>
