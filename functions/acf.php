<?php

/*
    Advanced Custom Fields
*/


// Add options pages
if(function_exists('acf_add_options_page')) {
    acf_add_options_page();
    acf_add_options_sub_page('Header');
    acf_add_options_sub_page('Footer');
    acf_add_options_sub_page('Members');
    acf_add_options_sub_page('Divisions');
}


// Order Relationship fields
function bearsmith_relationship_order_by_date($args, $field, $post_id) {
    $args['orderby'] = 'date';
    $args['order'] = 'DESC';
    $args['posts_per_page'] = 60;
    return $args;
}
add_filter('acf/fields/relationship/query', 'bearsmith_relationship_order_by_date', 10, 3);


function bearsmith_acf_enqueue_scripts() {
    wp_enqueue_style( 'bearsmith-acf-css', get_stylesheet_directory_uri() . '/acf.css', false, '1.0.0' );
}
add_action('acf/input/admin_enqueue_scripts', 'bearsmith_acf_enqueue_scripts');


add_filter( 'postmeta_form_limit' , 'customfield_limit_increase' );
function customfield_limit_increase( $limit ) {
    $limit = 60;
    return $limit;
}


// Order Relationship fields
function bearsmith_team_relationship_order($args, $field, $post_id) {
    $args['orderby'] = 'name';
    $args['order'] = 'ASC';
    $args['posts_per_page'] = 60;
    return $args;
}
add_filter('acf/fields/relationship/query/key=field_603e78e196eea', 'bearsmith_team_relationship_order', 10, 3);
add_filter('acf/fields/relationship/query/key=field_60528f187c47d', 'bearsmith_team_relationship_order', 10, 3);
add_filter('acf/fields/relationship/query/key=field_60528c634455f', 'bearsmith_team_relationship_order', 10, 3);
