<?php
    $meta = get_field('meta');
    $induction_type = $meta['induction_type']['label'];
    if($induction_type !== 'Special Merit'):
?>

    <section class="vitals">
        <?php get_template_part('templates/single-member/vitals/header'); ?>

        <?php get_template_part('templates/single-member/vitals/career-information'); ?>

        <?php get_template_part('templates/single-member/vitals/us-championships'); ?>

        <?php get_template_part('templates/single-member/vitals/wfdf-championships'); ?>

        <?php get_template_part('templates/single-member/vitals/pro-championships'); ?>

        <?php get_template_part('templates/single-member/vitals/awards'); ?>
    </section>

<?php endif; ?>