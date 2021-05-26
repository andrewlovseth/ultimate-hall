<?php

    $worlds_teams = get_field('wfdf_championships');
    $worlds_first = array();
    $worlds_second = array();

    if($worlds_teams) {
        foreach($worlds_teams as $worlds_team) {
            $tournament = $worlds_team['tournament'];
            $tournament_year = get_field('details_year', $tournament->ID);
            $year = get_the_title($tournament_year);
            $placement = $worlds_team['placement'];
    
            if ($placement == '1st') {
                array_push($worlds_first, $year);
            }
    
            if ($placement == '2nd') {
                array_push($worlds_second, $year);
            }
        }
    }
    
    if($worlds_first || $worlds_second): 

?>

    <div class="world-championships vitals-section">
        <div class="vitals-header">
            <h3>World Championships</h3>
        </div>

        <?php if($worlds_first): ?>
            <div class="championships worlds first">
                <p><?php echo count($worlds_first); ?>x World Champion (<?php echo implode(', ', $worlds_first); ?>)</p>
            </div>
        <?php endif; ?>

        <?php if($worlds_second): ?>
            <div class="championships worlds second">
                <p><?php echo count($worlds_second); ?>x World Runner Up (<?php echo implode(', ', $worlds_second); ?>)</p>
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>