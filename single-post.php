<?php get_header(); ?>

	<?php if ( have_posts() ): while ( have_posts() ): the_post(); ?>

		<article <?php post_class( 'grid' ); ?>>
			<div class="article-header">
				<div class="date">
                    <span><?php the_time('F j, Y'); ?></span>
                </div>

				<div class="title">
					<h1><?php the_title(); ?></h1>
				</div>
			</div>

			<div class="article-body copy p1 extended">
				<?php the_content(); ?>

			</div>
			
		</article>

	<?php endwhile; endif; ?>

<?php get_footer(); ?>