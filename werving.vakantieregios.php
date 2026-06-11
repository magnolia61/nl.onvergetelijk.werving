<?php

/**
 * =======================================================================================
 * FUNCTIE-INDEX: werving.vakantieregios.php
 * =======================================================================================
 *   werving_civicrm_vakantieregio()
 *   werving_civicrm_vakantieregio_write()
 * =======================================================================================
 */

// -----------------------------------------------------------------------------
// HELPER FUNCTIES
// -----------------------------------------------------------------------------

function werving_civicrm_vakantieregio($contactid) {

    $extdebug = 'werving.vakantieregios'; // Kanaal voor centrale debug-config; niveau wordt opgezocht in ozk.debug.config.php
    $apidebug   = FALSE;

    if (empty($contactid)) {
        return;
    } else {
        $contact_id     = $contactid;
    }

    $extwrite           = 1;
    $extwerving         = 1;

    $adres_street_name  = NULL;
    $adres_lat          = NULL;
    $adres_lng          = NULL;
    $adres_construction = NULL;
    $adres_surfacearea  = NULL;
    $regio_name         = NULL;
    $result_update_adres = NULL;

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

        if (isset($result_adres_get[0])) {
            $displayname           = $result_adres_get[0]['display_name']                                                   ?? NULL;
            $adres_id              = $result_adres_get[0]['address_primary.id']                                             ?? NULL;
            $adres_street_address  = trim((string)($result_adres_get[0]['address_primary.street_address']           ?? '')) ?? NULL;
            $adres_street_number   = trim((string)($result_adres_get[0]['address_primary.street_number']            ?? '')) ?? NULL;
            $adres_street_suffix   = trim((string)($result_adres_get[0]['address_primary.street_number_suffix']     ?? '')) ?? NULL;
            $adres_postcode        = trim((string)($result_adres_get[0]['address_primary.postal_code']              ?? '')) ?? NULL;
            $adres_plaats          = trim((string)($result_adres_get[0]['address_primary.city']                     ?? '')) ?? NULL;
            $adres_gemeente        = trim((string)($result_adres_get[0]['address_primary.Adresgegevens.Gemeente']   ?? '')) ?? NULL;
            $adres_provincie       = trim((string)($result_adres_get[0]['address_primary.Adresgegevens.Provincie']  ?? '')) ?? NULL;
            $werving_vakantieregio = trim((string)($result_adres_get[0]['WERVING.vakantieregio']                    ?? '')) ?? NULL;

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
                $jobsArray      = $pro6pp_result['data'] ?? NULL;
            }

            curl_close($curl);

            wachthond($extdebug,2, 'pro6pp_result',     $pro6pp_result);

            $adres_gemeente     = $pro6pp_result[0]['municipality'] ?? NULL;
            $adres_provincie    = $pro6pp_result[0]['province']     ?? NULL;

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

    $extdebug = 'werving.vakantieregios'; // Kanaal voor centrale debug-config; niveau wordt opgezocht in ozk.debug.config.php
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