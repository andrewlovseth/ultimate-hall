<?php

    $mission = get_field('mission');
    $mission_headline = $mission['headline'];
    $mission_copy = $mission['copy'];


    $vision = get_field('vision');
    $vision_headline = $vision['headline'];
    $vision_copy = $vision['copy'];

?>

<aside class="sidebar">
    <div class="mission module">
        <div class="section-header">
            <h3 class="small"><?php echo $mission_headline; ?></h3>
        </div>

        <div class="copy p3">
            <?php echo $mission_copy; ?>
        </div>
    </div>

    <div class="vision module">
        <div class="section-header">
            <h3 class="small"><?php echo $vision_headline; ?></h3>
        </div>

        <div class="copy p3">
            <?php echo $vision_copy; ?>
        </div>
    </div>

    <?php get_template_part('templates/about/history-book'); ?>

</aside>

