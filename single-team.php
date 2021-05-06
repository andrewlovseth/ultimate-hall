<?php get_header(); ?>


    <section class="page-header grid">
        <h1><?php the_title(); ?></h1>

        <div class="location sub-title">
            <h2><?php the_field('city'); ?></h2>
        </div>
        
    </section>

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

                <div class="member">
                    <div class="photo">
                        <a href="<?php the_permalink(); ?>">
                            <div class="content">
                                <img src="<?php $image = get_field('photos_headshot'); echo $image['sizes']['thumbnail']; ?>" alt="<?php echo $image['alt']; ?>" />
                            </div>
                        </a>
                    </div>

                    <div class="info">
                        <div class="name">
                            <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                        </div>
                    </div>


                </div>

            <?php endwhile; endif; wp_reset_postdata(); ?>

        </div>
    </section>

<?php get_footer(); ?>