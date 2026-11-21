<?php
/**
 * Query Helper Functions
 *
 * Helper functions for modifying WordPress queries
 */

/**
 * Get default query arguments with common settings
 *
 * Provides standard query arguments used across the theme to reduce duplication.
 * Default behavior: get all posts of a type, ordered alphabetically by title.
 *
 * @param string $post_type Post type to query (default: 'post')
 * @param array  $custom_args Optional. Additional arguments to merge with defaults.
 * @return array Merged query arguments ready for WP_Query or get_posts
 *
 * @example
 * // Basic usage - get all members alphabetically
 * $args = bearsmith_default_query_args('member');
 * $query = new WP_Query($args);
 *
 * @example
 * // With custom arguments - add meta_query
 * $args = bearsmith_default_query_args('member', array(
 *     'meta_query' => array(
 *         array(
 *             'key' => 'featured',
 *             'value' => '1'
 *         )
 *     )
 * ));
 *
 * @example
 * // Override defaults - limit to 10 posts
 * $args = bearsmith_default_query_args('events', array(
 *     'posts_per_page' => 10
 * ));
 */
function bearsmith_default_query_args($post_type = 'post', $custom_args = array()) {
    $defaults = array(
        'post_type'      => $post_type,
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC'
    );

    return array_merge($defaults, $custom_args);
}
