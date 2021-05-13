<?php

    $teams = get_field('playing_career');
    $college = array();
    $club = array();
    $masters = array();

    if($teams) {
        foreach($teams as $team) {
            $division_obj = get_field('division', $team['team']->ID);
            $division = $division_obj[0]->post_name;
            $year = $team['year'];
    
            if (in_array($division, array("college-women", "college-men", "college-mixed"))) {
                array_push($college, $team);
            }
    
            if (in_array($division, array("club-women", "club-men", "club-mixed"))) {
                array_push($club, $team);
            }
    
            if (in_array($division, array("masters-women", "masters-men", "masters-mixed", "grandmasters-women", "grandmasters-men", "grandmasters-mixed", "great-grandmasters-men"))) {
                array_push($masters, $team);
            }
        }
    }

 


    $wfdfs = get_field('wfdf_championships');
    $national_teams = array();
    if($wfdfs) {
        foreach($wfdfs as $wfdf) {
            $needles = array('world-games', 'wugc', 'wcbu');
            $haystack = $wfdf['tournament']->post_name;
            
            foreach($needles as $needle) {
                if(strpos($haystack, $needle)){
                    array_push($national_teams, $wfdf);
                }
            }        
        }
    }

    if($college || $club || $masters || $national_team ):

?>

    <div class="career-information vitals-section">
        <div class="vitals-header">
            <h3>Career Information</h3>
        </div>

        <?php if($college): ?>
            <div class="college division">
                <div class="division-header">
                    <h4>College</h4>
                </div>

                <?php foreach($college as $college_team): ?>
                    
                    <?php
                        $year = $college_team['year'];
                        $team = $college_team['team'];

                        $args = [
                            'year' => $year,
                            'team' => $team
                        ];
                        get_template_part('templates/single-member/vitals/career-entry', null, $args);

                    ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if($club): ?>
            <div class="club division">
                <div class="division-header">
                    <h4>Club</h4>
                </div>

                <?php foreach($club as $club_team): ?>
                    
                    <?php
                        $year = $club_team['year'];
                        $team = $club_team['team'];

                        $args = [
                            'year' => $year,
                            'team' => $team
                        ];
                        get_template_part('templates/single-member/vitals/career-entry', null, $args);

                    ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if($masters): ?>
            <div class="masters division">
                <div class="division-header">
                    <h4>Masters</h4>
                </div>

                <?php foreach($masters as $masters_team): ?>
                    
                    <?php
                        $year = $masters_team['year'];
                        $team = $masters_team['team'];

                        $args = [
                            'year' => $year,
                            'team' => $team
                        ];
                        get_template_part('templates/single-member/vitals/career-entry', null, $args);

                    ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if($national_teams): ?>
            <div class="national-team division">
                <div class="division-header">
                    <h4>National Team</h4>
                </div>

                <?php foreach($national_teams as $national_team): ?>
                    
                    <?php
                        $tournament = $national_team['tournament'];
                        $team = $national_team['team'];
                        $year = get_field('details_year', $tournament->ID);
                        
                        $team_name = $team->post_title;
                        $tournament_name = $tournament->post_title;

                    ?>
                        <div class="entry">
                            <div class="year">
                                <span><?php echo $year->post_title; ?></span>
                            </div>

                            <div class="team">
                                <a href="<?php echo get_permalink($team->ID); ?>"><?php echo $team_name; ?></a>
                                <span class="tournament"><a href="<?php echo get_permalink($tournament->ID); ?>"><?php echo $tournament_name; ?></a></span>
                            </div>
                        </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>