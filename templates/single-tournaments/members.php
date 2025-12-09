<section class="members grid">
    <div class="member-grid">

        <?php
            // Add filters to support ACF repeater field wildcards for both championship types
            $us_filter = bearsmith_modify_repeater_meta_query('us_championships');
            $wfdf_filter = bearsmith_modify_repeater_meta_query('wfdf_championships');
            add_filter('posts_where', $us_filter);
            add_filter('posts_where', $wfdf_filter);

            $args = array(
                'post_type' => 'member',
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key'		=> 'us_championships_$_tournament',
                        'compare'	=> '=',
                        'value'		=> get_the_ID(),
                    ),
                    array(
                        'key'		=> 'wfdf_championships_$_tournament',
                        'compare'	=> '=',
                        'value'		=> get_the_ID(),
                    ),
                )
            );
            $query = new WP_Query( $args );

            // Remove filters after query to prevent affecting other queries
            remove_filter('posts_where', $us_filter);
            remove_filter('posts_where', $wfdf_filter);

            if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post(); ?>

            <?php get_template_part('template-parts/global/member'); ?>


        <?php endwhile; endif; wp_reset_postdata(); ?>

    </div>
</section>