<?php

    $news = get_field('news');
    $headline = $news['headline'];
    $link = $news['cta'];

?>

<section class="news grid">
    <div class="section-header">
        <h3><?php echo $headline; ?></h3>
    </div>

    <div class="recent-news-grid">
        <?php
        $args = array(
            'post_type' => 'post',
            'posts_per_page' => 3
        );
        $query = new WP_Query( $args );
        if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post(); ?>

            <article <?php post_class('post-teaser'); ?>>
                <div class="meta">
                    <span class="date"><?php the_time('F j, Y'); ?></span>
                </div>

                <div class="info">
                    <div class="headline">
                        <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                    </div>

                    <div class="excerpt copy p3">
                        <?php
                            $excerpt = get_the_excerpt(); 
                            $excerpt = substr( $excerpt, 0, 140 );
                            $result = substr( $excerpt, 0, strrpos( $excerpt, ' ' ) );
                            echo $result . ' ...';
                        ?>
                    </div>
                </div>
            </article>
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