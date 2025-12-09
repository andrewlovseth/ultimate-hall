<?php
// Build subtitle parts from team data
$division_obj = get_field('division');
$division = ($division_obj) ? $division_obj[0]->post_title : '';
$city = get_field('city');

$subtitle_parts = array();
if ($city) {
    $subtitle_parts['city'] = $city;
}
if ($division) {
    $subtitle_parts['division'] = $division;
}

// Use unified page header template
get_template_part('template-parts/global/page-header-unified', null, array(
    'subtitle_parts' => $subtitle_parts
));
