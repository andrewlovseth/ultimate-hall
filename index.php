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
                    <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                </div>
            </article>

        <?php endwhile; endif; ?>

    </section>


<?php get_footer(); ?>