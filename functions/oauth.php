<?php
/**
 * Sign in to the Hall's MCP server with OAuth, so claude.ai (and Claude Code,
 * Claude Desktop connectors) get a normal "connect" button instead of an
 * Application Password pasted into a config file.
 *
 * A deliberately small OAuth 2.1 authorization server, scoped to one resource:
 * the uhof MCP server at /wp-json/uhof/mcp. No plugin.
 *
 *   /.well-known/oauth-protected-resource[/wp-json/uhof/mcp]   RFC 9728 metadata
 *   /.well-known/oauth-authorization-server                    RFC 8414 metadata
 *   POST /wp-json/uhof/oauth/register                          RFC 7591 client registration
 *   /oauth/authorize                                           WordPress login, then an Allow screen
 *   POST /wp-json/uhof/oauth/token                             code (PKCE S256 only) and refresh grants
 *   POST /wp-json/uhof/oauth/revoke                            RFC 7009
 *
 * Rules that keep it small and safe:
 *   - Public clients only, PKCE S256 required, codes single-use for 5 minutes.
 *   - Redirect URIs are limited to Claude's own callbacks and localhost
 *     (Claude Code), so a registered client can't send codes anywhere else.
 *   - Tokens are random 256-bit values stored only as SHA-256 hashes. Access
 *     tokens last an hour; refresh tokens 30 days and rotate on use.
 *   - A bearer token is accepted only on the uhof MCP route, only while its
 *     user can still edit others' posts (Editor and up).
 *   - Each user sees and revokes their connections on their profile page.
 *     Changing a password revokes all of that user's connections.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const UH_OAUTH_CLIENTS     = 'uh_oauth_clients';
const UH_OAUTH_GRANTS      = 'uh_oauth_grants';
const UH_OAUTH_MAX_CLIENTS = 200;
const UH_OAUTH_ACCESS_TTL  = HOUR_IN_SECONDS;
const UH_OAUTH_REFRESH_TTL = 30 * DAY_IN_SECONDS;
const UH_OAUTH_CODE_TTL    = 5 * MINUTE_IN_SECONDS;
const UH_OAUTH_SCOPE       = 'uhof';

/** The one protected resource: the uhof MCP server. */
function uh_oauth_resource() {
	return rest_url( 'uhof/mcp' );
}

function uh_oauth_issuer() {
	return untrailingslashit( home_url() );
}

/** Redirect URIs a client may register: Claude's callbacks, or a loopback address for Claude Code. */
function uh_oauth_redirect_allowed( $uri ) {
	$allowed = apply_filters( 'uh_oauth_redirect_uris', array( 'https://claude.ai/api/mcp/auth_callback', 'https://claude.com/api/mcp/auth_callback' ) );
	if ( in_array( $uri, $allowed, true ) ) {
		return true;
	}
	$p = wp_parse_url( $uri );
	return $p && 'http' === ( $p['scheme'] ?? '' ) && in_array( $p['host'] ?? '', array( 'localhost', '127.0.0.1' ), true ) && empty( $p['user'] ) && empty( $p['fragment'] );
}

function uh_oauth_hash( $token ) {
	return hash( 'sha256', (string) $token );
}

function uh_oauth_random( $prefix ) {
	return $prefix . bin2hex( random_bytes( 32 ) );
}

function uh_oauth_can_connect( $user_id ) {
	return $user_id && user_can( $user_id, 'edit_others_posts' );
}

function uh_oauth_json( $data, $status = 200 ) {
	nocache_headers();
	status_header( $status );
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Access-Control-Allow-Origin: *' );
	echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES );
	exit;
}

/** RFC 6749 error body. */
function uh_oauth_error( $code, $description, $status = 400 ) {
	return new WP_REST_Response( array( 'error' => $code, 'error_description' => $description ), $status );
}

/* ---------- discovery ---------- */

function uh_oauth_resource_metadata() {
	return array(
		'resource'                 => uh_oauth_resource(),
		'authorization_servers'    => array( uh_oauth_issuer() ),
		'bearer_methods_supported' => array( 'header' ),
		'scopes_supported'         => array( UH_OAUTH_SCOPE ),
		'resource_name'            => get_bloginfo( 'name' ),
	);
}

function uh_oauth_server_metadata() {
	return array(
		'issuer'                                => uh_oauth_issuer(),
		'authorization_endpoint'                => home_url( '/oauth/authorize' ),
		'token_endpoint'                        => rest_url( 'uhof/oauth/token' ),
		'registration_endpoint'                 => rest_url( 'uhof/oauth/register' ),
		'revocation_endpoint'                   => rest_url( 'uhof/oauth/revoke' ),
		'response_types_supported'              => array( 'code' ),
		'grant_types_supported'                 => array( 'authorization_code', 'refresh_token' ),
		'code_challenge_methods_supported'      => array( 'S256' ),
		'token_endpoint_auth_methods_supported' => array( 'none' ),
		'scopes_supported'                      => array( UH_OAUTH_SCOPE ),
	);
}

/** Serve the well-known documents and the authorize page before WordPress routes the request. */
add_action(
	'init',
	function () {
		$path = untrailingslashit( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) );
		$home = untrailingslashit( (string) wp_parse_url( home_url(), PHP_URL_PATH ) );
		$path = substr( $path, strlen( $home ) );
		if ( 0 === strpos( $path, '/.well-known/oauth-protected-resource' ) ) {
			uh_oauth_json( uh_oauth_resource_metadata() );
		}
		if ( '/.well-known/oauth-authorization-server' === $path || '/.well-known/oauth-authorization-server/wp-json/uhof/mcp' === $path ) {
			uh_oauth_json( uh_oauth_server_metadata() );
		}
		if ( '/oauth/authorize' === $path ) {
			uh_oauth_authorize_page();
		}
	},
	1
);

/* ---------- clients ---------- */

function uh_oauth_client( $client_id ) {
	$clients = get_option( UH_OAUTH_CLIENTS, array() );
	return is_string( $client_id ) && isset( $clients[ $client_id ] ) ? $clients[ $client_id ] : null;
}

function uh_oauth_register( WP_REST_Request $request ) {
	$body = $request->get_json_params() ?: $request->get_body_params();
	$uris = array_values( array_filter( (array) ( $body['redirect_uris'] ?? array() ), 'is_string' ) );
	if ( ! $uris ) {
		return uh_oauth_error( 'invalid_redirect_uri', 'redirect_uris is required.' );
	}
	foreach ( $uris as $uri ) {
		if ( ! uh_oauth_redirect_allowed( $uri ) ) {
			return uh_oauth_error( 'invalid_redirect_uri', 'This server only issues codes to Claude (claude.ai, claude.com) and to localhost.' );
		}
	}
	$method = $body['token_endpoint_auth_method'] ?? 'none';
	if ( 'none' !== $method ) {
		return uh_oauth_error( 'invalid_client_metadata', 'Only public clients (token_endpoint_auth_method "none") are supported.' );
	}
	$id      = 'uhc_' . bin2hex( random_bytes( 16 ) );
	$name    = sanitize_text_field( (string) ( $body['client_name'] ?? 'Claude' ) ) ?: 'Claude';
	$clients = get_option( UH_OAUTH_CLIENTS, array() );
	$clients[ $id ] = array( 'name' => substr( $name, 0, 80 ), 'redirect_uris' => $uris, 'created' => time() );
	if ( count( $clients ) > UH_OAUTH_MAX_CLIENTS ) {
		// Drop the oldest registrations that no live connection uses.
		$used = array_column( get_option( UH_OAUTH_GRANTS, array() ), 'client_id' );
		uasort( $clients, fn( $a, $b ) => $a['created'] <=> $b['created'] );
		foreach ( array_keys( $clients ) as $cid ) {
			if ( count( $clients ) <= UH_OAUTH_MAX_CLIENTS ) {
				break;
			}
			if ( $cid !== $id && ! in_array( $cid, $used, true ) ) {
				unset( $clients[ $cid ] );
			}
		}
	}
	update_option( UH_OAUTH_CLIENTS, $clients, false );
	return new WP_REST_Response(
		array(
			'client_id'                  => $id,
			'client_id_issued_at'        => $clients[ $id ]['created'],
			'client_name'                => $clients[ $id ]['name'],
			'redirect_uris'              => $uris,
			'grant_types'                => array( 'authorization_code', 'refresh_token' ),
			'response_types'             => array( 'code' ),
			'token_endpoint_auth_method' => 'none',
		),
		201
	);
}

/* ---------- authorize ---------- */

/** Validate an authorize request. Errors before the redirect URI is trusted are shown, never redirected. */
function uh_oauth_authorize_params( array $src ) {
	$p = array();
	foreach ( array( 'response_type', 'client_id', 'redirect_uri', 'code_challenge', 'code_challenge_method', 'state', 'scope', 'resource' ) as $k ) {
		$p[ $k ] = isset( $src[ $k ] ) ? (string) wp_unslash( $src[ $k ] ) : '';
	}
	$client = uh_oauth_client( $p['client_id'] );
	if ( ! $client ) {
		return new WP_Error( 'invalid_client', 'This app isn\'t registered with the Hall. Start the connection again from Claude.' );
	}
	if ( ! in_array( $p['redirect_uri'], $client['redirect_uris'], true ) ) {
		return new WP_Error( 'invalid_request', 'The return address doesn\'t match this app\'s registration.' );
	}
	if ( 'code' !== $p['response_type'] || 'S256' !== $p['code_challenge_method'] || ! preg_match( '/^[A-Za-z0-9_-]{43,128}$/', $p['code_challenge'] ) ) {
		return new WP_Error( 'invalid_request', 'The request is missing a valid PKCE code challenge.' );
	}
	if ( '' !== $p['resource'] && untrailingslashit( $p['resource'] ) !== untrailingslashit( uh_oauth_resource() ) ) {
		return new WP_Error( 'invalid_target', 'This server only signs in to the Hall of Fame tools.' );
	}
	$p['client_name'] = $client['name'];
	return $p;
}

/** A single-use authorization code for validated params and a user who approved. */
function uh_oauth_create_code( array $p, $user_id ) {
	$code = uh_oauth_random( 'uhcd_' );
	set_transient(
		'uh_oauth_code_' . uh_oauth_hash( $code ),
		array(
			'client_id'      => $p['client_id'],
			'redirect_uri'   => $p['redirect_uri'],
			'code_challenge' => $p['code_challenge'],
			'user_id'        => (int) $user_id,
		),
		UH_OAUTH_CODE_TTL
	);
	return $code;
}

function uh_oauth_redirect( $uri, array $args ) {
	wp_redirect( add_query_arg( array_map( 'rawurlencode', array_filter( $args, 'strlen' ) ), $uri ) );
	exit;
}

function uh_oauth_authorize_page() {
	nocache_headers();
	header( 'X-Frame-Options: DENY' );
	header( "Content-Security-Policy: frame-ancestors 'none'" );
	$post = 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' );
	$p    = uh_oauth_authorize_params( $post ? $_POST : $_GET );
	if ( is_wp_error( $p ) ) {
		uh_oauth_screen( 'Can\'t connect', $p->get_error_message() );
	}
	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( home_url( add_query_arg( null, null ) ) ) );
		exit;
	}
	$user = wp_get_current_user();
	if ( ! uh_oauth_can_connect( $user->ID ) ) {
		uh_oauth_screen( 'Can\'t connect', 'Your account (' . $user->user_login . ') can\'t edit Hall of Fame content, so it can\'t connect Claude. Ask a site admin for an Editor account.' );
	}
	if ( $post ) {
		if ( ! wp_verify_nonce( $_POST['_uh_oauth'] ?? '', 'uh_oauth_authorize_' . $p['client_id'] ) ) {
			uh_oauth_screen( 'Can\'t connect', 'That approval expired. Start the connection again from Claude.' );
		}
		if ( 'allow' !== ( $_POST['decision'] ?? '' ) ) {
			uh_oauth_redirect( $p['redirect_uri'], array( 'error' => 'access_denied', 'state' => $p['state'], 'iss' => uh_oauth_issuer() ) );
		}
		$code = uh_oauth_create_code( $p, $user->ID );
		uh_oauth_redirect( $p['redirect_uri'], array( 'code' => $code, 'state' => $p['state'], 'iss' => uh_oauth_issuer() ) );
	}

	$host   = wp_parse_url( $p['redirect_uri'], PHP_URL_HOST );
	$hidden = '';
	foreach ( array( 'response_type', 'client_id', 'redirect_uri', 'code_challenge', 'code_challenge_method', 'state', 'scope', 'resource' ) as $k ) {
		$hidden .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $p[ $k ] ) . '">';
	}
	$body = '<p><strong>' . esc_html( $p['client_name'] ) . '</strong> (' . esc_html( $host ) . ') wants to use the Hall of Fame tools as <strong>' . esc_html( $user->display_name ) . '</strong>.</p>'
		. '<p>It will be able to find, add, change, publish and trash members, teams, tournaments and classes, the same as your account can in wp-admin. Every change is logged and can be undone. You can disconnect it any time from your profile page.</p>'
		. '<form method="post" action="' . esc_url( home_url( '/oauth/authorize' ) ) . '">' . $hidden
		. wp_nonce_field( 'uh_oauth_authorize_' . $p['client_id'], '_uh_oauth', false, false )
		. '<div class="actions"><button name="decision" value="allow" class="primary">Allow</button><button name="decision" value="deny">Cancel</button></div></form>';
	uh_oauth_screen( 'Connect Claude to the Hall of Fame', $body, true );
}

/** A plain standalone page; no theme chrome, so nothing else on it can be clicked. */
function uh_oauth_screen( $title, $body, $html = false ) {
	status_header( 200 );
	header( 'Content-Type: text/html; charset=utf-8' );
	echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">'
		. '<title>' . esc_html( $title ) . '</title><style>'
		. ':root{--bg:#f6f7f5;--fg:#1d2321;--muted:#5b6662;--card:#fff;--line:#d9dedb;--accent:#1f6f4a;--on-accent:#fff}'
		. '@media (prefers-color-scheme:dark){:root{--bg:#141816;--fg:#e8ece9;--muted:#a3ada8;--card:#1d2321;--line:#33403a;--accent:#4fb383;--on-accent:#0c120f}}'
		. 'body{margin:0;background:var(--bg);color:var(--fg);font:16px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;padding:48px max(16px,env(safe-area-inset-left))}'
		. 'main{max-width:440px;margin:0 auto;background:var(--card);border:1px solid var(--line);border-radius:12px;padding:28px}'
		. 'h1{font-size:20px;margin:0 0 12px}p{margin:0 0 12px;color:var(--fg)}p+p{color:var(--muted)}'
		. '.actions{display:flex;gap:12px;margin-top:20px}button{font:inherit;padding:10px 18px;border-radius:8px;border:1px solid var(--line);background:transparent;color:var(--fg);cursor:pointer}'
		. 'button.primary{background:var(--accent);border-color:var(--accent);color:var(--on-accent)}button:focus-visible{outline:2px solid var(--accent);outline-offset:2px}'
		. '</style></head><body><main><h1>' . esc_html( $title ) . '</h1>' . ( $html ? $body : '<p>' . esc_html( $body ) . '</p>' ) . '</main></body></html>';
	exit;
}

/* ---------- tokens ---------- */

/** Issue a fresh token pair on a grant (new, or rotated on refresh). */
function uh_oauth_issue( $grant_id, array $grant ) {
	$access          = uh_oauth_random( 'uhat_' );
	$refresh         = uh_oauth_random( 'uhrt_' );
	$grant['access'] = uh_oauth_hash( $access );
	$grant['access_exp']  = time() + UH_OAUTH_ACCESS_TTL;
	$grant['refresh']     = uh_oauth_hash( $refresh );
	$grant['refresh_exp'] = time() + UH_OAUTH_REFRESH_TTL;
	$grants              = get_option( UH_OAUTH_GRANTS, array() );
	// Drop grants whose refresh token has expired while we're here.
	$grants              = array_filter( $grants, fn( $g ) => $g['refresh_exp'] > time() );
	$grants[ $grant_id ] = $grant;
	update_option( UH_OAUTH_GRANTS, $grants, false );
	return new WP_REST_Response(
		array(
			'access_token'  => $access,
			'token_type'    => 'Bearer',
			'expires_in'    => UH_OAUTH_ACCESS_TTL,
			'refresh_token' => $refresh,
			'scope'         => UH_OAUTH_SCOPE,
		),
		200,
		array( 'Cache-Control' => 'no-store', 'Pragma' => 'no-cache' )
	);
}

/** Find a grant by a token hash in the given slot ('access' or 'refresh'). */
function uh_oauth_find_grant( $token, $slot ) {
	$hash = uh_oauth_hash( $token );
	foreach ( get_option( UH_OAUTH_GRANTS, array() ) as $id => $g ) {
		if ( hash_equals( (string) $g[ $slot ], $hash ) ) {
			return array( $id, $g );
		}
	}
	return array( null, null );
}

function uh_oauth_token( WP_REST_Request $request ) {
	$type      = (string) $request->get_param( 'grant_type' );
	$client_id = (string) $request->get_param( 'client_id' );
	$client    = uh_oauth_client( $client_id );
	if ( ! $client ) {
		return uh_oauth_error( 'invalid_client', 'Unknown client_id.', 401 );
	}

	if ( 'authorization_code' === $type ) {
		$code = (string) $request->get_param( 'code' );
		$key  = 'uh_oauth_code_' . uh_oauth_hash( $code );
		$data = get_transient( $key );
		delete_transient( $key ); // Single use, even when the exchange fails.
		if ( ! $data || $data['client_id'] !== $client_id || $data['redirect_uri'] !== (string) $request->get_param( 'redirect_uri' ) ) {
			return uh_oauth_error( 'invalid_grant', 'The code is invalid, expired or was issued to another client.' );
		}
		$verifier = (string) $request->get_param( 'code_verifier' );
		$expected = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
		if ( ! preg_match( '/^[A-Za-z0-9._~-]{43,128}$/', $verifier ) || ! hash_equals( $data['code_challenge'], $expected ) ) {
			return uh_oauth_error( 'invalid_grant', 'PKCE verification failed.' );
		}
		if ( ! uh_oauth_can_connect( $data['user_id'] ) ) {
			return uh_oauth_error( 'invalid_grant', 'This account can no longer edit Hall of Fame content.' );
		}
		return uh_oauth_issue(
			'uhg_' . bin2hex( random_bytes( 8 ) ),
			array(
				'user_id'     => (int) $data['user_id'],
				'client_id'   => $client_id,
				'client_name' => $client['name'],
				'created'     => time(),
				'last_used'   => 0,
			)
		);
	}

	if ( 'refresh_token' === $type ) {
		list( $id, $grant ) = uh_oauth_find_grant( (string) $request->get_param( 'refresh_token' ), 'refresh' );
		if ( ! $grant || $grant['client_id'] !== $client_id || $grant['refresh_exp'] < time() || ! uh_oauth_can_connect( $grant['user_id'] ) ) {
			return uh_oauth_error( 'invalid_grant', 'The refresh token is invalid or expired. Connect again from Claude.' );
		}
		return uh_oauth_issue( $id, $grant );
	}

	return uh_oauth_error( 'unsupported_grant_type', 'Use authorization_code or refresh_token.' );
}

function uh_oauth_revoke_grant( $grant_id ) {
	$grants = get_option( UH_OAUTH_GRANTS, array() );
	unset( $grants[ $grant_id ] );
	update_option( UH_OAUTH_GRANTS, $grants, false );
}

function uh_oauth_revoke( WP_REST_Request $request ) {
	$token = (string) $request->get_param( 'token' );
	foreach ( array( 'access', 'refresh' ) as $slot ) {
		list( $id ) = uh_oauth_find_grant( $token, $slot );
		if ( $id ) {
			uh_oauth_revoke_grant( $id );
			break;
		}
	}
	return new WP_REST_Response( null, 200 ); // RFC 7009: 200 even for unknown tokens.
}

add_action(
	'rest_api_init',
	function () {
		$open = '__return_true'; // Public by design: these are the OAuth endpoints themselves.
		register_rest_route( 'uhof/oauth', '/register', array( 'methods' => 'POST', 'callback' => 'uh_oauth_register', 'permission_callback' => $open ) );
		register_rest_route( 'uhof/oauth', '/token', array( 'methods' => 'POST', 'callback' => 'uh_oauth_token', 'permission_callback' => $open ) );
		register_rest_route( 'uhof/oauth', '/revoke', array( 'methods' => 'POST', 'callback' => 'uh_oauth_revoke', 'permission_callback' => $open ) );
	}
);

/* ---------- bearer auth on the MCP route ---------- */

function uh_oauth_is_mcp_request() {
	$route = isset( $_GET['rest_route'] ) ? (string) $_GET['rest_route'] : (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
	return (bool) preg_match( '#/uhof/mcp/?$#', $route );
}

function uh_oauth_bearer() {
	$header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
	return preg_match( '/^Bearer\s+(uhat_[a-f0-9]{64})$/', trim( (string) $header ), $m ) ? $m[1] : '';
}

add_filter(
	'determine_current_user',
	function ( $user_id ) {
		if ( $user_id || ! uh_oauth_is_mcp_request() ) {
			return $user_id;
		}
		$token = uh_oauth_bearer();
		if ( ! $token ) {
			return $user_id;
		}
		list( $id, $grant ) = uh_oauth_find_grant( $token, 'access' );
		if ( ! $grant || $grant['access_exp'] < time() || ! uh_oauth_can_connect( $grant['user_id'] ) ) {
			return $user_id;
		}
		if ( time() - (int) $grant['last_used'] > 5 * MINUTE_IN_SECONDS ) {
			$grants                     = get_option( UH_OAUTH_GRANTS, array() );
			$grants[ $id ]['last_used'] = time();
			update_option( UH_OAUTH_GRANTS, $grants, false );
		}
		return (int) $grant['user_id'];
	},
	30
);

/** Unauthenticated calls to the MCP route get a 401 that tells the client where to sign in. */
add_filter(
	'rest_pre_dispatch',
	function ( $result, $server, $request ) {
		if ( null !== $result || ! preg_match( '#^/uhof/mcp/?$#', $request->get_route() ) || is_user_logged_in() ) {
			return $result;
		}
		if ( 'OPTIONS' === $request->get_method() ) {
			return $result;
		}
		$metadata = home_url( '/.well-known/oauth-protected-resource/wp-json/uhof/mcp' );
		$server->send_header( 'WWW-Authenticate', 'Bearer resource_metadata="' . $metadata . '"' . ( uh_oauth_bearer() ? ', error="invalid_token"' : '' ) );
		return new WP_Error( 'uh_oauth_required', 'Sign in to the Hall of Fame to use these tools.', array( 'status' => 401 ) );
	},
	10,
	3
);

/* ---------- connections on the profile page ---------- */

function uh_oauth_user_grants( $user_id ) {
	return array_filter( get_option( UH_OAUTH_GRANTS, array() ), fn( $g ) => (int) $g['user_id'] === (int) $user_id && $g['refresh_exp'] > time() );
}

function uh_oauth_profile_section( $user ) {
	$grants = uh_oauth_user_grants( $user->ID );
	echo '<h2>Claude connections</h2>';
	if ( ! $grants ) {
		echo '<p class="description">No Claude apps are connected to this account. Connect one from Claude with the Hall\'s address: <code>' . esc_html( uh_oauth_resource() ) . '</code></p>';
		return;
	}
	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( $grants as $id => $g ) {
		$used = $g['last_used'] ? human_time_diff( $g['last_used'] ) . ' ago' : 'not yet';
		echo '<tr><th scope="row">' . esc_html( $g['client_name'] ) . '</th><td>Connected ' . esc_html( wp_date( 'Y-m-d', $g['created'] ) ) . ', last used ' . esc_html( $used ) . '. '
			. '<label><input type="checkbox" name="uh_oauth_revoke[]" value="' . esc_attr( $id ) . '"> Disconnect</label></td></tr>';
	}
	echo '</tbody></table>';
	wp_nonce_field( 'uh_oauth_profile', '_uh_oauth_profile' );
}
add_action( 'show_user_profile', 'uh_oauth_profile_section' );
add_action( 'edit_user_profile', 'uh_oauth_profile_section' );

function uh_oauth_profile_save( $user_id ) {
	if ( empty( $_POST['uh_oauth_revoke'] ) || ! current_user_can( 'edit_user', $user_id ) || ! wp_verify_nonce( $_POST['_uh_oauth_profile'] ?? '', 'uh_oauth_profile' ) ) {
		return;
	}
	$mine = uh_oauth_user_grants( $user_id );
	foreach ( (array) $_POST['uh_oauth_revoke'] as $id ) {
		if ( isset( $mine[ $id ] ) ) {
			uh_oauth_revoke_grant( $id );
		}
	}
}
add_action( 'personal_options_update', 'uh_oauth_profile_save' );
add_action( 'edit_user_profile_update', 'uh_oauth_profile_save' );

/** A new password ends every Claude connection on that account. */
add_action(
	'wp_set_password',
	function ( $password, $user_id ) {
		foreach ( array_keys( uh_oauth_user_grants( $user_id ) ) as $id ) {
			uh_oauth_revoke_grant( $id );
		}
	},
	10,
	2
);
add_action( 'delete_user', fn( $user_id ) => array_map( 'uh_oauth_revoke_grant', array_keys( uh_oauth_user_grants( $user_id ) ) ) );
