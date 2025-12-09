<?php
/**
 * Member Helper Functions
 *
 * Helper functions for querying and working with member post types
 */

/**
 * Get members by class/year
 *
 * Returns a WP_Query object containing members filtered by their class/year.
 * Default ordering is alphabetical by title (A-Z).
 *
 * @param int   $class_id The class/year post ID to filter members by
 * @param array $args     Optional. Additional WP_Query arguments to merge with defaults.
 *                        Allows overriding any default query parameters.
 * @return WP_Query Query object containing the members
 *
 * @example
 * // Basic usage - get all members from a class
 * $query = bearsmith_get_members_by_class($class_id);
 * if ($query->have_posts()) : while ($query->have_posts()) : $query->the_post();
 *     // Display member
 * endwhile; endif;
 * wp_reset_postdata();
 *
 * @example
 * // With custom arguments - limit to 10 members
 * $query = bearsmith_get_members_by_class($class_id, array(
 *     'posts_per_page' => 10
 * ));
 */
function bearsmith_get_members_by_class($class_id, $args = array()) {
    $default_args = array(
        'post_type'      => 'member',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'meta_query'     => array(
            array(
                'key'     => 'meta_class',
                'compare' => '=',
                'value'   => $class_id,
            ),
        ),
    );

    $merged_args = array_merge($default_args, $args);
    return new WP_Query($merged_args);
}
