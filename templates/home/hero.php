<?php

    $hero = get_field('hero');
    $photos = $hero['photos'];
    $headline = $hero['headline'];
    $sub_headline = $hero['sub_headline'];

?>

<section class="hero grid">
    <div class="gallery">
        <div class="hero-slider">
            <?php if( $photos ): ?>
                <?php foreach( $photos as $photo ): ?>
                    <div class="photo">
                        <?php echo wp_get_attachment_image($photo['ID'], 'full'); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="info">
        <div class="info-wrapper">
            <div class="headline">
                <h1><?php echo $headline; ?></h1>
            </div>

            <div class="sub-headline">
                <h2><?php echo $sub_headline; ?></h2>
            </div>
        </div>
    </div>
</section>