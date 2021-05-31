<?php 

/*
  
    Template Name: Selection Process
*/

get_header(); ?>

    <?php get_template_part('template-parts/global/hero-image'); ?>

    <?php get_template_part('template-parts/global/page-header'); ?>

    <section class="main grid">
        <?php get_template_part('templates/selection-process/info'); ?>

        <aside class="sidebar">
            <?php get_template_part('templates/selection-process/cta'); ?>

            <?php get_template_part('templates/selection-process/vetting-committee'); ?>
            
        </aside>
    </section>

<?php get_footer(); ?>