<?php
/**
 * SVG uploads, replacing the SVG Support plugin.
 *
 * Only administrators may upload SVGs, and every SVG is rewritten through an allowlist
 * sanitizer before WordPress stores it. Anything the sanitizer cannot parse is refused.
 * Uploads through wp-admin, the REST API and sideloads all pass the same prefilter; XML-RPC SVG uploads are refused.
 */

/** Who may upload SVGs. */
function uh_svg_user_can_upload() {
	return current_user_can( 'manage_options' );
}

add_filter(
	'upload_mimes',
	function ( $mimes ) {
		if ( uh_svg_user_can_upload() ) {
			$mimes['svg'] = 'image/svg+xml';
		} else {
			unset( $mimes['svg'], $mimes['svgz'] );
		}
		return $mimes;
	}
);

/** File-info can report an SVG as text/xml or text/plain; trust the extension once the file parses as SVG. */
add_filter(
	'wp_check_filetype_and_ext',
	function ( $data, $file, $filename ) {
		if ( 'svg' === strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) && uh_svg_user_can_upload() && empty( $data['type'] ) ) {
			$contents = is_readable( $file ) ? file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( false !== uh_svg_sanitize( $contents ) ) {
				$data['ext']             = 'svg';
				$data['type']            = 'image/svg+xml';
				$data['proper_filename'] = $data['proper_filename'] ?? false;
			}
		}
		return $data;
	},
	10,
	3
);

/** Sanitizes an SVG in place before WordPress moves it into uploads. */
function uh_svg_prefilter( $file ) {
	$ext = strtolower( pathinfo( $file['name'] ?? '', PATHINFO_EXTENSION ) );
	if ( 'svgz' === $ext ) {
		$file['error'] = 'Compressed SVG (.svgz) uploads are not allowed.';
		return $file;
	}
	if ( 'svg' !== $ext && 'image/svg+xml' !== ( $file['type'] ?? '' ) ) {
		return $file;
	}
	if ( ! uh_svg_user_can_upload() ) {
		$file['error'] = 'Only administrators can upload SVG files.';
		return $file;
	}
	$contents = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$clean    = false === $contents ? false : uh_svg_sanitize( $contents );
	if ( false === $clean || false === file_put_contents( $file['tmp_name'], $clean ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
		$file['error'] = 'This SVG could not be sanitized, so it was not uploaded.';
	}
	return $file;
}
add_filter( 'wp_handle_upload_prefilter', 'uh_svg_prefilter' );
add_filter( 'wp_handle_sideload_prefilter', 'uh_svg_prefilter' );

/** XML-RPC uploads go through wp_upload_bits(), which has no prefilter and can't rewrite the file, so SVGs are refused there. */
add_filter(
	'wp_upload_bits',
	function ( $upload ) {
		if ( is_array( $upload ) && preg_match( '/\.svgz?$/i', $upload['name'] ?? '' ) ) {
			return 'Upload SVG files through the media library.';
		}
		return $upload;
	}
);

/**
 * Rewrites an SVG keeping only allowlisted SVG-namespace elements and attributes.
 *
 * Drops scripts, event handlers, foreignObject, images, links, external references (href, url(), @import),
 * comments, processing instructions and the DOCTYPE. Style text that could hide an external reference
 * (CSS escapes, @import, url() to anything but #id) is dropped whole rather than rewritten.
 * Refuses files that are not UTF-8, that don't parse, and that declare anything but simple internal entities.
 *
 * @param string $svg Raw SVG markup.
 * @return string|false Clean markup, or false when it is not a usable SVG.
 */
function uh_svg_sanitize( $svg ) {
	if ( ! is_string( $svg ) || '' === trim( $svg ) || false !== strpos( $svg, "\0" ) || ! mb_check_encoding( $svg, 'UTF-8' ) ) {
		return false;
	}
	if ( preg_match( '/<\?xml[^>]*encoding\s*=\s*["\'](?!utf-?8["\'])/i', $svg ) ) {
		return false;
	}

	// Illustrator declares short internal entities (<!ENTITY ns_svg "http://...">). Allow only those:
	// no external, parameter or nested entities, so expansion stays small and nothing is fetched.
	$declared = preg_match_all( '/<!ENTITY/i', $svg );
	if ( $declared ) {
		$safe = preg_match_all( '/<!ENTITY\s+[A-Za-z_][\w.-]*\s+"[^"<&%]{0,200}"\s*>/', $svg );
		if ( $safe !== $declared || $declared > 20 ) {
			return false;
		}
	}

	$elements = array(
		'svg', 'g', 'defs', 'symbol', 'use', 'title', 'desc', 'switch',
		'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
		'text', 'tspan', 'textpath',
		'lineargradient', 'radialgradient', 'stop', 'pattern', 'clippath', 'mask', 'marker',
		'filter', 'feblend', 'fecolormatrix', 'fecomponenttransfer', 'fecomposite', 'feconvolvematrix',
		'fediffuselighting', 'fedisplacementmap', 'fedistantlight', 'fedropshadow', 'feflood',
		'fefunca', 'fefuncb', 'fefuncg', 'fefuncr', 'fegaussianblur', 'femerge', 'femergenode',
		'femorphology', 'feoffset', 'fepointlight', 'fespecularlighting', 'fespotlight', 'fetile', 'feturbulence',
		'style',
	);
	$attributes = array(
		'id', 'class', 'style', 'lang', 'xml:space', 'xmlns', 'xmlns:xlink', 'version', 'role', 'aria-hidden', 'aria-label', 'focusable',
		'width', 'height', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry', 'fx', 'fy', 'fr',
		'd', 'points', 'pathlength', 'viewbox', 'preserveaspectratio', 'transform', 'href', 'xlink:href',
		'fill', 'fill-opacity', 'fill-rule', 'clip-rule', 'clip-path', 'mask', 'filter', 'opacity', 'visibility', 'display',
		'stroke', 'stroke-width', 'stroke-opacity', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset',
		'color', 'stop-color', 'stop-opacity', 'offset', 'gradientunits', 'gradienttransform', 'spreadmethod',
		'patternunits', 'patterncontentunits', 'patterntransform', 'clippathunits', 'maskunits', 'maskcontentunits',
		'markerwidth', 'markerheight', 'markerunits', 'refx', 'refy', 'orient', 'marker-start', 'marker-mid', 'marker-end',
		'font-family', 'font-size', 'font-weight', 'font-style', 'font-variant', 'letter-spacing', 'word-spacing',
		'text-anchor', 'dominant-baseline', 'alignment-baseline', 'baseline-shift', 'text-decoration', 'dx', 'dy', 'rotate', 'textlength', 'lengthadjust', 'startoffset',
		'filterunits', 'primitiveunits', 'in', 'in2', 'result', 'stddeviation', 'mode', 'type', 'values', 'operator',
		'k1', 'k2', 'k3', 'k4', 'scale', 'xchannelselector', 'ychannelselector', 'flood-color', 'flood-opacity',
		'basefrequency', 'numoctaves', 'seed', 'stitchtiles', 'radius', 'dx', 'dy', 'slope', 'intercept', 'amplitude', 'exponent', 'tablevalues',
		'surfacescale', 'diffuseconstant', 'specularconstant', 'specularexponent', 'kernelunitlength', 'lighting-color',
		'azimuth', 'elevation', 'z', 'pointsatx', 'pointsaty', 'pointsatz', 'limitingconeangle', 'order', 'kernelmatrix', 'divisor', 'bias', 'targetx', 'targety', 'edgemode', 'preservealpha',
		'color-interpolation-filters', 'shape-rendering', 'image-rendering', 'vector-effect', 'overflow', 'enable-background', 'data-name',
	);
	$elements   = array_flip( $elements );
	$attributes = array_flip( $attributes );
	$svg_ns     = 'http://www.w3.org/2000/svg';

	$doc      = new DOMDocument();
	$previous = libxml_use_internal_errors( true );
	$loaded   = $doc->loadXML( $svg, LIBXML_NONET | ( $declared ? LIBXML_NOENT : 0 ) );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );
	$root = $loaded ? $doc->documentElement : null;
	if ( ! $root || 'svg' !== $root->localName || $svg_ns !== $root->namespaceURI ) {
		return false;
	}

	// True when CSS could reach outside the file.
	$unsafe_css = function ( $css ) {
		return (bool) preg_match( '/\\\\|@import|url\s*+\(\s*+[\'"]?+\s*+(?!#)|image-set|image\s*\(|cross-fade|element\s*\(|expression\s*\(|javascript:|behavior\s*:|-moz-binding/i', $css );
	};

	$walk = function ( DOMNode $node ) use ( &$walk, $elements, $attributes, $svg_ns, $unsafe_css, $doc ) {
		for ( $i = $node->childNodes->length - 1; $i >= 0; $i-- ) {
			$child = $node->childNodes->item( $i );
			if ( XML_CDATA_SECTION_NODE === $child->nodeType ) {
				// Re-emit as escaped text so nothing survives as markup if the SVG is ever inlined in HTML.
				$text = $doc->createTextNode( $child->nodeValue );
				$node->replaceChild( $text, $child );
				$child = $text;
			}
			if ( XML_TEXT_NODE === $child->nodeType ) {
				continue;
			}
			if ( XML_ELEMENT_NODE !== $child->nodeType || $svg_ns !== $child->namespaceURI || ! isset( $elements[ strtolower( $child->localName ) ] ) ) {
				$node->removeChild( $child ); // Comments, processing instructions, entity references, scripts, foreignObject, images, other namespaces.
				continue;
			}
			for ( $a = $child->attributes->length - 1; $a >= 0; $a-- ) {
				$attr  = $child->attributes->item( $a );
				$name  = strtolower( $attr->nodeName );
				$value = trim( $attr->nodeValue );
				$keep  = isset( $attributes[ $name ] ) && 0 !== strpos( $name, 'on' );
				if ( $keep && ( 'href' === $name || 'xlink:href' === $name ) ) {
					$keep = 0 === strpos( $value, '#' );
				} elseif ( $keep && $unsafe_css( $value ) ) {
					$keep = false;
				}
				if ( ! $keep ) {
					$child->removeAttributeNode( $attr );
				}
			}
			if ( 'style' === strtolower( $child->localName ) ) {
				// Judge the stylesheet as the browser will read it: comments, CDATA and entities can split it into pieces.
				$css = $child->textContent;
				while ( $child->firstChild ) {
					$child->removeChild( $child->firstChild );
				}
				if ( ! $unsafe_css( $css ) ) {
					$child->appendChild( $doc->createTextNode( $css ) );
				}
				continue;
			}
			$walk( $child );
		}
	};

	// Wrap the root so it goes through the same checks as every other element.
	$wrapper = $doc->createElement( 'uh-root' );
	$doc->replaceChild( $wrapper, $root );
	$wrapper->appendChild( $root );
	$walk( $wrapper );
	if ( ! $wrapper->firstChild ) {
		return false;
	}

	return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $doc->saveXML( $wrapper->firstChild );
}

/** Width and height of an SVG from its attributes, falling back to the viewBox. */
function uh_svg_dimensions( $path ) {
	$contents = is_readable( $path ) ? file_get_contents( $path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
	$previous = libxml_use_internal_errors( true );
	$svg      = $contents ? simplexml_load_string( $contents, 'SimpleXMLElement', LIBXML_NONET ) : false;
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );
	if ( ! $svg ) {
		return array( 0, 0 );
	}
	// Percent sizes say nothing about the drawing; use the viewBox instead.
	$width  = false === strpos( (string) $svg['width'], '%' ) ? (float) $svg['width'] : 0;
	$height = false === strpos( (string) $svg['height'], '%' ) ? (float) $svg['height'] : 0;
	if ( ( ! $width || ! $height ) && preg_match( '/^\s*[-\d.]+[\s,]+[-\d.]+[\s,]+([\d.]+)[\s,]+([\d.]+)\s*$/', (string) $svg['viewBox'], $box ) ) {
		$width  = (float) $box[1];
		$height = (float) $box[2];
	}
	return array( (int) round( $width ), (int) round( $height ) );
}

/** Gives SVG attachments a width and height so wp_get_attachment_image() and the media library can size them. */
add_filter(
	'wp_generate_attachment_metadata',
	function ( $metadata, $attachment_id ) {
		if ( 'image/svg+xml' !== get_post_mime_type( $attachment_id ) ) {
			return $metadata;
		}
		list( $width, $height ) = uh_svg_dimensions( get_attached_file( $attachment_id ) );
		return array_merge(
			(array) $metadata,
			array(
				'width'  => $width,
				'height' => $height,
				'file'   => _wp_relative_upload_path( get_attached_file( $attachment_id ) ),
			)
		);
	},
	10,
	2
);

/** Media library grid and modal: show the SVG itself as its preview. */
add_filter(
	'wp_prepare_attachment_for_js',
	function ( $response, $attachment ) {
		if ( 'image/svg+xml' !== $response['mime'] ) {
			return $response;
		}
		$width  = $response['width'] ?? 0;
		$height = $response['height'] ?? 0;
		if ( ! $width || ! $height ) {
			list( $width, $height ) = uh_svg_dimensions( get_attached_file( $attachment->ID ) );
		}
		$full              = array(
			'url'         => $response['url'],
			'width'       => $width,
			'height'      => $height,
			'orientation' => $height > $width ? 'portrait' : 'landscape',
		);
		$response['image'] = array( 'src' => $response['url'], 'width' => $width, 'height' => $height );
		$response['sizes'] = array( 'full' => $full, 'medium' => $full, 'thumbnail' => $full );
		return $response;
	},
	10,
	2
);

/** SVGs scale on their own, so they get no srcset and fall back to their own size when metadata is missing. */
add_filter(
	'wp_get_attachment_image_src',
	function ( $image, $attachment_id ) {
		if ( $image && 'image/svg+xml' === get_post_mime_type( $attachment_id ) && ( empty( $image[1] ) || empty( $image[2] ) ) ) {
			list( $image[1], $image[2] ) = uh_svg_dimensions( get_attached_file( $attachment_id ) );
		}
		return $image;
	},
	10,
	2
);

add_filter(
	'wp_calculate_image_srcset',
	function ( $sources, $size_array, $image_src ) {
		return preg_match( '/\.svg(\?|$)/i', $image_src ) ? false : $sources;
	},
	10,
	3
);

add_action(
	'admin_head',
	function () {
		echo '<style>.media-icon img[src$=".svg"],.attachment-preview img[src$=".svg"],img.attachment-thumbnail[src$=".svg"]{width:100%;height:auto}</style>';
	}
);
