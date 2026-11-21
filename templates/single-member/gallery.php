<?php

    $images = get_field('gallery');
    
    if($images):
?>

    <section class="gallery grid">
        <div class="gallery__wrapper">
            <?php foreach( $images as $image ): ?>
                <div class="photo">
                    <a class="photo__link" href="<?php echo esc_url( wp_get_attachment_image_url($image['id'], 'full') ); ?>" data-fslightbox="<?php echo esc_attr( get_the_title() ); ?>" aria-label="<?php echo esc_attr( sprintf( 'View photo: %s', $image['alt'] ?: $image['title'] ?: get_the_title() ) ); ?>">
                        <div class="photo__thumbnail">
                            <?php echo wp_get_attachment_image($image['ID'], 'large', false, array( 'loading' => 'lazy' )); ?>
                        </div>
                    </a>
                </div>                
            <?php endforeach; ?>
        </div>  
    </section>

<?php endif; ?>


