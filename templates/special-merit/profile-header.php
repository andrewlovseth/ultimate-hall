<?php
    $meta = get_field('meta');
    $class = $meta['class'];
    $year = $class->post_name;
?>

<section class="profile-header grid">
    <div class="class">
        <h2 class="class__title"><?php if($year == '2004'): ?>Inaugural <?php endif; ?> Class of <?php echo $year; ?></h2>
    </div>

    <div class="name">
        <h1 class="name__title">hi<?php the_title(); ?></h1>
    </div>

    <div class="meta">
        <?php if($induction_type): ?>
            <span class="meta__type"><?php echo $induction_type; ?></span>
        <?php endif; ?>

        <?php if($induction_division): ?>
            <span class="meta__division"><?php echo $induction_division['label']; ?></span>
        <?php endif; ?>        
    </div>
</section>