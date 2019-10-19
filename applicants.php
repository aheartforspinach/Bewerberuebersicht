<?php
define("IN_MYBB", 1);
require_once "global.php";

add_breadcrumb("Bewerberfristen", "applicants.php");
global $db, $templates, $mybb;
$email = $mybb->user['email'];
$today = new DateTime(date("Y-m-d", time())); //heute

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

//Korrektur vom Thread annehmen
if(isset($_GET["applicantId"]) && $mybb->usergroup['canmodcp'] == 1){
    $applicantUid = $_GET["applicantId"];
    correction($applicantUid);
}

//alle Bewerber
$allApplicants = $db->simple_select('applicants', '*', '', array("order_by" => 'expirationDate',));

while($applicant=$db->fetch_array($allApplicants)){
    $username = build_profile_link($applicant['username'], $applicant['uid']);
    $corrector = "";
    $deadline = "";
    $deadlineDays = "";
    $deadlineText = "";
    $correctionButton ="";

    //Korrektornamen bauen
    if($applicant['corrector'] != NULL){
        $corrector = "Es korrigiert <b>" . $applicant['corrector'] . "</b>";
    }else{
        if($mybb->usergroup['canmodcp'] == 1){
            $corrector = "";
            $correctionButton = '<i class="fas fa-check correction" title="Steckbrief übernehmen?" id="' .  $applicant['uid']  . '"></i>';
        }else{
            $corrector = "-";
            $correctionButton = "";
        }
    }

    //Tage für Stecki
    $expiration = new DateTime($applicant['expirationDate']);
    $interval = $expiration->diff($today);
    $deadline = $expiration->format('d.m.Y');
    $deadlineDays = $interval->d;
    $isExpired = false;
    if($expiration->format('Y-m-d') < $today->format('Y-m-d')){
        $isExpired = true;
    }

    if ($applicant['corrector'] != null) {
        $deadlineText = "unter Korrektur";
    } else if ($isExpired) {
        $deadlineText = "abgelaufen";
    } else if ($deadlineDays == 1){
        $deadlineText = "<b>noch einen Tag</b> bis " . $deadline;
    }else{
        $deadlineText = "<b>noch " . $deadlineDays . " Tage</b> bis " . $deadline;
    }

    $buttonExtend = "";
    //Verlängern Button
    if ($applicant['corrector'] == null) {
        if (($email == $applicant['email'] && !$isExpired || $mybb->usergroup['canmodcp'] == 1) && $deadlineDays <= $alertDays && $applicant['extensionCtr'] < $timesToExtend) {
            $buttonExtend = '<i class="fas fa-plus extend" title="Frist verlängern" id="' .  $applicant['uid']  . '"></i> ';
        } 
    }

    eval("\$applicants .= \"".$templates->get("applicantsUser")."\";");
}

//Frist verlängern
if($_POST["action"] == "extend"){
    $uid = $_POST["id"];
    $db->query("UPDATE ".TABLE_PREFIX."applicants SET expirationDate = DATE_ADD(expirationDate, INTERVAL +$timeForApplication day),extensionCtr = extensionCtr + 1  WHERE uid = $uid");
}

//Steckbrief übernehmen
if($_POST["action"] == "correction"){
    $uid = $_POST["id"];
    correction($uid);
}

//Steckbriefübernahme
function correction($uid){
    global $db, $pmAlert, $mybb, $playerFid;
    $corrector = $mybb->user['fid'. $playerFid];
    $db->query("UPDATE ".TABLE_PREFIX."applicants SET corrector = '$corrector' WHERE uid = $uid");

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

eval("\$page = \"".$templates->get("applicants")."\";");
output_page($page);
?>