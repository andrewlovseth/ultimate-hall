<?php
/**
 * World Championships summary: titles and runner-up finishes at World / International tournaments.
 */
$first  = array();
$second = array();
foreach ( uh_member_results( get_the_ID(), 'world' ) as $result ) {
	if ( ! $result['year'] ) {
		continue;
	}
	if ( '1st' === $result['placement'] ) {
		$first[] = $result['year'];
	} elseif ( '2nd' === $result['placement'] ) {
		$second[] = $result['year'];
	}
}

if ( $first || $second ) :
?>

	<div class="world-championships vitals-section">
		<div class="vitals-header">
			<h3>World Championships</h3>
		</div>

		<?php if ( $first ) : ?>
			<div class="championships worlds first">
				<p><?php echo count( $first ); ?>x World Champion (<?php echo esc_html( implode( ', ', $first ) ); ?>)</p>
			</div>
		<?php endif; ?>

		<?php if ( $second ) : ?>
			<div class="championships worlds second">
				<p><?php echo count( $second ); ?>x World Runner Up (<?php echo esc_html( implode( ', ', $second ) ); ?>)</p>
			</div>
		<?php endif; ?>
	</div>

<?php endif; ?>
