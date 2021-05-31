<?php

    $mission = get_field('mission');
    $headline = $mission['headline'];
    $copy = $mission['copy'];

?>

<aside class="sidebar mission">
    <div class="info">
        <div class="section-header">
            <h3 class="small"><?php echo $headline; ?></h3>
        </div>

        <div class="copy p3">
            <?php echo $copy; ?>
        </div>
    </div>
</aside>

