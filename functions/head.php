<?php
/**
 * Front-end <head> additions done in the theme rather than with an SEO plugin:
 * a meta description, a favicon fallback and font preloads.
 */

/** Plain-text description for the current view, at most ~155 characters. */
function uh_meta_description() {
	$text = '';

	if ( is_front_page() ) {
		$text = get_bloginfo( 'description' );
	} elseif ( is_singular() ) {
		$post = get_queried_object();
		if ( has_excerpt( $post ) ) {
			$text = get_the_excerpt( $post );
		} elseif ( 'member' === $post->post_type && function_exists( 'get_field' ) ) {
			$text = (string) get_field( 'biography', $post->ID );
			if ( '' === trim( wp_strip_all_tags( $text ) ) ) {
				$text = sprintf( '%s, member of the Ultimate Hall of Fame.', get_the_title( $post ) );
			}
		} elseif ( 'year' === $post->post_type ) {
			$text = sprintf( 'The Ultimate Hall of Fame %s: the players, builders and teams inducted.', get_the_title( $post ) );
		} elseif ( 'team' === $post->post_type ) {
			$text = sprintf( '%s: Ultimate Hall of Fame members who played for the team.', get_the_title( $post ) );
		} elseif ( 'tournaments' === $post->post_type ) {
			$text = sprintf( '%s: results for Ultimate Hall of Fame members.', get_the_title( $post ) );
		} else {
			$text = $post->post_content;
		}
	} elseif ( is_post_type_archive( 'member' ) ) {
		$text = 'Every member of the Ultimate Hall of Fame, by induction class.';
	} elseif ( is_archive() ) {
		$text = get_the_archive_description();
	}

	if ( '' === trim( wp_strip_all_tags( (string) $text ) ) ) {
		$text = get_bloginfo( 'description' ) ?: get_bloginfo( 'name' );
	}

	$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( strip_shortcodes( (string) $text ) ) ) );
	return wp_html_excerpt( $text, 155, '…' );
}

add_action( 'wp_head', function () {
	if ( is_404() || is_search() ) {
		return;
	}
	$desc = uh_meta_description();
	if ( $desc ) {
		printf( "<meta name=\"description\" content=\"%s\">\n", esc_attr( $desc ) );
	}
}, 1 );

// Browsers ask for /favicon.ico when no icon is declared, which 404s. Use the Site Icon when
// one is set (wp_site_icon prints it); otherwise point at the header logo.
add_action( 'wp_head', function () {
	if ( has_site_icon() || ! function_exists( 'get_field' ) ) {
		return;
	}
	$logo = get_field( 'header_logo', 'options' );
	if ( ! empty( $logo['url'] ) ) {
		$type = ! empty( $logo['mime_type'] ) ? $logo['mime_type'] : 'image/svg+xml';
		printf( "<link rel=\"icon\" href=\"%s\" type=\"%s\">\n", esc_url( $logo['url'] ), esc_attr( $type ) );
	}
}, 2 );

// Preload the two faces nearly every page paints first, so text doesn't wait on the stylesheet.
add_action( 'wp_head', function () {
	$uri = get_stylesheet_directory_uri() . '/fonts/';
	foreach ( array( 'work-sans-normal-latin.woff2', 'merriweather-normal-latin.woff2' ) as $file ) {
		printf( "<link rel=\"preload\" href=\"%s\" as=\"font\" type=\"font/woff2\" crossorigin>\n", esc_url( $uri . $file ) );
	}
}, 2 );
