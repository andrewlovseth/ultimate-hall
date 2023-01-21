<?php
    $meta = get_field('meta');
    $class = $meta['class'];
    $induction_type = $meta['induction_type']['label'];
    $induction_division = $meta['induction_division'];
    $year = $class->post_name;
?>

<section class="profile-header">    
        <div class="class">
            <h2>
                <?php if($year == '2004'): ?>Inaugural<?php endif; ?>
                
                Class of <?php echo $year; ?>
                
                    <?php if($induction_type): ?>
                 - <?php echo $induction_type; ?>
                <?php endif; ?>

                <?php if($induction_division): ?>
                 - <?php echo $induction_division['value']; ?>
                <?php endif; ?>
            </h2>
        </div>


    <div class="name">
        <h1><?php the_title(); ?></h1>
    </div>
</section>