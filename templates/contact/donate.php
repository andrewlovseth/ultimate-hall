<?php

    $donate = get_field('donate');
    $headline =  $donate['headline']; 
    $copy =  $donate['copy']; 
    $link =  $donate['link']; 

?>

<section class="donate grid">
    <div class="info">
        <div class="section-header">
            <h3 class="small"><?php echo $headline; ?></h3>
        </div>

        <div class="copy p3">
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