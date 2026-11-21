<?php
/**
 * Member-level abilities: create, update and publish members, bios, photos,
 * awards, class review, and class (year) pages. Row-level edits live in
 * abilities.php; logging and undo in abilities-log.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Induction choices, value => label, read from the field group so the tools never drift from wp-admin. */
function uh_meta_choices( $name ) {
	$field = acf_get_field( 'meta' );
	foreach ( (array) ( $field['sub_fields'] ?? array() ) as $sf ) {
		if ( $sf['name'] === $name ) {
			return (array) $sf['choices'];
		}
	}
	return array();
}

/** Accept a choice by value or label ("Women's Division" or "womens-division"). */
function uh_choice_value( $name, $given ) {
	foreach ( uh_meta_choices( $name ) as $value => $label ) {
		if ( uh_normalize_name( $given ) === uh_normalize_name( $value ) || uh_normalize_name( $given ) === uh_normalize_name( $label ) ) {
			return $value;
		}
	}
	return new WP_Error( 'uh_bad_choice', "Unknown {$name}. Use one of: " . implode( ', ', uh_meta_choices( $name ) ) );
}

/** Bio HTML: paragraphs, emphasis, links and lists survive; everything else is dropped. Plain text becomes paragraphs. */
function uh_clean_bio( $bio ) {
	$bio = trim( (string) $bio );
	if ( '' === $bio ) {
		return '';
	}
	if ( false === strpos( $bio, '<' ) ) {
		return wpautop( esc_html( $bio ) );
	}
	return trim(
		wp_kses(
			$bio,
			array(
				'p'          => array(),
				'br'         => array(),
				'strong'     => array(),
				'b'          => array(),
				'em'         => array(),
				'i'          => array(),
				'a'          => array( 'href' => array(), 'title' => array() ),
				'ul'         => array(),
				'ol'         => array(),
				'li'         => array(),
				'blockquote' => array(),
				'h3'         => array(),
				'h4'         => array(),
			)
		)
	);
}

/** One line per member for class review sheets. */
function uh_member_line( $id ) {
	$meta = get_field( 'meta', $id ) ?: array();
	$line = array(
		'id'                 => (int) $id,
		'name'               => html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
		'status'             => get_post_status( $id ),
		'induction_type'     => $meta['induction_type']['label'] ?? null,
		'induction_division' => $meta['induction_division']['label'] ?? null,
		'career_rows'        => count( uh_raw_rows( $id, 'career' ) ),
		'result_rows'        => count( uh_raw_rows( $id, 'results' ) ),
		'service_rows'       => count( uh_raw_rows( $id, 'service' ) ),
		'has_bio'            => '' !== trim( wp_strip_all_tags( (string) uh_get_raw( $id, 'biography' ) ) ),
		'has_headshot'       => (bool) uh_get_raw( $id, 'photos.headshot' ),
		'url'                => get_permalink( $id ),
	);
	$missing = array();
	foreach ( array( 'induction_type', 'induction_division' ) as $k ) {
		if ( ! $line[ $k ] ) {
			$missing[] = $k;
		}
	}
	foreach ( array( 'has_bio' => 'bio', 'has_headshot' => 'headshot' ) as $k => $label ) {
		if ( ! $line[ $k ] ) {
			$missing[] = $label;
		}
	}
	if ( 'player' === ( $meta['induction_type']['value'] ?? '' ) && ! $line['career_rows'] ) {
		$missing[] = 'career';
	}
	$line['missing'] = $missing;
	return $line;
}

/** Check class fields in ability input before anything is written. */
function uh_validate_class_fields( $input ) {
	if ( isset( $input['class_year'] ) && is_wp_error( uh_require_year( $input['class_year'] ) ) ) {
		return uh_require_year( $input['class_year'] );
	}
	foreach ( array( 'induction_type', 'induction_division' ) as $k ) {
		if ( isset( $input[ $k ] ) && is_wp_error( uh_choice_value( $k, $input[ $k ] ) ) ) {
			return uh_choice_value( $k, $input[ $k ] );
		}
	}
	return true;
}

/** Set class fields from ability input (validated first); returns the list of fields changed or an error. */
function uh_apply_class_fields( $id, $input ) {
	$changed = array();
	if ( isset( $input['class_year'] ) ) {
		$year = uh_require_year( $input['class_year'] );
		if ( is_wp_error( $year ) ) {
			return $year;
		}
		uh_set_raw( $id, 'meta.class', $year );
		$changed[] = 'class';
	}
	foreach ( array( 'induction_type', 'induction_division' ) as $k ) {
		if ( isset( $input[ $k ] ) ) {
			$v = uh_choice_value( $k, $input[ $k ] );
			if ( is_wp_error( $v ) ) {
				return $v;
			}
			uh_set_raw( $id, 'meta.' . $k, $v );
			$changed[] = $k;
		}
	}
	return $changed;
}

/** Find an image in the media library by file name or title. */
function uh_find_media( $query, $limit = 10 ) {
	global $wpdb;
	$like = '%' . $wpdb->esc_like( $query ) . '%';
	$ids  = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_wp_attached_file'
			 WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%%' AND ( p.post_title LIKE %s OR m.meta_value LIKE %s )
			 ORDER BY p.post_date DESC LIMIT %d",
			$like,
			$like,
			$limit
		)
	);
	return array_map(
		fn( $id ) => array(
			'attachment_id' => (int) $id,
			'title'         => get_the_title( $id ),
			'file'          => wp_basename( (string) get_attached_file( $id ) ),
			'url'           => wp_get_attachment_url( $id ),
			'uploaded'      => get_the_date( 'Y-m-d', $id ),
		),
		$ids
	);
}

add_action( 'wp_abilities_api_init', 'uh_register_member_abilities' );

function uh_register_member_abilities() {
	$ro   = array( 'mcp' => array( 'public' => false ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) );
	$add  = array( 'mcp' => array( 'public' => false ), 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ) );
	$edit = array( 'mcp' => array( 'public' => false ), 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => true ) );

	$type_desc     = 'Player, Contributor or Special Merit.';
	$division_desc = 'Open Division, Women\'s Division, Mixed Division, or one of the Look Back or Inaugural Class choices.';

	wp_register_ability(
		'uhof/create-member',
		array(
			'label'               => 'Create member',
			'description'         => 'Start a new member as a draft, invisible on the site until published with update-member. Refuses when a member with a similar name already exists (drafts included) unless confirm_new is true. Then add vitals, career, results, service, bio and photos with the other tools.',
			'category'            => 'uhof',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'first_name'         => array( 'type' => 'string', 'minLength' => 1 ),
					'last_name'          => array( 'type' => 'string', 'minLength' => 1 ),
					'title'              => array( 'type' => 'string', 'description' => 'Page title if not "First Last", e.g. with a nickname.' ),
					'hometown'           => array( 'type' => 'string' ),
					'class_year'         => uh_year_schema( 'Induction class, e.g. 2027.' ),
					'induction_type'     => array( 'type' => 'string', 'description' => $type_desc ),
					'induction_division' => array( 'type' => 'string', 'description' => $division_desc ),
					'confirm_new'        => array( 'type' => 'boolean' ),
				),
				'required'   => array( 'first_name', 'last_name' ),
			),
			'execute_callback'    => function ( $input ) {
				$title = sanitize_text_field( $input['title'] ?? trim( $input['first_name'] . ' ' . $input['last_name'] ) );
				if ( empty( $input['confirm_new'] ) ) {
					$similar = array();
					foreach ( uh_fuzzy_find( 'member', $title, 5, null, uh_editable_statuses() ) as $hit ) {
						if ( $hit[0] >= 85 ) {
							$similar[] = array( 'id' => $hit[1], 'name' => get_the_title( $hit[1] ), 'status' => get_post_status( $hit[1] ), 'score' => $hit[0] );
						}
					}
					if ( $similar ) {
						return new WP_Error( 'uh_similar_exists', 'A member with a similar name already exists. Use that one, or call again with confirm_new: true if this is a different person.', array( 'similar' => $similar ) );
					}
				}
				$valid = uh_validate_class_fields( $input );
				if ( is_wp_error( $valid ) ) {
					return $valid;
				}
				$id = wp_insert_post( array( 'post_type' => 'member', 'post_status' => 'draft', 'post_title' => $title ), true );
				if ( is_wp_error( $id ) ) {
					return $id;
				}
				update_field(
					'vitals',
					array(
						'first_name' => sanitize_text_field( $input['first_name'] ),
						'last_name'  => sanitize_text_field( $input['last_name'] ),
						'hometown'   => sanitize_text_field( (string) ( $input['hometown'] ?? '' ) ),
					),
					$id
				);
				uh_apply_class_fields( $id, $input );
				$change = uh_log_change( $id, 'create-member', 'create', 'Created member ' . $title . ' (draft)', null );
				uh_after_member_edit( $id );
				return array( 'change_id' => $change, 'member' => uh_member_detail( $id ) );
			},
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
			'meta'                => $add,
		)
	);

	wp_register_ability(
		'uhof/update-member',
		array(
			'label'               => 'Update member',
			'description'         => 'Change a member\'s page title (renames the page and its web address; the old address redirects), induction class, induction type, induction division, or status: publish to put it live, draft to take it down. Only the fields you pass change.',
			'category'            => 'uhof',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'member_id'          => array( 'type' => 'integer' ),
					'title'              => array( 'type' => 'string', 'minLength' => 2 ),
					'class_year'         => uh_year_schema( 'Induction class, e.g. 2027.' ),
					'induction_type'     => array( 'type' => 'string', 'description' => $type_desc ),
					'induction_division' => array( 'type' => 'string', 'description' => $division_desc ),
					'status'             => array( 'type' => 'string', 'enum' => array( 'publish', 'draft' ) ),
				),
				'required'   => array( 'member_id' ),
			),
			'execute_callback'    => function ( $input ) {
				$id = uh_require_post( $input['member_id'], 'member' );
				if ( is_wp_error( $id ) ) {
					return $id;
				}
				$valid = uh_validate_class_fields( $input );
				if ( is_wp_error( $valid ) ) {
					return $valid;
				}
				$before = uh_snapshot( $id, array( 'post_title', 'post_name', 'post_status' ), array( 'meta.class', 'meta.induction_type', 'meta.induction_division' ) );
				$post   = array();
				if ( isset( $input['title'] ) ) {
					$post['post_title'] = sanitize_text_field( $input['title'] );
					$post['post_name']  = sanitize_title( $input['title'] );
				}
				if ( isset( $input['status'] ) ) {
					if ( 'publish' === $input['status'] && ! current_user_can( get_post_type_object( 'member' )->cap->publish_posts ) ) {
						return new WP_Error( 'uh_cant_publish', 'Your account can\'t publish members.' );
					}
					$post['post_status'] = $input['status'];
				}
				$changed = uh_apply_class_fields( $id, $input );
				if ( is_wp_error( $changed ) ) {
					return $changed;
				}
				if ( $post ) {
					$ok = wp_update_post( array( 'ID' => $id ) + $post, true );
					if ( is_wp_error( $ok ) ) {
						return $ok;
					}
				}
				$what   = array_merge( array_map( fn( $k ) => str_replace( 'post_', '', $k ), array_keys( $post ) ), $changed );
				$change = uh_log_change( $id, 'update-member', 'fields', 'Updated ' . implode( ', ', array_unique( $what ) ), $before );
				uh_after_member_edit( $id );
				$detail = uh_member_detail( $id );
				return array( 'change_id' => $change ) + array_intersect_key( $detail, array_flip( array( 'id', 'name', 'status', 'url', 'class', 'induction_type', 'induction_division' ) ) );
			},
			'permission_callback' => 'uh_can_edit_member',
			'meta'                => $edit,
		)
	);

	wp_register_ability(
		'uhof/list-class',
		array(
			'label'               => 'List a class',
			'description'         => 'Everyone in one induction class, drafts included, with status and what each is still missing (bio, headshot, career, induction fields). Use it as the review sheet before publishing a class.',
			'category'            => 'uhof',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array( 'class_year' => uh_year_schema( 'Induction class, e.g. 2027.' ) ),
				'required'   => array( 'class_year' ),
			),
			'execute_callback'    => function ( $input ) {
				$year = uh_require_year( $input['class_year'] );
				if ( is_wp_error( $year ) ) {
					return $year;
				}
				$q = bearsmith_get_members_by_class( $year, array( 'post_status' => uh_editable_statuses(), 'fields' => 'ids' ) );
				return array(
					'class'   => (int) $input['class_year'],
					'url'     => get_permalink( $year ),
					'members' => array_map( 'uh_member_line', $q->posts ),
				);
			},
			'permission_callback' => 'uh_can_read',
			'meta'                => $ro,
		)
	);

	wp_register_ability(
		'uhof/set-bio',
		array(
			'label'               => 'Set biography',
			'description'         => 'Replace a member\'s biography. Plain text with blank lines between paragraphs works; simple HTML (paragraphs, bold, italics, links, lists) is kept and anything else is removed. Read the current bio with get-member first when editing rather than replacing it.',
			'category'            => 'uhof',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'member_id' => array( 'type' => 'integer' ),
					'biography' => array( 'type' => 'string' ),
				),
				'required'   => array( 'member_id', 'biography' ),
			),
			'execute_callback'    => function ( $input ) {
				$id = uh_require_post( $input['member_id'], 'member' );
				if ( is_wp_error( $id ) ) {
					return $id;
				}
				$before = uh_snapshot( $id, array(), array( 'biography' ) );
				$bio    = uh_clean_bio( $input['biography'] );
				$ok     = uh_set_raw( $id, 'biography', $bio );
				if ( is_wp_error( $ok ) ) {
					return $ok;
				}
				$change = uh_log_change( $id, 'set-bio', 'fields', 'Set biography (' . str_word_count( wp_strip_all_tags( $bio ) ) . ' words)', $before );
				uh_after_member_edit( $id );
				return array( 'change_id' => $change, 'biography' => $bio );
			},
			'permission_callback' => 'uh_can_edit_member',
			'meta'                => $edit,
		)
	);

	wp_register_ability(
		'uhof/find-media',
		array(
			'label'               => 'Find media',
			'description'         => 'Find images already in the media library by file name or title, newest first. Use it to pick a photo someone uploaded in wp-admin for set-photo.',
			'category'            => 'uhof',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array( 'query' => array( 'type' => 'string', 'minLength' => 2 ) ),
				'required'   => array( 'query' ),
			),
			'execute_callback'    => fn( $input ) => array( 'images' => uh_find_media( $input['query'] ) ),
			'permission_callback' => fn() => current_user_can( 'upload_files' ),
			'meta'                => $ro,
		)
	);

	wp_register_ability(
		'uhof/set-photo',
		array(
			'label'               => 'Set photo',
			'description'         => 'Set or clear a member\'s headshot (member cards and profile) or player card (beside the bio). Give an attachment_id from find-media, or an https url of a JPEG, PNG, GIF or WebP image to upload. Pass clear: true to remove it.',
			'category'            => 'uhof',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'member_id'     => array( 'type' => 'integer' ),
					'slot'          => array( 'type' => 'string', 'enum' => array( 'headshot', 'player_card' ) ),
					'attachment_id' => array( 'type' => 'integer' ),
					'url'           => array( 'type' => 'string', 'pattern' => '^https://' ),
					'clear'         => array( 'type' => 'boolean' ),
				),
				'required'   => array( 'member_id', 'slot' ),
			),
			'execute_callback'    => function ( $input ) {
				$id = uh_require_post( $input['member_id'], 'member' );
				if ( is_wp_error( $id ) ) {
					return $id;
				}
				$path = 'photos.' . $input['slot'];
				if ( ! empty( $input['clear'] ) ) {
					$image = '';
				} elseif ( ! empty( $input['attachment_id'] ) ) {
					$image = (int) $input['attachment_id'];
					if ( 'attachment' !== get_post_type( $image ) || ! wp_attachment_is_image( $image ) ) {
						return new WP_Error( 'uh_not_image', 'That attachment isn\'t an image in the media library.' );
					}
				} elseif ( ! empty( $input['url'] ) ) {
					if ( ! preg_match( '/\.(jpe?g|png|gif|webp)(\?.*)?$/i', $input['url'] ) ) {
						return new WP_Error( 'uh_bad_url', 'The url must point straight at a .jpg, .png, .gif or .webp file.' );
					}
					require_once ABSPATH . 'wp-admin/includes/media.php';
					require_once ABSPATH . 'wp-admin/includes/file.php';
					require_once ABSPATH . 'wp-admin/includes/image.php';
					$image = media_sideload_image( esc_url_raw( $input['url'] ), $id, get_the_title( $id ), 'id' );
					if ( is_wp_error( $image ) ) {
						return $image;
					}
					update_post_meta( $image, '_wp_attachment_image_alt', get_the_title( $id ) );
				} else {
					return new WP_Error( 'uh_no_image', 'Give attachment_id, url or clear.' );
				}
				$before = uh_snapshot( $id, array(), array( $path ) );
				uh_set_raw( $id, $path, $image );
				$change = uh_log_change( $id, 'set-photo', 'fields', ( $image ? 'Set ' : 'Cleared ' ) . str_replace( '_', ' ', $input['slot'] ), $before );
				uh_after_member_edit( $id );
				return array( 'change_id' => $change, $input['slot'] => $image ? array( 'attachment_id' => $image, 'url' => wp_get_attachment_url( $image ) ) : null );
			},
			'permission_callback' => fn( $input ) => uh_can_edit_member( $input ) && current_user_can( 'upload_files' ),
			'meta'                => $edit,
		)
	);

	wp_register_ability(
		'uhof/add-award',
		array(
			'label'               => 'Add award',
			'description'         => 'Add one award to a member, with the year it was given (the year page must exist).',
			'category'            => 'uhof',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'member_id' => array( 'type' => 'integer' ),
					'year'      => uh_year_schema( 'Year of the award.' ),
					'award'     => array( 'type' => 'string', 'minLength' => 2 ),
				),
				'required'   => array( 'member_id', 'year', 'award' ),
			),
			'execute_callback'    => function ( $input ) {
				$id   = uh_require_post( $input['member_id'], 'member' );
				$year = uh_require_year( $input['year'] );
				if ( is_wp_error( $id ) || is_wp_error( $year ) ) {
					return is_wp_error( $id ) ? $id : $year;
				}
				$rows = uh_raw_rows( $id, 'awards' );
				foreach ( $rows as $r ) {
					if ( (int) $r['year'] === $year && uh_normalize_name( $r['award'] ) === uh_normalize_name( $input['award'] ) ) {
						return new WP_Error( 'uh_duplicate', 'This member already has that award for that year.' );
					}
				}
				$rows[] = array( 'year' => $year, 'award' => $input['award'] );
				$change = uh_write_rows( $id, 'awards', $rows, 'add-award', sprintf( 'Added award: %d %s', $input['year'], $input['award'] ) );
				if ( is_wp_error( $change ) ) {
					return $change;
				}
				return array( 'change_id' => $change, 'row' => count( $rows ) - 1, 'list_version' => uh_list_version( $id, 'awards' ) );
			},
			'permission_callback' => 'uh_can_edit_member',
			'meta'                => $add,
		)
	);

	wp_register_ability(
		'uhof/create-year',
		array(
			'label'               => 'Create year',
			'description'         => 'Create the page for a year, which tournaments and induction classes hang off. For a new induction class, pass induction_class: true to also list it on the Members page (it shows publicly once a published member is in it). With induction_class: true on a year that already exists, it only adds it to the Members page list.',
			'category'            => 'uhof',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'year'            => uh_year_schema( 'The year, e.g. 2027.' ),
					'induction_class' => array( 'type' => 'boolean' ),
				),
				'required'   => array( 'year' ),
			),
			'execute_callback'    => function ( $input ) {
				$year     = (int) $input['year'];
				$existing = uh_year_post( $year );
				$classes  = array_map( 'intval', (array) ( get_field( 'classes', 'option', false ) ?: array() ) );
				if ( $existing && ( empty( $input['induction_class'] ) || in_array( $existing, $classes, true ) ) ) {
					return new WP_Error( 'uh_exists', "The {$year} page already exists" . ( in_array( $existing, $classes, true ) ? ' and is on the Members page.' : '.' ), array( 'id' => $existing ) );
				}
				$id = $existing ?: wp_insert_post( array( 'post_type' => 'year', 'post_status' => 'publish', 'post_title' => (string) $year ), true );
				if ( is_wp_error( $id ) ) {
					return $id;
				}
				$before = array();
				if ( ! empty( $input['induction_class'] ) ) {
					$before['options_classes'] = $classes;
					// Keep the list in the order it already runs (newest first, as the site has it, or oldest first).
					$desc = count( $classes ) < 2 || (int) get_the_title( $classes[0] ) >= (int) get_the_title( end( $classes ) );
					$all  = array_merge( $classes, array( $id ) );
					usort( $all, fn( $a, $b ) => $desc ? (int) get_the_title( $b ) <=> (int) get_the_title( $a ) : (int) get_the_title( $a ) <=> (int) get_the_title( $b ) );
					update_field( 'classes', $all, 'option' );
				}
				// Undo of a list-only change restores the Members page list and keeps the page.
				$change = $existing
					? uh_log_change( $id, 'create-year', 'create', "Added {$year} to the Members page class list", $before + array( 'keep_post' => true ) )
					: uh_log_change( $id, 'create-year', 'create', "Created year {$year}" . ( $before ? ' as an induction class' : '' ), $before );
				uh_after_edit( $id );
				return array( 'change_id' => $change, 'id' => $id, 'year' => $year, 'url' => get_permalink( $id ), 'induction_class' => ! empty( $input['induction_class'] ) );
			},
			'permission_callback' => fn() => current_user_can( 'publish_posts' ),
			'meta'                => $add,
		)
	);
}
