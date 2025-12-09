<?php get_header(); ?>


    <?php
        // Use unified page header with custom title
        get_template_part('template-parts/global/page-header-unified', null, array(
            'title' => 'Hall of Famers',
            'id' => 'top'
        ));
    ?>

    <?php get_template_part('templates/archive-member/sub-nav'); ?>

    <?php get_template_part('templates/archive-member/classes'); ?>


    <div class="back-to-top">
        <a href="#page" class="smooth">Back to Top</a>
    </div>


<?php get_footer(); ?>