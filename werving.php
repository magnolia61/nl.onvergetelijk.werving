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
        'werving_trigger_2327'      => 'WERVING.werving_trigger',
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

    wachthond($extdebug, 1, "########################################################################");
    wachthond($extdebug, 1, "### WERVING [PRE] 1.0 EXTRACTIE & MAPPING",         "[groupID: $groupID]");
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

    // --- OPTIMALISATIE 3: VROEGE RETURN ALS ALLEEN GECACHEDE LEEFTIJDVELDEN IN PARAMS ZITTEN ---
    // Formulieren die GID 270 schrijven maar alleen output-velden bevatten (bijv. de inloglink-
    // aanvraag) hebben geen actionable input. configure() zou ze toch recalculeren tot dezelfde
    // waarden. Dit bespaart ~3 seconden per aanvraag.
    static $passive_werving_fields = [
        'WERVING.leeftijd_decimalen', 'WERVING.leeftijd_rondjaren',
        'WERVING.nextkamp_decimalen', 'WERVING.nextkamp_rondjaren', 'WERVING.nextkamp_rondmaand',
        'WERVING.mee_update_year',
    ];
    if (empty(array_diff(array_keys($params_werving), $passive_werving_fields))) {
        return;
    }

    // --- DE LOCK PAS AANZETTEN ALS WE ZEKER WETEN DAT WE DOORGAAN ---
    $processing_werving_pre  = TRUE;

    $werving_custompre_start = microtime(TRUE);
    watchdog('civicrm_timing', base_microtimer("START werving_custompre [GID: $groupID / EID: $entityID]"), NULL, WATCHDOG_DEBUG);

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
    // We hebben nu berekende data (bijv. exacte leeftijden, status mee, kampweken).
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

    // --- STAP 4.1: CV TRIGGER ---
    // Als werving_trigger wordt gezet (bijv. door sqltask 236 als failsafe voor
    // contacten zonder curriculum-record), draai cv_civicrm_configure direct.
    // customPre is hiervoor goed genoeg: cv leest participant-records, niet
    // WERVING-velden, dus we hoeven niet te wachten op de DB-commit.
    // Lichter dan JAAROVERZICHT-trigger (full core); hierdoor kan sqltask 236
    // meerdere contacten per run verwerken.
    if (isset($params_werving['WERVING.werving_trigger']) && function_exists('cv_civicrm_configure')) {
        wachthond($extdebug, 1, "### WERVING [PRE] 4.1 CV TRIGGER via werving_trigger", "[$entityID]");
        cv_civicrm_configure($entityID);
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

    // --- HIER GAAT DE DEUR WEER VAN HET SLOT VOOR HET VOLGENDE RECORD IN DE BATCH ---
    $processing_werving_pre = FALSE;
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
    // force_fresh = TRUE: bij meerdere saves in één request (bijv. een webform dat
    // leeftijdsgroep en kampweek in aparte saves wegschrijft) zou een eerdere save
    // de cache al gevuld hebben vóór dit veld in de DB stond. Zonder verse lezing
    // mist de fallback dan de zojuist opgeslagen waarde en blijft Welke_kampweken leeg.
    $cont = base_cid2cont($contact_id, TRUE);
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
    $ditjaar_wait_deel_part_id  = $array_allpart_ditjaar['result_allpart_wait_deel_part_id']    ?? 0;

    $is_deelnemer_nu            = ($ditjaar_pos_deel_part_id > 0);
    $is_deelnemer_wachtlijst    = ($ditjaar_wait_deel_part_id > 0);

    // Poortwachter: mag deze persoon als (aankomend) leiding-kandidaat gelden? Gebruikt
    // dezelfde gedeelde ladder als de mail-beslissing (base_bepaal_rolstatus() in
    // nl.onvergetelijk.base), zodat de 17-jaargrens en de "bevestigd deelnemer
    // overrult"-regel hier altijd synchroon lopen met de rest van het systeem — niet
    // langer een eigen, apart onderhouden 17.0-vergelijking. We toetsen HYPOTHETISCH
    // ("als er nu belangstelling getoond zou worden, wat zou de uitkomst zijn?") omdat
    // dit precies de vraag is die de poortwachter beantwoordt: we zijn immers zelf bezig
    // te bepalen of we datum_belangstelling wel mogen (blijven) zetten. $is_deelnemer_nu/
    // $is_deelnemer_wachtlijst zitten al verwerkt in deze uitkomst (via ditjaardeelyes/mss),
    // dus verderop hoeft die niet nogmaals los gecheckt te worden. Wachtlijst telt symmetrisch
    // mee met een definitieve deelnemer-registratie (3-jul-2026, zelfde regel als bij leiding).
    $rolstatus_kandidaat        = base_bepaal_rolstatus([
        'leeftijd_decimalen'   => $new_nextkamp_decimalen ?? NULL,
        'ditjaardeelyes'       => $is_deelnemer_nu ? 1 : 0,
        'ditjaardeelmss'       => $is_deelnemer_wachtlijst ? 1 : 0,
        'datum_belangstelling' => $today_datetime, // hypothetisch: "stel dat het nu gezet wordt"
    ]);
    $is_geen_leiding_kandidaat  = ($rolstatus_kandidaat['rol'] !== 'leiding');

    wachthond($extdebug, 2, "########################################################################");
    wachthond($extdebug, 1, "### WERVING CONFIGURE - 2.0 BEPAAL LEIDENDE WAARDEN",           "[INPUT]");
    wachthond($extdebug, 2, "########################################################################");

    // FORMULIER IS LEIDEND: Als een waarde in $params_werving zit (dus vers uit een formulier komt),
    // gebruiken we die. Zo niet, dan gebruiken we de historische waarde uit de database ($cont).
    $val_welke_kampweken        = $params_werving['WERVING.Welke_kampweken']        ?? $cont['werving_kampweken']           ?? NULL;
    $val_welke_leeftijdsgroep   = $params_werving['WERVING.Welke_leeftijdsgroep']   ?? $cont['werving_leeftijdsgroep']      ?? NULL;
    $val_welke_kampweek         = $params_werving['WERVING.Welke_kampweek']         ?? $cont['werving_kampweek']            ?? NULL;
    
    $val_datum_belangstelling   = $params_werving['WERVING.Datum_belangstelling']   ?? $cont['datum_belangstelling']        ?? NULL;

    // DB-fallback (als veld niet in dit formulier zat): lees uit $cont met de keys
    // zoals base_cid2cont ze teruggeeft (prefix 'werving_'). Eerder stonden hier de
    // ongeprefixte keys ($cont['mee_status'] etc.) die base NOOIT teruggeeft → fallback
    // was altijd NULL → elke WERVING-save zónder deze velden in de params wiste mee_status/
    // mee_verwachting/mee_toelichting. (Fix 30-jun-2026; mee_status ontbrak ook in base_cid2cont.)
    $val_mee_update             = $params_werving['WERVING.mee_update']             ?? $cont['werving_mee_update']          ?? NULL;
    $val_mee_verwachting        = $params_werving['WERVING.mee_verwachting']        ?? $cont['werving_mee_verwachting']     ?? NULL;
    $val_mee_status             = $params_werving['WERVING.mee_status']             ?? $cont['werving_mee_status']          ?? NULL;
    $val_mee_toelichting        = $params_werving['WERVING.mee_toelichting']        ?? $cont['werving_mee_toelichting']     ?? NULL;

    wachthond($extdebug, 2, "########################################################################");
    wachthond($extdebug, 1, "### WERVING CONFIGURE - 3.0 AUTOMATISCHE DATUM BELANGSTELLING", "[DATUM]");
    wachthond($extdebug, 2, "########################################################################");

    $valid_leeftijdsgroepen = ['kinderkamp', 'brugkamp', 'tienerkamp', 'jeugdkamp'];
    $valid_kampweken        = ['week1', 'week2', 'maaktnietuit'];

    $input_groep            = $params_werving['WERVING.Welke_leeftijdsgroep'] ?? NULL;
    $input_week             = $params_werving['WERVING.Welke_kampweek']       ?? NULL;

    // Gebruik (int) of de ? 1 : 0 shorthand om booleans te forceren naar integers
    $heeft_belang_input     = (in_array($input_groep, $valid_leeftijdsgroepen) || in_array($input_week, $valid_kampweken)) ? 1 : 0;

    if ($is_geen_leiding_kandidaat) {
        // RESET ALLE WERVINGSVELDEN VOOR NIET-VRIJWILLIGERS ($is_deelnemer_nu zit al
        // verwerkt in $is_geen_leiding_kandidaat, zie toelichting hierboven)
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
    //
    // BELANGRIJK: Dit gebeurt hier (in configure()), niet in customPre/custom,
    // omdat configure() de bron is voor alle berekeningen. customPre injecteert
    // het resultaat automatisch via base_inject_params().
    $new_welke_kampweken = "";

    if (!empty($val_welke_leeftijdsgroep) || !empty($val_welke_kampweek)) {

        // Multiselect-velden komen uit de DB-fallback ($cont) als array, maar uit
        // het formulier ($params) als \x01-string. Beide platslaan naar één string
        // zodat de strpos-checks hieronder in beide gevallen werken.
        $str_groep  = is_array($val_welke_leeftijdsgroep) ? implode('', $val_welke_leeftijdsgroep) : (string) $val_welke_leeftijdsgroep;
        $str_week   = is_array($val_welke_kampweek)       ? implode('', $val_welke_kampweek)       : (string) $val_welke_kampweek;

        $kamp_mapping = [
            'kinderkamp'    => 'KK',
            'brugkamp'      => 'BK',
            'tienerkamp'    => 'TK',
            'jeugdkamp'     => 'JK',
        ];

        foreach ($kamp_mapping as $zoekterm => $prefix) {
            if (strpos(strtolower($str_groep), $zoekterm) !== false) {
                // Voeg specifieke weken toe als die gekozen zijn
                if (strpos($str_week, '1') !== false)               { $new_welke_kampweken .= "\x01{$prefix}1\x01"; }
                if (strpos($str_week, '2') !== false)               { $new_welke_kampweken .= "\x01{$prefix}2\x01"; }

                // Als 'maakt niet uit' is aangevinkt, schrijven we ze in voor beide opties
                if (strpos($str_week, 'maaktnietuit') !== false)    { $new_welke_kampweken .= "\x01{$prefix}1\x01{$prefix}2\x01"; }
            }
        }

        wachthond($extdebug, 3, "KAMPWEKEN BEREKENING", "groep=$str_groep, week=$str_week, result=$new_welke_kampweken");
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
                    $new_mee_status = 'onbekend'; // Interesse getoond, nog geen contact gehad
                }
                if (empty($val_mee_verwachting)) {
                    $new_mee_verwachting = 'misschien'; // Verwachting: misschien gaat ze mee (tot we meer weten)
                }
            }
        }
    }

    wachthond($extdebug, 2, "########################################################################");
    wachthond($extdebug, 1, "### WERVING CONFIGURE - 3.4 LOGICA: ACTIVITY MEE",         "[ACTIVITY]");
    wachthond($extdebug, 2, "########################################################################");

    // Werk de CiviCRM tijdlijn-activiteit bij ("Mee dit jaar")
    if (!empty($new_mee_update)) {
        werving_civicrm_activitymee($contact_id, $new_mee_update, ($cont['displayname'] ?? 'Onbekend'));
    }

    wachthond($extdebug, 2, "########################################################################");
    wachthond($extdebug, 1, "### WERVING CONFIGURE - 3.5 LOGICA: REGIO",                       "[REGIO]");
    wachthond($extdebug, 2, "########################################################################");

    $new_vakantieregio = werving_civicrm_vakantieregio($contact_id);
    
    wachthond($extdebug, 2, "########################################################################");
    wachthond($extdebug, 1, "### WERVING CONFIGURE - 3.6 LOGICA: SYNC LEEFTIJD NAAR KAMP","[PART/LEID]");
    wachthond($extdebug, 2, "########################################################################");

    if ($active_pid > 0 && !empty($part_details) && function_exists('partstatus_leeftijd_configure')) {
    
        // Bepaal de juiste GroupID (190 voor leiding, 139 voor deelnemer)
        $target_group_id            = ($ditjaar_pos_leid_part_id > 0) ? "190" : "139";
        
        $part_data                  = $part_details;
        $part_data['birth_date']    = $part_data['birth_date'] ?? $cont['birth_date'] ?? NULL;
        $part_data['contact_id']    = $contact_id; 
        
        // Sla de leeftijden direct op in de Participant database velden
        partstatus_leeftijd_configure($part_data, $today_datetime, $target_group_id, "event", TRUE);
    }

    wachthond($extdebug, 2, "########################################################################");
    wachthond($extdebug, 1, "### WERVING CONFIGURE - 3.7 LOGICA: TRIGGER PARTSTATUS MOTOR","[$active_pid]");
    wachthond($extdebug, 2, "########################################################################");

    if ($active_pid > 0 && !empty($part_details) && function_exists('partstatus_configure')) {
    
        // We roepen de hoofdfunctie van de partstatus module aan voor dit record.
        // Omdat we $array_part als NULL meegeven, wordt deze intern "vers" uit de database 
        // ingeladen (inclusief de leeftijden die we hierboven in 3.6 zojuist hebben opgeslagen).
        // Context 'werving_sync' is puur voor nette logboekregistratie.
        partstatus_configure($active_pid, NULL, NULL, 'werving_sync');
    }

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
        $api_parts  = explode('.', (string)$api_name);
        $suffix     = end($api_parts);
        $var_new    = 'new_' . strtolower($suffix);

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
 * Implements hook_civicrm_custom().
 *
 * Vuurt NA de DB-commit van custom data (post-save), in tegenstelling tot customPre.
 * Wanneer Datum_belangstelling gezet wordt, draaien we de volledige pipeline:
 * CV → INTAKE → ACCOUNT → ACL.
 *
 * Waarom WERVING (270) NIET in profilecvmax staat (en dus core hier NIET vuurt):
 *   Core schrijft zelf terug naar WERVING (leeftijd, nextkamp, etc.). Als WERVING
 *   in profilecvmax zou zitten, veroorzaakt elk core-run een nieuwe WERVING-save
 *   die opnieuw core triggert → oneindige loop.
 *
 * Waarom de pipeline hier volledig is (CV + INTAKE + ACCOUNT + ACL):
 *   Een nieuwe vrijwilliger heeft na het invullen van het belangstellingsformulier
 *   nog geen Drupal-account en geen ACL-rollen. Zonder ACCOUNT blijft het account
 *   ongemaakt; zonder ACL krijgt het account verkeerde rollen (bijv. ooit_deelnemer
 *   door de hardcoded rid 11 in drupal.php die vroeger bij account-aanmaak stond —
 *   zie commit waarbij dat gefixed is). CV en INTAKE moeten voor ACL draaien zodat
 *   ACL de juiste keren_deel=0 kan lezen.
 *
 * Waarom hier en niet in customPre:
 *   In customPre (vóór commit) staat bio_ingevuld nog NIET in de DB als WERVING (270)
 *   eerder verwerkt wordt dan INTAKE (181). intake_civicrm_configure zou dan
 *   BIO_status='ongecheckt' opslaan en de correcte injectie van intake_customPre(181)
 *   overschrijven. Door hier te wachten tot ná de commit zijn ALLE velden beschikbaar.
 */
function werving_civicrm_custom($op, $groupID, $entityID, &$params): void {

    static $processing_werving_custom = [];

    // Alleen WERVING (270), alleen na opslaan
    if ($groupID !== 270 || !in_array($op, ['create', 'edit'])) {
        return;
    }

    // Voorkom dubbele uitvoering voor dezelfde entiteit in één request
    if (!empty($processing_werving_custom[$entityID])) {
        return;
    }
    $processing_werving_custom[$entityID] = true;

    $extdebug = 'werving.custom';

    // Alleen triggeren als Datum_belangstelling gevuld is
    $datum_belang = civicrm_api4('Contact', 'get', [
        'checkPermissions' => FALSE,
        'select'           => ['WERVING.Datum_belangstelling'],
        'where'            => [['id', '=', $entityID]],
    ])->first()['WERVING.Datum_belangstelling'] ?? NULL;

    if (empty($datum_belang)) {
        $processing_werving_custom[$entityID] = false;
        return;
    }

    wachthond($extdebug, 1, "WERVING [CUSTOM] Volledige pipeline triggeren na DB-commit", "[$entityID]");

    wachthond($extdebug, 2, "########################################################################");
    wachthond($extdebug, 1, "### WERVING CUSTOM 1.0 CV (keren_deel, keren_leid berekenen)");
    wachthond($extdebug, 2, "########################################################################");

    // CV: keren_leid, keren_deel etc. berekenen
    // Moet VOOR ACL draaien zodat ACL keren_deel=0 kan lezen en ooit_deelnemer
    // niet ten onrechte toekent.
    if (function_exists('cv_civicrm_configure')) {
        cv_civicrm_configure($entityID);
    }

    wachthond($extdebug, 2, "########################################################################");
    wachthond($extdebug, 1, "### WERVING CUSTOM 2.0 INTAKE (INT_nodig, BIO_status berekenen)");
    wachthond($extdebug, 2, "########################################################################");

    // INTAKE: INT_nodig, BIO_status etc. berekenen
    // Op dit punt zijn ALLE webform-velden (incl. bio_ingevuld) al in de DB.
    if (function_exists('intake_civicrm_configure') && function_exists('base_cid2cont')) {
        $cont_array = base_cid2cont($entityID) ?: [];
        $empty      = [];
        intake_civicrm_configure($cont_array, [], $empty, 'belangstelling_trigger');
    }

    wachthond($extdebug, 2, "########################################################################");
    wachthond($extdebug, 1, "### WERVING CUSTOM 2.5 MEE_STATUS INITIALISATIE (fallback voor webforms)");
    wachthond($extdebug, 2, "########################################################################");

    // Bij een nieuwe belangstelling kan mee_status nog leeg zijn (customPre vult
    // mee_status niet bij elke route). Hier stellen we het in op 'onbekend' als:
    // - datum_belangstelling is onlangs ingevuld (< 1 jaar geleden)
    // - mee_status is nog leeg
    // Lees mee_status en mee_verwachting AUTORITATIEF uit de DB via APIv4.
    //
    // BELANGRIJK (bugfix 30-jun-2026): hier stond eerst base_cid2cont($entityID, TRUE).
    // In deze post-commit hook gaf die — ondanks force_fresh — een STALE/lege mee_status
    // terug (cache-timing in de hookstack). Daardoor "initialiseerde" de logica hieronder
    // een zojuist gezette status (bv. 'gaatmee', door CiviRule of back-office) ten onrechte
    // terug naar 'onbekend' via een extra Contact.update — die op zijn beurt customPre +
    // configure opnieuw afvuurde en de waarde definitief overschreef. De directe APIv4-get
    // (zoals Datum_belangstelling hierboven op r604) leest wél de zojuist gecommitte waarde.
    $mee_now = civicrm_api4('Contact', 'get', [
        'checkPermissions' => FALSE,
        'select'           => ['WERVING.mee_status', 'WERVING.mee_verwachting'],
        'where'            => [['id', '=', $entityID]],
    ])->first();

    $val_mee_status      = $mee_now['WERVING.mee_status']      ?? NULL;
    $val_mee_verwachting = $mee_now['WERVING.mee_verwachting'] ?? NULL;


    // Initialiseer alleen wanneer de waarde ECHT leeg is én de belangstelling recent (< 1 jaar).
    // $datum_belang is hierboven (r604) autoritatief gelezen en gegarandeerd gevuld (early return r610).
    $days_since = (int) date_diff(date_create($datum_belang), date_create('now'))->format('%a');
    if ($days_since < 365) {
        $update_vals = [];

        if (empty($val_mee_status)) {
            $update_vals['WERVING.mee_status'] = 'onbekend';
            wachthond($extdebug, 1, "MEE_STATUS INITIALISATIE", "Datum belangstelling is recent ($days_since dagen), mee_status was leeg → zet op 'onbekend'");
        }

        if (empty($val_mee_verwachting)) {
            $update_vals['WERVING.mee_verwachting'] = 'misschien';
            wachthond($extdebug, 1, "MEE_VERWACHTING INITIALISATIE", "Datum belangstelling is recent, mee_verwachting was leeg → zet op 'misschien'");
        }

        if (!empty($update_vals)) {
            civicrm_api4('Contact', 'update', [
                'checkPermissions' => FALSE,
                'values' => array_merge(['id' => $entityID], $update_vals),
            ]);
            wachthond($extdebug, 3, "mee status/verwachting update", "OK");
        }
    }

    wachthond($extdebug, 2, "########################################################################");
    wachthond($extdebug, 1, "### WERVING CUSTOM 3.0 ACCOUNT (Drupal account aanmaken als nodig)");
    wachthond($extdebug, 2, "########################################################################");

    // ACCOUNT: Drupal account aanmaken als het contact nog geen account heeft,
    // en de eenmalige inloglink genereren. Moet voor ACL draaien zodat ACL
    // een geldig drupal_id heeft om rollen op te zetten.
    if (function_exists('account_civicrm_configure')) {
        account_civicrm_configure($entityID);
    }

    wachthond($extdebug, 2, "########################################################################");
    wachthond($extdebug, 1, "### WERVING CUSTOM 4.0 ACL (Drupal rollen en CiviCRM groepen)");
    wachthond($extdebug, 2, "########################################################################");

    // ACL: Drupal-rollen (ooit_belangstelling) en CiviCRM ACL-groepen (groep 855)
    // synchroniseren op basis van de nu berekende CV-data en datum_belangstelling.
    //
    // Forceer een verse contactlezing ($force_fresh = TRUE) zodat base_cid2cont de
    // static cache negeert. De cache kan stale datum_belang=NULL bevatten omdat een
    // eerdere hook (civicrm_post op Contact) base_cid2cont al aanriep vóór de
    // WERVING-custom-data werd gecommit.
    if (function_exists('acl_civicrm_configure')) {
        $cont_array_fresh = function_exists('base_cid2cont') ? base_cid2cont($entityID, TRUE) : NULL;
        acl_civicrm_configure($entityID, $cont_array_fresh);
    }

    $processing_werving_custom[$entityID] = false;
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
    _werving_register_civirules_conditions();
}

/**
 * Implements hook_civicrm_enable().
 */
function werving_civicrm_enable(): void {
    _werving_civix_civicrm_enable();
    _werving_register_civirules_conditions();
}

/**
 * Registreert de custom CiviRules-condities van de Werving-module.
 *
 * Idempotent: maakt de conditie alleen aan als die nog niet bestaat (op class_name).
 * Wordt aangeroepen vanuit install én enable, zodat (her)installatie de conditie
 * altijd herstelt. CiviRules-condities leven in de tabel civirule_condition; we
 * gebruiken de APIv4-entity CiviRulesCondition.
 */
function _werving_register_civirules_conditions(): void {

    $conditions = [
        [
            'name'       => 'werving_belangstelling_fiscaaljaar',
            'label'      => 'WERVING: Datum belangstelling valt in huidig boekjaar',
            'class_name' => 'CRM_Werving_CivirulesCondition_BelangstellingFiscaaljaar',
        ],
    ];

    foreach ($conditions as $condition) {
        try {
            $bestaat = civicrm_api4('CiviRulesCondition', 'get', [
                'checkPermissions' => FALSE,
                'select'           => ['id'],
                'where'            => [['class_name', '=', $condition['class_name']]],
            ])->count();

            if ($bestaat == 0) {
                civicrm_api4('CiviRulesCondition', 'create', [
                    'checkPermissions' => FALSE,
                    'values'           => [
                        'name'       => $condition['name'],
                        'label'      => $condition['label'],
                        'class_name' => $condition['class_name'],
                        'is_active'  => TRUE,
                    ],
                ]);
            }
        }
        catch (Exception $e) {
            // Niet fataal: registratie mag de (de)activatie van de extensie niet blokkeren.
            Civi::log()->warning('werving: registratie CiviRules-conditie mislukt: ' . $e->getMessage());
        }
    }
}