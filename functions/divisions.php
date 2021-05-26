<?php

function bearsmith_divisions_array($acf_divisions) {
    $divisions = get_field($acf_divisions, 'options');
    $division_array = array();
    foreach($divisions as $division) {
        array_push($division_array, $division->post_name);
    }

    return $division_array;
}

function bearsmith_global_vars() {
	global $divisions;
	$divisions = array(
		'college'  => bearsmith_divisions_array('college_divisions'),
        'club'  => bearsmith_divisions_array('club_divisions'),
        'masters'  => bearsmith_divisions_array('masters_divisions'),
        'grandmasters'  => bearsmith_divisions_array('grandmasters_divisions'),
        'great_grandmasters'  => bearsmith_divisions_array('great_grandmasters_divisions'),
        'professional'  => bearsmith_divisions_array('professional_divisions'),
        'beach'  => bearsmith_divisions_array('beach_divisions'),
	);
}