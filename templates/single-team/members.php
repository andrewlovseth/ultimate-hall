<section class="members grid">
    <div class="member-grid">

        <?php
            function my_posts_where( $where ) {                    
                $where = str_replace("meta_key = 'playing_career_$", "meta_key LIKE 'playing_career_%", $where);
                return $where;
            }

            add_filter('posts_where', 'my_posts_where');
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
            if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post(); ?>

            <?php get_template_part('template-parts/global/member'); ?>


        <?php endwhile; endif; wp_reset_postdata(); ?>

    </div>
</section>