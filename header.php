<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div id="page" class="site">
	
	<header class="site-header grid">
		<div class="logo">
			<a href="<?php echo site_url('/'); ?>"><img src="<?php $image = get_field('header_logo', 'options'); echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" /></a>
		</div>
		
		<nav class="site-nav">
			<?php if(have_rows('navigation', 'options')): while(have_rows('navigation', 'options')): the_row(); ?>
				<?php 
					$link = get_sub_field('link');
					if( $link ): 
					$link_url = $link['url'];
					$link_title = $link['title'];
					$link_target = $link['target'] ? $link['target'] : '_self';
				?>

					<div class="link">
						<a href="<?php echo esc_url($link_url); ?>" target="<?php echo esc_attr($link_target); ?>"><?php echo esc_html($link_title); ?></a>
					</div>
								
				<?php endif; ?>

			<?php endwhile; endif; ?>
		</nav>
	</header>

	<main class="site-content">