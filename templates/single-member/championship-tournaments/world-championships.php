<?php

    $team_name_edits = array('(Open)', '(Women)', '(Mixed)', '(Masters Women)', '(Beach Masters Women)', '(Beach Masters Mixed)', ' (Men)', ' (Masters)', ' (Grand Masters)');

    $world_teams = get_field('wfdf_championships');
    if($world_teams):
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
                <?php foreach($world_teams as $world_team): ?>
                    <?php
                        $tournament_obj = $world_team['tournament'];
                        if($tournament_obj) {
                            $tournament_name = $tournament_obj->post_title;
                            $tournament = preg_replace('/[0-9]+/', '', $tournament_name);
                            $location = get_field('details_location', $tournament_obj->ID);
    
                            $year_obj = get_field('details_year', $tournament_obj->ID);
                            $year = get_the_title($year_obj);
    
                            $team_obj = $world_team['team'];
                            $team = $team_obj->post_title;
                            $team_name = str_replace($team_name_edits, '', $team);
                            $team_name = preg_replace('/[0-9]+/', '', $team_name);
                            $division_obj = get_field('division', $team_obj->ID);
                            $division = $division_obj[0]->post_title;
    
                            $placement = $world_team['placement'];
                        }


                    ?>
                    <tr>
                        <td class="year"><a href="<?php echo get_permalink($tournament_obj->ID); ?>"><?php echo $year; ?></a></td>
                        <td class="tournament"><a href="<?php echo get_permalink($tournament_obj->ID); ?>"><?php echo $tournament; ?> <span class="location"><?php echo $location; ?></span></a></td>
                        <td class="team"><a href="<?php echo get_permalink($team_obj->ID); ?>"><?php echo $team_name; ?></a> <span class="division"><?php echo $division; ?></span></td>
                        <td class="placement"><?php echo $placement; ?></td>
                    </tr>

                <?php endforeach; ?>
            </tbody>
        </table>

    </div>

<?php endif; ?>