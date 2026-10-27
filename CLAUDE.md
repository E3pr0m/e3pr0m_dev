# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Panoramica

`E3pr0m_dev` raccoglie i progetti di sviluppo di E3pr0m su WordPress:

- **Plugin** a marchio E3pr0m, pensati per la directory di WordPress.org. Ogni plugin ha una versione gratuita e, se serve, una versione Pro come plugin separato.
- **Temi custom**.
- **Debug su WordPress**: analisi e correzione di problemi su siti e codice WordPress.

Le regole di questo file valgono per tutto il repository; quelle specifiche di un singolo progetto stanno nel `CLAUDE.md` della sua cartella. Le sezioni "Struttura di un plugin", "Convenzioni di nomenclatura", "Traduzioni" e "Vincoli" riguardano i plugin: per temi custom e debug le convenzioni si definiscono quando nasce il primo progetto e si aggiungono qui.

Plugin presenti:

| Plugin | Cartella | Cosa fa |
|---|---|---|
| BccPilot | `Plugin/bccpilot/` | Aggiunge un destinatario `Bcc:` nascosto alle mail di sistema che WordPress invia all'amministratore. Dettagli in `Plugin/bccpilot/CLAUDE.md`. |

Lingua: commenti nel codice e risposte in italiano. Testi dell'interfaccia (`__()`) e `readme.txt` in inglese, come richiede la directory.

Non ci sono build, lint o test. Per provare una modifica, copiare la cartella del plugin in `wp-content/plugins/` di un sito WordPress e attivarla.

## Struttura di un plugin

Ogni plugin sta in `Plugin/<nome>/` e contiene almeno il file principale `<nome>.php`, `uninstall.php`, `readme.txt` (documentazione per l'utente, in inglese) e `languages/`. Requisiti minimi di partenza: PHP 7.4+, WP 5.2+, senza dipendenze; un plugin può alzarli se serve, dichiarandolo nell'intestazione e nel `readme.txt`.

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
- Ogni dato in uscita va escapato e ogni dato in ingresso sanificato. Le scritture sul database sono ridotte al minimo, documentate nel `CLAUDE.md` del plugin e rimosse da `uninstall.php`.
- Se cambia il comportamento o la versione, aggiornare insieme intestazione del plugin e `readme.txt` (Version e Changelog).
- Licenza GPLv2 or later (richiesta dalla directory di WordPress.org): intestazione e `readme.txt` devono riportare `License` e `License URI`. Non aggiungere codice di terzi con licenza incompatibile con la GPL.
