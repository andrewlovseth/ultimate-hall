<?php

    $cta = get_field('cta');
    $headline = $cta['headline'];
    $more_info_link = $cta['more_info_link'];

if(have_rows('cta')): while(have_rows('cta')): the_row(); ?>

<section class="nominate-cta">
    <div class="section-header">
        <h3 class="small"><?php echo $headline; ?></h3>
    </div>

    <?php if(have_rows('links')): while(have_rows('links')): the_row(); ?>
 
        <?php
            $link = get_sub_field('link');
            if( $link ): 
            $link_url = $link['url'];
            $link_title = $link['title'];
            $link_target = $link['target'] ? $link['target'] : '_self';
        ?>

            <div class="cta">
                <a class="btn" href="<?php echo esc_url($link_url); ?>" target="<?php echo esc_attr($link_target); ?>"><?php echo esc_html($link_title); ?></a>
            </div>

        <?php endif; ?>
    
    <?php endwhile; endif; ?>

    <?php 
        if( $more_info_link ): 
        $link_url = $more_info_link['url'];
        $link_title = $more_info_link['title'];
        $link_target = $more_info_link['target'] ? $link['target'] : '_self';
    ?>

        <div class="more-info">
            <a class="smooth" href="<?php echo esc_url($link_url); ?>" target="<?php echo esc_attr($link_target); ?>"><?php echo esc_html($link_title); ?></a>
        </div>

    <?php endif; ?>

</section>

<?php endwhile; endif; ?>