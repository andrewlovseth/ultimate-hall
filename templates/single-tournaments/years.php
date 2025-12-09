<section class="years grid">
    <div class="section-header align-center">
        <h3>Explore Ultimate through the Years</h3>
    </div>

    <div class="years-list">
        <?php
            $args = bearsmith_default_query_args('year');
            $query = new WP_Query( $args );
            if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post(); ?>

            <div class="year">
                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
            </div>

        <?php endwhile; endif; wp_reset_postdata(); ?>
    </div>

</section>