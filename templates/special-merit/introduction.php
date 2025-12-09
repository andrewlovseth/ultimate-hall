<?php

    $meta = get_field('meta');
    $class = $meta['class'];
    $induction_type = $meta['induction_type']['label'];
    $induction_division = $meta['induction_division'];

    $year = $class->post_name;

    $introduction = get_field('introduction');
    $copy = $introduction['copy'];
    $photo = $introduction['photo'];

?>

<section class="introduction grid">
    <div class="profile-header">
        <div class="class">
            <h2 class="class__title"><?php echo inaugural_get_class_prefix($year); ?>Class of <?php echo $year; ?></h2>
        </div>

        <div class="name">
            <h1 class="name__title"><?php the_title(); ?></h1>
        </div>

    <div class="meta">
        <?php if($induction_type): ?>
            <span class="meta__type"><?php echo $induction_type; ?></span>
        <?php endif; ?>

        <?php if($induction_division): ?>
            <span class="meta__division"><?php echo $induction_division['label']; ?></span>
        <?php endif; ?>        
    </div>
    </div>


    <?php if($photo): ?>
        <div class="photo">
            <?php echo wp_get_attachment_image($photo['ID'], 'full'); ?>
        </div>
    <?php endif; ?>
    
    <?php if($copy): ?>
        <div class="copy p1">
            <?php echo $copy; ?>
        </div>
    <?php endif; ?>
</section>

