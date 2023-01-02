<?php

    $vitals = get_sub_field('vitals');
    $first_name = $vitals['first_name'];
    $last_name = $vitals['last_name'];

    $info = get_sub_field('info');
    $images = $info['gallery'];

?>

<div class="entry__media<?php if(!$images): ?> no-photos<?php endif; ?>">
    <?php get_template_part('templates/special-merit/vitals'); ?>

    <?php if($images): ?>
        <div class="info">
            <div class="section-header">
                <h3 class="small"><?php echo $first_name . ' ' . $last_name; ?>'s Photos</h3>
            </div>
            <div class="media">
                <?php foreach( $images as $image ): ?>
                    <div class="media__photo">
                        <a data-fslightbox="<?php echo $first_name . '-' . $last_name; ?>" href="<?php echo wp_get_attachment_image_url($image['id'], 'full'); ?>">
                            <?php echo wp_get_attachment_image($image['ID'], 'large'); ?>
                        </a>
                    </div>
                    
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

</div>