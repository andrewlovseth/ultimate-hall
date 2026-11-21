<?php
$team_name_edits = array( '(Open)', '(Women)', '(Mixed)', ' - Masters Women', ' - Beach Masters Women', ' - Beach Masters Mixed', ' (Men)', ' (Masters)', ' (Grand Masters)' );
$results         = $args['results'] ?? array();

if ( $results ) :
?>

	<div class="us-championships championships-table">
		<div class="table-header">
			<h4>U.S. National Championships</h4>
		</div>

		<table>
			<thead>
				<tr>
					<th class="year">Year</th>
					<th class="team">Team</th>
					<th class="placement">Placement</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $results as $result ) : $team = $result['team']; $division = $team ? uh_team_division( $team->ID ) : null; ?>
					<tr>
						<td class="year"><?php if ( $result['tournament'] ) : ?><a href="<?php echo get_permalink( $result['tournament'] ); ?>"><?php echo esc_html( $result['year'] ); ?></a><?php endif; ?></td>
						<td class="team">
							<?php if ( $team ) : ?><a href="<?php echo get_permalink( $team ); ?>"><?php echo str_replace( $team_name_edits, '', get_the_title( $team ) ); ?></a><?php endif; ?>
							<?php if ( $division || $result['beach'] ) : ?><span class="division"><?php echo $division ? get_the_title( $division ) : ''; ?><?php if ( $result['beach'] ) : ?> (Beach)<?php endif; ?></span><?php endif; ?>
						</td>
						<td class="placement"><?php echo esc_html( $result['placement'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

<?php endif; ?>
