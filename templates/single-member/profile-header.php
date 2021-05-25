<?php
    $meta = get_field('meta');
    $class = $meta['class'];
    $induction_type = $meta['induction_type']['label'];
    $year = $class->post_name;
?>

<section class="profile-header">
    <?php if($year == '2004'): ?>
        <div class="class">
            <h2>Inaugural Class of <?php echo $year; ?> - <?php echo $induction_type; ?></h2>
        </div>
    <?php else: ?>
        <div class="class">
            <h2>Class of <?php echo $year; ?> - <?php echo $induction_type; ?></h2>
        </div>
    <?php endif; ?>

    <div class="name">
        <h1><?php the_title(); ?></h1>
    </div>
</section>