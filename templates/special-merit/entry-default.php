<?php

    $info = get_sub_field('info');
    $bio = $info['bio'];

?>

<div class="entry__default">
    <?php get_template_part('templates/special-merit/vitals'); ?>

    <div class="info">
        <?php if($bio): ?>
            <div class="copy bio extended">
                <?php echo $bio; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
