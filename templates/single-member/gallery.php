<?php

    $images = get_field('gallery');
    
    if($images):
?>

    <section class="gallery">
        <div class="gallery-slider">
            <?php foreach( $images as $image ): ?>
                <div class="photo">
                    <div class="photo-wrapper">
                        <div class="content">
                            <div class="image-wrapper">
                                <img src="<?php echo $image['sizes']['large']; ?>" alt="<?php echo $image['alt']; ?>" />                
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

<?php endif; ?>