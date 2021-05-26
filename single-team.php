<?php get_header(); ?>

    <?php get_template_part('templates/single-team/page-header'); ?>

    <?php if(get_field('national_team') == TRUE): ?>
        <?php get_template_part('templates/single-team/national-team-members'); ?>
    <?php else:?>
        <?php get_template_part('templates/single-team/members'); ?>
    <?php endif; ?>

    <?php get_template_part('templates/single-team/other-teams'); ?>

<?php get_footer(); ?>