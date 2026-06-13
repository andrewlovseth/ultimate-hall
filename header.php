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

<?php
/*
	Split the ACF options "navigation" repeater into the three places a link
	can render. Built once here, then handed to the tray + navigation partials
	via get_template_part() $args so the repeater loop only runs once.

	- button == true      -> donate  (gold CTA: tray right cell + drawer foot)
	- menu == 'secondary' -> secondary (tray left cell + drawer secondary list)
	- everything else     -> primary (brand bar + drawer primary list)

	Rows saved before the `menu` sub-field existed return '' from
	get_sub_field() — those fall through to primary.
*/
$uh_nav = array('primary' => array(), 'secondary' => array(), 'donate' => array());

if (have_rows('navigation', 'options')): while (have_rows('navigation', 'options')): the_row();
	$link = get_sub_field('link');
	if (empty($link['url'])) {
		continue;
	}

	$item = array(
		'url'    => $link['url'],
		'title'  => $link['title'],
		'target' => !empty($link['target']) ? $link['target'] : '_self',
	);

	if (get_sub_field('button')) {
		$uh_nav['donate'][] = $item;
	} elseif ('secondary' === (get_sub_field('menu') ?: 'primary')) {
		$uh_nav['secondary'][] = $item;
	} else {
		$uh_nav['primary'][] = $item;
	}
endwhile; endif;
?>

<div id="page" class="site">

	<?php
	/*
		The tray lives OUTSIDE .site-header on purpose: position: sticky is
		constrained to its parent's box, so inside the ~110px header it would
		stop sticking almost immediately. As a direct child of #page it pins
		for the whole scroll while the brand bar below scrolls away.
	*/
	get_template_part('template-parts/header/tray', null, array('nav' => $uh_nav));
	?>

	<header class="site-header grid">
		<?php get_template_part('template-parts/header/logo'); ?>

		<?php get_template_part('template-parts/header/hamburger'); ?>

		<?php get_template_part('template-parts/header/navigation', null, array('nav' => $uh_nav)); ?>
	</header>

	<main class="site-content<?php if(get_field('hero_image')): ?> has-hero<?php endif; ?>">
