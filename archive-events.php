<?php 

$intro_copy = get_field('events_intro_copy', 'option');

get_header(); ?>


    <?php get_template_part('templates/archive-events/hero-image'); ?>

    <?php
        // Use unified page header with custom title
        get_template_part('template-parts/global/page-header-unified', null, array(
            'title' => 'Events',
            'id' => 'top'
        ));
    ?>

    <section class="intro grid">
        <div class="copy p2">
            <?php echo $intro_copy; ?>
        </div>
    </section>


    <section class="events-list grid">
        <div class="events-list__wrapper">
            <?php
                $args = bearsmith_default_query_args('events', array(
                    'post_status' => 'publish, future',
                ));
                $query = new WP_Query( $args );
            ?>

            <?php if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post(); ?>

                <?php get_template_part('templates/archive-events/event'); ?>

            <?php endwhile; endif; wp_reset_postdata(); ?>
        </div>
    </section>



<?php get_footer(); ?>