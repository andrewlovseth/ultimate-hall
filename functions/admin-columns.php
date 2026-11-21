<?php
/**
 * Admin list-table columns, sorting and filters.
 *
 * Replaces the Admin Columns Pro setup (four list screens, unchanged since 2021):
 *   Members      Title, Class, Type, Date. Class and Type sortable and filterable.
 *   Teams        Title, Division, Date. Division links to the division, sortable and filterable.
 *   Tournaments  Title, Date. Defaults to title, Z to A.
 *   Media        File, Author, Uploaded to, Comments, Date, Width. Width sortable.
 * Categories and Tags are hidden on Members and Teams: no member or team has a term.
 */

/** The member list screen's columns. */
add_filter(
	'manage_member_posts_columns',
	function ( $columns ) {
		return array(
			'cb'           => $columns['cb'],
			'title'        => __( 'Title' ),
			'uh_class'     => 'Class',
			'uh_induction' => 'Type',
			'date'         => __( 'Date' ),
		);
	}
);

add_action(
	'manage_member_posts_custom_column',
	function ( $column, $post_id ) {
		if ( 'uh_class' === $column ) {
			$year_id = (int) get_post_meta( $post_id, 'meta_class', true );
			echo $year_id ? esc_html( get_the_title( $year_id ) ) : '&mdash;';
		} elseif ( 'uh_induction' === $column ) {
			$type = get_post_meta( $post_id, 'meta_induction_type', true );
			echo $type ? esc_html( uh_admin_induction_types()[ $type ] ?? ucwords( str_replace( '-', ' ', $type ) ) ) : '&mdash;';
		}
	},
	10,
	2
);

add_filter(
	'manage_edit-member_sortable_columns',
	function ( $columns ) {
		$columns['uh_class']     = 'uh_class';
		$columns['uh_induction'] = 'uh_induction';
		return $columns;
	}
);

/** The team list screen's columns. */
add_filter(
	'manage_team_posts_columns',
	function ( $columns ) {
		return array(
			'cb'          => $columns['cb'],
			'title'       => __( 'Title' ),
			'uh_division' => 'Division',
			'date'        => __( 'Date' ),
		);
	}
);

add_action(
	'manage_team_posts_custom_column',
	function ( $column, $post_id ) {
		if ( 'uh_division' !== $column ) {
			return;
		}
		$links = array();
		foreach ( (array) get_post_meta( $post_id, 'division', true ) as $division_id ) {
			if ( $division_id && get_post( (int) $division_id ) ) {
				$links[] = sprintf( '<a href="%s">%s</a>', esc_url( get_edit_post_link( (int) $division_id ) ), esc_html( get_the_title( (int) $division_id ) ) );
			}
		}
		echo $links ? implode( ', ', $links ) : '&mdash;'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
	},
	10,
	2
);

add_filter(
	'manage_edit-team_sortable_columns',
	function ( $columns ) {
		$columns['uh_division'] = 'uh_division';
		return $columns;
	}
);

/** The media list screen: ACP's widths plus an image Width column. */
add_filter(
	'manage_media_columns',
	function ( $columns ) {
		$columns['uh_width'] = 'Width';
		return $columns;
	}
);

add_action(
	'manage_media_custom_column',
	function ( $column, $post_id ) {
		if ( 'uh_width' !== $column ) {
			return;
		}
		$meta = wp_get_attachment_metadata( $post_id );
		echo ! empty( $meta['width'] ) ? esc_html( $meta['width'] . 'px' ) : '&mdash;';
	},
	10,
	2
);

add_filter(
	'manage_upload_sortable_columns',
	function ( $columns ) {
		$columns['uh_width'] = 'uh_width';
		return $columns;
	}
);

add_action(
	'admin_head-upload.php',
	function () {
		echo '<style>.wp-list-table .column-author{width:10%}.wp-list-table .column-parent{width:15%}.wp-list-table .column-date,.wp-list-table .column-uh_width{width:10%}</style>';
	}
);

add_action(
	'admin_head-edit.php',
	function () {
		if ( 'member' === get_current_screen()->post_type ) {
			echo '<style>.wp-list-table .column-title{width:40%}</style>';
		}
	}
);

/** Induction type labels, read from the ACF field so they stay in one place. */
function uh_admin_induction_types() {
	static $choices = null;
	if ( null === $choices ) {
		$field   = function_exists( 'acf_get_field' ) ? acf_get_field( 'field_603d25e0d569b' ) : null;
		$choices = $field['choices'] ?? array();
	}
	return $choices;
}

/** Dropdown filters above the Members and Teams tables. */
add_action(
	'restrict_manage_posts',
	function ( $post_type ) {
		if ( 'member' === $post_type ) {
			$years = get_posts(
				array(
					'post_type'      => 'year',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'orderby'        => 'title',
					'order'          => 'DESC',
				)
			);
			uh_admin_filter_dropdown( 'uh_class', 'All classes', wp_list_pluck( $years, 'post_title', 'ID' ) );
			uh_admin_filter_dropdown( 'uh_induction', 'All types', uh_admin_induction_types() );
		} elseif ( 'team' === $post_type ) {
			$divisions = get_posts(
				array(
					'post_type'      => 'division',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			);
			uh_admin_filter_dropdown( 'uh_division', 'All divisions', wp_list_pluck( $divisions, 'post_title', 'ID' ) );
		}
	}
);

/** One filter dropdown, keeping the current choice selected. */
function uh_admin_filter_dropdown( $name, $all_label, array $options ) {
	$current = isset( $_GET[ $name ] ) ? sanitize_text_field( wp_unslash( $_GET[ $name ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- read-only list filter.
	printf( '<label class="screen-reader-text" for="%1$s">%2$s</label><select name="%1$s" id="%1$s">', esc_attr( $name ), esc_html( $all_label ) );
	printf( '<option value="">%s</option>', esc_html( $all_label ) );
	foreach ( $options as $value => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $current, (string) $value, false ), esc_html( $label ) );
	}
	echo '</select>';
}

/** True for the main query of a post or media list screen. */
function uh_admin_is_list_query( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || ! function_exists( 'get_current_screen' ) ) {
		return false;
	}
	$screen = get_current_screen();
	return $screen && in_array( $screen->base, array( 'edit', 'upload' ), true );
}

/** Applies the filters and the simple sorts to the list query. */
add_action(
	'pre_get_posts',
	function ( $query ) {
		if ( ! uh_admin_is_list_query( $query ) ) {
			return;
		}
		$post_type = $query->get( 'post_type' );
		$meta      = array_filter( (array) $query->get( 'meta_query' ) );

		// phpcs:disable WordPress.Security.NonceVerification -- read-only list filters.
		if ( 'member' === $post_type ) {
			if ( ! empty( $_GET['uh_class'] ) ) {
				$meta[] = array(
					'key'   => 'meta_class',
					'value' => absint( $_GET['uh_class'] ),
				);
			}
			if ( ! empty( $_GET['uh_induction'] ) ) {
				$meta[] = array(
					'key'   => 'meta_induction_type',
					'value' => sanitize_key( wp_unslash( $_GET['uh_induction'] ) ),
				);
			}
			if ( 'uh_induction' === $query->get( 'orderby' ) ) {
				$query->set( 'meta_key', 'meta_induction_type' );
				$query->set( 'orderby', 'meta_value' );
			}
		} elseif ( 'team' === $post_type && ! empty( $_GET['uh_division'] ) ) {
			// ACF stores a relationship as a serialized array of ID strings.
			$meta[] = array(
				'key'     => 'division',
				'value'   => '"' . absint( $_GET['uh_division'] ) . '"',
				'compare' => 'LIKE',
			);
		} elseif ( 'tournaments' === $post_type && ! $query->get( 'orderby' ) ) {
			$query->set( 'orderby', 'title' );
			$query->set( 'order', 'DESC' );
		}
		// phpcs:enable

		if ( $meta ) {
			$query->set( 'meta_query', $meta );
		}
	}
);

/**
 * Sorts that need a join: member Class and team Division sort by the linked post's title,
 * media Width by the width inside the serialized attachment metadata.
 */
add_filter(
	'posts_clauses',
	function ( $clauses, $query ) {
		global $wpdb;
		if ( ! uh_admin_is_list_query( $query ) ) {
			return $clauses;
		}
		$orderby = $query->get( 'orderby' );
		$order   = 'DESC' === strtoupper( (string) $query->get( 'order' ) ) ? 'DESC' : 'ASC';

		if ( 'uh_class' === $orderby ) {
			$clauses['join']   .= " LEFT JOIN {$wpdb->postmeta} uh_cm ON uh_cm.post_id = {$wpdb->posts}.ID AND uh_cm.meta_key = 'meta_class'"
				. " LEFT JOIN {$wpdb->posts} uh_cp ON uh_cp.ID = uh_cm.meta_value";
			$clauses['orderby'] = "uh_cp.post_title {$order}, {$wpdb->posts}.post_title ASC";
		} elseif ( 'uh_division' === $orderby ) {
			// The first ID in a:N:{i:0;s:L:"123";...}.
			$clauses['join']   .= " LEFT JOIN {$wpdb->postmeta} uh_dm ON uh_dm.post_id = {$wpdb->posts}.ID AND uh_dm.meta_key = 'division'"
				. " LEFT JOIN {$wpdb->posts} uh_dp ON uh_dp.ID = REGEXP_SUBSTR(REGEXP_SUBSTR(uh_dm.meta_value, 'i:0;s:[0-9]+:\"[0-9]+\"'), '[0-9]+(?=\"$)')";
			$clauses['orderby'] = "uh_dp.post_title {$order}, {$wpdb->posts}.post_title ASC";
		} elseif ( 'uh_width' === $orderby ) {
			$clauses['join']   .= " LEFT JOIN {$wpdb->postmeta} uh_wm ON uh_wm.post_id = {$wpdb->posts}.ID AND uh_wm.meta_key = '_wp_attachment_metadata'";
			$clauses['orderby'] = "CAST(REGEXP_SUBSTR(REGEXP_SUBSTR(uh_wm.meta_value, 's:5:\"width\";i:[0-9]+'), '[0-9]+$') AS UNSIGNED) {$order}, {$wpdb->posts}.post_date DESC";
		}
		return $clauses;
	},
	10,
	2
);
