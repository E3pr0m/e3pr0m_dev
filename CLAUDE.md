# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Panoramica

`E3pr0m_dev` raccoglie i progetti di sviluppo di E3pr0m. Al momento contiene un solo plugin WordPress, `Plugin/e3p-admin-mail-bcc/`: un plugin (`e3p-admin-mail-bcc.php` più `uninstall.php`, PHP 7.4+, WP 5.2+, senza dipendenze) che aggiunge un destinatario `Bcc:` nascosto alle mail di *sistema* che WordPress invia all'amministratore, scelte dall'utente in Impostazioni → Admin Mail Bcc. È pensato per la directory di WordPress.org, con una futura versione Pro come plugin separato. La documentazione per l'utente è in `Plugin/e3p-admin-mail-bcc/readme.txt`.

Lingua: commenti nel codice e risposte in italiano. Testi dell'interfaccia (`__()`) e `readme.txt` in inglese, come richiede la directory.

Non ci sono build, lint o test. Per provare una modifica, copiare la cartella in `wp-content/plugins/` di un sito WordPress e attivarla.

## Convenzioni di nomenclatura

Tutto ciò che si crea qui usa il prefisso `e3p` (identifica E3pr0m) seguito da una sigla del progetto (es. `amb` per Admin Mail Bcc), per evitare collisioni con altri plugin:

| Elemento | Formato | Esempio |
|---|---|---|
| Cartella e slug | `e3p-<nome>` | `e3p-admin-mail-bcc` |
| File principale | `e3p-<nome>.php` | `e3p-admin-mail-bcc.php` |
| Funzioni, opzioni, hook, handle | `e3p_<sigla>_` | `e3p_amb_add_bcc()` |
| Costanti | `E3P_<SIGLA>_` | `E3P_AMB_EXTRA_EMAIL` |
| Text Domain | uguale allo slug | `e3p-admin-mail-bcc` |

Con le classi si può usare invece un namespace (`E3pr0m\<Nome>`). Il nome visibile del plugin (`Plugin Name`) resta in chiaro, senza prefisso.

## Architettura

- `e3p_amb_get_notifications()` è il catalogo delle notifiche copiabili: per ogni voce `hook` (filtro della singola notifica), `type` (`email` se il filtro riceve l'array in stile `wp_mail`, `headers` se riceve solo gli header) e `default`. I testi per la schermata stanno in `e3p_amb_get_notification_texts()`, separati perché le traduzioni non si possono chiamare prima di `init` mentre i filtri vanno agganciati in `plugins_loaded` (la mail di errore critico può partire prima di `init`). I filtri `e3p_amb_notifications` e `e3p_amb_notification_texts` sono il punto di estensione per la futura Pro.
- `e3p_amb_register_filters()` (su `plugins_loaded`) aggancia solo le voci scelte dall'utente, e solo se esiste un indirizzo valido. I callback sono `e3p_amb_add_bcc()` (array in stile `wp_mail`) e `e3p_amb_add_bcc_headers()` (solo header); entrambi usano `e3p_amb_append_bcc()`.
- L'indirizzo è `e3p_amb_get_email()`: la costante `E3P_AMB_EXTRA_EMAIL` in `wp-config.php` ha la precedenza sull'opzione salvata. Non esiste un indirizzo di default.
- Traduzioni in `languages/`: `.pot` (modello), `-it_IT.po` e `-it_IT.mo`. Se si aggiunge o cambia una stringa `__()`, rigenerare i tre file (stringhe estratte dal PHP) e aggiornare le traduzioni italiane. Non si usa `load_plugin_textdomain()` (sconsigliato dalla directory): in produzione le traduzioni arrivano da translate.wordpress.org.
- Una sola opzione, `e3p_amb_settings` (`email` + `notifications`), salvata con la Settings API e rimossa da `uninstall.php`.

## Vincoli da rispettare

- NON agganciare `wp_mail` né altri filtri generici sulle mail: il plugin non deve copiare le mail di WooCommerce o altre mail del sito. Aggiungere solo filtri di singole notifiche.
- Nessun indirizzo personale come default nel codice: senza indirizzo valido il plugin non fa nulla.
- Nel plugin gratuito non devono esserci funzioni bloccate o disattivate in attesa di pagamento (regola della directory). Le funzioni Pro vivono in un plugin separato che usa i filtri di estensione.
- Unica scrittura sul database: l'opzione `e3p_amb_settings`. Ogni dato in uscita va escapato e ogni dato in ingresso sanificato.
- Se cambia il comportamento o la versione, aggiornare insieme intestazione del plugin e `readme.txt` (Version e Changelog).
- Licenza GPLv2 or later (richiesta dalla directory di WordPress.org): intestazione e `readme.txt` devono riportare `License` e `License URI`. Non aggiungere codice di terzi con licenza incompatibile con la GPL.
