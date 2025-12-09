<?php
/**
 * Unified Page Header Template
 *
 * A flexible page header component that handles various layouts across the site.
 *
 * @param string $title - Main title (default: current post title)
 * @param string $subtitle - Optional subtitle text
 * @param array  $subtitle_parts - Array of subtitle parts to display with separators
 * @param string $alignment - 'center' or 'left' (default: 'center')
 * @param string $id - Optional ID attribute for the section
 * @param array  $custom_classes - Additional CSS classes
 *
 * @example Basic usage (just title):
 * get_template_part('template-parts/global/page-header-unified');
 *
 * @example With custom title:
 * get_template_part('template-parts/global/page-header-unified', null, array(
 *     'title' => 'Events'
 * ));
 *
 * @example With subtitle parts (location + date):
 * get_template_part('template-parts/global/page-header-unified', null, array(
 *     'subtitle_parts' => array(
 *         'location' => get_field('location'),
 *         'date' => get_the_time('F j, Y')
 *     )
 * ));
 */

// Get parameters from $args or use defaults
$title = $args['title'] ?? get_the_title();
$subtitle = $args['subtitle'] ?? '';
$subtitle_parts = $args['subtitle_parts'] ?? array();
$alignment = $args['alignment'] ?? 'center';
$id = $args['id'] ?? '';
$custom_classes = $args['custom_classes'] ?? array();

// Build CSS classes
$classes = array_merge(
    array('page-header', "align-{$alignment}", 'grid'),
    $custom_classes
);

// Build subtitle from parts if provided
$subtitle_html = '';
if (!empty($subtitle_parts)) {
    $parts = array();
    foreach ($subtitle_parts as $key => $value) {
        if ($value) {
            $parts[] = '<span class="' . esc_attr($key) . '">' . esc_html($value) . '</span>';
        }
    }
    if (!empty($parts)) {
        $subtitle_html = implode(' &middot; ', $parts);
    }
} elseif ($subtitle) {
    $subtitle_html = esc_html($subtitle);
}
?>

<section class="<?php echo esc_attr(implode(' ', $classes)); ?>"<?php if($id): ?> id="<?php echo esc_attr($id); ?>"<?php endif; ?>>
    <h1><?php echo $title; // Already escaped in context where it's set ?></h1>

    <?php if ($subtitle_html): ?>
        <div class="location sub-title">
            <h2><?php echo $subtitle_html; // Already escaped above ?></h2>
        </div>
    <?php endif; ?>
</section>
