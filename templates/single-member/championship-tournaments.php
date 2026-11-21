<?php
$us    = uh_member_results( get_the_ID(), 'us' );
$world = uh_member_results( get_the_ID(), 'world' );
$count = ( $us ? 1 : 0 ) + ( $world ? 1 : 0 );

if ( $count ) :
?>

	<section class="championship-tournaments grid sections-<?php echo $count; ?>">
		<div class="section-header align-center">
			<h3>Championship Tournaments</h3>
		</div>

		<?php get_template_part( 'templates/single-member/championship-tournaments/us-championships', null, array( 'results' => $us ) ); ?>

		<?php get_template_part( 'templates/single-member/championship-tournaments/world-championships', null, array( 'results' => $world ) ); ?>
	</section>

<?php endif; ?>
