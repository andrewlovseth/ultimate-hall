<?php
/**
 * WP-CLI commands for the data model.
 *
 *   wp uh migrate [--apply]          Old repeaters -> career / results / service, tournament series.
 *   wp uh reindex                    Rebuild _uh_team / _uh_tournament and search index for all members.
 *   wp uh cleanup-legacy [--apply]   Delete meta of the retired repeaters after migrating.
 *   wp uh merge <keep> <drop>... [--apply]   Repoint every reference from duplicate posts to one, then trash the duplicates.
 *
 * Writes raw postmeta with ACF field-key references, so it runs before or
 * after the theme's field groups are deployed.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

class UH_CLI {

	/** Manual fixes for year strings the parser can't read. raw => [spans, extra text]. */
	const YEAR_OVERRIDES = array(
		'990-98, member 1990-present' => array( array( array( 1990, 1998, false, false ) ), 'member 1990–present' ),
	);

	private $apply = false;
	private $log   = array();

	/**
	 * Migrate old repeaters to the new model.
	 *
	 * ## OPTIONS
	 * [--apply]
	 * : Write changes. Without it, prints what would change.
	 */
	public function migrate( $args, $assoc ) {
		$this->apply = ! empty( $assoc['apply'] );
		$members     = get_posts( array( 'post_type' => 'member', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) );
		$stats       = array( 'members' => 0, 'career_in' => 0, 'career_out' => 0, 'results' => 0, 'service_in' => 0, 'service_out' => 0, 'moved_series' => 0 );

		$series = $this->migrate_series( $stats );

		foreach ( $members as $id ) {
			$stats['members']++;
			$this->migrate_career( $id, $stats );
			$this->migrate_results( $id, $series, $stats );
			$this->migrate_service( $id, $stats );
			if ( $this->apply ) {
				uh_sync_member( $id );
			}
		}

		foreach ( $this->log as $line ) {
			WP_CLI::log( $line );
		}
		WP_CLI::log( wp_json_encode( $stats ) );
		WP_CLI::success( $this->apply ? 'Migrated.' : 'Dry run only. Re-run with --apply to write.' );
	}

	/** Set details_series on every tournament; return id => series. */
	private function migrate_series( &$stats ) {
		$out = array();
		foreach ( get_posts( array( 'post_type' => 'tournaments', 'post_status' => 'any', 'numberposts' => -1 ) ) as $t ) {
			$existing = get_post_meta( $t->ID, 'details_series', true );
			$series   = $existing ?: uh_guess_series( $t->post_title );
			$out[ $t->ID ] = $series;
			if ( $this->apply && ! $existing ) {
				update_post_meta( $t->ID, 'details_series', $series );
				update_post_meta( $t->ID, '_details_series', 'field_uh_tournament_series' );
			}
		}
		return $out;
	}

	private function rows( $id, $repeater, array $cols ) {
		$rows  = array();
		$count = (int) get_post_meta( $id, $repeater, true );
		for ( $i = 0; $i < $count; $i++ ) {
			$row = array();
			foreach ( $cols as $col ) {
				$row[ $col ] = get_post_meta( $id, "{$repeater}_{$i}_{$col}", true );
			}
			$rows[] = $row;
		}
		return $rows;
	}

	private function spans_for( $raw, $who ) {
		$key = trim( (string) $raw );
		if ( isset( self::YEAR_OVERRIDES[ $key ] ) ) {
			list( $spans, $extra ) = self::YEAR_OVERRIDES[ $key ];
			return array(
				array_map( fn( $s ) => array( 'start' => $s[0], 'end' => $s[1], 'to_present' => $s[2], 'approximate' => $s[3] ), $spans ),
				$extra,
			);
		}
		$parsed = uh_parse_years( $raw );
		$extra  = implode( ', ', $parsed['unparsed'] );
		if ( $extra ) {
			$this->log[] = "UNPARSED years for {$who}: \"{$raw}\" -> kept as text \"{$extra}\"";
		}
		return array( $parsed['spans'], $extra );
	}

	/** Replace a repeater's meta with new rows. $fields: col => field key. */
	private function write_repeater( $id, $name, $key, array $fields, array $rows ) {
		if ( ! $this->apply ) {
			return;
		}
		$old = (int) get_post_meta( $id, $name, true );
		for ( $i = 0; $i < max( $old, count( $rows ) ); $i++ ) {
			foreach ( array_keys( $fields ) as $col ) {
				delete_post_meta( $id, "{$name}_{$i}_{$col}" );
				delete_post_meta( $id, "_{$name}_{$i}_{$col}" );
			}
		}
		foreach ( $rows as $i => $row ) {
			foreach ( $fields as $col => $fkey ) {
				$value = $row[ $col ] ?? '';
				if ( is_bool( $value ) ) {
					$value = $value ? '1' : '0';
				}
				update_post_meta( $id, "{$name}_{$i}_{$col}", null === $value ? '' : $value );
				update_post_meta( $id, "_{$name}_{$i}_{$col}", $fkey );
			}
		}
		update_post_meta( $id, $name, count( $rows ) );
		update_post_meta( $id, "_{$name}", $key );
	}

	private function migrate_career( $id, &$stats ) {
		$who = get_the_title( $id );
		$out = array();
		foreach ( $this->rows( $id, 'playing_career', array( 'year', 'team', 'note' ) ) as $row ) {
			$stats['career_in']++;
			list( $spans, $extra ) = $this->spans_for( $row['year'], $who );
			$note = trim( implode( '; ', array_filter( array( $row['note'], $extra ) ) ) );
			if ( ! $spans ) {
				$spans = array( array( 'start' => null, 'end' => null, 'to_present' => false, 'approximate' => false ) );
			}
			foreach ( $spans as $n => $sp ) {
				$out[] = array(
					'team'        => $row['team'],
					'start_year'  => $sp['start'],
					'end_year'    => $sp['end'],
					'to_present'  => $sp['to_present'],
					'approximate' => $sp['approximate'],
					'note'        => 0 === $n ? $note : '',
				);
			}
		}
		$stats['career_out'] += count( $out );
		$this->write_repeater(
			$id, 'career', 'field_uh_career',
			array( 'team' => 'field_uh_career_team', 'start_year' => 'field_uh_career_start', 'end_year' => 'field_uh_career_end', 'to_present' => 'field_uh_career_present', 'approximate' => 'field_uh_career_approx', 'note' => 'field_uh_career_note' ),
			$out
		);
	}

	private function migrate_results( $id, array $series, &$stats ) {
		$who  = get_the_title( $id );
		$out  = array();
		$from = array( 'us_championships' => 'us', 'wfdf_championships' => 'world', 'pro_seasons' => 'pro' );
		foreach ( $from as $repeater => $expected ) {
			$col = 'pro_seasons' === $repeater ? 'season' : 'tournament';
			foreach ( $this->rows( $id, $repeater, array( 'team', $col, 'placement' ) ) as $row ) {
				if ( ! $row[ $col ] && ! $row['team'] ) {
					continue;
				}
				if ( ! $row[ $col ] ) {
					$this->log[] = "NO TOURNAMENT (kept) {$who}: {$repeater}, team " . get_the_title( $row['team'] ) . ", {$row['placement']}";
				}
				$actual = $series[ (int) $row[ $col ] ] ?? '';
				if ( $actual && $actual !== $expected ) {
					$stats['moved_series']++;
					$this->log[] = "MOVED {$who}: " . get_the_title( $row[ $col ] ) . " was under {$repeater}, tournament series is {$actual}";
				}
				$out[] = array( 'tournament' => $row[ $col ], 'team' => $row['team'], 'placement' => $row['placement'] );
			}
		}
		$stats['results'] += count( $out );
		$this->write_repeater(
			$id, 'results', 'field_uh_results',
			array( 'tournament' => 'field_uh_results_tournament', 'team' => 'field_uh_results_team', 'placement' => 'field_uh_results_placement' ),
			$out
		);
	}

	private function migrate_service( $id, &$stats ) {
		$who = get_the_title( $id );
		$out = array();
		foreach ( $this->rows( $id, 'contributions_and_service', array( 'year', 'description' ) ) as $row ) {
			$stats['service_in']++;
			list( $spans, $extra ) = $this->spans_for( $row['year'], $who );
			$desc = $row['description'] . ( $extra ? " ({$extra})" : '' );
			if ( ! $spans ) {
				$spans = array( array( 'start' => null, 'end' => null, 'to_present' => false, 'approximate' => false ) );
			}
			foreach ( $spans as $sp ) {
				$out[] = array( 'start_year' => $sp['start'], 'end_year' => $sp['end'], 'to_present' => $sp['to_present'], 'approximate' => $sp['approximate'], 'description' => $desc );
			}
		}
		$stats['service_out'] += count( $out );
		$this->write_repeater(
			$id, 'service', 'field_uh_service',
			array( 'start_year' => 'field_uh_service_start', 'end_year' => 'field_uh_service_end', 'to_present' => 'field_uh_service_present', 'approximate' => 'field_uh_service_approx', 'description' => 'field_uh_service_desc' ),
			$out
		);
	}

	/** Rebuild derived meta (reverse lookups + search index) for every member. */
	public function reindex() {
		$ids = get_posts( array( 'post_type' => 'member', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) );
		foreach ( $ids as $id ) {
			uh_sync_member( $id );
		}
		WP_CLI::success( count( $ids ) . ' members reindexed.' );
	}

	/**
	 * Delete postmeta of the retired repeaters and the tournament "professional" flag.
	 *
	 * ## OPTIONS
	 * [--apply]
	 * : Delete. Without it, counts rows only.
	 */
	public function cleanup_legacy( $args, $assoc ) {
		global $wpdb;
		$pattern = '^_?(playing_career|us_championships|wfdf_championships|pro_seasons|contributions_and_service)(_[0-9]+_.+)?$|^_?details_professional$';
		$count   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key REGEXP %s", $pattern ) );
		if ( empty( $assoc['apply'] ) ) {
			WP_CLI::success( "{$count} legacy meta rows would be deleted. Re-run with --apply." );
			return;
		}
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key REGEXP %s", $pattern ) );
		wp_cache_flush();
		WP_CLI::success( "{$count} legacy meta rows deleted." );
	}

	/**
	 * Merge duplicate teams or tournaments into one.
	 *
	 * ## OPTIONS
	 * <keep>
	 * : Post ID to keep.
	 * <drop>...
	 * : Post IDs to merge into <keep> and trash.
	 * [--apply]
	 * : Write. Without it, lists the references that would move.
	 */
	public function merge( $args, $assoc ) {
		global $wpdb;
		$keep = (int) array_shift( $args );
		$drop = array_map( 'intval', $args );
		$type = get_post_type( $keep );
		foreach ( $drop as $d ) {
			if ( get_post_type( $d ) !== $type ) {
				WP_CLI::error( "Post {$d} is not a {$type}." );
			}
		}
		$in   = implode( ',', $drop );
		$keys = 'team' === $type
			? '^(career|results)_[0-9]+_team$'
			: '^results_[0-9]+_tournament$';
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key REGEXP %s AND meta_value IN ({$in})", $keys ) );
		WP_CLI::log( count( $rows ) . " references from {$in} -> {$keep} (" . get_the_title( $keep ) . ')' );
		if ( empty( $assoc['apply'] ) ) {
			return;
		}
		$touched = array();
		foreach ( $rows as $r ) {
			$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $keep ), array( 'meta_id' => $r->meta_id ) );
			clean_post_cache( $r->post_id );
			$touched[ $r->post_id ] = true;
		}
		foreach ( array_keys( $touched ) as $pid ) {
			uh_sync_member( $pid );
		}
		foreach ( $drop as $d ) {
			wp_trash_post( $d );
		}
		WP_CLI::success( 'Merged ' . count( $drop ) . ' into ' . $keep . '; ' . count( $touched ) . ' members updated.' );
	}
}

WP_CLI::add_command( 'uh', 'UH_CLI' );
