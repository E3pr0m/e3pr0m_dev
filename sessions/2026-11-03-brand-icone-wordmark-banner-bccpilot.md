# Sessione 2026-11-03: brand E3pr0m, icone, wordmark, icone dei plugin e banner di BccPilot

Continua `2026-11-03-sito-piano-sezioni-plugin-e-temi.md`: il sito è fermo finché il brand non è chiuso. Il documento di riferimento è `Brand/BRAND.md` (file di sviluppo, `Brand/` è fuori da git: queste note e i file di `Brand/` restano solo sul disco).

## Decisioni di marchio (tutte in `Brand/BRAND.md`)
- Accento verde `#2BD576`, fondo di marca grigio scuro `#2B2F36`, impostazione mista (scuro per il marchio, chiaro per la lettura).
- Caratteri: IBM Plex Sans per titoli e testi, IBM Plex Mono per il tecnico. File in `Brand/font/` (messi dall'utente, licenza OFL).
- **Icona `>e3p`**, look D (quadrato arrotondato, fondo scuro, anello verde sottile), Plex Mono 400. Contenuto per misura: `>e3p` (128+), `>e3` (48), `>3` (32), `>` (16). Freccia a 0,9 em nel master, riga al 70% del lato. Fondo uniformato a `#2B2F36`.
- **Wordmark "E3pr0m"** in Plex Sans 400 (cambiato da Plex Mono perché `r` e `m` del monospazio non convincevano), `3` e `0` verdi `#2BD576` anche su fondo chiaro (alternativa `#0B7A3B`), zero **puntato** (`zero.alt01`; alternativa barrato `alt02`, più visibile sotto i 100 px).
- Il nome del plugin non sta nell'icona: WordPress.org lo mostra accanto, va nei banner.

## Cosa è stato prodotto (in `Brand/`, tutto generato da script)
- `icona/`: SVG master `icona-master.svg`, `icona-48`, `icona-32`, `icona-16`, `icona-apple`, `icona-tonda`, `icona-tonda-piena`, `favicon.svg`/`favicon.ico`, PNG in `icona/png/` (avatar 512/1024, avatar tondo, icona sito 512, apple touch 180, favicon 16/32/48). Script: `icona/genera_icona.py`. Richiede `fontTools` (installato dall'utente con pip).
- `wordmark/`: `wordmark-su-scuro.svg` e `-su-chiaro.svg` (sfondo trasparente) più PNG 1600/800/400 in `wordmark/png/`. Script: `wordmark/genera_wordmark.py`.
- Icone dei tre plugin, in `Brand/<plugin>/icona/` (`icon.svg` 1024, `icon-256x256.png`, `icon-128x128.png`), con firma `>e3` in basso a destra (freccia più piccola della `e` e del `3`): BccPilot **3c** (busta bianca con copia tratteggiata dietro, la Bcc), Plugin Audit **PA2** (elenco con lente e spunta, lente alzata e a tratto fine; niente scudo perché il plugin non è una scansione di sicurezza), LogPilot **LG2** (righe di log con una evidenziata in verde). Script: `icona/genera_icone_plugin.py`.
- Banner BccPilot versione **B**, in `Brand/bccpilot/banner/` (`banner.svg`, `banner-772x250.png`, `banner-1544x500.png`). Scritte riprese dal kit v1 (titolo Plex Mono con "Pilot" verde, sottotitolo, riga verde delle funzioni), sfondo grigio con profondità (sfumatura, trama di codice del plugin al 6,5%, ombra sotto l'icona), quadrato dell'icona con fondo al 40%, firma con il wordmark. Script: `icona/genera_banner_bccpilot.py`.
- Cartelle create: `Brand/pluginaudit/` e `Brand/logpilot/` (con `icona/`). I file del kit v1 in `Brand/bccpilot/` non sono stati toccati.
- Prove in `Brand/prove/` (pagine HTML): `icone-plugin.html`, `bccpilot-icona.html`, `banner-bccpilot.html`, `wordmark-font.html` e le prove precedenti.

## Come rigenerare
Dalla cartella `Brand/icona/`: `python genera_icona.py`, `python genera_icone_plugin.py`, `python genera_banner_bccpilot.py`; dalla cartella `Brand/wordmark/`: `python genera_wordmark.py [glifo-zero]`. Gli SVG si rifanno da qui. I PNG li ho esportati con Chrome (pagina temporanea e server locale sulla porta 8765 che salva i file), non c'è uno script per i PNG: vanno rifatti allo stesso modo se cambiano gli SVG.

## Kit v1 (scartato; le cancellazioni le ha fatte l'utente, io non ho cancellato nulla)
- **Già eliminati dall'utente** (verificato alla fine della sessione): `Brand/avatar/`, `Brand/logo/` e i file vecchi direttamente in `Brand/bccpilot/` (icona e banner del kit v1, `bccpilot-icon-v4.zip`). Ora `Brand/bccpilot/` contiene solo `icona/` e `banner/`.
- **Ancora presenti** al momento di salvare: `Brand/social/` (immagine social del kit v1, da rifare), `Brand/templates/`, `Brand/tokens/` (palette blu del kit v1: `brand.css` e `tokens.json`), `README.md`, `estetica.md` e `e3pr0m-brand-kit-v1.zip`. Il valore nuovo di colori e font è solo in `BRAND.md`: un `brand.css` o `tokens.json` aggiornato non esiste ancora e va fatto dopo che i valori aperti sono chiusi, se serve.

## Aperto
- **Banner di Plugin Audit**: fatto il 2026-11-04 (testi proposta C, `Brand/pluginaudit/banner/`, script `icona/genera_banner_pluginaudit.py`). **Banner di LogPilot**: fatto il 2026-11-04 (`Brand/logpilot/banner/`, script `icona/genera_banner_logpilot.py`; riga verde "Color-coded levels · ..." per il riferimento a colori e livelli chiesto dall'utente, versione scelta da me, da confermare). Titolo di Plugin Audit scritto attaccato (`PluginAudit`) per volere dell'utente.
- **BccPilot è online su WordPress.org** (confermato dall'utente il 2026-11-04). Su WordPress.org oggi non ha nessuna grafica (né icone né banner). Da fare: caricare per la prima volta quelle nuove di `Brand/bccpilot/` nella cartella `assets/` dell'SVN; l'upload SVN lo fa l'utente o su sua richiesta.
- **Immagine social 1200×630**: fatta il 2026-11-04 (`Brand/social/social-1200x630.png`, script `icona/genera_social.py`, riga "Custom WordPress themes, plugins & debugging" e "Developer on WordPress.org"). Non ancora usata.
- **Palette e card**: prove in `Brand/prove/palette-pratica.html` e `card-plugin.html` (tre card plugin, A/B/C) e `card-plugin-misto.html` (misto A e C, **scelta dall'utente il 2026-11-04**, descritta in `BRAND.md`). **Palette mista per fasce confermata dall'utente il 2026-11-04** (tutto scuro scartato, `palette-pratica-scuro.html`): valori definitivi in `BRAND.md`, sezione "Palette del sito". Il sito si costruirà a mano nel tema, usando la skill `frontend-design`. Il sito attuale usa verde neon `#00ff99`, JetBrains Mono, Inter: da sostituire; da tenere gli elementi dinamici (typing nel titolo, ombra header allo scroll, hover su card/bottoni/filtri).
- Immagini del sito (card 600×400, testata 1200×600) e icona del menu in amministrazione: non fatte.
- Ancora da decidere nel brand: grigi delle superfici oltre allo sfondo del banner, grigio chiaro dei fondi di lettura, verde scuro per i testi su chiaro, tono e frasi ("tu" o "lei"), cosa cerca ciascun pubblico.
- Banner di BccPilot su WordPress.org: online dal 2026-11-04 (r3784232), confermato dall'utente che è nitido; issue #1 chiusa. Restano da guardare solo i banner di PluginAudit e LogPilot alla loro pubblicazione (peso ~720 KB, eventuale sovrapposizione del titolo).
- Avatar WordPress.org: serve Gravatar (profilo pubblico legato all'email); l'utente ha lasciato perdere. Il file consigliato era `icona/png/avatar-tondo-pieno-1024.png`.
- CLAUDE.md: non c'è ancora un rimando a `Brand/BRAND.md` (offerto, non fatto).
- Sito (`e3pr0m-plugins`, template del tema, contenuti, cambio di `Plugin URI`): fermo, come nelle note precedenti.

## Nulla da committare
`Brand/` è escluso da git. In git ci sono solo le note in `sessions/` (non tracciate). Nessun commit fatto.
