<?php

function task_applicants($task){
    global $db, $mybb;
    $applicantGid = intval($mybb->settings['applicants_gid']);
    $timeForApplication = intval($mybb->settings['applicants_time']);

    // remove deleted applicants
    $allDeletedApplicants = $db->query("SELECT uid
        FROM " . TABLE_PREFIX . "applicants
        WHERE uid NOT IN(
        SELECT uid 
        FROM " . TABLE_PREFIX . "users)");

    while ($deletedApplicants = $db->fetch_array($allDeletedApplicants)) {
        $db->delete_query('applicants', 'uid = ' . $deletedApplicants['uid']);
    }

    // remove old applicants
    $allExApplicants = $db->query("SELECT uid
        FROM " . TABLE_PREFIX . "users
        WHERE usergroup != $applicantGid AND uid IN(
        SELECT uid 
        FROM " . TABLE_PREFIX . "applicants)");

    while ($exApplicant = $db->fetch_array($allExApplicants)) {
        $db->delete_query('applicants', 'uid = ' . $exApplicant['uid']);
    }

    if ($timeForApplication != 0) {
        // add new applicants
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

    add_task_log($task, 'Gelöschte und angenommene Bewerber:innen wurde entfernt.');
}