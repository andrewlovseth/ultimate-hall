<?php

    $latest_class = get_field('latest_class');
    $headline = $latest_class['headline'];
    $copy = $latest_class['copy'];
    $year = $latest_class['year'];
    $link = $latest_class['cta'];

?>

<section class="latest-class grid">
    <div class="section-header align-center">
        <h3><?php echo $headline; ?></h3>
    </div>

    <div class="copy p1 extended">
        <?php echo $copy; ?>
    </div>

    <div class="member-grid">
        <?php
            $query = bearsmith_get_members_by_class($year->ID);
            if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post(); ?>

            <?php get_template_part('template-parts/global/member'); ?>

        <?php endwhile; endif; wp_reset_postdata(); ?>
    </div>

    <?php 
        if( $link ): 
        $link_url = $link['url'];
        $link_title = $link['title'];
        $link_target = $link['target'] ? $link['target'] : '_self';
    ?>

        <div class="cta align-center">
            <a class="btn" href="<?php echo esc_url($link_url); ?>" target="<?php echo esc_attr($link_target); ?>"><?php echo esc_html($link_title); ?></a>
        </div>

    <?php endif; ?>
</section>