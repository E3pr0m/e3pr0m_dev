# Sessione 2026-11-02: sito e3pr0m.com, sezione plugin

## Scopo
Aggiungere al sito e3pr0m.com i tre plugin (BccPilot, Plugin Audit, LogPilot) nella sezione Progetti già esistente. Più avanti il tema custom che si sta costruendo andrà nella stessa sezione, come progetto di tipo "tema". Plugin Audit e LogPilot compaiono con lo stato "In arrivo".

## Dove sta il sito (verificato)
- Cartella Local: `C:\Users\Eeprom\Local Sites\e3pr0mcom\` (esistono anche `e3pr0mux`, `e3pr0mux-react`, `e3pr0mux-react-no_claude`: non toccati).
- Tema: `app/public/wp-content/themes/e3pr0m-theme`.
- Servizi da `AppData\Roaming\Local\sites.json`: MySQL 8.0.35 (porta 10010), PHP 8.5.1, Apache 2.4.43. Database `local`, utente `root`, prefisso `wp_`.
- `wp` (WP-CLI) non è nel PATH della shell di Claude; in Local c'è "Open site shell". Il PHP 8.5 in `C:\php-8.5.11` esiste ma non ho verificato che abbia `mysqli`.

## Com'è fatta la sezione Progetti (letto)
- Custom post type `projects` con tassonomia `project_type`, registrato dal plugin `e3pr0m-projects` (`includes/cpt.php`). Nella cartella plugin ci sono anche `e3pr0m-projects-OOP` e `e3pr0m-portfolio-cpt` che registrano lo stesso CPT: quale sia attivo si vede solo nel database, non verificato.
- Tema: `archive-projects.php`, `template-parts/card-project.php`, home in `index.php` (ultimi progetti).
- Meta della card: `_project_tech` (etichetta), `_project_github`, `_project_demo` (bottoni). Il contenuto sta nel database, non nei file.

## Decisioni
- I tre plugin entrano come voci del CPT `projects`, non come pagine nuove.
- Plugin Audit e LogPilot "in arrivo".
- Proposta (in attesa di ok): meta `_project_status` nel plugin `e3pr0m-projects` (campo nel meta box) e badge "In arrivo" nella card; il badge sparisce alla pubblicazione.

## Da fare
1. Ok dell'utente a toccare `e3pr0m-projects` per `_project_status` + badge nella card.
2. Creare le tre voci: testi presi dai `readme.txt` di `Plugin/`. Strada preferita: WP-CLI da "Open site shell" di Local, comandi preparati da Claude e lanciati dall'utente; alternativa: testi da incollare nell'admin.
3. Verificare quale dei tre plugin CPT è attivo prima di scrivere meta.
4. Tema custom: da costruire, andrà come progetto di tipo "tema".

## Nulla è stato modificato
Solo lettura di tema, plugin e `sites.json`. Nessun file del sito o del repo cambiato, tranne questa nota e la memoria. Nessun commit.

## Errore da non ripetere
Ho scritto "MariaDB" deducendolo dalle cartelle di Local, ma il sito usa MySQL. Per i siti Local si legge `sites.json` prima di nominare database, PHP o server (regola salvata in memoria, `feedback_controlla-due-volte`).
