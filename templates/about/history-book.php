<?php

    $history_book = get_field('history_book');
    $headline = $history_book['headline'];
    $copy = $history_book['copy'];
    $link = $history_book['link'];

?>

<div class="history-book">
    <div class="section-header">
        <h3 class="small"><?php echo $headline; ?></h3>
    </div>

    <div class="copy p3 extended">
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