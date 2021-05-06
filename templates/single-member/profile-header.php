<?php
    $meta = get_field('meta');
    $class = $meta['class'];
    $type = $meta['induction_type'];
?>


<section class="profile-header">
    <h2>Class of <?php echo $class->post_name; ?></h2>
    <h1><?php the_title(); ?></h1>
</section>