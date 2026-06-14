<?php 

/*

    Template Name: Special Merit
    Template Post Type: member

*/

get_header(); ?>
    
    <?php get_template_part('templates/special-merit/introduction'); ?>

    <?php if ( ! get_field('hide_member_list') ) : ?>
        <?php get_template_part('templates/special-merit/entries'); ?>
    <?php endif; ?>

    <?php get_template_part('templates/single-member/gallery'); ?>
    
<?php get_footer(); ?>

