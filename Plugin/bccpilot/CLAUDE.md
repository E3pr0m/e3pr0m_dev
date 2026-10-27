# CLAUDE.md: BccPilot

Regole specifiche di BccPilot. Convenzioni, traduzioni e vincoli comuni a tutti i plugin sono nel `CLAUDE.md` alla radice del repository.

## Cosa fa

Aggiunge un destinatario `Bcc:` nascosto alle mail di *sistema* che WordPress invia all'amministratore, scelte dall'utente in Impostazioni → BccPilot. Pensato per la directory di WordPress.org, con una futura versione Pro come plugin separato. File: `bccpilot.php`, `uninstall.php`, `readme.txt`, `languages/`.

## Architettura

- `e3p_bccpilot_get_notifications()` è il catalogo delle notifiche copiabili: per ogni voce `hook` (filtro della singola notifica), `type` (`email` se il filtro riceve l'array in stile `wp_mail`, `headers` se riceve solo gli header) e `default`. I testi per la schermata stanno in `e3p_bccpilot_get_notification_texts()`, separati perché le traduzioni non si possono chiamare prima di `init` mentre i filtri vanno agganciati in `plugins_loaded` (la mail di errore critico può partire prima di `init`). I filtri `e3p_bccpilot_notifications` e `e3p_bccpilot_notification_texts` sono il punto di estensione per la futura Pro.
- `e3p_bccpilot_register_filters()` (su `plugins_loaded`) aggancia solo le voci scelte dall'utente, e solo se esiste un indirizzo valido. I callback sono `e3p_bccpilot_add_bcc()` (array in stile `wp_mail`) e `e3p_bccpilot_add_bcc_headers()` (solo header); entrambi usano `e3p_bccpilot_append_bcc()`.
- L'indirizzo è `e3p_bccpilot_get_email()`: viene solo dall'opzione salvata (nessuna costante in `wp-config.php`, rimandata a sviluppi futuri). Non esiste un indirizzo di default.
- Una sola opzione, `e3p_bccpilot_settings` (`email` + `notifications`), salvata con la Settings API e rimossa da `uninstall.php`.

## Vincoli specifici

- NON agganciare `wp_mail` né altri filtri generici sulle mail: il plugin non deve copiare le mail di WooCommerce o altre mail del sito. Aggiungere solo filtri di singole notifiche.
- Senza indirizzo valido il plugin non fa nulla.
- Unica scrittura sul database: l'opzione `e3p_bccpilot_settings`.
