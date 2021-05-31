<?php

    $info = get_field('info');
    $headline = $info['headline'];
    $copy = $info['copy'];

?>

<section class="info">
    <div class="headline">
        <h2 class="page-title"><?php echo $headline; ?></h2>
    </div>

    <div class="copy p1 extended">
        <?php echo $copy; ?>
    </div>
</section>