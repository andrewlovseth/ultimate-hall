<?php 

/*
  
    Template Name: About
*/

get_header(); ?>

    <?php get_template_part('template-parts/global/hero-image'); ?>

    <?php get_template_part('template-parts/global/page-header'); ?>

    <section class="main grid">
        <?php get_template_part('templates/about/info'); ?>

        <?php get_template_part('templates/about/mission'); ?>
    </section>

    <?php get_template_part('templates/about/board-of-directors'); ?>

    <?php get_template_part('templates/about/partners'); ?>

    <?php get_template_part('templates/about/legal-status'); ?>

<?php get_footer(); ?>