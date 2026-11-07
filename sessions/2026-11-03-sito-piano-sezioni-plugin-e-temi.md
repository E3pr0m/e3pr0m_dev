# Sessione 2026-11-03: sito e3pr0m.com, piano sezioni Plugin e Temi

Continua `2026-11-02-sito-e3pr0mcom-sezione-plugin.md`. Il piano di quella sessione (voci nel CPT `projects`, meta `_project_status`) è sostituito da questo.

## Obiettivo (deciso dall'utente)
- Presentare brevemente ogni plugin sul sito.
- Ogni plugin ha una pagina propria: serve come `Plugin URI` nel readme e nell'intestazione.
- Plugin Audit e LogPilot subito online con stato "In arrivo" e una breve descrizione.

## Decisioni
- Sezioni separate per tipo di contenuto, non un solo CPT con tassonomia.
- URL stabili: `/plugin/<slug>/` e, più avanti, `/theme/<slug>/`.
- "Progetti" (`e3pr0m-projects`) verrà eliminato dall'utente disattivando il plugin; si potrà riusare in futuro. Nessun lavoro da fare su di esso.
- Idee per un eventuale riuso di "Progetti": piccoli strumenti e utility, casi di debug WordPress come casi studio, esperimenti e siti di prova (`e3pr0mux`).

## Piano proposto (in attesa di ok finale, dopo il branding)
1. Nuovo plugin del sito `e3pr0m-plugins`: CPT `e3p_plugin` con slug `plugin`, archivio `/plugin/`; meta box con stato (In arrivo / Pubblicato), versione, link WordPress.org, link GitHub, requisiti PHP/WP. Il CPT `theme` verrà dopo, con lo stesso schema.
2. Tema `e3pr0m-theme`: `archive-e3p_plugin.php`, `single-e3p_plugin.php`, `template-parts/card-plugin.php` con badge "In arrivo"; in `index.php` la sezione "Ultimi progetti" e il pulsante "Vedi i progetti" passano a `/plugin/` (oggi usano `projects`); CSS minimo; flush dei permalink una volta.
3. Contenuti: tre schede dai `readme.txt` (italiano per il sito), da incollare nell'admin o via WP-CLI. Plugin Audit e LogPilot dicono anche cosa NON fanno.
4. Dopo: l'utente disattiva `e3pr0m-projects`; `Plugin URI` dei tre plugin e dei readme aggiornato solo quando le pagine sono online.

## Cosa ho trovato nel codice (letto, nulla modificato)
- Il tema non ha `single-projects.php`: il singolo progetto usa `single.php`.
- Home e hero puntano a `get_post_type_archive_link('projects')`: disattivando il plugin restano vuoti finché non si cambiano.
- Nella cartella plugin del sito ci sono anche `e3pr0m-codesnippet` e `e3pr0m-devcard`.

## Domande ancora aperte
- Lavorare solo sul sito in locale (Local, `e3pr0mcom`) e pubblicare a mano: da confermare.
- Stile delle pagine: oggi il tema è "// terminale" con font mono; dipende dal branding.
- Testi: li propongo io dai readme o li scrive l'utente.

## Prossimo passo: branding
Va definito prima di grafica e contenuto delle pagine. Da fare nella prossima fase: nome e tono, logo, colori, tipografia, e se il tema attuale resta la base o cambia. Il branding vale per sito, plugin e tema custom.

## Nulla è stato modificato
Solo lettura di tema e plugin. Nessun file del sito o del repo cambiato, tranne queste note. Nessun commit.
