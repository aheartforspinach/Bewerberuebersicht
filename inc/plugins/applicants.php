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
        "authorsite"    => "https://github.com/aheartforspinach",
        "version"        => "1.1",
        "compatibility" => "18*"
    );
}

function applicants_install()
{
    global $db, $cache, $mybb; 

    if ($db->engine == 'mysql' || $db->engine == 'mysqli') {
        $db->query("CREATE TABLE `" . TABLE_PREFIX . "applicants` (
        `uid` int(11) unsigned NOT NULL,
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
            'optionscode' => 'numeric',
            'value' => '0', // Default
            'disporder' => 1
        ),
        'applicants_extendTimes' => array(
            'title' => 'Verlängerungszeitraum',
            'description' => 'Um wie viele Tage kann der User seine Frist verlängern? WICHTIG: in Tagen angeben',
            'optionscode' => 'numeric',
            'value' => '14', // Default
            'disporder' => 2
        ),
        'applicants_extend' => array(
            'title' => 'Verlängerung',
            'description' => 'Wie oft dürfen User ihre Frist verlängern?',
            'optionscode' => 'numeric',
            'value' => '1', // Default
            'disporder' => 3
        ),
        'applicants_alert' => array(
            'title' => 'Benachrichtigung',
            'description' => 'Wie viele Tage vor Ablauf sollen Bewerber eine Benachrichtigung bekommen? (Ab diesem Zeitpunkt darf man auch die Frist verlängern)',
            'optionscode' => 'numeric',
            'value' => '5', // Default
            'disporder' => 4
        ),
        'applicants_teamaccount' => array(
            'title' => 'Teamaccount',
            'description' => 'Gib die UID vom Account an, der die Steckivorlage gepostet hat, um sein Thema unkorrigierbar zu machen. 0 falls nicht gebraucht wird.',
            'optionscode' => 'numeric',
            'value' => '0', // Default
            'disporder' => 5
        ),
        'applicants_fid' => array(
            'title' => 'FID des Bewerbungsforums',
            'description' => 'Wie lautet die FID des Forums, wo User ihre fertigen Steckbriefe posten?',
            'optionscode' => 'forumselectsingle',
            'value' => '0', // Default
            'disporder' => 6
        ),
        'applicants_gid' => array(
            'title' => 'Bewerbergruppe',
            'description' => 'Wie lautet die Gruppen-Id von Bewerbern?',
            'optionscode' => 'groupselectsingle',
            'value' => '2', // Default
            'disporder' => 7
        ),
        'applicants_player' => array(
            'title' => 'Spielerprofilfeld',
            'description' => 'Gib die FID vom Profilfeld an, wo User ihren Spielernamen angeben.',
            'optionscode' => 'numeric',
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


    // create templates
    $templategroup = array(
        "prefix" => "applicants",
        "title" => $db->escape_string("Bewerberübersicht"),
    );

    $db->insert_query("templategroups", $templategroup);

    //Template applicant bauen
    $insert_array = array(
        'title'        => 'applicants',
        'template'    => $db->escape_string('<html xml:lang="de" lang="de" xmlns="http://www.w3.org/1999/xhtml">
        <head>
            <title>{$mybb->settings[\'bbname\']} - {$lang->applicants_page_title}</title>
            {$headerinclude}
        </head>
        
        <body>
            {$header}
            <div class="panel" id="panel">
                <div id="panel">$menu</div>
                <h1>{$lang->applicants_page_title}</h1>
				<blockquote>{$lang->applicants_page_info}<br><br>
				<table width="100%">
					<tr>
						<td class="thead" width="33%">
                        {$lang->applicants_page_charaname}
						</td>
						<td class="thead" width="33%">
                        {$lang->applicants_page_extension}
						</td>
						<td class="thead" width="33%">
                        {$lang->applicants_page_correction}
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
        'sid'        => '-2',
        'version'    => '',
        'dateline'    => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    //Template applicantUser bauen
    $insert_array = array(
        'title'        => 'applicants_User',
        'template'    => $db->escape_string('<tr style="text-align:center;">
        <td>
            {$username} &nbsp; {$buttonExtend}
        </td>
        <td>
            {$deadlineText}
        </td>
        <td>
            {$corrector} {$correctionButton} {$reverseButton}
        </td>
    </tr>'),
        'sid'        => '-2',
        'version'    => '',
        'dateline'    => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    //Template applicantHeader bauen
    $insert_array = array(
        'title'        => 'applicants_Header',
        'template'    => $db->escape_string('<div class="red_alert">{$bannerText}</div>'),
        'sid'        => '-2',
        'version'    => '',
        'dateline'    => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    //Template applicantsButton bauen
    $insert_array = array(
        'title'        => 'applicants_Button',
        'template'    => $db->escape_string('<a href="applicants.php?applicantId={$applicantUid}"><i class="fas fa-check" title="Steckbrief übernehmen?"></i></a>'),
        'sid'        => '-2',
        'version'    => '',
        'dateline'    => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    //Template applicantsButtonThread bauen
    $insert_array = array(
        'title'        => 'applicants_ButtonThread',
        'template'    => $db->escape_string('<a href="applicants.php?applicantId={$applicantUid}"><i class="fas fa-check" title="Steckbrief übernehmen?"></i></a>'),
        'sid'        => '-2',
        'version'    => '',
        'dateline'    => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    //Template applicantsReversePage bauen
    $insert_array = array(
        'title'        => 'applicants_ReversePage',
        'template'    => $db->escape_string('<html>
        <head>
        <title>{$mybb->settings[\'bbname\']} - {$lang->applicants_submitpage_title}</title>
        {$headerinclude}
        </head>
        <body>
        {$header}
        <form action="applicants.php" method="post">
        <table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
        <tr>
        <td class="thead" colspan="2"><strong>{$lang->applicants_submitpage_title}</strong></td>
        </tr>
            <tr>
                <td colspan="2" class="tcat"><center>{$infoText}</center></td>
            </tr>
            <tr>
                <td width="50%">
                    <center>{$lang->applicants_submitpage_extension}<br>
                    <input type="radio" id="start_yes" name="applicationStart" value="yes">
          <label for="start_yes">{$lang->applicants_submitpage_yes}</label><br>
                        <input type="radio" id="start_no" name="applicationStart" value="no" checked>
          <label for="start_no">{$lang->applicants_submitpage_no}</label><br></center>
                    </td>
                <td width="50%">
                    <center>{$lang->applicants_submitpage_control}<br>
                    <input type="radio" id="control_yes" name="applicationControl" value="yes">
          <label for="control_yes">{$lang->applicants_submitpage_yes}</label><br>
                        <input type="radio" id="control_no" name="applicationControl" value="no" checked>
          <label for="control_no">{$lang->applicants_submitpage_no}</label><br></center>
                </td>
            </tr>
        </table>
        <br />
        <div align="center"><input type="submit" class="button" name="submit" value="{$lang->applicants_submitpage_submit}" /></div>
        <input type="hidden" name="action" value="reverse" />
        <input type="hidden" name="aid" value="{$aid}" />
        </form>
        {$footer}
        </body>
        </html>'),
        'sid'        => '-2',
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
    $db->delete_query("templategroups", 'prefix = "applicants"');
    $db->delete_query("templates", "title like 'applicants%'");
    if ($db->table_exists("applicants")) $db->drop_table("applicants");
    rebuild_settings();
}

function applicants_activate()
{
    global $db, $cache;
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
    find_replace_templatesets("showthread", "#" . preg_quote('{$newreply}') . "#i", '{$correctionButton} {$newreply}');

    if (function_exists('myalerts_is_activated') && myalerts_is_activated()) {
		$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);

		$alertType = new MybbStuff_MyAlerts_Entity_AlertType();
		$alertType->setCanBeUserDisabled(false);
		$alertType->setCode("applicants");
		$alertType->setEnabled(true);

		$alertTypeManager->add($alertType);
	}
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
    find_replace_templatesets("showthread", "#" . preg_quote('{$correctionButton}') . "#i", '', 0);

    if (function_exists('myalerts_is_activated') && myalerts_is_activated()) {
		$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

		$alertTypeManager->deleteByCode('applicants');
	}
}

$plugins->add_hook('forumdisplay_thread', 'applicants_forumdisplay_thread');
function applicants_forumdisplay_thread()
{
    global $thread, $correctionButton, $corrector;
    $array = setCorrectionButton($thread['uid'], 'applicants_Button');
    $correctionButton = $array['correctionButton'];
    $corrector = $array['corrector'];
}

$plugins->add_hook('showthread_start', 'applicants_showthread_start');
function applicants_showthread_start()
{
    global $correctionButton, $thread;
    $array = setCorrectionButton($thread['uid'], 'applicants_ButtonThread');
    $correctionButton = $array['correctionButton'];
}

function setCorrectionButton($applicantUid, $templateName)
{
    global $mybb, $db, $templates, $thread;
    $applicationFid = intval($mybb->settings['applicants_fid']);
    $teamaccount = intval($mybb->settings['applicants_teamaccount']);
    $returnArray = array('correctionButton' => '', 'corrector' => '');
    //einfügen vom Button zur Korrekturübernahme
    if ($thread['fid'] == $applicationFid) { //nur wenn Bewerbungsbereich ausführen
        $correctors = $db->fetch_array($db->simple_select("applicants", "corrector", "uid = '$applicantUid'"));
        if ($correctors['corrector'] != null) {
            $returnArray['corrector'] = '<div><b>Korrigiert: </b>' . $correctors['corrector'] . '</div>';
        }
        if ($mybb->usergroup['canmodcp'] == 1 && $applicantUid != $teamaccount && $returnArray['corrector'] == '') {
            $returnArray['correctionButton'] = eval($templates->render($templateName));
        }
    }
    return $returnArray;
}

//Benachrichtung bei auslaufender Frist
$plugins->add_hook('global_intermediate', 'applicants_alert');
function applicants_alert()
{
    global $db, $mybb, $templates, $header_applicants, $lang;

    $today = new DateTime(date("Y-m-d", time())); //heute
    $alertDays = intval($mybb->settings['applicants_alert']);
    $email = $mybb->user['email'];
    $deadlineDays = "";
    $deadlineText = "";
    $lang->load('applicants');

    updateDatabase();

    // Meldung zusammenbauen
    $allApplicants = $db->simple_select('applicants', 'uid, expirationDate', "email = '" . $email . "' AND corrector IS null");
    while ($applicant = $db->fetch_array($allApplicants)) {
        $expiration = new DateTime($applicant['expirationDate']);
        $interval = $expiration->diff($today);
        $deadline = $expiration->format('d.m.Y');
        $deadlineDays = $interval->d;

        if ($interval->m != 0) {
            continue;
        }

        $isExpired = false;
        if ($expiration->format('Y-m-d') < $today->format('Y-m-d')) {
            $isExpired = true;
        }
        $applicant = get_user($applicant['uid'])['username'];

        if ($isExpired) {
            $bannerText = $lang->sprintf($lang->applicants_banner_user_expired, $applicant);
        } else if ($deadlineDays == 0) {
            $bannerText = $lang->sprintf($lang->applicants_banner_user, $applicant, '<b>heute</b>');
        } else if ($deadlineDays == 1) {
            $bannerText = $lang->sprintf($lang->applicants_banner_user, $applicant, '<b>morgen</b>');
        } else if ($deadlineDays <= $alertDays) {
            $bannerText = $lang->sprintf($lang->applicants_banner_user, $applicant, 'in <b>' . $deadlineDays . ' Tagen</b>');
        } else {
            continue;
        }

        eval("\$header_applicants .= \"" . $templates->get("applicants_Header") . "\";");
    }

    //nur für Teammitglieder: abgelaufene Bewerbungen
    if ($mybb->usergroup['canmodcp'] == 1) {
        $deadlineUserCount = (int) $db->fetch_array($db->simple_select('applicants', 'COUNT(uid)', "expirationDate < '" . $today->format('Y-m-d') . "' AND corrector IS null"))["COUNT(uid)"];
        if ($deadlineUserCount == 1) {
            $bannerText =  $lang->sprintf($lang->applicants_banner_team, 'ist', 'eine', 'Bewerberfrist');
        } else if ($deadlineUserCount > 1) {
            $bannerText = $lang->sprintf($lang->applicants_banner_team, 'sind', $deadlineUserCount, 'Bewerberfristen');
        } else {
            return;
        }

        eval("\$header_applicants .= \"" . $templates->get("applicants_Header") . "\";");
    }
}

function updateDatabase()
{
    global $db, $mybb;
    $applicantGid = intval($mybb->settings['applicants_gid']);
    $timeForApplication = intval($mybb->settings['applicants_time']);

    //gelöschte User rausschmeißen
    $allDeletedApplicants = $db->query("SELECT uid
        FROM " . TABLE_PREFIX . "applicants
        WHERE uid NOT IN(
        SELECT uid 
        FROM " . TABLE_PREFIX . "users)");

    while ($deletedApplicants = $db->fetch_array($allDeletedApplicants)) {
        $db->delete_query('applicants', 'uid = ' . $deletedApplicants['uid']);
    }

    // alte Bewerber rausschmeißen
    $allExApplicants = $db->query("SELECT uid
        FROM " . TABLE_PREFIX . "users
        WHERE usergroup != $applicantGid AND uid IN(
        SELECT uid 
        FROM " . TABLE_PREFIX . "applicants)");

    while ($exApplicant = $db->fetch_array($allExApplicants)) {
        $db->delete_query('applicants', 'uid = ' . $exApplicant['uid']);
    }

    if ($timeForApplication != 0) {
        // neue Bewerber hinzufügen
        $allApplicants = $db->query("SELECT uid, email, regdate
        FROM " . TABLE_PREFIX . "users
        WHERE usergroup = $applicantGid AND uid NOT IN(
            SELECT uid 
            FROM " . TABLE_PREFIX . "applicants
        )");

        while ($applicant = $db->fetch_array($allApplicants)) {
            $applicationDeadline = new DateTime();
            $applicationDeadline->setTimestamp($applicant['regdate']);
            date_add($applicationDeadline, date_interval_create_from_date_string($timeForApplication . 'days'));

            $insertApplicant = array(
                'uid' => $applicant['uid'],
                'email' => $applicant['email'],
                'expirationDate' => $applicationDeadline->format('Y-m-d'),
                'extensionCtr' => 0
            );

            $db->insert_query('applicants', $insertApplicant);
        }
    }
}

$plugins->add_hook("global_start", "applicants_myalerts");
function applicants_myalerts()
{
	global $mybb, $lang;

	if (!$mybb->user['uid']) return;

	if (function_exists('myalerts_is_activated') && myalerts_is_activated()) {
		class Applicants_AlertFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
		{

			public function init()
			{
				
			}

			public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
			{
                return sprintf(
                    'Ich hab die Korrektur deiner Bewerbung übernommen :)',
                    $outputAlert['dateline']
                );
			}

			public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
			{
				return get_profile_link($alert->getFromUserId());
			}
		}

		$formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();
		$formatterManager->registerFormatter(new Applicants_AlertFormatter($mybb, $lang, 'applicants'));
	}
}