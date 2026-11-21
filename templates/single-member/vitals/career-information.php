<?php
/**
 * Career Information: career stints grouped by the team's division group,
 * plus national-team appearances taken from World results.
 */
$career = uh_member_career( get_the_ID() );

$groups = array(
	'youth'              => array( 'class' => 'youth', 'label' => 'Youth', 'entries' => array() ),
	'college'            => array( 'class' => 'college', 'label' => 'College', 'entries' => array() ),
	'club'               => array( 'class' => 'club', 'label' => 'Club', 'entries' => array() ),
	'masters'            => array( 'class' => 'masters', 'label' => 'Masters', 'entries' => array() ),
	'grandmasters'       => array( 'class' => 'grandmasters', 'label' => 'Grandmasters', 'entries' => array() ),
	'great_grandmasters' => array( 'class' => 'great-grandmasters', 'label' => 'Great Grandmasters', 'entries' => array() ),
);
$professional = array();

foreach ( $career as $stint ) {
	if ( 'professional' === $stint['group'] ) {
		$professional[] = $stint;
	} elseif ( isset( $groups[ $stint['group'] ] ) ) {
		$groups[ $stint['group'] ]['entries'][] = $stint;
	}
}

$national_teams = array();
foreach ( uh_member_results( get_the_ID(), 'world' ) as $result ) {
	if ( $result['tournament'] && $result['team'] && preg_match( '/world-games|wugc|wcbu/', $result['tournament']->post_name ) ) {
		$national_teams[] = $result;
	}
}

$has_groups = array_filter( $groups, fn( $g ) => $g['entries'] );
if ( $has_groups || $national_teams || $professional ) :
?>

	<div class="career-information vitals-section">
		<div class="vitals-header">
			<h3>Career Information</h3>
		</div>

		<?php foreach ( $groups as $group ) : if ( ! $group['entries'] ) { continue; } ?>
			<div class="<?php echo esc_attr( $group['class'] ); ?> division">
				<div class="division-header">
					<h4><?php echo esc_html( $group['label'] ); ?></h4>
				</div>

				<?php foreach ( $group['entries'] as $stint ) : ?>
					<?php get_template_part( 'templates/single-member/vitals/career-entry', null, array( 'year' => $stint['years'], 'team' => $stint['team'] ) ); ?>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>

		<?php if ( $national_teams ) : ?>
			<div class="national-team division">
				<div class="division-header">
					<h4>National Team</h4>
				</div>

				<?php foreach ( $national_teams as $result ) : ?>
					<div class="entry">
						<div class="year">
							<span><?php echo esc_html( $result['year'] ); ?></span>
						</div>

						<div class="team">
							<a href="<?php echo get_permalink( $result['team'] ); ?>"><?php echo $result['team']->post_title; ?></a>
							<span class="tournament"><a href="<?php echo get_permalink( $result['tournament'] ); ?>"><?php echo $result['tournament']->post_title; ?></a></span>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( $professional ) : ?>
			<div class="pro division">
				<div class="division-header">
					<h4>Professional</h4>
				</div>

				<?php foreach ( $professional as $stint ) : $division = uh_team_division( $stint['team']->ID ); ?>
					<div class="entry">
						<div class="year">
							<span><?php echo esc_html( $stint['years'] ); ?></span>
						</div>

						<div class="team">
							<a href="<?php echo get_permalink( $stint['team'] ); ?>"><?php echo $stint['team']->post_title; ?></a>
							<?php if ( $division ) : ?><span class="tournament"><a href="<?php echo get_permalink( $division ); ?>"><?php echo get_the_title( $division ); ?></a></span><?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

<?php endif; ?>
