# Sessione 2026-11-01: LogPilot (secondo plugin)

## Scopo
Plugin per facilitare l'accesso ai log di errore di PHP e di WordPress (`debug.log`) a utenti non esperti: aprirli, scaricarli, riceverli per email e soprattutto attivare e disattivare il logging da pannello, senza aprire file. Versione gratuita e Pro come plugin separati. Solo amministratori. Nessun servizio esterno nella gratuita. Requisiti invariati (PHP 7.4+, WP 5.2+, nessuna dipendenza).

## Nome
Nome visibile **LogPilot**, slug `logpilot` (libero al 2026-11-01: nessun plugin pubblicato; non vede slug riservati). Prefisso codice `e3p_logpilot_`, costanti `E3P_LOGPILOT_`, Text Domain `logpilot`. Collegamento con BccPilot o Plugin Audit: non deciso.

## Log coperti
- Partenza: `debug.log` di WordPress (esiste solo con `WP_DEBUG_LOG` attivo, percorso anche personalizzato) e `error_log` di PHP (percorso dall'ini `error_log`; alcuni host scrivono un `error_log` in ogni cartella).
- Altri log (Apache, Nginx, PHP-FPM, MySQL) spesso non raggiungibili da PHP: eventualmente dopo.
- I file possono essere enormi: si legge solo la coda, con un limite. I log contengono dati sensibili.

## Attivare e disattivare il debug
`WP_DEBUG` e `WP_DEBUG_LOG` si leggono prima del caricamento dei plugin, quindi serve modificare `wp-config.php`. I concorrenti (Debug Log Manager e simili) lo fanno già con una classe tipo WPConfigTransformer (licenza da verificare), `is_writable` e scrittura diretta, senza backup né controllo di sintassi. Debug Log Manager imposta anche `DISALLOW_FILE_EDIT` a false: noi NON lo facciamo. Nel loro changelog c'è una correzione di XSS memorizzato da contenuto del log: le righe del log sono sempre dati non fidati e vanno escapate.

Protezioni approvate:
- copia di backup prima della modifica, scrittura atomica (file temporaneo e rename), controllo di sintassi, ripristino automatico in caso di errore;
- controllo `is_writable`: se non scrivibile, mostrare le righe da aggiungere a mano, senza interruttore;
- si toccano solo le costanti di debug, delimitate da un commento marcatore riconoscibile;
- `manage_options`, nonce e conferma esplicita;
- percorso del log personalizzato, non indovinabile e protetto; `WP_DEBUG_DISPLAY` spento;
- spegnimento automatico dopo una durata scelta (default) oppure, se disattivato, promemoria via email ogni N giorni. Indirizzo impostato dall'utente: l'email dell'amministratore compare come proposta modificabile a runtime, mai scritta nel codice.

## Evidenza degli errori per l'utente inesperto (decisa: nella gratuita)
Riconoscimento del livello dalla riga (`Fatal error`, `Parse error`, `Warning`, `Notice`, `Deprecated`, `WordPress database error`). Etichetta di testo e colore (mai solo colore), frase semplice per livello, riassunto in cima ("N errori gravi nelle ultime 24 ore"). Righe non riconosciute: "Non classificato", mai indovinate. Voci su più righe (stack trace) da trattare come una sola. Contatori con cache che si aggiorna solo se il file cambia (data e dimensione). Le frasi sono nostre, senza promesse. Il testo dell'errore resta in inglese. Il plugin non dice "sito sano" o "sito in pericolo".

## Free (9 issue) e Pro (13 idee)
- Free: lettura con limite, evidenza per livello, filtro e ricerca, plugin/tema dal percorso, download, estratto per email (senza agganciare `wp_mail`), attivazione da pannello, spegnimento automatico o promemoria, percorso del log personalizzato e protetto.
- Pro: avvisi sui nuovi errori, riepilogo periodico, altri canali (con consenso), raggruppamento con contatore e andamento, statistiche per plugin e tema, archivio con rotazione, versioni di `wp-config.php` con ripristino, mascheramento dei dati sensibili, profili, report esportabili, WP-CLI, multisite, accesso in sola lettura.
- Nucleo Pro suggerito: avvisi e riepilogo, raggruppamento, archivio con rotazione, versioni di `wp-config.php` con mascheramento.
- Principio: la gratuita è completa, la Pro aggiunge automazione, storico e scala.

## Punti aperti
- Licenza della classe WPConfigTransformer (non usarla senza verificarla; valutare codice proprio).
- Collegamento con BccPilot (filtri del catalogo) e con Plugin Audit (errori per plugin come indizio d'uso).
- Testo delle Guideline 11 da rileggere prima di inserire link promozionali.
- Lettura della coda su file molto grandi: da provare.

## Fatto in sessione
- Project n. 11 `Plugin | LogPilot` (https://github.com/users/E3pr0m/projects/11), label `logpilot` e `logpilot_pro`.
- Issue gratuite #33-#41 (create prima), Pro #42-#54, tutte assegnate a E3pr0m e nel Project.
- Scheletro `Plugin/logpilot/` (`logpilot.php`, `uninstall.php`, `readme.txt`, `languages/logpilot.pot`, `VINCOLI.md`), riga in tabella e sezione "LogPilot" in `CLAUDE.md`.
- I Project n. 10 e n. 11 erano stati creati ma non collegati al repository (si vedeva solo il n. 9): collegati con `gh project link`. Per i prossimi: collegare subito dopo la creazione.

## Prossimi passi
- Primo lavoro sul codice: lettura della coda dei log con limite (#33) e riconoscimento dei livelli (#34).
- Decidere la licenza/alternativa a WPConfigTransformer prima della #39.
- Decidere se il log personalizzato si cancella alla disinstallazione (#41).
- Rileggere le Guideline 11 prima di inserire link promozionali.
