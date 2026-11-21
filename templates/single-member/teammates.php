<?php
/**
 * Teammates: other members who share a U.S. Championships result (same tournament, same team).
 */
$type    = get_field( 'meta_induction_type' );
$results = uh_member_results( get_the_ID(), 'us' );
if ( isset( $type['value'] ) && 'player' === $type['value'] && $results ) :

	$pairs = array();
	foreach ( $results as $r ) {
		if ( $r['tournament'] && $r['team'] ) {
			$pairs[ $r['tournament']->ID . ':' . $r['team']->ID ] = $r['tournament']->ID;
		}
	}

	$teammates = array();
	if ( $pairs ) {
		$candidates = get_posts(
			array(
				'post_type'      => 'member',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post__not_in'   => array( get_the_ID() ),
				'meta_query'     => array( array( 'key' => UH_TOURNAMENT_INDEX, 'value' => array_values( array_unique( $pairs ) ), 'compare' => 'IN' ) ),
			)
		);
		foreach ( $candidates as $member_id ) {
			foreach ( uh_member_results( $member_id, 'us' ) as $r ) {
				if ( $r['tournament'] && $r['team'] && isset( $pairs[ $r['tournament']->ID . ':' . $r['team']->ID ] ) ) {
					$teammates[] = $member_id;
					break;
				}
			}
		}
	}

	$first_name = get_field( 'vitals_first_name' );

	if ( $teammates ) :
		$query = new WP_Query( array( 'post_type' => 'member', 'posts_per_page' => -1, 'post__in' => $teammates ) );
		if ( $query->have_posts() ) : ?>

		<section class="teammates grid">
			<div class="section-header align-center">
				<h3><?php if ( $first_name ) : ?><?php echo $first_name; ?>'s <?php endif; ?>Teammates</h3>
			</div>

			<div class="member-grid">
				<?php while ( $query->have_posts() ) : $query->the_post(); ?>
					<?php get_template_part( 'template-parts/global/member' ); ?>
				<?php endwhile; ?>
			</div>
		</section>

		<?php endif; wp_reset_postdata();
	endif;
endif;
