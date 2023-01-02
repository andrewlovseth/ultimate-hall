<?php

    $images = get_field('gallery');
    
    if($images):
?>

    <section class="gallery">
        <div class="gallery-slider">
            <?php $count = 1; foreach( $images as $image ): ?>
                <div class="photo">
                    <div class="photo-wrapper">
                        <div class="content">
                            <div class="image-wrapper">
                                <a data-fslightbox="<?php echo get_the_title(); ?>" href="<?php echo wp_get_attachment_image_url($image['id'], 'full'); ?>">
                                    <?php echo wp_get_attachment_image($image['ID'], 'large'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php $count++; endforeach; ?>
        </div>
    </section>



<?php endif; ?>