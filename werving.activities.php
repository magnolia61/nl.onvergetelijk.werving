<?php

/**
 * =======================================================================================
 * FUNCTIE-INDEX: werving.activities.php
 * =======================================================================================
 *   werving_civicrm_activitymee()
 * =======================================================================================
 */

function werving_civicrm_activitymee($contactid, $mee_update, $displayname = NULL) {

    $extdebug       = 0;  //  1 = basic // 2 = verbose // 3 = params / 4 = results
    $apidebug       = FALSE;

    $contact_id     = $contactid;
    $displayname    = $displayname;

    if (empty($contact_id) OR empty($mee_update)) {
        return;
    }

    wachthond($extdebug,1, "########################################################################");
    wachthond($extdebug,1, "### WERVING 3.0 CONFIGURE ACTIVITY MEE $displayname",           "[START]");
    wachthond($extdebug,1, "########################################################################");

    $mee_update             = $mee_update;
    wachthond($extdebug,3, 'mee_update',                    $mee_update);

    $today_ts                       = time();
    $today_datetime                 = date("Y-m-d H:i:s", $today_ts);
    $today_datetime_past            = date('Y-m-d H:i:s', strtotime('-50 year', $today_ts));

    $mee_update_nextkamp_lastnext   = find_lastnext($mee_update); 
    wachthond($extdebug,3, 'mee_update_nextkamp_lastnext',          $mee_update_nextkamp_lastnext);

    $mee_update_nextkamp_start_date =   $mee_update_nextkamp_lastnext['next_start_date'];
    $mee_update_nextkamp_einde_date =   $mee_update_nextkamp_lastnext['next_einde_date'];
    $mee_update_nextkamp_kampjaar   = date('Y', strtotime($mee_update_nextkamp_start_date)) ?? NULL;
    wachthond($extdebug,3, 'mee_update_nextkamp_start_date',        $mee_update_nextkamp_start_date);
    wachthond($extdebug,3, 'mee_update_nextkamp_einde_date',        $mee_update_nextkamp_einde_date);
    wachthond($extdebug,3, 'mee_update_nextkamp_kampjaar',          $mee_update_nextkamp_kampjaar);

    $mee_update_nextkamp_fiscalyear         = curriculum_civicrm_fiscalyear($mee_update_nextkamp_start_date);
    $mee_update_nextkamp_fiscalyear_start   = $mee_update_nextkamp_fiscalyear['fiscalyear_start'] ?? NULL;
    $mee_update_nextkamp_fiscalyear_einde   = $mee_update_nextkamp_fiscalyear['fiscalyear_einde'] ?? NULL;
    wachthond($extdebug,3, 'mee_update_nextkamp_fiscalyear_start', $mee_update_nextkamp_fiscalyear_start);
    wachthond($extdebug,3, 'mee_update_nextkamp_fiscalyear_einde', $mee_update_nextkamp_fiscalyear_einde);    

    $array_contditjaar          = base_cid2cont($contact_id);
    wachthond($extdebug,4, "array_contditjaar",         $array_contditjaar);

    $array_allpart_ditjaar      = base_find_allpart($contact_id, $mee_update_nextkamp_start_date) ?: [];
    wachthond($extdebug,3, "array_allpart_ditjaar",     $array_allpart_ditjaar);

    $ditjaar_one_part_id                = $array_allpart_ditjaar['result_allpart_one_part_id'];
    $ditjaar_one_deel_part_id           = $array_allpart_ditjaar['result_allpart_one_deel_part_id'];
    $ditjaar_one_leid_part_id           = $array_allpart_ditjaar['result_allpart_one_leid_part_id'];

    $ditjaar_one_event_id               = $array_allpart_ditjaar['result_allpart_one_event_id'];
    $ditjaar_one_deel_event_id          = $array_allpart_ditjaar['result_allpart_one_deel_event_id'];
    $ditjaar_one_leid_event_id          = $array_allpart_ditjaar['result_allpart_one_leid_event_id'];

    $ditjaar_one_kampfunctie            = $array_allpart_ditjaar['result_allpart_one_kampfunctie'];
    $ditjaar_one_deel_kampfunctie       = $array_allpart_ditjaar['result_allpart_one_deel_kampfunctie'];
    $ditjaar_one_leid_kampfunctie       = $array_allpart_ditjaar['result_allpart_one_leid_kampfunctie'];

    $ditjaar_one_kampkort               = $array_allpart_ditjaar['result_allpart_one_kampkort'];
    $ditjaar_one_deel_kampkort          = $array_allpart_ditjaar['result_allpart_one_deel_kampkort'];
    $ditjaar_one_leid_kampkort          = $array_allpart_ditjaar['result_allpart_one_leid_kampkort'];

    $ditjaar_pos_part_id                = $array_allpart_ditjaar['result_allpart_pos_part_id'];
    $ditjaar_pos_deel_part_id           = $array_allpart_ditjaar['result_allpart_pos_deel_part_id'];
    $ditjaar_pos_leid_part_id           = $array_allpart_ditjaar['result_allpart_pos_leid_part_id'];

    $ditjaar_pos_event_id               = $array_allpart_ditjaar['result_allpart_pos_event_id'];
    $ditjaar_pos_deel_event_id          = $array_allpart_ditjaar['result_allpart_pos_deel_event_id'];
    $ditjaar_pos_leid_event_id          = $array_allpart_ditjaar['result_allpart_pos_leid_event_id'];

    $ditjaar_pos_kampfunctie            = $array_allpart_ditjaar['result_allpart_pos_kampfunctie'];
    $ditjaar_pos_deel_kampfunctie       = $array_allpart_ditjaar['result_allpart_pos_deel_kampfunctie'];
    $ditjaar_pos_leid_kampfunctie       = $array_allpart_ditjaar['result_allpart_pos_leid_kampfunctie'];

    $ditjaar_pos_kampkort               = $array_allpart_ditjaar['result_allpart_pos_kampkort'];
    $ditjaar_pos_deel_kampkort          = $array_allpart_ditjaar['result_allpart_pos_deel_kampkort'];
    $ditjaar_pos_leid_kampkort          = $array_allpart_ditjaar['result_allpart_pos_leid_kampkort'];    

    $ditjaar_kampkort_low               = preg_replace('/[^\w-]/', '', strtolower(trim((string)$ditjaar_kampkort))); // letters/0-9/dashes    
    $ditjaar_kampkort_cap               = preg_replace('/[^\w-]/', '', strtoupper(trim((string)$ditjaar_kampkort))); // letters/0-9/dashes    

    // ALS ER 1 IS DAN DIE
    if ($ditjaar_one_kampkort) {
        $ditjaar_kampkort       = $ditjaar_one_kampkort;
        $ditjaar_kampfunctie    = $ditjaar_one_kampfunctie;
    }
    // ALS ER MEER ZIJN DAN DE POS
    if ($ditjaar_pos_kampkort) {
        $ditjaar_kampkort       = $ditjaar_pos_kampkort;
        $ditjaar_kampfunctie    = $ditjaar_pos_kampfunctie;
    }

    if ($ditjaar_one_deel_kampkort OR $ditjaar_pos_deel_kampkort) {
        $ditjaar_kamprol        = 'deelnemer';
    }
    if ($ditjaar_one_leid_kampkort OR $ditjaar_pos_leid_kampkort) {
        $ditjaar_kamprol        = 'leiding';
    }

    wachthond($extdebug,3, 'ditjaar_one_kampfunctie',       $ditjaar_one_kampfunctie);
    wachthond($extdebug,3, 'ditjaar_one_deel_kampfunctie',  $ditjaar_one_deel_kampfunctie);
    wachthond($extdebug,3, 'ditjaar_one_leid_kampfunctie',  $ditjaar_one_leid_kampfunctie);

    wachthond($extdebug,3, 'ditjaar_pos_kampfunctie',       $ditjaar_pos_kampfunctie);
    wachthond($extdebug,3, 'ditjaar_pos_deel_kampfunctie',  $ditjaar_pos_deel_kampfunctie);
    wachthond($extdebug,3, 'ditjaar_pos_leid_kampfunctie',  $ditjaar_pos_leid_kampfunctie);

    wachthond($extdebug,3, 'ditjaar_kamprol',               $ditjaar_kamprol);
    wachthond($extdebug,3, 'ditjaar_kampfunctie',           $ditjaar_kampfunctie);

    $birthdate          = $array_contditjaar['birth_date']      ?? NULL;
    $geslacht           = $array_contditjaar['gender']          ?? NULL;
    $first_name         = $array_contditjaar['first_name']      ?? NULL;
    $middle_name        = $array_contditjaar['middle_name']     ?? NULL;
    $last_name          = $array_contditjaar['last_name']       ?? NULL;    
    $displayname        = $array_contditjaar['displayname']     ?? NULL;
    $crm_drupalnaam     = $array_contditjaar['crm_drupalnaam']  ?? NULL;  // drupal username
    $crm_externalid     = $array_contditjaar['crm_externalid']  ?? NULL;  // drupal cmsid

    $mee_komendkamp     = $array_contditjaar['mee_komendkamp']  ?? NULL;
    $mee_verwachting    = $array_contditjaar['mee_verwachting'] ?? NULL;
    $mee_toelichting    = $array_contditjaar['mee_toelichting'] ?? NULL;
    $mee_update         = $array_contditjaar['mee_update']      ?? NULL;
    $mee_update_year    = $array_contditjaar['mee_update_year'] ?? NULL;
    $mee_notities       = $array_contditjaar['mee_notities']    ?? NULL;

    wachthond($extdebug,1, "########################################################################");
    wachthond($extdebug,1, "### WERVING 3.1 MEE ACTIVITY - GET (cid: $contact_id)",      $displayname);
    wachthond($extdebug,1, "########################################################################");

    wachthond($extdebug,4, 'mee_update',            $mee_update);
    wachthond($extdebug,4, 'today_datetime_past',   $today_datetime_past);

    if (date_bigger($mee_update, $today_datetime_past) == 1) {

        $params_activity_mee_get = [
            'checkPermissions' => FALSE,
            'debug' => $apidebug,
            'select' => [
                'row_count', 'id', 'activity_date_time', 'status_id', 'status_id:name', 'subject', 
                'activity_contact.contact_id', 'activity_contact.display_nem',
                'target_contact_id.display_name',
            ],
            'join' => [
                ['ActivityContact AS activity_contact', 'INNER'],
            ],
            'where' => [
                ['activity_contact.contact_id',       '=',  $contact_id],
                ['activity_contact.record_type_id',   '=', 3],
                ['activity_type_id:name',             '=', 'mee_ditjaar'],
                ['activity_date_time',                '>=', $mee_update_nextkamp_fiscalyear_start],
                ['activity_date_time',                '<=', $mee_update_nextkamp_fiscalyear_einde],
            ],
            'limit' => 1,
        ];

        wachthond($extdebug,3, 'params_activity_mee_get',       $params_activity_mee_get);
        $result_mee_get       = civicrm_api4('Activity','get',  $params_activity_mee_get);
        $result_mee_get_count = $result_mee_get->countMatched();
//      wachthond($extdebug,9, 'result_activity_mee_get ',      $result_mee_get);   
        wachthond($extdebug,3, 'result_mee_count',              $result_mee_get_count);
    }

    if ($mee_update AND $result_mee_get_count AND $result_mee_get_count == 1) {

        $mee_activity_id            = $result_mee_get[0]['id']                              ?? NULL;
        $mee_activity_status_id     = $result_mee_get[0]['status_id']                       ?? NULL;
        $mee_activity_status_name   = $result_mee_get[0]['status_id:name']                  ?? NULL;
        $mee_activity_datum         = $result_mee_get[0]['activity_date_time']              ?? NULL;
        $mee_activity_contact_id    = $result_mee_get[0]['activity_contact.contact_id']     ?? NULL;
        $mee_activity_displayname   = $result_mee_get[0]['target_contact_id.display_name']  ?? NULL;

        wachthond($extdebug,2, 'mee_activity_id',               $mee_activity_id);
        wachthond($extdebug,2, 'mee_activity_status_id',        $mee_activity_status_id);
        wachthond($extdebug,2, 'mee_activity_status_name',      $mee_activity_status_name);
        wachthond($extdebug,2, 'mee_activity_datum',            $mee_activity_datum);
        wachthond($extdebug,2, 'mee_activity_contact_id',       $mee_activity_contact_id);
        wachthond($extdebug,2, 'mee_activity_displayname',      $mee_activity_displayname);

      } else {

        $mee_activity_id        = NULL;
        $mee_activity_status    = NULL;
        $mee_activity_datum     = NULL;
        wachthond($extdebug,3, 'mee_activity_id',   "No Activity Found");
    }

    if (date_bigger($mee_update, $today_datetime_past) == 1 AND $result_mee_get_count == 0 AND $mee_update_nextkamp_kampjaar > 2000) {

        wachthond($extdebug,2, "########################################################################");
        wachthond($extdebug,1, "### WERVING 3.2 MEE ACTIVITY - CREATE (cid: $contact_id)",   $displayname);
        wachthond($extdebug,2, "########################################################################");

        wachthond($extdebug,1, "########################################################################");
        wachthond($extdebug,1, "CREATE mee_verwachting",                 $mee_verwachting);
        wachthond($extdebug,1, "CREATE mee_toelichting",                 $mee_toelichting);
        wachthond($extdebug,1, "########################################################################");

        $params_activity_mee_create = [
            'checkPermissions' => FALSE,
            'debug' => $apidebug,
            'values' => [
                'source_contact_id'         => $contact_id,
                'target_contact_id'         => $contact_id,
//              'activity_type_id:name'     => 'mee_ditjaar',
                'activity_type_id'          => '146',
                'activity_date_time'        => $mee_update,
                'subject'                   => 'Mee in '. $mee_update_nextkamp_kampjaar.' : '.$mee_verwachting,
                'status_id:name'            => 'Completed',
                'ACT_MEE.kampjaar'          => $mee_update_nextkamp_kampjaar,
                'ACT_MEE.komendkamp'        => $mee_update_nextkamp_start_date,
                'ACT_MEE.rol'               => $ditjaar_kamprol,
                'ACT_MEE.verwachting'       => $mee_verwachting, 
                'ACT_MEE.toelichting'       => $mee_toelichting, 
                'ACT_MEE.update'            => $mee_update,

                'ACT_ALG.actcontact_naam'   => $mee_activity_displayname,
                'ACT_ALG.actcontact_cid'    => $contact_id,

                'ACT_ALG.kampnaam'          => $ditjaar_kampkort_cap,
                'ACT_ALG.kampkort'          => $ditjaar_kampkort_low,
                'ACT_ALG.kampfunctie'       => $ditjaar_kampfunctie,
                'ACT_ALG.kamprol'           => $ditjaar_kamprol, 
                'ACT_ALG.kampstart'         => $mee_update_nextkamp_start_date,
                'ACT_ALG.kampeinde'         => $mee_update_nextkamp_einde_date,
                'ACT_ALG.kampjaar'          => $mee_update_nextkamp_kampjaar,
                'ACT_ALG.modified'          => $today_datetime,
                'ACT_ALG.prioriteit:label'  => 'Normaal',
            ],
        ];
        wachthond($extdebug,3, 'params_activity_mee_create',                $params_activity_mee_create);
        if ($contact_id) {
            $result_activity_mee_create = civicrm_api4('Activity','create', $params_activity_mee_create);
        wachthond($extdebug,9, 'result_activity_mee_create',                $result_activity_mee_create);
        }
    }        

    if (date_bigger($mee_update, $today_datetime_past) == 1 AND $result_mee_get_count == 1) {

        wachthond($extdebug,2, "########################################################################");
        wachthond($extdebug,1, "### WERVING 3.3 MEE ACTIVITY - UPDATE (cid: $contact_id)",   $displayname);
        wachthond($extdebug,2, "########################################################################");

        wachthond($extdebug,2, 'mee_activity_id',           $mee_activity_id);
        wachthond($extdebug,2, 'mee_activity_status_id',    $mee_activity_status_id);
        wachthond($extdebug,2, 'mee_activity_status_name',  $mee_activity_status_name);
        wachthond($extdebug,2, 'mee_activity_datum',        $mee_activity_datum);

        wachthond($extdebug,1, "mee_verwachting",           $mee_verwachting);
        wachthond($extdebug,1, "mee_toelichting",           $mee_toelichting);
        wachthond($extdebug,3, "########################################################################");

        $params_activity_mee_update = [
            'checkPermissions' => FALSE,
            'debug' => $apidebug,
            'where' => [
                ['id',                      '=', $mee_activity_id],
            ],        
            'values' => [
                'source_contact_id'         => $contact_id,
                'target_contact_id'         => $contact_id,
//              'activity_type_id:name'     => 'mee_ditjaar',
                'activity_type_id'          => '146',
                'activity_date_time'        => $mee_update,
                'subject'                   => 'Mee in '. $mee_update_nextkamp_kampjaar.' : '.$mee_verwachting,
                'status_id:name'            => 'Completed',
                'ACT_MEE.kampjaar'          => $mee_update_nextkamp_kampjaar,
                'ACT_MEE.komendkamp'        => $mee_update_nextkamp_start_date,
                'ACT_MEE.rol'               => $ditjaar_kamprol,
                'ACT_MEE.verwachting'       => $mee_verwachting,
                'ACT_MEE.toelichting'       => $mee_toelichting,
                'ACT_MEE.update'            => $mee_update,

                'ACT_ALG.actcontact_naam'   => $mee_activity_displayname,
                'ACT_ALG.actcontact_cid'    => $contact_id,

                'ACT_ALG.kampnaam'          => $ditjaar_kampkort_cap,
                'ACT_ALG.kampkort'          => $ditjaar_kampkort_low,
                'ACT_ALG.kampfunctie'       => $ditjaar_kampfunctie,
                'ACT_ALG.rol'               => $ditjaar_kamprol,
                'ACT_ALG.kampstart'         => $mee_update_nextkamp_start_date,
                'ACT_ALG.kampeinde'         => $mee_update_nextkamp_einde_date,
                'ACT_ALG.kampjaar'          => $mee_update_nextkamp_kampjaar,
                'ACT_ALG.modified'          => $today_datetime,
                'ACT_ALG.activity_id'       => $mee_activity_id,
                'ACT_ALG.prioriteit:label'  => 'Normaal',
            ],
        ];
        wachthond($extdebug,3, 'params_activity_mee_update',                $params_activity_mee_update);
        if ($mee_activity_id) {
            $result_activity_mee_update = civicrm_api4('Activity','update', $params_activity_mee_update);
        }
//      wachthond($extdebug,9, 'result_activity_mee_update',                $result_activity_mee_update);

        wachthond($extdebug,3, 'ditevent_event_start',  $ditevent_event_start);
        wachthond($extdebug,3, 'ditevent_event_einde',  $ditevent_event_einde);

        wachthond($extdebug,3, 'mee_update_next_year',  $mee_update_next_year);
        wachthond($extdebug,3, 'mee_event_past_date',   $mee_event_past_date);
        wachthond($extdebug,3, 'mee_event_next_date',   $mee_event_next_date);
        wachthond($extdebug,3, 'mee_event_next_year',   $mee_event_next_year);
    }

    wachthond($extdebug,1, "########################################################################");
    wachthond($extdebug,1, "### WERVING 3.0 CONFIGURE ACTIVITY MEE VOOR $displayname",      "[EINDE]");
    wachthond($extdebug,1, "########################################################################");

}
