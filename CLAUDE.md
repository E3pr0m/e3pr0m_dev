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
| PluginAudit | `Plugin/pluginaudit/` | Elenca i plugin del sito con avvisi di vulnerabilità, indizi d'uso e stato di salute. In sviluppo, non ancora pubblicato. Dettagli nella sezione "PluginAudit" in fondo. |
| LogPilot | `Plugin/logpilot/` | Facilita l'accesso a `debug.log` e all'`error_log` di PHP, evidenzia gli errori per livello e attiva/disattiva il debug da pannello. Versione 1.0.0 pronta per la pubblicazione (non ancora pubblicato). Dettagli nella sezione "LogPilot" in fondo. |

Lingua: commenti nel codice e risposte in italiano. Testi dell'interfaccia (`__()`) e `readme.txt` in inglese, come richiede la directory.

Pagina di amministrazione dei plugin: prima di creare o modificare la schermata di un plugin leggere `Brand/PAGINA-PLUGIN.md` (testata grigia, schede, verde su chiaro, pulsanti, controlli); l'implementazione di riferimento è LogPilot.

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

## PluginAudit

Cartella `Plugin/pluginaudit/`. Elenca i plugin del sito, attivi e non, con il loro stato di salute: avvisi di vulnerabilità divisi per gravità (colori) e categoria (con una breve descrizione), indizi d'uso, aggiornamenti disponibili, compatibilità PHP/WP, plugin disattivati ma installati. È un audit dello stato dei plugin, non una scansione del codice né del malware. Versione gratuita su WordPress.org e Pro come plugin separato (`pluginaudit-pro`). Stato: scheletro in sviluppo, non ancora pubblicato. File: `pluginaudit.php`, `uninstall.php`, `readme.txt`, `languages/` (solo il `.pot`) e `VINCOLI.md` (file di sviluppo, vedi sotto).

Le issue stanno nel Project `Plugin | Plugin Audit` (n. 10), label `pluginaudit` (gratuita, #26-#32) e `pluginaudit_pro` (Pro, #16-#25).

### Architettura (da definire)

- Fonte dei dati sulle vulnerabilità: Wordfence Intelligence v3, scelta provvisoria in attesa della risposta di Wordfence (uso in un plugin gratuito e Pro, limiti, campi del production feed). Ogni utente inserisce la propria chiave API. Lo scanner feed non ha CVSS né CWE: la categoria si ricava dal titolo e la gravità va decisa.
- Il feed è grande (circa 82 MB di JSON): da scaricare al massimo una volta al giorno, conservare e gestire il 429 senza errori visibili. Il problema della memoria in PHP è da verificare.
- Indizi d'uso: etichetta "nessun segno d'uso trovato", mai "inutilizzato".
- Integrazione con BccPilot: la Pro aggiunge la sua voce al catalogo con i filtri `e3p_bccpilot_notifications` e `e3p_bccpilot_notification_texts`. In BccPilot, dopo la pubblicazione di PluginAudit, una riga di testo con link se manca e il checkbox se è attivo.
- Scritture sul database: da definire (chiave API, feed in cache, impostazioni). Ognuna va documentata qui e rimossa da `uninstall.php`.

### Vincoli specifici

- Il nome deve essere vero il giorno del rilascio: il `readme.txt` dice anche cosa il plugin NON fa (non scansiona il codice, non cerca malware, non sostituisce un plugin di sicurezza).
- Servizi esterni: consenso esplicito prima del primo uso, dati inviati e termini documentati nel `readme.txt` (Guideline 7); si scaricano solo dati, mai codice (Guideline 8).
- Nessuna chiave API nel codice e nessuna chiave condivisa (termini Wordfence).
- Ogni voce di Wordfence mostrata riporta il link al record e il copyright e la licenza di Defiant.
- La gravità si mostra sempre anche con testo e icona, mai solo con il colore.
- `Plugin/pluginaudit/VINCOLI.md` è un file di sviluppo e fa eccezione alla regola "niente file di sviluppo": non va nello zip né in SVN. Prima di ogni commit SVN, `svn status` deve mostrare solo i file del plugin.

## LogPilot

Cartella `Plugin/logpilot/`. Legge `debug.log` e l'`error_log` di PHP, evidenzia gli errori per livello con spiegazioni semplici per utenti non esperti e permette agli amministratori di attivare e disattivare il debug da pannello, senza aprire file. Versione gratuita su WordPress.org e Pro come plugin separato (`logpilot-pro`). Stato (07/11/2026): versione 1.0.0 completa (tutte le issue gratuite chiuse), non ancora pubblicata; richiede WP 5.3 (usa `wp_date()`) e PHP 7.4. File: `logpilot.php`, `includes/` (config-editor, debug-switch, auto-off, log-location, download, email-excerpt), `assets/icon.svg`, `uninstall.php`, `readme.txt`, `languages/` (solo il `.pot`) e `VINCOLI.md` (file di sviluppo, vedi sotto).

Le issue stanno nel Project `Plugin | LogPilot` (n. 11), label `logpilot` (gratuita, #33-#41, tutte chiuse) e `logpilot_pro` (Pro, #42-#54).

### Architettura (da definire)

- Lettura: solo la coda dei file, con un limite. Il livello di ogni voce si riconosce dalla riga; voci su più righe trattate come una; righe non riconosciute come "Non classificato". Ogni riga mostrata va escapata (dato non fidato).
- Attivazione del debug: modifica di `wp-config.php` con backup, scrittura atomica, controllo di sintassi, ripristino automatico, solo le costanti di debug delimitate da un commento marcatore. Se il file non è scrivibile, il plugin mostra le righe da aggiungere a mano. `WP_DEBUG_DISPLAY` sempre spento; `DISALLOW_FILE_EDIT` non si tocca. Decisione del 05/11: codice proprio, senza WPConfigTransformer (MIT, compatibile con la GPL ma non serve), con la regola "riconosco o rifiuto": si tocca solo la forma standard delle tre costanti di debug; se il file ha una forma diversa (per esempio le `define()` dentro un `if`) il plugin non modifica nulla e mostra come dovrebbero essere scritte perché il pulsante funzioni; per ogni riga rifiutata il pannello dice una sola causa (dentro un blocco, non è un `define()` semplice, valore diverso da true/false, altro codice sulla riga, percorso non di LogPilot) e ne mostra il testo (messo alla prova su un sito live il 07/11, dove `WP_DEBUG` era in un `if`). Il resto di `wp-config.php` non ci riguarda.
- Spegnimento automatico o promemoria (#40, `includes/auto-off.php`, decisioni del 05/11): all'accensione dal pulsante si sceglie in un menu "Turn logging off automatically / After": 1 ora, 6 ore, 24 ore (preselezionata), 3 giorni, 7 giorni, oppure "Don't turn it off, remind me" con intervallo (ogni 1, 3 o 7 giorni) e indirizzo email, obbligatorio e controllato (l'email dell'amministratore è solo una proposta nel campo, modificabile; nessun indirizzo nel codice). Vale solo per l'accensione fatta dal pulsante: un debug acceso a mano non ha un'ora di inizio. Lo spegnimento usa le stesse protezioni del pulsante (`e3p_logpilot_turn_off_now`), parte da WP-Cron (`e3p_logpilot_auto_off`) e si ricontrolla a ogni `admin_init`, perché WP-Cron gira solo con le visite; se fallisce il debug resta acceso, il pannello lo dice e parte una sola mail (`e3p_logpilot_send_failure_notice`, senza parti del log) all'indirizzo dell'amministratore impostato in WordPress (`admin_email`): scelta del 05/11, dichiarata nel pannello prima di accendere, perché in modalità automatica non c'è un campo email. È l'unico caso in cui si usa l'indirizzo dell'amministratore senza che l'utente lo confermi; per il promemoria resta una proposta modificabile. Il promemoria (`e3p_logpilot_remind`, evento singolo che si ripianifica) usa `wp_mail()` chiamato direttamente, senza agganciare le mail del sito, e non contiene parti del log; se il debug risulta già spento si annulla da solo. Spegnere dal pulsante, disattivare il plugin e disinstallarlo tolgono impostazioni ed eventi (alla disattivazione il debug acceso resta acceso).
- Posto del log (#41, `includes/log-location.php`, decisioni del 05/11): accendendo dal pulsante `WP_DEBUG_LOG` non vale `true` ma il percorso di una cartella con nome non indovinabile, `e3p-logpilot-<16 caratteri esadecimali ricavati da wp_hash()>` (stesso calcolo in `uninstall.php`), sempre uguale per il sito, quindi non va salvato (nessuna scrittura nel database) e si ritrova anche a debug spento. Posto: la cartella sopra WordPress se è scrivibile e fuori dalla radice web (`DOCUMENT_ROOT`; se non si stabilisce si assume il caso peggiore), altrimenti `wp-content` con `.htaccess` (Apache), `web.config` (IIS) e `index.php`; Nginx ignora queste regole. Verifica (`e3p_logpilot_run_checks`, all'accensione e col pulsante "Check that my logs cannot be downloaded"): un file di controllo in `wp-content` prova che l'indirizzo corrisponde alla cartella, poi un file di prova nella cartella dei log; esiti `outside`, `protected`, `exposed`, `unknown`, mostrati senza salvarli (parametri `e3p_logpilot_pr` e `e3p_logpilot_od`). Il motore di `wp-config.php` accetta in `WP_DEBUG_LOG` un percorso tra apici solo se contiene la cartella di LogPilot; un percorso scelto dall'utente resta "forma non standard". Il vecchio `wp-content/debug.log` si controlla (HEAD) e si può eliminare con pulsante, nonce e casella di conferma (solo quel file). `uninstall.php` cancella la cartella dei log (fuori dal sito e in `wp-content`) perché può contenere dati sensibili; prima di disinstallare conviene scaricarli (#37) e spegnere il debug, altrimenti `wp-config.php` resta con un percorso che non esiste più.
- Scritture sul database: solo l'opzione `e3p_logpilot_settings` (modo, ora di accensione, scadenza, giorni, email del promemoria, ultimo errore dello spegnimento) e i due eventi di WP-Cron, creati all'accensione e rimossi allo spegnimento, alla disattivazione e da `uninstall.php`. Scritture su disco: solo `wp-config.php` (a richiesta, con pulsante, nonce e casella di conferma) e la sua copia di sicurezza `wp-config-logpilot-backup.php` accanto al file (il file com'era prima di accendere: si rifà a ogni accensione e, spegnendo, solo se manca; il ripristino automatico usa il contenuto letto in memoria), con estensione `.php` perché un `.bak` potrebbe essere scaricabile con le password dentro; la cartella dei log con le sue regole di protezione (vedi sopra) e, a richiesta con conferma, l'eliminazione del vecchio `wp-content/debug.log`; `uninstall.php` rimuove la copia e la cartella dei log, non le righe in `wp-config.php`.
- Debug da pannello (#39): `includes/config-editor.php` (analisi e modifica del testo, scrittura sicura: backup, file temporaneo, controllo di sintassi con `token_get_all( TOKEN_PARSE )`, `rename` atomico, rilettura e ripristino) e `includes/debug-switch.php` (sezione del pannello e pulsante). Ogni riga toccata porta un commento: `// LogPilot (original: false)` oppure `// LogPilot (added)`, così lo stato originale del debug non si salva nel database. Acceso: `WP_DEBUG` e `WP_DEBUG_LOG` true, `WP_DEBUG_DISPLAY` sempre false. Spento: `WP_DEBUG` e `WP_DEBUG_LOG` a false (decisione 05/11: non si ripristina l'originale), le righe aggiunte da noi si tolgono, e se in origine erano true il pannello lo segnala. Se il file non è nella forma standard o non è scrivibile, il pannello non tocca nulla e mostra le righe da scrivere a mano.
- Aspetto (06/11): icona `assets/icon.svg` accanto al titolo; verde del brand al posto del blu (`#15803d` testo e pulsanti, `#2BD576` solo barre decorative) con le regole sui pulsanti limitate alla classe `e3p-logpilot-wrap`; restano blu i livelli notice/deprecated.
- Pannello: Strumenti → LogPilot (`manage_options`), `logpilot.php` (pagina, lettura, filtri, tabella, CSS e JS) con funzioni `e3p_logpilot_*`, più le parti in `includes/`. Implementati #33 (coda con limite, filtri `e3p_logpilot_tail_bytes` e `e3p_logpilot_per_page`: 50 voci per pagina, pagine sulle sole voci già lette, parametro `paged`, nessuna lettura in più dal disco) e #34 (livelli: fatal, parse, database, warning, notice, deprecated, non classificato). Intervallo di giorni (solo giorni, non ore): date `Y-m-d` come scritte nel log, offset trovati con ricerca a bisezione (il log è cronologico), limite 2 MB per intervallo (filtro `e3p_logpilot_max_range_bytes`), oltre il quale il plugin non carica e lo dice; senza intervallo si legge la coda del log, per default quanto può caricare un intervallo (2 MB, ridotto dalla memoria; il filtro `e3p_logpilot_tail_bytes` può abbassarla), così "le voci più recenti" e gli intervalli sono coerenti per l'utente (deciso il 05/11). I conteggi riguardano la sola parte letta. Il tetto dei 2 MB scende se la memoria libera di PHP è poca: `(memory_limit - memoria in uso - 8 MB) / 25` (fattore misurato: 2 MB di log usano circa 40 MB di picco), con `memory_limit` illimitato vale 2 MB; sotto i 64 KB il log non si carica. L'avviso spiega come aumentare la memoria (`WP_MEMORY_LIMIT`, `php.ini`, assistenza dell'hosting). Niente "ultime 24 ore" (deciso il 05/11: il fuso orario nel log non è sempre dichiarato) e niente cache dei contatori nella gratuita (2 MB si analizzano in circa 40 ms): la cache è nell'issue Pro #55. Interfaccia pensata per chi non conosce i log (05/11): riquadro di accensione in parole semplici, tre gruppi di livelli (Serious problems, Warnings, Minor notes) sopra il filtro per tipo esatto, tabella con frase semplice (`e3p_logpilot_explain`), colonna "Reported in" (plugin, tema o core dal percorso, `e3p_logpilot_find_origin`) che sugli schermi stretti passa sotto la frase, ora leggibile e tempo relativo (solo se il log dichiara UTC), voci uguali raggruppate (`e3p_logpilot_group_hash`; `flat=1` le elenca tutte, `grp=<chiave>` mostra un solo messaggio), pagine da 50 righe, due campi di ricerca (colonne "What happened" e "Reported in"), pulsanti dei messaggi più ripetuti e dei problemi noti (`e3p_logpilot_common_problems`), pulsante "Copy" sulle voci e "Copy a summary for support" (versione prudente con `e3p_logpilot_scrub`, e completa con avviso sui dati personali). Ogni controllo che filtra si toglie con un secondo clic. Filtri e ricerca non scrivono nulla nel database: stanno nell'indirizzo.
- Scarico (#37, `includes/download.php`): pulsante POST con nonce e `manage_options`, solo la chiave di un log noto (mai percorsi in ingresso), file servito a blocchi da PHP, tetto 50 MB (`e3p_logpilot_download_max_bytes`) oltre il quale si scarica la sola parte finale. Email (#38, `includes/email-excerpt.php`): `wp_mail()` diretto, indirizzo digitato a ogni invio (l'admin è solo una proposta, mai salvato), 20/50/100 righe, pulizia con `e3p_logpilot_scrub` preselezionata, conferma obbligatoria, tetto 100 KB (`e3p_logpilot_email_max_bytes`). L'esito di entrambi compare nella riga delle azioni (ancora `#e3p-logpilot-actions`, ritorno allo stesso log). Pagina a quattro schede con testata grigia: regole in `Brand/PAGINA-PLUGIN.md`.
- Collegamento con BccPilot (filtri del catalogo) e con PluginAudit: non deciso.

### Vincoli specifici

- Nessun servizio esterno nella gratuita; canali esterni solo in Pro, con consenso esplicito e documentazione (Guideline 7).
- Il `readme.txt` dice cosa il plugin NON fa: non corregge gli errori, non dice se il sito è sano o sicuro.
- La gravità si mostra sempre con testo e icona, mai solo con il colore.
- Non agganciare `wp_mail`: il plugin invia solo le proprie mail.
- `Plugin/logpilot/VINCOLI.md` è un file di sviluppo e fa eccezione alla regola "niente file di sviluppo": non va nello zip né in SVN. Prima di ogni commit SVN, `svn status` deve mostrare solo i file del plugin.
