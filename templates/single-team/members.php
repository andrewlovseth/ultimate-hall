<section class="members grid">
    <div class="member-grid">

        <?php
            // Add filter to support ACF repeater field wildcards
            $filter = bearsmith_modify_repeater_meta_query('playing_career');
            add_filter('posts_where', $filter);

            $args = array(
                'post_type' => 'member',
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
                'meta_query' => array(
                    array(
                        'key'		=> 'playing_career_$_team',
                        'compare'	=> '=',
                        'value'		=> get_the_ID(),
                    ),
                )
            );
            $query = new WP_Query( $args );

            // Remove filter after query to prevent affecting other queries
            remove_filter('posts_where', $filter);

            if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post(); ?>

            <?php get_template_part('template-parts/global/member'); ?>


        <?php endwhile; endif; wp_reset_postdata(); ?>

    </div>
</section>