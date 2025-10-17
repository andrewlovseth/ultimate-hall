<?php

    $images = get_field('gallery');
    
    if($images):
?>

    <section class="gallery grid">
        <div class="section-header align-center">
            <h3>Gallery</h3>
        </div>

        <div class="gallery__wrapper">
            <?php foreach( $images as $image ): ?>
                <div class="photo">
                    <a class="photo__link" href="<?php echo wp_get_attachment_image_url($image['id'], 'full'); ?>" data-fslightbox="<?php echo get_the_title(); ?>">
                        <div class="photo__thumbnail">
                            <?php echo wp_get_attachment_image($image['ID'], 'large'); ?>
                        </div>
                    </a>
                </div>                
            <?php endforeach; ?>
        </div>  
    </section>

<?php endif; ?>


