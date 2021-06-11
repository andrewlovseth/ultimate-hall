<?php

    $selection = get_field('selection');
    $headline = $selection['headline'];
    $copy = $selection['copy'];
    $link = $selection['cta'];
    $photo = $selection['photo'];

?>

<section class="selection grid">
    <div class="photo">
        <div class="content">
            <?php echo wp_get_attachment_image($photo['ID'], 'large'); ?>
        </div>
    </div>

    <div class="info">
        <div class="section-header">
            <h3><?php echo $headline; ?></h3>
        </div>

        <div class="copy p2">
            <?php echo $copy; ?>
        </div>

        <?php 
            if( $link ): 
            $link_url = $link['url'];
            $link_title = $link['title'];
            $link_target = $link['target'] ? $link['target'] : '_self';
        ?>

            <div class="cta">
                <a class="btn" href="<?php echo esc_url($link_url); ?>" target="<?php echo esc_attr($link_target); ?>"><?php echo esc_html($link_title); ?></a>
            </div>

        <?php endif; ?>
    </div>
</section>