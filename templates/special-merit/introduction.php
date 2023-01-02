<?php

    $meta = get_field('meta');
    $class = $meta['class'];
    $year = $class->post_name;

    $introduction = get_field('introduction');
    $copy = $introduction['copy'];
    $photo = $introduction['photo'];

?>

<section class="introduction grid">
    <div class="profile-header">
        <?php if($year == '2004'): ?>
            <div class="class">
                <h2>Inaugural Class of <?php echo $year; ?> - Special Merit</h2>
            </div>
        <?php else: ?>
            <div class="class">
                <h2>Class of <?php echo $year; ?> - Special Merit</h2>
            </div>
        <?php endif; ?>

        <div class="name">
            <h1><?php the_title(); ?></h1>
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

