<?php 

/*
  
    Template Name: Home
*/

get_header(); ?>

    <?php get_template_part('templates/home/hero'); ?>

    <?php get_template_part('templates/home/about'); ?>

    <?php get_template_part('templates/home/latest-class'); ?>

    <?php get_template_part('templates/home/selection'); ?>

    <?php get_template_part('templates/home/news'); ?>

<?php get_footer(); ?>