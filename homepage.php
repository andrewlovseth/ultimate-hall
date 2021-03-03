<?php 

/*
  
    Template Name: Homepage

*/

get_header(); ?>


    <section class="members-list grid">

        <ul>
            <?php
            $args = array(
                'post_type' => 'member',
                'posts_per_page' => 100,
                'orderby' => 'title',
                'order' => 'ASC'
            );
            $query = new WP_Query( $args );
            if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post(); ?>

                <?php 
                    $meta = get_field('meta');
                    $year = $meta['class'];
                ?>


                <li class="member">
                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?> (Class of <?php echo $year->post_name; ?>)</a>
                </li>

            <?php endwhile; endif; wp_reset_postdata(); ?>
        </ul>
    </section>


<?php get_footer(); ?>