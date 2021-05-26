<?php

    $location = get_field('details_location');

?>


<section class="page-header align-center grid">
    <h1><?php the_title(); ?></h1>

    <div class="location sub-title">
        <h2>
            <?php if($location): ?><span class="location"><?php echo $location; ?></span><?php endif; ?>
        </h2>
    </div>        
</section>