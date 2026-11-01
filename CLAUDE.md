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
| Plugin Audit | `Plugin/pluginaudit/` | Elenca i plugin del sito con avvisi di vulnerabilità, indizi d'uso e stato di salute. In sviluppo, non ancora pubblicato. Dettagli nella sezione "Plugin Audit" in fondo. |
| LogPilot | `Plugin/logpilot/` | Facilita l'accesso a `debug.log` e all'`error_log` di PHP, evidenzia gli errori per livello e attiva/disattiva il debug da pannello. In sviluppo, non ancora pubblicato. Dettagli nella sezione "LogPilot" in fondo. |

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

## Plugin Audit

Cartella `Plugin/pluginaudit/`. Elenca i plugin del sito, attivi e non, con il loro stato di salute: avvisi di vulnerabilità divisi per gravità (colori) e categoria (con una breve descrizione), indizi d'uso, aggiornamenti disponibili, compatibilità PHP/WP, plugin disattivati ma installati. È un audit dello stato dei plugin, non una scansione del codice né del malware. Versione gratuita su WordPress.org e Pro come plugin separato (`pluginaudit-pro`). Stato: scheletro in sviluppo, non ancora pubblicato. File: `pluginaudit.php`, `uninstall.php`, `readme.txt`, `languages/` (solo il `.pot`) e `VINCOLI.md` (file di sviluppo, vedi sotto).

Le issue stanno nel Project `Plugin | Plugin Audit` (n. 10), label `pluginaudit` (gratuita, #26-#32) e `pluginaudit_pro` (Pro, #16-#25).

### Architettura (da definire)

- Fonte dei dati sulle vulnerabilità: Wordfence Intelligence v3, scelta provvisoria in attesa della risposta di Wordfence (uso in un plugin gratuito e Pro, limiti, campi del production feed). Ogni utente inserisce la propria chiave API. Lo scanner feed non ha CVSS né CWE: la categoria si ricava dal titolo e la gravità va decisa.
- Il feed è grande (circa 82 MB di JSON): da scaricare al massimo una volta al giorno, conservare e gestire il 429 senza errori visibili. Il problema della memoria in PHP è da verificare.
- Indizi d'uso: etichetta "nessun segno d'uso trovato", mai "inutilizzato".
- Integrazione con BccPilot: la Pro aggiunge la sua voce al catalogo con i filtri `e3p_bccpilot_notifications` e `e3p_bccpilot_notification_texts`. In BccPilot, dopo la pubblicazione di Plugin Audit, una riga di testo con link se manca e il checkbox se è attivo.
- Scritture sul database: da definire (chiave API, feed in cache, impostazioni). Ognuna va documentata qui e rimossa da `uninstall.php`.

### Vincoli specifici

- Il nome deve essere vero il giorno del rilascio: il `readme.txt` dice anche cosa il plugin NON fa (non scansiona il codice, non cerca malware, non sostituisce un plugin di sicurezza).
- Servizi esterni: consenso esplicito prima del primo uso, dati inviati e termini documentati nel `readme.txt` (Guideline 7); si scaricano solo dati, mai codice (Guideline 8).
- Nessuna chiave API nel codice e nessuna chiave condivisa (termini Wordfence).
- Ogni voce di Wordfence mostrata riporta il link al record e il copyright e la licenza di Defiant.
- La gravità si mostra sempre anche con testo e icona, mai solo con il colore.
- `Plugin/pluginaudit/VINCOLI.md` è un file di sviluppo e fa eccezione alla regola "niente file di sviluppo": non va nello zip né in SVN. Prima di ogni commit SVN, `svn status` deve mostrare solo i file del plugin.

## LogPilot

Cartella `Plugin/logpilot/`. Legge `debug.log` e l'`error_log` di PHP, evidenzia gli errori per livello con spiegazioni semplici per utenti non esperti e permette agli amministratori di attivare e disattivare il debug da pannello, senza aprire file. Versione gratuita su WordPress.org e Pro come plugin separato (`logpilot-pro`). Stato: scheletro in sviluppo, non ancora pubblicato. File: `logpilot.php`, `uninstall.php`, `readme.txt`, `languages/` (solo il `.pot`) e `VINCOLI.md` (file di sviluppo, vedi sotto).

Le issue stanno nel Project `Plugin | LogPilot` (n. 11), label `logpilot` (gratuita, #33-#41) e `logpilot_pro` (Pro, #42-#54).

### Architettura (da definire)

- Lettura: solo la coda dei file, con un limite. Il livello di ogni voce si riconosce dalla riga; voci su più righe trattate come una; righe non riconosciute come "Non classificato". Ogni riga mostrata va escapata (dato non fidato).
- Attivazione del debug: modifica di `wp-config.php` con backup, scrittura atomica, controllo di sintassi, ripristino automatico, solo le costanti di debug delimitate da un commento marcatore. Se il file non è scrivibile, il plugin mostra le righe da aggiungere a mano. `WP_DEBUG_DISPLAY` sempre spento; `DISALLOW_FILE_EDIT` non si tocca. Licenza di WPConfigTransformer da verificare prima dell'uso.
- Spegnimento automatico dopo una durata scelta, oppure promemoria via email ogni N giorni a un indirizzo scelto dall'utente (l'email dell'amministratore solo come proposta modificabile).
- Scritture sul database e su disco: da definire. Ognuna va documentata qui e rimossa da `uninstall.php`.
- Collegamento con BccPilot (filtri del catalogo) e con Plugin Audit: non deciso.

### Vincoli specifici

- Nessun servizio esterno nella gratuita; canali esterni solo in Pro, con consenso esplicito e documentazione (Guideline 7).
- Il `readme.txt` dice cosa il plugin NON fa: non corregge gli errori, non dice se il sito è sano o sicuro.
- La gravità si mostra sempre con testo e icona, mai solo con il colore.
- Non agganciare `wp_mail`: il plugin invia solo le proprie mail.
- `Plugin/logpilot/VINCOLI.md` è un file di sviluppo e fa eccezione alla regola "niente file di sviluppo": non va nello zip né in SVN. Prima di ogni commit SVN, `svn status` deve mostrare solo i file del plugin.
