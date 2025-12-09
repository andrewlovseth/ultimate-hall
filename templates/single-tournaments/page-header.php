<?php
// Build subtitle parts from tournament data
$location = get_field('details_location');

$subtitle_parts = array();
if ($location) {
    $subtitle_parts['location'] = $location;
}

// Use unified page header template
get_template_part('template-parts/global/page-header-unified', null, array(
    'subtitle_parts' => $subtitle_parts
));
