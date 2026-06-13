<?php
/*
	Site Navigation — one element, two presentations:

	- Desktop (>= desktop-small): inline primary links in the brand bar.
	  The search button, secondary links, and Donate hide here — they live
	  in the tray up top instead.
	- Mobile/tablet (< desktop-small): the slide-in drawer toggled by
	  body.nav-overlay-open (js/site.js binds .js-nav-trigger). Search
	  trigger pinned at the top, then primary links, secondary links, and
	  Donate at the foot.

	The .nav-search button is the drawer's palette trigger: js/search.js
	binds open to every .js-omni-open and closes this overlay when the
	palette opens. Icons are Lucide (search, chevron-right) inlined at
	stroke-width 1.5.

	The .site-navigation class exists for js/site.js — its outside-click
	handler treats ".site-navigation, .js-nav-trigger" as inside-the-menu.

	Receives $args['nav'] from header.php (see the bucket loop there).
*/

$nav       = isset($args['nav']) && is_array($args['nav']) ? $args['nav'] : array();
$primary   = isset($nav['primary']) ? $nav['primary'] : array();
$secondary = isset($nav['secondary']) ? $nav['secondary'] : array();
$donate    = isset($nav['donate']) ? $nav['donate'] : array();
?>

<nav class="site-nav site-navigation">
	<button type="button" class="nav-search js-omni-open" aria-haspopup="dialog">
		<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/></svg>
		<span>Search</span>
	</button>

	<?php if ($primary): ?>
		<div class="site-nav__links site-nav__links--primary">
			<?php foreach ($primary as $item): ?>
				<a href="<?php echo esc_url($item['url']); ?>" target="<?php echo esc_attr($item['target']); ?>"<?php if ('_blank' === $item['target']) echo ' rel="noopener"'; ?>>
					<span><?php echo esc_html($item['title']); ?></span>
					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m9 18 6-6-6-6"/></svg>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ($secondary): ?>
		<div class="site-nav__links site-nav__links--secondary">
			<?php foreach ($secondary as $item): ?>
				<a href="<?php echo esc_url($item['url']); ?>" target="<?php echo esc_attr($item['target']); ?>"<?php if ('_blank' === $item['target']) echo ' rel="noopener"'; ?>>
					<span><?php echo esc_html($item['title']); ?></span>
					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m9 18 6-6-6-6"/></svg>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ($donate): ?>
		<div class="site-nav__donate">
			<?php foreach ($donate as $item): ?>
				<div class="cta">
					<a class="btn btn__nav" href="<?php echo esc_url($item['url']); ?>" target="<?php echo esc_attr($item['target']); ?>"<?php if ('_blank' === $item['target']) echo ' rel="noopener"'; ?>><?php echo esc_html($item['title']); ?></a>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</nav>
