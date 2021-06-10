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
                                <a class="js-modal" href="#" data-modal="modal-<?php echo $count; ?>"><img src="<?php echo $image['sizes']['large']; ?>" alt="<?php echo $image['alt']; ?>" /></a>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php $count++; endforeach; ?>
        </div>
    </section>

    <?php $count = 1; foreach( $images as $image ): ?>
        <div class="modal micromodal-slide" id="modal-<?php echo $count; ?>" aria-hidden="true">
            <div class="modal__overlay" tabindex="-1" data-micromodal-close>
                <div class="modal__container" role="dialog" aria-modal="true">

                    <div class="modal__close js-close-modal" aria-label="Close modal" data-micromodal-close>
                        <?php get_template_part('template-parts/svg/icon-close'); ?>
                    </div>

                    <div class="modal__content" id="modal-<?php echo $count; ?>-content">
                        <img src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" />

                        <?php if($image['caption']): ?>
                            <div class="caption">
                                <p><?php echo $image['caption']; ?></p>
                            </div>
                        <?php endif; ?>                        
                    </div>

                </div>
            </div>
        </div>
    <?php $count++; endforeach; ?>

<?php endif; ?>