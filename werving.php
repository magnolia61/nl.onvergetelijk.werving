<?php

require_once 'werving.civix.php';
use CRM_Werving_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 */
function werving_civicrm_config(&$config): void {
    _werving_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 */
function werving_civicrm_install(): void {
    _werving_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 */
function werving_civicrm_enable(): void {
    _werving_civix_civicrm_enable();
}

/**
 * Hook Pre: De "Verkeersregelaar" voor Werving.
 */
function werving_civicrm_customPre(string $op, int $groupID, int $entityID, array &$params): void {

    // -------------------------------------------------------------------------
    // STAP 0: INITIALISATIE
    // -------------------------------------------------------------------------
    
    static $processing_werving_custompre = false;
    if ($processing_werving_custompre) return;
    $processing_werving_custompre = true;

    $extdebug       = 3;     // 1=Basic, 2=Flow, 3=Data
    $extwrite       = 1;     // 1=Schrijf wijzigingen terug
    $apidebug       = FALSE; 
    $profilewerving = [270]; 
    $today_datetime      = date("Y-m-d H:i:s");
    $today_datetime_past = date("Y-m-d H:i:s", strtotime("-1 day"));

    if (!in_array($groupID, $profilewerving) || ($op != 'create' && $op != 'edit')) {
        $processing_werving_custompre = false;
        return;
    }

    $params_org = $params;

    wachthond($extdebug, 1, "########################################################################");
    wachthond($extdebug, 1, "### WERVING [PRE] 0.1 START HOOK & INIT", "[EntityID: $entityID]");
    wachthond($extdebug, 1, "########################################################################");

    // -------------------------------------------------------------------------
    // STAP 1: DEFINITIE MAPPINGS
    // -------------------------------------------------------------------------
    
    $field_ids = [
        'WERVING.leeftijd_decimalen'   => 1665,
        'WERVING.leeftijd_rondjaren'   => 1666,
        'WERVING.nextkamp_decimalen'   => 1580,
        'WERVING.nextkamp_rondjaren'   => 1578,
        'WERVING.nextkamp_rondmaand'   => 1579,
        'WERVING.datum_belangstelling' => 647,
        'WERVING.leeftijdsgroep'       => 378,
        'WERVING.kampweek'             => 377,
        'WERVING.kampweken'            => 1172,
        'WERVING.mee_update'           => 1614,
        'WERVING.mee_update_year'      => 1619,
        'WERVING.mee_verwachting'      => 1615,
        'WERVING.mee_toelichting'      => 1616,
        'WERVING.mee_status'           => 1967,
        'WERVING.mee_notities'         => 1764,
        'WERVING.vakantieregio'        => 1949,
        'WERVING.mee_update_text'      => 2224,
        'WERVING.mee_contact'          => 2234,
    ];

    $name_map = [
        'leeftijd_decimalen_1665'         => 'WERVING.leeftijd_decimalen',
        'leeftijd_rondjaren_1666'         => 'WERVING.leeftijd_rondjaren',
        'nextkamp_decimalen_1580'         => 'WERVING.nextkamp_decimalen',
        'nextkamp_rondjaren_1578'         => 'WERVING.nextkamp_rondjaren',
        'nextkamp_rondmaand_1579'         => 'WERVING.nextkamp_rondmaand',
        'datum_belangstelling_647'        => 'WERVING.datum_belangstelling',
        'welke_leeftijdsgroep_378'        => 'WERVING.leeftijdsgroep',
        'welke_kampweek_377'              => 'WERVING.kampweek',
        'welke_kampweken_1172'            => 'WERVING.kampweken',
        'mee_update_1614'                 => 'WERVING.mee_update',
        'mee_komendkampjaar_1619'         => 'WERVING.mee_update_year',
        'mee_verwachting_1615'            => 'WERVING.mee_verwachting',
        'mee_toelichting_1616'            => 'WERVING.mee_toelichting',
        'mee_status_1967'                 => 'WERVING.mee_status',
        'mee_notities_1764'               => 'WERVING.mee_notities',
        'vakantieregio_1949'              => 'WERVING.vakantieregio',
        'mee_update_text_2224'            => 'WERVING.mee_update_text',
        'mee_contact_2234'                => 'WERVING.mee_contact',
    ];

wachthond($extdebug, 1, "########################################################################");
    wachthond($extdebug, 1, "### WERVING [PRE] 1.1 GET VALUES & NORMALIZE", "");
    wachthond($extdebug, 1, "########################################################################");

    // A. RUWE DUMP (Wat komt er exact binnen?)
    wachthond($extdebug, 4, "--- RAW PARAMS DUMP ---", $params);

    $lists = [];
    
    // B. LOOP DOOR ALLE VELDEN EN MAAK VARIABELEN
    foreach ($field_ids as $internal_name => $id) {
        
        $raw_value = null;
        $key       = 'custom_' . $id;
        
        // 1. Check Webform structuur (Genest)
        foreach ($params as $p_val) {
            if (is_array($p_val) && isset($p_val['custom_field_id']) && $p_val['custom_field_id'] == $id) {
                $raw_value = $p_val['value'] ?? null;
                break;
            }
        }
        
        // 2. Check Backend structuur (Plat) - als nog niet gevonden
        if ($raw_value === null && isset($params[$key])) {
            $raw_value = $params[$key];
        }

        // 3. Variabele aanmaken (val_...)
        // Maak de naam schoon: WERVING.mee_notities -> mee_notities
        $clean_name = str_replace('WERVING.', '', $internal_name);
        $var_name   = 'val_' . $clean_name;

        // 4. Formattering toepassen
        // Datums krijgen extra tijd-behandeling indien nodig
        if (strpos($internal_name, 'datum') !== false || strpos($internal_name, 'update') !== false) {
             $formatted_val = (function_exists('base_addtime_iftoday')) 
                                ? base_addtime_iftoday(format_civicrm_smart($raw_value, $clean_name))
                                : format_civicrm_smart($raw_value, $clean_name);
        } else {
             $formatted_val = format_civicrm_smart($raw_value, $clean_name);
        }

        // 5. Dynamische toewijzing: $$var_name wordt $val_mee_notities etc.
        $$var_name = $formatted_val;
        
        // Sla ook op in lists voor interne referentie (backward compatibility)
        $lists[$internal_name] = $formatted_val;

        // 6. DEBUG DEZE SPECIFIEKE VARIABELE
        wachthond($extdebug, 4, "VAR: \$$var_name ($internal_name)", $formatted_val ?? '{LEEG}');
    }

    // C. LEGACY ALIASSEN (Voor bestaande logica verderop)
    // Omdat je logica soms andere namen gebruikt, zetten we die hier expliciet gelijk.
    $belangstelling_week  = $val_kampweek       ?? NULL;
    $belangstelling_groep = $val_leeftijdsgroep ?? NULL;

    // -------------------------------------------------------------------------
    // STAP 2: CONTEXT OPHALEN
    // -------------------------------------------------------------------------
    
    wachthond($extdebug, 1, "########################################################################");
    wachthond($extdebug, 1, "### WERVING [PRE] 2.1 GET CONTACT CONTEXT", "");
    wachthond($extdebug, 1, "########################################################################");

    $displayname           = 'Onbekend';
    $drupal_id             = 0;
    $birthdate             = NULL;
    $werving_vakantieregio = NULL;
    $contact_id            = $entityID; 

    if ($contact_id > 0) {
        try {
            $result_contact_get = civicrm_api4('Contact', 'get', [
                'checkPermissions' => FALSE,
                'select'           => ['display_name', 'external_identifier', 'birth_date', 'WERVING.vakantieregio'],
                'where'            => [['id', '=', $contact_id]],
                'limit'            => 1,
            ]);
            if (isset($result_contact_get[0])) {
                $displayname           = $result_contact_get[0]['display_name']          ?? 'Onbekend';
                $drupal_id             = $result_contact_get[0]['external_identifier']   ?? 0;
                $birthdate             = $result_contact_get[0]['birth_date']            ?? NULL;
                $werving_vakantieregio = $result_contact_get[0]['WERVING.vakantieregio'] ?? NULL;
            }
        } catch (\Exception $e) { }
    }

    if ($belangstelling_week && $belangstelling_groep) {
        
        wachthond($extdebug, 1, "########################################################################");
        wachthond($extdebug, 1, "### WERVING [PRE] 3.1 LOGICA: KAMPWEKEN", "");
        wachthond($extdebug, 1, "########################################################################");

        $grp_arr_raw = is_array($belangstelling_groep) ? $belangstelling_groep : [$belangstelling_groep];
        $grp_arr     = [];
        foreach ($grp_arr_raw as $item) {
            $clean_item = trim((string)$item, "\x01 \t\n\r\0\x0B"); 
            if (!empty($clean_item)) $grp_arr[] = $clean_item;
        }

        $belangstelling_array = [];
        if (($belangstelling_week == 'week1' OR $belangstelling_week == 'maaktnietuit') AND in_array('kinderkamp',  $grp_arr)) $belangstelling_array[] = 'KK1';
        if (($belangstelling_week == 'week2' OR $belangstelling_week == 'maaktnietuit') AND in_array('kinderkamp',  $grp_arr)) $belangstelling_array[] = 'KK2';
        if (($belangstelling_week == 'week1' OR $belangstelling_week == 'maaktnietuit') AND in_array('brugkamp',    $grp_arr)) $belangstelling_array[] = 'BK1';
        if (($belangstelling_week == 'week2' OR $belangstelling_week == 'maaktnietuit') AND in_array('brugkamp',    $grp_arr)) $belangstelling_array[] = 'BK2';
        if (($belangstelling_week == 'week1' OR $belangstelling_week == 'maaktnietuit') AND in_array('tienerkamp',  $grp_arr)) $belangstelling_array[] = 'TK1';
        if (($belangstelling_week == 'week2' OR $belangstelling_week == 'maaktnietuit') AND in_array('tienerkamp',  $grp_arr)) $belangstelling_array[] = 'TK2';
        if (($belangstelling_week == 'week1' OR $belangstelling_week == 'maaktnietuit') AND in_array('jeugdkamp',   $grp_arr)) $belangstelling_array[] = 'JK1';
        if (($belangstelling_week == 'week2' OR $belangstelling_week == 'maaktnietuit') AND in_array('jeugdkamp',   $grp_arr)) $belangstelling_array[] = 'JK2';

        if (!empty($belangstelling_array)) {
            $new_kampweken = implode('', $belangstelling_array);
            
            // DIRECTE INJECTIE (Voorkomt array/key mismatch error)
            foreach ($params as $key => $val) {
                if (is_array($val) && isset($val['custom_field_id']) && $val['custom_field_id'] == 1172) {
                    $params[$key]['value'] = $new_kampweken;
                    wachthond($extdebug, 3, "LOGICA: Kampweken geinjecteerd", $new_kampweken);
                    break;
                }
            }
        }
    }

    wachthond($extdebug, 1, "########################################################################");
    wachthond($extdebug, 1, "### WERVING [PRE] 3.2 LOGICA: DATUMS & TERMIJNEN", "");
    wachthond($extdebug, 1, "########################################################################");

    // 1. BEPAAL DE LEIDENDE DATUM
    // We kijken welke datum recenter is: de nieuwe 'datum_belangstelling' of 
    // de al bestaande 'mee_update' datum. De nieuwste wint.
    $temp_mee_update = (date_biggerequal($val_datum_belangstelling, $val_mee_update) == 1 OR empty($val_mee_update)) 
                       ? $val_datum_belangstelling : $val_mee_update;

    // DEBUG: Welke datum is leidend geworden?
    wachthond($extdebug, 3, "DEBUG: Datum Belangstelling",  $val_datum_belangstelling);
    wachthond($extdebug, 3, "DEBUG: Datum Mee Update (Old)",$val_mee_update);
    wachthond($extdebug, 3, "DEBUG: Datum Leidend (Temp)",  $temp_mee_update);

    $new_mee_update_nextyear = NULL; 
    
    // 2. BEPAAL HET KAMPJAAR (DIT JAAR OF VOLGEND JAAR?)
    if ($temp_mee_update) {

        $temp_mee_update_year = date('Y', strtotime($temp_mee_update));
        
        if (!empty($temp_mee_update_year)) {
            
            // A. HARDE GRENZEN (SNELLE CHECK)
            // 18 juli: Vóór de kampen. | 07 augustus: Na de kampen.
            $datumzekervoorkamp_string = $temp_mee_update_year . '-07-18 16:00:00';
            $datumzekernakamp_string   = $temp_mee_update_year . '-08-07 16:00:00';

            // Is de datum NA 7 augustus? -> Volgend jaar (+1)
            if (date_biggerequal($temp_mee_update, $datumzekernakamp_string) == 1) {
                $new_mee_update_nextyear = date('Y', strtotime('+1 year', strtotime($temp_mee_update)));        
                wachthond($extdebug, 3, "LOGIC: Harde grens (> 7 aug)", "Volgend jaar ($new_mee_update_nextyear)");

            // Is de datum VOOR 18 juli? -> Dit jaar
            } elseif (date_biggerequal($datumzekervoorkamp_string, $temp_mee_update) == 1) {
                $new_mee_update_nextyear = $temp_mee_update_year;
                wachthond($extdebug, 3, "LOGIC: Harde grens (< 18 jul)", "Dit jaar ($new_mee_update_nextyear)");
            }
        }

        // B. VANGNET (DATABASE CHECK)
        // Als datum PRECIES in de kampweken valt (tussen 18 juli en 7 aug)
        if (empty($new_mee_update_nextyear)) {
            
            wachthond($extdebug, 3, "LOGIC: Vangnet nodig", "Datum valt midden in seizoen, DB raadplegen");

            // Zoek wanneer het laatste kamp daadwerkelijk eindigt
            $participant_lastnext        = find_lastnext_part($contact_id, $temp_mee_update);
            $participant_last_einde_date = $participant_lastnext['last_einde_date'];
            
            wachthond($extdebug, 3, "DEBUG: DB Laatste Einde", $participant_last_einde_date);

            // Datum NA einde laatste kamp? -> Volgend jaar
            if (date_biggerequal($temp_mee_update, $participant_last_einde_date) == 1) {
                $new_mee_update_nextyear  = date('Y', strtotime('+1 year', strtotime($temp_mee_update)) );        
                wachthond($extdebug, 3, "LOGIC: Na DB Einde", "Volgend jaar ($new_mee_update_nextyear)");

            // Datum VOOR einde laatste kamp? -> Dit jaar
            } elseif (date_biggerequal($participant_last_einde_date, $temp_mee_update) == 1) {
                $new_mee_update_nextyear  = date('Y', strtotime('+0 year', strtotime($temp_mee_update)) );
                wachthond($extdebug, 3, "LOGIC: Voor DB Einde", "Dit jaar ($new_mee_update_nextyear)");
            }
        }
    }

    // 3. FINALISEREN DATUMS
    $new_mee_update = $temp_mee_update; 
    
    // Als we een doeljaar hebben (bijv 2027), zet de datum op 1 augustus van dat jaar
    if (!empty($new_mee_update_nextyear) && $new_mee_update_nextyear > 1970) {
        $check_date = $new_mee_update_nextyear . '-08-01 16:00:00';
        
        // Check of datum in de toekomst ligt
        if (date_bigger($today_datetime_past, $check_date) != 1) {
            $new_mee_update_year = $check_date; 
            wachthond($extdebug, 3, "RESULT: Mee Update Year Set", $new_mee_update_year);
        } else {
            wachthond($extdebug, 3, "RESULT: Mee Update Year Skipped", "Datum $check_date ligt in verleden");
        }
    }

    wachthond($extdebug, 1, "########################################################################");
    wachthond($extdebug, 1, "### WERVING [PRE] 3.3 LOGICA: STATUS MEE (UPDATE FISCAL YEAR)",    "[MEE]");
    wachthond($extdebug, 1, "########################################################################");

    // -------------------------------------------------------------------------
    // 0. BEPAAL CONTEXT VAN DE UPDATE DATUM
    // -------------------------------------------------------------------------
    
    // We zoeken het kamp/seizoen dat hoort bij de update datum ($temp_mee_update)
    $mee_update_nextkamp_lastnext           = find_lastnext($temp_mee_update);     
    $mee_update_nextkamp_start_date         = $mee_update_nextkamp_lastnext['next_start_date']      ?? NULL;
    $mee_update_nextkamp_einde_date         = $mee_update_nextkamp_lastnext['next_einde_date']      ?? NULL;
    
    // Op basis van de startdatum van dat kamp, bepalen we het boekjaar
    $mee_update_nextkamp_fiscalyear         = curriculum_civicrm_fiscalyear($mee_update_nextkamp_start_date);
    $mee_update_nextkamp_fiscalyear_start   = $mee_update_nextkamp_fiscalyear['fiscalyear_start']   ?? NULL;
    $mee_update_nextkamp_fiscalyear_einde   = $mee_update_nextkamp_fiscalyear['fiscalyear_einde']   ?? NULL;

    wachthond($extdebug, 3, "DEBUG: Temp Mee Update Datum",       $temp_mee_update);
    wachthond($extdebug, 3, "DEBUG: Context Kamp Start",          $mee_update_nextkamp_start_date);
    wachthond($extdebug, 3, "DEBUG: Context Fiscal Year Start",   $mee_update_nextkamp_fiscalyear_start);

    // We kunnen alleen rekenen als we een fiscal startdatum hebben gevonden
    if (!empty($mee_update_nextkamp_fiscalyear_start)) {

        // -------------------------------------------------------------------------
        // 1. REGEL: BINNEN DAT SPECIFIEKE SEIZOEN -> UPGRADE VERWACHTING
        // -------------------------------------------------------------------------
        // Als de datum van belangstelling NA de start van het boekjaar van de update valt.
        // Datum Belangstelling >= Update Fiscal Start
        
        if (date_biggerequal($val_datum_belangstelling, $mee_update_nextkamp_fiscalyear_start) == 1) {
            
            // Upgrade 'nee' naar 'misschien' omdat het een nieuwe aanmelding in dit seizoen is
            if (in_array($val_mee_verwachting, array('zekerniet','waarschijnlijkniet','weetnogniet')) OR empty($val_mee_verwachting)) {
                $new_mee_verwachting = "misschien";
                wachthond($extdebug, 3, "LOGIC: Verwachting Upgrade", "Datum in context seizoen -> Zet op 'misschien'");
            }
        
        // -------------------------------------------------------------------------
        // 2. REGEL: OUDER DAN DAT SEIZOEN -> STATUS ONBEKEND (INDIEN LEEG)
        // -------------------------------------------------------------------------
        // Als de start van het context-seizoen groter is dan de datum (dus datum ligt in verleden).
        // Update Fiscal Start >= Datum Belangstelling
        
        } elseif (date_biggerequal($mee_update_nextkamp_fiscalyear_start, $val_datum_belangstelling) == 1) {
            
            if (empty($val_mee_status)) {
                $new_mee_status = 'onbekend';
                wachthond($extdebug, 3, "LOGIC: Status Fallback", "Datum ouder dan context seizoen -> Zet op 'onbekend'");
            }
        }
    }

    // Sync de activiteit
    werving_civicrm_activitymee($contact_id, $temp_mee_update, $displayname);

    wachthond($extdebug, 1, "########################################################################");
    wachthond($extdebug, 1, "### WERVING [PRE] 3.3 LOGICA: BEPAAL VAKANTIEREGIO",            "[REGIO]");
    wachthond($extdebug, 1, "########################################################################");

    $calc_vakantieregio = werving_civicrm_vakantieregio($contact_id);
    if (empty($werving_vakantieregio) AND !empty($calc_vakantieregio)) {
        $new_vakantieregio = (string)$calc_vakantieregio;
    }

    wachthond($extdebug, 3, "new_vakantieregio",            $new_vakantieregio);
    
    wachthond($extdebug, 1, "########################################################################");
    wachthond($extdebug, 1, "### WERVING [PRE] 3.4 LOGICA: LEEFTIJDEN",                   "[LEEFTIJD]");
    wachthond($extdebug, 1, "########################################################################");

    // FIX: Forceer (float)/(int) om PHP 8 errors in Webform te voorkomen.
    $leeftijd_vantoday = leeftijd_civicrm_diff('vandaag',  $birthdate, $today_datetime);
    
    if (isset($leeftijd_vantoday['leeftijd_decimalen'])) $new_leeftijd_decimalen = (float)$leeftijd_vantoday['leeftijd_decimalen'];
    if (isset($leeftijd_vantoday['leeftijd_rondjaren'])) $new_leeftijd_rondjaren = (int)$leeftijd_vantoday['leeftijd_rondjaren'];

    $lastnext_kamp_fromtoday = find_lastnext($today_datetime);
    $nextkamp_start_date     = $lastnext_kamp_fromtoday['next_start_date'];
    $leeftijd_nextkamp       = leeftijd_civicrm_diff('nextkamp',  $birthdate, $nextkamp_start_date);

    if (isset($leeftijd_nextkamp['leeftijd_decimalen'])) $new_nextkamp_decimalen = (float)$leeftijd_nextkamp['leeftijd_decimalen'];
    if (isset($leeftijd_nextkamp['leeftijd_rondjaren'])) $new_nextkamp_rondjaren = (int)$leeftijd_nextkamp['leeftijd_rondjaren'];
    if (isset($leeftijd_nextkamp['leeftijd_rondmaand'])) $new_nextkamp_rondmaand = (int)$leeftijd_nextkamp['leeftijd_rondmaand'];

    wachthond($extdebug, 1, "########################################################################");
    wachthond($extdebug, 1, "### WERVING [PRE] 3.4 RUN ACL VOOR BELANGSTELLENDE",              "[ACL]");
    wachthond($extdebug, 1, "########################################################################");

    if ($val_datum_belangstelling) {
        werving_civicrm_acl($contact_id, $val_datum_belangstelling);
    }

    wachthond($extdebug, 1, "########################################################################");
    wachthond($extdebug, 1, "### WERVING [PRE] 4.0 INJECTIE EN EXTERNAL SAVE", "");
    wachthond($extdebug, 1, "########################################################################");

    // Verzamel alle berekende 'new_' variabelen
    $data_to_inject = [];
    if (isset($new_mee_update))          $data_to_inject['WERVING.mee_update']          = $new_mee_update;
    if (isset($new_mee_update_year))     $data_to_inject['WERVING.mee_update_year']     = $new_mee_update_year;
    if (isset($new_mee_verwachting))     $data_to_inject['WERVING.mee_verwachting']     = $new_mee_verwachting;
    if (isset($new_mee_status))          $data_to_inject['WERVING.mee_status']          = $new_mee_status;
    if (isset($new_vakantieregio))       $data_to_inject['WERVING.vakantieregio']       = $new_vakantieregio;
    if (isset($new_leeftijd_decimalen))  $data_to_inject['WERVING.leeftijd_decimalen']  = $new_leeftijd_decimalen;
    if (isset($new_leeftijd_rondjaren))  $data_to_inject['WERVING.leeftijd_rondjaren']  = $new_leeftijd_rondjaren;
    if (isset($new_nextkamp_decimalen))  $data_to_inject['WERVING.nextkamp_decimalen']  = $new_nextkamp_decimalen;
    if (isset($new_nextkamp_rondjaren))  $data_to_inject['WERVING.nextkamp_rondjaren']  = $new_nextkamp_rondjaren;
    if (isset($new_nextkamp_rondmaand))  $data_to_inject['WERVING.nextkamp_rondmaand']  = $new_nextkamp_rondmaand;

    $external_updates = [];
    if ($extwrite == 1 && !empty($data_to_inject)) {
        // Gebruik de slimme injectie functie die beslist: Params of API?
        $external_updates = werving_inject_params($params, $data_to_inject, $field_ids);
    }

    // -------------------------------------------------------------------------
    // STAP 5: API SAVE (VOOR DE RESTANTEN)
    // -------------------------------------------------------------------------

    if (!empty($external_updates) && $entityID > 0) {
        wachthond($extdebug, 1, "########################################################################");
        wachthond($extdebug, 1, "### WERVING [PRE] 5.0 API SAVE (EXTERNAL FIELDS)", "");
        wachthond($extdebug, 1, "########################################################################");

        $external_updates['id'] = $entityID;
        try {
            civicrm_api3('Contact', 'create', $external_updates);
            wachthond($extdebug, 1, "API SAVE SUCCESS: " . count($external_updates) . " fields saved.");
        } catch (\Exception $e) {
            wachthond(1, 1, "API SAVE ERROR: " . $e->getMessage());
        }
    }

    if (function_exists('drupal_timestamp_sweep')) {
        drupal_timestamp_sweep($params);
    }
    
    wachthond($extdebug, 1, "########################################################################");
    wachthond($extdebug, 1, "### WERVING [PRE] EINDE", "");
    wachthond($extdebug, 1, "########################################################################");

    $processing_werving_custompre = false;
}

// -----------------------------------------------------------------------------
// HELPER FUNCTIES
// -----------------------------------------------------------------------------

/**
 * Injecteert waarden in params OF zet ze apart voor API update.
 * Voorkomt de "DB Error: no such field" in Webform context.
 * Voorkomt de "TypeError: string * int" door te casten.
 */
function werving_inject_params(array &$params, array $data, array $field_ids): array {

    $extdebug = 3;
    
    $external_updates = [];

    foreach ($data as $internal_name => $value) {
        
        $field_id = $field_ids[$internal_name] ?? null;
        if (!$field_id) continue;

        if ($value === "") $value = null;

        // --- DE FIX VOOR DE FATAL ERROR ---
        // Zorg dat numerieke strings ("18.2") echte getallen worden (18.2)
        if ($value !== null && is_numeric($value)) {
            if (strpos((string)$value, '.') !== false) {
                $value = (float)$value;
            } else {
                $value = (float)$value; // Float is veilig voor zowel 18 als 18.2
            }
        }
        // ----------------------------------

        $param_found = false;
        $flat_key    = 'custom_' . $field_id;

        // A. Check Webform structuur
        foreach ($params as $k => $v) {
            if (is_array($v) && isset($v['custom_field_id']) && $v['custom_field_id'] == $field_id) {
                $params[$k]['value'] = $value;
                $param_found = true;
                wachthond($extdebug, 3, "INJECT [NESTED] $internal_name", $value);
                break; 
            }
        }

        // B. Check Backend structuur
        if (!$param_found && array_key_exists($flat_key, $params)) {
            $params[$flat_key] = $value;
            $param_found = true;
            wachthond($extdebug, 3, "INJECT [FLAT] $internal_name", $value);
        }

        // C. Niet gevonden in params? -> Naar API wachtrij
        if (!$param_found && $value !== null) {
            $external_updates[$flat_key] = $value;
            wachthond($extdebug, 3, "DEFER [API] $internal_name", $value);
        }
    }

    return $external_updates;
}

function werving_civicrm_vakantieregio($contactid) {

    $extdebug   = 0;  //  1 = basic // 2 = verbose // 3 = params / 4 = results
    $apidebug   = FALSE;

    if (empty($contactid)) {
        return;
    } else {
        $contact_id     = $contactid;
    }

    $extwrite           = 1;
    $extwerving         = 1;

    $profilewerving     = array(270);

    wachthond($extdebug,1, "########################################################################");
    wachthond($extdebug,1, "### REGIO 1.1 GET ADRESGEGEVENS & LOOKUP VAKANTIEREGIO", "[CID: $contact_id]");
    wachthond($extdebug,1, "########################################################################");

    if ($contact_id > 0) {

        $params_adres_get = [
            'checkPermissions' => FALSE,
            'debug'  => $apidebug,              
            'select' => [
                'id',
                'display_name',
                'birth_date',
                'address_primary.id',
                'address_primary.street_address',
                'address_primary.street_number',
                'address_primary.street_number_suffix',
                'address_primary.postal_code',
                'address_primary.city',
                'address_primary.Adresgegevens.Gemeente',
                'address_primary.Adresgegevens.Provincie',
                'WERVING.vakantieregio',
            ],
            'where' => [
                ['id',              'IN', [$contact_id]],
            ],
        ];
        
        wachthond($extdebug,7, 'params_adres_get',          $params_adres_get);
        $result_adres_get    = civicrm_api4('Contact','get',$params_adres_get);
        wachthond($extdebug,9, 'result_adres_get',          $result_adres_get);

        if (isset($result_adres_get))    {
            $displayname            = $result_adres_get[0]['display_name']                                              ?? NULL;
            $adres_id               = $result_adres_get[0]['address_primary.id']                                        ?? NULL;
            $adres_street_address   = trim($result_adres_get[0]['address_primary.street_address']           ?? '')      ?? NULL;
            $adres_street_number    = trim($result_adres_get[0]['address_primary.street_number']            ?? '')      ?? NULL;
            $adres_street_suffix    = trim($result_adres_get[0]['address_primary.street_number_suffix']     ?? '')      ?? NULL;
            $adres_postcode         = trim($result_adres_get[0]['address_primary.postal_code']              ?? '')      ?? NULL;
            $adres_plaats           = trim($result_adres_get[0]['address_primary.city']                     ?? '')      ?? NULL;
            $adres_gemeente         = trim($result_adres_get[0]['address_primary.Adresgegevens.Gemeente']   ?? '')      ?? NULL;
            $adres_provincie        = trim($result_adres_get[0]['address_primary.Adresgegevens.Provincie']  ?? '')      ?? NULL;
            $werving_vakantieregio  = trim($result_adres_get[0]['WERVING.vakantieregio'])                   ?? NULL;

            if ($adres_postcode) { $adres_postcode             = str_replace(' ', '',  $adres_postcode); }

            wachthond($extdebug,1, 'displayname',               $displayname);
            wachthond($extdebug,2, 'adres_id',                  $adres_id);
            wachthond($extdebug,1, 'adres_street_address',      $adres_street_address);
            wachthond($extdebug,2, 'adres_street_number',       $adres_street_number);
            wachthond($extdebug,2, 'adres_street_suffix',       $adres_street_suffix);
            wachthond($extdebug,1, 'adres_postcode',            $adres_postcode);
            wachthond($extdebug,1, 'adres_plaats',              $adres_plaats);
            wachthond($extdebug,1, 'adres_gemeente',            $adres_gemeente);
            wachthond($extdebug,2, 'adres_provincie',           $adres_provincie);
            wachthond($extdebug,2, 'werving_vakantieregio',     $werving_vakantieregio);
        }

        if (empty($adres_streetnumber) AND !empty($adres_streetaddress)) {

            wachthond($extdebug,2, "########################################################################");
            wachthond($extdebug,2, "### REGIO 1.2 SPLIT STRAAT, HUISNUMMER EN SUFFIX EN SCHRIJF NAAR DB");
            wachthond($extdebug,2, "########################################################################");

            $splitstreetaddress = splitstreetaddress($adres_streetaddress);
            wachthond($extdebug,2, 'splitstreetaddress', $splitstreetaddress);

            $adres_street_name      = $splitstreetaddress['street_name'];
            $adres_street_number    = $splitstreetaddress['street_number'];
            $adres_street_suffix    = $splitstreetaddress['street_suffix'];

            wachthond($extdebug,1, 'adres_street_name',     $adres_street_name);
            wachthond($extdebug,1, 'adres_street_number',   $adres_street_number);
            wachthond($extdebug,1, 'adres_street_suffix',   $adres_street_suffix); 

            $params_update_adres = [
                'checkPermissions' => FALSE,
                'debug' => $apidebug,
                'where' => [
                    ['id',          '=', $adres_id],
                ],
                'values' => [
                    'street_name'           => $adres_street_name,
                    'street_number'         => $adres_street_number,
                    'street_number_suffix'  => $adres_street_suffix,
                ],
            ];

            wachthond($extdebug,3, 'params_update_adres',               $params_update_adres);
            if ($adres_street_name AND $adres_street_number) {
                $result_update_adres = civicrm_api4('Address','update', $params_update_adres);
            }
            wachthond($extdebug,9, 'result_update_adres',               $result_update_adres);

        }

        wachthond($extdebug,2, "########################################################################");
        wachthond($extdebug,2, "### REGIO 1.3 ZOEK GEGEVENS OP VIA PRO6PP API MET POSTCODE / HUISNUMMER");
        wachthond($extdebug,2, "########################################################################");

        wachthond($extdebug,1, 'contact_id',                $contact_id);
        wachthond($extdebug,1, 'adres_postcode',            $adres_postcode);
        wachthond($extdebug,2, 'adres_street_number',       $adres_street_number);
        wachthond($extdebug,2, 'adres_street_suffix',       $adres_street_suffix);

        if ($adres_postcode AND $adres_street_number) {

            wachthond($extdebug,4, 'LOOKUP ADDRESS IN PRO6PP DATABASE BY POSTCODE / NUMBER');
            $adres_array = werving_civicrm_pro6pp_postcode($contact_id, $adres_postcode, $adres_street_number, $adres_street_suffix);

            $adres_postcode         = $adres_array['adres_postcode'];
            $adres_street_name      = $adres_array['adres_street_name'];
            $adres_street_number    = $adres_array['adres_street_number'];
            $adres_street_suffix    = $adres_array['adres_street_suffix'];
            $adres_plaats           = $adres_array['adres_plaats'];
            $adres_gemeente         = $adres_array['adres_gemeente'];
            $adres_provincie        = $adres_array['adres_provincie'];

            $adres_lat              = $adres_array['adres_lat'];
            $adres_lng              = $adres_array['adres_lng'];

            $adres_construction     = $adres_array['adres_construction'];
            $adres_surfacearea      = $adres_array['adres_surfacearea'];

            wachthond($extdebug,3, 'adres_postcode',        $adres_postcode);
            wachthond($extdebug,3, 'adres_street_name',     $adres_street_name);
            wachthond($extdebug,3, 'adres_street_number',   $adres_street_number);
            wachthond($extdebug,3, 'adres_plaats',          $adres_plaats);
            wachthond($extdebug,3, 'adres_gemeente',        $adres_gemeente);
            wachthond($extdebug,3, 'adres_provincie',       $adres_provincie);

            wachthond($extdebug,3, 'adres_lat',             $adres_lat);
            wachthond($extdebug,3, 'adres_lng',             $adres_lng);

            wachthond($extdebug,3, 'adres_construction',    $adres_construction);
            wachthond($extdebug,3, 'adres_surfacearea',     $adres_surfacearea);

        }

        ##########################################################################################
        ### RETURN ALS ER GEEN ADRESGEGEVENS ZIJN GEVONDEN
        ##########################################################################################        

        if (empty($adres_postcode) AND empty($adres_plaats) AND empty($adres_streetaddress)) {
            return;
        }

        if (empty($adres_gemeente) AND !empty($adres_plaats)) {

            wachthond($extdebug,2, "########################################################################");
            wachthond($extdebug,2, "### REGIO 1.3 ZOEK GEMEENTE OP VIA PRO6PP API INDIEN DEZE ONTBREEKT");
            wachthond($extdebug,2, "########################################################################");

            $pro6pp_key =  "6P84FpVsdq5pRunD";
            $pro6pp_url =  "https://api.pro6pp.nl/v2/suggest/nl/settlement?settlement=$adres_plaats&authKey=$pro6pp_key";

            wachthond($extdebug,4, 'pro6pp_url', $pro6pp_url);

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL             => $pro6pp_url,
                CURLOPT_RETURNTRANSFER  => true,
                CURLOPT_ENCODING        => "",
                CURLOPT_MAXREDIRS       => 10,
                CURLOPT_TIMEOUT         => 30,
                CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST   => "GET",
                CURLOPT_HTTPHEADER      => [
                    "x-api-key: $pro6pp_key"
                ],
            ]);

            $response           = curl_exec($curl);
            $err                = curl_error($curl);

            if ($err) {
                wachthond($extdebug,2, 'PRO6PP cURL Error #:', $err);
            } else {
                $pro6pp_result  = json_decode($response, true);
                $jobsArray      = $pro6pp_result['data'];
            }

            curl_close($curl);

            wachthond($extdebug,2, 'pro6pp_result',     $pro6pp_result);

            $adres_gemeente     = $pro6pp_result[0]['municipality'];
            $adres_provincie    = $pro6pp_result[0]['province'];

            wachthond($extdebug,2, 'adres_gemeente',    $adres_gemeente);
            wachthond($extdebug,2, 'adres_provincie',   $adres_provincie);

            $params_update_adres = [
                'checkPermissions' => FALSE,
                'debug' => $apidebug,
                'where' => [
                    ['id',          '=', $adres_id],
//                  ['contact_id',  '=', $contact_id],
                ],
                'values' => [
                    'Adresgegevens.Gemeente'    => $adres_gemeente,
                    'Adresgegevens.Provincie'   => $adres_provincie,
                ],
            ];

            wachthond($extdebug,3, 'params_update_adres',               $params_update_adres);
            if ($adres_gemeente AND $adres_provincie) {
                $result_update_adres = civicrm_api4('Address','update', $params_update_adres);
            }
            wachthond($extdebug,9, 'result_update_adres',               $result_update_adres);
        }

        if (!empty($adres_gemeente)) {

            wachthond($extdebug,1, "########################################################################");
            wachthond($extdebug,1, "### REGIO 1.4 ZOEK VAKANTIEREGIO OP",                     $adres_gemeente);
            wachthond($extdebug,1, "########################################################################");

            $params_options_regio = [
                'checkPermissions' => FALSE,
                    'debug' => $apidebug,
                    'select' => [
                        'value',
                        'label', 
                        'name',                         
                        'description', 
                    ],
                    'where' => [
                        ['option_group_id', '=', 722], 
                        ['value',           '=', $adres_gemeente],
                    ],
            ];
            wachthond($extdebug,7, 'params_options_regio',                  $params_options_regio);
            $result_options_regio = civicrm_api4('OptionValue', 'get',      $params_options_regio);
            wachthond($extdebug,9, 'result_options_regio',                  $result_options_regio);

            $regio_value        = $result_options_regio[0]['value']         ?? NULL;
            $regio_label        = $result_options_regio[0]['label']         ?? NULL;
            $regio_name         = $result_options_regio[0]['name']          ?? NULL;
            $regio_description  = $result_options_regio[0]['description']   ?? NULL;

            $regio_label        = strtolower(trim($regio_label));

            wachthond($extdebug,1, 'regio_value',       $regio_value);
            wachthond($extdebug,1, 'regio_label',       $regio_label);
            wachthond($extdebug,1, 'regio_name',        $regio_name);
            wachthond($extdebug,4, 'regio_description', $regio_description);
        }

        if (!empty($adres_plaats)) {

            wachthond($extdebug,1, "########################################################################");
            wachthond($extdebug,1, "### REGIO 1.5 HOUD REKENING MET UITZONDERINGEN",            $adres_plaats);
            wachthond($extdebug,1, "########################################################################");

            if (in_array($adres_plaats, array("Eemnes", "Abcoude"))) {
                $regio_name = '1noord';
                wachthond($extdebug,2, "alsnog regio noord voor $adres_plaats uit de gemeente $adres_gemeente",                  "[toch noord]");
            }

            ######################################################################
            # NOORD
            # Flevoland Alle gemeenten behalve Zeewolde
            # Gelderland    (gemeente) Hattem
            # Utrecht   Eemnes en wat vroeger gemeente Abcoude was
            ######################################################################

            if (in_array($adres_plaats, array("Zeewolde", "Werkendam", "Sleeuwijk", "Nieuwendijk", "Woudrichem"))) {
                $regio_name = '2midden';
                wachthond($extdebug,2, "alsnog regio midden voor $adres_plaats uit de gemeente $adres_gemeente",                  "[toch midden]");
            }

            //        Almkerk, Andel, Babyloniënbroek, Drongelen, Dussen, Eethen, Genderen, Giessen, Hank, Meeuwen, Nieuwendijk, Rijswijk (Altena), Sleeuwijk, Uitwijk, Veen, Waardhuizen, Werkendam, Wijk en Aalburg, Woudrichem.

            ######################################################################
            # MIDDEN
            # Noord-Brabant     Altena (behalve de kernen Hank en Dussen)
            # Flevoland         (gemeente) Zeewolde
            # Montferland       behalve wat vroeger gemeente Didam was
            # Utrecht           behalve Eemnes en wat vroeger gemeente Abcoude was
            ######################################################################

            if (in_array($adres_plaats, array("Didam", "Dodewaard", "Hank", "Dussen"))) {
                $regio_name = '3zuid';
                wachthond($extdebug,2, "alsnog regio zuid voor $adres_plaats uit de gemeente $adres_gemeente",                  "[toch zuid]");
            }

            // M61: op basis van eigen onderzoek ook nog deze uitzonderingen voor de gemeente Altena
            // Midden: Almkerk, Waardhuizen 

            if (in_array($adres_plaats, array("Babyloniënbroek", "Wijk En Aalburg", "Dussen", "Meeuwen", "Eethen", "Genderen", "Drongelen"))) {
                $regio_name = '3zuid';
                wachthond($extdebug,2, "alsnog regio zuid voor $adres_plaats uit de gemeente $adres_gemeente",                  "[toch zuid]");
            }

            ######################################################################
            # ZUID
            # Neder-Betuwe (alleen wat vroeger gemeente Dodewaard was)
            # Noord-Brabant Alle gemeenten behalve Woudrichem 
            # en de kernen Sleeuwijk, Nieuwendijk en Werkendam in de gemeente Altena 
            ######################################################################
        }

        wachthond($extdebug,1, "########################################################################");
        wachthond($extdebug,1, "### REGIO 1.6 UPDATE ADRES MET NIEUWE / AANVULLENDE WAARDEN");
        wachthond($extdebug,1, "########################################################################");

        $adres_update_array = array(

            'adres_postcode'        => $adres_postcode,
            'adres_street_name'     => $adres_street_name,
            'adres_street_number'   => $adres_street_number,
            'adres_plaats'          => $adres_plaats,
            'adres_gemeente'        => $adres_gemeente,
            'adres_provincie'       => $adres_provincie,

            'adres_lat'             => $adres_lat,
            'adres_lng'             => $adres_lng,

            'adres_construction'    => $adres_construction,
            'adres_surfacearea'     => $adres_surfacearea,

            'adres_vakantieregio'   => $regio_name,
        );

        wachthond($extdebug,3, "adres_id",              $adres_id);
        wachthond($extdebug,3, "adres_update_array",    $adres_update_array);

        werving_civicrm_address_update($contact_id, $adres_id, $adres_update_array);

        if (!empty($regio_name)) {

            wachthond($extdebug,1, "########################################################################");
            wachthond($extdebug,1, "NIEUWE WAARDE VAKANTIEREGIO ($regio_name)",                  $regio_label);
            wachthond($extdebug,1, "########################################################################");        

            return $regio_name;
        }

    }
}

function werving_civicrm_vakantieregio_write($contactid, $regio) {

    $extdebug       = 0;  //  1 = basic // 2 = verbose // 3 = params / 4 = results
    $apidebug       = FALSE;

    $contact_id     =   $contactid;
    $vakantieregio  =   $regio;

    wachthond($extdebug,2, "########################################################################");
    wachthond($extdebug,1, "### REGIO WRITE - RETREIVE CURRENT VALUES",          "[CID: $contact_id]");
    wachthond($extdebug,2, "########################################################################");

    if ($contact_id > 0) {

        $params_contact_get = [
            'checkPermissions' => FALSE,
            'debug'  => $apidebug,              
            'select' => [
                'display_name',
                'WERVING.vakantieregio',
            ],
            'where' => [
                ['id',  'IN', [$contact_id]],
            ],
        ];
    }        
    wachthond($extdebug,7, 'params_contact_get',            $params_contact_get);
    $result_contact_get    = civicrm_api4('Contact','get',  $params_contact_get);
    wachthond($extdebug,9, 'result_contact_get',            $result_contact_get);

    if (isset($result_contact_get))    {
        $displayname            = $result_contact_get[0]['display_name']            ?? NULL;
        $werving_vakantieregio  = $result_contact_get[0]['WERVING.vakantieregio']   ?? NULL;

        wachthond($extdebug,1, 'displayname',               $displayname);
        wachthond($extdebug,1, 'werving_vakantieregio',     $werving_vakantieregio);
    }

    wachthond($extdebug,2, "########################################################################");
    wachthond($extdebug,1, "### REGIO WRITE - SCHRIJF WAARDE NAAR DB",      "[REGIO: $vakantieregio]");
    wachthond($extdebug,2, "########################################################################");

    if (!empty($werving_vakantieregio)) {
        wachthond($extdebug,1, 'SKIP: vakantieregio had al een waarde in de database', "$werving_vakantieregio");
        return;
    }

    if (in_array($vakantieregio, array("1noord", "2midden", "3zuid"))) {

        $params_update_vakantieregio = [
            'checkPermissions' => FALSE,
            'debug' => $apidebug,
            'where' => [
                ['id', '=', $contact_id],
            ],
            'values' => [
                'WERVING.vakantieregio' => $vakantieregio,
            ],
        ];
        wachthond($extdebug,3, 'params_update_vakantieregio',           $params_update_vakantieregio);
        wachthond($extdebug,1, 'DONE: vakantieregio via api4 naar database geschreven', "$vakantieregio");        
        $result_update_vakantieregio = civicrm_api4('Contact','update', $params_update_vakantieregio);
        wachthond($extdebug,3, 'result_update_vakantieregio',           $result_update_vakantieregio);
    }
}

function werving_civicrm_pro6pp_postcode($contactid, $postcode, $huisnummer, $nummersuffix = NULL) {

    $extdebug               = 0;  //  1 = basic // 2 = verbose // 3 = params / 4 = results
    $apidebug               = FALSE;

    $contact_id             =   $contactid;
    $adres_postcode         =   $postcode;
    $adres_street_number    =   $huisnummer;
    $adres_street_suffix    =   $nummersuffix;

    wachthond($extdebug,2, "########################################################################");
    wachthond($extdebug,2, "### PRO6PP - ZOEK GEGEVENS OP VIA PRO6PP API MET POSTCODE / HUISNUMMER");
    wachthond($extdebug,2, "########################################################################");

    wachthond($extdebug,2, 'adres_postcode',        $adres_postcode);
    wachthond($extdebug,1, 'adres_street_number',   $adres_street_number);
    wachthond($extdebug,1, 'adres_street_suffix',   $adres_street_suffix); 

    if (empty($adres_postcode) OR empty($adres_street_number)) {
        return;
    }

    $pro6pp_key     =  "6P84FpVsdq5pRunD";
    $pro6pp_url     =  "https://api.pro6pp.nl/v2/autocomplete/nl?postalCode=$adres_postcode&streetNumber=$adres_street_number&authKey=$pro6pp_key";

    wachthond($extdebug,4, 'pro6pp_url', $pro6pp_url);

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL             => $pro6pp_url,
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_ENCODING        => "",
        CURLOPT_MAXREDIRS       => 10,
        CURLOPT_TIMEOUT         => 30,
        CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST   => "GET",
        CURLOPT_HTTPHEADER      => [
            "x-api-key: $pro6pp_key"
        ],
    ]);

    $response           = curl_exec($curl);
    $err                = curl_error($curl);

    if ($err) {
        wachthond($extdebug,2, 'PRO6PP cURL Error #:', $err);
    } else {
        $pro6pp_result  = json_decode($response, true);
        $jobsArray      = $pro6pp_result['data'];
    }

    curl_close($curl);

    wachthond($extdebug,2, 'pro6pp_result', $pro6pp_result);

    $adres_postcode         = $pro6pp_result['postalCode'];
    $adres_street_name      = $pro6pp_result['street'];
    $adres_street_number    = $pro6pp_result['streetNumber'];    
    $adres_plaats           = $pro6pp_result['settlement'];
    $adres_gemeente         = $pro6pp_result['municipality'];
    $adres_provincie        = $pro6pp_result['province'];

    $adres_lat              = $pro6pp_result['lat'];
    $adres_lng              = $pro6pp_result['lng'];

    $adres_construction     = $pro6pp_result['constructionYear'];
    $adres_surfacearea      = $pro6pp_result['surfaceArea'];

    wachthond($extdebug,2, 'adres_postcode',        $adres_postcode);
    wachthond($extdebug,2, 'adres_street_name',     $adres_street_name);
    wachthond($extdebug,2, 'adres_street_number',   $adres_street_number);
    wachthond($extdebug,2, 'adres_plaats',          $adres_plaats);
    wachthond($extdebug,2, 'adres_gemeente',        $adres_gemeente);
    wachthond($extdebug,2, 'adres_provincie',       $adres_provincie);

    wachthond($extdebug,2, 'adres_lat',             $adres_lat);
    wachthond($extdebug,2, 'adres_lng',             $adres_lng);

    wachthond($extdebug,2, 'adres_construction',    $adres_construction);
    wachthond($extdebug,2, 'adres_surfacearea',     $adres_surfacearea);

    $pro6ppresult_array = array(

        'adres_postcode'        => $adres_postcode,
        'adres_street_name'     => $adres_street_name,
        'adres_street_number'   => $adres_street_number,
        'adres_plaats'          => $adres_plaats,
        'adres_gemeente'        => $adres_gemeente,
        'adres_provincie'       => $adres_provincie,

        'adres_lat'             => $adres_lat,
        'adres_lng'             => $adres_lng,

        'adres_construction'    => $adres_construction,
        'adres_surfacearea'     => $adres_surfacearea,
    );

    return $pro6ppresult_array;
}


function splitstreetaddress($streetAddress) {
    
    $result = array();
    /*
     * do nothing if streetAddress is empty
     */

    if (!empty($streetAddress)) {
        /*
         * split into parts separated by spaces
         */

        $addressParts       = explode(" ", $streetAddress);
        $foundStreetNumber  = false;
        $streetName         = null;
        $streetNumber       = null;
        $streetUnit         = null;
 
        foreach($addressParts as $partKey => $addressPart) {

            /*
             * if the part is numeric, there are several possibilities:
             * - if the partKey is 0 so it is the first element, it is
             *   assumed it is part of the street_name to cater for 
             *   situation like 2e Wormenseweg
             * - if not the first part and there is no street_number yet (foundStreetNumber
             *   is false), it is assumed this numeric part contains the street_number
             * - if not the first part but we already have a street_number (foundStreetNumber
             *   is true) it is assumed this is part of the street_unit
             */

            if (is_numeric($addressPart)) {
 
                if ($foundStreetNumber == false) {
                    $streetNumber = $addressPart;
                    $foundStreetNumber = true;
                } else {
                    $streetUnit .= " ".$addressPart;
                }
 
            } else {
                /*
                 * if part is not numeric, there are several possibilities:
                 * - if the street number is found, set the whole part to streetUnit
                 * - if there is no streetNumber yet and it is the first part, set the
                 *   whole part to streetName
                 * - if there is no streetNumber yet and it is not the first part,
                 *   check all digits:
                 *   - if the first digit is numeric, put the numeric part in streetNumber
                 *     and all non-numerics to street_unit
                 *   - if the first digit is not numeric, put the lot into streetName
                 */
 
                if ($foundStreetNumber == true) {
 
                    if (!empty($streetName)) {
                        $streetUnit .= " ".$addressPart;
                    } else {
                        $streetName .= " ".$addressPart;
                    }
 
                } else {
 
                    if ($partKey == 0) {
                        $streetName .= $addressPart;
                    } else {
 
                        $partLength = strlen($addressPart);
 
                        if (is_numeric(substr($addressPart, 0, 1))) {
 
                            for ($i=0; $i<$partLength; $i++) {
 
                                if (is_numeric(substr($addressPart, $i, 1))) {
                                    $streetNumber .= substr($addressPart, $i, 1);
                                    $foundStreetNumber = true;
                                } else {
                                    $streetUnit .= " ".substr($addressPart, $i, 1);
                                }
                            }
                        } else {
                            $streetName .= " ".$addressPart;
                        }
                    }
                }
            }
        }
        $result['street_name']   = trim($streetName);
        $result['street_number'] = $streetNumber;
        $result['street_suffix'] = trim($streetUnit);
        /*
         * if we still have no street_number, add contact to checkgroup
         */
        
    }

    return $result;
}

function werving_civicrm_address_update($contactid, $adresid, $adres_array) {

    $extdebug               = 0;  //  1 = basic // 2 = verbose // 3 = params / 4 = results
    $apidebug               = FALSE;

    $contact_id             = $contactid;
    $address_id             = $adresid;

    wachthond($extdebug,2, "########################################################################");
    wachthond($extdebug,2, "### ADRES - UPDATE PRIMARY ADRES MET NIEUWE GEGEVENS",          "[START]");
    wachthond($extdebug,2, "########################################################################");

    $adres_postcode         = $adres_array['adres_postcode'];
    $adres_street_name      = $adres_array['adres_street_name'];
    $adres_street_number    = $adres_array['adres_street_number'];
    $adres_street_suffix    = $adres_array['adres_street_suffix'];

    if ($adres_street_name AND $adres_street_number) {
        $adres_street_address   = "$adres_street_name $adres_street_number$adres_street_suffix";
    }

    $adres_plaats           = $adres_array['adres_plaats'];
    $adres_gemeente         = $adres_array['adres_gemeente'];
    $adres_provincie        = $adres_array['adres_provincie'];

    $adres_lat              = $adres_array['adres_lat'];
    $adres_lng              = $adres_array['adres_lng'];

    $adres_construction     = $adres_array['adres_construction'];
    $adres_surfacearea      = $adres_array['adres_surfacearea'];

    $adres_vakantieregio    = $adres_array['adres_vakantieregio'];

    wachthond($extdebug,2, 'contact_id',            $contact_id);
    wachthond($extdebug,2, 'address_id',            $address_id);

    wachthond($extdebug,2, 'adres_postcode',        $adres_postcode);
    wachthond($extdebug,2, 'adres_street_name',     $adres_street_name);
    wachthond($extdebug,2, 'adres_street_number',   $adres_street_number);
    wachthond($extdebug,2, 'adres_street_address',  $adres_street_address);

    wachthond($extdebug,2, 'adres_plaats',          $adres_plaats);
    wachthond($extdebug,2, 'adres_gemeente',        $adres_gemeente);
    wachthond($extdebug,2, 'adres_provincie',       $adres_provincie);

    wachthond($extdebug,2, 'adres_lat',             $adres_lat);
    wachthond($extdebug,2, 'adres_lng',             $adres_lng);

    wachthond($extdebug,2, 'adres_construction',    $adres_construction);
    wachthond($extdebug,2, 'adres_surfacearea',     $adres_surfacearea);

    $params_update_adres = [
        'checkPermissions' => FALSE,
        'debug' => $apidebug,
        'where' => [
            ['id',          '=', $address_id],
//          ['contact_id',  '=', $contact_id],
        ],
        'values' => [
            'postal_code'               => $adres_postcode,
//          'street_name'               => $adres_street_name,
//          'street_number'             => $adres_street_number,
//          'street_number_suffix'      => $adres_street_suffix,
//          'city'                      => $adres_plaats,
//          'Adresgegevens.Gemeente'    => $adres_gemeente,
//          'Adresgegevens.Provincie'   => $adres_provincie,
//          'geo_code_1'                => $adres_lat,
//          'geo_code_2'                => $adres_lng,
//          'street_type'               => $adres_surfacearea,
        ],
    ];

    if ($adres_postcode)        { $params_update_adres['values']['postal_code']             = $adres_postcode;          }
    if ($adres_street_name)     { $params_update_adres['values']['street_number_name']      = $adres_street_name;       }
    if ($adres_street_number)   { $params_update_adres['values']['street_number_number']    = $adres_street_number;     }
    if ($adres_street_suffix)   { $params_update_adres['values']['street_number_suffix']    = $adres_street_suffix;     }
    if ($adres_street_address)  { $params_update_adres['values']['street_address']          = $adres_street_address;    }
    if ($adres_plaats)          { $params_update_adres['values']['city']                    = $adres_plaats;            }
    if ($adres_gemeente)        { $params_update_adres['values']['Adresgegevens.Gemeente']  = $adres_gemeente;          }
    if ($adres_provincie)       { $params_update_adres['values']['Adresgegevens.Provincie'] = $adres_provincie;         }
    if ($adres_lat)             { $params_update_adres['values']['geo_code_1']              = $adres_lat;               }
    if ($adres_lng)             { $params_update_adres['values']['geo_code_2']              = $adres_lng;               }
    if ($adres_surfacearea)     { $params_update_adres['values']['street_type']             = $adres_surfacearea;       }

    wachthond($extdebug,3, 'params_update_adres',               $params_update_adres);
    if ($address_id > 0) {
        $result_update_adres = civicrm_api4('Address','update', $params_update_adres);
    }
    wachthond($extdebug,9, 'result_update_adres',               $result_update_adres);

    wachthond($extdebug,2, "########################################################################");
    wachthond($extdebug,2, "### ADRES - UPDATE PRIMARY ADRES MET NIEUWE GEGEVENS",          "[EINDE]");
    wachthond($extdebug,2, "########################################################################");

}

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

    $today_datetime                 = date("Y-m-d H:i:s");
    $today_datetime_past            = date('Y-m-d H:i:s', strtotime('-50 year', strtotime($today_datetime)) );

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
/*
    $today_nextkamp_lastnext        = find_lastnext($today_datetime); 
    wachthond($extdebug,3, 'today_nextkamp_lastnext',               $today_nextkamp_lastnext);
    $today_nextkamp_start_date      =   $today_nextkamp_lastnext['next_start_date'];
    wachthond($extdebug,3, 'today_nextkamp_start_date',             $today_nextkamp_start_date);
    $today_nextkamp_einde_date      =   $today_nextkamp_lastnext['next_einde_date'];
    wachthond($extdebug,3, 'today_nextkamp_einde_date',             $today_nextkamp_einde_date);
    $today_nextkamp_kampjaar        = date('Y', strtotime($today_nextkamp_start_date))      ?? NULL;

    ##########################################################################################
    ### SET DEFAULT VALUE FOR NEW_WERVING_MEE_UPDATE & BEPAAL FISCAL YEAR START & EINDE
    ##########################################################################################        

    $datum_komendkamp_datetime  = new DateTime($new_mee_update_nextyear.'-08-01 16:00:00');
    wachthond($extdebug,3, 'datum_komendkamp_datetime',     $datum_komendkamp_datetime);
    $datum_komendkamp_string    = $datum_komendkamp_datetime->format('Y-m-d H:i:s');
    wachthond($extdebug,3, 'datum_komendkamp_string',       $datum_komendkamp_string);
    $datum_komendkamp_dbstring  = date("YmdHis", strtotime($datum_komendkamp_string));
    wachthond($extdebug,2, 'datum_komendkamp_dbstring',     $datum_komendkamp_dbstring);

    $today_nextkamp_nextkamp_fiscalyear     = curriculum_civicrm_fiscalyear($today_nextkamp_start_date);
    $today_nextkamp_fiscalyear_start        = $mee_fiscalyear['fiscalyear_start'] ?? NULL;
    $today_nextkamp_fiscalyear_einde        = $mee_fiscalyear['fiscalyear_einde'] ?? NULL;

    wachthond($extdebug,3, 'today_nextkamp_fiscalyear_start',      $today_nextkamp_fiscalyear_start);
    wachthond($extdebug,3, 'today_nextkamp_fiscalyear_einde',      $today_nextkamp_fiscalyear_einde);
*/
    $array_contditjaar          = base_cid2cont($contact_id);
    wachthond($extdebug,4, "array_contditjaar",         $array_contditjaar);

    $array_allpart_ditjaar      = base_find_allpart($contact_id, $mee_update_nextkamp_start_date);
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

    $ditjaar_kampkort_low               = preg_replace('/[^ \w-]/','',strtolower(trim($ditjaar_kampkort)));  // only letters/numbers/dashes
    $ditjaar_kampkort_cap               = preg_replace('/[^ \w-]/','',strtoupper(trim($ditjaar_kampkort)));  // only letters/numbers/dashes

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
    $geslacht           = $array_contditjaar['geslacht']        ?? NULL;
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
                'activity_type_id:name'     => 'mee_ditjaar',
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
        wachthond($extdebug,9, 'result_activity_mee_create',            $result_activity_mee_create);
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
                'activity_type_id:name'     => 'mee_ditjaar',
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

function werving_civicrm_acl($contactid, $datumbelangstelling, $drupalid = NULL) {

    $extdebug               = 0;  //  1 = basic // 2 = verbose // 3 = params / 4 = results
    $apidebug               = FALSE;

    $contact_id             = $contactid;
    $drupal_id              = $drupalid;
    $datum_belangstelling   = $datumbelangstelling;

    if (empty($contact_id) OR empty($datum_belangstelling)) {
        return;
    }

    if ($contact_id > 0 AND empty($drupal_id)) {

        $params_contact_get = [
            'checkPermissions' => FALSE,
            'debug'  => $apidebug,              
            'select' => [
                'display_name',
                'external_identifier',
            ],
            'where' => [
                ['id',  'IN', [$contact_id]],
            ],
        ];
    }        
    wachthond($extdebug,7, 'params_contact_get',            $params_contact_get);
    $result_contact_get    = civicrm_api4('Contact','get',  $params_contact_get);
    wachthond($extdebug,9, 'result_contact_get',            $result_contact_get);

    if (isset($result_contact_get))    {
        $displayname        = $result_contact_get[0]['display_name']        ?? NULL;
        $drupal_id          = $result_contact_get[0]['external_identifier'] ?? NULL;

        wachthond($extdebug,1, 'display_name',              $displayname);
        wachthond($extdebug,1, 'drupal_id',                 $drupal_id);
        wachthond($extdebug,1, 'datum_belangstelling',      $datum_belangstelling);        
    }

    if ($drupal_id > 0) {

        wachthond($extdebug,1, "########################################################################");
        wachthond($extdebug,1, "### WERVING - ACL ADD TO CMS GROUP BELANGSTELLING",      "[$displayname]");
        wachthond($extdebug,1, "########################################################################");

//      cms_group_create($drupal_id, 'belangstelling');
    }
}