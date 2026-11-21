<?php
/**
 * Pro Championships summary: titles and runner-up finishes in professional leagues.
 */
$first  = array();
$second = array();
foreach ( uh_member_results( get_the_ID(), 'pro' ) as $result ) {
	if ( '1st' === $result['placement'] ) {
		$first[] = $result['year'];
	} elseif ( '2nd' === $result['placement'] ) {
		$second[] = $result['year'];
	}
}

if ( $first || $second ) :
?>

	<div class="pro-championships vitals-section">
		<div class="vitals-header">
			<h3>Pro Championships</h3>
		</div>

		<?php if ( $first ) : ?>
			<div class="championships pro first">
				<p><?php echo count( $first ); ?>x Pro Champion (<?php echo esc_html( implode( ', ', $first ) ); ?>)</p>
			</div>
		<?php endif; ?>

		<?php if ( $second ) : ?>
			<div class="championships pro second">
				<p><?php echo count( $second ); ?>x Pro Runner Up (<?php echo esc_html( implode( ', ', $second ) ); ?>)</p>
			</div>
		<?php endif; ?>
	</div>

<?php endif; ?>
