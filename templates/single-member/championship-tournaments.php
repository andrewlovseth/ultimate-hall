<?php
    $us_championships = get_field('us_championships');
    $world_championships = get_field('wfdf_championships');

    $count = 0;

    if($us_championships || $world_championships):

    if($us_championships) {
        $count++;
    }

    if($world_championships) {
        $count++;
    }



?>

    <section class="championship-tournaments grid sections-<?php echo $count; ?>">
        <div class="section-header align-center">
            <h3>Championship Tournaments</h3>
        </div>

        <?php get_template_part('templates/single-member/championship-tournaments/us-championships'); ?>

        <?php get_template_part('templates/single-member/championship-tournaments/world-championships'); ?>
    </section>

<?php endif; ?>