# Sessione 2026-10-24: repo, Project e prime issue

## Contesto
Il repo era stato cancellato e la cartella spostata fuori da OneDrive in `C:\E3pr0m_dev`, per evitare problemi con `.git`.

## Fatto
- Primo commit (`96d2bb7`) fatto dall'utente. `.gitignore` esclude `vendor/` e i tre file composer.
- Il push non partiva: `origin` puntava a un URL con segnaposto. Corretto con `git remote set-url` su `https://github.com/E3pr0m/e3pr0m_dev.git`.
- Letto il codice del plugin (v1.0.0): Bcc nascosto su 7 notifiche di sistema, mai `wp_mail`.
- `gh` aggiornato con lo scope `project` (`gh auth refresh -h github.com -s project`, da terminale interattivo).
- Creato il Project `Plugin | Admin Mail Bcc` (n. 9): https://github.com/users/E3pr0m/projects/9, collegato al repo.
- Creata la label `amb`.
- Create e assegnate all'utente due issue, entrambe nel Project:
  - #1 Aggiungere banner e icone per la directory WordPress.org (`amb`, `enhancement`)
  - #2 Chiarire e verificare il flusso delle traduzioni (`amb`, `documentation`)

- Plugin Check eseguito sul sito e3pr0m.com con tutte le categorie: nessun errore e nessun avviso (incluso `Tested up to: 7.1`).

- Readme validato con il validator di WordPress.org (solo avvisi su sezioni facoltative) e contributore `e3pr0m` verificato.
- Creata l'issue #3 (pubblicazione 1.0.0), con voce per il `Donate link` di GitHub Sponsors (profilo inviato, in attesa di approvazione).
- Slug cambiato in `admin-mail-bcc` (senza prefisso `e3p`): cartella, file principale, Text Domain e file in `languages/` rinominati; `CLAUDE.md` aggiornato. Il prefisso `e3p` resta solo in funzioni, opzioni, hook e costanti. Modifiche locali, ancora da committare.
- Plugin Check rieseguito dopo la rinomina: tutto ok (il messaggio "not found in the WordPress.org SVN repository" è atteso finché il plugin non è pubblicato).

- Commit e push della rinomina dello slug fatti dall'utente (branch `main`) con lo slug `admin-mail-bcc`, poi superato dal nome BccPilot.
- Zip di pubblicazione rigenerato in `dist/admin-mail-bcc.zip` (cartella `admin-mail-bcc/` alla radice, 6 file, verificato). `/dist/` e `*.zip` aggiunti al `.gitignore` (modifica non ancora committata).

- Guideline 17 della directory: "Admin Mail Bcc" è troppo generico. Nome definitivo scelto: **BccPilot** (slug `bccpilot`, ricerche su UE/USA, directory e Google senza collisioni; il marchio USA trovato è abbandonato dal 2005). Il plugin è stato rinominato di nuovo (cartella, file, Text Domain, `languages/`) e le descrizioni riscritte per non limitarsi alle mail di sistema. Plugin Check ok. Il prefisso `e3p_amb_` resta nel codice.
- Zip aggiornato in `dist/bccpilot.zip`. Project e issue #3 rinominati con il nuovo nome.
- Utente sta creando `dev@e3pr0m.com` da usare come email dell'account wordpress.org.

## Decisioni
- Slug senza prefisso `e3p`, per non dipendere dall'approvazione di un revisore a ogni plugin.
- Un Project per ogni cosa creata, più una label con la sigla del plugin.
- Le issue si mostrano in bozza e si creano solo dopo l'ok dell'utente; vanno assegnate all'utente.
- Traduzioni: il plugin non usa `load_plugin_textdomain()`. In produzione le traduzioni arrivano da translate.wordpress.org; i file in `languages/` sono la base di partenza.

## Da fare / aperto
- Committare la rinomina in `Plugin/bccpilot/` (`git add -A Plugin`), `CLAUDE.md`, `.gitignore` e `sessions/`.
- Verificare che `wordpress.org/plugins/bccpilot/` dia 404, poi inviare `dist/bccpilot.zip` dal modulo di WordPress.org.
- Rimuovere dal sito e3pr0m.com le vecchie cartelle `e3p-admin-mail-bcc` e `admin-mail-bcc` (se presenti).
- Aggiungere il `Donate link` (GitHub Sponsors) quando il profilo è approvato.
- Issue #1 e #2.
- Cambiare l'email dell'account wordpress.org con `dev@e3pr0m.com` prima di inviare lo zip.
- Decidere se escludere `.vscode/` dal repo.
- Il `CLAUDE.md` non ha una roadmap: l'utente deve ancora elencare le funzioni da implementare (plugin gratuito e Pro), poi scriverle nel `CLAUDE.md`.
