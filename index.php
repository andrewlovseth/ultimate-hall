<?php get_header(); ?>

    <section class="page-header grid">
        <h1>News</h1>
    </section>

    <section class="posts grid">

        <?php if ( have_posts() ): while ( have_posts() ): the_post(); ?>

            <article <?php post_class('sub-grid post-teaser'); ?>>
                <div class="meta">
                    <span class="date"><?php the_time('F j, Y'); ?></span>
                </div>

                <div class="info">
                    <div class="headline">
                        <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                    </div>

                    <div class="excerpt copy p3">
                        <?php the_excerpt(); ?>
                    </div>
                </div>
            </article>

        <?php endwhile; ?>

        <?php
            the_posts_pagination( array(
                'screen_reader_text' => 'Archive',
                'format' => '?paged=%#%',
                'mid_size'  => 2,
                'prev_text' => __('Prev'),
                'next_text' => __('Next'),
            ) );
        ?>
    
    <?php endif; ?>

    </section>


<?php get_footer(); ?>