<?php
/**
 * WebP copies of uploaded images.
 *
 * Every JPEG or PNG in the media library can have a WebP copy of each file (the main file and
 * every size) saved next to it as "<file>.webp", e.g. photo-768x513.jpg.webp. Originals are
 * never changed. A copy is kept only when it is smaller than its original.
 *
 * The list of files that have a copy is stored on the attachment (_uh_webp). Wherever WordPress
 * builds an image URL for the front end (src, srcset, images in post content), a file on that
 * list is swapped for its .webp copy, so templates and markup don't change.
 *
 *   New uploads get copies right away.
 *   `wp uh webp` makes copies for existing images (the deploy runs it in the background).
 *   Deleting an attachment deletes its copies.
 *   To switch it off, remove this file's require; the copies are then simply unused.
 */

const UH_WEBP_META    = '_uh_webp';
const UH_WEBP_QUALITY = 80;

/** Files of this attachment that have a WebP copy, as basename => true. */
function uh_webp_files( $attachment_id ) {
	$meta = get_post_meta( (int) $attachment_id, UH_WEBP_META, true );
	return ( is_array( $meta ) && ! empty( $meta['files'] ) ) ? $meta['files'] : array();
}

/** The .webp URL for an attachment file URL, or the URL unchanged if it has no copy. */
function uh_webp_url( $url, $attachment_id ) {
	if ( ! $url || ! $attachment_id ) {
		return $url;
	}
	$files = uh_webp_files( $attachment_id );
	if ( ! $files ) {
		return $url;
	}
	$path = wp_parse_url( $url, PHP_URL_PATH );
	return ( $path && isset( $files[ wp_basename( $path ) ] ) ) ? $url . '.webp' : $url;
}

/** Only swap URLs for visitors: wp-admin, REST and the editing tools keep the originals. */
function uh_webp_active() {
	return ! is_admin() && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && ! ( defined( 'WP_CLI' ) && WP_CLI );
}

add_filter( 'wp_get_attachment_image_src', function ( $image, $attachment_id ) {
	if ( $image && uh_webp_active() ) {
		$image[0] = uh_webp_url( $image[0], $attachment_id );
	}
	return $image;
}, 10, 2 );

add_filter( 'wp_calculate_image_srcset', function ( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
	if ( is_array( $sources ) && uh_webp_active() ) {
		foreach ( $sources as $w => $source ) {
			$sources[ $w ]['url'] = uh_webp_url( $source['url'], $attachment_id );
		}
	}
	return $sources;
}, 10, 5 );

// Images inside post content (bios, pages): srcset goes through the filter above; fix src here.
add_filter( 'wp_content_img_tag', function ( $tag, $context, $attachment_id ) {
	if ( $attachment_id && uh_webp_active() && preg_match( '/ src="([^"]+)"/', $tag, $m ) ) {
		$webp = uh_webp_url( $m[1], $attachment_id );
		if ( $webp !== $m[1] ) {
			$tag = str_replace( $m[0], ' src="' . esc_url( $webp ) . '"', $tag );
		}
	}
	return $tag;
}, 10, 3 );

/**
 * Make WebP copies for one attachment. Returns [made, kept, bytes saved], or null when the
 * attachment isn't a JPEG/PNG or the server can't write WebP.
 *
 * @param array|null $meta Attachment metadata, when the caller has it before it is saved.
 */
function uh_webp_generate( $attachment_id, $meta = null, $force = false ) {
	$attachment_id = (int) $attachment_id;
	$mime          = get_post_mime_type( $attachment_id );
	if ( ! in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) {
		return null;
	}
	if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
		return null;
	}
	if ( null === $meta ) {
		$meta = wp_get_attachment_metadata( $attachment_id );
	}
	if ( empty( $meta['file'] ) ) {
		return null;
	}

	$uploads = wp_get_upload_dir();
	$main    = path_join( $uploads['basedir'], $meta['file'] );
	$dir     = dirname( $main );
	$names   = array( wp_basename( $meta['file'] ) );
	foreach ( (array) ( $meta['sizes'] ?? array() ) as $size ) {
		if ( ! empty( $size['file'] ) && ( empty( $size['mime-type'] ) || $size['mime-type'] === $mime ) ) {
			$names[] = $size['file'];
		}
	}
	$names = array_unique( $names );

	$old  = $force ? array() : uh_webp_files( $attachment_id );
	$kept = array();
	$made = 0;
	$saved = 0;

	foreach ( $names as $name ) {
		$src  = $dir . '/' . $name;
		$dest = $src . '.webp';
		if ( ! file_exists( $src ) || uh_webp_is_palette_png( $src ) ) {
			continue;
		}
		if ( isset( $old[ $name ] ) && file_exists( $dest ) && ! $force ) {
			$kept[ $name ] = true;
			continue;
		}
		$editor = wp_get_image_editor( $src );
		if ( is_wp_error( $editor ) ) {
			continue;
		}
		$editor->set_quality( UH_WEBP_QUALITY );
		$result = $editor->save( $dest, 'image/webp' );
		if ( is_wp_error( $result ) || ! file_exists( $dest ) ) {
			continue;
		}
		$made++;
		$before = filesize( $src );
		$after  = filesize( $dest );
		if ( $after > 0 && $after < $before ) {
			$kept[ $name ] = true;
			$saved        += $before - $after;
		} else {
			wp_delete_file( $dest );
		}
	}

	update_post_meta( $attachment_id, UH_WEBP_META, array( 'v' => 1, 'files' => $kept ) );
	return array( $made, count( $kept ), $saved );
}

/** GD can't write WebP from a palette PNG (it warns and gives up), so those keep their PNG. */
function uh_webp_is_palette_png( $path ) {
	$head = @file_get_contents( $path, false, null, 0, 26 );
	return is_string( $head ) && 26 === strlen( $head ) && "\x89PNG" === substr( $head, 0, 4 ) && 3 === ord( $head[25] );
}

// New uploads (and regenerated sizes): make copies as soon as the sizes exist.
add_filter( 'wp_generate_attachment_metadata', function ( $meta, $attachment_id ) {
	uh_webp_generate( $attachment_id, $meta, true );
	return $meta;
}, 20, 2 );

// Deleting an attachment deletes its copies.
add_action( 'delete_attachment', function ( $attachment_id ) {
	$files = uh_webp_files( $attachment_id );
	$meta  = wp_get_attachment_metadata( $attachment_id );
	if ( ! $files || empty( $meta['file'] ) ) {
		return;
	}
	$dir = dirname( path_join( wp_get_upload_dir()['basedir'], $meta['file'] ) );
	foreach ( array_keys( $files ) as $name ) {
		wp_delete_file( $dir . '/' . $name . '.webp' );
	}
} );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Make WebP copies of media library images that don't have them yet.
	 *
	 * ## OPTIONS
	 * [--id=<id>]
	 * : Only this attachment.
	 *
	 * [--force]
	 * : Remake copies that already exist.
	 */
	WP_CLI::add_command( 'uh webp', function ( $args, $assoc ) {
		if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			WP_CLI::error( 'This server cannot write WebP (needs GD or Imagick with WebP).' );
		}
		$force = ! empty( $assoc['force'] );
		$query = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => array( 'image/jpeg', 'image/png' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'DESC',
		);
		if ( ! empty( $assoc['id'] ) ) {
			$query['post__in'] = array( (int) $assoc['id'] );
		} elseif ( ! $force ) {
			$query['meta_query'] = array( array( 'key' => UH_WEBP_META, 'compare' => 'NOT EXISTS' ) );
		}
		$ids = get_posts( $query );
		$n   = count( $ids );
		$tot = array( 0, 0, 0 );
		foreach ( $ids as $i => $id ) {
			$r = uh_webp_generate( $id, null, $force );
			if ( $r ) {
				$tot[0] += $r[0];
				$tot[1] += $r[1];
				$tot[2] += $r[2];
			}
			if ( 0 === ( $i + 1 ) % 50 ) {
				WP_CLI::log( sprintf( '%d / %d images', $i + 1, $n ) );
				if ( function_exists( 'wp_cache_flush_runtime' ) ) {
					wp_cache_flush_runtime();
				}
			}
		}
		WP_CLI::success( sprintf( '%d images, %d WebP files written, %d kept, %s saved.', $n, $tot[0], $tot[1], size_format( $tot[2] ) ) );
	} );
}
