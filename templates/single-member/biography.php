<?php

    $biography = get_field('biography');

?>

<section class="biography grid">

    <div class="section-header headline">
        <h3>Biography</h3>
    </div>

    <div class="copy p1">
        <?php echo $biography; ?>
    </div>

</section>