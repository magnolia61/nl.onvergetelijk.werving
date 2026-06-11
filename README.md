# nl.onvergetelijk.werving

## Functionele beschrijving

De `werving`-extensie houdt bij hoe en wanneer een deelnemer of begeleider in aanraking is gekomen met OZK, en welke wervings- en verwijzingsinformatie er bekend is. Denk aan: de datum van eerste belangstelling, de regio waar iemand vandaan komt, de leeftijdsgroep waarvoor iemand in aanmerking komt, en of iemand via mond-tot-mondreclame is aangemeld.

Daarnaast beheert `werving` de automatische adresverrijking via de Pro6PP-postcodeliverancierservice, de "Mee"-activiteiten (een activiteitentype dat bijhoudt of iemand dit jaar meegaat), en de toevoeging aan de "Belangstelling"-groep in CiviCRM voor nieuwe contacten.

## Afhankelijkheden

- `nl.onvergetelijk.base`
- `nl.onvergetelijk.mee` (voor mee-statusdata)

---

## Technische documentatie

### Bestandsstructuur

| Bestand | Inhoud |
|---|---|
| `werving.php` | Hooks en `werving_civicrm_configure` (hoofdmotor) |
| `werving.activities.php` | `werving_civicrm_activitymee` — aanmaken/bijwerken van Mee-activiteit |
| `werving.functions.php` | `werving_civicrm_acl` (Belangstelling-groep) |
| `werving.postcode.php` | Pro6PP adresverrijking, adresupdate-helper |
| `werving.vakantieregios.php` | Vakantiere­gio-mapping op basis van postcode |

### Kernfuncties

- `werving_get_field_map()` — field map van werving-custom fields naar API-namen
- `werving_civicrm_customPre($op, $groupID, $entityID, &$params)` — pre-hook: extraheert wervingsvelden, roept `werving_civicrm_configure` aan en injecteert resultaat terug
- `werving_civicrm_configure($contact_id, $context, $params_werving)` — de hoofdmotor:
  1. Data inladen uit database
  2. Leidende waarden bepalen
  3. Kampweken berekenen (op basis van inschrijvingen dit jaar)
  4. Datums en termijnen (datum eerste belangstelling, termijnen)
  5. Status "mee" dit jaar (samenvatting van mee-module)
  6. Mee-activiteit aanmaken of bijwerken
  7. Regio en leeftijdsgroep berekenen
  8. Opslaan
- `werving_civicrm_activitymee($contactid, $mee_update, $displayname)` — maakt de "Mee"-activiteit aan (als ze niet bestaat) of werkt deze bij met de actuele mee-status
- `werving_civicrm_acl($contactid, $datumbelangstelling, $drupalid)` — voegt een nieuw contact toe aan de Belangstelling-groep in CiviCRM
- `werving_civicrm_pro6pp_postcode($contactid, $postcode, $huisnummer, $nummersuffix)` — verrijkt het adres van een contact via de Pro6PP API (straat, stad, gemeente)
- `werving_civicrm_address_update($contactid, $adresid, $adres_array)` — werkt het primaire adres bij met de verrijkte gegevens

### Hooks geïmplementeerd
- `civicrm_customPre`
- `civicrm_config`, `civicrm_install`, `civicrm_enable`

---

*Beheerd door Stichting Onvergetelijke Zomerkampen.*
