<?php

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

function applicants_info()
{
    return array(
        "name"            => "Bewerberübersicht",
        "description"    => "Gibt automatisch an wie lange ein Bewerber noch Zeit für den Steckbrief hat",
        "author"        => "aheartforspinach",
        "authorsite"    => "https://storming-gates.de/member.php?action=profile&uid=176",
        "version"        => "1.0",
        "compatibility" => "18*"
    );
}



function applicants_install()
{
    global $db, $cache, $mybb;

    if ($db->engine == 'mysql' || $db->engine == 'mysqli') {
        $db->query("CREATE TABLE `" . TABLE_PREFIX . "applicants` (
        `uid` int(11) unsigned NOT NULL,
        `username` VARCHAR(264),
        `email` VARCHAR(50),
        `corrector` VARCHAR(25),
        `expirationDate` date,
        `extensionCtr` int(10) DEFAULT 0,
        PRIMARY KEY (`uid`)
        ) ENGINE=MyISAM" . $db->build_create_table_collation());
    }

    //Einstellungen 
    $setting_group = array(
        'name' => 'applicants',
        'title' => 'Bewerberübersicht',
        'description' => 'Einstellungen für das Bewerber-Plugin',
        'isdefault' => 0
    );
    $gid = $db->insert_query("settinggroups", $setting_group);

    $setting_array = array(
        'applicants_time' => array(
            'title' => 'Zeit für die Bewerbung',
            'description' => 'Wie lange haben Bewerber Zeit die Bewerbung fertig zu schreiben? WICHTIG: in Tagen angeben',
            'optionscode' => 'text',
            'value' => '14', // Default
            'disporder' => 1
        ),
        'applicants_extendTimes' => array(
            'title' => 'Verlängerungszeitraum',
            'description' => 'Um wie viele Tage kann der User seine Frist verlängern? WICHTIG: in Tagen angeben',
            'optionscode' => 'text',
            'value' => '14', // Default
            'disporder' => 2
        ),
        'applicants_extend' => array(
            'title' => 'Verlängerung',
            'description' => 'Wie oft dürfen User ihre Frist verlängern?',
            'optionscode' => 'text',
            'value' => '1', // Default
            'disporder' => 3
        ),
        'applicants_alert' => array(
            'title' => 'Benachrichtigung',
            'description' => 'Wie viele Tage vor Ablauf sollen Bewerber eine Benachrichtigung bekommen? (Ab diesem Zeitpunkt darf man auch die Frist verlängern)',
            'optionscode' => 'text',
            'value' => '5', // Default
            'disporder' => 4
        ),
        'applicants_teamaccount' => array(
            'title' => 'Teamaccount',
            'description' => 'Gib die UID vom Account an, der die Steckivorlage gepostet hat, um sein Thema unkorrigierbar zu machen. 0 falls nicht gebraucht wird.',
            'optionscode' => 'text',
            'value' => '0', // Default
            'disporder' => 5
        ),
        'applicants_fid' => array(
            'title' => 'FID des Bewerbungsforums',
            'description' => 'Wie lautet die FID des Forums, wo User ihre fertigen Steckbriefe posten?',
            'optionscode' => 'text',
            'value' => '0', // Default
            'disporder' => 6
        ),
        'applicants_gid' => array(
            'title' => 'Bewerbergruppe',
            'description' => 'Wie lautet die Gruppen-Id von Bewerbern?',
            'optionscode' => 'text',
            'value' => '2', // Default
            'disporder' => 7
        ),
        'applicants_player' => array(
            'title' => 'Spielerprofilfeld',
            'description' => 'Gib die FID vom Profilfeld an, wo User ihren Spielernamen angeben.',
            'optionscode' => 'text',
            'value' => '0', // Default
            'disporder' => 8
        ),
        'applicants_pmAlert' => array(
            'title' => 'PN-Benachrichtigung',
            'description' => 'Sollen Bewerber per PN benachrichtigt werden, wenn der Steckbrief übernommen wurde?',
            'optionscode' => 'yesno',
            'value' => 1, // Default
            'disporder' => 9
        ),
        'applicants_pmText' => array(
            'title' => 'PN-Text',
            'description' => 'Der Standardtext, welcher versendet wird, wenn einer den Steckbief übernimmt, sofern es erlaubt ist. Das ; trennt PN-Titel und PN-Text. Zeilenumbrüche müssen mit <"br"> (ohne ") erfolgen',
            'optionscode' => 'textarea',
            'value' => 'Ich hab deinen Steckbrief übernommen!;Hey, ich habe deinen Steckbrief übernommen und werde ihn so bald wie möglich korrigieren!', // Default
            'disporder' => 10
        ),
    );

    foreach ($setting_array as $name => $setting) {
        $setting['name'] = $name;
        $setting['gid'] = $gid;

        $db->insert_query('settings', $setting);
    }

    //Template applicant bauen
    $insert_array = array(
        'title'        => 'applicants',
        'template'    => $db->escape_string('<html xml:lang="de" lang="de" xmlns="http://www.w3.org/1999/xhtml">

        <head>
            <title>{$mybb->settings[\'bbname\']} - Bewerberfristen</title>
            {$headerinclude}
        </head>
        
        <body>
            {$header}
            <div class="panel" id="panel">
                <div id="panel">$menu</div>
                <h1>Bewerberfristen</h1>
				<blockquote>Hier ist eine kleine Übersicht darüber wer für seine Bewerbung wie lange noch Zeit hat. Zudem könnt ihr hier eure eigene Frist verlängern, falls euch die Zeit nicht reichen sollte. Falls ihr nach der Verlängerung immer noch ein wenig Zeit braucht, wendet euch bitte einfach an das Team :)<br><br>
				<table width="100%">
					<tr>
						<td class="thead" width="33%">
							Charaktername
						</td>
						<td class="thead" width="33%">
							verbleibene Tage
						</td>
						<td class="thead" width="33%">
							Korrektur
						</td>
					</tr>
					{$applicants}
				</table>
					</blockquote>
            </div>
            {$footer}
        </body>
        
        </html>
        <script>
	$(\'.extend\').click(function () {
        if (confirm("Möchtest du deine Frist verlängern?")) {
            $.post("applicants.php", {
                id: this.id,
                action: \'extend\'
            })
			.done(function() {
         		location.reload(); 
        	})
        }
    });

   $(\'.correction\').click(function () {
    if (confirm("Möchtest du den Steckbrief übernehmen?")) {
        $.post("applicants.php", {
            id: this.id,
            action: \'correction\'
        })
        .done(function() {
         location.reload(); 
        })
    }
});
</script>'),
        'sid'        => '-1',
        'version'    => '',
        'dateline'    => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    //Template applicantUser bauen
    $insert_array = array(
        'title'        => 'applicantsUser',
        'template'    => $db->escape_string('<tr style="text-align:center;">
        <td>
            {$username} &nbsp; {$buttonExtend}
        </td>
        <td>
            {$deadlineText}
        </td>
        <td>
            {$corrector} {$correctionButton}
        </td>
    </tr>'),
        'sid'        => '-1',
        'version'    => '',
        'dateline'    => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    //Template applicantHeader bauen
    $insert_array = array(
        'title'        => 'applicantsHeader',
        'template'    => $db->escape_string('<div class="red_alert">Deine <a href="/applicants.php">Bewerbungsfrist</a> für {$applicant} {$deadlineText}</div>'),
        'sid'        => '-1',
        'version'    => '',
        'dateline'    => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    //Template applicantHeaderTeam bauen
    $insert_array = array(
        'title'        => 'applicantHeaderTeam',
        'template'    => $db->escape_string('<div class="red_alert">{$deadlineText} ausgelaufen.</div>'),
        'sid'        => '-1',
        'version'    => '',
        'dateline'    => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    rebuild_settings();
}

function applicants_is_installed()
{
    global $db, $mybb;
    if ($db->table_exists("applicants")) {
        return true;
    }
    return false;
}

function applicants_uninstall()
{
    global $db;
    $db->delete_query('settings', "name IN('applicants_time', 'applicants_fid', 'applicants_alert', 'applicants_teamaccount', 'applicants_extend','applicants_gid', 'applicants_player', 'applicants_pmAlert', 'applicants_pmText')");
    $db->delete_query('settinggroups', "name = 'applicants'");
    $db->delete_query("templates", "title IN('applicants', 'applicantsUser', 'applicantsHeader', 'applicantsUserTeam')");
    if ($db->table_exists("applicants")) {
        $db->drop_table("applicants");
    }
    rebuild_settings();
}

function applicants_activate()
{
    global $db, $mybb;
    include MYBB_ROOT . "/inc/adminfunctions_templates.php";
    find_replace_templatesets("header", "#" . preg_quote('{$awaitingusers}') . "#i", '{$awaitingusers} {$header_applicants}');
    find_replace_templatesets("forumdisplay_thread", "#" . preg_quote('{$thread[\'multipage\']}') . "#i", '{$thread[\'multipage\']} {$correctionButton}');
    find_replace_templatesets("forumdisplay_thread", "#" . preg_quote('<div class="author smalltext">') . "#i", '{$corrector} <div class="author smalltext">');
    find_replace_templatesets("checklist", "#" . preg_quote('{$checklist_check}') . "#i", '<?php
    $query = $db->simple_select("applicants", "expirationDate", "uid = ". $mybb->user[\'uid\']);
    while($date = $db->fetch_array($query)){
        $date = new DateTime($date[\'expirationDate\']);
           echo \'<tr><td colspan="2" class="tcat">Deine Bewerbungsfrist endet am \'. $date->format(\'d.m.Y\') .\'</td></tr>\';	
    } ?> {$checklist_check}');
}

function applicants_deactivate()
{
    global $db, $mybb;
    include MYBB_ROOT . "/inc/adminfunctions_templates.php";
    find_replace_templatesets("header", "#" . preg_quote('{$header_applicants}') . "#i", '', 0);
    find_replace_templatesets("forumdisplay_thread", "#" . preg_quote('{$correctionButton}') . "#i", '', 0);
    find_replace_templatesets("forumdisplay_thread", "#" . preg_quote('{$corrector}') . "#i", '', 0);
    find_replace_templatesets("checklist", "#" . preg_quote('<?php
    $query = $db->simple_select("applicants", "expirationDate", "uid = ". $mybb->user[\'uid\']);
			while($date = $db->fetch_array($query)){
				$date = new DateTime($date[\'expirationDate\']);
   				echo \'<tr><td colspan="2" class="tcat">Deine Bewerbungsfrist endet am \'. $date->format(\'d.m.Y\') .\'</td></tr>\';	
			} ?>') . "#i", '', 0);
}

$plugins->add_hook('forumdisplay_thread', 'applicants_forumdisplay_thread');
function applicants_forumdisplay_thread()
{
    global $thread, $mybb, $correctionButton, $db, $corrector;
    $applicationFid = intval($mybb->settings['applicants_fid']);
    $teamaccount = intval($mybb->settings['applicants_teamaccount']);
    $correctionButton = '';
    $corrector = '';
    $applicantUid = $thread['uid'];
    //einfügen vom Button zur Korrekturübernahme
    if ($mybb->input['fid'] == $applicationFid) { //nur wenn Bewerbungsbereich ausführen
        $correctors = $db->fetch_array($db->simple_select("applicants", "corrector", "uid = '$applicantUid'"));
        if ($correctors['corrector'] != null) {
            $corrector = '<div><b>Korrigiert: </b>' . $correctors['corrector'] . '</div>';
        }
        if ($mybb->usergroup['canmodcp'] == 1 && $thread['uid'] != $teamaccount && $corrector == '') {
            $correctionButton = ' <a href="applicants.php?applicantId=' . $applicantUid . '"><i class="fas fa-check" title="Steckbrief übernehmen?"></i></a>';
        }
    }
}

//Benachrichtung bei auslaufender Frist
$plugins->add_hook('global_intermediate', 'applicants_alert');
function applicants_alert()
{
    global $db, $mybb, $templates, $header_applicants;

    $today = new DateTime(date("Y-m-d", time())); //heute
    $timeForApplication = intval($mybb->settings['applicants_time']);
    $alertDays = intval($mybb->settings['applicants_alert']);
    $applicantGid = intval($mybb->settings['applicants_gid']);
    $email = $mybb->user['email'];
    $deadlineDays = "";
    $deadlineText = "";

    //gelöschte User rausschmeißen
    $allDeletedApplicants = $db->query("SELECT uid
    FROM " . TABLE_PREFIX . "applicants
    WHERE uid NOT IN(
    SELECT uid 
    FROM " . TABLE_PREFIX . "users)");

    while ($deletedApplicants = $db->fetch_array($allDeletedApplicants)) {
        $db->delete_query('applicants', 'uid = '. $deletedApplicants['uid']);
    }

    // alte Bewerber rausschmeißen
    $allExApplicants = $db->query("SELECT uid
    FROM " . TABLE_PREFIX . "users
    WHERE usergroup != $applicantGid AND uid IN(
    SELECT uid 
    FROM " . TABLE_PREFIX . "applicants)");

    while ($exApplicant = $db->fetch_array($allExApplicants)) {
        $db->delete_query('applicants', 'uid = '. $exApplicant['uid']);
    }

    // neue Bewerber hinzufügen
    $allApplicants = $db->query("SELECT uid, email, regdate, username
    FROM " . TABLE_PREFIX . "users
    WHERE usergroup = $applicantGid AND uid NOT IN(
        SELECT uid 
        FROM " . TABLE_PREFIX . "applicants
    )");

    while ($applicant = $db->fetch_array($allApplicants)) {
        $applicationDeadline = new DateTime();
        $applicationDeadline->setTimestamp($applicant['regdate']);
        date_add($applicationDeadline, date_interval_create_from_date_string($timeForApplication. 'days'));

        $insertApplicant = array(
            'uid' => $applicant['uid'],
            'username' => $applicant['username'],
            'email' => $applicant['email'],
            'expirationDate' => $applicationDeadline->format('Y-m-d'),
            'extensionCtr' => 0
        );

        $db->insert_query('applicants', $insertApplicant);
    }

    // Meldung zusammenbauen
    $allApplicants = $db->simple_select('applicants', 'username, expirationDate', "email = '". $email ."' AND corrector IS null");
    while ($applicant = $db->fetch_array($allApplicants)) {
        $expiration = new DateTime($applicant['expirationDate']);
        $interval = $expiration->diff($today);
        $deadline = $expiration->format('d.m.Y');
        $deadlineDays = $interval->d;
        $isExpired = false;
        if($expiration->format('Y-m-d') < $today->format('Y-m-d')){
            $isExpired = true;
        }
        $applicant = $applicant['username'];

        if ($isExpired) {
            $deadlineText = "ist <b>abgelaufen</b>.";
        }else if ($deadlineDays == 0) {
            $deadlineText = "läuft <b>heute</b> ab.";
        } else if ($deadlineDays == 1) {
            $deadlineText = "läuft <b>morgen</b> ab.";
        } else if ($deadlineDays <= $alertDays) {
            $deadlineText = "läuft in <b>" . $deadlineDays . " Tagen</b> aus.";
        } else {
            continue;
        }

        eval("\$header_applicants .= \"" . $templates->get("applicantsHeader") . "\";");
    }

    //nur für Teammitglieder: abgelaufene Bewerbungen
    if ($mybb->usergroup['canmodcp'] == 1) {
        $today = date("Y-m-d", time());

        $deadlineAmount = $db->simple_select('applicants', 'COUNT(uid)', "expirationDate = '". $today ."' AND corrector IS null");
        $deadlineUserCount = $db->fetch_array($deadlineAmount)["COUNT(uid)"];
        if ($deadlineUserCount == 1) {
            $deadlineText = 'Es ist ingesamt eine <a href="/applicants.php">Bewerbungsfrist</a>';
        } else if ($deadlineUserCount > 1) {
            $deadlineText = 'Es sind insgesamt ' . $deadlineUserCount . ' <a href="/applicants.php">Bewerbungsfristen</a>';
        } else {
            return;
        }

        eval("\$header_applicants .= \"" . $templates->get("applicantsHeaderTeam") . "\";");
    }
}
