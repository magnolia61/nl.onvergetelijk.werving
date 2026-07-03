<?php

/**
 * =======================================================================================
 * COLOFON: CRM_Werving_CivirulesCondition_BelangstellingFiscaaljaar
 * =======================================================================================
 * @description     CiviRules-conditie (parameterloos) die controleert of het veld
 *                  WERVING.Datum_belangstelling (custom_647) van het contact in het
 *                  HUIDIGE boekjaar (fiscaal jaar) valt.
 *
 *                  Functioneel: de "nu aanmelden"-mail (template 162, via rule 575
 *                  "BELANGSTELLING gaat mee") mag alleen naar belangstellenden van de
 *                  LOPENDE wervingscyclus. Iemand met een belangstelling uit een vorig
 *                  boekjaar moet niet opnieuw gemaild worden als leiding later alsnog
 *                  mee_status op 'gaatmee' zet.
 *
 *                  Technisch: we hergebruiken bewust de centrale base-functie
 *                  infiscalyear(), zodat de boekjaargrens (OZK: 1 december) op één
 *                  plek beheerd wordt en automatisch meebeweegt. Een relatieve
 *                  CiviRules-datumconditie ('-1 year') zou de 1-december-grens niet
 *                  exact volgen; deze conditie wel.
 *
 * @dependencies    infiscalyear() en curriculum_civicrm_fiscalyear() uit
 *                  nl.onvergetelijk.base (base.helpers.dates.php), wachthond() uit
 *                  nl.onvergetelijk.logger.
 * =======================================================================================
 */
class CRM_Werving_CivirulesCondition_BelangstellingFiscaaljaar extends CRM_Civirules_Condition {

    /**
     * Custom field id van WERVING.Datum_belangstelling.
     * Vast veld: deze conditie is doelbewust niet generiek/configureerbaar.
     */
    const FIELD_DATUM_BELANGSTELLING = 647;

    /**
     * Deze conditie heeft geen extra invoer nodig van de gebruiker.
     *
     * @param int $ruleConditionId
     * @return bool
     */
    public function getExtraDataInputUrl($ruleConditionId) {
        return FALSE;
    }

    /**
     * Leesbare omschrijving in de CiviRules-UI.
     *
     * @return string
     */
    public function userFriendlyConditionParams() {
        return 'Datum belangstelling valt in het huidige boekjaar (lopende wervingscyclus)';
    }

    /**
     * Kernlogica: is de belangstellingsdatum van dit contact in het huidige boekjaar?
     *
     * @param CRM_Civirules_TriggerData_TriggerData $triggerData
     * @return bool
     */
    public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {

        $extdebug = 'werving';

        wachthond($extdebug, 2, "########################################################################");
        wachthond($extdebug, 1, "### WERVING [CIVIRULE] CONDITIE: BELANGSTELLING IN BOEKJAAR", "[START]");
        wachthond($extdebug, 2, "########################################################################");

        // --- 1.0 VEILIGHEID: ZIJN DE BASE-DATUMFUNCTIES BESCHIKBAAR? ---
        // Faalt veilig (geen mail) als nl.onvergetelijk.base onverhoopt niet geladen is.
        if (!function_exists('infiscalyear')) {
            wachthond($extdebug, 1, "CONDITIE FALSE", "infiscalyear() niet beschikbaar (base niet geladen?)");
            return FALSE;
        }

        // --- 2.0 HAAL DE BELANGSTELLINGSDATUM OP (UIT DE DB) ---
        // BELANGRIJK: de trigger 'Contact Custom Data Changed' vult de trigger-data
        // alléén met de GEWIJZIGDE custom-velden (hier: mee_status). Datum_belangstelling
        // verandert bij die actie niet mee, dus $triggerData->getCustomFieldValue(647)
        // zou NULL teruggeven. Daarom halen we de waarde, net als de core-conditie
        // FieldValueComparison, vers op uit de database op basis van het contact-id.
        $contact_id = $triggerData->getContactId();
        wachthond($extdebug, 3, 'contact_id', $contact_id);

        if (empty($contact_id)) {
            wachthond($extdebug, 1, "CONDITIE FALSE", "Geen contact_id in trigger-data");
            return FALSE;
        }

        $params_contact_belang = [
            'checkPermissions' => FALSE,
            'select'           => [
                'id',
                'WERVING.Datum_belangstelling',
            ],
            'where'            => [
                ['id', '=', $contact_id],
            ],
        ];
        wachthond($extdebug, 7, 'params_contact_belang', $params_contact_belang);
        $result_contact_belang = civicrm_api4('Contact', 'get', $params_contact_belang);
        wachthond($extdebug, 9, 'result_contact_belang', $result_contact_belang);

        $val_datum_belangstelling = $result_contact_belang->first()['WERVING.Datum_belangstelling'] ?? NULL;
        wachthond($extdebug, 3, 'val_datum_belangstelling', $val_datum_belangstelling);

        // Geen datum = geen geldige belangstelling = geen mail.
        if (empty($val_datum_belangstelling)) {
            wachthond($extdebug, 1, "CONDITIE FALSE", "Datum_belangstelling (custom_647) is leeg");
            return FALSE;
        }

        // --- 3.0 VERGELIJK MET HET HUIDIGE BOEKJAAR ---
        // infiscalyear() bepaalt het boekjaar rond 'now' (vandaag) en geeft 1 terug
        // wanneer de belangstellingsdatum daarbinnen valt ('before'/'after'/0 = erbuiten).
        $in_boekjaar = infiscalyear($val_datum_belangstelling, 'now', 'Datum_belangstelling', 'huidig boekjaar');
        wachthond($extdebug, 3, 'in_boekjaar (1=ja)', $in_boekjaar);

        $is_valid = ($in_boekjaar === 1);
        wachthond($extdebug, 1, "CONDITIE RESULTAAT", $is_valid ? "[TRUE] binnen boekjaar" : "[FALSE] buiten boekjaar");

        return $is_valid;
    }

}
