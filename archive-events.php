<?php 

$intro_copy = get_field('events_intro_copy', 'option');

get_header(); ?>


    <?php get_template_part('templates/archive-events/hero-image'); ?>

    <section class="page-header align-center grid" id="top">
        <h1>Events</h1>
    </section>

    <section class="intro grid">
        <div class="copy p2">
            <?php echo $intro_copy; ?>
        </div>
    </section>


    <section class="events-list grid">
        <div class="events-list__wrapper">
            <?php
                $args = array(
                    'post_type' => 'events',
                    'posts_per_page' => -1,
                    'post_status' => 'publish, future',
                );
                $query = new WP_Query( $args );
            ?>

            <?php if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post(); ?>

                <?php get_template_part('templates/archive-events/event'); ?>

            <?php endwhile; endif; wp_reset_postdata(); ?>
        </div>
    </section>



<?php get_footer(); ?>