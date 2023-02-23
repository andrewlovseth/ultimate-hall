<?php

    $vitals = get_sub_field('vitals');
    $first_name = $vitals['first_name'];
    $last_name = $vitals['last_name'];

    $info = get_sub_field('info');
    $images = $info['gallery'];
    $bio = $info['bio'];
?>

<div class="entry__media<?php if(!$images): ?> no-photos<?php endif; ?>">
    <?php get_template_part('templates/special-merit/vitals'); ?>

    <div class="info">
        <?php if($bio): ?>
            <div class="copy bio extended">
                <?php echo $bio; ?>
            </div>
        <?php endif; ?>
        
        <?php if($images): ?>
            <div class="media">
                <?php foreach( $images as $image ): ?>
                    <div class="media__photo">
                        <a data-fslightbox="<?php echo $first_name . '-' . $last_name; ?>" href="<?php echo wp_get_attachment_image_url($image['id'], 'full'); ?>">
                            <?php echo wp_get_attachment_image($image['ID'], 'large'); ?>
                        </a>
                    </div>
                    
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>