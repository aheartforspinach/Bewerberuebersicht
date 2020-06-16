<?php
define("IN_MYBB", 1);
require_once "global.php";

add_breadcrumb("Bewerberfristen", "applicants.php");
global $db, $templates, $mybb, $lang;
$email = $mybb->user['email'];
$today = new DateTime(date("Y-m-d", time())); //heute

if (!$db->table_exists("applicants")) {
    die("Die Bewerberliste ist zur Zeit nicht installiert.");
}
$lang->load('applicants');

//Einstellungen holen
$alertDays = intval($mybb->settings['applicants_alert']);
$timeForApplication = intval($mybb->settings['applicants_time']);
$applicationFid = intval($mybb->settings['applicants_fid']);
$playerFid = intval($mybb->settings['applicants_player']);
$timesToExtend = intval($mybb->settings['applicants_extend']);
$timeframeExtension = intval($mybb->settings['applicants_extendTimes']);
$pmAlert;
if (intval($mybb->settings['applicants_pmAlert']) == 1) {
    $pmAlert = true;
} else {
    $pmAlert = false;
}

//------------- Bestätigungsseite ---------------------------
if ($mybb->input['action'] == 'reverseSite') {
    if (!$mybb->usergroup['canmodcp'] == 1) {
        redirect('applicants.php', $lang->applicants_submitpage_fail);
    }
    $aid = $mybb->input['uid'];
    $infoText =$lang->sprintf($lang->applicants_submitpage_text, get_user($aid)['username']);

    eval("\$page = \"" . $templates->get("applicantsReversePage") . "\";");
    output_page($page);
    return;
}


//-------------------- normale Seite ------------------
//Korrektur vom Thread annehmen
if (isset($_GET["applicantId"]) && $mybb->usergroup['canmodcp'] == 1) {
    $applicantUid = $_GET["applicantId"];
    correction($applicantUid);
}

//Zurücksetzen
if ($_POST['action']  ==  'reverse') {
    $uid = $_POST['aid'];
    if ($_POST['applicationStart'] == 'yes') {
        $applicationDeadline = new DateTime();
        $applicationDeadline->setTimestamp(time());
        date_add($applicationDeadline, date_interval_create_from_date_string($timeForApplication . 'days'));
        $update = array('expirationDate' => $applicationDeadline->format('Y-m-d'));
        $db->update_query('applicants', $update, 'uid = ' . $db->escape_string($uid));
    }

    if ($_POST['applicationControl'] == 'yes') {
        $update = array('corrector' => NULL);
        $db->update_query('applicants', $update, 'uid = ' . $db->escape_string($uid));
    }

    redirect('applicants.php', $lang->applicants_submitpage_success);
}

//Frist verlängern
if ($_POST["action"] == "extend") {
    $uid = $_POST["id"];
    $db->query("UPDATE " . TABLE_PREFIX . "applicants SET expirationDate = DATE_ADD(expirationDate, INTERVAL +$timeframeExtension day),extensionCtr = extensionCtr + 1  WHERE uid = $uid");
}

//Steckbrief übernehmen
if ($_POST["action"] == "correction") {
    $uid = $_POST["id"];
    correction($uid);
}

//alle Bewerber
$allApplicants = $db->simple_select('applicants', '*', '', array("order_by" => 'expirationDate',));

while ($applicant = $db->fetch_array($allApplicants)) {
    $user = get_user($applicant['uid']);
    $username =  build_profile_link($user['username'], $applicant['uid']);
    $corrector = '';
    $deadline = '';
    $deadlineDays = '';
    $deadlineText = '';
    $correctionButton = '';
    $correction = '';

    $applicationThread = $db->fetch_array($db->simple_select('threads', 'subject', 'uid = ' . $applicant['uid'] . ' AND fid = ' . $applicationFid . ' AND visible = 1'))['subject'];     //Korrektornamen bauen
    if ($applicant['corrector'] != NULL) {
        $corrector = "Es korrigiert <b>" . $applicant['corrector'] . "</b>";
    } else {
        if ($mybb->usergroup['canmodcp'] == 1 && $applicationThread != '') {
            $corrector = "";
            $correctionButton = '<i class="fas fa-check correction" title="Steckbrief übernehmen?" id="' .  $applicant['uid']  . '"></i>';
        } else {
            $corrector = "-";
            $correctionButton = "";
        }
    }
    if ($mybb->usergroup['canmodcp'] == 1) {
        $reverseButton = ' <a href="/applicants.php?action=reverseSite&uid=' .  $applicant['uid']  . '"><i class="fas fa-redo" title="Zurücksetzen?"></i></a>';
    }

    //Tage für Stecki
    $expiration = new DateTime($applicant['expirationDate']);
    $interval = $expiration->diff($today);
    $deadline = $expiration->format('d.m.Y');
    $deadlineDays = $interval->d;
    $isExpired = false;
    if ($expiration->format('Y-m-d') < $today->format('Y-m-d')) {
        $isExpired = true;
    }

    if ($applicant['corrector'] != null) {
        $deadlineText = "unter Korrektur";
        $correctionDate = new DateTime($applicant['correctionDate']);
        $correction = 'seit dem ' . $correctionDate->format('d.m.Y');
    } else if ($isExpired) {
        $deadlineText = "abgelaufen";
    } else if ($deadlineDays == 1) {
        $deadlineText = "<b>noch einen Tag</b> bis " . $deadline;
    } else {
        $deadlineText = "<b>noch " . $deadlineDays . " Tage</b> bis " . $deadline;
    }

    $buttonExtend = "";
    //Verlängern Button
    if ($applicant['corrector'] == null) {
        //user
        if (!$mybb->usergroup['canmodcp'] == 1) {
            if ($email == $applicant['email'] && !$isExpired && $deadlineDays <= $alertDays && $applicant['extensionCtr'] < $timesToExtend) {
                $buttonExtend = '<i class="fas fa-plus extend" title="Frist verlängern" id="' .  $applicant['uid']  . '"></i> ';
            }
        } else { //team
            $buttonExtend = '<i class="fas fa-plus extend" title="Frist verlängern" id="' .  $applicant['uid']  . '"></i> ';
        }
    }

    eval("\$applicants .= \"" . $templates->get("applicantsUser") . "\";");
}

//Steckbriefübernahme
function correction($uid)
{
    global $db, $pmAlert, $mybb, $playerFid;
    $corrector = $mybb->user['fid' . $playerFid];
    $update = array('corrector' => $corrector);
    $db->update_query('applicants', $update, 'uid = ' . $db->escape_string($uid));

    //Pn verschicken
    if ($pmAlert) {
        $pmTextAll = explode(";", $db->escape_string($mybb->settings['applicants_pmText']));
        $pmSubject = $pmTextAll[0];
        $pmText = $pmTextAll[1];

        // PN bei Übernahme des Steckbriefes
        require_once MYBB_ROOT . "inc/datahandlers/pm.php";
        $pmhandler = new PMDataHandler();

        $pm = array(
            "subject" => $pmSubject,
            "message" => $pmText,
            "fromid" => $mybb->user['uid'],
            "toid" => $uid
        );

        $pmhandler->set_data($pm);

        // PN versenden
        if ($pmhandler->validate_pm()) {
            $pmhandler->insert_pm();
        }
    }
}

eval("\$page = \"" . $templates->get("applicants") . "\";");
output_page($page);