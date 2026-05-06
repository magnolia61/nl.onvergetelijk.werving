<?php

/**
 * =======================================================================================
 * FUNCTIE-INDEX: werving.functions.php
 * =======================================================================================
 *   werving_civicrm_acl()
 * =======================================================================================
 */

function werving_civicrm_acl($contactid, $datumbelangstelling, $drupalid = NULL) {

    $extdebug = 'werving.helpers'; // Kanaal voor centrale debug-config; niveau wordt opgezocht in ozk.debug.config.php
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
        wachthond($extdebug,7, 'params_contact_get',            $params_contact_get);
        $result_contact_get    = civicrm_api4('Contact','get',  $params_contact_get);
        wachthond($extdebug,9, 'result_contact_get',            $result_contact_get);
    }

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