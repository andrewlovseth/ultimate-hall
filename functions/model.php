<?php
/**
 * Data model helpers for members, teams and tournaments.
 *
 * The model (since the Nov 2026 rework):
 *
 *   member.career[]   one stint on a team: team, start_year, end_year, to_present, approximate, note
 *   member.results[]  one appearance at a tournament: tournament, team, placement
 *   member.service[]  one contribution: start_year, end_year, to_present, approximate, description
 *   tournaments.details.series   us | world | pro  (what the old us/wfdf/pro repeaters encoded)
 *
 * Reverse lookups ("which members played for this team / at this tournament")
 * read two derived, non-unique meta keys kept on each member:
 *
 *   _uh_team        one row per team ID found in career or results
 *   _uh_tournament  one row per tournament ID found in results
 *
 * uh_sync_member() rebuilds them, and the search index, on every save path:
 * wp-admin (acf/save_post), REST (rest_after_insert_member) and WP-CLI.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const UH_TEAM_INDEX       = '_uh_team';
const UH_TOURNAMENT_INDEX = '_uh_tournament';

/** Placement vocabulary, in display order. Shared by the field group, REST and MCP schemas. */
function uh_placements() {
	return array(
		'1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th', '11th', '12th', '13th', '14th', '15th', '16th',
		'Finals', 'Semifinals', 'Quarterfinals', 'Pre-Quarterfinals', 'Power Pools', 'Pool Play', 'Playoffs', 'Missed Playoffs',
		'1st Round', '2nd Round', '3rd Round', '4th Round', 'Regionals', 'Sectionals',
	);
}

/** Tournament series vocabulary. */
function uh_series() {
	return array(
		'us'    => 'U.S. National Championships',
		'world' => 'World / International',
		'pro'   => 'Professional',
	);
}

/**
 * Suggest a series from a tournament title. Used by the migration and by
 * the create-tournament ability when the caller doesn't say.
 */
function uh_guess_series( $title ) {
	$t = strtolower( $title );
	if ( preg_match( '/\b(audl|pul|mlu|ufa|wul)\b|premier ultimate|major league ultimate|american ultimate disc|ultimate frisbee association/', $t ) ) {
		return 'pro';
	}
	if ( preg_match( '/\b(wugc|wucc|wcbu|wmucc|wuc|wbuc|wfdf|pauc)\b|world games|world juniors|pan american|european championships/', $t ) ) {
		return 'world';
	}
	return 'us';
}

/**
 * Parse a free-text year string ("1992-93, 1995", "Late 1970s", "2017-Present")
 * into year spans. Returns array( 'spans' => [...], 'unparsed' => [...] ).
 * Each span: start (int|null), end (int|null), to_present (bool), approximate (bool).
 */
function uh_parse_years( $raw ) {
	$s = trim( (string) $raw );
	$s = str_replace( array( "\xE2\x80\x93", "\xE2\x80\x94", "\t" ), array( '-', '-', ' ' ), $s );
	$spans    = array();
	$unparsed = array();
	if ( '' === $s ) {
		return compact( 'spans', 'unparsed' );
	}
	foreach ( preg_split( '/[,;&+]/', $s ) as $piece ) {
		$p = strtolower( trim( $piece ) );
		$p = preg_replace( '/^and\s+/', '', $p );
		$p = preg_replace( '/\s*-\s*/', '-', $p );
		$p = preg_replace( '/^1(19\d\d|20\d\d)/', '$1', $p ); // "11990" typo.
		if ( '' === $p ) {
			continue;
		}
		if ( preg_match( '/^(\d{4})$/', $p, $m ) ) {
			$spans[] = array( 'start' => (int) $m[1], 'end' => null, 'to_present' => false, 'approximate' => false );
		} elseif ( preg_match( '/^(\d{4})-(\d{4}|\d{2})$/', $p, $m ) ) {
			$start = (int) $m[1];
			$end   = (int) $m[2];
			if ( strlen( $m[2] ) === 2 ) {
				$end = (int) floor( $start / 100 ) * 100 + $end;
				if ( $end < $start ) {
					$end += 100;
				}
			}
			$spans[] = array( 'start' => $start, 'end' => $end, 'to_present' => false, 'approximate' => false );
		} elseif ( preg_match( '/^(\d{4})-(present|current|now)$/', $p, $m ) ) {
			$spans[] = array( 'start' => (int) $m[1], 'end' => null, 'to_present' => true, 'approximate' => false );
		} elseif ( preg_match( '/^(early|mid|late)?-?\s*(\d{3})0s$/', $p, $m ) ) {
			$decade = (int) $m[2] * 10;
			$range  = array( '' => array( 0, 9 ), 'early' => array( 0, 3 ), 'mid' => array( 4, 6 ), 'late' => array( 7, 9 ) );
			$r      = $range[ $m[1] ];
			$spans[] = array( 'start' => $decade + $r[0], 'end' => $decade + $r[1], 'to_present' => false, 'approximate' => true );
		} elseif ( preg_match( '/^(\d{3})0s-(\d{3})0s$/', $p, $m ) ) {
			$spans[] = array( 'start' => (int) $m[1] * 10, 'end' => (int) $m[2] * 10 + 9, 'to_present' => false, 'approximate' => true );
		} else {
			$unparsed[] = trim( $piece );
		}
	}
	return compact( 'spans', 'unparsed' );
}

/** Format one span: 1995, 1992–1993, 2017–present, c. 1977–1979. */
function uh_format_span( $start, $end, $to_present = false, $approximate = false ) {
	$start = $start ? (int) $start : null;
	$end   = $end ? (int) $end : null;
	if ( ! $start ) {
		return '';
	}
	if ( $approximate && $end ) {
		$decade = $start - $start % 10;
		$named  = array( '0-9' => '', '0-3' => 'early ', '4-6' => 'mid ', '7-9' => 'late ' );
		$k      = ( $start - $decade ) . '-' . ( $end - $decade );
		if ( isset( $named[ $k ] ) ) {
			return $named[ $k ] . $decade . 's';
		}
	}
	$out = (string) $start;
	if ( $to_present ) {
		$out .= "\u{2013}present";
	} elseif ( $end && $end !== $start ) {
		$out .= "\u{2013}" . $end;
	}
	return $approximate ? 'c. ' . $out : $out;
}

/** Format a list of spans, merging ones that touch or overlap: 1988–1990 + 1991 + 1992–1994 -> 1988–1994. */
function uh_format_spans( array $spans ) {
	$spans = array_values( array_filter( $spans, fn( $s ) => ! empty( $s['start'] ) ) );
	usort( $spans, fn( $a, $b ) => (int) $a['start'] <=> (int) $b['start'] );
	$merged = array();
	foreach ( $spans as $sp ) {
		$sp['start'] = (int) $sp['start'];
		$sp['end']   = ! empty( $sp['end'] ) ? (int) $sp['end'] : $sp['start'];
		$last        = count( $merged ) - 1;
		if ( $last >= 0 && empty( $sp['approximate'] ) && empty( $merged[ $last ]['approximate'] ) && empty( $merged[ $last ]['to_present'] )
			&& $sp['start'] <= $merged[ $last ]['end'] + 1 ) {
			$merged[ $last ]['end']        = max( $merged[ $last ]['end'], $sp['end'] );
			$merged[ $last ]['to_present'] = ! empty( $sp['to_present'] );
			continue;
		}
		$merged[] = $sp;
	}
	$parts = array();
	foreach ( $merged as $sp ) {
		$parts[] = uh_format_span( $sp['start'], $sp['end'], ! empty( $sp['to_present'] ), ! empty( $sp['approximate'] ) );
	}
	return implode( ', ', array_unique( $parts ) );
}

function uh_post_id( $value ) {
	if ( is_object( $value ) && isset( $value->ID ) ) {
		return (int) $value->ID;
	}
	return $value ? (int) $value : 0;
}

/** Which display group a team belongs to: youth, college, club, masters, grandmasters, great_grandmasters, professional, beach, or ''. */
function uh_team_group( $team_id ) {
	static $map = null;
	if ( null === $map ) {
		$map = array();
		foreach ( array( 'youth', 'college', 'club', 'masters', 'grandmasters', 'great_grandmasters', 'professional', 'beach' ) as $group ) {
			foreach ( (array) get_field( $group . '_divisions', 'options' ) as $division ) {
				if ( $division ) {
					$map[ $division->post_name ][] = $group;
				}
			}
		}
	}
	$division = get_field( 'division', $team_id );
	if ( ! $division ) {
		return '';
	}
	$groups = $map[ $division[0]->post_name ] ?? array();
	return $groups[0] ?? '';
}

/** A team's division post (first in the relationship) or null. */
function uh_team_division( $team_id ) {
	$division = get_field( 'division', $team_id );
	return $division ? $division[0] : null;
}

/** Tournament year as a display string ("1995"), from its linked year post. */
function uh_tournament_year( $tournament_id ) {
	$year = get_field( 'details_year', $tournament_id );
	return $year ? get_the_title( $year ) : '';
}

/**
 * A member's career stints, grouped by team in first-seen order, each with
 * its display years. Returns list of array( team => WP_Post, years => string, note => string, group => string ).
 */
function uh_member_career( $member_id ) {
	$rows    = get_field( 'career', $member_id ) ?: array();
	$grouped = array();
	foreach ( $rows as $row ) {
		$team_id = uh_post_id( $row['team'] ?? 0 );
		if ( ! $team_id || ! get_post( $team_id ) ) {
			continue;
		}
		if ( ! isset( $grouped[ $team_id ] ) ) {
			$grouped[ $team_id ] = array( 'team' => get_post( $team_id ), 'spans' => array(), 'notes' => array(), 'group' => uh_team_group( $team_id ) );
		}
		$grouped[ $team_id ]['spans'][] = array(
			'start'       => $row['start_year'] ?? null,
			'end'         => $row['end_year'] ?? null,
			'to_present'  => ! empty( $row['to_present'] ),
			'approximate' => ! empty( $row['approximate'] ),
		);
		if ( ! empty( $row['note'] ) ) {
			$grouped[ $team_id ]['notes'][] = $row['note'];
		}
	}
	$out = array();
	foreach ( $grouped as $g ) {
		$out[] = array(
			'team'  => $g['team'],
			'years' => uh_format_spans( $g['spans'] ),
			'first' => min( array_map( fn( $s ) => (int) ( $s['start'] ?: 9999 ), $g['spans'] ) ),
			'note'  => implode( '; ', array_unique( $g['notes'] ) ),
			'group' => $g['group'],
		);
	}
	return $out;
}

/**
 * A member's results, optionally filtered by series. Each: tournament, team
 * (WP_Post or null), placement, series, year, beach. Sorted by year.
 */
function uh_member_results( $member_id, $series = null ) {
	$rows = get_field( 'results', $member_id ) ?: array();
	$out  = array();
	foreach ( $rows as $row ) {
		$tid = uh_post_id( $row['tournament'] ?? 0 );
		if ( $tid && ! get_post( $tid ) ) {
			$tid = 0;
		}
		// A result with no tournament yet (legacy data) counts as U.S. so it still shows.
		$row_series = $tid ? ( get_field( 'details_series', $tid ) ?: uh_guess_series( get_the_title( $tid ) ) ) : 'us';
		if ( $series && $row_series !== $series ) {
			continue;
		}
		$team_id = uh_post_id( $row['team'] ?? 0 );
		$out[]   = array(
			'tournament' => $tid ? get_post( $tid ) : null,
			'team'       => $team_id ? get_post( $team_id ) : null,
			'placement'  => (string) ( $row['placement'] ?? '' ),
			'series'     => $row_series,
			'year'       => $tid ? uh_tournament_year( $tid ) : '',
			'beach'      => $tid ? (bool) get_field( 'details_beach', $tid ) : false,
		);
	}
	usort( $out, fn( $a, $b ) => strcmp( $a['year'] ?: '9999', $b['year'] ?: '9999' ) );
	return $out;
}

/** A member's service entries with display years. Consecutive rows with the same description merge: "1992, 1994: Board member". */
function uh_member_service( $member_id ) {
	$out = array();
	foreach ( get_field( 'service', $member_id ) ?: array() as $row ) {
		$desc = (string) ( $row['description'] ?? '' );
		$span = array( 'start' => $row['start_year'] ?? null, 'end' => $row['end_year'] ?? null, 'to_present' => ! empty( $row['to_present'] ), 'approximate' => ! empty( $row['approximate'] ) );
		$last = count( $out ) - 1;
		if ( $last >= 0 && $out[ $last ]['description'] === $desc && '' !== $desc ) {
			$out[ $last ]['spans'][] = $span;
		} else {
			$out[] = array( 'description' => $desc, 'spans' => array( $span ) );
		}
	}
	foreach ( $out as &$entry ) {
		$entry['years'] = uh_format_spans( $entry['spans'] );
		unset( $entry['spans'] );
	}
	return $out;
}

/** Published member IDs that played for a team (career or results), alphabetical. */
function uh_members_for_team( $team_id ) {
	return get_posts(
		array(
			'post_type'      => 'member',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
			'meta_query'     => array( array( 'key' => UH_TEAM_INDEX, 'value' => (int) $team_id ) ),
		)
	);
}

/** Published member IDs with a result at a tournament, alphabetical. */
function uh_members_for_tournament( $tournament_id ) {
	return get_posts(
		array(
			'post_type'      => 'member',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
			'meta_query'     => array( array( 'key' => UH_TOURNAMENT_INDEX, 'value' => (int) $tournament_id ) ),
		)
	);
}

/** Rebuild one member's reverse-lookup index from raw meta (no ACF formatting). */
function uh_sync_member_index( $member_id ) {
	$teams       = array();
	$tournaments = array();
	foreach ( array( 'career' => array( 'team' ), 'results' => array( 'team', 'tournament' ) ) as $repeater => $cols ) {
		$count = (int) get_post_meta( $member_id, $repeater, true );
		for ( $i = 0; $i < $count; $i++ ) {
			foreach ( $cols as $col ) {
				$id = (int) get_post_meta( $member_id, "{$repeater}_{$i}_{$col}", true );
				if ( ! $id ) {
					continue;
				}
				if ( 'team' === $col ) {
					$teams[ $id ] = true;
				} else {
					$tournaments[ $id ] = true;
				}
			}
		}
	}
	delete_post_meta( $member_id, UH_TEAM_INDEX );
	delete_post_meta( $member_id, UH_TOURNAMENT_INDEX );
	foreach ( array_keys( $teams ) as $id ) {
		add_post_meta( $member_id, UH_TEAM_INDEX, $id );
	}
	foreach ( array_keys( $tournaments ) as $id ) {
		add_post_meta( $member_id, UH_TOURNAMENT_INDEX, $id );
	}
}

/** Everything derived from a member's fields: reverse-lookup index and search index. */
function uh_sync_member( $member_id ) {
	if ( ! is_numeric( $member_id ) || 'member' !== get_post_type( (int) $member_id ) ) {
		return;
	}
	uh_sync_member_index( (int) $member_id );
	if ( function_exists( 'bearsmith_build_member_search_index' ) ) {
		bearsmith_build_member_search_index( (int) $member_id );
	}
}
add_action( 'acf/save_post', 'uh_sync_member', 20 );
add_action(
	'rest_after_insert_member',
	function ( $post ) {
		uh_sync_member( $post->ID );
	}
);
