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
                        $tournament_obj = isset($world_team['tournament']) ? $world_team['tournament'] : null;
                        if ($tournament_obj && is_numeric($tournament_obj)) {
                            $tournament_obj = get_post((int) $tournament_obj);
                        }

                        $team_obj = isset($world_team['team']) ? $world_team['team'] : null;
                        if ($team_obj && is_numeric($team_obj)) {
                            $team_obj = get_post((int) $team_obj);
                        }

                        // Skip row if required objects are missing
                        if (!($tournament_obj && is_object($tournament_obj) && !empty($tournament_obj->ID) &&
                              $team_obj && is_object($team_obj) && !empty($team_obj->ID))) {
                            continue;
                        }

                        $tournament_name = $tournament_obj->post_title;
                        $tournament = preg_replace('/[0-9]+/', '', $tournament_name);
                        $location = get_field('details_location', $tournament_obj->ID);

                        $year = '';
                        $year_obj = get_field('details_year', $tournament_obj->ID);
                        if ($year_obj) {
                            $year = get_the_title($year_obj);
                        }

                        $team = $team_obj->post_title;
                        $team_name = str_replace($team_name_edits, '', $team);
                        $team_name = preg_replace('/[0-9]+/', '', $team_name);

                        $division = '';
                        $division_obj = get_field('division', $team_obj->ID);
                        if ($division_obj && is_array($division_obj) && isset($division_obj[0]) && is_object($division_obj[0])) {
                            $division = $division_obj[0]->post_title;
                        }

                        $placement = isset($world_team['placement']) ? $world_team['placement'] : '';
                    ?>
                    <tr>
                        <td class="year"><?php if ($year) : ?><a href="<?php echo get_permalink($tournament_obj->ID); ?>"><?php echo $year; ?></a><?php else: ?><?php echo $year; ?><?php endif; ?></td>
                        <td class="tournament"><a href="<?php echo get_permalink($tournament_obj->ID); ?>"><?php echo $tournament; ?> <span class="location"><?php echo $location; ?></span></a></td>
                        <td class="team"><a href="<?php echo get_permalink($team_obj->ID); ?>"><?php echo $team_name; ?></a> <span class="division"><?php echo $division; ?></span></td>
                        <td class="placement"><?php echo $placement; ?></td>
                    </tr>

                <?php endforeach; ?>
            </tbody>
        </table>

    </div>

<?php endif; ?>