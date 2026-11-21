<?php
/**
 * U.S. National Championships summary: titles and runner-up finishes by division group.
 */
$labels = array(
	'college'            => array( 'College', 'college' ),
	'club'               => array( 'Club', 'club' ),
	'masters'            => array( 'Masters', 'masters' ),
	'grandmasters'       => array( 'Grandmasters', 'masters' ),
	'great_grandmasters' => array( 'Great Grandmasters', 'masters' ),
);
$tally = array();
foreach ( uh_member_results( get_the_ID(), 'us' ) as $result ) {
	if ( ! $result['team'] || ! $result['year'] || ! in_array( $result['placement'], array( '1st', '2nd' ), true ) ) {
		continue;
	}
	$group = uh_team_group( $result['team']->ID );
	if ( isset( $labels[ $group ] ) ) {
		$tally[ $result['placement'] ][ $group ][] = $result['year'];
	}
}

if ( $tally ) :
?>

	<div class="us-championships vitals-section">
		<div class="vitals-header">
			<h3>U.S. National Championships</h3>
		</div>

		<?php foreach ( array( '1st' => array( 'first', 'Champion' ), '2nd' => array( 'second', 'Runner Up' ) ) as $place => $meta ) : ?>
			<?php foreach ( $labels as $group => $label ) : if ( empty( $tally[ $place ][ $group ] ) ) { continue; } $years = $tally[ $place ][ $group ]; ?>
				<div class="championships <?php echo esc_attr( $label[1] . ' ' . $meta[0] ); ?>">
					<p><?php echo count( $years ); ?>x U.S. <?php echo esc_html( $label[0] . ' ' . $meta[1] ); ?> (<?php echo esc_html( implode( ', ', $years ) ); ?>)</p>
				</div>
			<?php endforeach; ?>
		<?php endforeach; ?>
	</div>

<?php endif; ?>
