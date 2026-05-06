<?php

/**
 * =======================================================================================
 * FUNCTIE-INDEX: werving.php
 * =======================================================================================
 *   werving_get_field_map()      De "Single Source of Truth" voor alle CiviCRM custom fields binnen
 *   werving_civicrm_customPre()  De "Portier" voor de Werving-module. Deze CiviCRM hook vangt
 *   werving_civicrm_configure()  De "Rekenmachine" voor de Werving-module. Deze functie bevat 100%
 *   werving_civicrm_config()     Implements hook_civicrm_config().
 *   werving_civicrm_install()    Implements hook_civicrm_install().
 *   werving_civicrm_enable()     Implements hook_civicrm_enable().
 * =======================================================================================
 */

require_once 'werving.civix.php';
require_once 'werving.functions.php';
require_once 'werving.vakantieregios.php';
require_once 'werving.postcode.php';
require_once 'werving.activities.php';

use CRM_Werving_ExtensionUtil as E;

/**
 * =======================================================================================
 * COLOFON: werving_get_field_map
 * =======================================================================================
 * @description     De "Single Source of Truth" voor alle CiviCRM custom fields binnen 
 * de Werving-module. Deze functie koppelt de ruwe database-kolommen 
 * (inclusief hun numerieke Field ID) aan een leesbare APIv4-naam.
 * * LET OP: De structuur van de array-sleutel is strikt. Het gedeelte
 * ná de laatste underscore MOET het werkelijke Custom Field ID 
 * in CiviCRM zijn. Dit is een vereiste voor base_extract_from_params()
 * en base_inject_params() om de data te kunnen verwerken.
 * * @usage           Wordt gebruikt door de Pre-Hook (voor extractie en filteren), 
 * de Rekenmachine (voor variabelen-toewijzing), en de Verzamelaar 
 * (voor automatische injectie bij opslaan).
 * @return array    Een associatieve array in het format: ['db_naam_ID' => 'API.naam']
 * =======================================================================================
 */
function werving_get_field_map(): array {
    return [
        'leeftijd_decimalen_1665'   => 'WERVING.leeftijd_decimalen',
        'leeftijd_rondjaren_1666'   => 'WERVING.leeftijd_rondjaren',
        'nextkamp_decimalen_1580'   => 'WERVING.nextkamp_decimalen',
        'nextkamp_rondjaren_1578'   => 'WERVING.nextkamp_rondjaren',
        'nextkamp_rondmaand_1579'   => 'WERVING.nextkamp_rondmaand',
        'datum_belangstelling_647'  => 'WERVING.Datum_belangstelling',
        'welke_leeftijdsgroep_378'  => 'WERVING.Welke_leeftijdsgroep',
        'welke_kampweek_377'        => 'WERVING.Welke_kampweek',
        'welke_kampweken_1172'      => 'WERVING.Welke_kampweken',
        'mee_update_1614'           => 'WERVING.mee_update',
        'mee_komendkampjaar_1619'   => 'WERVING.mee_update_year',
        'mee_verwachting_1615'      => 'WERVING.mee_verwachting',
        'mee_toelichting_1616'      => 'WERVING.mee_toelichting',
        'mee_status_1967'           => 'WERVING.mee_status',
        'mee_notities_1764'         => 'WERVING.mee_notities',
        'vakantieregio_1949'        => 'WERVING.vakantieregio',
        'mee_update_text_2224'      => 'WERVING.mee_update_text',
        'mee_contact_2234'          => 'WERVING.mee_contact',
    ];
}

/**
 * =======================================================================================
 * COLOFON: werving_civicrm_customPre
 * =======================================================================================
 * @description     De "Portier" voor de Werving-module. Deze CiviCRM hook vangt 
 * data op voordat deze in de database wordt opgeslagen. Hij is 
 * volledig 'Webform-proof' (ondersteunt platte en geneste arrays) 
 * en delegeert alle complexe berekeningen naar de rekenmachine 
 * (werving_civicrm_configure). Tot slot injecteert hij de 
 * berekende waarden terug in de opslag-transactie.
 * * @trigger         Wordt getriggerd bij het opslaan/updaten van een Contact (create/edit).
 * @dependencies    base_get_field_ids(), base_extract_from_params(), base_inject_params()
 * @param string $op        De actie (bijv. 'create' of 'edit').
 * @param int    $groupID   Het Profile ID (0 bij Drupal Webforms).
 * @param int    $entityID  Het Contact ID van de vrijwilliger.
 * @param array  $params    De data-array die CiviCRM op het punt staat op te slaan.
 * @return void             Past de &$params array aan via een pass-by-reference.
 * =======================================================================================
 */
function werving_civicrm_customPre(string $op, int $groupID, int $entityID, array &$params): void {

    // --- STAP 0: PREVENTIE VAN DUBBELE UITVOERING ---
    // CiviCRM vuurt hooks soms meerdere keren af per form-submit. 
    // Deze statische variabelen voorkomen dat we onnodig dubbel rekenen.
    static $werving_request_skip = FALSE;
    static $processing_werving_pre  = FALSE;

    if ($werving_request_skip || $processing_werving_pre) return;

    if (!in_array($op, ['create', 'edit'])) {
        return;
    }

    $extdebug = 'werving.custompre'; // Kanaal voor centrale debug-config; niveau wordt opgezocht in ozk.debug.config.php
//  $profileids = [270, 102, 142, 160]; // Bekende profielen (als fallback voor als webform faalt)
    $profileids = [270]; // Alleen het specifieke Werving profiel als fallback

    // --- OPTIMALISATIE 1: VROEGE RETURN VOOR ONBEKENDE PROFIELEN ---
    // Als er géén velden uit onze name_map in het formulier zaten (empty params_werving), 
    // én we komen niet uit een bekend CiviCRM-profiel, dan heeft dit formulier 
    // niets met werving te maken en stoppen we het script.
    if (!in_array($groupID, $profileids)) {
        return;
    }

    $processing_werving_pre  = TRUE;
    $werving_custompre_start = microtime(TRUE);
    watchdog('civicrm_timing', base_microtimer("START werving_custompre [GID: $groupID / EID: $entityID]"), NULL, WATCHDOG_DEBUG);

    wachthond($extdebug, 1, "########################################################################");
    wachthond($extdebug, 1, "### WERVING [PRE] 1.0 EXTRACTIE & MAPPING",                       "[MAP]");
    wachthond($extdebug, 1, "########################################################################");

    // --- STAP 1.0: EXTRACTIE ---
    // Nu we weten dat het ofwel profiel 270 is, of een webform (0), halen we de data op.
    $name_map       = werving_get_field_map();
    $field_ids      = base_get_field_ids($name_map);
    $params_werving = base_extract_from_params($params, $name_map);

    // --- OPTIMALISATIE 2: VROEGE RETURN VOOR LEGE DATA ---
    // Als na extractie blijkt dat er totaal geen werving-relevante velden in de params zitten: STOP.
    if (empty($params_werving)) {
        return;
    }

    wachthond($extdebug, 1, "########################################################################");
    wachthond($extdebug, 1, "### WERVING [PRE] 2.0 START VERWERKING",                "[ID: $entityID]");
    wachthond($extdebug, 1, "########################################################################");

    // --- STAP 2.0: LOGICA UITBESTEDEN AAN DE REKENMACHINE ---
    // We geven de schoongemaakte formulier-data door aan onze eigen rekenmachine.
    // Omdat we context 'hook' meegeven, slaat de rekenmachine niets zelf op, 
    // maar geeft hij alleen de berekende resultaten terug in $data_to_inject.
    $data_to_inject = werving_civicrm_configure($entityID, 'hook', $params_werving);

    wachthond($extdebug, 2, "########################################################################");
    wachthond($extdebug, 1, "### WERVING [PRE] 3.0 INJECTIE EN EXTERNAL SAVE",           "[$entityID]");
    wachthond($extdebug, 2, "########################################################################");

    // --- STAP 3.0: RESULTATEN TERUGSTOPPEN IN HET FORMULIER ---
    // We hebben nu berekende data (bijv. exacte leeftijden of status mee). 
    // Deze injecteren we naadloos terug in de originele $params transactie, 
    // zodat CiviCRM ze straks samen met de rest van het formulier opslaat.
    if (!empty($data_to_inject)) {
        $success_list = base_inject_params($params, $data_to_inject, $field_ids, $entityID, "WERVING", $extdebug);

        if (!empty($success_list)) {
            wachthond($extdebug, 1, "WERVING [PRE] SUCCES: Injectie voltooid", $success_list);
        }
    }

    // --- STAP 4.0: DRUPAL RECHTEN (ACL) UPDATEN ---
    // Als iemand belangstelling toont, moeten we zorgen dat diegene in Drupal 
    // de juiste rechten krijgt om de portal te zien.
    $val_datum_belangstelling = $data_to_inject['WERVING.Datum_belangstelling'] ?? $params_werving['WERVING.Datum_belangstelling'] ?? null;
    
    if (isset($val_datum_belangstelling)) {
        werving_civicrm_acl($entityID, $val_datum_belangstelling);
    }

    // --- STAP 5.0: DRUPAL DATUM CRASH VOORKOMEN ---
    // Vlak voordat de data naar de DB gaat, strippen we eventuele onjuiste array-structuren
    // uit de datums om een bekende fatale Drupal Entity error te voorkomen.
    if (function_exists('drupal_timestamp_sweep')) {
        drupal_timestamp_sweep($params);
    }

    wachthond($extdebug, 1, "########################################################################");
    wachthond($extdebug, 1, "### WERVING [PRE] EINDE VERWERKING",                        "[SUCCESS]");
    wachthond($extdebug, 1, "########################################################################");

    $total_werving_custompre_duur = number_format(microtime(TRUE) - $werving_custompre_start, 3);
    wachthond($extdebug, 3, "WERVING [PRE] duur totaal: {$total_werving_custompre_duur}s");
    watchdog('civicrm_timing', base_microtimer("EINDE werving_custompre"), NULL, WATCHDOG_DEBUG);
}

/**
 * =======================================================================================
 * COLOFON: werving_civicrm_configure
 * =======================================================================================
 * @description     De "Rekenmachine" voor de Werving-module. Deze functie bevat 100% 
 * van de wervings-bedrijfslogica (kampweken vertalen, datums checken,
 * leeftijden berekenen). Kan opereren via een formulier ('hook' context)
 * waarbij formulier-input voorrang krijgt, of volledig zelfstandig 
 * ('direct' context) via een cronjob/API-call op basis van database data.
 * * @param int    $contact_id        Het CiviCRM Contact ID.
 * @param string $context           'hook' (geeft array terug) of 'direct' (slaat direct op).
 * @param array  $params_werving    (Optioneel) De uitgepakte waarden vanuit het formulier.
 * @return array                    Een array met alle berekende APIv4-sleutels en waarden.
 * =======================================================================================
 */
function werving_civicrm_configure(int $contact_id, string $context = 'direct', array $params_werving = []): array {
    
    $extdebug = 'werving.configure'; // Kanaal voor centrale debug-config; niveau wordt opgezocht in ozk.debug.config.php
    $today_datetime = date("Y-m-d H:i:s");

    $werving_configure_start = microtime(TRUE);
    watchdog('civicrm_timing', base_microtimer("START werving_configure [CID: $contact_id / CTX: $context]"), NULL, WATCHDOG_DEBUG);

    wachthond($extdebug, 2, "########################################################################");
    wachthond($extdebug, 1, "### WERVING CONFIGURE - 1.0 DATA INLADEN UIT DATABASE",    "[FALLBACK]");
    wachthond($extdebug, 2, "########################################################################");

    // Haal de hudige situatie van dit contact uit de database.
    // Dit is nodig als fallback voor velden die niet in het formulier zaten.
    $cont = base_cid2cont($contact_id);
    if (empty($cont)) return [];

    wachthond($extdebug, 2, "########################################################################");
    wachthond($extdebug, 1, "### WERVING CONFIGURE - 1.1 LEEFTIJD BEREKENEN",             "[LEEFTIJD]");
    wachthond($extdebug, 2, "########################################################################");

    $birth_date = $cont['birth_date'] ?? NULL;
    if (!empty($birth_date)) {
        
        // Leeftijd op DIT moment (handig voor UI weergave)
        $leeftijd_vantoday      = partstatus_leeftijd_diff('vandaag', $birth_date, $today_datetime);
        $new_leeftijd_decimalen = (float)$leeftijd_vantoday['leeftijd_decimalen'];
        $new_leeftijd_rondjaren = (int)$leeftijd_vantoday['leeftijd_rondjaren'];

        // Belangrijkste meting: Leeftijd exact op de startdag van hun volgende kamp
        $lastnext_kamp          = find_lastnext($today_datetime);
        $leeftijd_nextkamp      = partstatus_leeftijd_diff('nextkamp', $birth_date, $lastnext_kamp['next_start_date']);
        $new_nextkamp_decimalen = (float)$leeftijd_nextkamp['leeftijd_decimalen'];
        $new_nextkamp_rondjaren = (int)$leeftijd_nextkamp['leeftijd_rondjaren'];
        $new_nextkamp_rondmaand = (int)$leeftijd_nextkamp['leeftijd_rondmaand'];
    }

    wachthond($extdebug, 2, "########################################################################");
    wachthond($extdebug, 1, "### WERVING CONFIGURE - 1.2 REGISTRATIE CHECK",           "[REGISTRATIE]");
    wachthond($extdebug, 2, "########################################################################");

    // Voorlopige berekening van de referentiedatum — nauwkeurig herberekend in sectie 3.2
    // na ophalen van de formulierwaarden. Nodig zodat base_find_allpart geen NULL krijgt.
    $mee_update_nextkamp_start  = find_lastnext($today_datetime)['next_start_date'] ?? NULL;

    // We halen hier zowel de deelnemer- als leidingstatus op voor het komende jaar.
    $array_allpart_ditjaar      = base_find_allpart($contact_id, $mee_update_nextkamp_start)    ?: [];
    $ditjaar_pos_deel_part_id   = $array_allpart_ditjaar['result_allpart_pos_deel_part_id']     ?? 0;
    $ditjaar_pos_leid_part_id   = $array_allpart_ditjaar['result_allpart_pos_leid_part_id']     ?? 0;

    $is_deelnemer_nu            = ($ditjaar_pos_deel_part_id > 0);
    $is_te_jong                 = (isset($new_nextkamp_decimalen) && $new_nextkamp_decimalen < 17.0);

    wachthond($extdebug, 2, "########################################################################");
    wachthond($extdebug, 1, "### WERVING CONFIGURE - 2.0 BEPAAL LEIDENDE WAARDEN",           "[INPUT]");
    wachthond($extdebug, 2, "########################################################################");

    // FORMULIER IS LEIDEND: Als een waarde in $params_werving zit (dus vers uit een formulier komt),
    // gebruiken we die. Zo niet, dan gebruiken we de historische waarde uit de database ($cont).
    $val_welke_kampweken        = $params_werving['WERVING.Welke_kampweken']        ?? $cont['werving_kampweken']           ?? NULL;
    $val_welke_leeftijdsgroep   = $params_werving['WERVING.Welke_leeftijdsgroep']   ?? $cont['werving_leeftijdsgroep']      ?? NULL;
    $val_welke_kampweek         = $params_werving['WERVING.Welke_kampweek']         ?? $cont['werving_kampweek']            ?? NULL;
    
    $val_datum_belangstelling   = $params_werving['WERVING.Datum_belangstelling']   ?? $cont['datum_belangstelling']        ?? NULL;

    $val_mee_update             = $params_werving['WERVING.mee_update']             ?? $cont['mee_update']                  ?? NULL;
    $val_mee_verwachting        = $params_werving['WERVING.mee_verwachting']        ?? $cont['mee_verwachting']             ?? NULL;
    $val_mee_status             = $params_werving['WERVING.mee_status']             ?? $cont['mee_status']                  ?? NULL;
    $val_mee_toelichting        = $params_werving['WERVING.mee_toelichting']        ?? $cont['mee_toelichting']             ?? NULL;

    wachthond($extdebug, 2, "########################################################################");
    wachthond($extdebug, 1, "### WERVING CONFIGURE - 3.0 AUTOMATISCHE DATUM BELANGSTELLING", "[DATUM]");
    wachthond($extdebug, 2, "########################################################################");

    $valid_leeftijdsgroepen = ['kinderkamp', 'brugkamp', 'tienerkamp', 'jeugdkamp'];
    $valid_kampweken        = ['week1', 'week2', 'maaktnietuit'];

    $input_groep            = $params_werving['WERVING.Welke_leeftijdsgroep'] ?? NULL;
    $input_week             = $params_werving['WERVING.Welke_kampweek']       ?? NULL;

    // Gebruik (int) of de ? 1 : 0 shorthand om booleans te forceren naar integers
    $heeft_belang_input     = (in_array($input_groep, $valid_leeftijdsgroepen) || in_array($input_week, $valid_kampweken)) ? 1 : 0;

    if ($is_deelnemer_nu || $is_te_jong) {
        // RESET ALLE WERVINGSVELDEN VOOR NIET-VRIJWILLIGERS
        $new_datum_belangstelling   = NULL;
        $new_mee_status             = NULL;
        $val_datum_belangstelling   = NULL;
        $val_mee_status             = NULL;
        wachthond($extdebug, 1, "POORTWACHTER RESET", "Contact voldoet niet aan profiel: velden geleegd.");

    } else {
        // 2. BEHOUD HISTORIE: Zorg dat de oude datum niet 'verdwijnt' uit de injectie
        $new_datum_belangstelling = $val_datum_belangstelling;

        // 3. VERNIEUWING: Alleen bij geverifieerde nieuwe input de datum naar VANDAAG zetten
        if ($context === 'hook' && $heeft_belang_input === 1 && !empty($val_datum_belangstelling)) {
            $new_datum_belangstelling   = $today_datetime;
            $val_datum_belangstelling   = $today_datetime;
            wachthond($extdebug, 1, "AUTOMATISCHE DATUM", "Bestaande datum vernieuwd naar vandaag.");
        }
    }

    wachthond($extdebug, 2, "########################################################################");
    wachthond($extdebug, 1, "### WERVING CONFIGURE - 3.1 LOGICA: KAMPWEKEN",             "[KAMPWEKEN]");
    wachthond($extdebug, 2, "########################################################################");

    // FUNCTIONEEL KAMPWEKEN:
    // Vertaling van menselijke voorkeur ("Kinderkamp" & "Week 1") naar technische 
    // CiviCRM multiselect string ("\x01KK1\x01").
    $new_welke_kampweken = "";

    if (isset($val_welke_leeftijdsgroep) || isset($val_welke_kampweek)) {
        
        $str_groep  = (string) $val_welke_leeftijdsgroep;
        $str_week   = (string) $val_welke_kampweek;

        $kamp_mapping = [
            'kinderkamp'    => 'KK',
            'brugkamp'      => 'BK',
            'tienerkamp'    => 'TK',
            'jeugdkamp'     => 'JK',
        ];

        foreach ($kamp_mapping as $zoekterm => $prefix) {
            if (strpos($str_groep, $zoekterm) !== false) {
                // Voeg specifieke weken toe als die gekozen zijn
                if (strpos($str_week, '1') !== false)               { $new_welke_kampweken .= "\x01{$prefix}1\x01"; }
                if (strpos($str_week, '2') !== false)               { $new_welke_kampweken .= "\x01{$prefix}2\x01"; }
                
                // Als 'maakt niet uit' is aangevinkt, schrijven we ze in voor beide opties
                if (strpos($str_week, 'maaktnietuit') !== false)    { $new_welke_kampweken .= "\x01{$prefix}1\x01{$prefix}2\x01"; }
            }
        }
    }

    wachthond($extdebug, 2, "########################################################################");
    wachthond($extdebug, 1, "### WERVING CONFIGURE - 3.2 LOGICA: DATUMS & TERMIJNEN",       "[DATUMS]");
    wachthond($extdebug, 2, "########################################################################");

    // Bepaal of de 'mee_update' nieuwer is dan de 'datum_belangstelling'
    $temp_mee_update = (date_biggerequal($val_datum_belangstelling, $val_mee_update) == 1 || empty($val_mee_update)) ? $val_datum_belangstelling : $val_mee_update;

    $mee_update_fiscal_start            = curriculum_civicrm_fiscalyear($temp_mee_update);
    $mee_update_fiscalyear_start        = $mee_update_fiscal_start['fiscalyear_start']      ?? NULL;
    $mee_update_nextkamp_lastnext       = find_lastnext($temp_mee_update);
    $mee_update_nextkamp_start          = $mee_update_nextkamp_lastnext['next_start_date']  ?? NULL;
    $mee_update_nextkamp_year           = date('Y', strtotime($mee_update_nextkamp_start));
    
    // FUNCTIONEEL GRENSKAMP: 
    // Na 18 juli is het actuele kamp fiscaal/operationeel al begonnen of afgelopen.
    // Aanmeldingen ná deze datum schuiven we automatisch door naar het kamp van het VOLGENDE jaar.
    $grenskamp_zomer                    = $mee_update_nextkamp_year . "-07-18 16:00:00"; 
    $new_mee_update                     = $temp_mee_update;

    if (date_biggerequal($temp_mee_update, $grenskamp_zomer) == 1) {
        $mee_update_nextkamp_start_obj  = new DateTime($mee_update_nextkamp_start);
        $new_mee_update_year            = $mee_update_nextkamp_start_obj->modify('+1 year')->format('Y-m-d H:i:s');
    } else {
        $new_mee_update_year            = $mee_update_nextkamp_start;
    }

    wachthond($extdebug, 2, "########################################################################");
    wachthond($extdebug, 1, "### WERVING CONFIGURE - 3.3 LOGICA: STATUS MEE",           "[STATUS MEE]");
    wachthond($extdebug, 2, "########################################################################");

    $new_mee_verwachting = $val_mee_verwachting;
    $new_mee_status      = $val_mee_status;
    $new_mee_toelichting = $val_mee_toelichting;

    // --- 1. OVERRIDE: IS DE PERSOON AL INGESCHREVEN (LEIDING OF DEELNEMER)? ---
    // We bepalen eerst of er een actieve registratie is voor het komende jaar.
    $active_pid = ($ditjaar_pos_leid_part_id > 0) ? $ditjaar_pos_leid_part_id : (($ditjaar_pos_deel_part_id > 0) ? $ditjaar_pos_deel_part_id : 0);

    if ($active_pid > 0) {
        
        $part_details = base_pid2part($active_pid);
        $reg_date     = $part_details['register_date']  ?? $today_datetime;
        $kampstart    = $part_details['part_kampstart'] ?? $mee_update_nextkamp_start;

        // FUNCTIONEEL: Indien ingeschreven en de kampstart ligt in de toekomst.
        if (date_biggerequal($kampstart, $today_datetime) == 1) {
            $new_mee_verwachting = 'zekerwel';
            $new_mee_update      = $reg_date;
        }

        // --- SPECIFIEKE LEIDING LOGICA ---
        if ($ditjaar_pos_leid_part_id > 0) {
            
            // Als er een actieve leiding-registratie is voor dit jaar:
            // 1. We zetten de status standaard op 'aangemeld'.
            // 2. Tenzij de registratie al volledig bevestigd is (bijv. via een andere hook), dan blijft 'gaatmee'.
            $new_mee_status      = ($val_mee_status === 'gaatmee') ? 'gaatmee' : 'aangemeld';
//          $new_mee_toelichting = '';
            $new_mee_update_year = date('Y', strtotime($kampstart));
            wachthond($extdebug, 1, "STATUS MEE OVERRIDE", "Leiding inschrijving gevonden. Status: $new_mee_status.");
        }

    } elseif ($heeft_belang_input === 1) {
        
        // --- 2. STANDAARD BELANGSTELLING LOGICA ---
        // FUNCTIONEEL: Bestaande vrijwilligers hebben vaak een oude status van vorig jaar staan.
        // Als we in een nieuw fiscaal jaar zijn beland, resetten we oude statussen ('weet niet' -> 'misschien')
        // zodat ze opnieuw uitgevraagd kunnen worden.
        if (!empty($mee_update_fiscalyear_start)) {
            
            // Oude belangstelling (van vorig jaar)
            if (date_biggerequal($val_datum_belangstelling, $mee_update_fiscalyear_start) == 1) {
                if (in_array($val_mee_verwachting, ['zekerniet', 'waarschijnlijktniet', 'weetnogniet']) || empty($val_mee_verwachting)) {
                    $new_mee_verwachting = "misschien";
                }
            } 
            // Verse belangstelling (van dit jaar)
            elseif (date_biggerequal($mee_update_fiscalyear_start, $val_datum_belangstelling) == 1) {
                if (empty($val_mee_status)) {
                    $new_mee_status = 'onbekend'; // HR moet dit nog bekijken
                }
            }
        }
    }

    wachthond($extdebug, 2, "########################################################################");
    wachthond($extdebug, 1, "### WERVING CONFIGURE - 3.4 LOGICA: ACTIVITY MEE",           "[ACTIVITY]");
    wachthond($extdebug, 2, "########################################################################");

    // Werk de CiviCRM tijdlijn-activiteit bij ("Mee dit jaar")
    if (!empty($new_mee_update)) {
        werving_civicrm_activitymee($contact_id, $new_mee_update, ($cont['displayname'] ?? 'Onbekend'));
    }

    wachthond($extdebug, 2, "########################################################################");
    wachthond($extdebug, 1, "### WERVING CONFIGURE - 3.5 LOGICA: REGIO",                     "[REGIO]");
    wachthond($extdebug, 2, "########################################################################");

    $new_vakantieregio = werving_civicrm_vakantieregio($contact_id);

    wachthond($extdebug, 2, "########################################################################");
    wachthond($extdebug, 1, "### WERVING CONFIGURE - 4.0 VERZAMELAAR & OPSLAAN",          "[INJECTIE]");
    wachthond($extdebug, 2, "########################################################################");

    $name_map = werving_get_field_map();
    
    // VERZAMELAAR:
    // Snapshot van alle gedefinieerde variabelen vóór de loop. Zo onderscheiden we
    // "$new_x bewust op NULL gezet" (wél injecteren) van "$new_x nooit berekend" (niet injecteren).
    // isset($$var_new) werkt hiervoor niet: dat returnt false voor zowel undefined als NULL.
    $computed_vars  = array_keys(get_defined_vars());
    $data_to_inject = [];
    foreach ($name_map as $db_col => $api_name) {
        $api_parts = explode('.', (string)$api_name);
        $suffix    = end($api_parts);
        $var_new   = 'new_' . strtolower($suffix);

        if (in_array($var_new, $computed_vars)) {
            $data_to_inject[$api_name] = $$var_new;
            wachthond($extdebug, 4, "Auto-Inject PREP: $api_name", $$var_new);
        }
    }

    // DIRECT OPSLAAN (Als het script standalone draait, buiten een formulier om)
    if ($context === 'direct' && !empty($data_to_inject)) {
        $res = base_api_wrapper('Contact', $contact_id, $data_to_inject, "WERVING_CONF", $extdebug);
        wachthond($extdebug, 9, "RESULT WERVING_CONF UPDATE", $res);
    }

    $total_werving_configure_duur = number_format(microtime(TRUE) - $werving_configure_start, 3);
    wachthond($extdebug, 3, "WERVING [CONFIGURE] duur totaal: {$total_werving_configure_duur}s");
    watchdog('civicrm_timing', base_microtimer("EINDE werving_configure"), NULL, WATCHDOG_DEBUG);

    return $data_to_inject;
}

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