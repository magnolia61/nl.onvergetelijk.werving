# Belangstelling Flow & Integration

## Werving Pipeline

**werving.php** is de hub voor alle belangstellings-logica. Na `werving_civicrm_customPre()` triggert:

```
1. Datum/status belangstelling bepalen
2. Leeftijdsberekeningen
3. [KRITIEK] intake_civicrm_configure() aanroepen (line 603-607)
4. ACL & Drupal-rollen synchroniseren
```

## Belangstellingsformulieren

### Node 842: Aanvraag Belangstellingsformulier
- Zet `datum_belangstelling` (werving, field 647, group 270)
- Triggert `werving_civicrm_custom()` (echte hook_civicrm_custom, post-commit)
- Berekent `mee_status`, leeftijden + volledige pipeline (cv/intake/account/acl)

### Node 844: Belangstellingsformulier Nieuwe Kampleiding
- Zet `BIO_ingevuld` (intake, field 1496, group 181)
- Triggert `intake_civicrm_custom()` (echte hook_civicrm_custom, op group 181)
- **OPGELOST 2026-06-06:** intake had alleen een dode `intake_civicrm_customPre()`
  (geen echte hook) en miste `intake_civicrm_custom()`. Toegevoegd + force_fresh
  contact-read. Zie README_BIO_TRIGGER.md.

## Werving → Intake Integration (Line 603-607)

**Code:**
```php
if (function_exists('intake_civicrm_configure') && function_exists('base_cid2cont')) {
    $cont_array = base_cid2cont($entityID) ?: [];
    $empty      = [];
    intake_civicrm_configure($cont_array, [], $empty, 'belangstelling_trigger');
}
```

**Wat het doet:**
- Roept intake_civicrm_configure aan met context='belangstelling_trigger'
- Berekent INT_nodig, NAW_nodig, BIO_nodig statussen
- Slaat deze op naar Contact via base_api_wrapper

**Context='belangstelling_trigger':**
- Niet 'hook_cont' (formulier-context)
- Dus data_cont WORDT naar DB geschreven via API
- Geen part_id (contact is geen participant yet)
- INT_status wordt 'gedeeltelijk' (incomplete)

## Hook-patroon (werving vs intake)

Beide extensies volgen hetzelfde patroon: een DODE `_civicrm_customPre` (geen
echte CiviCRM-hook, alleen door tests direct aangeroepen) naast een WERKENDE
`_civicrm_custom` (de echte hook_civicrm_custom, post-commit).

| Functie | Echte hook? | Live in productie? |
|---|---|---|
| `werving_civicrm_customPre` | nee (customPre bestaat niet) | ❌ dood |
| `werving_civicrm_custom`    | ja (hook_civicrm_custom)     | ✅ |
| `intake_civicrm_pre`        | ja (hook_civicrm_pre)        | ✅ (alleen foto) |
| `intake_civicrm_custom`     | ja (hook_civicrm_custom)     | ✅ (NIEUW 2026-06-06) |
| `intake_civicrm_customPre`  | nee (customPre bestaat niet) | ❌ dood |

## Opgelost (2026-06-06): Node 844 BIO-flow

- **Symptoom:** Klaas Varkevisser (ID 27453) niet zichtbaar in rapport na BIO-formulier.
- **Echte oorzaak (NIET het webform):** intake miste de echte `intake_civicrm_custom()`
  hook (had alleen de dode `customPre`), plus een stale static cache in `base_cid2cont`.
- **Fix:** `intake_civicrm_custom()` toegevoegd (gemodelleerd naar `werving_civicrm_custom`)
  met `base_cid2cont($cid, TRUE)` force_fresh. Zie README_BIO_TRIGGER.md.

## Test Coverage

- **BelangstellingTriggerTest:** context='belangstelling_trigger' flow ✅
- **BioIngevuldDbTest (intake):** end-to-end Contact.update → echte hook → BIO_status ✅

## References

- werving.php: `werving_civicrm_custom()` (line 555) — echte hook, post-commit pipeline
- werving.php: intake call (line 603-607)
- intake.php: `intake_civicrm_custom()` — echte hook (NIEUW)
- intake.php: `intake_civicrm_configure()`
