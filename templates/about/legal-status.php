<?php

    $legal_status = get_field('legal_status');
    $headline = $legal_status['headline'];
    $copy = $legal_status['copy'];

?>

<section id="legal" class="legal-status grid">
    <div class="section-header">
        <h3><?php echo $headline; ?></h3>
    </div>

    <div class="copy p1 extended">
        <?php echo $copy; ?>
    </div>
</section>