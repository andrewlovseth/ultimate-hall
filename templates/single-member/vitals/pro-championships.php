<?php
    bearsmith_global_vars();

    $teams = get_field('pro_seasons');
    $pro_teams = array();

    if($teams) {
        foreach($teams as $team) {
            $division_obj = get_field('division', $team['team']);
            $division = $division_obj[0]->post_name;
    
            if (in_array($division, $GLOBALS['divisions']['professional'])) {
                array_push($pro_teams, $team);
            }
        }
    }

    $pro_team_first = array();
    $pro_team_second = array();

    foreach($pro_teams as $pro_team) {
        $season = $pro_team['season'];
        $season_year = get_field('details_year', $season);
        $year = $season_year->post_title;
        $placement = $pro_team['placement'];

        if ($placement == '1st') {
            array_push($pro_team_first, $year);
        }

        if ($placement == '2nd') {
            array_push($pro_team_second, $year);
        }
    }

    if($pro_team_first || $pro_team_second ): 

?>

    <div class="pro-championships vitals-section">
        <div class="vitals-header">
            <h3>Pro Championships</h3>
        </div>

        <?php if($pro_team_first): ?>
            <div class="championships pro first">
                <p><?php echo count($pro_team_first); ?>x Pro Champion (<?php echo implode(', ', $pro_team_first); ?>)</p>
            </div>
        <?php endif; ?>

        <?php if($pro_team_second): ?>
            <div class="championships pro second">
                <p><?php echo count($pro_team_second); ?>x Pro Runner Up (<?php echo implode(', ', $pro_team_second); ?>)</p>
            </div>
        <?php endif; ?>  
    </div>

<?php endif; ?>