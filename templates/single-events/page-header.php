<?php
// Build subtitle parts from event data
$location = get_field('location');
$date = get_the_time('F j, Y');

$subtitle_parts = array();
if ($location) {
    $subtitle_parts['location'] = $location;
}
if ($date) {
    $subtitle_parts['date'] = $date;
}

// Use unified page header template
get_template_part('template-parts/global/page-header-unified', null, array(
    'subtitle_parts' => $subtitle_parts
));
