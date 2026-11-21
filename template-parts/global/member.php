<?php
    $args = wp_parse_args($args);

    $member_ID = $args['member_ID'] ?? get_the_ID();

    // The name's heading level follows the page outline: h3 under a section heading (default),
    // h2 where the card sits straight under the page's h1 (a class page).
    $heading = in_array( $args['heading'] ?? '', array( 'h2', 'h3', 'h4' ), true ) ? $args['heading'] : 'h3';
    $subheading = 'h' . ( (int) substr( $heading, 1 ) + 1 ); // the type/division line, one level down

    // Pass 'loading' => 'lazy' where the grid is always below the fold (teammates, the home page's
    // latest class). Elsewhere WordPress decides, so cards near the top can still be the LCP.
    $img_attr = array( 'sizes' => '(min-width: 768px) 170px, 25vw' );
    if ( 'lazy' === ( $args['loading'] ?? '' ) ) {
        $img_attr['loading'] = 'lazy';
    }

    $type = get_field('meta_induction_type', $member_ID);
    $division = get_field('meta_induction_division', $member_ID);
    $template = get_page_template_slug($member_ID);

    if($template == 'templates/special-merit.php') {
        $image = get_field('introduction_photo', $member_ID); 
    } else {
        $image = get_field('photos_headshot', $member_ID); 
    }

?>

<div class="member">
    <div class="photo">
        <a href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
            <div class="content">
                <?php if($image): ?>
                    <?php echo wp_get_attachment_image( $image['ID'], 'thumbnail', false, $img_attr ); ?>
                <?php else: ?>
                    <div class="empty"></div>
                <?php endif; ?>
            </div>
        </a>
    </div>

    <div class="info">
        <div class="name">
            <<?php echo $heading; ?>><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></<?php echo $heading; ?>>
        </div>

        <?php if($type || $division): ?>
            <div class="meta">
                <<?php echo $subheading; ?>>
                    <?php if($type): ?>
                        <span class="type"><?php echo esc_html( $type['label'] ); ?></span>                        
                    <?php endif; ?>

                    <?php if($division): ?>
                        <span class="division"><?php echo esc_html( $division['label'] ); ?></span>
                    <?php endif; ?>                
                </<?php echo $subheading; ?>>
            </div>
        <?php endif; ?>
    </div>
</div>