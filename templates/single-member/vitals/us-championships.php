<?php
    bearsmith_global_vars();

    $teams = get_field('us_championships');
    $college_teams = array();
    $club_teams = array();
    $masters_teams = array();
    $grandmasters_teams = array();
    $great_grandmasters_teams = array();

    if($teams) {
        foreach($teams as $team) {
            $division_obj = get_field('division', $team['team']);
            if($division_obj) {
                $division = $division_obj[0]->post_name;
    
                if (in_array($division, $GLOBALS['divisions']['college'])) {
                    array_push($college_teams, $team);
                }
        
                if (in_array($division, $GLOBALS['divisions']['club'])) {
                    array_push($club_teams, $team);
                }
        
                if (in_array($division, $GLOBALS['divisions']['masters'])) {
                    array_push($masters_teams, $team);
                }
    
                if (in_array($division, $GLOBALS['divisions']['grandmasters'])) {
                    array_push($grandmasters_teams, $team);
                }
    
                if (in_array($division, $GLOBALS['divisions']['great_grandmasters'])) {
                    array_push($great_grandmasters_teams, $team);
                }
            }
        }
    }
    
    $college_first = array();
    $college_second = array();

    foreach($college_teams as $college_team) {
        $tournament = $college_team['tournament'];
        $tournament_year = get_field('details_year', $tournament);
        $year = get_the_title($tournament_year);
        $placement = $college_team['placement'];

        if ($placement == '1st') {
            array_push($college_first, $year);
        }

        if ($placement == '2nd') {
            array_push($college_second, $year);
        }
    }

    $club_first = array();
    $club_second = array();

    foreach($club_teams as $club_team) {
        $tournament = $club_team['tournament'];
        if($tournament ) {
            $tournament_year = get_field('details_year', $tournament);
            $year = get_the_title($tournament_year);
            $placement = $club_team['placement'];
    
            if ($placement == '1st') {
                array_push($club_first, $year);
            }
    
            if ($placement == '2nd') {
                array_push($club_second, $year);
            }
        }
    }

    $masters_first = array();
    $masters_second = array();

    foreach($masters_teams as $masters_team) {
        $tournament = $masters_team['tournament'];
        $tournament_year = get_field('details_year', $tournament);
        $year = get_the_title($tournament_year);
        $placement = $masters_team['placement'];

        if ($placement == '1st') {
            array_push($masters_first, $year);
        }

        if ($placement == '2nd') {
            array_push($masters_second, $year);
        }
    }


    $grandmasters_first = array();
    $grandmasters_second = array();

    foreach($grandmasters_teams as $masters_team) {
        $tournament = $masters_team['tournament'];
        $tournament_year = get_field('details_year', $tournament);
        $year = get_the_title($tournament_year);
        $placement = $masters_team['placement'];

        if ($placement == '1st') {
            array_push($grandmasters_first, $year);
        }

        if ($placement == '2nd') {
            array_push($grandmasters_second, $year);
        }
    }


    $great_grandmasters_first = array();
    $great_grandmasters_second = array();

    foreach($great_grandmasters_teams as $masters_team) {
        $tournament = $masters_team['tournament'];
        $tournament_year = get_field('details_year', $tournament);
        $year = get_the_title($tournament_year);
        $placement = $masters_team['placement'];

        if ($placement == '1st') {
            array_push($great_grandmasters_first, $year);
        }

        if ($placement == '2nd') {
            array_push($great_grandmasters_second, $year);
        }
    }

    if($college_first || $college_second || $club_first || $club_second || $masters_first || $masters_second || $grandmasters_first || $grandmasters_second || $great_grandmasters_first || $great_grandmasters_second ): 

?>

    <div class="us-championships vitals-section">
        <div class="vitals-header">
            <h3>U.S. National Championships</h3>
        </div>

        <?php if($college_first): ?>
            <div class="championships college first">
                <p><?php echo count($college_first); ?>x U.S. College Champion (<?php echo implode(', ', $college_first); ?>)</p>
            </div>
        <?php endif; ?>

        <?php if($club_first): ?>
            <div class="championships club first">
                <p><?php echo count($club_first); ?>x U.S. Club Champion (<?php echo implode(', ', $club_first); ?>)</p>
            </div>
        <?php endif; ?>

        <?php if($masters_first): ?>
            <div class="championships masters first">
                <p><?php echo count($masters_first); ?>x U.S. Masters Champion (<?php echo implode(', ', $masters_first); ?>)</p>
            </div>
        <?php endif; ?>

        <?php if($grandmasters_first): ?>
            <div class="championships masters first">
                <p><?php echo count($grandmasters_first); ?>x U.S. Grandmasters Champion (<?php echo implode(', ', $grandmasters_first); ?>)</p>
            </div>
        <?php endif; ?>

        <?php if($great_grandmasters_first): ?>
            <div class="championships masters first">
                <p><?php echo count($great_grandmasters_first); ?>x U.S. Great Grandmasters Champion (<?php echo implode(', ', $great_grandmasters_first); ?>)</p>
            </div>
        <?php endif; ?>

        <?php if($college_second): ?>
            <div class="championships college second">
                <p><?php echo count($college_second); ?>x U.S. College Runner Up (<?php echo implode(', ', $college_second); ?>)</p>
            </div>
        <?php endif; ?>

        <?php if($club_second): ?>
            <div class="championships club second">
                <p><?php echo count($club_second); ?>x U.S. Club Runner Up (<?php echo implode(', ', $club_second); ?>)</p>
            </div>
        <?php endif; ?>

        <?php if($masters_second): ?>
            <div class="championships masters second">
                <p><?php echo count($masters_second); ?>x U.S. Masters Runner Up (<?php echo implode(', ', $masters_second); ?>)</p>
            </div>
        <?php endif; ?>

        <?php if($grandmasters_second): ?>
            <div class="championships masters second">
                <p><?php echo count($grandmasters_second); ?>x U.S. Grandmasters Runner Up (<?php echo implode(', ', $grandmasters_second); ?>)</p>
            </div>
        <?php endif; ?>

        <?php if($great_grandmasters_second): ?>
            <div class="championships masters second">
                <p><?php echo count($great_grandmasters_second); ?>x U.S. Great Grandmasters Runner Up (<?php echo implode(', ', $great_grandmasters_second); ?>)</p>
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>