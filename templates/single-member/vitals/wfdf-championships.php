<?php

    $worlds_teams = get_field('wfdf_championships');
    $worlds_first = array();
    $worlds_second = array();

    if($worlds_teams) {
        foreach($worlds_teams as $worlds_team) {
            $tournament = isset($worlds_team['tournament']) ? $worlds_team['tournament'] : null;

            // Normalize to WP_Post object if an ID was stored
            if ($tournament && is_numeric($tournament)) {
                $tournament = get_post((int) $tournament);
            }

            $year = null;
            if ($tournament && is_object($tournament) && !empty($tournament->ID)) {
                $tournament_year = get_field('details_year', $tournament->ID);
                if ($tournament_year) {
                    $year = get_the_title($tournament_year);
                }
            }

            $placement = isset($worlds_team['placement']) ? $worlds_team['placement'] : null;
    
            if ($year && $placement == '1st') {
                array_push($worlds_first, $year);
            }

            if ($year && $placement == '2nd') {
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