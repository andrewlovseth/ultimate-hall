<section class="members grid">
    <div class="member-grid">

        <?php
            function us_posts_where( $where ) {                    
                $where = str_replace("meta_key = 'us_championships_$", "meta_key LIKE 'us_championships_%", $where);
                return $where;
            }

            function wfdf_posts_where( $where ) {                    
                $where = str_replace("meta_key = 'wfdf_championships_$", "meta_key LIKE 'wfdf_championships_%", $where);
                return $where;
            }
            add_filter('posts_where', 'us_posts_where');

            add_filter('posts_where', 'wfdf_posts_where');
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
            if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post(); ?>

            <?php get_template_part('template-parts/global/member'); ?>


        <?php endwhile; endif; wp_reset_postdata(); ?>

    </div>
</section>