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
                <?php foreach( array_values( $photos ) as $i => $photo ): ?>
                    <div class="photo<?php if ( 0 === $i ) echo ' is-active'; ?>">
                        <?php
                            // First photo is the LCP: load it eagerly at high priority; the rest wait.
                            echo wp_get_attachment_image( $photo['ID'], 'full', false, array(
                                'sizes'         => '(min-width: 768px) 100vw, 150vw',
                                'loading'       => 0 === $i ? 'eager' : 'lazy',
                                'fetchpriority' => 0 === $i ? 'high' : 'low',
                            ) );
                        ?>
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