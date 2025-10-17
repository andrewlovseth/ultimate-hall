<?php

    $title = get_the_title();
    $location = get_field('location');
    $date = get_the_time('F j, Y');
?>

<section class="page-header align-center grid">
    <h1><?php echo $title; ?></h1>

    <div class="location">
        <h2>
            <?php if($location): ?><span class="location"><?php echo $location; ?></span><?php endif; ?>
            <?php if($date): ?> &middot; <span class="date"><?php echo $date; ?></span><?php endif; ?>
        </h2>
    </div>
</section>