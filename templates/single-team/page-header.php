<?php

    $division_obj = get_field('division');
    if($division_obj) {
        $division = $division_obj[0]->post_title;
    }

    $city = get_field('city');

?>


<section class="page-header align-center grid">
    <h1><?php the_title(); ?></h1>

    <div class="location sub-title">
        <h2>
            <?php if($city): ?><span class="city"><?php echo $city; ?></span><?php endif; ?>
            <?php if($division): ?><span class="division"><?php echo $division; ?></span><?php endif; ?>
        </h2>
    </div>        
</section>