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

				<?php $authors = get_field('author'); if($authors): ?>
					<div class="byline">
						<?php foreach($authors as $author): ?>
							<?php 
								$image = get_field('photos_headshot', $author->ID); 
							?>

							<div class="author">
								<div class="photo">
									<a href="<?php echo get_permalink($author->ID); ?>">
										<?php if($image): ?>
											<img src="<?php echo $image['sizes']['thumbnail']; ?>" alt="<?php echo $image['alt']; ?>" />
										<?php else: ?>
											<div class="empty"></div>
										<?php endif; ?>
									</a>
								</div>

								<div class="info">
									<a href="<?php echo get_permalink($author->ID); ?>"><?php echo get_the_title($author->ID); ?></a>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="article-body copy p1 extended">
				<?php the_content(); ?>

			</div>
			
		</article>

	<?php endwhile; endif; ?>

<?php get_footer(); ?>