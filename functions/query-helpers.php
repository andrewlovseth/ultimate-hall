<?php
/**
 * Query Helper Functions
 *
 * Helper functions for modifying WordPress queries
 */

/**
 * Modify meta query to support ACF repeater field wildcards
 *
 * This helper modifies the WHERE clause to allow querying ACF repeater fields
 * by converting the $ wildcard to a SQL LIKE pattern with %.
 *
 * @param string $meta_key_prefix The meta key prefix to match (e.g., 'playing_career')
 * @return callable Anonymous function to use as posts_where filter
 *
 * @example
 * // Usage in templates:
 * add_filter('posts_where', bearsmith_modify_repeater_meta_query('playing_career'));
 * $query = new WP_Query($args);
 * remove_filter('posts_where', bearsmith_modify_repeater_meta_query('playing_career'));
 */
function bearsmith_modify_repeater_meta_query($meta_key_prefix) {
    return function($where) use ($meta_key_prefix) {
        $where = str_replace(
            "meta_key = '{$meta_key_prefix}_$",
            "meta_key LIKE '{$meta_key_prefix}_%",
            $where
        );
        return $where;
    };
}
