<?php
    $us_championships = get_field('us_championships');
    $world_championships = get_field('wfdf_championships');

    if($us_championships || $world_championships): ?>
?>

    <section class="championship-tournaments grid">
        <div class="section-header align-center">
            <h3>Championship Tournaments</h3>
        </div>

        <?php get_template_part('templates/single-member/championship-tournaments/us-championships'); ?>

        <?php get_template_part('templates/single-member/championship-tournaments/world-championships'); ?>
    </section>

<?php endif; ?>