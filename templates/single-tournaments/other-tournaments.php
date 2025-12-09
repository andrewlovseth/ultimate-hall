<?php

    $year = get_field('details_year');

?>

<section class="other-tournaments grid">

    <div class="content">
        <div class="section-sub-header">
            <h3><?php echo get_the_title($year); ?> Tournaments</h3>
        </div>

        <div class="tournaments-list">
            <?php
                $this_tournament = get_the_ID();
                $args = bearsmith_default_query_args('tournaments', array(
                    'meta_query' => array(
                        array(
                            'key' => 'details_year',
                            'value' => $year,
                            'compare' => '='
                        )
                    )
                ));
                $query = new WP_Query( $args );
                if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post(); ?>

                    <div class="tournament<?php if(get_the_ID() == $this_tournament): ?> active<?php endif; ?>">
                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                    </div>

            <?php endwhile; endif; wp_reset_postdata(); ?>
        </div>            
    </div>

</section>