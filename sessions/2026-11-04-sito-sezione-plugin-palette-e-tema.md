# Sessione 2026-11-04: palette del sito, sezione Plugin nel tema, banner BccPilot online

Continua `2026-11-03-brand-icone-wordmark-banner-bccpilot.md` e `2026-11-03-sito-piano-sezioni-plugin-e-temi.md`. Il sito è in Local (`C:\Users\Eeprom\Local Sites\e3pr0mcom\`, http://e3pr0mcom.local). Si lavora solo in locale; l'utente fa il commit dalla cartella del sito e aggiorna il sito online da git via FTP. Nessun commit fatto da me in questa sessione.

## Decisioni
- **Palette mista per fasce** (scuro per header/hero/footer/card, chiaro per i testi di lettura), valori definitivi in `Brand/BRAND.md`, sezione "Palette del sito". Tutto scuro scartato (`Brand/prove/palette-pratica-scuro.html`). I colori nel CSS sono sempre variabili.
- Il sito si costruisce a mano nel tema (PHP, CSS, JS semplice), con la skill `frontend-design`. Non si usano le skill/template di AnimatedWebs. Ammissione: la skill è stata caricata ma il suo metodo (piano, revisione, critica) non è stato seguito; l'utente ha poi corretto a mano l'aspetto. Se si rifà una pagina, partire dal piano.
- **Card plugin**: mista A+C (descritta in `BRAND.md`).
- **Nome sul sito in minuscolo** (`e3pr0m`) in header, hero e footer, con solo 3 e 0 in verde. Il `>` davanti e il cursore lampeggiante nell'header sono rimasti. **Zero puntato solo per il nome** (logo, titolo hero, nome nel footer), non nel resto del sito. Le grafiche (banner, social) hanno ancora "E3pr0m" maiuscolo: la regola "sito minuscolo, grafiche maiuscolo" NON è ancora scritta in `BRAND.md`, chiesto all'utente.
- Titolo hero: Plex Sans peso 400.

## Fatto nel sito (tutto in locale)
- **Plugin del sito `e3pr0m-plugins`** (`wp-content/plugins/e3pr0m-plugins/e3pr0m-plugins.php`): CPT `e3p_plugin` (archivio `/plugin/`), meta `_e3p_status` (coming/published), `_e3p_version`, `_e3p_php`, `_e3p_wp`, `_e3p_wporg`, `_e3p_github`; icona = immagine in evidenza; colonne nella lista.
- **Tema `e3pr0m-theme`** (versione 1.1.0): font IBM Plex in locale in `assets/fonts/` (WOFF, perché `brotli` non è installato; licenze OFL incluse), tolta la chiamata a Google Fonts; token colore nuovi in `style.css` e fascia `.band-light` per la lettura; nuovi template `archive-e3p_plugin.php`, `single-e3p_plugin.php`, `template-parts/card-plugin.php`; helper in `inc/template-functions.php` (`e3pr0m_plugin_name`, `e3pr0m_plugin_data`, `e3pr0m_plugin_meta_line`, `e3pr0m_name`); home dalla sezione progetti a quella plugin; stili in `assets/css/main.css`; versione dei CSS con `filemtime` (contro la cache).
- Sfondo dei banner anche sul sito: `body::before` fisso, trama in `assets/img/sfondo-codice.svg` (126 KB), generata da `Brand/icona/genera_sfondo_sito.py`. Spazi ridotti (`--space-xl` 2.5rem, `--space-2xl` 4rem). Tocchi di verde nelle fasce chiare (`//` nei titoli, pallini, riquadro `.nota`).
- Script `Brand/prove/crea-schede-plugin.php`: crea/aggiorna le tre schede (BccPilot Pubblicato, PluginAudit e LogPilot In arrivo, testi proposti da me dai `readme.txt`, ancora da rileggere). L'utente lo lancia da Local con `wp eval-file`. L'ultima versione aggiunge il riquadro "Cosa non fa": va rilanciato per vederlo.

## Da fare / aperto
- Rilanciare lo script in locale (riquadro "Cosa non fa") e rileggere i testi delle schede. GitHub delle schede: vuoto (chiesto se il repo è pubblico).
- **Online** (non fatto): caricare tema e plugin `e3pr0m-plugins`, attivarlo, salvare i permalink; poi portare le tre schede con Strumenti → Esporta/Importa (con allegati; il plugin attivo prima dell'import), non con lo script (percorsi locali).
- Altre pagine del sito da completare (da decidere quali). Pagina singola del plugin guardata solo per BccPilot.
- Hero: il typing sostituisce il punto e le parole si attaccano a "e3pr0m" (comportamento già del tema vecchio); chiesto se mettere uno spazio o un separatore.
- Disattivare `e3pr0m-projects` (l'utente), poi aggiornare `Plugin URI` dei tre plugin e dei readme solo con le pagine online. Rinominare il Project GitHub `Plugin | Plugin Audit` (n. 10) in PluginAudit (l'utente), poi CLAUDE.md riga 82.
- Il tema ha ancora altre pagine/stili col look vecchio da controllare (blog, singolo post, pagine).
- Kit v1 ancora da eliminare a cura dell'utente (vedi nota del 2026-11-03).

## Altro avvenuto oggi
- Banner di PluginAudit e LogPilot, immagine social 1200×630 (riga "Developer on WordPress.org"), card plugin: vedi nota del 2026-11-03 (aggiornata).
- Grafiche di BccPilot caricate su WordPress.org (r3784232, hook SVN: serve `--username e3pr0m` minuscolo); online e nitide, confermato dall'utente; **issue #1 chiusa** da me su richiesta, con commento.
- Nome ufficiale **PluginAudit** allineato in tutti i file.

## Per il commit dalla cartella del sito
Il tema e il plugin `e3pr0m-plugins` stanno nella cartella del sito, non in `C:\E3pr0m_dev`. Prima di committare controllare `git status` lì: dovrebbero risultare modificati tema (`style.css`, `functions.php`, `header.php`, `footer.php`, `index.php`, `inc/template-functions.php`, `assets/css/main.css`), nuovi i template plugin, `assets/fonts/`, `assets/img/sfondo-codice.svg` e il plugin `e3pr0m-plugins`. Non committare nulla di `Brand/`.
