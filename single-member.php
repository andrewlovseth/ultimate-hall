<?php get_header(); ?>

    <section class="profile grid">
        <?php get_template_part('templates/single-member/profile-header'); ?>
        
        <?php get_template_part('templates/single-member/vitals'); ?>

        <?php get_template_part('templates/single-member/biography'); ?>
    </section>

    <?php get_template_part('templates/single-member/letters-of-recommendation'); ?>

    <?php get_template_part('templates/single-member/gallery'); ?>

    <?php get_template_part('templates/single-member/championship-tournaments'); ?>

    <?php get_template_part('templates/single-member/interview'); ?>
    
    <?php get_template_part('templates/single-member/teammates'); ?>

<?php get_footer(); ?>