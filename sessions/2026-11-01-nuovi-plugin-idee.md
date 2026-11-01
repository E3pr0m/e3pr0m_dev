# Sessione 2026-11-01: BccPilot chiuso, idee per due nuovi plugin

## Contesto
BccPilot 1.0.0 è approvato e pubblicato in SVN (r3773355). Issue #3 aggiornata e chiusa. Creata la #15 (release 1.0.1: Plugin URI e Donate link). Da qui si lavora sull'idea di due nuovi plugin; ne è stato descritto solo uno.

## Nome scelto: Plugin Audit (2026-11-01)
- Nome visibile **Plugin Audit**, slug `pluginaudit` (libero al 2026-11-01: nessun plugin pubblicato; non vede slug riservati né il controllo di somiglianza del team). Cartella e file principale `pluginaudit` / `pluginaudit.php`, Text Domain `pluginaudit`, prefisso codice `e3p_pluginaudit_`, costanti `E3P_PLUGINAUDIT_`. Come per BccPilot: nessuna regola nuova da aggiungere in CLAUDE.md.
- Direzione: **audit dello stato dei plugin** (salute dell'installazione), non audit di sicurezza del codice. Il nome deve essere vero il giorno del rilascio: se la 1.0 ha meno, il `readme.txt` definisce cosa intende per audit.
- Perimetro previsto: vulnerabilità note (gravità, categoria, descrizione), indizi d'uso, aggiornamenti disponibili (dato locale di WordPress), compatibilità PHP/WP dalle intestazioni (locale), plugin disattivati ma installati; poi plugin non aggiornati da anni o chiusi (richiede chiamata a wordpress.org, consenso e documentazione).
- Il `readme.txt` dice anche cosa NON fa: non scansiona il codice, non cerca malware, non sostituisce un plugin di sicurezza.
- Rischio: nome generico, il team può chiedere un tratto distintivo.

## Plugin 1: decisioni (le precedenti, ora riferite a Plugin Audit)
- Scopo: mostra chiaramente i plugin del sito, attivi e non, con gli avvisi di vulnerabilità; evidenzia i plugin attivi ma probabilmente inutilizzati.
- Versione gratuita e Pro, come plugin separati, con filtri di estensione fin dall'inizio (come BccPilot).
- Avvisi di vulnerabilità divisi per **gravità** (colori) e per **categoria** (etichetta di testo), con una **breve descrizione accanto alla categoria** perché non tutti sanno cosa significa (es. XSS, CSRF). Il colore non è l'unico segnale: sempre anche testo (accessibilità). Dipende dai campi che la fonte dati fornisce.
- Indizi d'uso già nella 1.0. Etichetta "nessun segno d'uso trovato", mai "inutilizzato". Segnali: shortcode, blocchi, widget, tipi di post, opzioni, cron. Nessun modo certo per saperlo.
- Integrazione con BccPilot (idea 8): gli avvisi di vulnerabilità possono arrivare in Bcc. Il nuovo plugin aggiunge la sua voce tramite i filtri `e3p_bccpilot_notifications` e `e3p_bccpilot_notification_texts`; BccPilot non cambia per questa parte. Da verificare l'ordine di caricamento dei filtri.
- Rimando in BccPilot, **variante A**: il checkbox "Notifiche (nome plugin)" compare solo con il nuovo plugin attivo (lo aggiunge lui); se manca, BccPilot mostra una riga di testo senza checkbox, con link alla pagina del plugin su wordpress.org. La riga richiede una piccola release di BccPilot, da fare **dopo** la pubblicazione del nuovo plugin, con issue a parte. Niente checkbox disattivato (rischio "funzione bloccata", Guideline 5).

## Proposta Free / Pro (non decisa)
- Free: elenco completo, controllo manuale delle vulnerabilità, filtro e ordine per tipo, pochi indizi d'uso.
- Pro (nucleo): scansione programmata con avvisi, storico, gestione degli avvisi (ignora con nota, scadenze), integrazione con BccPilot. Altre idee: analisi d'uso più profonda, multisite, canali esterni.
- Report esportabili: non deciso.

## Fonti dati sulle vulnerabilità (verificate il 2026-11-01)
- **WPScan**: gratuito (25 chiamate al giorno) solo per uso non commerciale; l'uso commerciale richiede licenza a pagamento con preventivo. Esclusa con una Pro.
- **Patchstack**: API a pagamento (standard, limite 5.000 chiamate/24 h non verificato sulla documentazione ufficiale; esteso su richiesta). Termini d'uso da leggere.
- **Wordfence Intelligence v3**: gratuita, anche per uso commerciale; chiave API gratuita legata a un account wordfence.com, non condivisibile (ogni utente avrebbe la sua).
  - Prova reale con lo scanner feed (`/api/intelligence/v3/vulnerabilities/scanner`): HTTP ok, 82 MB di JSON, circa 1,9 s di download. Voci con `id`, `title`, `published`, `informational`, `software` (`type`, `name`, `slug`, `affected_versions` con `from_version`/`to_version` e relativi `_inclusive`, `patched`, `patched_versions`), `references`, `copyrights`. **Nessun `cvss` né `cwe`**: la categoria compare solo nel titolo.
  - Licenza del dato (in ogni voce): copyright Defiant, concessione perpetua, gratuita, anche sublicenza e distribuzione, **a condizione di includere un link al record e riprodurre copyright e licenza**. Le regole sulla chiave API restano separate.
  - Il production feed (indirizzo dedotto: `.../vulnerabilities/production`) ha risposto **429** dopo due richieste; è arrivata una mail di Wordfence che avvisa di possibile sospensione della chiave. I limiti non sono documentati. Non rifare prove prima di 24 ore; dopo, una sola richiesta salvando le intestazioni.
  - Implicazioni di progetto: scaricare al massimo una volta al giorno, conservare, gestire il 429 senza errori visibili; 82 MB in PHP con `json_decode` sono un problema di memoria (inferenza, da provare) e servirebbe un parser a flusso o altra riduzione.
  - Mail a wfi-support@wordfence.com **inviata dall'utente il 2026-11-01** (uso in plugin gratuito e Pro a pagamento, limiti, endpoint e campi del production feed, frequenza di aggiornamento). In attesa di risposta.

## Aperto
- Fonte dei dati: scelta aperta, in attesa della risposta di Wordfence e della prova sul production feed (campi di gravità e categoria).
- Descrizione testuale accanto alla categoria: le categorie vanno ricavate dal titolo se il feed non le dà.
- Con un'API serve il consenso esplicito (Guideline 7); con feed scaricato e confronto locale non escono dati del sito.
- Secondo plugin: descritto, vedi `2026-11-01-logpilot-idee.md`.

## Stato a fine sessione (Plugin Audit)
- Creati: Project n. 10 `Plugin | Plugin Audit`, label `pluginaudit` e `pluginaudit_pro`, issue Pro #16-#25 e gratuite #26-#32 (numerazione intercalata male, scelta A: si tiene), scheletro `Plugin/pluginaudit/` (con `VINCOLI.md`), sezione "Plugin Audit" in `CLAUDE.md`.
- Nome e slug scelti: Plugin Audit / `pluginaudit`.
- Prossimi passi: attendere la risposta di Wordfence; una sola richiesta al production feed non prima del 2026-11-02, con intestazioni salvate (`-D`) e chiave rimossa dopo; decidere la fonte, la gestione dei 82 MB e come ricavare la gravità senza CVSS; poi le funzioni della gratuita.
