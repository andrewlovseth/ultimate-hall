<?php
    $meta = get_field('meta');
    $class = $meta['class'];
    $year = $class->post_name;
?>

<section class="profile-header grid">
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
</section>