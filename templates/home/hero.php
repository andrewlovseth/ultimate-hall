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
                            // The first photo is the LCP: it loads eagerly at high priority.
                            // The others sit in the same grid cell, so lazy loading can't hold them back;
                            // they ship with data-src/data-srcset and js/site.js fills them in after
                            // the page has loaded, well before the first crossfade.
                            $attr = array( 'sizes' => '(min-width: 768px) 100vw, 150vw', 'loading' => false );
                            if ( 0 === $i ) {
                                $attr = array_merge( $attr, array( 'loading' => 'eager', 'fetchpriority' => 'high' ) );
                            }
                            $img = wp_get_attachment_image( $photo['ID'], 'full', false, $attr );
                            if ( 0 !== $i ) {
                                $img = str_replace( array( ' src=', ' srcset=' ), array( ' data-src=', ' data-srcset=' ), $img );
                            }
                            echo $img;
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