# Sessione 2026-10-24/25: repo, rinomina in BccPilot, invio a WordPress.org, backlog

## Contesto
Il repo era stato cancellato e la cartella spostata fuori da OneDrive in `C:\E3pr0m_dev`, per evitare problemi con `.git`. Il plugin (Bcc nascosto su 7 notifiche di sistema, mai `wp_mail`) era alla 1.0.0 con il nome "Admin Mail Bcc".

## Fatto
**Repo e GitHub**
- Remote `origin` corretto (aveva un URL con segnaposto): `https://github.com/E3pr0m/e3pr0m_dev.git`, branch `main`.
- `gh` con scope `project` (`gh auth refresh -h github.com -s project`, da terminale interattivo, non con `!`).
- Project `Plugin | BccPilot` (n. 9): https://github.com/users/E3pr0m/projects/9. Label per plugin: `bccpilot` (gratuito), `bccpilot_pro` (Pro).
- Repo allineato a `origin/main`; nel repo c'è solo `Plugin/bccpilot/`.

**Plugin: nome, slug, codice**
- Guideline 17 della directory: "Admin Mail Bcc" troppo generico. Nome scelto: **BccPilot** (slug `bccpilot`), con ricerche su UE/USA, directory e Google senza collisioni (un marchio USA trovato è abbandonato dal 2005).
- Rinominati cartella, file principale, Text Domain e file in `languages/`; `.mo` rigenerato in locale con uno script Python (non c'è `msgfmt`).
- Descrizioni riscritte per non limitarsi alle mail di sistema (la Pro aggiungerà altre notifiche).
- Rimossa la costante `wp-config.php` (era per bloccare l'indirizzo): rimandata a sviluppi futuri (#4).
- Prefisso del codice: `e3p_bccpilot_` (`e3p` = autore, comune a tutti i prodotti; `bccpilot` = prodotto). Opzione: `e3p_bccpilot_settings`.
- `CLAUDE.md` aggiornato (nomenclatura, architettura). `/dist/` e `*.zip` nel `.gitignore`.

**Controlli e invio**
- Plugin Check: nessun errore né avviso (rieseguito dopo ogni rinomina). Il messaggio "not found in the WordPress.org SVN repository" è atteso finché non è pubblicato.
- Readme validato; contributore `e3pr0m` verificato. Rivista la conformità alle linee guida (punti toccati: 1, 4, 5, 7, 8, 10, 11, 12, 13, 16, 17).
- Email dell'account wordpress.org cambiata in `dev@e3pr0m.com` (l'inoltro verso Gmail funziona).
- **Plugin inviato il 2026-10-24** con `dist/bccpilot.zip` (cartella `bccpilot/` alla radice, 6 file). Mail di conferma ricevuta: slug `bccpilot`. In coda di prima review (776 plugin; risposte già date agli invii prima del 18 ottobre).

**Issue (tutte assegnate a E3pr0m e nel Project 9)**
- #1 Banner e icone · #2 Flusso traduzioni · #3 Pubblicazione 1.0.0 (checklist aggiornata) · #4 Valutare la costante `wp-config.php`
- Gratuito: #5 Filtro `e3p_bccpilot_recipients` (da fare prima della Pro, versione 1.1.0) · #6 Pulsante "invia una mail di prova"
- Pro: #7 Notifiche ampliate · #8 Più indirizzi per notifica (richiede #5) · #9 Log per indirizzo (richiede #5, #8) · #10 Digest · #11 Condizioni · #12 Altri canali (webhook, Slack) · #13 Multisite · #14 Esporta/importa impostazioni

## Decisioni
- Slug senza prefisso `e3p` (il nome conta di più per la directory); `e3p_<prodotto>_` per funzioni, opzioni, hook; costanti `E3P_<PRODOTTO>_`.
- La Pro è un plugin separato che usa i filtri del gratuito. Il filtro sui destinatari è pubblico e documentato: nessuna funzione bloccata nel gratuito (Guideline 5). La parte visibile (interfaccia, log, notifiche extra) vive solo nella Pro.
- Lo zip serve solo per la prima sottomissione; dopo l'approvazione le release vanno in SVN. Lo zip si fa a mano (nessuno script nel repo).
- `Plugin URI` rimandato a quando ci sarà una pagina dedicata su e3pr0m.com (richiede una nuova versione, es. 1.0.1). `Donate link` (GitHub Sponsors, profilo in approvazione) e `Plugin URI` possono andare nella stessa release.
- Le issue si mostrano in bozza e si creano solo dopo l'ok; vanno assegnate all'utente. Le risposte all'utente devono essere brevi e tecniche (esperto WordPress).
- Traduzioni: il plugin non usa `load_plugin_textdomain()`; in produzione arrivano da translate.wordpress.org.

## Da fare / aperto
- Committare questo riepilogo.
- Attendere la review (mail a `dev@e3pr0m.com`, spam compreso; aggiungere `plugins@wordpress.org` ai contatti) e rispondere nello stesso thread.
- Dopo l'approvazione: installare un client SVN (`winget install Slik.Subversion`), `trunk/` + `tags/1.0.0`, un solo commit (linea guida 14).
- Rimuovere dal sito e3pr0m.com le vecchie cartelle `e3p-admin-mail-bcc` e `admin-mail-bcc` (se presenti).
- Nei prossimi giorni: #6 (mail di prova) e #5 (filtro) su un branch `v1.1`, da unire dopo l'approvazione della 1.0.0; poi nuovo Plugin Check e traduzioni aggiornate.
- Indipendenti dalla review: #1 (banner e icone) e #2 (traduzioni).
- Decidere se escludere `.vscode/` dal repo.
