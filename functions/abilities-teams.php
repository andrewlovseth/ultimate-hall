<?php
/**
 * Team and tournament abilities beyond create: rosters, edits, aliases,
 * merging duplicates, and trashing. Also the one trash tool for members.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Aliases as a clean list, one per line in storage. */
function uh_aliases( $post_id ) {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\n/', (string) get_field( 'aliases', $post_id ) ) ) ) );
}

/** Apply add/remove alias lists; returns the new newline-joined value. */
function uh_edit_aliases( $post_id, $add, $remove ) {
	$list = uh_aliases( $post_id );
	foreach ( (array) $add as $a ) {
		$a = sanitize_text_field( $a );
		if ( '' !== $a && ! in_array( uh_normalize_name( $a ), array_map( 'uh_normalize_name', $list ), true ) ) {
			$list[] = $a;
		}
	}
	$drop = array_map( 'uh_normalize_name', (array) $remove );
	$list = array_values( array_filter( $list, fn( $a ) => ! in_array( uh_normalize_name( $a ), $drop, true ) ) );
	return implode( "\n", $list );
}

/** Roster of a team: each member with their stints and results on it. */
function uh_team_roster( $team_id ) {
	$out = array();
	foreach ( uh_members_referencing( $team_id ) as $mid ) {
		$spans   = array();
		$results = 0;
		foreach ( uh_raw_rows( $mid, 'career' ) as $r ) {
			if ( (int) $r['team'] === (int) $team_id ) {
				$spans[] = array( 'start' => $r['start_year'], 'end' => $r['end_year'], 'to_present' => ! empty( $r['to_present'] ), 'approximate' => ! empty( $r['approximate'] ) );
			}
		}
		foreach ( uh_raw_rows( $mid, 'results' ) as $r ) {
			$results += (int) ( (int) $r['team'] === (int) $team_id );
		}
		$out[] = array(
			'id'      => $mid,
			'name'    => html_entity_decode( get_the_title( $mid ), ENT_QUOTES, 'UTF-8' ),
			'status'  => get_post_status( $mid ),
			'years'   => uh_format_spans( $spans ),
			'results' => $results,
		);
	}
	return $out;
}

/** Entries at a tournament: member, team, placement. */
function uh_tournament_entries( $tid ) {
	$out   = array();
	$order = array_flip( uh_placements() );
	foreach ( uh_members_referencing( $tid ) as $mid ) {
		foreach ( uh_raw_rows( $mid, 'results' ) as $i => $r ) {
			if ( (int) $r['tournament'] !== (int) $tid ) {
				continue;
			}
			$out[] = array(
				'member_id' => $mid,
				'member'    => html_entity_decode( get_the_title( $mid ), ENT_QUOTES, 'UTF-8' ),
				'status'    => get_post_status( $mid ),
				'row'       => $i,
				'team_id'   => (int) $r['team'] ?: null,
				'team'      => $r['team'] ? html_entity_decode( get_the_title( $r['team'] ), ENT_QUOTES, 'UTF-8' ) : null,
				'placement' => (string) $r['placement'],
			);
		}
	}
	usort( $out, fn( $a, $b ) => array( $order[ $a['placement'] ] ?? 99, $a['team'], $a['member'] ) <=> array( $order[ $b['placement'] ] ?? 99, $b['team'], $b['member'] ) );
	return $out;
}

add_action( 'wp_abilities_api_init', 'uh_register_team_abilities' );

function uh_register_team_abilities() {
	$ro   = array( 'mcp' => array( 'public' => false ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) );
	$edit = array( 'mcp' => array( 'public' => false ), 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => true ) );
	$big  = array( 'mcp' => array( 'public' => false ), 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ) );

	$aliases = array( 'type' => 'array', 'items' => array( 'type' => 'string' ) );

	wp_register_ability(
		'uhof/get-team',
		array(
			'label'               => 'Get team',
			'description'         => 'One team: name, city, division, aliases, and its roster of members with their years on the team and how many results they have with it. Drafts included, marked by status.',
			'category'            => 'uhof',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array( 'team_id' => array( 'type' => 'integer' ) ),
				'required'   => array( 'team_id' ),
			),
			'execute_callback'    => function ( $input ) {
				$id = uh_require_post( $input['team_id'], 'team' );
				return is_wp_error( $id ) ? $id : uh_team_summary( $id ) + array( 'roster' => uh_team_roster( $id ) );
			},
			'permission_callback' => 'uh_can_read',
			'meta'                => $ro,
		)
	);

	wp_register_ability(
		'uhof/get-tournament',
		array(
			'label'               => 'Get tournament',
			'description'         => 'One tournament: name, year, series, location, aliases, and every member result at it (member, team, placement, and the row number on that member).',
			'category'            => 'uhof',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array( 'tournament_id' => array( 'type' => 'integer' ) ),
				'required'   => array( 'tournament_id' ),
			),
			'execute_callback'    => function ( $input ) {
				$id = uh_require_post( $input['tournament_id'], 'tournaments' );
				return is_wp_error( $id ) ? $id : uh_tournament_summary( $id ) + array( 'aliases' => uh_aliases( $id ), 'results' => uh_tournament_entries( $id ) );
			},
			'permission_callback' => 'uh_can_read',
			'meta'                => $ro,
		)
	);

	wp_register_ability(
		'uhof/update-team',
		array(
			'label'               => 'Update team',
			'description'         => 'Change a team\'s name, city, division or national-team flag, and add or remove aliases (the other names people use for it, so lookups find it). Only the fields you pass change. Every member page that shows the team updates.',
			'category'            => 'uhof',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'team_id'        => array( 'type' => 'integer' ),
					'name'           => array( 'type' => 'string', 'minLength' => 1 ),
					'city'           => array( 'type' => 'string' ),
					'division_id'    => array( 'type' => 'integer' ),
					'national_team'  => array( 'type' => 'boolean' ),
					'add_aliases'    => $aliases,
					'remove_aliases' => $aliases,
				),
				'required'   => array( 'team_id' ),
			),
			'execute_callback'    => function ( $input ) {
				$id = uh_require_post( $input['team_id'], 'team' );
				if ( is_wp_error( $id ) ) {
					return $id;
				}
				if ( isset( $input['division_id'] ) && is_wp_error( uh_require_post( $input['division_id'], 'division' ) ) ) {
					return uh_require_post( $input['division_id'], 'division' );
				}
				$before  = uh_snapshot( $id, array( 'post_title', 'post_name' ), array( 'city', 'division', 'national_team', 'aliases' ) );
				$changed = array();
				if ( isset( $input['name'] ) ) {
					wp_update_post( array( 'ID' => $id, 'post_title' => sanitize_text_field( $input['name'] ), 'post_name' => sanitize_title( $input['name'] ) ) );
					$changed[] = 'name';
				}
				if ( isset( $input['city'] ) ) {
					uh_set_raw( $id, 'city', sanitize_text_field( $input['city'] ) );
					$changed[] = 'city';
				}
				if ( isset( $input['division_id'] ) ) {
					uh_set_raw( $id, 'division', array( (int) $input['division_id'] ) );
					$changed[] = 'division';
				}
				if ( isset( $input['national_team'] ) ) {
					uh_set_raw( $id, 'national_team', (bool) $input['national_team'] );
					$changed[] = 'national team';
				}
				if ( ! empty( $input['add_aliases'] ) || ! empty( $input['remove_aliases'] ) ) {
					uh_set_raw( $id, 'aliases', uh_edit_aliases( $id, $input['add_aliases'] ?? array(), $input['remove_aliases'] ?? array() ) );
					$changed[] = 'aliases';
				}
				if ( ! $changed ) {
					return new WP_Error( 'uh_nothing', 'Nothing to change; pass at least one field.' );
				}
				$change = uh_log_change( $id, 'update-team', 'fields', 'Updated ' . implode( ', ', $changed ), $before );
				uh_after_edit( $id );
				return array( 'change_id' => $change ) + uh_team_summary( $id );
			},
			'permission_callback' => fn( $input ) => current_user_can( 'edit_post', (int) ( $input['team_id'] ?? 0 ) ),
			'meta'                => $edit,
		)
	);

	wp_register_ability(
		'uhof/update-tournament',
		array(
			'label'               => 'Update tournament',
			'description'         => 'Change a tournament\'s name, year, series (us, world or pro; decides which table its results show in on member pages), location or beach flag, and add or remove aliases. Only the fields you pass change.',
			'category'            => 'uhof',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'tournament_id'  => array( 'type' => 'integer' ),
					'name'           => array( 'type' => 'string', 'minLength' => 5 ),
					'year'           => uh_year_schema( 'Year the tournament was held.' ),
					'series'         => array( 'type' => 'string', 'enum' => array_keys( uh_series() ) ),
					'location'       => array( 'type' => 'string' ),
					'beach'          => array( 'type' => 'boolean' ),
					'add_aliases'    => $aliases,
					'remove_aliases' => $aliases,
				),
				'required'   => array( 'tournament_id' ),
			),
			'execute_callback'    => function ( $input ) {
				$id = uh_require_post( $input['tournament_id'], 'tournaments' );
				if ( is_wp_error( $id ) ) {
					return $id;
				}
				$year = isset( $input['year'] ) ? uh_require_year( $input['year'] ) : 0;
				if ( is_wp_error( $year ) ) {
					return $year;
				}
				$before  = uh_snapshot( $id, array( 'post_title', 'post_name' ), array( 'details.year', 'details.series', 'details.location', 'details.beach', 'aliases' ) );
				$changed = array();
				if ( isset( $input['name'] ) ) {
					wp_update_post( array( 'ID' => $id, 'post_title' => sanitize_text_field( $input['name'] ), 'post_name' => sanitize_title( $input['name'] ) ) );
					$changed[] = 'name';
				}
				if ( $year ) {
					uh_set_raw( $id, 'details.year', $year );
					$changed[] = 'year';
				}
				foreach ( array( 'series', 'location', 'beach' ) as $k ) {
					if ( isset( $input[ $k ] ) ) {
						uh_set_raw( $id, 'details.' . $k, 'beach' === $k ? (bool) $input[ $k ] : sanitize_text_field( $input[ $k ] ) );
						$changed[] = $k;
					}
				}
				if ( ! empty( $input['add_aliases'] ) || ! empty( $input['remove_aliases'] ) ) {
					uh_set_raw( $id, 'aliases', uh_edit_aliases( $id, $input['add_aliases'] ?? array(), $input['remove_aliases'] ?? array() ) );
					$changed[] = 'aliases';
				}
				if ( ! $changed ) {
					return new WP_Error( 'uh_nothing', 'Nothing to change; pass at least one field.' );
				}
				$change = uh_log_change( $id, 'update-tournament', 'fields', 'Updated ' . implode( ', ', $changed ), $before );
				uh_after_edit( $id );
				$warn = ( $year && ! preg_match( '/^' . (int) $input['year'] . '\b/', get_the_title( $id ) ) ) ? array( 'warnings' => array( 'The name doesn\'t start with the new year. Tournament names start with their year; rename it with name.' ) ) : array();
				return array( 'change_id' => $change ) + uh_tournament_summary( $id ) + array( 'aliases' => uh_aliases( $id ) ) + $warn;
			},
			'permission_callback' => fn( $input ) => current_user_can( 'edit_post', (int) ( $input['tournament_id'] ?? 0 ) ),
			'meta'                => $edit,
		)
	);

	wp_register_ability(
		'uhof/merge',
		array(
			'label'               => 'Merge duplicates',
			'description'         => 'Merge duplicate teams (or duplicate tournaments) into the one to keep: every member row pointing at a duplicate is moved to the keeper, the duplicates\' names become aliases of the keeper, and the duplicates are trashed. Call first without confirm to preview who is affected, show the person, and call again with confirm: true only after they agree. Undo with undo-change on the keeper.',
			'category'            => 'uhof',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'keep_id'   => array( 'type' => 'integer', 'description' => 'The team or tournament to keep.' ),
					'merge_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'minItems' => 1, 'description' => 'Duplicates to fold into it.' ),
					'confirm'   => array( 'type' => 'boolean' ),
				),
				'required'   => array( 'keep_id', 'merge_ids' ),
			),
			'execute_callback'    => function ( $input ) {
				$keep = (int) $input['keep_id'];
				$type = get_post_type( $keep );
				if ( ! in_array( $type, array( 'team', 'tournaments' ), true ) || 'trash' === get_post_status( $keep ) ) {
					return new WP_Error( 'uh_bad_merge', 'keep_id must be a team or tournament.' );
				}
				$drop = array_values( array_diff( array_unique( array_map( 'intval', $input['merge_ids'] ) ), array( $keep ) ) );
				foreach ( $drop as $d ) {
					if ( is_wp_error( uh_require_post( $d, $type ) ) ) {
						return new WP_Error( 'uh_bad_merge', "Post {$d} isn't a {$type}, or is already trashed. Teams merge only with teams, tournaments only with tournaments." );
					}
				}
				if ( ! $drop ) {
					return new WP_Error( 'uh_bad_merge', 'Nothing to merge.' );
				}
				$cols    = 'team' === $type ? array( 'career' => 'team', 'results' => 'team' ) : array( 'results' => 'tournament' );
				$members = array();
				foreach ( $drop as $d ) {
					foreach ( uh_members_referencing( $d ) as $mid ) {
						$members[ $mid ] = true;
					}
				}
				$preview = array();
				foreach ( array_keys( $members ) as $mid ) {
					$moved = 0;
					$dups  = 0;
					foreach ( $cols as $list => $col ) {
						$seen = array();
						foreach ( uh_raw_rows( $mid, $list ) as $r ) {
							$hit    = in_array( (int) $r[ $col ], $drop, true );
							$moved += (int) $hit;
							$r[ $col ] = $hit ? $keep : (int) $r[ $col ];
							$sig      = 'career' === $list ? $r['team'] . '|' . $r['start_year'] : $r['tournament'] . '|' . $r['team'];
							$dups    += (int) isset( $seen[ $sig ] );
							$seen[ $sig ] = true;
						}
					}
					$preview[] = array( 'member_id' => $mid, 'member' => html_entity_decode( get_the_title( $mid ), ENT_QUOTES, 'UTF-8' ), 'rows_moved' => $moved, 'duplicate_rows_after' => $dups );
				}
				$summary = array(
					'keep'     => array( 'id' => $keep, 'name' => get_the_title( $keep ) ),
					'merge'    => array_map( fn( $d ) => array( 'id' => $d, 'name' => get_the_title( $d ) ), $drop ),
					'members'  => $preview,
				);
				if ( empty( $input['confirm'] ) ) {
					return $summary + array( 'note' => 'Preview only; nothing changed. Members with duplicate_rows_after above 0 will have two identical rows to tidy with remove-row.' );
				}

				$pairs = array();
				foreach ( array_keys( $members ) as $mid ) {
					foreach ( $cols as $list => $col ) {
						$rows = uh_raw_rows( $mid, $list );
						$hit  = false;
						foreach ( $rows as &$r ) {
							if ( in_array( (int) $r[ $col ], $drop, true ) ) {
								$r[ $col ] = $keep;
								$hit       = true;
							}
						}
						unset( $r );
						if ( $hit ) {
							$cid = uh_write_rows( $mid, $list, $rows, 'merge', 'Merge: moved ' . $list . ' rows to ' . get_the_title( $keep ) );
							if ( is_wp_error( $cid ) ) {
								return $cid;
							}
							$pairs[] = array( $mid, $cid );
						}
					}
				}
				$aliases_before = (string) get_field( 'aliases', $keep );
				$names          = array();
				foreach ( $drop as $d ) {
					$names[] = html_entity_decode( get_the_title( $d ), ENT_QUOTES, 'UTF-8' );
					$names   = array_merge( $names, uh_aliases( $d ) );
				}
				$names = array_filter( $names, fn( $n ) => uh_normalize_name( $n ) !== uh_normalize_name( get_the_title( $keep ) ) );
				uh_set_raw( $keep, 'aliases', uh_edit_aliases( $keep, $names, array() ) );
				$dropped = array();
				foreach ( $drop as $d ) {
					$dropped[] = array( $d, get_post_status( $d ) );
					uh_log_change( $d, 'merge', 'restore', 'Merged into ' . get_the_title( $keep ) . " ({$keep})", null );
					wp_trash_post( $d );
				}
				$change = uh_log_change(
					$keep,
					'merge',
					'merge',
					'Merged ' . implode( ', ', array_map( fn( $p ) => get_the_title( $p[0] ) . " ({$p[0]})", $dropped ) ) . ' into this; ' . count( $pairs ) . ' member lists updated',
					array( 'members' => $pairs, 'dropped' => $dropped, 'aliases' => $aliases_before )
				);
				uh_after_edit( $keep );
				return array( 'change_id' => $change, 'merged' => true ) + $summary;
			},
			'permission_callback' => fn() => current_user_can( 'edit_others_posts' ) && current_user_can( 'delete_others_posts' ),
			'meta'                => $big,
		)
	);

	wp_register_ability(
		'uhof/trash',
		array(
			'label'               => 'Trash',
			'description'         => 'Move a member, team or tournament to the trash (an admin can still restore it, and undo-change puts it back). A team or tournament that members still point at is refused; merge it into the right one instead.',
			'category'            => 'uhof',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array( 'type' => 'integer' ),
					'reason'  => array( 'type' => 'string', 'description' => 'Short note for the change log.' ),
				),
				'required'   => array( 'post_id', 'reason' ),
			),
			'execute_callback'    => function ( $input ) {
				$id   = (int) $input['post_id'];
				$type = get_post_type( $id );
				if ( ! in_array( $type, array( 'member', 'team', 'tournaments' ), true ) || 'trash' === get_post_status( $id ) ) {
					return new WP_Error( 'uh_bad_trash', 'Only members, teams and tournaments that aren\'t already trashed can be trashed here.' );
				}
				if ( 'member' !== $type ) {
					$refs = uh_members_referencing( $id );
					if ( $refs ) {
						return new WP_Error( 'uh_in_use', count( $refs ) . ' members point at this ' . $type . '. Merge it into the right one with merge, or move their rows first.', array( 'members' => array_map( fn( $m ) => array( 'id' => $m, 'name' => get_the_title( $m ) ), array_slice( $refs, 0, 20 ) ) ) );
					}
				}
				$change = uh_log_change( $id, 'trash', 'trash', 'Trashed: ' . sanitize_text_field( $input['reason'] ), array( 'status' => get_post_status( $id ) ) );
				wp_trash_post( $id );
				uh_after_edit( $id );
				return array( 'change_id' => $change, 'trashed' => $id, 'name' => get_the_title( $id ) );
			},
			'permission_callback' => fn( $input ) => current_user_can( 'delete_post', (int) ( $input['post_id'] ?? 0 ) ),
			'meta'                => $big,
		)
	);
}
