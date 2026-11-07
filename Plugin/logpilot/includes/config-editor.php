<?php
/**
 * Modifica di wp-config.php limitata alle costanti di debug.
 *
 * Regola "riconosco o rifiuto": si toccano solo WP_DEBUG, WP_DEBUG_LOG e WP_DEBUG_DISPLAY, e solo se
 * sono scritte nella forma standard (una define() su una riga, al livello principale del file).
 * In ogni altro caso non si modifica nulla. Il resto del file non ci riguarda.
 *
 * Ogni riga toccata porta un commento marcatore che dice com'era prima, così non serve salvare
 * niente nel database:
 *   define( 'WP_DEBUG', true ); // LogPilot (original: false)
 *   define( 'WP_DEBUG_LOG', true ); // LogPilot (added)
 *
 * Le funzioni di analisi e di trasformazione lavorano solo su stringhe (testabili senza WordPress).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Costanti gestite.
 *
 * @return string[]
 */
function e3p_logpilot_config_names() {
    return array( 'WP_DEBUG', 'WP_DEBUG_LOG', 'WP_DEBUG_DISPLAY' );
}

/**
 * Percorso come stringa PHP tra apici singoli (con \ e ' protetti), per la define() di WP_DEBUG_LOG.
 *
 * @param string $path Percorso, già con le barre normali.
 * @return string
 */
function e3p_logpilot_config_quote( $path ) {
    return "'" . str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), $path ) . "'";
}

/**
 * Riga di define() con eventuale commento dell'utente e marcatore LogPilot.
 *
 * @param string      $indent  Rientro.
 * @param string      $name    Nome della costante.
 * @param string      $value   'true', 'false' oppure un percorso già tra apici (vedi e3p_logpilot_config_quote()).
 * @param string      $comment Commento già presente (senza il nostro marcatore).
 * @param string|null $marker  'added', 'original: true', 'original: false' oppure null.
 * @return string
 */
function e3p_logpilot_config_build_line( $indent, $name, $value, $comment, $marker ) {
    $line = $indent . "define( '" . $name . "', " . $value . ' );';
    if ( '' !== $comment ) {
        $line .= ' ' . $comment;
    }
    if ( null !== $marker ) {
        $line .= ' // LogPilot (' . $marker . ')';
    }
    return $line;
}

/**
 * Analizza il testo di wp-config.php.
 *
 * @param string $src Contenuto del file.
 * @return array{ok: bool, eol: string, lines: string[], anchor: int|null, constants: array, problems: array}
 *         Ogni costante: found, index, indent, value, comment, marker (null, 'added', 'true' o 'false').
 */
function e3p_logpilot_config_analyze( $src ) {
    $eol = false !== strpos( $src, "\r\n" ) ? "\r\n" : "\n";
    $res = array(
        'ok'        => false,
        'eol'       => $eol,
        'lines'     => preg_split( '/\r\n|\n|\r/', $src ),
        'anchor'    => null,
        'constants' => array(),
        'problems'  => array(),
    );
    $names = e3p_logpilot_config_names();
    foreach ( $names as $name ) {
        $res['constants'][ $name ] = array( 'found' => false );
    }

    // Un file con errori di sintassi non si tocca (TOKEN_PARSE segnala i ParseError senza eseguire nulla).
    try {
        $raw = token_get_all( $src, TOKEN_PARSE );
    } catch ( ParseError $e ) {
        $res['problems'][] = array(
            'code' => 'syntax',
            'name' => '',
            'line' => 0,
        );
        return $res;
    }

    // Token significativi con riga e profondità delle parentesi graffe.
    $sig   = array();
    $depth = 0;
    $line  = 1;
    foreach ( $raw as $t ) {
        if ( is_array( $t ) ) {
            $line = $t[2];
            if ( ! in_array( $t[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
                if ( T_CURLY_OPEN === $t[0] || T_DOLLAR_OPEN_CURLY_BRACES === $t[0] ) {
                    $depth++;
                }
                $sig[] = array( $t[0], $t[1], $t[2], $depth );
            }
            $line += substr_count( $t[1], "\n" );
            continue;
        }
        if ( '{' === $t ) {
            $depth++;
        } elseif ( '}' === $t ) {
            $depth--;
        }
        $sig[] = array( $t, $t, $line, $depth );
    }

    $found = array();
    $total = count( $sig );
    for ( $i = 0; $i < $total; $i++ ) {
        if ( T_CONSTANT_ENCAPSED_STRING !== $sig[ $i ][0] ) {
            continue;
        }
        $name = substr( $sig[ $i ][1], 1, -1 );
        if ( ! in_array( $name, $names, true ) ) {
            continue;
        }

        $standard = false;
        // Perché una riga non è nella forma standard (per spiegarlo all'utente): dentro un blocco, non è un define()
        // semplice, valore diverso da true/false, altro codice sulla stessa riga, percorso non di LogPilot, o altro.
        $reason = ( 0 !== $sig[ $i ][3] ) ? 'nested' : 'format';
        // WP_DEBUG_LOG può avere anche un percorso tra apici singoli, ma solo se è quello di LogPilot (cartella
        // e3p-logpilot-<16 caratteri esadecimali>): un percorso scelto dall'utente resta "forma non standard".
        $is_log = ( 'WP_DEBUG_LOG' === $name );
        if ( $i >= 3 && $i + 4 < $total && 0 === $sig[ $i ][3] ) {
            $prev      = $sig[ $i - 3 ];
            $prev_ok   = T_OPEN_TAG === $prev[0] || ';' === $prev[1] || '}' === $prev[1];
            $define_ok = '(' === $sig[ $i - 1 ][1] && T_STRING === $sig[ $i - 2 ][0] && 0 === strcasecmp( $sig[ $i - 2 ][1], 'define' );
            $value_tok = $sig[ $i + 2 ];
            $value_ok  = ( T_STRING === $value_tok[0] && in_array( strtolower( $value_tok[1] ), array( 'true', 'false' ), true ) )
                || ( $is_log && T_CONSTANT_ENCAPSED_STRING === $value_tok[0] && "'" === substr( $value_tok[1], 0, 1 ) );
            $tail_ok   = ',' === $sig[ $i + 1 ][1]
                && $value_ok
                && ')' === $sig[ $i + 3 ][1]
                && ';' === $sig[ $i + 4 ][1];
            $same_line = $sig[ $i - 2 ][2] === $sig[ $i + 4 ][2];
            if ( ! $prev_ok || ! $define_ok ) {
                $reason = 'notdefine';
            } elseif ( ! $value_ok ) {
                $reason = 'value';
            } elseif ( $tail_ok && ! $same_line ) {
                $reason = 'sameline';
            }

            if ( $prev_ok && $define_ok && $tail_ok && $same_line ) {
                $index    = $sig[ $i - 2 ][2] - 1;
                $text     = isset( $res['lines'][ $index ] ) ? $res['lines'][ $index ] : '';
                $value_re = $is_log ? '(true|false|\'(?:[^\'\\\\]|\\\\.)*\')' : '(true|false)';
                $re       = '/^(\s*)define\s*\(\s*([\'"])' . preg_quote( $name, '/' ) . '\2\s*,\s*' . $value_re . '\s*\)\s*;(.*)$/i';
                if ( preg_match( $re, $text, $m ) ) {
                    $trail = trim( $m[4] );
                    if ( '' !== $trail && ! preg_match( '/^(?:\/\/.*|#.*|\/\*.*\*\/)$/', $trail ) ) {
                        $reason = 'sameline'; // Dopo il define() c'è altro codice sulla stessa riga.
                    }
                    if ( preg_match( '/^(?:\/\/.*|#.*|\/\*.*\*\/)?$/', $trail ) ) {
                        $marker = null;
                        if ( preg_match( '/\s*\/\/\s*LogPilot \((?:original: (true|false)|(added))\)\s*$/', $trail, $mm ) ) {
                            $marker = ( isset( $mm[1] ) && '' !== $mm[1] ) ? $mm[1] : 'added';
                            $trail  = trim( (string) preg_replace( '/\s*\/\/\s*LogPilot \((?:original: (?:true|false)|added)\)\s*$/', '', $trail ) );
                        }
                        $value = strtolower( $m[3] );
                        $path  = '';
                        if ( "'" === substr( $m[3], 0, 1 ) ) {
                            // Come PHP tra apici singoli: \\ diventa \ e \' diventa '.
                            $value = 'path';
                            $path  = strtr( substr( $m[3], 1, -1 ), array( '\\\\' => '\\', "\\'" => "'" ) );
                        }
                        if ( 'path' !== $value || preg_match( '#/e3p-logpilot-[a-f0-9]{16}(?:/|$)#', $path ) ) {
                            $standard         = true;
                            $found[ $name ][] = array(
                                'found'   => true,
                                'index'   => $index,
                                'indent'  => $m[1],
                                'value'   => $value,
                                'path'    => $path,
                                'comment' => $trail,
                                'marker'  => $marker,
                            );
                        }
                        if ( ! $standard ) {
                            $reason = 'path'; // Un percorso scelto dall'utente: si lascia com'è.
                        }
                    }
                }
            }
        }

        if ( ! $standard ) {
            // Il testo della riga si mostra nel pannello (con l'escape), accorciato: sono righe delle costanti di debug.
            $line_text = isset( $res['lines'][ $sig[ $i ][2] - 1 ] ) ? trim( $res['lines'][ $sig[ $i ][2] - 1 ] ) : '';
            $res['problems'][] = array(
                'code'   => 'nonstandard',
                'name'   => $name,
                'line'   => $sig[ $i ][2],
                'reason' => $reason,
                'text'   => mb_substr( $line_text, 0, 120 ),
            );
        }
    }

    foreach ( $found as $name => $items ) {
        if ( count( $items ) > 1 ) {
            $res['problems'][] = array(
                'code' => 'duplicate',
                'name' => $name,
                'line' => 0,
            );
        } else {
            $res['constants'][ $name ] = $items[0];
        }
    }

    // Punto dove aggiungere le costanti mancanti: prima di "That's all, stop editing!" o del require di wp-settings.php.
    foreach ( $res['lines'] as $index => $text ) {
        if ( preg_match( '/That\'?s all,? stop editing/i', $text ) ) {
            $res['anchor'] = $index;
            break;
        }
    }
    if ( null === $res['anchor'] ) {
        foreach ( $res['lines'] as $index => $text ) {
            if ( preg_match( '/^\s*require_once\s+ABSPATH\s*\.\s*[\'"]wp-settings\.php[\'"]\s*;/', $text ) ) {
                $res['anchor'] = $index;
                break;
            }
        }
    }
    if ( null === $res['anchor'] ) {
        $res['problems'][] = array(
            'code' => 'anchor',
            'name' => '',
            'line' => 0,
        );
    }

    $res['ok'] = empty( $res['problems'] );
    return $res;
}

/**
 * Stato letto dall'analisi: cosa è gestito da LogPilot e se il debug era già acceso prima.
 *
 * @param array $analysis Risultato di e3p_logpilot_config_analyze().
 * @return array{managed: bool, was_on: bool, unmanaged_on: bool}
 */
function e3p_logpilot_config_state( $analysis ) {
    $state = array(
        'managed'      => false,
        'was_on'       => false,
        'unmanaged_on' => false,
    );
    foreach ( $analysis['constants'] as $name => $c ) {
        if ( empty( $c['found'] ) ) {
            continue;
        }
        if ( null !== $c['marker'] ) {
            $state['managed'] = true;
            if ( 'true' === $c['marker'] && 'WP_DEBUG_DISPLAY' !== $name ) {
                $state['was_on'] = true;
            }
        } elseif ( 'true' === $c['value'] && 'WP_DEBUG_DISPLAY' !== $name ) {
            $state['unmanaged_on'] = true;
        }
    }
    return $state;
}

/**
 * Applica "acceso" o "spento" al testo di wp-config.php.
 *
 * Acceso: WP_DEBUG e WP_DEBUG_LOG a true, WP_DEBUG_DISPLAY sempre a false.
 * Spento: WP_DEBUG e WP_DEBUG_LOG a false (le righe aggiunte da noi si tolgono); WP_DEBUG_DISPLAY
 * torna com'era se l'avevamo toccato.
 *
 * @param string      $src      Contenuto attuale.
 * @param bool        $on       True per accendere, false per spegnere.
 * @param string|null $log_path Percorso del log di LogPilot (barre normali): se c'è, accendendo WP_DEBUG_LOG
 *                              prende quel percorso al posto di `true`.
 * @return array{src: string|null, error: string, analysis: array} `error` vuoto se tutto è andato bene.
 */
function e3p_logpilot_config_apply( $src, $on, $log_path = null ) {
    $a = e3p_logpilot_config_analyze( $src );
    if ( ! $a['ok'] ) {
        return array(
            'src'      => null,
            'error'    => 'unrecognized',
            'analysis' => $a,
        );
    }

    $lines  = $a['lines'];
    $remove = array();
    $add    = array();

    foreach ( e3p_logpilot_config_names() as $name ) {
        $c       = $a['constants'][ $name ];
        $display = ( 'WP_DEBUG_DISPLAY' === $name );

        if ( $on ) {
            $target = $display ? 'false' : 'true';
            if ( 'WP_DEBUG_LOG' === $name && null !== $log_path ) {
                $target = e3p_logpilot_config_quote( $log_path );
            }
            if ( $c['found'] ) {
                $orig = null !== $c['marker'] ? $c['marker'] : $c['value'];
                if ( 'path' === $orig ) {
                    $orig = 'false'; // Un percorso nostro senza marcatore: si tratta come se fosse spento in origine.
                }
                $lines[ $c['index'] ] = e3p_logpilot_config_build_line( $c['indent'], $name, $target, $c['comment'], 'added' === $orig ? 'added' : 'original: ' . $orig );
            } else {
                $add[] = e3p_logpilot_config_build_line( '', $name, $target, '', 'added' );
            }
            continue;
        }

        if ( ! $c['found'] ) {
            continue;
        }
        if ( $display ) {
            if ( null === $c['marker'] ) {
                continue; // Non l'abbiamo toccata noi: non si tocca.
            }
            if ( 'added' === $c['marker'] ) {
                $remove[] = $c['index'];
            } else {
                $lines[ $c['index'] ] = e3p_logpilot_config_build_line( $c['indent'], $name, $c['marker'], $c['comment'], null );
            }
            continue;
        }

        $orig = null !== $c['marker'] ? $c['marker'] : $c['value'];
        if ( 'path' === $orig ) {
            $orig = 'false';
        }
        if ( 'added' === $orig ) {
            $remove[] = $c['index'];
        } elseif ( 'true' === $orig ) {
            // Era già acceso prima di LogPilot: si spegne, ma il marcatore resta per segnalarlo.
            $lines[ $c['index'] ] = e3p_logpilot_config_build_line( $c['indent'], $name, 'false', $c['comment'], 'original: true' );
        } else {
            $lines[ $c['index'] ] = e3p_logpilot_config_build_line( $c['indent'], $name, 'false', $c['comment'], null );
        }
    }

    $out = array();
    foreach ( $lines as $i => $text ) {
        if ( $i === $a['anchor'] ) {
            foreach ( $add as $new_line ) {
                $out[] = $new_line;
            }
        }
        if ( in_array( $i, $remove, true ) ) {
            continue;
        }
        $out[] = $text;
    }
    $new = implode( $a['eol'], $out );

    // Controllo: il risultato deve avere la sintassi giusta e le costanti con i valori voluti.
    $check = e3p_logpilot_config_analyze( $new );
    $good  = $check['ok'];
    if ( $good ) {
        foreach ( array( 'WP_DEBUG', 'WP_DEBUG_LOG' ) as $name ) {
            $c = $check['constants'][ $name ];
            if ( $on && 'WP_DEBUG_LOG' === $name && null !== $log_path ) {
                // Con il percorso di LogPilot, WP_DEBUG_LOG deve valere proprio quel percorso.
                if ( empty( $c['found'] ) || 'path' !== $c['value'] || $log_path !== $c['path'] ) {
                    $good = false;
                }
            } elseif ( $on && ( empty( $c['found'] ) || 'true' !== $c['value'] ) ) {
                $good = false;
            }
            if ( ! $on && ! empty( $c['found'] ) && 'false' !== $c['value'] ) {
                $good = false;
            }
        }
        $d = $check['constants']['WP_DEBUG_DISPLAY'];
        if ( $on && ( empty( $d['found'] ) || 'false' !== $d['value'] ) ) {
            $good = false;
        }
    }

    return array(
        'src'      => $good ? $new : null,
        'error'    => $good ? '' : 'selfcheck',
        'analysis' => $check,
    );
}

/**
 * Percorso di wp-config.php (stessa regola di WordPress: nella radice o una cartella sopra).
 *
 * @return string Percorso, o stringa vuota se non si trova.
 */
function e3p_logpilot_config_path() {
    if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
        return ABSPATH . 'wp-config.php';
    }
    $parent = dirname( ABSPATH );
    if ( file_exists( $parent . '/wp-config.php' ) && ! file_exists( $parent . '/wp-settings.php' ) ) {
        return $parent . '/wp-config.php';
    }
    return '';
}

/**
 * Nome del file di backup: ha estensione .php perché con un'altra (es. .bak) il server potrebbe
 * mostrarlo come testo, con le password del database dentro.
 *
 * @param string $path Percorso di wp-config.php.
 * @return string
 */
function e3p_logpilot_config_backup_path( $path ) {
    return dirname( $path ) . '/wp-config-logpilot-backup.php';
}

/**
 * Sintassi PHP corretta? Si controlla senza eseguire nulla.
 *
 * @param string $src Codice.
 * @return bool
 */
function e3p_logpilot_config_syntax_ok( $src ) {
    try {
        token_get_all( $src, TOKEN_PARSE );
        return true;
    } catch ( ParseError $e ) {
        return false;
    }
}

/**
 * Scrive il nuovo wp-config.php in modo sicuro: backup, file temporaneo, controllo di sintassi,
 * sostituzione atomica, rilettura e ripristino automatico se qualcosa non torna.
 *
 * @param string $path        Percorso di wp-config.php.
 * @param string $new_src     Nuovo contenuto.
 * @param bool   $make_backup True per (ri)creare il backup prima di scrivere. Il ripristino automatico non
 *                            dipende dal file di backup: usa il contenuto letto qui.
 * @return string Codice di errore (`read`, `not_writable`, `syntax`, `backup`, `write`, `verify`) o stringa vuota.
 */
function e3p_logpilot_config_write( $path, $new_src, $make_backup = true ) {
    $old = file_get_contents( $path );
    if ( false === $old ) {
        return 'read';
    }
    if ( ! is_writable( $path ) || ! is_writable( dirname( $path ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Checks real write access to wp-config.php or the log folder before changing anything.
        return 'not_writable';
    }
    if ( ! e3p_logpilot_config_syntax_ok( $new_src ) ) {
        return 'syntax';
    }

    if ( $make_backup && ! copy( $path, e3p_logpilot_config_backup_path( $path ) ) ) {
        return 'backup';
    }

    $temp  = dirname( $path ) . '/wp-config-logpilot-tmp.php';
    $bytes = file_put_contents( $temp, $new_src, LOCK_EX );
    if ( strlen( $new_src ) !== $bytes ) {
        if ( file_exists( $temp ) ) {
            wp_delete_file( $temp );
        }
        return 'write';
    }
    $perms = fileperms( $path );
    if ( false !== $perms ) {
        chmod( $temp, $perms & 0777 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Keeps the permissions of wp-config.php on its replacement.
    }

    if ( ! rename( $temp, $path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Atomic replacement of wp-config.php: the new file is complete or nothing changes.
        if ( file_exists( $temp ) ) {
            wp_delete_file( $temp );
        }
        return 'write';
    }
    if ( function_exists( 'opcache_invalidate' ) ) {
        opcache_invalidate( $path, true );
    }

    // Rilettura: se non è identico o la sintassi non è valida, si ripristina il contenuto di prima.
    $after = file_get_contents( $path );
    if ( $after !== $new_src || ! e3p_logpilot_config_syntax_ok( $after ) ) {
        file_put_contents( $path, $old, LOCK_EX );
        if ( function_exists( 'opcache_invalidate' ) ) {
            opcache_invalidate( $path, true );
        }
        return 'verify';
    }

    return '';
}
