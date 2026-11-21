<?php
/**
 * REST API shaping for members, teams and tournaments.
 *
 * ACF field groups have "Show in REST" on, so /wp/v2/member etc. carry an
 * `acf` object. Anonymous readers get the public subset: personal and
 * long-form fields are removed unless the request is authenticated by
 * someone who can edit posts.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** ACF member fields hidden from logged-out REST readers. Dotted paths reach into groups. */
function uh_rest_private_member_fields() {
	return array( 'vitals.birthdate', 'vitals.date_of_death', 'interview', 'letters_of_recommendation', 'show_letters', 'induction_speech', 'entries' );
}

add_filter(
	'rest_prepare_member',
	function ( $response ) {
		if ( current_user_can( 'edit_posts' ) || empty( $response->data['acf'] ) || ! is_array( $response->data['acf'] ) ) {
			return $response;
		}
		$acf = $response->data['acf'];
		foreach ( uh_rest_private_member_fields() as $path ) {
			$parts = explode( '.', $path );
			if ( 1 === count( $parts ) ) {
				unset( $acf[ $parts[0] ] );
			} elseif ( isset( $acf[ $parts[0] ] ) && is_array( $acf[ $parts[0] ] ) ) {
				unset( $acf[ $parts[0] ][ $parts[1] ] );
			}
		}
		$response->data['acf'] = $acf;
		return $response;
	},
	99
);
