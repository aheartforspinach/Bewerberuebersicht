<?php
define("IN_MYBB", 1);
require_once "global.php";

add_breadcrumb("Bewerberfristen", "applicants.php");
global $db, $templates, $mybb, $lang;
$email = $mybb->user['email'];
$today = new DateTime(date("Y-m-d", time()));

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

//------------- submit page ---------------------------
if ($mybb->get_input('action', MyBB::INPUT_STRING) === 'reverseSite') {
    if (!$mybb->usergroup['canmodcp'] == 1) {
        redirect('applicants.php', $lang->applicants_submitpage_fail);
    }
    $aid = $mybb->input['uid'];
    $infoText = $lang->sprintf($lang->applicants_submitpage_text, get_user($aid)['username']);

    eval("\$page = \"" . $templates->get("applicants_ReversePage") . "\";");
    output_page($page);
    return;
}


//-------------------- get correction from thread ------------------
if ($mybb->get_input('applicantId', MyBB::INPUT_INT) !== 0 && $mybb->usergroup['canmodcp'] == 1) {
    correction($mybb->get_input('applicantId', MyBB::INPUT_INT));
}

//-------------------- reset page ------------------
if ($mybb->get_input('action', MyBB::INPUT_STRING) === 'reverse') {
    $uid = $mybb->get_input('aid', MyBB::INPUT_INT);

    if ($mybb->get_input('applicationStart', MyBB::INPUT_STRING) === 'yes' || $mybb->get_input('applicationStart', MyBB::INPUT_STRING) == 'yes_extend') {
        $applicationDeadline = new DateTime();
        $applicationDeadline->setTimestamp(time());
        $extendTime = $mybb->get_input('applicationStart', MyBB::INPUT_STRING) === 'yes' ? $timeForApplication : $timeframeExtension;
        date_add($applicationDeadline, date_interval_create_from_date_string($extendTime . 'days'));
        $update = array('expirationDate' => $applicationDeadline->format('Y-m-d'));
        $db->update_query('applicants', $update, 'uid = ' . $db->escape_string($uid));
    }

    if ($mybb->get_input('applicationControl', MyBB::INPUT_STRING) === 'yes') {
        $update = array('corrector' => NULL);
        $db->update_query('applicants', $update, 'uid = ' . $db->escape_string($uid));
    }

    redirect('applicants.php', $lang->applicants_submitpage_success);
}

//-------------------- expand expiration ------------------
if ($mybb->get_input('action', MyBB::INPUT_STRING) === "extend") {
    $uid = $mybb->get_input('id', MyBB::INPUT_INT);
    $db->query("UPDATE " . TABLE_PREFIX . "applicants SET expirationDate = DATE_ADD(expirationDate, INTERVAL +$timeframeExtension day),extensionCtr = extensionCtr + 1  WHERE uid = $uid");
}

//-------------------- start correction ------------------
if ($mybb->get_input('action', MyBB::INPUT_STRING) === "correction") {
    correction($mybb->get_input('id', MyBB::INPUT_INT));
}

//-------------------- normal page ------------------
$applicants = '';
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

    if ($applicant['corrector'] !== null) {
        $corrector = $lang->sprintf($lang->applicants_submitpage_corrector, $applicant['corrector']);
    } else {
        $applicationSubject = $db->fetch_field($db->simple_select(
            'threads', 
            'subject', 
            'uid = ' . $applicant['uid'] . ' AND fid = ' . $applicationFid . ' AND visible = 1'),
        'subject');

        $isApplicationThere = $mybb->usergroup['canmodcp'] == 1 && $applicationSubject != '';
        $corrector = $isApplicationThere ? '' : '-';
        $correctionButton = $isApplicationThere ? $lang->sprintf($lang->applicants_submitpage_correct_application, $applicant['uid']) : '';
    }

    if ($mybb->usergroup['canmodcp'] == 1) {
        $reverseButton = $lang->sprintf($lang->applicants_submitpage_revert, $applicant['uid']);
    }

    // expiration
    $expiration = new DateTime($applicant['expirationDate']);
    $interval = $expiration->diff($today);
    $deadline = $expiration->format('d.m.Y');
    $deadlineDays = $interval->d;
    $isExpired = false;

    if ($expiration->format('Y-m-d') < $today->format('Y-m-d')) {
        $isExpired = true;
    }

    if ($applicant['corrector'] !== null) {
        $deadlineText = $lang->applicants_submitpage_correction;
        $correctionDate = new DateTime($applicant['correctionDate']);
        $correction = $lang->sprintf($lang->applicants_submitpage_correction_since, $correctionDate->format('d.m.Y'));
    } else if ($isExpired) {
        $deadlineText = $lang->applicants_submitpage_expired;
    } else if ($deadlineDays == 1) {
        $deadlineText = $lang->sprintf($lang->applicants_submitpage_one_day, $deadline);
    } else {
        $deadlineText = $lang->sprintf($lang->applicants_submitpage_more_days, $deadlineDays, $deadline);
    }

    $buttonExtend = '';

    // extend button
    if ($applicant['corrector'] == null) {
        //user
        if (!$mybb->usergroup['canmodcp'] == 1) {
            if ($email == $applicant['email'] && !$isExpired && $deadlineDays <= $alertDays && $applicant['extensionCtr'] < $timesToExtend) {
                $buttonExtend = $lang->sprintf($lang->applicants_submitpage_extend, $applicant['uid']);
            }
        } else { //team
            $buttonExtend = $lang->sprintf($lang->applicants_submitpage_extend, $applicant['uid']);
        }
    }

    eval("\$applicants .= \"" . $templates->get("applicants_User") . "\";");
}

//Steckbriefübernahme
function correction($uid)
{
    global $db, $pmAlert, $mybb, $playerFid;
    $corrector = $mybb->user['fid' . $playerFid];
    $update = array('corrector' => $corrector);
    $db->update_query('applicants', $update, 'uid = ' . $db->escape_string($uid));

    // alert stuff
    if (function_exists('myalerts_is_activated') && myalerts_is_activated()) {
        $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();
        $alertType = $alertTypeManager->getByCode('applicants');
        $alertTypeId = $alertType->getId();
        $fromUser = $mybb->user['uid'];
        $toUser = $db->escape_string($uid);
        $alertManager = MybbStuff_MyAlerts_AlertManager::getInstance();

        $alert = new MybbStuff_MyAlerts_Entity_Alert($toUser, $alertType, $db->escape_string($uid));
        $alert->setFromUserId($fromUser);
        $alertManager->addAlert($alert);
    }

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