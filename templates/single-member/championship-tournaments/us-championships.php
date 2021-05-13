<?php
    $team_name_edits = array('(Open)', '(Women)', '(Mixed)', ' - Masters Women', ' - Beach Masters Women', ' - Beach Masters Mixed', ' (Men)', ' (Masters)', ' (Grand Masters)');

    $us_teams = get_field('us_championships');
    if($us_teams):
?>

    <div class="us-championships championships-table">
        <div class="table-header">
            <h4>U.S. National Championships</h4>
        </div>

        <table>
            <thead>
                <tr>
                    <th class="year">Year</th>
                    <th class="team">Team</th>
                    <th class="placement">Placement</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($us_teams as $us_team): ?>
                    <?php
                        $tournament = $us_team['tournament'];
                        $year_obj = get_field('details_year', $tournament);
                        $year = $year_obj->post_title;

                        $team = $us_team['team'];
                        $team_name = get_the_title($team);
                        $team_name = str_replace($team_name_edits, '', $team_name);
                        $division_obj = get_field('division', $team);
                        $division = $division_obj[0]->post_title;

                        $placement = $us_team['placement'];

                        $beach = get_field('details_beach', $tournament);

                    ?>
                    <tr>
                        <td class="year"><a href="<?php echo get_permalink($tournament); ?>"><?php echo $year; ?></a></td>
                        <td class="team">
                            <a href="<?php echo get_permalink($team); ?>"><?php echo $team_name; ?></a>
                            <span class="division"><?php echo $division; ?><?php if($beach == TRUE): ?> (Beach)<?php endif; ?></span></td>
                        <td class="placement"><?php echo $placement; ?></td>
                    </tr>

                <?php endforeach; ?>
            </tbody>
        </table>

    </div>

<?php endif; ?>