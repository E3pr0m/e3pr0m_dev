# BccPilot 1.1.0: pagina nuova, mail di prova e prove di aspetto (07-08/11/2026)

Nota di lavoro per riprendere. Stato a fine serata dell'08/11: **niente è ancora stato committato**; LogPilot 1.0.0 è stato inviato in revisione a WordPress.org.

## Stato del codice di BccPilot (non committato)
File toccati: `Plugin/bccpilot/bccpilot.php`, `languages/bccpilot.pot` (45 voci), `readme.txt` (riga nelle funzioni, frase nella Privacy, FAQ "The test email does not arrive"), `CLAUDE.md` (sezione BccPilot: pagina e mail di prova), nuova cartella `Plugin/bccpilot/assets/` con `icon.svg` (copia di `Brand/bccpilot/icona/icon.svg`).

Cosa c'è nel plugin **adesso** (copiato anche sul sito Local):
- #56: testata grigia con icona e "Bcc"+"Pilot" (verde), due schede ("Who gets the copy?", "Which emails do you want copied?"), stato Active / Not active (testo e icona), caselle spuntate verdi (`#15803d`, spunta bianca, focus verde), CSS in linea solo su `settings_page_bccpilot`, classe `e3p-bccpilot-wrap`, messaggio d'errore "That email address does not look valid, so it was not changed."
- #6: terza scheda "Check that it works" con modulo separato, `admin_post_e3p_bccpilot_test_email`, nonce + `manage_options`, `wp_mail()` diretto all'indirizzo **salvato**, esiti `sent|failed|noaddress` nell'indirizzo (`e3p_bccpilot_test`). Provato con Mailpit: funziona.
- **Stato "Active"**: attivo solo con indirizzo valido E almeno una email spuntata (`e3p_bccpilot_get_state()`). Confermato dall'utente.
- Versione e Changelog NON toccati (1.0.0 nel file e nel readme).

## Decisioni
- Versione: l'utente è propenso alla **1.1.0** (UX + mail di prova); da confermare. Poi: #15 (Plugin URI e Donate link) diventerebbe 1.1.1; #5 (filtro destinatari) 1.2.0 (correggere il numero nel testo dell'issue, dice 1.1.0).
- **#4 chiusa come "non pianificata"** (costante in wp-config scartata). Nuova **#58** "Registro delle modifiche alle impostazioni" (Project Plugin | BccPilot, assegnata): chi/quando/cosa, indirizzi mascherati, ultime 10-20 voci, nuova opzione del database (da dichiarare in CLAUDE.md e togliere in `uninstall.php`), scheda "Recent changes", versione dopo la 1.1.0. Il testo non nomina "amministratore": chi riceve le copie può essere chiunque.
- #56 e #6 si chiudono dopo la pubblicazione della 1.1.0, con commento mostrato prima.
- #2 (traduzioni) dopo la pubblicazione: il `.pot` ha 45 voci, più di quelle citate nell'issue.

## Flusso di rilascio (concordato)
- Git = sviluppo (branch solo `main`, nessun branch per versione); SVN = distribuzione (`trunk/`, `tags/X.Y.Z`, `assets/`).
- **Gli aggiornamenti di un plugin già pubblicato (BccPilot) NON passano da una nuova revisione**: si fa `svn ci` e vanno online. La revisione c'è solo al primo invio (LogPilot).
- Prima di `svn ci`: versione, `Stable tag`, Changelog e `.pot` allineati, test, Plugin Check sullo zip, `svn status` solo con i file del plugin.
- Dopo `svn ci` riuscito: tag di git sul commit il cui contenuto del plugin è identico a `trunk` (si verifica con `diff -rq`). Comandi: `git tag bccpilot-1.1.0 <hash>` e `git push origin bccpilot-1.1.0`. Il tag punta a un commit, non ai file in sospeso.
- Tag già creati e inviati: `bccpilot-1.0.0` → `e4cca7f` (identico a svn tags/1.0.0 e trunk); `logpilot-1.0.0` → `b4a3708` (identico allo zip inviato in revisione, vale anche `0177165`). Per LogPilot il tag vale "inviato in revisione".
- Versionamento semantico: correzione = terzo numero, funzione nuova = secondo, rottura = primo; un numero per rilascio, mai riusare un tag, niente suffissi.
- Per il commit usare `git add Plugin/bccpilot CLAUDE.md` (non `git add .`): nella radice c'è `sync.ffs_db` non seguito (FreeFileSync), da ignorare o mettere nel `.gitignore`.
- LogPilot: Changelog minimo ("First release.") va bene; si arricchisce con la 1.0.1 (#57).

## Prove di aspetto (aperte, da scegliere domani)
Pagine statiche in `Brand/prove/bccpilot/` (fuori da git, `1-` … `8-*.html`, più `icon.svg`):
1. verde attuale + schede bianche; 2. verde Pilot + schede bianche; 3/4. come 1/2 con schede grigie quasi bianche (`#f6f7f7`, troppo poco); 5/6. schede grigio deciso `#e5e8eb`; **7**. verde attuale + schede bianche + modifiche sotto; **8**. verde Pilot + schede grigie `#e5e8eb` + modifiche sotto.

Modifiche provate nelle pagine 7 e 8 (copia di prova del plugin, solo nella cartella temporanea della sessione, non nel repository):
- **Save dentro la seconda scheda**, in fondo, sotto una linea sottile, con icona `dashicons-saved` (in un `<button type="submit" name="submit" id="submit" class="button button-primary">`; nel plugin vero va tradotto/gestito con testo del core).
- **Anello sottile 1 px `#2BD576`** sui cerchi delle icone dei titoli (padding 9 px invece di 10 per tenere la stessa dimensione). Già previsto come "anello dell'icona" in `Brand/PAGINA-PLUGIN.md`.
- **Pulsanti** ("Send a test email" e "Save Changes"): sfondo con la stessa sfumatura della testata `radial-gradient(ellipse 80% 160% at 10% 0%, #3B4049 0%, #2B2F36 100%)`, bordo e testo `#2BD576`, hover `#3B4049`. Classe `e3p-bccpilot-testbtn` (doppia nel selettore per battere le regole verdi dei pulsanti). Problema aperto: **i due pulsanti diventano indistinguibili**; idea: Save con riempimento verde Pilot e testo scuro `#2B2F36`.
- Cose scartate: pulsante nero pieno con testo bianco (frainteso: l'utente voleva il grigio *della testata*, con testo verde); grigio `#e6e9ec`→`#a1a3a5` (testo verde `#15803d` sotto 2:1 di contrasto).

Da decidere: verde scuro o verde Pilot, schede bianche o grigie, stile dei pulsanti. Poi applicare a `bccpilot.php`, aggiornare `Brand/PAGINA-PLUGIN.md` e copiare su Local.

## Da non dimenticare
- Aggiornare `Brand/PAGINA-PLUGIN.md`: caselle spuntate verdi (sfondo, spunta bianca, focus), niente `vertical-align` sull'icona dentro `.button` (WordPress la centra da solo; la regola mia l'aveva spostata), anello dell'icona, azione di salvataggio dentro l'ultima scheda del modulo.
- Aggiornare la guida con quello che si decide su verde, schede e pulsanti; poi #56 nel Project (stile) e, per coerenza, LogPilot 1.0.1 (#57).
- Intelephense segnala 7 "Undefined constant" in `logpilot.php` (`WP_CONTENT_DIR`, `ABSPATH`, `MB_IN_BYTES`): sono costanti del core, falsi positivi; si sistemano con gli stub di WordPress per l'editor, non con modifiche al codice.

## Aggiornamento 08/11 pomeriggio: aspetto deciso e 1.1.0 preparata
Decisioni (sostituiscono le prove 1-8 sopra):
- **Un solo verde `#28CA6F`** (testo di stato, icone, bordi, caselle, anello icone, "Pilot" nella testata); hover `#22ac5e`. Applicato anche al sito Local (`themes/e3pr0m-theme/style.css`, `PALETTE.md`). Loghi, icone e `BRAND.md` restano nel `#2BD576` (da decidere se rigenerarli). Contrasto basso su chiaro (circa 2:1) accettato: lo stato dice sempre testo + icona.
- Schede grigie `#e5e8eb`; pulsanti grigio chiaro `#f6f7f7`, bordo `#8c8f94`, testo nero, icona verde; Save dentro l'ultima scheda; **caselle spuntate: sfondo e bordo verdi, spunta scura `#1d2327`** (scelta 14; la 16 con bordo nero scartata); anello 1 px verde sulle icone dei titoli.
- **Riga standard in tutti i plugin**: "by E3pr0m · Support development" in basso a destra della testata (12 px, peso 300, `3` e `0` verdi; `bottom:15px; margin:0`); il nome va sempre a `https://www.e3pr0m.com/plugin/` (generico, volutamente), "Support development" a `https://github.com/sponsors/E3pr0m` (pagina verificata dall'utente). Scartate: Donate, Buy me a coffee, "Enjoying … Support it", varianti con la frase sotto.
- Riferimento grafico: `Brand/pagina-plugin-riferimento/` (HTML + PNG, = prova 19). Guida `Brand/PAGINA-PLUGIN.md` e `CLAUDE.md` aggiornati.
- LogPilot: issue **#59** (allineare la pagina, con la riga standard), da fare con la 1.0.1 (#57) dopo la revisione.
- **BccPilot 1.1.0 preparata nel codice**: `Version` e `Stable tag` 1.1.0, Plugin URI `https://www.e3pr0m.com/plugin/bccpilot/`, `Donate link: https://github.com/sponsors/E3pr0m` nel solo `readme.txt` (nell'intestazione del plugin non esiste il campo), Changelog 1.1.0, `.pot` 49 voci. Copiata su Local. #15 chiusa dalla 1.1.0 (non più 1.1.1).
- Restano: sistemare la pagina BccPilot sul sito, test e Plugin Check sullo zip, commit (`git add Plugin/bccpilot Brand CLAUDE.md sessions`; `Brand/` è fuori da git per scelta dell'utente: verificare), `svn ci` trunk + tags/1.1.0, tag git `bccpilot-1.1.0`, commenti di chiusura #56, #6, #15 (testi da mostrare prima). Poi la #2.
