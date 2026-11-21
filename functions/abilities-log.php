<?php
/**
 * Change log and undo for every uhof/* write.
 *
 * Each change is stored twice:
 *   - on the post it touched (`_uh_changes`, newest first, last 100), with a
 *     `before` snapshot that undo-change restores;
 *   - in one site-wide feed option (`uh_change_feed`, last 1,000), summary
 *     only, so recent-changes can answer "what changed this week" across
 *     members, teams, tournaments and classes.
 *
 * Kinds of change, by the entry's `list`:
 *   career | results | service | awards   before = the list's raw rows
 *   vitals                                 before = vitals by sub-field name
 *   fields                                 before = { post: {post_title, …}, acf: {path: raw value} }
 *   create                                 before = { options_classes?: […] }; undo trashes the post
 *   trash                                  before = { status }; undo restores it
 *   merge                                  before = { members: [[member_id, change_id]…], dropped: [[id, status]…], aliases }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const UH_CHANGE_FEED      = 'uh_change_feed';
const UH_CHANGE_FEED_KEEP = 1000;

/** Record a change on a post and in the site-wide feed. Returns the change id. */
function uh_log_change( $post_id, $ability, $list, $summary, $before ) {
	$entry = array(
		'id'      => wp_generate_uuid4(),
		'time'    => gmdate( 'c' ),
		'user'    => wp_get_current_user()->user_login,
		'ability' => $ability,
		'list'    => $list,
		'summary' => $summary,
		'before'  => $before,
	);
	$log = get_post_meta( $post_id, UH_CHANGELOG_META, true ) ?: array();
	array_unshift( $log, $entry );
	update_post_meta( $post_id, UH_CHANGELOG_META, array_slice( $log, 0, UH_CHANGELOG_KEEP ) );

	$feed = get_option( UH_CHANGE_FEED, array() );
	array_unshift(
		$feed,
		array(
			'id'        => $entry['id'],
			'time'      => $entry['time'],
			'user'      => $entry['user'],
			'post_id'   => (int) $post_id,
			'post_type' => get_post_type( $post_id ),
			'title'     => html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' ),
			'ability'   => $ability,
			'summary'   => $summary,
		)
	);
	update_option( UH_CHANGE_FEED, array_slice( $feed, 0, UH_CHANGE_FEED_KEEP ), false );
	return $entry['id'];
}

/* ---------- field paths: "biography", "meta.class", "details.series" ---------- */

/** The ACF field object for a path on a post, sub-fields included. */
function uh_field_for_path( $post_id, $path ) {
	$parts = explode( '.', $path );
	$field = acf_maybe_get_field( $parts[0], $post_id, false );
	if ( ! $field ) {
		return null;
	}
	if ( isset( $parts[1] ) ) {
		foreach ( (array) ( $field['sub_fields'] ?? array() ) as $sf ) {
			if ( $sf['name'] === $parts[1] ) {
				return $sf;
			}
		}
		return null;
	}
	return $field;
}

/** Unformatted stored value at a path: ids for posts and images, plain strings otherwise. */
function uh_get_raw( $post_id, $path ) {
	$parts = explode( '.', $path );
	if ( isset( $parts[1] ) ) {
		$group = get_field( $parts[0], $post_id, false );
		$sub   = uh_field_for_path( $post_id, $path );
		return ( $sub && is_array( $group ) ) ? ( $group[ $sub['key'] ] ?? '' ) : '';
	}
	$value = get_field( $path, $post_id, false );
	return null === $value ? '' : $value;
}

function uh_set_raw( $post_id, $path, $value ) {
	$parts = explode( '.', $path );
	$field = uh_field_for_path( $post_id, $path );
	if ( ! $field ) {
		return new WP_Error( 'uh_missing_field', "The {$path} field is missing; nothing was written." );
	}
	$value = null === $value ? '' : $value;
	if ( isset( $parts[1] ) ) {
		// A partial group write: ACF updates only the sub-fields present, and stores them as group_sub.
		update_field( acf_maybe_get_field( $parts[0], $post_id, false )['key'], array( $parts[1] => $value ), $post_id );
	} else {
		update_field( $field['key'], $value, $post_id );
	}
	return true;
}

/** Snapshot post fields and ACF paths, for a `fields` change. */
function uh_snapshot( $post_id, array $post_keys, array $paths ) {
	$post = get_post( $post_id );
	$snap = array( 'post' => array(), 'acf' => array() );
	foreach ( $post_keys as $k ) {
		$snap['post'][ $k ] = $post->$k;
	}
	foreach ( $paths as $path ) {
		$snap['acf'][ $path ] = uh_get_raw( $post_id, $path );
	}
	return $snap;
}

function uh_restore_snapshot( $post_id, array $snap ) {
	if ( ! empty( $snap['post'] ) ) {
		$update = wp_update_post( array( 'ID' => $post_id ) + $snap['post'], true );
		if ( is_wp_error( $update ) ) {
			return $update;
		}
	}
	foreach ( (array) ( $snap['acf'] ?? array() ) as $path => $value ) {
		uh_set_raw( $post_id, $path, $value );
	}
	return true;
}

/** Vitals by sub-field name, unformatted (dates as Ymd). */
function uh_vitals_raw( $member_id ) {
	$vitals = get_field( 'vitals', $member_id, false ) ?: array();
	$field  = acf_get_field( 'vitals' );
	$out    = array();
	foreach ( $field['sub_fields'] as $sf ) {
		if ( in_array( $sf['name'], array( 'first_name', 'last_name', 'hometown', 'birthdate', 'date_of_death' ), true ) ) {
			$out[ $sf['name'] ] = $vitals[ $sf['key'] ] ?? '';
		}
	}
	return $out;
}

/** After an edit to anything but a member: purge the page cache, since member pages show team and tournament names. */
function uh_after_edit( $post_id ) {
	clean_post_cache( $post_id );
	uh_member_results_cache( 'flush' );
	do_action( 'spinupwp_purge_page_cache' );
}

/** Members (any status but trash) that point at a team or tournament. */
function uh_members_referencing( $post_id ) {
	$key = 'team' === get_post_type( $post_id ) ? UH_TEAM_INDEX : UH_TOURNAMENT_INDEX;
	return get_posts(
		array(
			'post_type'      => 'member',
			'post_status'    => uh_editable_statuses(),
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
			'meta_query'     => array( array( 'key' => $key, 'value' => (int) $post_id ) ),
		)
	);
}

/* ---------- undo ---------- */

function uh_undo_change( $post_id, $change_id ) {
	$post_id = (int) $post_id;
	$log     = get_post_meta( $post_id, UH_CHANGELOG_META, true ) ?: array();
	foreach ( $log as $c ) {
		if ( $c['id'] !== $change_id ) {
			continue;
		}
		$summary = 'Undid: ' . $c['summary'];
		switch ( $c['list'] ) {
			case 'career':
			case 'results':
			case 'service':
			case 'awards':
				$new = uh_write_rows( $post_id, $c['list'], $c['before'], 'undo-change', $summary );
				return is_wp_error( $new ) ? $new : array( 'restored' => $c['list'], 'change_id' => $new );

			case 'vitals':
				$new = uh_log_change( $post_id, 'undo-change', 'vitals', $summary, uh_vitals_raw( $post_id ) );
				update_field( 'vitals', $c['before'], $post_id );
				uh_after_member_edit( $post_id );
				return array( 'restored' => 'vitals', 'change_id' => $new );

			case 'fields':
				$now = uh_snapshot( $post_id, array_keys( $c['before']['post'] ?? array() ), array_keys( $c['before']['acf'] ?? array() ) );
				$new = uh_log_change( $post_id, 'undo-change', 'fields', $summary, $now );
				$ok  = uh_restore_snapshot( $post_id, $c['before'] );
				if ( is_wp_error( $ok ) ) {
					return $ok;
				}
				'member' === get_post_type( $post_id ) ? uh_after_member_edit( $post_id ) : uh_after_edit( $post_id );
				return array( 'restored' => array_merge( array_keys( $c['before']['post'] ?? array() ), array_keys( $c['before']['acf'] ?? array() ) ), 'change_id' => $new );

			case 'create':
				if ( ! empty( $c['before']['keep_post'] ) ) {
					update_field( 'classes', $c['before']['options_classes'], 'option' );
					uh_after_edit( $post_id );
					return array( 'restored' => 'Members page class list', 'change_id' => uh_log_change( $post_id, 'undo-change', 'restore', $summary, null ) );
				}
				if ( in_array( get_post_type( $post_id ), array( 'team', 'tournaments' ), true ) && uh_members_referencing( $post_id ) ) {
					return new WP_Error( 'uh_in_use', 'Members now point at this ' . get_post_type( $post_id ) . ', so it can\'t simply be removed. Merge it into the right one with merge instead.' );
				}
				if ( 'year' === get_post_type( $post_id ) && ( bearsmith_get_members_by_class( $post_id, array( 'post_status' => uh_editable_statuses() ) )->found_posts || uh_tournaments_in_year( $post_id ) ) ) {
					return new WP_Error( 'uh_in_use', 'Members or tournaments now use this year, so it can\'t be removed.' );
				}
				if ( isset( $c['before']['options_classes'] ) ) {
					update_field( 'classes', $c['before']['options_classes'], 'option' );
				}
				$new = uh_log_change( $post_id, 'undo-change', 'trash', $summary, array( 'status' => get_post_status( $post_id ) ) );
				wp_trash_post( $post_id );
				uh_after_edit( $post_id );
				return array( 'trashed' => $post_id, 'change_id' => $new );

			case 'trash':
				wp_untrash_post( $post_id );
				wp_update_post( array( 'ID' => $post_id, 'post_status' => $c['before']['status'] ) );
				$new = uh_log_change( $post_id, 'undo-change', 'restore', $summary, null );
				'member' === get_post_type( $post_id ) ? uh_after_member_edit( $post_id ) : uh_after_edit( $post_id );
				return array( 'restored' => $post_id, 'status' => get_post_status( $post_id ), 'change_id' => $new );

			case 'merge':
				$done = array();
				foreach ( $c['before']['members'] as $pair ) {
					$r = uh_undo_change( $pair[0], $pair[1] );
					if ( is_wp_error( $r ) ) {
						return $r;
					}
					$done[] = $pair[0];
				}
				foreach ( $c['before']['dropped'] as $pair ) {
					wp_untrash_post( $pair[0] );
					wp_update_post( array( 'ID' => $pair[0], 'post_status' => $pair[1] ) );
				}
				update_field( 'aliases', $c['before']['aliases'], $post_id );
				$new = uh_log_change( $post_id, 'undo-change', 'restore', $summary, null );
				uh_after_edit( $post_id );
				return array( 'restored_members' => $done, 'restored_posts' => array_column( $c['before']['dropped'], 0 ), 'change_id' => $new );
		}
		return new WP_Error( 'uh_cant_undo', 'That change can\'t be undone (it was itself a restore).' );
	}
	return new WP_Error( 'uh_no_change', 'No change with that id on this post. Check member-history or recent-changes.' );
}

/** Tournament ids whose year is this year post. */
function uh_tournaments_in_year( $year_post ) {
	return get_posts(
		array(
			'post_type'   => 'tournaments',
			'post_status' => uh_editable_statuses(),
			'numberposts' => -1,
			'fields'      => 'ids',
			'meta_query'  => array( array( 'key' => 'details_year', 'value' => (int) $year_post ) ),
		)
	);
}

/** post_id, falling back to member_id for callers written before post_id existed. */
function uh_input_post_id( $input ) {
	return (int) ( $input['post_id'] ?? $input['member_id'] ?? 0 );
}

/* ---------- abilities ---------- */

add_action( 'wp_abilities_api_init', 'uh_register_log_abilities' );

function uh_register_log_abilities() {
	$ro   = array( 'mcp' => array( 'public' => false ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) );
	$edit = array( 'mcp' => array( 'public' => false ), 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ) );

	wp_register_ability(
		'uhof/recent-changes',
		array(
			'label'               => 'Recent changes',
			'description'         => 'Changes made through these tools across the whole site, newest first: members, teams, tournaments and classes. Filter by who made them, since when, or one post. Each has a post_id and change id for undo-change.',
			'category'            => 'uhof',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'since'   => array( 'type' => 'string', 'description' => 'Only changes on or after this date, YYYY-MM-DD.' ),
					'user'    => array( 'type' => 'string', 'description' => 'WordPress username.' ),
					'post_id' => array( 'type' => 'integer' ),
					'limit'   => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200 ),
				),
			),
			'execute_callback'    => function ( $input ) {
				$since = ! empty( $input['since'] ) ? strtotime( $input['since'] . ' 00:00:00 UTC' ) : 0;
				$out   = array();
				foreach ( get_option( UH_CHANGE_FEED, array() ) as $c ) {
					if ( $since && strtotime( $c['time'] ) < $since ) {
						break;
					}
					if ( ( ! empty( $input['user'] ) && $c['user'] !== $input['user'] ) || ( ! empty( $input['post_id'] ) && $c['post_id'] !== (int) $input['post_id'] ) ) {
						continue;
					}
					$out[] = $c;
					if ( count( $out ) >= (int) ( $input['limit'] ?? 50 ) ) {
						break;
					}
				}
				return array( 'changes' => $out );
			},
			'permission_callback' => 'uh_can_read',
			'meta'                => $ro,
		)
	);

	wp_register_ability(
		'uhof/member-history',
		array(
			'label'               => 'Change history',
			'description'         => 'Recent changes to one member, team, tournament or year made through these tools, newest first, with change ids for undo-change.',
			'category'            => 'uhof',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'   => array( 'type' => 'integer', 'description' => 'Member, team, tournament or year id.' ),
					'member_id' => array( 'type' => 'integer', 'description' => 'Same as post_id, for members.' ),
				),
			),
			'execute_callback'    => function ( $input ) {
				$log = get_post_meta( uh_input_post_id( $input ), UH_CHANGELOG_META, true ) ?: array();
				return array( 'changes' => array_map( fn( $c ) => array_diff_key( $c, array( 'before' => 1 ) ), $log ) );
			},
			'permission_callback' => 'uh_can_read',
			'meta'                => $ro,
		)
	);

	wp_register_ability(
		'uhof/undo-change',
		array(
			'label'               => 'Undo a change',
			'description'         => 'Put things back the way they were just before a change: a member list, vitals, fields, a creation (trashes it), a trash (restores it) or a merge (restores every member it touched and the merged-away posts). Undoing an older change also discards later changes to the same list or fields, so check the history first and undo newest first.',
			'category'            => 'uhof',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'   => array( 'type' => 'integer', 'description' => 'The post the change is on (post_id in recent-changes).' ),
					'member_id' => array( 'type' => 'integer', 'description' => 'Same as post_id, for members.' ),
					'change_id' => array( 'type' => 'string' ),
				),
				'required'   => array( 'change_id' ),
			),
			'execute_callback'    => function ( $input ) {
				return uh_undo_change( uh_input_post_id( $input ), $input['change_id'] );
			},
			'permission_callback' => function ( $input ) {
				$id = uh_input_post_id( $input );
				return $id && current_user_can( 'edit_post', $id );
			},
			'meta'                => $edit,
		)
	);
}
