<?php
$team_name_edits = array( '(Open)', '(Women)', '(Mixed)', '(Masters Women)', '(Beach Masters Women)', '(Beach Masters Mixed)', ' (Men)', ' (Masters)', ' (Grand Masters)' );
$results         = array_filter( $args['results'] ?? array(), fn( $r ) => $r['tournament'] && $r['team'] );

if ( $results ) :
?>

	<div class="world-championships championships-table">
		<div class="table-header">
			<h4>World Championships</h4>
		</div>

		<table>
			<thead>
				<tr>
					<th class="year">Year</th>
					<th class="tournament">Tournament</th>
					<th class="team">Team</th>
					<th class="placement">Placement</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $results as $result ) :
					$t        = $result['tournament'];
					$team     = $result['team'];
					$division = uh_team_division( $team->ID );
					$location = get_field( 'details_location', $t->ID );
					?>
					<tr>
						<td class="year"><?php if ( $result['year'] ) : ?><a href="<?php echo get_permalink( $t ); ?>"><?php echo esc_html( $result['year'] ); ?></a><?php endif; ?></td>
						<td class="tournament"><a href="<?php echo get_permalink( $t ); ?>"><?php echo preg_replace( '/[0-9]+/', '', get_the_title( $t ) ); ?> <span class="location"><?php echo $location; ?></span></a></td>
						<td class="team"><a href="<?php echo get_permalink( $team ); ?>"><?php echo preg_replace( '/[0-9]+/', '', str_replace( $team_name_edits, '', get_the_title( $team ) ) ); ?></a> <span class="division"><?php echo $division ? get_the_title( $division ) : ''; ?></span></td>
						<td class="placement"><?php echo esc_html( $result['placement'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

<?php endif; ?>
