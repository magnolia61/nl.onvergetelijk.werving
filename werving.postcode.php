<?php

/**
 * =======================================================================================
 * FUNCTIE-INDEX: werving.postcode.php
 * =======================================================================================
 *   werving_civicrm_pro6pp_postcode()
 *   splitstreetaddress()
 *   werving_civicrm_address_update()
 * =======================================================================================
 */

function werving_civicrm_pro6pp_postcode($contactid, $postcode, $huisnummer, $nummersuffix = NULL) {

    $extdebug = 'werving.postcode'; // Kanaal voor centrale debug-config; niveau wordt opgezocht in ozk.debug.config.php
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
        curl_close($curl);
        return;
    }

    $pro6pp_result  = json_decode($response, true);
    $jobsArray      = $pro6pp_result['data'];

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

    $extdebug = 'werving.postcode'; // Kanaal voor centrale debug-config; niveau wordt opgezocht in ozk.debug.config.php
    $apidebug               = FALSE;

    $contact_id             = $contactid;
    $address_id             = $adresid;

    wachthond($extdebug,2, "########################################################################");
    wachthond($extdebug,2, "### ADRES - UPDATE PRIMARY ADRES MET NIEUWE GEGEVENS",          "[START]");
    wachthond($extdebug,2, "########################################################################");

    $adres_postcode         = $adres_array['adres_postcode'];
    $adres_street_name      = $adres_array['adres_street_name'];
    $adres_street_number    = $adres_array['adres_street_number'];
    $adres_street_suffix    = $adres_array['adres_street_suffix']   ?? NULL;

    $adres_street_address = NULL;
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
        wachthond($extdebug,9, 'result_update_adres',               $result_update_adres);
    }

    wachthond($extdebug,2, "########################################################################");
    wachthond($extdebug,2, "### ADRES - UPDATE PRIMARY ADRES MET NIEUWE GEGEVENS",          "[EINDE]");
    wachthond($extdebug,2, "########################################################################");

}
