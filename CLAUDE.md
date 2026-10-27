# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Panoramica

`E3pr0m_dev` raccoglie i progetti di sviluppo di E3pr0m su WordPress:

- **Plugin** a marchio E3pr0m, pensati per la directory di WordPress.org. Ogni plugin ha una versione gratuita e, se serve, una versione Pro come plugin separato.
- **Temi custom**.
- **Debug su WordPress**: analisi e correzione di problemi su siti e codice WordPress.

Questo è l'unico `CLAUDE.md` del repository: le regole comuni stanno nelle prime sezioni, quelle specifiche di ogni progetto in una sezione propria in fondo. Non creare altri `CLAUDE.md` nelle cartelle dei progetti. Le sezioni "Struttura di un plugin", "Convenzioni di nomenclatura", "Traduzioni" e "Vincoli" riguardano i plugin: per temi custom e debug le convenzioni si definiscono quando nasce il primo progetto e si aggiungono qui.

Plugin presenti:

| Plugin | Cartella | Cosa fa |
|---|---|---|
| BccPilot | `Plugin/bccpilot/` | Aggiunge un destinatario `Bcc:` nascosto alle mail di sistema che WordPress invia all'amministratore. Dettagli nella sezione "BccPilot" in fondo. |

Lingua: commenti nel codice e risposte in italiano. Testi dell'interfaccia (`__()`) e `readme.txt` in inglese, come richiede la directory.

Non ci sono build, lint o test. Per provare una modifica, copiare la cartella del plugin in `wp-content/plugins/` di un sito WordPress e attivarla.

## Struttura di un plugin

Ogni plugin sta in `Plugin/<nome>/` e contiene almeno il file principale `<nome>.php`, `uninstall.php`, `readme.txt` (documentazione per l'utente, in inglese) e `languages/`. Requisiti minimi di partenza: PHP 7.4+, WP 5.2+, senza dipendenze; un plugin può alzarli se serve, dichiarandolo nell'intestazione e nel `readme.txt`.

La cartella del plugin è quella che diventa lo zip per la directory: ci va solo ciò che serve al plugin. Niente file di sviluppo (`CLAUDE.md`, note, file `.po`/`.mo`).

## Convenzioni di nomenclatura

Cartella, slug, file principale e Text Domain NON hanno prefisso: lo slug della directory di WordPress.org deriva dal nome del plugin. Tutto ciò che vive nel codice usa invece il prefisso `e3p` (identifica E3pr0m, comune a tutti i suoi prodotti) seguito dal nome del prodotto (uguale allo slug), per evitare collisioni con altri plugin:

| Elemento | Formato | Esempio (BccPilot) |
|---|---|---|
| Cartella e slug | `<nome>` | `bccpilot` |
| File principale | `<nome>.php` | `bccpilot.php` |
| Funzioni, opzioni, hook, handle | `e3p_<nome>_` | `e3p_bccpilot_add_bcc()` |
| Costanti | `E3P_<NOME>_` | (nessuna al momento) |
| Text Domain | uguale allo slug | `bccpilot` |

Con le classi si può usare invece un namespace (`E3pr0m\<Nome>`). Il nome visibile del plugin (`Plugin Name`) resta in chiaro, senza prefisso.

## Traduzioni

- Ogni plugin ha in `languages/` solo il `.pot` (modello). NON includere `.po`, `.mo` o `.php` di traduzione: la directory li rifiuta (review del 27/10/2026), le traduzioni si gestiscono su translate.wordpress.org. Se si aggiunge o cambia una stringa `__()`, rigenerare il `.pot` (stringhe estratte dal PHP).
- Non si usa `load_plugin_textdomain()` (sconsigliato dalla directory): in produzione le traduzioni arrivano da translate.wordpress.org.
- Le traduzioni non si possono chiamare prima di `init`: i testi per l'interfaccia vanno tenuti separati dalla logica che si aggancia prima (es. in `plugins_loaded`).

## Vincoli da rispettare (plugin)

- Nei plugin gratuiti non devono esserci funzioni bloccate o disattivate in attesa di pagamento (regola della directory). Le funzioni Pro vivono in un plugin separato che usa i filtri di estensione del plugin gratuito.
- Nessun indirizzo, chiave o dato personale come default nel codice.
- Ogni dato in uscita va escapato e ogni dato in ingresso sanificato. Le scritture sul database sono ridotte al minimo, documentate nella sezione del plugin in questo file e rimosse da `uninstall.php`.
- Se cambia il comportamento o la versione, aggiornare insieme intestazione del plugin e `readme.txt` (Version e Changelog).
- Licenza GPLv2 or later (richiesta dalla directory di WordPress.org): intestazione e `readme.txt` devono riportare `License` e `License URI`. Non aggiungere codice di terzi con licenza incompatibile con la GPL.

## BccPilot

Cartella `Plugin/bccpilot/`. Aggiunge un destinatario `Bcc:` nascosto alle mail di *sistema* che WordPress invia all'amministratore, scelte dall'utente in Impostazioni → BccPilot. Pensato per la directory di WordPress.org, con una futura versione Pro come plugin separato. File: `bccpilot.php`, `uninstall.php`, `readme.txt`, `languages/` (solo il `.pot`).

### Architettura

- `e3p_bccpilot_get_notifications()` è il catalogo delle notifiche copiabili: per ogni voce `hook` (filtro della singola notifica), `type` (`email` se il filtro riceve l'array in stile `wp_mail`, `headers` se riceve solo gli header) e `default`. Gli aggiornamenti automatici di plugin e temi sono una voce sola (`plugin_theme_update`, filtro `auto_plugin_theme_update_email`): i filtri separati per plugin e per temi non esistono nel core. I testi per la schermata stanno in `e3p_bccpilot_get_notification_texts()`, separati perché le traduzioni non si possono chiamare prima di `init` mentre i filtri vanno agganciati in `plugins_loaded` (la mail di errore critico può partire prima di `init`). I filtri `e3p_bccpilot_notifications` e `e3p_bccpilot_notification_texts` sono il punto di estensione per la futura Pro.
- `e3p_bccpilot_register_filters()` (su `plugins_loaded`) aggancia solo le voci scelte dall'utente, e solo se esiste un indirizzo valido. I callback sono `e3p_bccpilot_add_bcc()` (array in stile `wp_mail`) e `e3p_bccpilot_add_bcc_headers()` (solo header); entrambi usano `e3p_bccpilot_append_bcc()`.
- L'indirizzo è `e3p_bccpilot_get_email()`: viene solo dall'opzione salvata (nessuna costante in `wp-config.php`, rimandata a sviluppi futuri). Non esiste un indirizzo di default.
- Una sola opzione, `e3p_bccpilot_settings` (`email` + `notifications`), salvata con la Settings API e rimossa da `uninstall.php`. Un indirizzo non valido dà un errore e lascia quello già salvato; il campo vuoto disattiva il plugin. Gli errori li stampa già il core nelle pagine sotto Impostazioni: non chiamare `settings_errors()` nella pagina (doppio messaggio).

### Vincoli specifici

- NON agganciare `wp_mail` né altri filtri generici sulle mail: il plugin non deve copiare le mail di WooCommerce o altre mail del sito. Aggiungere solo filtri di singole notifiche, dopo aver verificato nel core che il filtro esista.
- Senza indirizzo valido il plugin non fa nulla.
- Unica scrittura sul database: l'opzione `e3p_bccpilot_settings`.
