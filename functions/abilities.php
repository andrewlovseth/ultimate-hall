<?php
/**
 * WordPress Abilities for Hall of Fame content, exposed to AI agents over MCP.
 *
 * Every write goes through these task-shaped abilities instead of raw REST,
 * so an agent can add one row without rewriting a repeater, lookups stop
 * creating duplicates, and every change is logged per member with an undo.
 *
 * Exposure: abilities are `meta.public`, and functions/mcp.php serves them as
 * individual MCP tools at /wp-json/uhof/mcp. Callers authenticate as a
 * WordPress user (Application Password); permission callbacks check that
 * user's capabilities, so an Editor can do everything here and nothing else.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const UH_CHANGELOG_META = '_uh_changes';
const UH_CHANGELOG_KEEP = 100;

/** Ability names served over MCP, in the order tools are listed. */
function uh_ability_names() {
	return array(
		// Read.
		'uhof/find-member', 'uhof/get-member', 'uhof/list-class', 'uhof/find-team', 'uhof/get-team',
		'uhof/find-tournament', 'uhof/get-tournament', 'uhof/list-divisions', 'uhof/find-media',
		// Members.
		'uhof/create-member', 'uhof/update-member', 'uhof/update-member-vitals', 'uhof/set-bio', 'uhof/set-photo',
		'uhof/add-career-stint', 'uhof/add-result', 'uhof/add-service', 'uhof/add-award', 'uhof/update-row', 'uhof/remove-row',
		// Teams, tournaments, classes.
		'uhof/create-team', 'uhof/update-team', 'uhof/create-tournament', 'uhof/update-tournament', 'uhof/create-year',
		'uhof/merge', 'uhof/trash',
		// History.
		'uhof/recent-changes', 'uhof/member-history', 'uhof/undo-change',
	);
}

/** Post statuses an editor works with: everything but trash and auto-drafts. */
function uh_editable_statuses() {
	return array( 'publish', 'draft', 'pending', 'private', 'future' );
}

/* ---------- shared helpers ---------- */

function uh_normalize_name( $s ) {
	$s = html_entity_decode( (string) $s, ENT_QUOTES, 'UTF-8' );
	$s = strtolower( remove_accents( $s ) );
	return preg_replace( '/[^a-z0-9]+/', '', $s );
}

/** Score how well $query matches a post title or any alias (0–100). */
function uh_match_score( $query, $title, $aliases = '' ) {
	$q     = uh_normalize_name( $query );
	$best  = 0;
	$names = array_merge( array( $title ), preg_split( '/\r\n|\n/', (string) $aliases ) );
	// "Death or Glory (DoG)" also answers to "Death or Glory" and "DoG".
	if ( preg_match( '/^(.*?)\s*\(([^)]+)\)\s*$/', html_entity_decode( (string) $title, ENT_QUOTES, 'UTF-8' ), $m ) ) {
		$names[] = $m[1];
		$names[] = $m[2];
	}
	foreach ( $names as $name ) {
		$n = uh_normalize_name( $name );
		if ( '' === $n || '' === $q ) {
			continue;
		}
		if ( $n === $q ) {
			return 100;
		}
		if ( false !== strpos( $n, $q ) || false !== strpos( $q, $n ) ) {
			// Contained match: 70–95, closer lengths rank higher.
			$best = max( $best, 70 + (int) round( 25 * min( strlen( $q ), strlen( $n ) ) / max( strlen( $q ), strlen( $n ) ) ) );
			continue;
		}
		similar_text( $q, $n, $pct );
		$best = max( $best, (int) $pct );
	}
	return $best;
}

function uh_team_summary( $team_id ) {
	$division = uh_team_division( $team_id );
	return array(
		'id'            => (int) $team_id,
		'name'          => html_entity_decode( get_the_title( $team_id ), ENT_QUOTES, 'UTF-8' ),
		'city'          => (string) get_field( 'city', $team_id ),
		'division'      => $division ? $division->post_title : null,
		'national_team' => (bool) get_field( 'national_team', $team_id ),
		'aliases'       => array_values( array_filter( preg_split( '/\r\n|\n/', (string) get_field( 'aliases', $team_id ) ) ) ),
		'url'           => get_permalink( $team_id ),
	);
}

function uh_tournament_summary( $tid ) {
	return array(
		'id'       => (int) $tid,
		'name'     => html_entity_decode( get_the_title( $tid ), ENT_QUOTES, 'UTF-8' ),
		'year'     => uh_tournament_year( $tid ) ? (int) uh_tournament_year( $tid ) : null,
		'series'   => get_field( 'details_series', $tid ) ?: uh_guess_series( get_the_title( $tid ) ),
		'location' => (string) get_field( 'details_location', $tid ),
		'beach'    => (bool) get_field( 'details_beach', $tid ),
		'url'      => get_permalink( $tid ),
	);
}

/** Fuzzy search a post type by title and aliases. */
function uh_fuzzy_find( $post_type, $query, $limit = 10, $filter = null, $statuses = 'publish' ) {
	$hits = array();
	foreach ( get_posts( array( 'post_type' => $post_type, 'post_status' => $statuses, 'numberposts' => -1 ) ) as $p ) {
		if ( $filter && ! $filter( $p ) ) {
			continue;
		}
		$score = uh_match_score( $query, $p->post_title, get_field( 'aliases', $p->ID ) );
		if ( $score >= 60 ) {
			$hits[] = array( $score, $p->ID );
		}
	}
	usort( $hits, fn( $a, $b ) => $b[0] <=> $a[0] );
	return array_slice( $hits, 0, $limit );
}

function uh_require_post( $id, $type ) {
	if ( ! $id || get_post_type( (int) $id ) !== $type || 'trash' === get_post_status( (int) $id ) ) {
		return new WP_Error( 'uh_not_found', sprintf( 'No %s with id %d.', $type, (int) $id ), array( 'status' => 404 ) );
	}
	return (int) $id;
}

function uh_year_post( $year ) {
	$found = get_posts( array( 'post_type' => 'year', 'title' => (string) (int) $year, 'numberposts' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ids' ) );
	return $found ? (int) $found[0] : 0;
}

/** Raw rows of a member list (career, results, service) as plain arrays of sub-field values. */
function uh_raw_rows( $member_id, $list ) {
	$rows = get_field( $list, $member_id, false ) ?: array();
	$out  = array();
	$keys = array();
	$field = acf_get_field( $list );
	if ( ! $field || empty( $field['sub_fields'] ) ) {
		return array(); // uh_write_rows() refuses to write in this case.
	}
	foreach ( $field['sub_fields'] as $sf ) {
		$keys[ $sf['key'] ] = $sf['name'];
	}
	foreach ( $rows as $row ) {
		$clean = array();
		foreach ( $row as $k => $v ) {
			$clean[ $keys[ $k ] ?? $k ] = $v;
		}
		$out[] = $clean;
	}
	return $out;
}

/** After a member edit: rebuild derived data and purge the page cache so visitors see it. */
function uh_after_member_edit( $member_id ) {
	clean_post_cache( $member_id );
	uh_sync_member( $member_id );
	// Meta-only writes don't trigger SpinupWP's purge-on-update; team and tournament pages change too.
	do_action( 'spinupwp_purge_page_cache' );
}

/**
 * Coerce one row to its stored types: ids and years to integers, flags to booleans, text to plain text.
 * Every ability write goes through this, so agent-supplied markup never reaches the templates.
 */
function uh_clean_row( array $row ) {
	foreach ( $row as $k => $v ) {
		if ( in_array( $k, array( 'team', 'tournament', 'year', 'start_year', 'end_year' ), true ) ) {
			$row[ $k ] = ( '' === $v || null === $v ) ? '' : (int) $v;
		} elseif ( in_array( $k, array( 'to_present', 'approximate' ), true ) ) {
			$row[ $k ] = (bool) $v;
		} else {
			$row[ $k ] = sanitize_text_field( (string) $v );
		}
	}
	return $row;
}

/** Write a member list, logging the previous rows so the change can be undone. */
function uh_write_rows( $member_id, $list, array $rows, $ability, $summary ) {
	if ( ! acf_get_field( $list ) ) {
		return new WP_Error( 'uh_missing_field', "The {$list} field group is missing; nothing was written." );
	}
	$before = uh_raw_rows( $member_id, $list );
	$id     = uh_log_change( $member_id, $ability, $list, $summary, $before );
	update_field( $list, array_values( array_map( 'uh_clean_row', $rows ) ), $member_id );
	uh_after_member_edit( $member_id );
	return $id;
}

/** Short fingerprint of a member list. Row edits must quote it, so stale row numbers are refused. */
function uh_list_version( $member_id, $list ) {
	return substr( md5( wp_json_encode( uh_raw_rows( $member_id, $list ) ) ), 0, 10 );
}

function uh_check_version( $member_id, $list, $version ) {
	if ( (string) $version !== uh_list_version( $member_id, $list ) ) {
		return new WP_Error( 'uh_stale', "The {$list} list changed since you read it, so its row numbers may have moved. Call get-member again and use the new row numbers and list_version." );
	}
	return true;
}

/** A calendar year as its year post id, or an error naming the fix. */
function uh_require_year( $year ) {
	$post = uh_year_post( $year );
	return $post ? $post : new WP_Error( 'uh_no_year', 'There is no year page for ' . (int) $year . '. Create it with create-year first.' );
}

/** Year spans of a member's stints on one team, as [start, end] pairs; approximate stints widen by two years. */
function uh_team_spans( $member_id, $team_id ) {
	$out = array();
	foreach ( uh_raw_rows( $member_id, 'career' ) as $r ) {
		if ( (int) $r['team'] !== (int) $team_id || ! $r['start_year'] ) {
			continue;
		}
		$start = (int) $r['start_year'];
		$end   = ! empty( $r['to_present'] ) ? (int) gmdate( 'Y' ) : ( $r['end_year'] ? (int) $r['end_year'] : $start );
		$pad   = ! empty( $r['approximate'] ) ? 2 : 0;
		$out[] = array( $start - $pad, $end + $pad );
	}
	return $out;
}

function uh_member_detail( $member_id ) {
	$vitals = get_field( 'vitals', $member_id ) ?: array();
	$meta   = get_field( 'meta', $member_id ) ?: array();
	$career = array();
	foreach ( uh_raw_rows( $member_id, 'career' ) as $i => $r ) {
		$career[] = array(
			'row'         => $i,
			'team'        => $r['team'] ? uh_team_summary( $r['team'] )['name'] : null,
			'team_id'     => $r['team'] ? (int) $r['team'] : null,
			'start_year'  => $r['start_year'] ? (int) $r['start_year'] : null,
			'end_year'    => $r['end_year'] ? (int) $r['end_year'] : null,
			'to_present'  => (bool) $r['to_present'],
			'approximate' => (bool) $r['approximate'],
			'note'        => (string) $r['note'],
		);
	}
	$results = array();
	foreach ( uh_raw_rows( $member_id, 'results' ) as $i => $r ) {
		$t         = $r['tournament'] && get_post( $r['tournament'] ) ? uh_tournament_summary( $r['tournament'] ) : null;
		$results[] = array(
			'row'           => $i,
			'tournament'    => $t ? $t['name'] : null,
			'tournament_id' => $t ? $t['id'] : null,
			'year'          => $t ? $t['year'] : null,
			'series'        => $t ? $t['series'] : null,
			'team'          => $r['team'] && get_post( $r['team'] ) ? uh_team_summary( $r['team'] )['name'] : null,
			'team_id'       => $r['team'] ? (int) $r['team'] : null,
			'placement'     => (string) $r['placement'],
		);
	}
	$service = array();
	foreach ( uh_raw_rows( $member_id, 'service' ) as $i => $r ) {
		$service[] = array(
			'row'         => $i,
			'start_year'  => $r['start_year'] ? (int) $r['start_year'] : null,
			'end_year'    => $r['end_year'] ? (int) $r['end_year'] : null,
			'to_present'  => (bool) $r['to_present'],
			'approximate' => (bool) $r['approximate'],
			'description' => (string) $r['description'],
		);
	}
	$awards = array();
	foreach ( uh_raw_rows( $member_id, 'awards' ) as $i => $r ) {
		$awards[] = array(
			'row'   => $i,
			'year'  => ! empty( $r['year'] ) ? (int) get_the_title( $r['year'] ) : null,
			'award' => (string) ( $r['award'] ?? '' ),
		);
	}
	$photo  = function ( $name ) use ( $member_id ) {
		$id = (int) uh_get_raw( $member_id, 'photos.' . $name );
		return $id ? array( 'attachment_id' => $id, 'url' => wp_get_attachment_url( $id ) ) : null;
	};
	return array(
		'id'                 => (int) $member_id,
		'name'               => html_entity_decode( get_the_title( $member_id ), ENT_QUOTES, 'UTF-8' ),
		'status'             => get_post_status( $member_id ),
		'url'                => get_permalink( $member_id ),
		'class'              => ! empty( $meta['class'] ) ? get_the_title( $meta['class'] ) : null,
		'induction_type'     => $meta['induction_type']['label'] ?? null,
		'induction_division' => $meta['induction_division']['label'] ?? null,
		'biography'          => (string) uh_get_raw( $member_id, 'biography' ),
		'headshot'           => $photo( 'headshot' ),
		'player_card'        => $photo( 'player_card' ),
		'list_versions'      => array(
			'career'  => uh_list_version( $member_id, 'career' ),
			'results' => uh_list_version( $member_id, 'results' ),
			'service' => uh_list_version( $member_id, 'service' ),
			'awards'  => uh_list_version( $member_id, 'awards' ),
		),
		'awards'             => $awards,
		'vitals'             => array(
			'first_name'    => $vitals['first_name'] ?? '',
			'last_name'     => $vitals['last_name'] ?? '',
			'hometown'      => $vitals['hometown'] ?? '',
			'birthdate'     => $vitals['birthdate'] ?? '',
			'date_of_death' => $vitals['date_of_death'] ?? '',
		),
		'career'         => $career,
		'results'        => $results,
		'service'        => $service,
	);
}

/* ---------- schemas ---------- */

function uh_year_schema( $desc ) {
	return array( 'type' => 'integer', 'minimum' => 1950, 'maximum' => 2100, 'description' => $desc );
}

function uh_lists_schema() {
	return array( 'type' => 'string', 'enum' => array( 'career', 'results', 'service', 'awards' ), 'description' => 'Which list on the member: career (team stints), results (tournament appearances), service (contributions) or awards.' );
}

function uh_version_schema() {
	return array( 'type' => 'string', 'description' => 'The list_versions value for this list from your latest get-member (or the list_version a previous write returned). Refused if the list changed since.' );
}

/* ---------- permissions ---------- */

/** Reads include drafts, private vitals and editors' logins in history, so they need Editor-level access. */
function uh_can_read() {
	return current_user_can( 'edit_others_posts' );
}

function uh_can_edit_member( $input ) {
	$id = (int) ( $input['member_id'] ?? 0 );
	return $id && current_user_can( 'edit_post', $id );
}

/* ---------- registration ---------- */

add_action(
	'wp_abilities_api_categories_init',
	function () {
		wp_register_ability_category(
			'uhof',
			array(
				'label'       => 'Ultimate Hall of Fame',
				'description' => 'Read and edit Hall of Fame members, teams and tournaments.',
			)
		);
	}
);

add_action( 'wp_abilities_api_init', 'uh_register_abilities' );

function uh_register_abilities() {
	$ro   = array( 'mcp' => array( 'public' => false ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) );
	$add  = array( 'mcp' => array( 'public' => false ), 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ) );
	$edit = array( 'mcp' => array( 'public' => false ), 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => true ) );

	wp_register_ability(
		'uhof/find-member',
		array(
			'label'               => 'Find member',
			'description'         => 'Find Hall of Fame members by name (nicknames and partial names work), drafts included. Returns ids and status to use with get-member and the add/update abilities.',
			'category'            => 'uhof',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array( 'query' => array( 'type' => 'string', 'minLength' => 2, 'description' => 'Name or part of a name.' ) ),
				'required'   => array( 'query' ),
			),
			'execute_callback'    => function ( $input ) {
				$out = array();
				foreach ( uh_fuzzy_find( 'member', $input['query'], 10, null, uh_editable_statuses() ) as $hit ) {
					$meta  = get_field( 'meta', $hit[1] ) ?: array();
					$out[] = array(
						'id'     => $hit[1],
						'name'   => html_entity_decode( get_the_title( $hit[1] ), ENT_QUOTES, 'UTF-8' ),
						'status' => get_post_status( $hit[1] ),
						'class'  => ! empty( $meta['class'] ) ? get_the_title( $meta['class'] ) : null,
						'score'  => $hit[0],
					);
				}
				return array( 'members' => $out );
			},
			'permission_callback' => 'uh_can_read',
			'meta'                => $ro,
		)
	);

	wp_register_ability(
		'uhof/get-member',
		array(
			'label'               => 'Get member',
			'description'         => 'Everything editable on one member: status, class, vitals, biography, photos, career stints, tournament results, service and awards, each row numbered, plus list_versions to pass to update-row and remove-row. Read this before adding or changing anything, to avoid duplicates.',
			'category'            => 'uhof',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array( 'member_id' => array( 'type' => 'integer' ) ),
				'required'   => array( 'member_id' ),
			),
			'execute_callback'    => function ( $input ) {
				$id = uh_require_post( $input['member_id'], 'member' );
				return is_wp_error( $id ) ? $id : uh_member_detail( $id );
			},
			'permission_callback' => 'uh_can_read',
			'meta'                => $ro,
		)
	);

	wp_register_ability(
		'uhof/find-team',
		array(
			'label'               => 'Find team',
			'description'         => 'Find teams by name or alias, best match first. Always look a team up before creating one; many teams share names across divisions and cities, so check city and division.',
			'category'            => 'uhof',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'query'    => array( 'type' => 'string', 'minLength' => 1 ),
					'division' => array( 'type' => 'string', 'description' => 'Optional division name to narrow, e.g. "Club Women".' ),
				),
				'required'   => array( 'query' ),
			),
			'execute_callback'    => function ( $input ) {
				$division = $input['division'] ?? '';
				$filter   = $division ? function ( $p ) use ( $division ) {
					$d = uh_team_division( $p->ID );
					return $d && false !== stripos( $d->post_title, $division );
				} : null;
				$out = array();
				foreach ( uh_fuzzy_find( 'team', $input['query'], 10, $filter ) as $hit ) {
					$out[] = uh_team_summary( $hit[1] ) + array( 'score' => $hit[0], 'members' => count( uh_members_for_team( $hit[1] ) ) );
				}
				return array( 'teams' => $out );
			},
			'permission_callback' => 'uh_can_read',
			'meta'                => $ro,
		)
	);

	wp_register_ability(
		'uhof/find-tournament',
		array(
			'label'               => 'Find tournament',
			'description'         => 'Find tournaments by name, optionally for one year or series. Tournament titles start with the year, e.g. "1995 UPA Club Championships".',
			'category'            => 'uhof',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'query'  => array( 'type' => 'string', 'minLength' => 1 ),
					'year'   => uh_year_schema( 'Optional year.' ),
					'series' => array( 'type' => 'string', 'enum' => array_keys( uh_series() ) ),
				),
				'required'   => array( 'query' ),
			),
			'execute_callback'    => function ( $input ) {
				$year   = (int) ( $input['year'] ?? 0 );
				$series = $input['series'] ?? '';
				$filter = function ( $p ) use ( $year, $series ) {
					if ( $year && (int) uh_tournament_year( $p->ID ) !== $year ) {
						return false;
					}
					return ! $series || ( get_field( 'details_series', $p->ID ) ?: uh_guess_series( $p->post_title ) ) === $series;
				};
				$out = array();
				foreach ( uh_fuzzy_find( 'tournaments', $input['query'], 15, $filter ) as $hit ) {
					$out[] = uh_tournament_summary( $hit[1] ) + array( 'score' => $hit[0] );
				}
				return array( 'tournaments' => $out );
			},
			'permission_callback' => 'uh_can_read',
			'meta'                => $ro,
		)
	);

	wp_register_ability(
		'uhof/list-divisions',
		array(
			'label'               => 'List divisions',
			'description'         => 'All divisions (Club Women, Masters Men, Beach Mixed…) with ids, for creating teams.',
			'category'            => 'uhof',
			'input_schema'        => array( 'type' => 'object', 'properties' => new stdClass() ),
			'execute_callback'    => function () {
				return array( 'divisions' => array_map( fn( $p ) => array( 'id' => $p->ID, 'name' => $p->post_title ), get_posts( array( 'post_type' => 'division', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) ) );
			},
			'permission_callback' => 'uh_can_read',
			'meta'                => $ro,
		)
	);

	wp_register_ability(
		'uhof/add-career-stint',
		array(
			'label'               => 'Add career stint',
			'description'         => 'Add one stint on a team to a member\'s career. Use one row per continuous run of seasons: a 1992–1994 and 1997 career on one team is two calls. Leave end_year out for a single season.',
			'category'            => 'uhof',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'member_id'   => array( 'type' => 'integer' ),
					'team_id'     => array( 'type' => 'integer' ),
					'start_year'  => uh_year_schema( 'First season.' ),
					'end_year'    => uh_year_schema( 'Last season, if more than one.' ),
					'to_present'  => array( 'type' => 'boolean', 'description' => 'Still on the team.' ),
					'approximate' => array( 'type' => 'boolean', 'description' => 'Years are approximate (shown as "late 1970s" or "c. 1980–1983").' ),
					'note'        => array( 'type' => 'string' ),
				),
				'required'   => array( 'member_id', 'team_id', 'start_year' ),
			),
			'execute_callback'    => function ( $input ) {
				$id   = uh_require_post( $input['member_id'], 'member' );
				$team = uh_require_post( $input['team_id'], 'team' );
				if ( is_wp_error( $id ) || is_wp_error( $team ) ) {
					return is_wp_error( $id ) ? $id : $team;
				}
				if ( ! empty( $input['end_year'] ) && $input['end_year'] < $input['start_year'] ) {
					return new WP_Error( 'uh_bad_years', 'end_year is before start_year.' );
				}
				$rows     = uh_raw_rows( $id, 'career' );
				$warnings = array();
				$start    = (int) $input['start_year'];
				$end      = ! empty( $input['to_present'] ) ? (int) gmdate( 'Y' ) : (int) ( $input['end_year'] ?? $start );
				foreach ( $rows as $r ) {
					if ( (int) $r['team'] === $team && (int) $r['start_year'] === $start ) {
						return new WP_Error( 'uh_duplicate', 'This member already has a stint on that team starting that year. Use update-row to change it.' );
					}
				}
				foreach ( uh_team_spans( $id, $team ) as $span ) {
					if ( $start <= $span[1] && $end >= $span[0] ) {
						$warnings[] = sprintf( 'Overlaps an existing stint on this team (%d to %d). If they are one continuous run, combine them with update-row and remove-row.', $span[0], $span[1] );
					}
				}
				$rows[] = array(
					'team'        => $team,
					'start_year'  => (int) $input['start_year'],
					'end_year'    => isset( $input['end_year'] ) ? (int) $input['end_year'] : '',
					'to_present'  => ! empty( $input['to_present'] ),
					'approximate' => ! empty( $input['approximate'] ),
					'note'        => (string) ( $input['note'] ?? '' ),
				);
				$label  = get_the_title( $team ) . ' ' . uh_format_span( $input['start_year'], $input['end_year'] ?? null, ! empty( $input['to_present'] ), ! empty( $input['approximate'] ) );
				$change = uh_write_rows( $id, 'career', $rows, 'add-career-stint', 'Added career stint: ' . $label );
				if ( is_wp_error( $change ) ) {
					return $change;
				}
				return array( 'change_id' => $change, 'row' => count( $rows ) - 1, 'list_version' => uh_list_version( $id, 'career' ), 'warnings' => $warnings, 'career' => uh_member_detail( $id )['career'] );
			},
			'permission_callback' => 'uh_can_edit_member',
			'meta'                => $add,
		)
	);

	wp_register_ability(
		'uhof/add-result',
		array(
			'label'               => 'Add tournament result',
			'description'         => 'Add one tournament appearance to a member: which tournament, which team, and the placement. The tournament\'s series decides whether it shows under U.S., World or Pro on the profile. Refuses an exact duplicate.',
			'category'            => 'uhof',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'member_id'     => array( 'type' => 'integer' ),
					'tournament_id' => array( 'type' => 'integer' ),
					'team_id'       => array( 'type' => 'integer' ),
					'placement'     => array( 'type' => 'string', 'enum' => uh_placements() ),
				),
				'required'   => array( 'member_id', 'tournament_id', 'team_id', 'placement' ),
			),
			'execute_callback'    => function ( $input ) {
				$id   = uh_require_post( $input['member_id'], 'member' );
				$tid  = uh_require_post( $input['tournament_id'], 'tournaments' );
				$team = uh_require_post( $input['team_id'], 'team' );
				foreach ( array( $id, $tid, $team ) as $check ) {
					if ( is_wp_error( $check ) ) {
						return $check;
					}
				}
				$rows = uh_raw_rows( $id, 'results' );
				foreach ( $rows as $r ) {
					if ( (int) $r['tournament'] === $tid && (int) $r['team'] === $team ) {
						return new WP_Error( 'uh_duplicate', 'This member already has a result at that tournament with that team. Use update-row to change the placement.' );
					}
				}
				$warnings = array();
				$year     = (int) uh_tournament_year( $tid );
				$spans    = uh_team_spans( $id, $team );
				if ( ! $spans ) {
					$warnings[] = 'This member has no career stint on ' . get_the_title( $team ) . '. Add one with add-career-stint if they played there.';
				} elseif ( $year && ! array_filter( $spans, fn( $s ) => $year >= $s[0] && $year <= $s[1] ) ) {
					$warnings[] = "None of this member's stints on " . get_the_title( $team ) . " covers {$year}. Check the tournament year or the career years.";
				}
				$rows[] = array( 'tournament' => $tid, 'team' => $team, 'placement' => $input['placement'] );
				$change = uh_write_rows( $id, 'results', $rows, 'add-result', sprintf( 'Added result: %s, %s, %s', get_the_title( $tid ), get_the_title( $team ), $input['placement'] ) );
				if ( is_wp_error( $change ) ) {
					return $change;
				}
				return array( 'change_id' => $change, 'row' => count( $rows ) - 1, 'list_version' => uh_list_version( $id, 'results' ), 'warnings' => $warnings );
			},
			'permission_callback' => 'uh_can_edit_member',
			'meta'                => $add,
		)
	);

	wp_register_ability(
		'uhof/add-service',
		array(
			'label'               => 'Add contribution or service',
			'description'         => 'Add one contributions-and-service entry (a role, committee, coaching job…) with its years.',
			'category'            => 'uhof',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'member_id'   => array( 'type' => 'integer' ),
					'description' => array( 'type' => 'string', 'minLength' => 2 ),
					'start_year'  => uh_year_schema( 'First year.' ),
					'end_year'    => uh_year_schema( 'Last year, if more than one.' ),
					'to_present'  => array( 'type' => 'boolean' ),
					'approximate' => array( 'type' => 'boolean' ),
				),
				'required'   => array( 'member_id', 'description' ),
			),
			'execute_callback'    => function ( $input ) {
				$id = uh_require_post( $input['member_id'], 'member' );
				if ( is_wp_error( $id ) ) {
					return $id;
				}
				$rows   = uh_raw_rows( $id, 'service' );
				$rows[] = array(
					'start_year'  => isset( $input['start_year'] ) ? (int) $input['start_year'] : '',
					'end_year'    => isset( $input['end_year'] ) ? (int) $input['end_year'] : '',
					'to_present'  => ! empty( $input['to_present'] ),
					'approximate' => ! empty( $input['approximate'] ),
					'description' => $input['description'],
				);
				$change = uh_write_rows( $id, 'service', $rows, 'add-service', 'Added service: ' . $input['description'] );
				if ( is_wp_error( $change ) ) {
					return $change;
				}
				return array( 'change_id' => $change, 'row' => count( $rows ) - 1, 'list_version' => uh_list_version( $id, 'service' ) );
			},
			'permission_callback' => 'uh_can_edit_member',
			'meta'                => $add,
		)
	);

	wp_register_ability(
		'uhof/update-row',
		array(
			'label'               => 'Update a row',
			'description'         => 'Change fields on one numbered row of a member\'s career, results, service or awards (row numbers and list_version come from get-member). Only the fields you pass change. Career: team_id, start_year, end_year, to_present, approximate, note. Results: tournament_id, team_id, placement. Service: start_year, end_year, to_present, approximate, description. Awards: year, award.',
			'category'            => 'uhof',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'member_id'    => array( 'type' => 'integer' ),
					'list'         => uh_lists_schema(),
					'row'          => array( 'type' => 'integer', 'minimum' => 0 ),
					'list_version' => uh_version_schema(),
					'fields'       => array( 'type' => 'object', 'description' => 'Fields to change on that row.' ),
				),
				'required'   => array( 'member_id', 'list', 'row', 'list_version', 'fields' ),
			),
			'execute_callback'    => function ( $input ) {
				$id = uh_require_post( $input['member_id'], 'member' );
				if ( is_wp_error( $id ) ) {
					return $id;
				}
				$list  = $input['list'];
				$fresh = uh_check_version( $id, $list, $input['list_version'] );
				if ( is_wp_error( $fresh ) ) {
					return $fresh;
				}
				$rows    = uh_raw_rows( $id, $list );
				$i       = (int) $input['row'];
				$allowed = array(
					'career'  => array( 'team_id' => 'team', 'start_year' => 'start_year', 'end_year' => 'end_year', 'to_present' => 'to_present', 'approximate' => 'approximate', 'note' => 'note' ),
					'results' => array( 'tournament_id' => 'tournament', 'team_id' => 'team', 'placement' => 'placement' ),
					'service' => array( 'start_year' => 'start_year', 'end_year' => 'end_year', 'to_present' => 'to_present', 'approximate' => 'approximate', 'description' => 'description' ),
					'awards'  => array( 'year' => 'year', 'award' => 'award' ),
				)[ $list ];
				if ( ! isset( $rows[ $i ] ) ) {
					return new WP_Error( 'uh_no_row', "No row {$i} in {$list}." );
				}
				$changed = array();
				foreach ( (array) $input['fields'] as $k => $v ) {
					if ( ! isset( $allowed[ $k ] ) ) {
						return new WP_Error( 'uh_bad_field', "Unknown field {$k} for {$list}. Allowed: " . implode( ', ', array_keys( $allowed ) ) );
					}
					if ( 'team_id' === $k && null !== $v && is_wp_error( uh_require_post( $v, 'team' ) ) ) {
						return uh_require_post( $v, 'team' );
					}
					if ( 'tournament_id' === $k && is_wp_error( uh_require_post( $v, 'tournaments' ) ) ) {
						return uh_require_post( $v, 'tournaments' );
					}
					if ( 'placement' === $k && ! in_array( $v, uh_placements(), true ) ) {
						return new WP_Error( 'uh_bad_placement', 'Placement must be one of: ' . implode( ', ', uh_placements() ) );
					}
					if ( 'year' === $k && 'awards' === $list ) {
						$v = uh_require_year( $v );
						if ( is_wp_error( $v ) ) {
							return $v;
						}
					}
					$rows[ $i ][ $allowed[ $k ] ] = null === $v ? '' : $v;
					$changed[]                    = $k;
				}
				$start = (int) ( $rows[ $i ]['start_year'] ?? 0 );
				$end   = (int) ( $rows[ $i ]['end_year'] ?? 0 );
				if ( $start && $end && $end < $start ) {
					return new WP_Error( 'uh_bad_years', 'end_year is before start_year.' );
				}
				$change = uh_write_rows( $id, $list, $rows, 'update-row', "Updated {$list} row {$i}: " . implode( ', ', $changed ) );
				if ( is_wp_error( $change ) ) {
					return $change;
				}
				return array( 'change_id' => $change, 'list_version' => uh_list_version( $id, $list ), $list => uh_member_detail( $id )[ $list ][ $i ] );
			},
			'permission_callback' => 'uh_can_edit_member',
			'meta'                => $edit,
		)
	);

	wp_register_ability(
		'uhof/remove-row',
		array(
			'label'               => 'Remove a row',
			'description'         => 'Delete one numbered row from a member\'s career, results, service or awards. Rows after it move up one, so use the list_version this returns for the next edit. Logged, and reversible with undo-change.',
			'category'            => 'uhof',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'member_id'    => array( 'type' => 'integer' ),
					'list'         => uh_lists_schema(),
					'row'          => array( 'type' => 'integer', 'minimum' => 0 ),
					'list_version' => uh_version_schema(),
				),
				'required'   => array( 'member_id', 'list', 'row', 'list_version' ),
			),
			'execute_callback'    => function ( $input ) {
				$id = uh_require_post( $input['member_id'], 'member' );
				if ( is_wp_error( $id ) ) {
					return $id;
				}
				$fresh = uh_check_version( $id, $input['list'], $input['list_version'] );
				if ( is_wp_error( $fresh ) ) {
					return $fresh;
				}
				$rows = uh_raw_rows( $id, $input['list'] );
				$i    = (int) $input['row'];
				if ( ! isset( $rows[ $i ] ) ) {
					return new WP_Error( 'uh_no_row', "No row {$i} in {$input['list']}." );
				}
				array_splice( $rows, $i, 1 );
				$change = uh_write_rows( $id, $input['list'], $rows, 'remove-row', "Removed {$input['list']} row {$i}" );
				if ( is_wp_error( $change ) ) {
					return $change;
				}
				return array( 'change_id' => $change, 'remaining' => count( $rows ), 'list_version' => uh_list_version( $id, $input['list'] ) );
			},
			'permission_callback' => 'uh_can_edit_member',
			'meta'                => $edit,
		)
	);

	wp_register_ability(
		'uhof/update-member-vitals',
		array(
			'label'               => 'Update member vitals',
			'description'         => 'Change a member\'s first name, last name, hometown, birthdate or date of death. Dates are YYYY-MM-DD. Only the fields you pass change. A name change does not rename the member\'s page; use update-member with title for that.',
			'category'            => 'uhof',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'member_id'     => array( 'type' => 'integer' ),
					'first_name'    => array( 'type' => 'string' ),
					'last_name'     => array( 'type' => 'string' ),
					'hometown'      => array( 'type' => 'string' ),
					'birthdate'     => array( 'type' => 'string', 'pattern' => '^(\\d{4}-\\d{2}-\\d{2})?$' ),
					'date_of_death' => array( 'type' => 'string', 'pattern' => '^(\\d{4}-\\d{2}-\\d{2})?$' ),
				),
				'required'   => array( 'member_id' ),
			),
			'execute_callback'    => function ( $input ) {
				$id = uh_require_post( $input['member_id'], 'member' );
				if ( is_wp_error( $id ) ) {
					return $id;
				}
				$before = uh_vitals_raw( $id );
				$after  = $before;
				foreach ( array_keys( $before ) as $k ) {
					if ( array_key_exists( $k, $input ) ) {
						$after[ $k ] = in_array( $k, array( 'birthdate', 'date_of_death' ), true ) ? str_replace( '-', '', $input[ $k ] ) : sanitize_text_field( $input[ $k ] );
					}
				}
				$change = uh_log_change( $id, 'update-member-vitals', 'vitals', 'Updated vitals: ' . implode( ', ', array_keys( array_diff_assoc( $after, $before ) ) ), $before );
				update_field( 'vitals', $after, $id );
				uh_after_member_edit( $id );
				$vitals = uh_member_detail( $id )['vitals'];
				$out    = array( 'change_id' => $change, 'vitals' => $vitals );
				$name   = trim( $vitals['first_name'] . ' ' . $vitals['last_name'] );
				if ( $name && uh_normalize_name( $name ) !== uh_normalize_name( get_the_title( $id ) ) ) {
					$out['warnings'] = array( 'The page title is still "' . html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ) . '". If the name changed, call update-member with the new title.' );
				}
				return $out;
			},
			'permission_callback' => 'uh_can_edit_member',
			'meta'                => $edit,
		)
	);

	wp_register_ability(
		'uhof/create-team',
		array(
			'label'               => 'Create team',
			'description'         => 'Create a new team. Refuses when a team with a similar name already exists in the same division unless confirm_new is true; run find-team first and only confirm after checking the matches really are different teams.',
			'category'            => 'uhof',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'name'          => array( 'type' => 'string', 'minLength' => 1 ),
					'division_id'   => array( 'type' => 'integer' ),
					'city'          => array( 'type' => 'string' ),
					'national_team' => array( 'type' => 'boolean' ),
					'aliases'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'confirm_new'   => array( 'type' => 'boolean' ),
				),
				'required'   => array( 'name', 'division_id' ),
			),
			'execute_callback'    => function ( $input ) {
				$division = uh_require_post( $input['division_id'], 'division' );
				if ( is_wp_error( $division ) ) {
					return $division;
				}
				if ( empty( $input['confirm_new'] ) ) {
					$similar = array();
					foreach ( uh_fuzzy_find( 'team', $input['name'], 5, fn( $p ) => ( uh_team_division( $p->ID )->ID ?? 0 ) === $division ) as $hit ) {
						if ( $hit[0] >= 80 ) {
							$similar[] = uh_team_summary( $hit[1] );
						}
					}
					if ( $similar ) {
						return new WP_Error( 'uh_similar_exists', 'Similar teams already exist in this division. Use one of these, or call again with confirm_new: true.', array( 'similar' => $similar ) );
					}
				}
				$team = wp_insert_post( array( 'post_type' => 'team', 'post_status' => 'publish', 'post_title' => sanitize_text_field( $input['name'] ) ), true );
				if ( is_wp_error( $team ) ) {
					return $team;
				}
				update_field( 'division', array( $division ), $team );
				update_field( 'city', sanitize_text_field( (string) ( $input['city'] ?? '' ) ), $team );
				update_field( 'national_team', ! empty( $input['national_team'] ), $team );
				update_field( 'aliases', implode( "\n", array_map( 'sanitize_text_field', (array) ( $input['aliases'] ?? array() ) ) ), $team );
				$change = uh_log_change( $team, 'create-team', 'create', 'Created team ' . get_the_title( $team ), null );
				return uh_team_summary( $team ) + array( 'change_id' => $change );
			},
			'permission_callback' => fn() => current_user_can( 'publish_posts' ),
			'meta'                => $add,
		)
	);

	wp_register_ability(
		'uhof/create-tournament',
		array(
			'label'               => 'Create tournament',
			'description'         => 'Create a tournament for a year. Name it like the existing ones: "<year> <event>", e.g. "2024 USA Ultimate Club Championships". Series: us (U.S. nationals), world (WFDF and other international events), pro (AUDL, UFA, PUL, WUL, MLU). Refuses near-duplicates for the same year unless confirm_new is true. The year page must exist (create-year).',
			'category'            => 'uhof',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'name'        => array( 'type' => 'string', 'minLength' => 5 ),
					'year'        => uh_year_schema( 'Year the tournament was held.' ),
					'series'      => array( 'type' => 'string', 'enum' => array_keys( uh_series() ) ),
					'location'    => array( 'type' => 'string' ),
					'beach'       => array( 'type' => 'boolean' ),
					'aliases'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'confirm_new' => array( 'type' => 'boolean' ),
				),
				'required'   => array( 'name', 'year' ),
			),
			'execute_callback'    => function ( $input ) {
				$year_post = uh_require_year( $input['year'] );
				if ( is_wp_error( $year_post ) ) {
					return $year_post;
				}
				if ( empty( $input['confirm_new'] ) ) {
					$similar = array();
					foreach ( uh_fuzzy_find( 'tournaments', $input['name'], 5, fn( $p ) => (int) uh_tournament_year( $p->ID ) === (int) $input['year'] ) as $hit ) {
						if ( $hit[0] >= 80 ) {
							$similar[] = uh_tournament_summary( $hit[1] );
						}
					}
					if ( $similar ) {
						return new WP_Error( 'uh_similar_exists', 'Similar tournaments already exist for that year. Use one of these, or call again with confirm_new: true.', array( 'similar' => $similar ) );
					}
				}
				$tid = wp_insert_post( array( 'post_type' => 'tournaments', 'post_status' => 'publish', 'post_title' => sanitize_text_field( $input['name'] ) ), true );
				if ( is_wp_error( $tid ) ) {
					return $tid;
				}
				update_field( 'details', array(
					'location' => sanitize_text_field( (string) ( $input['location'] ?? '' ) ),
					'year'     => $year_post,
					'series'   => $input['series'] ?? uh_guess_series( $input['name'] ),
					'beach'    => ! empty( $input['beach'] ),
				), $tid );
				update_field( 'aliases', implode( "\n", array_map( 'sanitize_text_field', (array) ( $input['aliases'] ?? array() ) ) ), $tid );
				$change = uh_log_change( $tid, 'create-tournament', 'create', 'Created tournament ' . get_the_title( $tid ), null );
				return uh_tournament_summary( $tid ) + array( 'change_id' => $change );
			},
			'permission_callback' => fn() => current_user_can( 'publish_posts' ),
			'meta'                => $add,
		)
	);
}
