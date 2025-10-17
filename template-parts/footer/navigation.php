<nav class="footer-nav">
    
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