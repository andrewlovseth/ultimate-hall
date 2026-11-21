<?php
/**
 * Check the Claude sign-in (functions/oauth.php) on a local copy:
 *
 *   ddev wp eval-file wp/wp-content/themes/ultimate-hall/ops/test-oauth.php <editor-login>
 *
 * Runs registration, code, token, refresh, bearer auth and revoke in-process,
 * plus the discovery documents and the MCP 401 over HTTP. Removes what it adds.
 * Never run it on production.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}
if ( false !== strpos( home_url(), 'ultimatehall.org' ) ) {
	WP_CLI::error( 'Run this on a local copy, not production.' );
}
$editor = get_user_by( 'login', $args[0] ?? '' );
if ( ! $editor || ! uh_oauth_can_connect( $editor->ID ) ) {
	WP_CLI::error( 'Pass the login of an Editor.' );
}

$fails = 0;
$check = function ( $label, $ok, $detail = '' ) use ( &$fails ) {
	WP_CLI::log( ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok ? '' : '  ' . ( is_string( $detail ) ? $detail : wp_json_encode( $detail ) ) ) );
	$fails += $ok ? 0 : 1;
};
$post = function ( $route, $params, $json = false ) {
	$r = new WP_REST_Request( 'POST', $route );
	if ( $json ) {
		$r->set_header( 'content-type', 'application/json' );
		$r->set_body( wp_json_encode( $params ) );
	} else {
		$r->set_body_params( $params );
	}
	return rest_do_request( $r );
};
$clients_before = get_option( UH_OAUTH_CLIENTS, array() );
$grants_before  = get_option( UH_OAUTH_GRANTS, array() );
$no_ssl         = array( 'sslverify' => false, 'redirection' => 0, 'timeout' => 10 );

// Discovery over HTTP.
$r = wp_remote_get( home_url( '/.well-known/oauth-protected-resource/wp-json/uhof/mcp' ), $no_ssl );
$j = json_decode( wp_remote_retrieve_body( $r ), true );
$check( 'protected resource metadata served', ( $j['resource'] ?? '' ) === uh_oauth_resource(), is_wp_error( $r ) ? $r->get_error_message() : wp_remote_retrieve_body( $r ) );
$r = wp_remote_get( home_url( '/.well-known/oauth-authorization-server' ), $no_ssl );
$j = json_decode( wp_remote_retrieve_body( $r ), true );
$check( 'authorization server metadata served', ( $j['issuer'] ?? '' ) === uh_oauth_issuer() && in_array( 'S256', $j['code_challenge_methods_supported'] ?? array(), true ), wp_remote_retrieve_body( $r ) );
$r = wp_remote_post( uh_oauth_resource(), $no_ssl + array( 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}' ) );
$check( 'MCP without sign-in: 401 with resource_metadata', 401 === wp_remote_retrieve_response_code( $r ) && false !== strpos( (string) wp_remote_retrieve_header( $r, 'www-authenticate' ), 'resource_metadata=' ), wp_remote_retrieve_response_code( $r ) . ' ' . wp_remote_retrieve_header( $r, 'www-authenticate' ) );
$r = wp_remote_get( add_query_arg( array( 'client_id' => 'nope' ), home_url( '/oauth/authorize' ) ), $no_ssl );
$check( 'authorize with an unknown client shows an error, no redirect', 200 === wp_remote_retrieve_response_code( $r ) && false !== strpos( wp_remote_retrieve_body( $r ), 'Can' ), wp_remote_retrieve_response_code( $r ) );

// Registration.
$res = $post( '/uhof/oauth/register', array( 'client_name' => 'Evil', 'redirect_uris' => array( 'https://evil.example/cb' ) ), true );
$check( 'register refuses a foreign redirect', 400 === $res->get_status() );
$res = $post( '/uhof/oauth/register', array( 'client_name' => 'claude.ai test', 'redirect_uris' => array( 'https://claude.ai/api/mcp/auth_callback' ) ), true );
$cid = $res->get_data()['client_id'] ?? '';
$check( 'register Claude', 201 === $res->get_status() && $cid, $res->get_data() );
$res = $post( '/uhof/oauth/register', array( 'redirect_uris' => array( 'http://localhost:54321/callback' ) ), true );
$check( 'register a localhost redirect (Claude Code)', 201 === $res->get_status() );

// Authorize parameter checks.
$verifier  = rtrim( strtr( base64_encode( random_bytes( 48 ) ), '+/', '-_' ), '=' );
$challenge = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
$params    = array( 'response_type' => 'code', 'client_id' => $cid, 'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback', 'code_challenge' => $challenge, 'code_challenge_method' => 'S256', 'state' => 'xyz', 'resource' => uh_oauth_resource() );
$check( 'authorize params accepted', ! is_wp_error( uh_oauth_authorize_params( $params ) ) );
$check( 'authorize refuses a different redirect', is_wp_error( uh_oauth_authorize_params( array( 'redirect_uri' => 'https://claude.ai/other' ) + $params ) ) );
$check( 'authorize refuses plain PKCE', is_wp_error( uh_oauth_authorize_params( array( 'code_challenge_method' => 'plain' ) + $params ) ) );
$check( 'authorize refuses another resource', is_wp_error( uh_oauth_authorize_params( array( 'resource' => 'https://example.com/mcp' ) + $params ) ) );

// Code exchange.
$p    = uh_oauth_authorize_params( $params );
$code = uh_oauth_create_code( $p, $editor->ID );
$res  = $post( '/uhof/oauth/token', array( 'grant_type' => 'authorization_code', 'code' => $code, 'client_id' => $cid, 'redirect_uri' => $params['redirect_uri'], 'code_verifier' => str_repeat( 'a', 43 ) ) );
$check( 'token refuses a wrong verifier', 400 === $res->get_status() );
$res = $post( '/uhof/oauth/token', array( 'grant_type' => 'authorization_code', 'code' => $code, 'client_id' => $cid, 'redirect_uri' => $params['redirect_uri'], 'code_verifier' => $verifier ) );
$check( 'a code is single-use, even after a failed try', 400 === $res->get_status() );
$code = uh_oauth_create_code( $p, $editor->ID );
$res  = $post( '/uhof/oauth/token', array( 'grant_type' => 'authorization_code', 'code' => $code, 'client_id' => $cid, 'redirect_uri' => $params['redirect_uri'], 'code_verifier' => $verifier ) );
$tok  = $res->get_data();
$check( 'token exchange', 200 === $res->get_status() && 0 === strpos( $tok['access_token'] ?? '', 'uhat_' ) && 3600 === $tok['expires_in'], $tok );
$stored = wp_json_encode( get_option( UH_OAUTH_GRANTS ) );
$check( 'tokens stored only as hashes', false === strpos( $stored, $tok['access_token'] ) && false === strpos( $stored, $tok['refresh_token'] ) );

// Bearer auth.
$as = function ( $token, $uri ) {
	$_SERVER['REQUEST_URI']        = $uri;
	$_SERVER['HTTP_AUTHORIZATION'] = $token ? 'Bearer ' . $token : '';
	unset( $_GET['rest_route'] );
	return (int) apply_filters( 'determine_current_user', 0 );
};
$check( 'bearer token signs in on the MCP route', $editor->ID === $as( $tok['access_token'], '/wp-json/uhof/mcp' ) );
$check( 'bearer token ignored on other routes', 0 === $as( $tok['access_token'], '/wp-json/wp/v2/users/me' ) );
$check( 'made-up token refused', 0 === $as( 'uhat_' . str_repeat( '0', 64 ), '/wp-json/uhof/mcp' ) );
$r = wp_remote_post( uh_oauth_resource(), $no_ssl + array( 'headers' => array( 'Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream', 'Authorization' => 'Bearer ' . $tok['access_token'] ), 'body' => '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"test","version":"1"}}}' ) );
$check( 'MCP initialize over HTTP with the bearer token', 200 === wp_remote_retrieve_response_code( $r ), wp_remote_retrieve_response_code( $r ) . ' ' . substr( wp_remote_retrieve_body( $r ), 0, 200 ) );

// Refresh rotates both tokens.
$res  = $post( '/uhof/oauth/token', array( 'grant_type' => 'refresh_token', 'refresh_token' => $tok['refresh_token'], 'client_id' => $cid ) );
$tok2 = $res->get_data();
$check( 'refresh issues new tokens', 200 === $res->get_status() && $tok2['access_token'] !== $tok['access_token'], $tok2 );
$check( 'old access token stops working', 0 === $as( $tok['access_token'], '/wp-json/uhof/mcp' ) );
$check( 'old refresh token refused', 400 === $post( '/uhof/oauth/token', array( 'grant_type' => 'refresh_token', 'refresh_token' => $tok['refresh_token'], 'client_id' => $cid ) )->get_status() );

// Profile list and revoke.
$check( 'connection listed for the editor', 1 <= count( uh_oauth_user_grants( $editor->ID ) ) );
$post( '/uhof/oauth/revoke', array( 'token' => $tok2['refresh_token'] ) );
$check( 'revoke ends the connection', 0 === $as( $tok2['access_token'], '/wp-json/uhof/mcp' ) );

// A password changed on the profile page ends the connection.
$ed2  = wp_insert_user( array( 'user_login' => 'zz-oauth-ed', 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
$code = uh_oauth_create_code( $p, $ed2 );
$tok3 = $post( '/uhof/oauth/token', array( 'grant_type' => 'authorization_code', 'code' => $code, 'client_id' => $cid, 'redirect_uri' => $params['redirect_uri'], 'code_verifier' => $verifier ) )->get_data();
$check( 'second editor connects', $ed2 === $as( $tok3['access_token'] ?? '', '/wp-json/uhof/mcp' ) );
wp_update_user( array( 'ID' => $ed2, 'user_pass' => wp_generate_password() ) );
$check( 'profile password change ends the connection', 0 === $as( $tok3['access_token'] ?? '', '/wp-json/uhof/mcp' ) );

// A subscriber can't connect.
$sub = wp_insert_user( array( 'user_login' => 'zz-oauth-sub', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$code = uh_oauth_create_code( $p, $sub );
$check( 'non-editor code refused at exchange', 400 === $post( '/uhof/oauth/token', array( 'grant_type' => 'authorization_code', 'code' => $code, 'client_id' => $cid, 'redirect_uri' => $params['redirect_uri'], 'code_verifier' => $verifier ) )->get_status() );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $sub );
wp_delete_user( $ed2 );

unset( $_SERVER['HTTP_AUTHORIZATION'] );
update_option( UH_OAUTH_CLIENTS, $clients_before, false );
update_option( UH_OAUTH_GRANTS, $grants_before, false );
$fails ? WP_CLI::error( "{$fails} checks failed." ) : WP_CLI::success( 'All sign-in checks passed; test clients and tokens removed.' );
