<?php
    bearsmith_global_vars();
    
    $teams = get_field('playing_career');
    $youth = array();
    $college = array();
    $club = array();
    $masters = array();
    $masters = array();
    $grandmasters = array();
    $great_grandmasters = array();
    $professional = array();

    if($teams) {
        foreach($teams as $team) {
            if($team['team']) {
                $division_obj = get_field('division', $team['team']->ID);
                if($division_obj) {
                    $division = $division_obj[0]->post_name;

                    if (in_array($division, $GLOBALS['divisions']['youth'])) {
                        array_push($youth, $team);
                    }

                    if (in_array($division, $GLOBALS['divisions']['college'])) {
                        array_push($college, $team);
                    }
            
                    if (in_array($division, $GLOBALS['divisions']['club'])) {
                        array_push($club, $team);
                    }
            
                    if (in_array($division, $GLOBALS['divisions']['masters'])) {
                        array_push($masters, $team);
                    }
        
                    if (in_array($division, $GLOBALS['divisions']['grandmasters'])) {
                        array_push($grandmasters, $team);
                    }
        
                    if (in_array($division, $GLOBALS['divisions']['great_grandmasters'])) {
                        array_push($great_grandmasters, $team);
                    }
        
                    if (in_array($division, $GLOBALS['divisions']['professional'])) {
                        array_push($professional, $team);
                    }
                } 
            }

            
        }
    }
    
    $wfdfs = get_field('wfdf_championships');
    $national_teams = array();
    if($wfdfs) {
        foreach($wfdfs as $wfdf) {
            $needles = array('world-games', 'wugc', 'wcbu');
            $tournament = isset($wfdf['tournament']) ? $wfdf['tournament'] : null;

            // Normalize tournament to a WP_Post object if possible
            if ($tournament && is_numeric($tournament)) {
                $tournament = get_post((int) $tournament);
            }

            if ($tournament && is_object($tournament) && !empty($tournament->post_name)) {
                $haystack = $tournament->post_name;

                foreach($needles as $needle) {
                    if(strpos($haystack, $needle) !== false){
                        // Ensure downstream expects a post object
                        $wfdf['tournament'] = $tournament;
                        array_push($national_teams, $wfdf);
                        break;
                    }
                }
            }
        }
    }

    if($youth || $college || $club || $masters || $national_teams || $professional ):
?>

    <div class="career-information vitals-section">
        <div class="vitals-header">
            <h3>Career Information</h3>
        </div>


        <?php if($youth): ?>
            <div class="youth division">
                <div class="division-header">
                    <h4>Youth</h4>
                </div>

                <?php foreach($youth as $youth_team): ?>
                    
                    <?php
                        $year = $youth_team['year'];
                        $team = $youth_team['team'];

                        $args = [
                            'year' => $year,
                            'team' => $team
                        ];
                        get_template_part('templates/single-member/vitals/career-entry', null, $args);

                    ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

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

        <?php if($grandmasters): ?>
            <div class="grandmasters division">
                <div class="division-header">
                    <h4>Grandmasters</h4>
                </div>

                <?php foreach($grandmasters as $masters_team): ?>
                    
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

        <?php if($great_grandmasters): ?>
            <div class="great-grandmasters division">
                <div class="division-header">
                    <h4>Great Grandmasters</h4>
                </div>

                <?php foreach($great_grandmasters as $masters_team): ?>
                    
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
                                <span><?php echo get_the_title($year); ?></span>
                            </div>

                            <div class="team">
                                <a href="<?php echo get_permalink($team->ID); ?>"><?php echo $team_name; ?></a>
                                <span class="tournament"><a href="<?php echo get_permalink($tournament->ID); ?>"><?php echo $tournament_name; ?></a></span>
                            </div>
                        </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if($professional): ?>
            <div class="pro division">
                <div class="division-header">
                    <h4>Professional</h4>
                </div>

                <?php foreach($professional as $pro_team): ?>
                    
                    <?php
                        $year = $pro_team['year'];
                        $team = $pro_team['team'];
                        $division_obj = get_field('division', $team->ID);
                        $division = $division_obj[0];
                        $team_name = $team->post_title;

                    ?>
                        <div class="entry">
                            <div class="year">
                                <span><?php echo $year; ?></span>
                            </div>

                            <div class="team">
                                <a href="<?php echo get_permalink($team->ID); ?>"><?php echo $team_name; ?></a>
                                <span class="tournament"><a href="<?php echo get_permalink($division->ID); ?>"><?php echo get_the_title($division->ID); ?></a></span>
                            </div>
                        </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>