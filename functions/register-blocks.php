<?php

/*
    Register Blocks
*/



add_action('acf/init', 'bearsmith_register_blocks');
function bearsmith_register_blocks() {

    if( function_exists('acf_register_block_type') ) {

        acf_register_block_type(array(
            'name'              => 'hall-class',
            'title'             => __('Hall of Fame Class'),
            'description'       => __('Display a grid of Hall of Fame members by class'),
            'render_template'   => 'blocks/hall-class/hall-class.php',
            'category'          => 'layout',
            'icon'              => 'grid-view',
            'align'             => 'full',
        ));

    }
}