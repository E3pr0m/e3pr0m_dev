<?php
/**
 * Plugin Name:       LogBeacon
 * Description:       Read the PHP and WordPress error logs, see which errors matter, and turn debug logging on and off from a panel, without editing files.
 * Version:           1.0.0
 * Author:            E3pr0m
 * Author URI:        https://www.e3pr0m.com
 * Requires at least: 5.3
 * Requires PHP:      7.4
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       logbeacon
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'E3P_LOGBEACON_VERSION', '1.0.0' );
define( 'E3P_LOGBEACON_DIR', plugin_dir_path( __FILE__ ) );

require_once E3P_LOGBEACON_DIR . 'includes/config-editor.php';
require_once E3P_LOGBEACON_DIR . 'includes/log-location.php';
require_once E3P_LOGBEACON_DIR . 'includes/download.php';
require_once E3P_LOGBEACON_DIR . 'includes/email-excerpt.php';
require_once E3P_LOGBEACON_DIR . 'includes/auto-off.php';
require_once E3P_LOGBEACON_DIR . 'includes/debug-switch.php';

// Alla disattivazione si tolgono gli eventi pianificati (spegnimento automatico e promemoria).
register_deactivation_hook( __FILE__, 'e3p_logbeacon_clear_schedule' );

// Sotto questa soglia (64 KB) la memoria libera è troppo poca per leggere il log in sicurezza.
define( 'E3P_LOGBEACON_MIN_READ_BYTES', 64 * 1024 );

// Misurato: analizzare 2 MB di log usa circa 40 MB di picco (fattore 20). Si aggiunge un margine del 25%.
define( 'E3P_LOGBEACON_MEMORY_FACTOR', 25 );

/**
 * Elenco dei livelli riconosciuti, in ordine di gravità (serve anche per il riassunto).
 * Solo le chiavi: i testi stanno in e3p_logbeacon_get_level_texts(), perché le traduzioni
 * non si possono chiamare prima di `init`.
 *
 * @return string[]
 */
function e3p_logbeacon_get_levels() {
    return array( 'fatal', 'parse', 'database', 'warning', 'notice', 'deprecated', 'unclassified' );
}

/**
 * Testi per l'interfaccia di ogni livello: etichetta, frase semplice, icona (dashicon).
 * La gravità si mostra sempre con testo e icona, mai solo con il colore.
 * Le frasi sono nostre e non promettono nulla sullo stato del sito.
 *
 * @return array<string, array{label: string, help: string, icon: string}>
 */
function e3p_logbeacon_get_level_texts() {
    return array(
        'fatal'        => array(
            'label' => __( 'Fatal error', 'logbeacon' ),
            'help'  => __( 'PHP stopped running a request, so the page or feature that was running may have failed.', 'logbeacon' ),
            'icon'  => 'dismiss',
        ),
        'parse'        => array(
            'label' => __( 'Parse error', 'logbeacon' ),
            'help'  => __( 'PHP could not read a file because of a syntax mistake in its code.', 'logbeacon' ),
            'icon'  => 'dismiss',
        ),
        'database'     => array(
            'label' => __( 'Database error', 'logbeacon' ),
            'help'  => __( 'A database query failed or reported a problem.', 'logbeacon' ),
            'icon'  => 'dismiss',
        ),
        'warning'      => array(
            'label' => __( 'Warning', 'logbeacon' ),
            'help'  => __( 'Something went wrong, but PHP kept running. It may or may not affect the site.', 'logbeacon' ),
            'icon'  => 'warning',
        ),
        'notice'       => array(
            'label' => __( 'Notice', 'logbeacon' ),
            'help'  => __( 'A minor issue in the code, normally harmless on its own.', 'logbeacon' ),
            'icon'  => 'info',
        ),
        'deprecated'   => array(
            'label' => __( 'Deprecated', 'logbeacon' ),
            'help'  => __( 'The code uses something that is planned to stop working in a future version.', 'logbeacon' ),
            'icon'  => 'info',
        ),
        'unclassified' => array(
            'label' => __( 'Unclassified', 'logbeacon' ),
            'help'  => __( 'LogBeacon did not recognize the type of this entry.', 'logbeacon' ),
            'icon'  => 'editor-help',
        ),
    );
}

/**
 * Gruppi di livelli mostrati nelle schede in alto, pensati per chi non conosce i log.
 * `tone` è il livello di cui il gruppo riprende i colori. Le frasi non dicono nulla sullo stato del sito.
 *
 * @return array<string, array{label: string, help: string, icon: string, tone: string, levels: string[]}>
 */
function e3p_logbeacon_get_groups() {
    return array(
        'serious'  => array(
            'label'  => __( 'Serious problems', 'logbeacon' ),
            'help'   => __( 'Something failed or stopped running. Look at these first.', 'logbeacon' ),
            'icon'   => 'dismiss',
            'tone'   => 'fatal',
            'levels' => array( 'fatal', 'parse', 'database' ),
        ),
        'warnings' => array(
            'label'  => __( 'Warnings', 'logbeacon' ),
            'help'   => __( 'Something went wrong, but the site kept running.', 'logbeacon' ),
            'icon'   => 'warning',
            'tone'   => 'warning',
            'levels' => array( 'warning' ),
        ),
        'minor'    => array(
            'label'  => __( 'Minor notes', 'logbeacon' ),
            'help'   => __( 'Small remarks, things that will change in future versions, and entries LogBeacon could not sort.', 'logbeacon' ),
            'icon'   => 'info',
            'tone'   => 'notice',
            'levels' => array( 'notice', 'deprecated', 'unclassified' ),
        ),
    );
}

/**
 * Se PHP registra i suoi errori (`log_errors`), letto dalla configurazione di php.ini: WordPress, con WP_DEBUG_LOG acceso,
 * lo imposta a runtime, quindi si guarda prima il valore originale (get_cfg_var).
 *
 * @return bool
 */
function e3p_logbeacon_php_logging_on() {
    $value = get_cfg_var( 'log_errors' );
    if ( false === $value ) {
        $value = ini_get( 'log_errors' ); // Non scritto in php.ini: vale quello di PHP (o di .user.ini).
    }
    if ( false === $value || null === $value ) {
        return true; // Non si sa: non si dice che è spento.
    }
    $on = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
    return null === $on ? true : $on;
}

/**
 * Dove PHP scrive i suoi errori, letto dalla configurazione (`error_log`).
 *
 * @return array{kind: string, path: string} `kind`: `file` (un file che si può leggere), `stream` (un flusso o un
 *         dispositivo come /dev/stderr: gli errori vanno al log del server, non c'è un file), `syslog` o `none`
 *         (nessun valore: PHP usa il log del server).
 */
function e3p_logbeacon_php_log_target() {
    $value = get_cfg_var( 'error_log' );
    if ( ! is_string( $value ) || '' === $value ) {
        $value = ini_get( 'error_log' );
    }
    $value = is_string( $value ) ? trim( $value ) : '';
    if ( '' === $value ) {
        return array(
            'kind' => 'none',
            'path' => '',
        );
    }
    $lower = strtolower( $value );
    if ( 'syslog' === $lower ) {
        return array(
            'kind' => 'syslog',
            'path' => $value,
        );
    }
    // Flussi (php://stderr) e dispositivi (/dev/stderr, /dev/null, /proc/self/fd/2, nul di Windows): non sono file che PHP possa rileggere.
    if ( 0 === strpos( $lower, 'php://' ) || 0 === strpos( $lower, '/dev/' ) || 'nul' === $lower || preg_match( '#^/proc/(?:self|\d+)/fd/#', $lower ) ) {
        return array(
            'kind' => 'stream',
            'path' => $value,
        );
    }
    return array(
        'kind' => 'file',
        'path' => $value,
    );
}

/**
 * Frase breve che dice perché il log di PHP non è disponibile (accanto al suo pulsante grigio).
 *
 * @param array $target Risultato di e3p_logbeacon_php_log_target().
 * @return string
 */
function e3p_logbeacon_php_log_reason( $target ) {
    if ( 'stream' === $target['kind'] ) {
        $text = sprintf(
            /* translators: %s: where PHP sends its errors, the value of its error_log setting, e.g. /dev/stderr */
            __( 'PHP sends its errors to %s, which is not a file (usually they end up in the server’s own log): there is no file to read.', 'logbeacon' ),
            $target['path']
        );
    } elseif ( 'syslog' === $target['kind'] ) {
        $text = __( 'PHP sends its errors to the system log (syslog), which is not a file: there is no file to read.', 'logbeacon' );
    } elseif ( 'unwritable' === $target['kind'] ) {
        $text = sprintf(
            /* translators: %s: path of the PHP error log set in php.ini */
            __( 'PHP is set to write its errors to %s, but that folder does not exist or PHP cannot write to it, so the file will never be created.', 'logbeacon' ),
            $target['path']
        );
    } elseif ( 'off' === $target['kind'] ) {
        $text = __( 'PHP error logging is turned off (log_errors), so PHP does not record its errors anywhere: there is no log to read.', 'logbeacon' );
    } else {
        $text = __( 'PHP is not set to write its errors to a file: there is no file to read.', 'logbeacon' );
    }
    return $text . ' ' . __( 'The WordPress debug.log still records the errors WordPress can see.', 'logbeacon' );
}

/**
 * Dettaglio apribile "come ottengo un log di PHP", sotto i pulsanti dei log quando quello di PHP non è un file.
 */
function e3p_logbeacon_render_php_log_help() {
    echo '<details class="e3p-logbeacon-details"><summary><span class="dashicons dashicons-editor-help" aria-hidden="true"></span>' . esc_html__( 'How can I get a PHP error log?', 'logbeacon' ) . '</summary>';
    echo '<p class="description">' . esc_html__( 'If your hosting lets you change PHP settings (in the hosting panel, in php.ini or in a .user.ini file), set these two lines, with a path that PHP can write to and that is outside your website folder. Some hosts do not allow it: ask them.', 'logbeacon' ) . '</p>';
    echo '<pre class="e3p-logbeacon-code">' . esc_html( "log_errors = On
error_log = /path/to/php-error.log" ) . '</pre></details>';
}

/**
 * Log leggibili: debug.log di WordPress ed error_log di PHP.
 * I percorsi vengono solo da costanti e ini del server, mai da dati in ingresso.
 *
 * @return array<string, array{label: string, path: string, status: string}> Chiave => etichetta, percorso
 *         e stato (`ok`, `missing` se non esiste, `unreadable` se esiste ma PHP non può leggerlo).
 */
function e3p_logbeacon_get_log_files() {
    $candidates = array();

    // Il debug.log di WordPress ha un solo nome nell'elenco. Se è quello di LogBeacon (cartella con nome non
    // indovinabile, anche a debug spento, quando WP_DEBUG_LOG non lo indica più) si chiama "LogBeacon protected
    // debug.log"; un percorso scelto dall'utente o il vecchio wp-content/debug.log restano "WordPress debug.log".
    $custom = ( defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) && '' !== WP_DEBUG_LOG ) ? wp_normalize_path( WP_DEBUG_LOG ) : '';
    $folder = '/' . e3p_logbeacon_log_folder_name() . '/';
    $own    = e3p_logbeacon_existing_log_files();
    $old    = wp_normalize_path( WP_CONTENT_DIR ) . '/debug.log';

    if ( $own ) {
        $candidates['protected'] = $own[0];
    } elseif ( '' !== $custom && false !== strpos( $custom, $folder ) ) {
        $candidates['protected'] = $custom; // Indicato da wp-config.php ma non ancora creato.
    }
    if ( '' !== $custom && false === strpos( $custom, $folder ) ) {
        $candidates['debug'] = $custom; // Percorso scelto dall'utente.
    } elseif ( file_exists( $old ) ) {
        $candidates['debug'] = $old; // Il vecchio debug.log in wp-content, finché esiste.
    }
    if ( ! isset( $candidates['protected'] ) && ! isset( $candidates['debug'] ) ) {
        // Nessun debug.log ancora: si mostra dove sarà quello di LogBeacon, come "non ancora creato".
        $candidates['protected'] = e3p_logbeacon_plan_log_location()['file'];
    }

    // error_log di PHP: l'ini può essere vuoto o puntare a syslog. Con WP_DEBUG_LOG attivo WordPress
    // lo reindirizza a debug.log con ini_set(): get_cfg_var() legge il valore originale di php.ini.
    $php_target = e3p_logbeacon_php_log_target();
    if ( 'file' === $php_target['kind'] ) {
        $candidates['php'] = $php_target['path'];
    }

    $labels = array(
        'debug'     => __( 'WordPress debug.log', 'logbeacon' ),
        'protected' => __( 'LogBeacon protected debug.log', 'logbeacon' ),
        'php'       => __( 'PHP error_log', 'logbeacon' ),
    );

    $files = array();
    $seen  = array();
    foreach ( $candidates as $key => $path ) {
        if ( ! file_exists( $path ) || ! is_file( $path ) ) {
            $status = 'missing';
            $real   = $path;
        } elseif ( ! is_readable( $path ) ) {
            $status = 'unreadable';
            $real   = $path;
        } else {
            $status = 'ok';
            $real   = realpath( $path );
            if ( false === $real ) {
                $status = 'unreadable';
                $real   = $path;
            } elseif ( isset( $seen[ $real ] ) ) {
                continue; // Stesso file indicato due volte.
            } else {
                $seen[ $real ] = true;
            }
        }
        $files[ $key ] = array(
            'label'  => $labels[ $key ],
            'path'   => $real,
            'status' => $status,
        );
    }

    // Un file configurato ma assente che PHP non potrà mai creare (log_errors spento, oppure la cartella non esiste
    // o non è scrivibile): non è "non ancora creato", è "non disponibile".
    if ( isset( $files['php'] ) && 'missing' === $files['php']['status'] ) {
        $php_dir = dirname( $files['php']['path'] );
        if ( ! e3p_logbeacon_php_logging_on() ) {
            unset( $files['php'] );
            $php_target['kind'] = 'off';
        } elseif ( ! is_dir( $php_dir ) || ! is_writable( $php_dir ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Checks whether PHP could ever create its own log file there.
            unset( $files['php'] );
            $php_target['kind'] = 'unwritable';
        }
    }
    if ( 'file' !== $php_target['kind'] ) {
        // Il log di PHP non è un file da leggere: resta nell'elenco, grigio, perché la scelta "quale log" abbia senso.
        $files['php'] = array(
            'label'  => $labels['php'],
            'path'   => '',
            'status' => 'unavailable',
            'target' => $php_target,
        );
    }

    return $files;
}

/**
 * Percorso da mostrare: relativo alla cartella di WordPress quando possibile.
 *
 * @param string $path Percorso completo.
 * @return string
 */
function e3p_logbeacon_display_path( $path ) {
    $root       = wp_normalize_path( ABSPATH );
    $normalized = wp_normalize_path( $path );
    if ( 0 === strpos( $normalized, $root ) ) {
        return substr( $normalized, strlen( $root ) );
    }
    return $path;
}

/**
 * Legge solo la coda di un file, con un limite in byte: i log possono pesare centinaia di MB.
 *
 * @param string $path      Percorso del file.
 * @param int    $max_bytes Byte massimi da leggere dalla fine.
 * @return array{content: string, truncated: bool, size: int}|false False se il file non si apre.
 */
function e3p_logbeacon_read_tail( $path, $max_bytes ) {
    $size = filesize( $path );
    if ( false === $size ) {
        return false;
    }

    $handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Reads only the end or a part of a log that can be hundreds of MB; WP_Filesystem would load it whole into memory.
    if ( false === $handle ) {
        return false;
    }

    $truncated = $size > $max_bytes;
    if ( $truncated ) {
        fseek( $handle, -$max_bytes, SEEK_END );
    }
    $content = $size > 0 ? stream_get_contents( $handle ) : '';
    fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Reads only the end or a part of a log that can be hundreds of MB; WP_Filesystem would load it whole into memory.

    if ( false === $content ) {
        return false;
    }

    // Se abbiamo tagliato a metà, la prima riga è incompleta: la scartiamo.
    if ( $truncated ) {
        $newline = strpos( $content, "\n" );
        $content = false === $newline ? '' : substr( $content, $newline + 1 );
    }

    return array(
        'content'   => $content,
        'truncated' => $truncated,
        'size'      => (int) $size,
    );
}

/**
 * Riconosce il livello dal primo rigo di una voce. Quello che non riconosce resta "unclassified".
 *
 * @param string $message Primo rigo della voce, senza data.
 * @return string Chiave del livello.
 */
function e3p_logbeacon_detect_level( $message ) {
    $message = ltrim( $message );

    if ( preg_match( '/^(?:PHP\s+)?(?:Catchable fatal error|Fatal error)\s*:/i', $message ) ) {
        return 'fatal';
    }
    if ( preg_match( '/^(?:PHP\s+)?Parse error\s*:/i', $message ) ) {
        return 'parse';
    }
    if ( preg_match( '/^WordPress database error\b/i', $message ) ) {
        return 'database';
    }
    if ( preg_match( '/^(?:PHP\s+)?Warning\s*:/i', $message ) ) {
        return 'warning';
    }
    if ( preg_match( '/^(?:PHP\s+)?Notice\s*:/i', $message ) ) {
        return 'notice';
    }
    if ( preg_match( '/^(?:PHP\s+)?Deprecated\s*:/i', $message ) ) {
        return 'deprecated';
    }

    return 'unclassified';
}

/**
 * Divide il testo del log in voci. Una voce inizia con una riga che ha la data tra parentesi quadre;
 * le righe senza data (stack trace) appartengono alla voce precedente. Se nessuna riga ha la data,
 * ogni riga è una voce.
 *
 * @param string $text Testo del log (coda).
 * @return array<int, array{time: string, level: string, text: string}> Voci in ordine di file.
 */
function e3p_logbeacon_parse_entries( $text ) {
    $lines  = preg_split( '/\r\n|\r|\n/', $text );
    $ts_re  = '/^\[((?:\d{4}-\d{2}-\d{2}[ T]|\d{1,2}-[A-Za-z]{3}-\d{4} )\d{2}:\d{2}:\d{2}[^\]]*)\]\s?(.*)$/';
    $has_ts = false;

    foreach ( $lines as $line ) {
        if ( preg_match( $ts_re, $line ) ) {
            $has_ts = true;
            break;
        }
    }

    $raw     = array();
    $current = null;

    foreach ( $lines as $line ) {
        if ( '' === trim( $line ) ) {
            continue;
        }

        if ( preg_match( $ts_re, $line, $m ) ) {
            if ( null !== $current ) {
                $raw[] = $current;
            }
            $current = array(
                'time'  => $m[1],
                'lines' => array( $m[2] ),
            );
        } elseif ( $has_ts && null !== $current ) {
            $current['lines'][] = $line; // Continuazione (stack trace).
        } elseif ( $has_ts ) {
            // Righe prima della prima data: coda di una voce tagliata. Le teniamo insieme.
            $current = array(
                'time'  => '',
                'lines' => array( $line ),
            );
        } else {
            if ( null !== $current ) {
                $raw[] = $current;
            }
            $current = array(
                'time'  => '',
                'lines' => array( $line ),
            );
        }
    }
    if ( null !== $current ) {
        $raw[] = $current;
    }

    $entries = array();
    foreach ( $raw as $item ) {
        $first = $item['lines'][0];
        // Il log è un dato non fidato: eventuali byte non UTF-8 vengono tolti, poi si escapa in uscita.
        $entries[] = array(
            'time'  => wp_check_invalid_utf8( $item['time'], true ),
            'level' => e3p_logbeacon_detect_level( $first ),
            'text'  => wp_check_invalid_utf8( implode( "\n", $item['lines'] ), true ),
        );
    }

    return $entries;
}

/**
 * Data (Y-m-d) di una riga di log, se la riga comincia con una data tra parentesi quadre.
 * Riconosce il formato di PHP (05-Nov-2026) e quello ISO (2026-11-05). La data è quella scritta nel log.
 *
 * @param string $line Riga del log.
 * @return string|null Data in formato Y-m-d, o null se la riga non ha data.
 */
function e3p_logbeacon_line_date( $line ) {
    if ( preg_match( '/^\[(\d{4})-(\d{2})-(\d{2})[ T]\d{2}:\d{2}/', $line, $m ) ) {
        return $m[1] . '-' . $m[2] . '-' . $m[3];
    }
    if ( preg_match( '/^\[(\d{1,2})-([A-Za-z]{3})-(\d{4}) \d{2}:\d{2}/', $line, $m ) ) {
        $months = array(
            'jan' => '01',
            'feb' => '02',
            'mar' => '03',
            'apr' => '04',
            'may' => '05',
            'jun' => '06',
            'jul' => '07',
            'aug' => '08',
            'sep' => '09',
            'oct' => '10',
            'nov' => '11',
            'dec' => '12',
        );
        $key    = strtolower( $m[2] );
        if ( ! isset( $months[ $key ] ) ) {
            return null;
        }
        return $m[3] . '-' . $months[ $key ] . '-' . str_pad( $m[1], 2, '0', STR_PAD_LEFT );
    }
    return null;
}

/**
 * Valida una data Y-m-d ricevuta in ingresso.
 *
 * @param mixed $value Valore grezzo (già senza slash).
 * @return string Data valida oppure stringa vuota.
 */
function e3p_logbeacon_sanitize_date( $value ) {
    if ( ! is_string( $value ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
        return '';
    }
    return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $value : '';
}

/**
 * Prima e ultima data presenti nel file, leggendo solo l'inizio e gli ultimi 64 KB.
 *
 * @param string $path Percorso del file.
 * @param int    $size Dimensione del file.
 * @return array{first: string, last: string}|null Null se il log non ha date riconoscibili.
 */
function e3p_logbeacon_get_date_bounds( $path, $size ) {
    if ( $size <= 0 ) {
        return null;
    }
    $handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Reads only the end or a part of a log that can be hundreds of MB; WP_Filesystem would load it whole into memory.
    if ( false === $handle ) {
        return null;
    }

    $first = null;
    for ( $i = 0; $i < 2000; $i++ ) {
        $line = fgets( $handle, 65537 );
        if ( false === $line ) {
            break;
        }
        $date = e3p_logbeacon_line_date( $line );
        if ( null !== $date ) {
            $first = $date;
            break;
        }
    }

    $last = null;
    fseek( $handle, max( 0, $size - 65536 ) );
    $chunk = stream_get_contents( $handle );
    fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Reads only the end or a part of a log that can be hundreds of MB; WP_Filesystem would load it whole into memory.

    if ( is_string( $chunk ) && '' !== $chunk ) {
        $lines = array_reverse( preg_split( '/\r\n|\r|\n/', $chunk ) );
        foreach ( $lines as $line ) {
            $date = e3p_logbeacon_line_date( $line );
            if ( null !== $date ) {
                $last = $date;
                break;
            }
        }
    }

    if ( null === $first || null === $last ) {
        return null;
    }

    return array(
        'first' => $first,
        'last'  => $last,
    );
}

/**
 * Offset (byte) dell'inizio della prima riga con data maggiore o uguale a quella data, o la
 * dimensione del file se non esiste. Il log è in ordine cronologico, quindi si usa una ricerca
 * a bisezione: poche letture anche su file molto grandi.
 *
 * @param resource $handle File aperto in lettura.
 * @param int      $size   Dimensione del file.
 * @param string   $date   Data Y-m-d.
 * @return int Offset in byte.
 */
function e3p_logbeacon_find_offset( $handle, $size, $date ) {
    $lo = 0;
    $hi = $size;

    while ( $hi - $lo > 8192 ) {
        $mid = (int) floor( ( $lo + $hi ) / 2 );
        fseek( $handle, $mid );
        if ( $mid > 0 ) {
            fgets( $handle, 65537 ); // Scarta la riga tagliata a metà.
        }

        $found = false;
        while ( ftell( $handle ) < $hi ) {
            $start = ftell( $handle );
            $line  = fgets( $handle, 65537 );
            if ( false === $line ) {
                break;
            }
            $line_date = e3p_logbeacon_line_date( $line );
            if ( null === $line_date ) {
                continue; // Continuazione di una voce (stack trace).
            }
            $found = true;
            if ( $line_date < $date ) {
                $lo = ftell( $handle );
            } else {
                $hi = $start;
            }
            break;
        }

        if ( ! $found ) {
            $hi = $mid;
        }
    }

    // Ultimo tratto: scansione lineare dalla zona trovata.
    fseek( $handle, $lo );
    $limit = $lo + 4 * MB_IN_BYTES;
    while ( ftell( $handle ) < $limit ) {
        $start = ftell( $handle );
        $line  = fgets( $handle, 65537 );
        if ( false === $line ) {
            break;
        }
        $line_date = e3p_logbeacon_line_date( $line );
        if ( null !== $line_date && $line_date >= $date ) {
            return (int) $start;
        }
    }

    return (int) $size;
}

/**
 * Byte da leggere per un intervallo di giorni (estremi inclusi).
 *
 * @param string $path Percorso del file.
 * @param int    $size Dimensione del file.
 * @param string $from Primo giorno, Y-m-d.
 * @param string $to   Ultimo giorno, Y-m-d.
 * @return array{start: int, end: int}|false
 */
function e3p_logbeacon_range_offsets( $path, $size, $from, $to ) {
    $handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Reads only the end or a part of a log that can be hundreds of MB; WP_Filesystem would load it whole into memory.
    if ( false === $handle ) {
        return false;
    }

    $next = date_create( $to, new DateTimeZone( 'UTC' ) );
    if ( false === $next ) {
        fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Reads only the end or a part of a log that can be hundreds of MB; WP_Filesystem would load it whole into memory.
        return false;
    }
    $next = $next->modify( '+1 day' )->format( 'Y-m-d' );

    $start = e3p_logbeacon_find_offset( $handle, $size, $from );
    $end   = e3p_logbeacon_find_offset( $handle, $size, $next );
    fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Reads only the end or a part of a log that can be hundreds of MB; WP_Filesystem would load it whole into memory.

    return array(
        'start' => $start,
        'end'   => max( $start, $end ),
    );
}

/**
 * Legge un tratto del file.
 *
 * @param string $path   Percorso del file.
 * @param int    $start  Offset di partenza.
 * @param int    $length Byte da leggere.
 * @return string|false
 */
function e3p_logbeacon_read_bytes( $path, $start, $length ) {
    if ( $length <= 0 ) {
        return '';
    }
    $handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Reads only the end or a part of a log that can be hundreds of MB; WP_Filesystem would load it whole into memory.
    if ( false === $handle ) {
        return false;
    }
    $content = stream_get_contents( $handle, $length, $start );
    fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Reads only the end or a part of a log that can be hundreds of MB; WP_Filesystem would load it whole into memory.
    return $content;
}

/**
 * Byte di log che la memoria libera di PHP permette di analizzare. Si parte dal memory_limit,
 * si tolgono la memoria già usata da WordPress e dagli altri plugin e una riserva di 8 MB per
 * costruire la pagina, poi si divide per il fattore misurato (con margine).
 *
 * @return int|null Byte consentiti, o null se il memory_limit è illimitato.
 */
function e3p_logbeacon_get_memory_cap() {
    $limit = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
    if ( $limit <= 0 ) {
        return null; // Illimitata (-1): vale il tetto normale.
    }

    $free = $limit - memory_get_usage( true ) - 8 * MB_IN_BYTES;

    return max( 0, (int) floor( $free / E3P_LOGBEACON_MEMORY_FACTOR ) );
}

/**
 * Avviso quando la memoria di PHP abbassa il tetto di lettura, con le istruzioni per aumentarla.
 *
 * @param int $max_range Tetto effettivo in byte.
 */
function e3p_logbeacon_render_memory_notice( $max_range ) {
    $limit = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );

    echo '<div class="e3p-logbeacon-problems">';
    echo '<p><span class="dashicons dashicons-warning" aria-hidden="true"></span> ';
    if ( $max_range < E3P_LOGBEACON_MIN_READ_BYTES ) {
        printf(
            /* translators: %s: PHP memory limit, e.g. "64 MB" */
            esc_html__( 'There is not enough free PHP memory (limit: %s) to read the log safely, so LogBeacon does not load it.', 'logbeacon' ),
            esc_html( size_format( $limit ) )
        );
    } else {
        printf(
            /* translators: 1: PHP memory limit, 2: log size that can be loaded, 3: usual log size limit */
            esc_html__( 'Your PHP memory limit (%1$s) leaves room for about %2$s of log at a time, less than the usual %3$s. LogBeacon lowers its limit so it does not run out of memory.', 'logbeacon' ),
            esc_html( size_format( $limit ) ),
            esc_html( size_format( $max_range ) ),
            esc_html( size_format( 2 * MB_IN_BYTES ) )
        );
    }
    echo '</p>';

    echo '<details><summary>' . esc_html__( 'How to get more memory', 'logbeacon' ) . '</summary>';
    echo '<ol>';
    echo '<li>';
    printf(
        /* translators: 1: PHP constant line, 2: file name, 3: comment text found in that file */
        esc_html__( 'In the %2$s file, in the site root, add the line %1$s above the line that says "%3$s". Take a backup of the file first.', 'logbeacon' ),
        '<code>' . esc_html( "define( 'WP_MEMORY_LIMIT', '256M' );" ) . '</code>',
        '<code>wp-config.php</code>',
        esc_html( "That's all, stop editing!" )
    );
    echo '</li>';
    echo '<li>';
    printf(
        /* translators: 1: php.ini setting, 2: file name */
        esc_html__( 'Or raise it in the PHP settings: many hosting control panels have a "PHP settings" or "PHP version" page with a memory limit field. On your own server, set %1$s in %2$s.', 'logbeacon' ),
        '<code>memory_limit = 256M</code>',
        '<code>php.ini</code>'
    );
    echo '</li>';
    echo '<li>' . esc_html__( 'If neither works, your hosting plan probably caps the memory. Ask your hosting provider\'s support to raise the PHP "memory_limit" to at least 256M, or ask the person who manages the site.', 'logbeacon' ) . '</li>';
    echo '</ol>';
    echo '<p class="description">' . esc_html__( 'Changing WP_MEMORY_LIMIT cannot exceed the cap set by the host. Reload this page afterwards to see the new limit.', 'logbeacon' ) . '</p>';
    echo '</details>';
    echo '</div>';
}

/**
 * Capacità richiesta per usare il plugin. Su un multisito `wp-config.php` e i log sono comuni a tutta la rete,
 * quindi la pagina e le azioni sono solo per il super amministratore; altrimenti basta `manage_options`.
 *
 * @return string
 */
function e3p_logbeacon_capability() {
    return is_multisite() ? 'manage_network_options' : 'manage_options';
}

/**
 * Menu: Strumenti → LogBeacon.
 */
function e3p_logbeacon_add_menu() {
    add_management_page(
        __( 'LogBeacon', 'logbeacon' ),
        __( 'LogBeacon', 'logbeacon' ),
        e3p_logbeacon_capability(),
        'logbeacon',
        'e3p_logbeacon_render_page'
    );
}
add_action( 'admin_menu', 'e3p_logbeacon_add_menu' );

/**
 * Frase semplice per un messaggio di log noto, oppure stringa vuota. Si guarda solo la prima riga.
 * Le frasi dicono cosa PHP ha segnalato, senza promettere cause certe né dire se il sito sia a posto.
 *
 * @param string $text Testo della voce (prima riga).
 * @return string
 */
function e3p_logbeacon_explain( $text ) {
    $first = (string) strtok( $text, "\n" );

    $patterns = array(
        '/Allowed memory size of/i'                           => __( 'PHP ran out of memory while running something.', 'logbeacon' ),
        '/Maximum execution time/i'                           => __( 'Something took too long to run, so PHP stopped it.', 'logbeacon' ),
        '/Call to undefined (function|method)/i'              => __( 'The code tried to use a function that does not exist. This often happens when a plugin or theme is missing, disabled or out of date.', 'logbeacon' ),
        '/Class .+ not found/i'                               => __( 'The code tried to use a class that was not found. This often happens when a plugin or theme is missing, disabled or out of date.', 'logbeacon' ),
        '/Undefined (variable|index|array key|property|offset)/i' => __( 'The code used a value that was never set.', 'logbeacon' ),
        '/headers already sent|Cannot modify header information/i' => __( 'Something printed text before WordPress could send the page headers.', 'logbeacon' ),
        '/Translation loading for the .+ domain was triggered too early|_load_textdomain_just_in_time/i' => __( 'A plugin or theme loaded its translations too early.', 'logbeacon' ),
        '/Failed to open stream|No such file or directory/i'  => __( 'The code tried to open a file that is missing or cannot be read.', 'logbeacon' ),
        '/Table .+ doesn\'t exist/i'                          => __( 'The code looked for a database table that does not exist.', 'logbeacon' ),
        '/MySQL server has gone away|Lost connection to MySQL/i' => __( 'The connection to the database was lost.', 'logbeacon' ),
        '/Access denied for user/i'                           => __( 'The database refused the login details it was given.', 'logbeacon' ),
    );

    foreach ( $patterns as $pattern => $sentence ) {
        if ( preg_match( $pattern, $first ) ) {
            return $sentence;
        }
    }
    return '';
}

/**
 * Parola chiave che identifica il messaggio di una voce, per raggruppare quelle uguali: il tratto più lungo
 * della prima riga senza cifre, virgolette né `$` (che cambiano da una voce all'altra), tolti il livello iniziale
 * e il percorso finale. È un pezzo di testo vero della voce, quindi funziona anche come ricerca. Vuota se troppo corta.
 *
 * @param string $text Testo della voce.
 * @return string
 */
function e3p_logbeacon_message_term( $text ) {
    $first = (string) strtok( $text, "\n" );
    $first = preg_replace( '/^(?:PHP\s+)?(?:Fatal error|Parse error|Warning|Notice|Deprecated|Strict Standards|Catchable fatal error)\s*:\s*/i', '', $first );
    $first = preg_replace( '/\s+in\s+(?:phar:\/\/)?(?:[A-Za-z]:)?[\\\\\/].*$/s', '', $first );

    // Il primo tratto abbastanza lungo, di solito l'inizio del messaggio.
    foreach ( (array) preg_split( '/[0-9\'"`$]+/u', $first ) as $piece ) {
        $piece = trim( $piece, " \t:,;.-" );
        if ( strlen( $piece ) >= 12 ) {
            return mb_substr( $piece, 0, 80 );
        }
    }
    return '';
}

/**
 * Problemi comuni proposti come pulsanti sopra la tabella: nome semplice e parola chiave che li trova
 * nel testo della voce (la stessa che si può scrivere nel campo di ricerca).
 *
 * @return array<string, array{label: string, term: string}>
 */
function e3p_logbeacon_common_problems() {
    return array(
        'memory'       => array(
            'label' => __( 'Out of memory', 'logbeacon' ),
            'term'  => 'memory size',
        ),
        'time'         => array(
            'label' => __( 'Took too long', 'logbeacon' ),
            'term'  => 'execution time',
        ),
        'undefined'    => array(
            'label' => __( 'Something not set or missing', 'logbeacon' ),
            'term'  => 'undefined',
        ),
        'headers'      => array(
            'label' => __( 'Text printed too early', 'logbeacon' ),
            'term'  => 'header',
        ),
        'file'         => array(
            'label' => __( 'File not found', 'logbeacon' ),
            'term'  => 'no such file',
        ),
        'table'        => array(
            'label' => __( 'Missing database table', 'logbeacon' ),
            'term'  => "doesn't exist",
        ),
        'translations' => array(
            'label' => __( 'Translations loaded too early', 'logbeacon' ),
            'term'  => 'translation loading',
        ),
        'dbfailed'     => array(
            'label' => __( 'Database connection failed', 'logbeacon' ),
            'term'  => 'mysqli_real_connect',
        ),
        'member'       => array(
            'label' => __( 'Method called on an empty value', 'logbeacon' ),
            'term'  => 'member function',
        ),
        'connection'   => array(
            'label' => __( 'Database connection lost', 'logbeacon' ),
            'term'  => 'gone away',
        ),
        'login'        => array(
            'label' => __( 'Database login refused', 'logbeacon' ),
            'term'  => 'access denied',
        ),
    );
}

/**
 * Data e ora di una voce in forma leggibile, senza convertire il fuso: si mostra ciò che dice il log.
 * Se il log dichiara il fuso con una sigla (per esempio UTC), viene aggiunta.
 *
 * @param string $raw Data come scritta nel log.
 * @return string Testo non escapato; se la data non si riconosce, quella originale.
 */
function e3p_logbeacon_readable_time( $raw ) {
    if ( ! preg_match( '/^(\d{4}-\d{2}-\d{2}|\d{1,2}-[A-Za-z]{3}-\d{4})[ T](\d{2}:\d{2}:\d{2})(.*)$/', $raw, $m ) ) {
        return $raw;
    }
    $dt = date_create( $m[1] . ' ' . $m[2], new DateTimeZone( 'UTC' ) );
    if ( ! $dt ) {
        return $raw;
    }
    $text = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $dt->getTimestamp(), new DateTimeZone( 'UTC' ) );
    $tz   = trim( $m[3] );
    if ( preg_match( '/^[A-Za-z]{2,5}$/', $tz ) ) {
        $text .= ' ' . $tz;
    }
    return $text;
}

/**
 * Plugin, tema o core indicati dal percorso del file nella prima riga di una voce (#36).
 * Si legge solo il testo del log (dato non fidato): niente accessi al disco con quel testo.
 * Il percorso si riconosce dalla parte `wp-content/plugins/<slug>/` e simili, non dal percorso assoluto,
 * che può essere diverso (log copiato da un altro server, separatori di Windows). Un cammino diverso
 * dal solito (cartella contenuti rinominata) non viene riconosciuto e non si mostra nulla.
 *
 * @param string $text Testo della voce.
 * @return array{type: string, slug: string}|null Tipo (`plugin`, `mu-plugin`, `theme`, `core`) e cartella, o null.
 */
function e3p_logbeacon_find_origin( $text ) {
    $first = (string) strtok( $text, "\n" );
    $found = null;
    $best  = PHP_INT_MAX;

    if ( preg_match( '~wp-content[\\\\/](plugins|mu-plugins|themes)[\\\\/]([A-Za-z0-9_-][A-Za-z0-9._-]*)~', $first, $m, PREG_OFFSET_CAPTURE ) ) {
        $types = array(
            'plugins'    => 'plugin',
            'mu-plugins' => 'mu-plugin',
            'themes'     => 'theme',
        );
        $slug  = $m[2][0];
        if ( 'themes' !== $m[1][0] ) {
            // Plugin di un solo file: il "slug" è il nome del file senza estensione.
            $slug = preg_replace( '/\.php$/i', '', $slug );
        }
        $found = array(
            'type' => $types[ $m[1][0] ],
            'slug' => $slug,
        );
        $best  = $m[0][1];
    }

    if ( preg_match( '~[\\\\/](wp-includes|wp-admin)[\\\\/]~', $first, $c, PREG_OFFSET_CAPTURE ) && $c[0][1] < $best ) {
        $found = array(
            'type' => 'core',
            'slug' => '',
        );
    }

    return $found;
}

/**
 * Chiave di un'origine per l'indirizzo: `core` oppure `<tipo>:<cartella>`, vuota se non c'è origine.
 *
 * @param array{type: string, slug: string}|null $origin Origine.
 * @return string
 */
function e3p_logbeacon_origin_key( $origin ) {
    if ( ! $origin ) {
        return '';
    }
    return 'core' === $origin['type'] ? 'core' : $origin['type'] . ':' . $origin['slug'];
}

/**
 * Controlla e scompone una chiave d'origine arrivata dall'indirizzo (dato non fidato).
 *
 * @param string $key Chiave.
 * @return array{type: string, slug: string}|null Origine, o null se il formato non è valido.
 */
function e3p_logbeacon_parse_origin_key( $key ) {
    if ( 'core' === $key ) {
        return array(
            'type' => 'core',
            'slug' => '',
        );
    }
    if ( preg_match( '/^(plugin|mu-plugin|theme):([A-Za-z0-9_-][A-Za-z0-9._-]*)$/', $key, $m ) ) {
        return array(
            'type' => $m[1],
            'slug' => $m[2],
        );
    }
    return null;
}

/**
 * Descrizione leggibile dell'origine trovata da e3p_logbeacon_find_origin(), con il nome vero
 * del plugin o del tema se è installato (altrimenti la cartella). Testo non escapato.
 *
 * @param array{type: string, slug: string} $origin Origine.
 * @return string
 */
function e3p_logbeacon_origin_label( $origin ) {
    static $plugin_names = null;
    static $theme_names  = null;

    if ( 'core' === $origin['type'] ) {
        return __( 'WordPress itself (a core file)', 'logbeacon' );
    }

    if ( 'plugin' === $origin['type'] ) {
        if ( null === $plugin_names ) {
            $plugin_names = array();
            if ( ! function_exists( 'get_plugins' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            foreach ( get_plugins() as $file => $data ) {
                $dir = dirname( $file );
                $key = '.' === $dir ? preg_replace( '/\.php$/i', '', $file ) : $dir;
                if ( ! isset( $plugin_names[ $key ] ) && '' !== $data['Name'] ) {
                    $plugin_names[ $key ] = $data['Name'];
                }
            }
        }
        $name = isset( $plugin_names[ $origin['slug'] ] ) ? $plugin_names[ $origin['slug'] ] : $origin['slug'];
        /* translators: %s: plugin name */
        return sprintf( __( 'Plugin: %s', 'logbeacon' ), $name );
    }

    if ( 'theme' === $origin['type'] ) {
        if ( null === $theme_names ) {
            $theme_names = array();
            foreach ( wp_get_themes() as $stylesheet => $theme ) {
                $theme_names[ $stylesheet ] = $theme->get( 'Name' );
            }
        }
        $name = isset( $theme_names[ $origin['slug'] ] ) ? $theme_names[ $origin['slug'] ] : $origin['slug'];
        /* translators: %s: theme name */
        return sprintf( __( 'Theme: %s', 'logbeacon' ), $name );
    }

    /* translators: %s: must-use plugin folder or file name */
    return sprintf( __( 'Must-use plugin: %s', 'logbeacon' ), $origin['slug'] );
}

/**
 * Chiave breve che accomuna le voci "uguali": stesso livello e stessa prima riga, senza il percorso finale
 * e con i numeri sostituiti (righe, dimensioni e simili cambiano da una voce all'altra).
 *
 * @param array{level: string, text: string} $entry Voce.
 * @return string
 */
function e3p_logbeacon_group_hash( $entry ) {
    $first = (string) strtok( $entry['text'], "\n" );
    $first = preg_replace( '/\s+in\s+(?:phar:\/\/)?(?:[A-Za-z]:)?[\\\\\/].*$/s', '', $first );
    $first = preg_replace( '/\d+/', '#', (string) $first );
    $first = preg_replace( '/\s+/', ' ', trim( (string) $first ) );
    return substr( md5( $entry['level'] . '|' . $first ), 0, 12 );
}

/**
 * Toglie da un testo indirizzi web, e-mail, indirizzi IP e percorsi di file. Serve al riepilogo "prudente" per
 * l'assistenza. Non è una garanzia: chi condivide il testo deve comunque rileggerlo.
 *
 * @param string $text Testo.
 * @return string
 */
function e3p_logbeacon_scrub( $text ) {
    $text = preg_replace( '~https?://\S+~i', '[web address]', $text );
    $text = preg_replace( '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', '[email]', (string) $text );
    // Percorsi di Windows: possono contenere spazi (per esempio "Local Sites"), quindi si prende fino all'estensione.
    $text = preg_replace( '/(?:phar:\/\/)?[A-Za-z]:[\\\\\/][^\r\n\'"<>|*?]*?\.[A-Za-z0-9]{1,5}\b/', '[path]', (string) $text );
    $text = preg_replace( '/(?:phar:\/\/)?(?:[A-Za-z]:)?[\\\\\/](?:[^\s\'"<>|:*?]+[\\\\\/])+[^\s\'"<>|:*?]*/', '[path]', (string) $text );
    $text = preg_replace( '/\b\d{1,3}(?:\.\d{1,3}){3}\b/', '[IP address]', (string) $text );
    return (string) $text;
}

/**
 * Testo da incollare quando si chiede aiuto. In inglese, perché lo legge chi assiste. La versione prudente
 * non contiene voci intere e passa i messaggi da e3p_logbeacon_scrub(); quella completa aggiunge le ultime voci
 * così come sono scritte nel log, che possono contenere dati personali.
 *
 * @param array $d        Dati: log, size, scope, counts, top (elenco di n/text/source) e latest (elenco di time/text).
 * @param bool  $complete True per la versione completa.
 * @return string
 */
function e3p_logbeacon_build_support_summary( $d, $complete ) {
    global $wp_version;
    $theme = wp_get_theme();

    $lines   = array();
    $lines[] = 'LogBeacon summary';
    $lines[] = 'WordPress ' . $wp_version . ' | PHP ' . PHP_VERSION . ' | Theme: ' . $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' );
    $lines[] = 'Log: ' . $d['log'] . ' (' . $d['size'] . '), ' . $d['scope'];
    $lines[] = 'Counts: ' . $d['counts'];

    if ( $d['top'] ) {
        $lines[] = '';
        $lines[] = 'Most repeated messages:';
        $i       = 1;
        foreach ( $d['top'] as $item ) {
            $lines[] = $i . '. ' . $item['n'] . ' x ' . e3p_logbeacon_scrub( $item['text'] ) . ( '' !== $item['source'] ? ' (' . $item['source'] . ')' : '' );
            ++$i;
        }
    }

    if ( $complete && $d['latest'] ) {
        $lines[] = '';
        $lines[] = 'Latest entries, as written in the log:';
        foreach ( $d['latest'] as $item ) {
            $lines[] = '- [' . $item['time'] . '] ' . mb_substr( $item['text'], 0, 600 );
        }
    }

    return implode( "\n", $lines );
}

/**
 * "5 minutes ago" per una voce, solo se il log dichiara il fuso come UTC (o +00:00): altrimenti il confronto
 * con l'ora attuale sarebbe sbagliato e non si mostra nulla.
 *
 * @param string $raw Data come scritta nel log.
 * @return string Testo non escapato, o stringa vuota.
 */
function e3p_logbeacon_relative_time( $raw ) {
    if ( ! preg_match( '/^(\d{4}-\d{2}-\d{2}|\d{1,2}-[A-Za-z]{3}-\d{4})[ T](\d{2}:\d{2}:\d{2})(?:\.\d+)?\s*(.*)$/', $raw, $m ) ) {
        return '';
    }
    if ( ! preg_match( '/^(?:UTC|GMT|Z|[+-]00:?00)$/i', trim( $m[3] ) ) ) {
        return '';
    }
    $dt = date_create( $m[1] . ' ' . $m[2], new DateTimeZone( 'UTC' ) );
    if ( ! $dt || $dt->getTimestamp() > time() ) {
        return '';
    }
    /* translators: %s: time elapsed, e.g. "5 minutes" */
    return sprintf( __( '%s ago', 'logbeacon' ), human_time_diff( $dt->getTimestamp(), time() ) );
}

/**
 * Riga con il peso di ciò che si sta per mostrare e il massimo caricabile, uguale per tutte le scelte
 * di periodo (scorciatoie e date), così l'utente vede quanto pesa ciò che ha scelto.
 *
 * @param int $bytes Byte che verranno letti.
 * @param int $max   Massimo caricabile in una volta.
 */
function e3p_logbeacon_render_weight( $bytes, $max ) {
    $too_big = $bytes > $max;

    echo '<p class="e3p-logbeacon-weight' . ( $too_big ? ' e3p-logbeacon-level-warning' : '' ) . '">';
    echo '<span class="dashicons dashicons-' . ( $too_big ? 'warning' : 'yes' ) . '" aria-hidden="true"></span> ';
    if ( $too_big ) {
        printf(
            /* translators: 1: size of the chosen period, 2: most LogBeacon loads at once */
            esc_html__( 'This period is too big to show (about %1$s). The most LogBeacon loads at once is %2$s. Choose fewer days.', 'logbeacon' ),
            esc_html( size_format( $bytes ) ),
            esc_html( size_format( $max ) )
        );
    } else {
        printf(
            /* translators: 1: size of the chosen period, 2: most LogBeacon loads at once */
            esc_html__( 'This period is small enough to show (about %1$s). The most LogBeacon loads at once is %2$s.', 'logbeacon' ),
            esc_html( size_format( $bytes ) ),
            esc_html( size_format( $max ) )
        );
    }
    echo '</p>';
}

/**
 * Stili della pagina, solo nella pagina di LogBeacon.
 *
 * @param string $hook_suffix Pagina admin corrente.
 */
function e3p_logbeacon_enqueue_styles( $hook_suffix ) {
    if ( 'tools_page_logbeacon' !== $hook_suffix ) {
        return;
    }

    $css = '
.e3p-logbeacon-summary{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0;padding:0;list-style:none}
.e3p-logbeacon-level-fatal,.e3p-logbeacon-level-parse,.e3p-logbeacon-level-database{--e3p-line:#b32d2e;--e3p-text:#8a1f20;--e3p-tint:#fcf0f1}
.e3p-logbeacon-level-warning{--e3p-line:#b26200;--e3p-text:#6b4300;--e3p-tint:#fcf3e0}
.e3p-logbeacon-level-notice,.e3p-logbeacon-level-deprecated{--e3p-line:#2271b1;--e3p-text:#135e96;--e3p-tint:#eef4fa}
.e3p-logbeacon-level-unclassified{--e3p-line:#787c82;--e3p-text:#3c434a;--e3p-tint:#f0f0f1}
.e3p-logbeacon-summary{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0;padding:0;list-style:none}
.e3p-logbeacon-summary li{display:flex;margin:0;background:var(--e3p-tint);border:1px solid #c3c4c7;border-left:4px solid var(--e3p-line);max-width:340px}
.e3p-logbeacon-summary li.is-current{box-shadow:0 0 0 2px var(--e3p-line)}
.e3p-logbeacon-card{display:block;flex:1 1 auto;padding:8px 12px;color:inherit;text-decoration:none}
.e3p-logbeacon-summary a.e3p-logbeacon-card:focus,.e3p-logbeacon-summary a.e3p-logbeacon-card:focus-visible{outline:none;box-shadow:inset 0 0 0 2px #15803d}
.e3p-logbeacon-summary li.is-empty{opacity:.65}
.e3p-logbeacon-summary a.e3p-logbeacon-card:hover .e3p-logbeacon-name,.e3p-logbeacon-summary a.e3p-logbeacon-card:focus .e3p-logbeacon-name{text-decoration:underline}
.e3p-logbeacon-summary strong,.e3p-logbeacon-badge{display:inline-flex;align-items:center;gap:4px;font-weight:600;color:var(--e3p-text)}
.e3p-logbeacon-summary .dashicons,.e3p-logbeacon-badge .dashicons{color:var(--e3p-line)}
.e3p-logbeacon-summary .description{display:block;margin-top:4px;color:#1d2327}
.e3p-logbeacon-problems{margin:12px 0;padding:8px 12px;list-style:none;background:#fcf3e0;border:1px solid #dcc9a0;border-left:4px solid #b26200;color:#1d2327}
.e3p-logbeacon-problems li{margin:4px 0}
.e3p-logbeacon-problem-line{display:inline-block;margin-top:4px;word-break:break-all}
.e3p-logbeacon-why{display:flex;align-items:flex-start;gap:6px;margin:8px 0 0;color:#50575e}
.e3p-logbeacon-why .dashicons{flex:none;width:18px;height:18px;font-size:18px;color:#b26200}
.e3p-logbeacon-problems ol{list-style:decimal;margin:8px 0 8px 20px}
.e3p-logbeacon-problems summary{cursor:pointer;font-weight:600}
.e3p-logbeacon-problems .dashicons{color:#b26200}
.e3p-logbeacon-box{margin:12px 0;padding:4px 16px 8px;background:#fff;border:1px solid #c3c4c7}
.e3p-logbeacon-box h2{margin-top:8px}
.e3p-logbeacon-friendly{padding:20px 24px 16px;border-radius:8px;border-left:4px solid #2BD576;background:#fff}
.e3p-logbeacon-friendly.is-on{border-left-color:#b26200;background:#fffaf0}
.e3p-logbeacon-head{display:flex;align-items:center;gap:14px;margin-bottom:12px}
.e3p-logbeacon-head-icon{flex:none;box-sizing:content-box;width:28px;height:28px;padding:10px;border-radius:50%;background:#eaf7ef;color:#15803d;font-size:28px;line-height:28px;text-align:center}
.e3p-logbeacon-friendly h2,.e3p-logbeacon-head h2{margin:0;font-size:1.4em}
.e3p-logbeacon-subtitle{margin:2px 0 0;color:#50575e;font-size:14px}
.e3p-logbeacon-status{display:inline-flex;align-items:center;gap:4px;padding:3px 12px 3px 8px;border-radius:999px;background:#eaf7ef;color:#0f6630;font-weight:600}
.e3p-logbeacon-friendly.is-on .e3p-logbeacon-status{background:#fcf3e0;color:#6b4300}
.e3p-logbeacon-friendly .button-primary{padding:2px 20px;font-size:14px}
.e3p-logbeacon-status .dashicons{color:inherit}
.e3p-logbeacon-types{display:flex;flex-wrap:wrap;gap:6px 20px;margin:8px 0;padding:0;list-style:none}
.e3p-logbeacon-types li{margin:0}
.e3p-logbeacon-types li.is-empty{opacity:.6}
.e3p-logbeacon-types .dashicons{color:var(--e3p-line)}
.e3p-logbeacon-help-list{list-style:disc;margin:4px 0 8px 20px}
.e3p-logbeacon-details{margin:8px 0}
.e3p-logbeacon-details summary{cursor:pointer;color:#15803d}
.e3p-logbeacon-code{display:block;margin:8px 0;padding:8px 12px;background:#f6f7f7;border:1px solid #dcdcde;font-size:12px;overflow:auto;white-space:pre}
.e3p-logbeacon-location{margin:16px 0 4px;padding-top:8px;border-top:1px solid #dcdcde}
.e3p-logbeacon-location h3{display:flex;align-items:center;gap:6px;margin:8px 0}
.e3p-logbeacon-location h3 .dashicons{color:#15803d}
.e3p-logbeacon-inline-form{margin:8px 0}
.e3p-logbeacon-old{margin:12px 0;padding:8px 14px;background:#fcf3e0;border:1px solid #dcc9a0;border-left:4px solid #b26200;border-radius:6px}
.e3p-logbeacon-old>p:first-child .dashicons{color:#b26200;margin-right:4px;vertical-align:middle}
.e3p-logbeacon-schedule{margin:12px 0;padding:8px 16px 4px;background:#fff;border:1px solid #dcdcde;border-radius:6px}
.e3p-logbeacon-schedule legend{display:inline-flex;align-items:center;gap:4px;padding:0 6px;font-weight:600}
.e3p-logbeacon-schedule .dashicons,.e3p-logbeacon-schedule-status .dashicons{width:18px;height:18px;font-size:18px;color:#15803d}
.e3p-logbeacon-schedule-status{display:flex;align-items:center;gap:6px;margin:8px 0}
.e3p-logbeacon-remind[hidden],.e3p-logbeacon-auto-note[hidden]{display:none}
.e3p-logbeacon-current{display:flex;gap:12px;align-items:flex-start;margin:14px 0;padding:12px 16px;background:#eaf7ef;border:1px solid #bfe3cc;border-left:4px solid #2BD576;border-radius:6px}
.e3p-logbeacon-current>.dashicons{flex:none;width:28px;height:28px;font-size:28px;color:#15803d}
.e3p-logbeacon-current p{margin:2px 0}
.e3p-logbeacon-current-title{font-size:15px}
.e3p-logbeacon-current-note{color:#1d2327}
.e3p-logbeacon-current-path code{padding:2px 6px;background:#fff;word-break:break-all}
.e3p-logbeacon-current-meta{color:#1d2327}
.e3p-logbeacon-log-off.button{color:#646970;background:#f6f7f7;border-color:#dcdcde;cursor:default;pointer-events:none;box-shadow:none}
.e3p-logbeacon-clear{display:inline-flex;align-items:center;gap:2px;margin-left:8px;line-height:1;text-decoration:none;vertical-align:middle}
.e3p-logbeacon-details summary .dashicons{width:18px;height:18px;font-size:18px;margin-right:4px;vertical-align:text-bottom}
.button.e3p-logbeacon-iconbtn .dashicons,.button.e3p-logbeacon-copy .dashicons{margin-right:4px}
.e3p-logbeacon-clear .dashicons,.e3p-logbeacon-copy.button-link .dashicons{flex:none;width:18px;height:18px;font-size:18px;line-height:18px;margin:0}
.e3p-logbeacon-range{margin:12px 0 4px}
.e3p-logbeacon-range label{margin-left:8px}
.e3p-logbeacon-range label:first-of-type{margin-left:0}
.e3p-logbeacon-shortcuts{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin:8px 0 4px}
.e3p-logbeacon-shortcuts-title{font-weight:600;margin-right:4px}
.e3p-logbeacon-shortcuts .button.is-active{background:#15803d;border-color:#15803d;color:#fff;font-weight:600}
.e3p-logbeacon-shortcuts .button.is-active:hover,.e3p-logbeacon-shortcuts .button.is-active:focus{background:#0f6630;border-color:#0f6630;color:#fff}
.e3p-logbeacon-weight{display:flex;align-items:center;gap:4px;font-weight:600;color:#1d2327}
.e3p-logbeacon-weight.e3p-logbeacon-level-warning{color:var(--e3p-text)}
.e3p-logbeacon-weight .dashicons{color:#1d2327}
.e3p-logbeacon-weight.e3p-logbeacon-level-warning .dashicons{color:var(--e3p-line)}
.e3p-logbeacon-table td{vertical-align:top}
.e3p-logbeacon-table .e3p-logbeacon-time{white-space:nowrap}
.e3p-logbeacon-table tr[class*="e3p-logbeacon-level-"] td:first-child{border-left:4px solid var(--e3p-line)}
.e3p-logbeacon-pager{display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin:10px 0}
.e3p-logbeacon-pager-count{margin-right:8px;color:#50575e}
.e3p-logbeacon-pager .page-numbers{display:inline-block;min-width:16px;padding:3px 10px;border:1px solid #c3c4c7;border-radius:3px;background:#fff;color:#15803d;text-align:center;text-decoration:none}
.e3p-logbeacon-pager a.page-numbers:hover,.e3p-logbeacon-pager a.page-numbers:focus{border-color:#15803d;background:#f6f7f7}
.e3p-logbeacon-pager .page-numbers.current{background:#15803d;border-color:#15803d;color:#fff;font-weight:600}
.e3p-logbeacon-pager .page-numbers.dots{border-color:transparent;background:transparent;color:#50575e}
.e3p-logbeacon-explain{margin:0 0 4px;font-size:14px}
.e3p-logbeacon-table .e3p-logbeacon-origin{min-width:120px;color:#1d2327}
.e3p-logbeacon-none{color:#646970;font-size:13px}
.e3p-logbeacon-search{display:flex;flex-wrap:wrap;align-items:flex-end;gap:8px 12px;margin:16px 0 12px}
.e3p-logbeacon-chips{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin:4px 0 12px}
.e3p-logbeacon-chips-title,.e3p-logbeacon-field label{display:inline-flex;align-items:center;gap:4px;font-weight:600}
.e3p-logbeacon-chips-title{margin-right:4px}
.e3p-logbeacon-chips-title .dashicons,.e3p-logbeacon-field label .dashicons{width:18px;height:18px;font-size:18px;color:#15803d}
.e3p-logbeacon-chips .button.is-active{background:#15803d;border-color:#15803d;color:#fff;font-weight:600}
.e3p-logbeacon-chips .button.is-active:hover,.e3p-logbeacon-chips .button.is-active:focus{background:#0f6630;border-color:#0f6630;color:#fff}
.e3p-logbeacon-field{display:flex;flex-direction:column;gap:4px}
.e3p-logbeacon-field label{font-weight:600}
.e3p-logbeacon-search input[type=search]{min-width:240px}
.e3p-logbeacon-search .description{flex-basis:100%;margin:0}
.e3p-logbeacon-origin-link{text-decoration:none}
.e3p-logbeacon-origin-link:hover strong,.e3p-logbeacon-origin-link:focus strong{text-decoration:underline}
.e3p-logbeacon-origin-inline{display:none;margin:0 0 4px}
@media (max-width:1200px){.e3p-logbeacon-table .e3p-logbeacon-col-origin{display:none}.e3p-logbeacon-origin-inline{display:block}}
.e3p-logbeacon-origin .dashicons{color:#50575e;font-size:16px;width:16px;height:16px;vertical-align:text-bottom}
.e3p-logbeacon-empty{display:flex;gap:14px;align-items:flex-start;margin:20px 0;padding:20px 24px;background:#fff;border:1px solid #c3c4c7;border-left:4px solid #2BD576;border-radius:8px}
.e3p-logbeacon-empty>.dashicons{flex:none;width:32px;height:32px;font-size:32px;color:#15803d}
.e3p-logbeacon-empty h2{margin:0 0 6px}
.e3p-logbeacon-empty p{margin:4px 0}
.e3p-logbeacon-summary-line{display:flex;align-items:center;gap:6px;margin:14px 0 4px;font-size:14px}
.e3p-logbeacon-summary-line .dashicons{color:#15803d}
.e3p-logbeacon-ago{color:#646970;font-size:12px}
.e3p-logbeacon-repeats{display:flex;flex-wrap:wrap;align-items:center;gap:4px 8px;margin:0 0 6px}
.e3p-logbeacon-repeats .dashicons{width:18px;height:18px;font-size:18px;color:#15803d}
.e3p-logbeacon-groupnote{margin:8px 0}
.e3p-logbeacon-support-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;margin:8px 0}
.e3p-logbeacon-support-opt{padding:12px 16px;background:#fff;border:1px solid #c3c4c7;border-radius:8px}
.e3p-logbeacon-support-opt h3{margin:0 0 6px}
.e3p-logbeacon-support-opt textarea{width:100%;font-size:12px}
.e3p-logbeacon-warn{margin:6px 0;padding:8px 12px;background:#fcf3e0;border:1px solid #dcc9a0;border-left:4px solid #b26200;color:#1d2327}
.e3p-logbeacon-warn .dashicons{color:#b26200;margin-right:4px;vertical-align:text-bottom}
.e3p-logbeacon-entry-row{display:flex;flex-wrap:wrap;align-items:flex-start;gap:6px 16px}
.e3p-logbeacon-entry-row .e3p-logbeacon-details{flex:1 1 320px;margin:0}
.e3p-logbeacon-copy.button-link{display:inline-flex;align-items:center;gap:4px;line-height:1;text-decoration:none}
.e3p-logbeacon-entry{margin:0;white-space:pre-wrap;word-break:break-word;font-size:12px;max-height:14em;overflow:auto}
.e3p-logbeacon-wrap .button:not(.is-active):not(:disabled):not(.disabled){color:#15803d;border-color:#15803d}
.e3p-logbeacon-wrap .button:not(.is-active):not(:disabled):not(.disabled):hover,.e3p-logbeacon-wrap .button:not(.is-active):not(:disabled):not(.disabled):focus{color:#0f6630;border-color:#0f6630;background:#f3faf6}
.e3p-logbeacon-wrap .button:not(.is-active):not(:disabled):not(.disabled):focus{box-shadow:0 0 0 1px #0f6630}
.e3p-logbeacon-wrap .button.button-primary:not(:disabled):not(.disabled){background:#15803d;border-color:#15803d;color:#fff}
.e3p-logbeacon-wrap .button.button-primary:not(:disabled):not(.disabled):hover,.e3p-logbeacon-wrap .button.button-primary:not(:disabled):not(.disabled):focus{background:#0f6630;border-color:#0f6630;color:#fff}
.e3p-logbeacon-mail label .dashicons{width:18px;height:18px;font-size:18px;margin-right:2px;color:#15803d;vertical-align:text-bottom}
.e3p-logbeacon-band{margin:12px 0 16px;padding:16px 24px;border-left:4px solid #2BD576;border-radius:8px;background:radial-gradient(ellipse 80% 160% at 10% 0%,#3B4049 0%,#2B2F36 100%);color:#F1F3F5}
.e3p-logbeacon-wrap .e3p-logbeacon-band h1{display:flex;align-items:center;gap:12px;margin:0;padding:0;color:#F1F3F5;font-size:28px;font-weight:600;line-height:1.2}
.e3p-logbeacon-band img{width:48px;height:48px;flex:none}
.e3p-logbeacon-band .e3p-logbeacon-accent{color:#2BD576}
.e3p-logbeacon-tagline{margin:4px 0 0 60px;font-size:14px;color:#dfe3e8}
@media (max-width:600px){.e3p-logbeacon-tagline{margin-left:0}}
.e3p-logbeacon-step{margin:16px 0;padding:20px 24px 16px;background:#fff;border:1px solid #c3c4c7;border-left:4px solid #2BD576;border-radius:8px}
.e3p-logbeacon-step .e3p-logbeacon-empty{margin:8px 0 0;padding:0;border:0;border-radius:0}
.e3p-logbeacon-actions{margin:12px 0 0}
.e3p-logbeacon-actions-row{display:flex;flex-wrap:wrap;align-items:center;gap:8px}
.e3p-logbeacon-actions-row form{margin:0}
.e3p-logbeacon-actions .description{margin:8px 0 0}
.e3p-logbeacon-mail{margin:12px 0 0;padding:4px 16px 8px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:6px}
';

    wp_register_style( 'e3p-logbeacon-admin', false, array(), E3P_LOGBEACON_VERSION );
    wp_enqueue_style( 'e3p-logbeacon-admin' );
    wp_add_inline_style( 'e3p-logbeacon-admin', $css );

    // Pulsante "Copy" sulle voci: copia il testo tecnico negli appunti. Nessuna libreria esterna.
    $js = <<<'JS'
(function () {
    function fallback(text) {
        var area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.top = '-1000px';
        document.body.appendChild(area);
        area.select();
        var ok = false;
        try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
        document.body.removeChild(area);
        return ok;
    }
    // Spegnimento automatico: i campi del promemoria compaiono solo se si sceglie "remind me".
    var hours = document.getElementById('e3p-logbeacon-minutes');
    var remind = document.getElementById('e3p-logbeacon-remind-fields');
    var email = document.getElementById('e3p-logbeacon-email');
    var autoNote = document.getElementById('e3p-logbeacon-auto-note');
    if (hours && remind) {
        var sync = function () {
            var wanted = hours.value === '0';
            remind.hidden = !wanted;
            if (autoNote) { autoNote.hidden = wanted; }
            if (email) { email.required = wanted; }
        };
        hours.addEventListener('change', sync);
        sync();
    }
    // Invio dell'email: il pulsante dice "Sending…" e non si preme due volte (l'evento parte solo se i campi sono validi).
    var mailForm = document.querySelector('#e3p-logbeacon-mail-panel form');
    if (mailForm) {
        mailForm.addEventListener('submit', function () {
            var send = mailForm.querySelector('button[type="submit"]');
            if (!send) { return; }
            var label = document.createElement('span');
            label.textContent = mailForm.getAttribute('data-sending');
            send.textContent = '';
            send.appendChild(label);
            setTimeout(function () { send.disabled = true; }, 0);
        });
    }
    document.addEventListener('click', function (event) {
        // Pulsante "Email an excerpt": apre e chiude il modulo sotto la riga delle azioni.
        var toggle = event.target.closest ? event.target.closest('.e3p-logbeacon-mail-toggle') : null;
        if (toggle) {
            var panel = document.getElementById(toggle.getAttribute('aria-controls'));
            if (panel) {
                var open = panel.hidden;
                panel.hidden = !open;
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
            return;
        }
        var button = event.target.closest ? event.target.closest('.e3p-logbeacon-copy') : null;
        if (!button) { return; }
        var text;
        var targetId = button.getAttribute('data-target');
        if (targetId) {
            // Riepiloghi per l'assistenza: si copia il contenuto della casella di testo.
            var area = document.getElementById(targetId);
            if (!area) { return; }
            text = area.value;
        } else {
            var cell = button.closest('td');
            var pre = cell ? cell.querySelector('.e3p-logbeacon-entry') : null;
            if (!pre) { return; }
            text = pre.textContent;
        }
        function done(ok) {
            var label = button.querySelector('.e3p-logbeacon-copy-label');
            if (!label) { return; }
            label.textContent = ok ? button.getAttribute('data-done') : button.getAttribute('data-fail');
            clearTimeout(button.e3pTimer);
            button.e3pTimer = setTimeout(function () { label.textContent = button.getAttribute('data-label'); }, 2500);
        }
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function () { done(true); }, function () { done(fallback(text)); });
        } else {
            done(fallback(text));
        }
    });
})();
JS;
    wp_register_script( 'e3p-logbeacon-admin', false, array(), E3P_LOGBEACON_VERSION, true );
    wp_enqueue_script( 'e3p-logbeacon-admin' );
    wp_add_inline_script( 'e3p-logbeacon-admin', $js );
}
add_action( 'admin_enqueue_scripts', 'e3p_logbeacon_enqueue_styles' );

/**
 * Titolo di una scheda, come quello di "Find your error": icona in un cerchio, titolo e una riga di spiegazione.
 *
 * @param string $icon     Nome della Dashicon, senza prefisso.
 * @param string $title    Titolo (già tradotto).
 * @param string $subtitle Riga di spiegazione sotto il titolo (già tradotta).
 */
function e3p_logbeacon_render_step_head( $icon, $title, $subtitle ) {
    echo '<div class="e3p-logbeacon-head"><span class="e3p-logbeacon-head-icon dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></span><div>';
    echo '<h2>' . esc_html( $title ) . '</h2>';
    echo '<p class="e3p-logbeacon-subtitle">' . esc_html( $subtitle ) . '</p>';
    echo '</div></div>';
}

/**
 * Pagina Strumenti → LogBeacon (sola lettura).
 */
function e3p_logbeacon_render_page() {
    if ( ! current_user_can( e3p_logbeacon_capability() ) ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'logbeacon' ), '', array( 'response' => 403 ) );
    }

    $texts     = e3p_logbeacon_get_level_texts();
    $all_files = e3p_logbeacon_get_log_files();
    $files     = array_filter(
        $all_files,
        function ( $file ) {
            return 'ok' === $file['status'];
        }
    );

    /**
     * Byte letti dalla fine del log quando non si sceglie un periodo. Il resto del file non viene caricato.
     * Con 0 (predefinito) si legge quanto può caricare un periodo; un valore più basso lo riduce.
     *
     * @param int $bytes Valore predefinito: 0.
     */
    $tail_bytes = max( 0, (int) apply_filters( 'e3p_logbeacon_tail_bytes', 0 ) );

    /**
     * Voci per pagina nella tabella (le più recenti per prime).
     *
     * @param int $per_page Valore predefinito: 50.
     */
    $per_page = max( 1, (int) apply_filters( 'e3p_logbeacon_per_page', 50 ) );

    // Scelta del log: solo una chiave tra quelle trovate. Lettura, nessuna modifica.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $selected = isset( $_GET['log'] ) ? sanitize_key( wp_unslash( $_GET['log'] ) ) : '';
    if ( ! isset( $files[ $selected ] ) ) {
        $keys     = array_keys( $files );
        $selected = $keys ? $keys[0] : '';
    }

    echo '<div class="wrap e3p-logbeacon-wrap">';
    // Testata grigia di marca: icona (decorativa, alt vuoto: il nome del plugin è il testo del titolo), titolo e una riga
    // che dice cos'è. L'<hr class="wp-header-end"> dice a WordPress dove mettere gli avvisi dell'admin: senza, li
    // infilerebbe dentro la testata, dopo il primo titolo.
    echo '<div class="e3p-logbeacon-band"><h1><img src="' . esc_url( plugins_url( 'assets/icon.svg', __FILE__ ) ) . '" alt="" width="48" height="48" />' . '<span>Log<span class="e3p-logbeacon-accent">Beacon</span></span></h1>'; // Il nome del marchio non si traduce: due colori, come nel banner.
    echo '<p class="e3p-logbeacon-tagline">' . esc_html__( 'Read your WordPress error logs and see what each message means.', 'logbeacon' ) . '</p></div>';
    echo '<hr class="wp-header-end" />';

    e3p_logbeacon_render_debug_section();

    // Tutti i log noti, anche quelli che non esistono ancora: si vede sempre quale si sta leggendo e perché
    // un altro non c'è. Un log che esiste si sceglie con un clic; gli altri sono grigi e dicono perché.
    // Scheda "Quale log": la scelta, il log che si sta leggendo e le azioni su di esso (scarica, email).
    echo '<div class="e3p-logbeacon-step">';
    if ( $all_files ) {
        e3p_logbeacon_render_step_head( 'media-text', __( 'Which log do you want to read?', 'logbeacon' ), __( 'Pick the log you want to look at. You can also download it or send an excerpt by e-mail.', 'logbeacon' ) );
        echo '<div class="e3p-logbeacon-shortcuts" role="group" aria-label="' . esc_attr__( 'Log files', 'logbeacon' ) . '">';
        $has_missing = false;
        foreach ( $all_files as $key => $log_info ) {
            if ( 'ok' === $log_info['status'] ) {
                $log_size = filesize( $log_info['path'] );
                $log_text = $log_info['label'] . ( false !== $log_size ? ' (' . size_format( $log_size ) . ')' : '' );
                $is_shown = ( $key === $selected );
                echo '<a class="button' . ( $is_shown ? ' is-active' : '' ) . '" href="' . esc_url(
                    add_query_arg(
                        array(
                            'page' => 'logbeacon',
                            'log'  => $key,
                        ),
                        admin_url( 'tools.php' )
                    )
                ) . '"' . ( $is_shown ? ' aria-current="true"' : '' ) . '>' . esc_html( $log_text ) . '</a>';
                continue;
            }
            if ( 'unavailable' === $log_info['status'] ) {
                $log_text = sprintf(
                    /* translators: %s: log name */
                    __( '%s — not available', 'logbeacon' ),
                    $log_info['label']
                );
            } elseif ( 'unreadable' === $log_info['status'] ) {
                $log_text = sprintf(
                    /* translators: %s: log name */
                    __( '%s — cannot be read', 'logbeacon' ),
                    $log_info['label']
                );
            } else {
                $has_missing = true;
                $log_text    = sprintf(
                    /* translators: %s: log name */
                    __( '%s — not created yet', 'logbeacon' ),
                    $log_info['label']
                );
            }
            echo '<span class="button e3p-logbeacon-log-off" aria-disabled="true">' . esc_html( $log_text ) . '</span>';
        }
        echo '</div>';
        // Il log di PHP non è un file da leggere (flusso del server, syslog o niente): subito sotto i pulsanti si dice perché.
        $php_unavailable = isset( $all_files['php'] ) && 'unavailable' === $all_files['php']['status'];
        if ( $php_unavailable ) {
            echo '<p class="e3p-logbeacon-why"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><span>' . esc_html( e3p_logbeacon_php_log_reason( $all_files['php']['target'] ) ) . '</span></p>';
        }
        // Il log di LogBeacon è il debug.log di WordPress: lo si dice, perché da solo il nome potrebbe far pensare a un log del plugin.
        if ( isset( $all_files['protected'] ) ) {
            echo '<p class="description">' . esc_html__( 'LogBeacon protected debug.log is the WordPress debug log (the one WP_DEBUG_LOG writes), kept in a protected place.', 'logbeacon' ) . '</p>';
        }
        if ( $has_missing ) {
            echo '<p class="description">' . esc_html__( 'A log marked “not created yet” does not exist: WordPress and PHP (if available) create their logs when the first message is written to them.', 'logbeacon' ) . '</p>';
        }

        // Come ottenere un log di PHP, se non c'è un file da leggere.
        if ( $php_unavailable ) {
            e3p_logbeacon_render_php_log_help();
        }

        // Un log che esiste ma PHP non legge: si dice come rimediare.
        foreach ( $all_files as $log_info ) {
            if ( 'unreadable' === $log_info['status'] ) {
                echo '<ul class="e3p-logbeacon-problems"><li><span class="dashicons dashicons-warning" aria-hidden="true"></span> ';
                printf(
                    /* translators: 1: log name, 2: file path */
                    esc_html__( '%1$s (%2$s): the file exists, but PHP cannot read it. Check its permissions.', 'logbeacon' ),
                    esc_html( $log_info['label'] ),
                    '<code>' . esc_html( e3p_logbeacon_display_path( $log_info['path'] ) ) . '</code>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- già escapato.
                );
                echo '</li></ul>';
            }
        }
    }

    if ( '' === $selected ) {
        // Nessun log ancora: spiegare cosa fare, con parole semplici.
        $logging_on = defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
        echo '<div class="e3p-logbeacon-empty"><span class="dashicons dashicons-media-text" aria-hidden="true"></span><div>';
        echo '<h2>' . esc_html__( 'Nothing to read yet', 'logbeacon' ) . '</h2>';
        echo '<p>' . esc_html(
            $logging_on
                ? __( 'Logging is on, but no log file has been created yet. Repeat the action that causes the problem, then reload this page.', 'logbeacon' )
                : __( 'No log file exists yet. Turn logging on in the box above, repeat the action that causes the problem, then reload this page.', 'logbeacon' )
        ) . '</p>';
        echo '<p class="description">' . esc_html__( 'WordPress only writes debug.log while logging is on, and PHP only writes its own log if your server is set up to do so.', 'logbeacon' ) . '</p>';
        echo '</div></div></div></div>';
        return;
    }

    $file = $files[ $selected ];
    $size = filesize( $file['path'] );

    if ( false === $size ) {
        echo '<p>' . esc_html__( 'The log file could not be read.', 'logbeacon' ) . '</p></div></div>';
        return;
    }

    /**
     * Byte massimi caricati per un intervallo di giorni.
     *
     * @param int $bytes Valore predefinito: 2 MB.
     */
    $max_range = max( 1024, (int) apply_filters( 'e3p_logbeacon_max_range_bytes', 2 * MB_IN_BYTES ) );

    // Il tetto non può superare ciò che la memoria libera di PHP permette di analizzare.
    $memory_cap     = e3p_logbeacon_get_memory_cap();
    $memory_limited = null !== $memory_cap && $memory_cap < $max_range;
    if ( $memory_limited ) {
        $max_range = $memory_cap;
    }
    // Senza periodo si legge la coda del log: per default quanto può caricare un periodo.
    $max_bytes = $tail_bytes > 0 ? max( 1024, min( $tail_bytes, $max_range ) ) : $max_range;

    $bounds = e3p_logbeacon_get_date_bounds( $file['path'], $size );

    // Intervallo scelto: solo date valide Y-m-d. Lettura, nessuna modifica.
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $from = isset( $_GET['from'] ) ? e3p_logbeacon_sanitize_date( sanitize_text_field( wp_unslash( $_GET['from'] ) ) ) : '';
    $to   = isset( $_GET['to'] ) ? e3p_logbeacon_sanitize_date( sanitize_text_field( wp_unslash( $_GET['to'] ) ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $range = null; // Null: ultima parte del file.
    if ( $bounds && ( '' !== $from || '' !== $to ) ) {
        $from = '' !== $from ? $from : $bounds['first'];
        $to   = '' !== $to ? $to : $bounds['last'];
        if ( $from > $to ) {
            list( $from, $to ) = array( $to, $from );
        }
        $range = array(
            'from' => $from,
            'to'   => $to,
        );
    }

    // Percorso mostrato relativo alla cartella di WordPress quando possibile.
    $shown_path = e3p_logbeacon_display_path( $file['path'] );

    $mtime = filemtime( $file['path'] );

    // Il log che si sta leggendo, in evidenza: nome, percorso, peso e ultima modifica.
    echo '<div class="e3p-logbeacon-current"><span class="dashicons dashicons-media-text" aria-hidden="true"></span><div>';
    printf(
        /* translators: %s: log name, e.g. "PHP error_log" */
        '<p class="e3p-logbeacon-current-title">' . esc_html__( 'You are reading: %s', 'logbeacon' ) . '</p>',
        '<strong>' . esc_html( $file['label'] ) . '</strong>'
    );
    if ( 'protected' === $selected ) {
        echo '<p class="e3p-logbeacon-current-note">' . esc_html__( 'This is WordPress’s own debug.log, saved by LogBeacon in a protected folder.', 'logbeacon' ) . '</p>';
    }
    echo '<p class="e3p-logbeacon-current-path"><code>' . esc_html( $shown_path ) . '</code></p>';
    printf(
        /* translators: 1: file size, 2: last modified date and time */
        '<p class="e3p-logbeacon-current-meta">' . esc_html__( '%1$s · last modified %2$s', 'logbeacon' ) . '</p>',
        '<strong>' . esc_html( size_format( $size ) ) . '</strong>',
        '<strong>' . esc_html( $mtime ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $mtime ) : '—' ) . '</strong>'
    );
    echo '</div></div>';

    // Azioni sul log che si sta leggendo, in una riga sotto il riquadro: scarica ed email.
    e3p_logbeacon_render_actions( $selected, (int) $size );
    echo '</div>'; // Fine della scheda "Quale log".

    if ( $memory_limited ) {
        e3p_logbeacon_render_memory_notice( $max_range );
        if ( $max_range < E3P_LOGBEACON_MIN_READ_BYTES ) {
            echo '</div>';
            return;
        }
    }

    // Scheda "Quale periodo": scelta dei giorni, peso di quanto si carica e avvisi sul limite.
    echo '<div class="e3p-logbeacon-step">';

    // Selettore di giorni, solo se il log ha date riconoscibili.
    if ( $bounds ) {
        $log_url = add_query_arg(
            array(
                'page' => 'logbeacon',
                'log'  => $selected,
            ),
            admin_url( 'tools.php' )
        );
        $last_dt = date_create( $bounds['last'], new DateTimeZone( 'UTC' ) );
        $week    = $last_dt ? $last_dt->modify( '-6 days' )->format( 'Y-m-d' ) : $bounds['last'];
        $week    = max( $week, $bounds['first'] );
        $days    = max( 1, (int) date_create( $bounds['first'], new DateTimeZone( 'UTC' ) )->diff( date_create( $bounds['last'], new DateTimeZone( 'UTC' ) ) )->days + 1 );
        $average = $size / $days;

        e3p_logbeacon_render_step_head( 'calendar-alt', __( 'Which period do you want to see?', 'logbeacon' ), __( 'Choose which days of the log you want to look at.', 'logbeacon' ) );

        // Scorciatoie: la scelta attiva è evidenziata (e segnalata con aria-current).
        $shortcuts = array(
            array(
                // Senza un periodo si mostra quanto il plugin riesce a caricare (le voci più recenti, fino al tetto).
                'label'  => __( 'All viewable entries', 'logbeacon' ),
                'url'    => $log_url,
                'active' => ! $range,
            ),
            array(
                'label'  => __( 'Last day in the log', 'logbeacon' ),
                'url'    => add_query_arg(
                    array(
                        'from' => $bounds['last'],
                        'to'   => $bounds['last'],
                    ),
                    $log_url
                ),
                'active' => $range && $range['from'] === $bounds['last'] && $range['to'] === $bounds['last'],
            ),
            array(
                'label'  => __( 'Last 7 days in the log', 'logbeacon' ),
                'url'    => add_query_arg(
                    array(
                        'from' => $week,
                        'to'   => $bounds['last'],
                    ),
                    $log_url
                ),
                'active' => $range && $range['from'] === $week && $range['to'] === $bounds['last'] && $week !== $bounds['last'],
            ),
        );

        echo '<div class="e3p-logbeacon-shortcuts" role="group" aria-label="' . esc_attr__( 'Quick choices', 'logbeacon' ) . '">';
        $shortcut_active = false;
        foreach ( $shortcuts as $shortcut ) {
            // Un secondo clic su un periodo attivo lo toglie e torna alle voci più recenti.
            $shortcut_back = $shortcut['active'] && $range;
            echo '<a class="button' . ( $shortcut['active'] ? ' is-active' : '' ) . '" href="' . esc_url( $shortcut_back ? $log_url : $shortcut['url'] ) . '"' . ( $shortcut['active'] ? ' aria-current="true"' : '' ) . ( $shortcut_back ? ' title="' . esc_attr__( 'Click again to go back to all viewable entries', 'logbeacon' ) . '"' : '' ) . '>' . esc_html( $shortcut['label'] ) . '</a>';
            $shortcut_active = $shortcut_active || $shortcut['active'];
        }
        echo '</div>';

        // Date a scelta: si apre da sola solo se è in uso un intervallo che non è una scorciatoia.
        echo '<details class="e3p-logbeacon-details"' . ( $range && ! $shortcut_active ? ' open' : '' ) . '><summary><span class="dashicons dashicons-edit" aria-hidden="true"></span>' . esc_html__( 'Pick your own dates', 'logbeacon' ) . '</summary>';
        echo '<form method="get" action="" class="e3p-logbeacon-range">';
        echo '<input type="hidden" name="page" value="logbeacon" />';
        echo '<input type="hidden" name="log" value="' . esc_attr( $selected ) . '" />';
        echo '<label for="e3p-logbeacon-from">' . esc_html__( 'From', 'logbeacon' ) . '</label> ';
        echo '<input type="date" id="e3p-logbeacon-from" name="from" value="' . esc_attr( $range ? $range['from'] : '' ) . '" min="' . esc_attr( $bounds['first'] ) . '" max="' . esc_attr( $bounds['last'] ) . '" /> ';
        echo '<label for="e3p-logbeacon-to">' . esc_html__( 'To', 'logbeacon' ) . '</label> ';
        echo '<input type="date" id="e3p-logbeacon-to" name="to" value="' . esc_attr( $range ? $range['to'] : '' ) . '" min="' . esc_attr( $bounds['first'] ) . '" max="' . esc_attr( $bounds['last'] ) . '" /> ';
        echo '<button type="submit" class="button">' . esc_html__( 'Show these dates', 'logbeacon' ) . '</button>';
        if ( $range ) {
            // Toglie le date e torna a "All viewable entries".
            echo ' <a class="e3p-logbeacon-clear" href="' . esc_url( $log_url ) . '"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span>' . esc_html__( 'Clear dates', 'logbeacon' ) . '</a>';
        }
        echo '</form></details>';

        echo '<p class="description">';
        printf(
            /* translators: 1: first date in the log, 2: last date in the log */
            esc_html__( 'This log covers %1$s to %2$s.', 'logbeacon' ),
            esc_html( $bounds['first'] ),
            esc_html( $bounds['last'] )
        );
        echo '</p>';

        // Limiti tecnici: a disposizione, ma fuori dalla vista.
        echo '<details class="e3p-logbeacon-details"><summary><span class="dashicons dashicons-editor-help" aria-hidden="true"></span>' . esc_html__( "Why can't I load everything at once?", 'logbeacon' ) . '</summary><p class="description">';
        esc_html_e( 'Very large logs can make the page slow or run out of memory, so LogBeacon only loads a limited amount at a time. Dates are the ones written in the log (usually UTC), not your site time.', 'logbeacon' );
        echo ' <strong>';
        printf(
            /* translators: %s: size limit, e.g. "2 MB" */
            esc_html__( 'A period can load at most %s of log.', 'logbeacon' ),
            esc_html( size_format( $max_range ) )
        );
        echo '</strong> ';
        if ( $average > $max_range ) {
            printf(
                /* translators: 1: average log growth per day, 2: size limit */
                esc_html__( 'On average it grows about %1$s per day, more than the %2$s limit: some single days may be too large to load.', 'logbeacon' ),
                esc_html( size_format( $average ) ),
                esc_html( size_format( $max_range ) )
            );
        } else {
            printf(
                /* translators: 1: average log growth per day, 2: number of days, 3: size limit */
                esc_html__( 'On average it grows about %1$s per day, so ranges of about %2$s days or less stay under the %3$s limit.', 'logbeacon' ),
                esc_html( size_format( $average ) ),
                esc_html( number_format_i18n( max( 1, (int) floor( $max_range / max( 1, $average ) ) ) ) ),
                esc_html( size_format( $max_range ) )
            );
        }
        echo '</p></details>';
    } elseif ( $size > 0 ) {
        echo '<p class="description">' . esc_html__( 'This log has no dates LogBeacon can read, so only its latest part can be shown.', 'logbeacon' ) . '</p>';
    }

    if ( $range ) {
        $offsets = e3p_logbeacon_range_offsets( $file['path'], $size, $range['from'], $range['to'] );
        if ( false === $offsets ) {
            echo '<p>' . esc_html__( 'The log file could not be read.', 'logbeacon' ) . '</p></div></div>';
            return;
        }
        $bytes   = $offsets['end'] - $offsets['start'];
        $too_big = $bytes > $max_range;

        e3p_logbeacon_render_weight( $bytes, $max_range );

        if ( $too_big ) {
            echo '</div></div>';
            return;
        }

        $content = e3p_logbeacon_read_bytes( $file['path'], $offsets['start'], $bytes );
        if ( false === $content ) {
            echo '<p>' . esc_html__( 'The log file could not be read.', 'logbeacon' ) . '</p></div></div>';
            return;
        }
        $truncated = false;
    } else {
        $tail = e3p_logbeacon_read_tail( $file['path'], $max_bytes );
        if ( false === $tail ) {
            echo '<p>' . esc_html__( 'The log file could not be read.', 'logbeacon' ) . '</p></div></div>';
            return;
        }
        $content   = $tail['content'];
        $truncated = $tail['truncated'];
        e3p_logbeacon_render_weight( min( $size, $max_bytes ), $max_range );
    }

    if ( $truncated ) {
        echo '<p class="description">';
        printf(
            /* translators: %s: size of the whole log file */
            esc_html__( 'This log is large (%s), so only its most recent part is shown. Pick a period above to look at older days.', 'logbeacon' ),
            esc_html( size_format( $size ) )
        );
        echo '</p>';
    }
    echo '</div>'; // Fine della scheda "Quale periodo".

    $entries = e3p_logbeacon_parse_entries( $content );

    if ( ! $entries ) {
        echo '<p>' . esc_html( $range ? __( 'There are no entries in this range.', 'logbeacon' ) : __( 'The log file is empty: nothing has been recorded in it yet.', 'logbeacon' ) ) . '</p></div>';
        return;
    }

    // Riassunto: conteggi per livello sulla parte letta.
    $counts = array_fill_keys( e3p_logbeacon_get_levels(), 0 );
    foreach ( $entries as $position => $entry ) {
        $counts[ $entry['level'] ]++;
        // Chiave per riconoscere le voci uguali (raggruppamento nella tabella).
        $entries[ $position ]['hash'] = e3p_logbeacon_group_hash( $entry );
    }

    // Filtro per livello: solo una chiave tra quelle note. Lettura, nessuna modifica.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $filter = isset( $_GET['level'] ) ? sanitize_key( wp_unslash( $_GET['level'] ) ) : '';
    if ( ! isset( $counts[ $filter ] ) || 0 === $counts[ $filter ] ) {
        $filter = '';
    }

    // Gruppi di livelli (schede in alto). Un filtro per livello esatto ha la precedenza sul gruppo.
    $groups       = e3p_logbeacon_get_groups();
    $group_counts = array();
    foreach ( $groups as $group_key => $group ) {
        $group_counts[ $group_key ] = 0;
        foreach ( $group['levels'] as $group_level ) {
            $group_counts[ $group_key ] += $counts[ $group_level ];
        }
    }
    $group_filter = isset( $_GET['group'] ) ? sanitize_key( wp_unslash( $_GET['group'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter taken from the address; nothing is written.
    if ( '' !== $filter || ! isset( $group_counts[ $group_filter ] ) || 0 === $group_counts[ $group_filter ] ) {
        $group_filter = '';
    }

    // Filtro per origine (plugin, tema, core) e ricerca di testo: arrivano dall'indirizzo, si controllano qui.
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $origin_filter = '';
    if ( isset( $_GET['origin'] ) ) {
        $candidate = sanitize_text_field( wp_unslash( $_GET['origin'] ) );
        if ( e3p_logbeacon_parse_origin_key( $candidate ) ) {
            $origin_filter = $candidate;
        }
    }
    $search        = isset( $_GET['s'] ) ? mb_substr( trim( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ), 0, 100 ) : '';
    $source_search = isset( $_GET['src'] ) ? mb_substr( trim( sanitize_text_field( wp_unslash( $_GET['src'] ) ) ), 0, 100 ) : '';
    // Voci uguali: di default raggruppate; `flat` le elenca tutte, `grp` mostra le voci di un solo messaggio (chiave di 12 caratteri esadecimali).
    $flat       = ! empty( $_GET['flat'] );
    $grp_filter = '';
    if ( isset( $_GET['grp'] ) ) {
        $grp_candidate = sanitize_text_field( wp_unslash( $_GET['grp'] ) );
        if ( preg_match( '/^[a-f0-9]{12}$/', $grp_candidate ) ) {
            $grp_filter = $grp_candidate;
            $flat       = true;
        }
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // I filtri attivi (log, periodo, livello o gruppo, origine, ricerca) viaggiano insieme in ogni indirizzo.
    $view = array(
        'page' => 'logbeacon',
        'log'  => $selected,
    );
    if ( $range ) {
        $view['from'] = $range['from'];
        $view['to']   = $range['to'];
    }
    if ( '' !== $filter ) {
        $view['level'] = $filter;
    } elseif ( '' !== $group_filter ) {
        $view['group'] = $group_filter;
    }
    if ( '' !== $origin_filter ) {
        $view['origin'] = $origin_filter;
    }
    if ( '' !== $search ) {
        $view['s'] = $search;
    }
    if ( '' !== $source_search ) {
        $view['src'] = $source_search;
    }
    if ( '' !== $grp_filter ) {
        $view['grp'] = $grp_filter;
    } elseif ( $flat ) {
        $view['flat'] = '1';
    }

    /**
     * Indirizzo della lista con i filtri attivi, cambiando solo quelli passati (valore vuoto = toglie il filtro).
     * Livello e gruppo si escludono a vicenda.
     */
    $make_url = function ( $over = array() ) use ( $view ) {
        if ( ! empty( $over['group'] ) ) {
            unset( $view['level'] );
        }
        if ( ! empty( $over['level'] ) ) {
            unset( $view['group'] );
        }
        $args = array_filter(
            array_merge( $view, $over ),
            function ( $value ) {
                return '' !== $value && null !== $value;
            }
        );
        return add_query_arg( $args, admin_url( 'tools.php' ) );
    };

    // Tre schede: i gruppi sono sempre tutti visibili; quelli a zero sono attenuati e senza link.
    // Riepilogo in una frase: solo conteggi della parte letta, senza giudizi sullo stato del sito.
    $summary_parts = array();
    foreach ( $groups as $group_key => $group ) {
        $n = $group_counts[ $group_key ];
        if ( 'serious' === $group_key ) {
            /* translators: %s: number of serious problems */
            $summary_parts[] = sprintf( _n( '%s serious problem', '%s serious problems', $n, 'logbeacon' ), number_format_i18n( $n ) );
        } elseif ( 'warnings' === $group_key ) {
            /* translators: %s: number of warnings */
            $summary_parts[] = sprintf( _n( '%s warning', '%s warnings', $n, 'logbeacon' ), number_format_i18n( $n ) );
        } else {
            /* translators: %s: number of minor notes */
            $summary_parts[] = sprintf( _n( '%s minor note', '%s minor notes', $n, 'logbeacon' ), number_format_i18n( $n ) );
        }
    }
    // Scheda "Quali voci": riepilogo, livelli, filtri e ricerche. La tabella resta fuori, in fondo alla pagina.
    echo '<div class="e3p-logbeacon-step">';
    e3p_logbeacon_render_step_head( 'filter', __( 'Which entries do you want to see?', 'logbeacon' ), __( 'Narrow down the list below by type, by message or by where it comes from.', 'logbeacon' ) );

    // Il testo sta in un solo <span>: il paragrafo è flex e senza il contenitore ogni pezzo diventerebbe un elemento separato.
    echo '<p class="e3p-logbeacon-summary-line"><span class="dashicons dashicons-chart-bar" aria-hidden="true"></span><span>';
    /* translators: %s: counts, e.g. "2 serious problems, 14 warnings, 1,402 minor notes" */
    printf( esc_html__( 'In the part of the log shown: %s.', 'logbeacon' ), '<strong>' . esc_html( implode( ', ', $summary_parts ) ) . '</strong>' );
    echo '</span></p>';

    // Riepilogo da incollare quando si chiede aiuto: versione prudente (consigliata) e completa (con avviso).
    $by_hash = array();
    foreach ( $entries as $listed_all ) {
        $hash_all = $listed_all['hash'];
        if ( ! isset( $by_hash[ $hash_all ] ) ) {
            $by_hash[ $hash_all ] = array(
                'n'    => 0,
                'text' => '',
            );
        }
        ++$by_hash[ $hash_all ]['n'];
        $by_hash[ $hash_all ]['text'] = $listed_all['text'];
    }
    uasort(
        $by_hash,
        function ( $a, $b ) {
            return $b['n'] <=> $a['n'];
        }
    );
    $top_items = array();
    foreach ( array_slice( $by_hash, 0, 3 ) as $top ) {
        $term = e3p_logbeacon_message_term( $top['text'] );
        if ( '' === $term ) {
            $term = mb_substr( (string) strtok( $top['text'], "\n" ), 0, 80 );
        }
        $top_origin  = e3p_logbeacon_find_origin( $top['text'] );
        $top_items[] = array(
            'n'      => number_format_i18n( $top['n'] ),
            'text'   => $term,
            'source' => $top_origin ? e3p_logbeacon_scrub( e3p_logbeacon_origin_label( $top_origin ) ) : '',
        );
    }
    $latest_items = array();
    foreach ( array_slice( array_reverse( $entries ), 0, 5 ) as $latest ) {
        $latest_items[] = array(
            'time' => $latest['time'],
            'text' => $latest['text'],
        );
    }
    $support = array(
        'log'    => $file['label'],
        'size'   => size_format( $size ),
        'scope'  => $range ? sprintf( 'days %s to %s', $range['from'], $range['to'] ) : 'most recent part of the file',
        'counts' => sprintf( '%d serious problems, %d warnings, %d minor notes', $group_counts['serious'], $group_counts['warnings'], $group_counts['minor'] ),
        'top'    => $top_items,
        'latest' => $latest_items,
    );

    echo '<details class="e3p-logbeacon-details e3p-logbeacon-support"><summary><span class="dashicons dashicons-sos" aria-hidden="true"></span>' . esc_html__( 'Copy a summary for support', 'logbeacon' ) . '</summary>';
    echo '<p class="description">' . esc_html__( 'Asking someone for help? Copy one of these summaries and paste it into your message.', 'logbeacon' ) . '</p>';
    echo '<div class="e3p-logbeacon-support-grid">';

    echo '<div class="e3p-logbeacon-support-opt"><h3>' . esc_html__( 'Safe summary (recommended)', 'logbeacon' ) . '</h3>';
    echo '<p class="description">' . esc_html__( 'Counts, versions and the most repeated messages. File paths, web addresses, e-mail addresses and IP addresses are removed automatically, but please read it before you share it.', 'logbeacon' ) . '</p>';
    echo '<textarea readonly rows="9" id="e3p-logbeacon-support-safe" class="large-text code">' . esc_textarea( e3p_logbeacon_build_support_summary( $support, false ) ) . '</textarea>';
    echo '<p><button type="button" class="button e3p-logbeacon-copy" data-target="e3p-logbeacon-support-safe" data-label="' . esc_attr__( 'Copy', 'logbeacon' ) . '" data-done="' . esc_attr__( 'Copied', 'logbeacon' ) . '" data-fail="' . esc_attr__( 'Press Ctrl+C to copy', 'logbeacon' ) . '"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span><span class="e3p-logbeacon-copy-label" aria-live="polite">' . esc_html__( 'Copy', 'logbeacon' ) . '</span></button></p></div>';

    echo '<div class="e3p-logbeacon-support-opt"><h3>' . esc_html__( 'Complete summary', 'logbeacon' ) . '</h3>';
    echo '<p class="e3p-logbeacon-warn"><span class="dashicons dashicons-warning" aria-hidden="true"></span><strong>' . esc_html__( 'Includes personal data.', 'logbeacon' ) . '</strong> ' . esc_html__( 'It adds the latest entries exactly as written in the log. They can contain file paths, web addresses, e-mail addresses or IP addresses. Share it only with people you trust.', 'logbeacon' ) . '</p>';
    echo '<textarea readonly rows="9" id="e3p-logbeacon-support-full" class="large-text code">' . esc_textarea( e3p_logbeacon_build_support_summary( $support, true ) ) . '</textarea>';
    echo '<p><button type="button" class="button e3p-logbeacon-copy" data-target="e3p-logbeacon-support-full" data-label="' . esc_attr__( 'Copy', 'logbeacon' ) . '" data-done="' . esc_attr__( 'Copied', 'logbeacon' ) . '" data-fail="' . esc_attr__( 'Press Ctrl+C to copy', 'logbeacon' ) . '"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span><span class="e3p-logbeacon-copy-label" aria-live="polite">' . esc_html__( 'Copy', 'logbeacon' ) . '</span></button></p></div>';

    echo '</div></details>';

    echo '<ul class="e3p-logbeacon-summary">';
    foreach ( $groups as $group_key => $group ) {
        $count      = $group_counts[ $group_key ];
        $is_current = ( $group_key === $group_filter );
        $is_empty   = ( 0 === $count );
        echo '<li class="e3p-logbeacon-level-' . esc_attr( $group['tone'] ) . ( $is_current ? ' is-current' : '' ) . ( $is_empty ? ' is-empty' : '' ) . '">';
        if ( $is_empty ) {
            echo '<div class="e3p-logbeacon-card">';
        } else {
            echo '<a class="e3p-logbeacon-card" href="' . esc_url( $make_url( array( 'group' => $is_current ? '' : $group_key ) ) ) . '"' . ( $is_current ? ' aria-current="true"' : '' ) . '>';
        }
        echo '<strong><span class="dashicons dashicons-' . esc_attr( $group['icon'] ) . '" aria-hidden="true"></span>';
        echo '<span class="e3p-logbeacon-name">' . esc_html( $group['label'] ) . ': ' . esc_html( number_format_i18n( $count ) ) . '</span></strong>';
        echo '<span class="description">' . esc_html( $group['help'] ) . '</span>';
        echo $is_empty ? '</div>' : '</a>';
        echo '</li>';
    }
    echo '</ul>';

    // Filtro avanzato per tipo esatto, per chi lo vuole.
    echo '<details class="e3p-logbeacon-details"' . ( '' !== $filter ? ' open' : '' ) . '><summary><span class="dashicons dashicons-filter" aria-hidden="true"></span>' . esc_html__( 'Filter by exact type', 'logbeacon' ) . '</summary><ul class="e3p-logbeacon-types">';
    foreach ( $counts as $level => $count ) {
        echo '<li class="e3p-logbeacon-level-' . esc_attr( $level ) . ( 0 === $count ? ' is-empty' : '' ) . '">';
        echo '<span class="dashicons dashicons-' . esc_attr( $texts[ $level ]['icon'] ) . '" aria-hidden="true"></span> ';
        $type_label = $texts[ $level ]['label'] . ' (' . number_format_i18n( $count ) . ')';
        if ( 0 === $count ) {
            echo esc_html( $type_label );
        } else {
            echo '<a href="' . esc_url( $make_url( array( 'level' => $level === $filter ? '' : $level ) ) ) . '"' . ( $level === $filter ? ' aria-current="true"' : '' ) . '>' . esc_html( $type_label ) . '</a>';
        }
        echo '</li>';
    }
    echo '</ul></details>';

    // Ricerca di testo: solo sulle voci già lette (la parte caricata), nessuna lettura in più dal disco.
    // Suggerimenti per il campo "Reported in": le origini presenti nella parte caricata, le più frequenti per prime.
    $source_options = array();
    foreach ( $entries as $listed ) {
        $listed_origin = e3p_logbeacon_find_origin( $listed['text'] );
        if ( $listed_origin ) {
            $listed_label = e3p_logbeacon_origin_label( $listed_origin );
            $source_options[ $listed_label ] = ( isset( $source_options[ $listed_label ] ) ? $source_options[ $listed_label ] : 0 ) + 1;
        }
    }
    arsort( $source_options );
    $source_options = array_slice( array_keys( $source_options ), 0, 50 );

    echo '<form method="get" action="" class="e3p-logbeacon-search" role="search">';
    foreach ( $view as $view_key => $view_value ) {
        if ( 's' !== $view_key && 'src' !== $view_key ) {
            echo '<input type="hidden" name="' . esc_attr( $view_key ) . '" value="' . esc_attr( $view_value ) . '" />';
        }
    }
    echo '<div class="e3p-logbeacon-field"><label for="e3p-logbeacon-search"><span class="dashicons dashicons-editor-alignleft" aria-hidden="true"></span>' . esc_html__( 'Search in “What happened”', 'logbeacon' ) . '</label>';
    echo '<input type="search" id="e3p-logbeacon-search" name="s" value="' . esc_attr( $search ) . '" maxlength="100" placeholder="' . esc_attr__( 'e.g. memory, undefined, table', 'logbeacon' ) . '" /></div>';
    echo '<div class="e3p-logbeacon-field"><label for="e3p-logbeacon-source"><span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>' . esc_html__( 'Search in “Reported in”', 'logbeacon' ) . '</label>';
    echo '<input type="search" id="e3p-logbeacon-source" name="src" value="' . esc_attr( $source_search ) . '" maxlength="100" list="e3p-logbeacon-sources" placeholder="' . esc_attr__( 'e.g. a plugin or theme name', 'logbeacon' ) . '" autocomplete="off" />';
    echo '<datalist id="e3p-logbeacon-sources">';
    foreach ( $source_options as $source_option ) {
        echo '<option value="' . esc_attr( $source_option ) . '"></option>';
    }
    echo '</datalist></div>';
    echo '<button type="submit" class="button e3p-logbeacon-iconbtn"><span class="dashicons dashicons-search" aria-hidden="true"></span>' . esc_html__( 'Search', 'logbeacon' ) . '</button>';
    echo '<p class="description">' . esc_html__( 'You can use one field or both. They only search the part of the log loaded above.', 'logbeacon' ) . '</p>';
    echo '</form>';

    // Filtri attivi: livello o gruppo, origine e testo cercato si combinano tra loro.
    $active_filters = array();

    $shown_levels = array();
    if ( '' !== $filter ) {
        $shown_levels     = array( $filter );
        $active_filters[] = $texts[ $filter ]['label'];
    } elseif ( '' !== $group_filter ) {
        $shown_levels     = $groups[ $group_filter ]['levels'];
        $active_filters[] = $groups[ $group_filter ]['label'];
    }
    if ( $shown_levels ) {
        $entries = array_values(
            array_filter(
                $entries,
                function ( $entry ) use ( $shown_levels ) {
                    return in_array( $entry['level'], $shown_levels, true );
                }
            )
        );
    }

    if ( '' !== $origin_filter ) {
        $active_filters[] = e3p_logbeacon_origin_label( e3p_logbeacon_parse_origin_key( $origin_filter ) );
        $entries          = array_values(
            array_filter(
                $entries,
                function ( $entry ) use ( $origin_filter ) {
                    return e3p_logbeacon_origin_key( e3p_logbeacon_find_origin( $entry['text'] ) ) === $origin_filter;
                }
            )
        );
    }

    // Corrispondenza con la ricerca in "Reported in": il nome mostrato e la cartella. Senza origine non c'è corrispondenza.
    $matches_source = function ( $entry ) use ( $source_search ) {
        $origin = e3p_logbeacon_find_origin( $entry['text'] );
        if ( ! $origin ) {
            return false;
        }
        return false !== stripos( e3p_logbeacon_origin_label( $origin ) . "\n" . $origin['slug'], $source_search );
    };

    // Problemi comuni: pulsanti che compilano il campo "What happened" con una parola chiave. Il numero è quello
    // che darebbe il clic: conta le voci con gli altri filtri già applicati (tutti tranne la ricerca in "What happened",
    // che il pulsante sostituisce). Compaiono solo i problemi con almeno una voce.
    $count_base = ( '' !== $source_search ) ? array_filter( $entries, $matches_source ) : $entries;
    $common     = e3p_logbeacon_common_problems();
    $known      = array(); // Problemi noti: chiave => numero di voci.
    $repeated   = array(); // Messaggi che si ripetono: parola chiave => numero di voci con quella parola chiave.
    $terms      = array(); // Parola chiave di ogni voce, per raggruppare i messaggi uguali.
    foreach ( $count_base as $listed ) {
        $sentence_for_count = e3p_logbeacon_explain( $listed['text'] );
        $haystack_for_count = $listed['text'] . "\n" . ( '' !== $sentence_for_count ? $sentence_for_count : $texts[ $listed['level'] ]['help'] );
        foreach ( $common as $common_key => $problem ) {
            if ( false !== stripos( $haystack_for_count, $problem['term'] ) ) {
                $known[ $common_key ] = ( isset( $known[ $common_key ] ) ? $known[ $common_key ] : 0 ) + 1;
            }
        }
        $term = e3p_logbeacon_message_term( $listed['text'] );
        if ( '' !== $term ) {
            $terms[ $term ] = ( isset( $terms[ $term ] ) ? $terms[ $term ] : 0 ) + 1;
        }
    }
    // Messaggi più ripetuti (almeno 2 volte). Quelli già coperti da un problema noto non si ripetono nella riga.
    arsort( $terms );
    foreach ( $terms as $term => $term_count ) {
        if ( $term_count < 2 || count( $repeated ) >= 5 ) {
            break;
        }
        foreach ( $common as $problem ) {
            if ( false !== stripos( $term, $problem['term'] ) ) {
                continue 2;
            }
        }
        // Il numero è quello della ricerca vera (la parola chiave può trovare anche altre voci simili).
        $repeated[ $term ] = 0;
        foreach ( $count_base as $listed ) {
            $sentence_for_count = e3p_logbeacon_explain( $listed['text'] );
            $haystack_for_count = $listed['text'] . "\n" . ( '' !== $sentence_for_count ? $sentence_for_count : $texts[ $listed['level'] ]['help'] );
            if ( false !== stripos( $haystack_for_count, (string) $term ) ) {
                ++$repeated[ $term ];
            }
        }
    }

    /**
     * Una riga di pulsanti che compilano il campo "What happened" con una parola chiave.
     *
     * @param string $title Titolo della riga.
     * @param array  $items Elenco di array con `label`, `term` e `count`.
     */
    $render_chips = function ( $icon, $title, $items ) use ( $search, $make_url ) {
        if ( ! $items ) {
            return;
        }
        echo '<div class="e3p-logbeacon-chips" role="group" aria-label="' . esc_attr( $title ) . '">';
        echo '<span class="e3p-logbeacon-chips-title"><span class="dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></span>' . esc_html( $title ) . '</span>';
        foreach ( $items as $item ) {
            $is_active_chip = ( 0 === strcasecmp( $search, $item['term'] ) );
            // Un secondo clic su un pulsante attivo toglie la ricerca.
            /* translators: %s: keyword that will be searched for */
            $chip_title = $is_active_chip ? __( 'Click again to remove this search', 'logbeacon' ) : sprintf( __( 'Search “What happened” for “%s”', 'logbeacon' ), $item['term'] );
            $label      = mb_strlen( $item['label'] ) > 60 ? mb_substr( $item['label'], 0, 60 ) . '…' : $item['label'];
            echo '<a class="button' . ( $is_active_chip ? ' is-active' : '' ) . '" href="' . esc_url( $make_url( array( 's' => $is_active_chip ? '' : $item['term'] ) ) ) . '" title="' . esc_attr( $chip_title ) . '"' . ( $is_active_chip ? ' aria-current="true"' : '' ) . '>';
            echo esc_html( $label . ' (' . number_format_i18n( $item['count'] ) . ')' ) . '</a>';
        }
        echo '</div>';
    };

    $repeated_items = array();
    foreach ( $repeated as $term => $term_count ) {
        $repeated_items[] = array(
            'label' => (string) $term,
            'term'  => (string) $term,
            'count' => $term_count,
        );
    }
    $known_items = array();
    foreach ( $common as $common_key => $problem ) {
        if ( ! empty( $known[ $common_key ] ) ) {
            $known_items[] = array(
                'label' => $problem['label'],
                'term'  => $problem['term'],
                'count' => $known[ $common_key ],
            );
        }
    }
    $render_chips( 'controls-repeat', __( 'Most repeated messages (search “What happened”):', 'logbeacon' ), $repeated_items );
    $render_chips( 'lightbulb', __( 'Known problems (search “What happened”):', 'logbeacon' ), $known_items );
    echo '</div>'; // Fine della scheda "Quali voci".

    if ( '' !== $search ) {
        /* translators: %s: text searched for */
        $active_filters[] = sprintf( __( 'what happened: “%s”', 'logbeacon' ), $search );
        $entries          = array_values(
            array_filter(
                $entries,
                function ( $entry ) use ( $search, $texts ) {
                    // Come la colonna "What happened": il testo tecnico e la frase semplice mostrata.
                    $sentence = e3p_logbeacon_explain( $entry['text'] );
                    $haystack = $entry['text'] . "\n" . ( '' !== $sentence ? $sentence : $texts[ $entry['level'] ]['help'] );
                    return false !== stripos( $haystack, $search );
                }
            )
        );
    }

    if ( '' !== $source_search ) {
        /* translators: %s: text searched for in the "Reported in" column */
        $active_filters[] = sprintf( __( 'reported in: “%s”', 'logbeacon' ), $source_search );
        $entries          = array_values(
            array_filter( $entries, $matches_source )
        );
    }

    // Un solo messaggio: arriva dal link "See each entry" di una riga raggruppata.
    if ( '' !== $grp_filter ) {
        $active_filters[] = __( 'one repeated message', 'logbeacon' );
        $entries          = array_values(
            array_filter(
                $entries,
                function ( $entry ) use ( $grp_filter ) {
                    return $entry['hash'] === $grp_filter;
                }
            )
        );
    }

    // Le voci più recenti per prime.
    $entries = array_reverse( $entries );
    $total   = count( $entries );

    if ( $active_filters ) {
        echo '<p>';
        printf(
            /* translators: 1: active filters, e.g. "Warnings, Plugin: Akismet", 2: number of matching entries */
            esc_html__( 'Showing only: %1$s (%2$s entries).', 'logbeacon' ),
            '<strong>' . esc_html( implode( ' · ', $active_filters ) ) . '</strong>',
            esc_html( number_format_i18n( $total ) )
        );
        echo ' <a href="' . esc_url(
            $make_url(
                array(
                    'level'  => '',
                    'group'  => '',
                    'origin' => '',
                    's'      => '',
                    'src'    => '',
                    'grp'    => '',
                )
            )
        ) . '">' . esc_html__( 'Show all', 'logbeacon' ) . '</a></p>';
    }

    if ( 0 === $total ) {
        echo '<p>' . esc_html__( 'Nothing found in the part of the log shown. Try another period or clear the filters.', 'logbeacon' ) . '</p></div>';
        return;
    }

    // Voci uguali: di default una riga sola per messaggio, con quante volte compare (le più recenti per prime).
    // Con "flat" (o "grp") si elencano tutte le voci, una riga ciascuna.
    $rows  = array();
    $index = array();
    foreach ( $entries as $entry ) {
        if ( $flat ) {
            $rows[] = array(
                'latest'  => $entry,
                'oldest'  => $entry,
                'count'   => 1,
                'sources' => array(),
            );
            continue;
        }
        $hash = $entry['hash'];
        if ( ! isset( $index[ $hash ] ) ) {
            $index[ $hash ] = count( $rows );
            $rows[]         = array(
                'latest'  => $entry,
                'oldest'  => $entry,
                'count'   => 0,
                'sources' => array(),
            );
        }
        $at = $index[ $hash ];
        ++$rows[ $at ]['count'];
        $rows[ $at ]['oldest']                                                           = $entry;
        $rows[ $at ]['sources'][ e3p_logbeacon_origin_key( e3p_logbeacon_find_origin( $entry['text'] ) ) ] = true;
    }

    // Pagine: solo sulle righe già lette. I filtri attivi fanno parte dell'indirizzo.
    $list_url     = $make_url();
    $total_rows   = count( $rows );
    $pages        = max( 1, (int) ceil( $total_rows / $per_page ) );
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $current_page = isset( $_GET['paged'] ) ? min( $pages, max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) ) : 1;
    $first_shown  = ( $current_page - 1 ) * $per_page;
    $rows         = array_slice( $rows, $first_shown, $per_page );

    // Come sono elencate le voci, con il modo di cambiare.
    echo '<p class="description e3p-logbeacon-groupnote">';
    if ( '' !== $grp_filter ) {
        echo esc_html__( 'Every entry of this message is listed.', 'logbeacon' ) . ' ';
        echo '<a href="' . esc_url( $make_url( array( 'grp' => '' ) ) ) . '">' . esc_html__( 'Group identical messages', 'logbeacon' ) . '</a>';
    } elseif ( $flat ) {
        echo esc_html__( 'Every entry is listed.', 'logbeacon' ) . ' ';
        echo '<a href="' . esc_url( $make_url( array( 'flat' => '' ) ) ) . '">' . esc_html__( 'Group identical messages', 'logbeacon' ) . '</a>';
    } else {
        echo esc_html__( 'Identical messages are shown once, with how many times they appear.', 'logbeacon' ) . ' ';
        echo '<a href="' . esc_url( $make_url( array( 'flat' => '1' ) ) ) . '">' . esc_html__( 'Show every entry', 'logbeacon' ) . '</a>';
    }
    echo '</p>';

    e3p_logbeacon_render_pager( $total_rows, $first_shown + 1, $first_shown + count( $rows ), $pages, $current_page, $list_url, ! $flat );

    echo '<table class="widefat striped e3p-logbeacon-table"><thead><tr>';
    echo '<th scope="col">' . esc_html__( 'Level', 'logbeacon' ) . '</th>';
    echo '<th scope="col">' . esc_html__( 'When', 'logbeacon' ) . '</th>';
    echo '<th scope="col">' . esc_html__( 'What happened', 'logbeacon' ) . '</th>';
    echo '<th scope="col" class="e3p-logbeacon-col-origin">' . esc_html__( 'Reported in', 'logbeacon' ) . '</th>';
    echo '</tr></thead><tbody>';

    foreach ( $rows as $row ) {
        $entry = $row['latest'];
        $count = $row['count'];
        $level = $entry['level'];
        // Una voce molto lunga viene accorciata solo nella vista.
        $text = $entry['text'];
        if ( strlen( $text ) > 4000 ) {
            $text = wp_check_invalid_utf8( substr( $text, 0, 4000 ), true ) . ' …';
        }

        echo '<tr class="e3p-logbeacon-level-' . esc_attr( $level ) . '">';
        echo '<td><span class="e3p-logbeacon-badge"><span class="dashicons dashicons-' . esc_attr( $texts[ $level ]['icon'] ) . '" aria-hidden="true"></span>';
        echo esc_html( $texts[ $level ]['label'] ) . '</span></td>';
        $ago = e3p_logbeacon_relative_time( $entry['time'] );
        echo '<td class="e3p-logbeacon-time" title="' . esc_attr( $entry['time'] ) . '">' . esc_html( e3p_logbeacon_readable_time( $entry['time'] ) );
        if ( '' !== $ago ) {
            echo '<br /><span class="e3p-logbeacon-ago">' . esc_html( $ago ) . '</span>';
        }
        if ( $count > 1 ) {
            /* translators: %s: date and time of the first occurrence */
            echo '<br /><span class="e3p-logbeacon-ago">' . esc_html( sprintf( __( 'First seen: %s', 'logbeacon' ), e3p_logbeacon_readable_time( $row['oldest']['time'] ) ) ) . '</span>';
        }
        echo '</td>';

        // Prima una frase semplice (specifica se il messaggio è noto, altrimenti quella del livello), poi il testo tecnico.
        // Plugin, tema o core a cui appartiene il file indicato nella voce (#36); null se non ricavabile.
        // Sugli schermi larghi sta in una colonna a parte, sugli stretti sotto la frase (il CSS mostra una delle due).
        $origin      = e3p_logbeacon_find_origin( $text );
        $origin_html = '';
        if ( $origin ) {
            $origin_icons = array(
                'plugin'    => 'admin-plugins',
                'mu-plugin' => 'admin-plugins',
                'theme'     => 'admin-appearance',
                'core'      => 'wordpress',
            );
            $origin_key  = e3p_logbeacon_origin_key( $origin );
            $origin_name = '<strong>' . esc_html( e3p_logbeacon_origin_label( $origin ) ) . '</strong>';
            // Un clic sul nome mostra solo le voci di quel plugin, tema o del core; un secondo clic toglie il filtro.
            $origin_active = ( $origin_key === $origin_filter );
            $origin_name   = '<a class="e3p-logbeacon-origin-link" href="' . esc_url( $make_url( array( 'origin' => $origin_active ? '' : $origin_key ) ) ) . '" title="' . esc_attr( $origin_active ? __( 'Click again to remove this filter', 'logbeacon' ) : __( 'Show only the entries from this source', 'logbeacon' ) ) . '">' . $origin_name . '</a>';
            $origin_html = '<span class="dashicons dashicons-' . esc_attr( $origin_icons[ $origin['type'] ] ) . '" aria-hidden="true"></span> ' . $origin_name;
        }
        // Una riga raggruppata con voci da fonti diverse non ne indica una sola.
        if ( $count > 1 && count( $row['sources'] ) > 1 ) {
            $origin_html = '<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span> <strong>' . esc_html__( 'Several sources', 'logbeacon' ) . '</strong>';
        }

        $sentence = e3p_logbeacon_explain( $text );
        echo '<td><p class="e3p-logbeacon-explain">' . esc_html( '' !== $sentence ? $sentence : $texts[ $level ]['help'] ) . '</p>';
        if ( $count > 1 ) {
            echo '<p class="e3p-logbeacon-repeats"><span class="dashicons dashicons-controls-repeat" aria-hidden="true"></span><strong>';
            /* translators: %s: number of times the same message appears */
            echo esc_html( sprintf( _n( 'Repeated %s time', 'Repeated %s times', $count, 'logbeacon' ), number_format_i18n( $count ) ) );
            echo '</strong> <a href="' . esc_url( $make_url( array( 'grp' => $entry['hash'] ) ) ) . '">' . esc_html__( 'See each entry', 'logbeacon' ) . '</a></p>';
        }
        if ( '' !== $origin_html ) {
            echo '<p class="e3p-logbeacon-origin e3p-logbeacon-origin-inline">' . $origin_html . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- già escapato sopra.
        }
        echo '<div class="e3p-logbeacon-entry-row"><details class="e3p-logbeacon-details"><summary>' . esc_html__( 'Show the technical text', 'logbeacon' ) . '</summary>';
        echo '<pre class="e3p-logbeacon-entry">' . esc_html( $text ) . '</pre></details>';
        // Copia il testo tecnico negli appunti (lo script è in e3p_logbeacon_enqueue_styles).
        echo '<button type="button" class="button-link e3p-logbeacon-copy" data-label="' . esc_attr__( 'Copy', 'logbeacon' ) . '" data-done="' . esc_attr__( 'Copied', 'logbeacon' ) . '" data-fail="' . esc_attr__( 'Press Ctrl+C to copy', 'logbeacon' ) . '">';
        echo '<span class="dashicons dashicons-admin-page" aria-hidden="true"></span><span class="e3p-logbeacon-copy-label" aria-live="polite">' . esc_html__( 'Copy', 'logbeacon' ) . '</span></button></div></td>';

        echo '<td class="e3p-logbeacon-origin e3p-logbeacon-col-origin">';
        echo '' !== $origin_html ? $origin_html : '<span class="e3p-logbeacon-none">' . esc_html__( 'Not identified', 'logbeacon' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- già escapato sopra.
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    e3p_logbeacon_render_pager( $total_rows, $first_shown + 1, $first_shown + count( $rows ), $pages, $current_page, $list_url, ! $flat );
    echo '</div>';
}

/**
 * Riga con "Showing 1–50 of 312 entries" e i numeri di pagina. Non mostra nulla se c'è una sola pagina.
 *
 * @param int    $total    Voci totali (dopo il filtro).
 * @param int    $from     Numero della prima voce mostrata.
 * @param int    $to       Numero dell'ultima voce mostrata.
 * @param int    $pages    Pagine totali.
 * @param int    $current  Pagina corrente.
 * @param string $list_url Indirizzo della lista con i filtri attivi, senza `paged`.
 * @param bool   $grouped  True se le righe sono messaggi raggruppati e non singole voci.
 */
function e3p_logbeacon_render_pager( $total, $from, $to, $pages, $current, $list_url, $grouped = false ) {
    if ( $pages < 2 ) {
        return;
    }

    echo '<div class="e3p-logbeacon-pager"><span class="e3p-logbeacon-pager-count">';
    printf(
        $grouped
            /* translators: 1: first row number, 2: last row number, 3: total different messages */
            ? esc_html__( 'Showing %1$s–%2$s of %3$s different messages', 'logbeacon' )
            /* translators: 1: first entry number, 2: last entry number, 3: total entries */
            : esc_html__( 'Showing %1$s–%2$s of %3$s entries', 'logbeacon' ),
        esc_html( number_format_i18n( $from ) ),
        esc_html( number_format_i18n( $to ) ),
        esc_html( number_format_i18n( $total ) )
    );
    echo '</span> ';

    $links = paginate_links(
        array(
            'base'      => add_query_arg( 'paged', '%#%', $list_url ),
            'format'    => '',
            'current'   => $current,
            'total'     => $pages,
            'prev_text' => esc_html__( '‹ Newer', 'logbeacon' ),
            'next_text' => esc_html__( 'Older ›', 'logbeacon' ),
            'mid_size'  => 2,
        )
    );
    echo wp_kses_post( (string) $links );
    echo '</div>';
}
