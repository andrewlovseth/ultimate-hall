<?php
/**
 * End-to-end check of every uhof/* ability, run as an Editor on a local copy:
 *
 *   wp eval-file wp-content/themes/ultimate-hall/ops/test-abilities.php <editor-login>
 *
 * Creates throwaway "Zz Test" posts and a 2099 class, exercises each tool
 * (including refusals, undo and merge), then deletes everything it made.
 * Never run it on production.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}
if ( false !== strpos( home_url(), 'ultimatehall.org' ) ) {
	WP_CLI::error( 'This creates and deletes posts. Run it on a local copy, not production.' );
}

$login = $args[0] ?? '';
$user  = get_user_by( 'login', $login );
if ( ! $user || ! user_can( $user, 'edit_others_posts' ) || user_can( $user, 'manage_options' ) ) {
	WP_CLI::error( 'Pass the login of an Editor (not an admin), so permissions are tested as Kendra would have them.' );
}
wp_set_current_user( $user->ID );

$fails = 0;
$made  = array();
$call  = function ( $name, $input = array() ) {
	$ability = wp_get_ability( 'uhof/' . $name );
	if ( ! $ability ) {
		return new WP_Error( 'missing', "uhof/{$name} is not registered" );
	}
	return $ability->execute( $input );
};
$check = function ( $label, $ok, $detail = '' ) use ( &$fails ) {
	if ( $ok ) {
		WP_CLI::log( "PASS  {$label}" );
	} else {
		$fails++;
		WP_CLI::log( "FAIL  {$label}  " . ( is_string( $detail ) ? $detail : wp_json_encode( $detail ) ) );
	}
};
$err = fn( $r, $code ) => is_wp_error( $r ) && $r->get_error_code() === $code;
$ok  = fn( $r ) => ! is_wp_error( $r );
$why = fn( $r ) => is_wp_error( $r ) ? $r->get_error_code() . ': ' . $r->get_error_message() : $r;

$classes_before = get_field( 'classes', 'option', false );

// Year and class.
$r = $call( 'create-year', array( 'year' => 2099, 'induction_class' => true ) );
$check( 'create-year 2099 as a class', $ok( $r ), $why( $r ) );
$year = $ok( $r ) ? $r['id'] : 0;
$made[] = $year;
$check( 'class list includes 2099', in_array( $year, array_map( 'intval', (array) get_field( 'classes', 'option', false ) ), true ) );
$check( 'create-year refuses a repeat', $err( $call( 'create-year', array( 'year' => 2099, 'induction_class' => true ) ), 'uh_exists' ) );

// Teams and tournaments.
$div  = $call( 'list-divisions' )['divisions'][0]['id'];
$r    = $call( 'create-team', array( 'name' => 'Zz Test Team', 'division_id' => $div, 'city' => 'Testville' ) );
$check( 'create-team', $ok( $r ), $why( $r ) );
$team_a = $r['id'] ?? 0;
$made[] = $team_a;
$check( 'create-team refuses a near-duplicate', $err( $call( 'create-team', array( 'name' => 'Zz Test Team', 'division_id' => $div ) ), 'uh_similar_exists' ) );
$r      = $call( 'create-team', array( 'name' => 'Zz Test Team', 'division_id' => $div, 'confirm_new' => true ) );
$team_b = $r['id'] ?? 0;
$made[] = $team_b;
$r      = $call( 'create-tournament', array( 'name' => '2099 Zz Test Championships', 'year' => 2099, 'series' => 'us' ) );
$check( 'create-tournament', $ok( $r ), $why( $r ) );
$tour   = $r['id'] ?? 0;
$made[] = $tour;
$check( 'create-tournament refuses a missing year', $err( $call( 'create-tournament', array( 'name' => '2098 Zz Nothing', 'year' => 2098 ) ), 'uh_no_year' ) );

// Member.
$r = $call( 'create-member', array( 'first_name' => 'Zz', 'last_name' => 'Testperson', 'hometown' => 'Testville', 'class_year' => 2099, 'induction_type' => 'Player', 'induction_division' => "Women's Division" ) );
$check( 'create-member as draft', $ok( $r ) && 'draft' === $r['member']['status'], $why( $r ) );
$m      = $r['member']['id'] ?? 0;
$made[] = $m;
$check( 'create-member sets class and induction fields', $ok( $r ) && '2099' === $r['member']['class'] && "Women's Division" === $r['member']['induction_division'], $r['member'] ?? '' );
$check( 'create-member refuses a near-duplicate', $err( $call( 'create-member', array( 'first_name' => 'Zz', 'last_name' => 'Testperson' ) ), 'uh_similar_exists' ) );
$check( 'create-member refuses a bad division', $err( $call( 'create-member', array( 'first_name' => 'Qq', 'last_name' => 'Other', 'induction_division' => 'Nope', 'confirm_new' => true ) ), 'uh_bad_choice' ) );
$found = $call( 'find-member', array( 'query' => 'Zz Testperson' ) );
$check( 'find-member finds drafts', in_array( $m, array_column( $found['members'] ?? array(), 'id' ), true ), $found );

// Rows, warnings and the stale-version guard.
$r = $call( 'add-career-stint', array( 'member_id' => $m, 'team_id' => $team_a, 'start_year' => 2090, 'end_year' => 2095 ) );
$check( 'add-career-stint', $ok( $r ) && ! $r['warnings'], $why( $r ) );
$r = $call( 'add-career-stint', array( 'member_id' => $m, 'team_id' => $team_a, 'start_year' => 2094, 'end_year' => 2096 ) );
$check( 'add-career-stint warns on overlap', $ok( $r ) && $r['warnings'], $why( $r ) );
$r = $call( 'add-result', array( 'member_id' => $m, 'tournament_id' => $tour, 'team_id' => $team_b, 'placement' => '1st' ) );
$check( 'add-result warns when no stint covers the team', $ok( $r ) && $r['warnings'], $why( $r ) );
$check( 'add-result refuses an exact duplicate', $err( $call( 'add-result', array( 'member_id' => $m, 'tournament_id' => $tour, 'team_id' => $team_b, 'placement' => '2nd' ) ), 'uh_duplicate' ) );
$r = $call( 'add-service', array( 'member_id' => $m, 'description' => 'Test committee', 'start_year' => 2091 ) );
$check( 'add-service', $ok( $r ), $why( $r ) );
$r = $call( 'add-award', array( 'member_id' => $m, 'year' => 2099, 'award' => 'Test Award' ) );
$check( 'add-award', $ok( $r ), $why( $r ) );
$g = $call( 'get-member', array( 'member_id' => $m ) );
$check( 'get-member returns lists, versions and awards', $ok( $g ) && 2 === count( $g['career'] ) && 1 === count( $g['awards'] ) && 2099 === $g['awards'][0]['year'] && ! empty( $g['list_versions']['results'] ), $g );
$r = $call( 'update-row', array( 'member_id' => $m, 'list' => 'results', 'row' => 0, 'list_version' => 'stale00000', 'fields' => array( 'placement' => '2nd' ) ) );
$check( 'update-row refuses a stale list_version', $err( $r, 'uh_stale' ), $why( $r ) );
$r = $call( 'update-row', array( 'member_id' => $m, 'list' => 'results', 'row' => 0, 'list_version' => $g['list_versions']['results'], 'fields' => array( 'placement' => '2nd' ) ) );
$check( 'update-row with a fresh list_version', $ok( $r ) && '2nd' === $r['results']['placement'], $why( $r ) );
$r = $call( 'remove-row', array( 'member_id' => $m, 'list' => 'career', 'row' => 1, 'list_version' => $g['list_versions']['career'] ) );
$check( 'remove-row', $ok( $r ) && 1 === $r['remaining'], $why( $r ) );
$undo_rm = $ok( $r ) ? $r['change_id'] : '';

// Bio, vitals, title and status.
$r = $call( 'set-bio', array( 'member_id' => $m, 'biography' => "First paragraph.\n\nSecond <b>para</b>." ) );
$check( 'set-bio turns plain text into paragraphs', $ok( $r ) && 2 === substr_count( $r['biography'], '<p>' ), $why( $r ) );
$r = $call( 'set-bio', array( 'member_id' => $m, 'biography' => '<p>Safe <a href="https://example.com" onclick="x()">link</a></p><script>alert(1)</script>' ) );
$check( 'set-bio keeps links, drops scripts and handlers', $ok( $r ) && false === strpos( $r['biography'], 'script' ) && false === strpos( $r['biography'], 'onclick' ) && false !== strpos( $r['biography'], 'href' ), $why( $r ) );
$r = $call( 'update-member-vitals', array( 'member_id' => $m, 'first_name' => 'Zed' ) );
$check( 'update-member-vitals warns the title is stale', $ok( $r ) && ! empty( $r['warnings'] ), $why( $r ) );
$check( 'undo vitals', $ok( $call( 'undo-change', array( 'post_id' => $m, 'change_id' => $r['change_id'] ?? '' ) ) ) && 'Zz' === $call( 'get-member', array( 'member_id' => $m ) )['vitals']['first_name'] );
$r = $call( 'update-member', array( 'member_id' => $m, 'title' => 'Zz Testperson Renamed', 'status' => 'publish' ) );
$check( 'update-member renames and publishes', $ok( $r ) && 'publish' === $r['status'] && 'zz-testperson-renamed' === get_post_field( 'post_name', $m ), $why( $r ) );
$check( 'undo update-member', $ok( $call( 'undo-change', array( 'post_id' => $m, 'change_id' => $r['change_id'] ?? '' ) ) ) && 'draft' === get_post_status( $m ) && 'Zz Testperson' === get_the_title( $m ) );
$r = $call( 'list-class', array( 'class_year' => 2099 ) );
$line = $ok( $r ) ? current( array_filter( $r['members'], fn( $l ) => $l['id'] === $m ) ) : null;
$check( 'list-class shows the draft and what it lacks', $line && in_array( 'headshot', $line['missing'], true ) && ! in_array( 'bio', $line['missing'], true ), $line );
$check( 'find-media runs', $ok( $call( 'find-media', array( 'query' => 'jpg' ) ) ) );
$check( 'set-photo refuses a non-image url', $err( $call( 'set-photo', array( 'member_id' => $m, 'slot' => 'headshot', 'url' => 'https://example.com/page' ) ), 'uh_bad_url' ) );

// Rosters, team and tournament edits.
$r = $call( 'get-team', array( 'team_id' => $team_a ) );
$check( 'get-team roster includes drafts', $ok( $r ) && in_array( $m, array_column( $r['roster'], 'id' ), true ), $why( $r ) );
$r = $call( 'get-tournament', array( 'tournament_id' => $tour ) );
$check( 'get-tournament lists results', $ok( $r ) && 1 === count( $r['results'] ), $why( $r ) );
$r = $call( 'update-team', array( 'team_id' => $team_a, 'city' => 'Newtown', 'add_aliases' => array( 'ZTT' ) ) );
$check( 'update-team city and alias', $ok( $r ) && 'Newtown' === $r['city'] && in_array( 'ZTT', $r['aliases'], true ), $why( $r ) );
$f = $call( 'find-team', array( 'query' => 'ZTT' ) );
$check( 'find-team finds the new alias', $ok( $f ) && $team_a === ( $f['teams'][0]['id'] ?? 0 ), $f );
$check( 'undo update-team', $ok( $call( 'undo-change', array( 'post_id' => $team_a, 'change_id' => $r['change_id'] ?? '' ) ) ) && 'Testville' === get_field( 'city', $team_a ) );
$r = $call( 'update-tournament', array( 'tournament_id' => $tour, 'series' => 'world' ) );
$check( 'update-tournament series', $ok( $r ) && 'world' === $r['series'], $why( $r ) );

// Trash, merge and undo.
$check( 'trash refuses a team members point at', $err( $call( 'trash', array( 'post_id' => $team_b, 'reason' => 'test' ) ), 'uh_in_use' ) );
$p = $call( 'merge', array( 'keep_id' => $team_a, 'merge_ids' => array( $team_b ) ) );
$check( 'merge preview changes nothing', $ok( $p ) && empty( $p['merged'] ) && 'publish' === get_post_status( $team_b ), $why( $p ) );
$r = $call( 'merge', array( 'keep_id' => $team_a, 'merge_ids' => array( $team_b ), 'confirm' => true ) );
$res = $call( 'get-member', array( 'member_id' => $m ) )['results'][0] ?? array();
$check( 'merge moves rows, trashes the duplicate', $ok( $r ) && 'trash' === get_post_status( $team_b ) && $team_a === $res['team_id'], $why( $r ) );
$u = $call( 'undo-change', array( 'post_id' => $team_a, 'change_id' => $r['change_id'] ?? '' ) );
$res = $call( 'get-member', array( 'member_id' => $m ) )['results'][0] ?? array();
$check( 'undo merge restores rows and the duplicate', $ok( $u ) && 'publish' === get_post_status( $team_b ) && $team_b === $res['team_id'], $why( $u ) );
$r = $call( 'trash', array( 'post_id' => $m, 'reason' => 'test' ) );
$check( 'trash a member', $ok( $r ) && 'trash' === get_post_status( $m ), $why( $r ) );
$check( 'undo trash restores its status', $ok( $call( 'undo-change', array( 'post_id' => $m, 'change_id' => $r['change_id'] ?? '' ) ) ) && 'draft' === get_post_status( $m ) );
$check( 'undo remove-row', $ok( $call( 'undo-change', array( 'member_id' => $m, 'change_id' => $undo_rm ) ) ) );

// History.
$h = $call( 'recent-changes', array( 'user' => $login, 'since' => gmdate( 'Y-m-d' ), 'limit' => 200 ) );
$check( 'recent-changes lists this run', $ok( $h ) && count( $h['changes'] ) >= 20, count( $h['changes'] ?? array() ) );
$check( 'member-history works for a team', $ok( $call( 'member-history', array( 'post_id' => $team_a ) ) ) );

// Permissions: a logged-out caller gets nothing.
wp_set_current_user( 0 );
$check( 'logged out: reads refused', is_wp_error( $call( 'find-member', array( 'query' => 'Zz' ) ) ) );
$check( 'logged out: writes refused', is_wp_error( $call( 'create-member', array( 'first_name' => 'No', 'last_name' => 'Body' ) ) ) );

// Clean up as an admin.
$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0];
wp_set_current_user( $admin->ID );
foreach ( array_filter( $made ) as $id ) {
	wp_delete_post( $id, true );
}
update_field( 'classes', $classes_before, 'option' );
update_option( UH_CHANGE_FEED, array_values( array_filter( get_option( UH_CHANGE_FEED, array() ), fn( $c ) => ! in_array( $c['post_id'], $made, true ) ) ), false );

$fails ? WP_CLI::error( "{$fails} checks failed." ) : WP_CLI::success( 'All checks passed; test posts removed.' );
