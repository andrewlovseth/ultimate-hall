<?php
/*
	Header Tray — sticky utility strip above the brand bar.

	Desktop (>= desktop-small): secondary links left / search trigger center
	(max ~440px cell) / Donate right.
	Tablet (tablet-small -> desktop-small): compact trigger + Donate only.
	Mobile (< tablet-small): renders nothing visually, but the markup stays in
	the DOM — the search partial outputs the fixed-position palette (.omni)
	alongside the trigger, and display: none on any ancestor would keep the
	palette from ever opening. The hide rules live in scss/header/_header.scss.

	Receives $args['nav'] from header.php (see the bucket loop there).
	Seam note: the trigger button itself (.omni-trigger) belongs to
	template-parts/header/search.php — this file only owns the cells around it.
*/

$nav       = isset($args['nav']) && is_array($args['nav']) ? $args['nav'] : array();
$secondary = isset($nav['secondary']) ? $nav['secondary'] : array();
$donate    = isset($nav['donate']) ? $nav['donate'] : array();
?>

<div class="header-tray">
	<div class="grid">
		<div class="header-tray__row">

			<nav class="header-tray__links" aria-label="Secondary">
				<?php foreach ($secondary as $item): ?>
					<a href="<?php echo esc_url($item['url']); ?>" target="<?php echo esc_attr($item['target']); ?>"<?php if ('_blank' === $item['target']) echo ' rel="noopener"'; ?>><?php echo esc_html($item['title']); ?></a>
				<?php endforeach; ?>
			</nav>

			<div class="header-tray__search">
				<?php get_template_part('template-parts/header/search'); ?>
			</div>

			<div class="header-tray__actions">
				<?php foreach ($donate as $item): ?>
					<div class="cta">
						<a class="btn btn__nav" href="<?php echo esc_url($item['url']); ?>" target="<?php echo esc_attr($item['target']); ?>"<?php if ('_blank' === $item['target']) echo ' rel="noopener"'; ?>><?php echo esc_html($item['title']); ?></a>
					</div>
				<?php endforeach; ?>
			</div>

		</div>
	</div>
</div>
