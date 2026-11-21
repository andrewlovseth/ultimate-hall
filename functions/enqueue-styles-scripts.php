<?php

/*
	Enqueue Styles & Scripts
*/


// Enqueue custom styles and scripts
function bearsmith_enqueue_styles_and_scripts() {
	$uri = get_stylesheet_directory_uri();
    $dir = get_stylesheet_directory();

    $style_last_updated_at = filemtime($dir . '/style.css');

    // Add style.css and third-party css
    wp_enqueue_style( 'style', $uri . '/style.css', array(), $style_last_updated_at );

    // plugins.js & site.js use WordPress's bundled jQuery (3.7+), each versioned by its own file time
    wp_enqueue_script( 'custom-plugins', $uri . '/js/plugins.js', array( 'jquery' ), filemtime( $dir . '/js/plugins.js' ), true );
    wp_enqueue_script( 'custom-site', $uri . '/js/site.js', array( 'jquery', 'custom-plugins' ), filemtime( $dir . '/js/site.js' ), true );

    // Omnibox search (vanilla JS, no jQuery dependency, loaded in footer)
    $search_script_last_updated_at = filemtime( $dir . '/js/search.js' );

    wp_enqueue_script( 'uh-search', $uri . '/js/search.js', array(), $search_script_last_updated_at, true );

    // Hand the REST endpoint + home URL to the script as window.uhSearch
    wp_localize_script( 'uh-search', 'uhSearch', array(
        'restUrl' => esc_url_raw( rest_url( 'ultimatehall/v1/search' ) ),
        'homeUrl' => esc_url_raw( home_url( '/' ) ),
    ) );
}
add_action( 'wp_enqueue_scripts', 'bearsmith_enqueue_styles_and_scripts' );