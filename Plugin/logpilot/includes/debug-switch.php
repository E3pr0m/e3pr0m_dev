<?php
/**
 * Pannello e pulsante per accendere e spegnere il debug (modifica di wp-config.php).
 * La logica di modifica sta in config-editor.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Messaggi dell'esito (codice => testo e tipo). I codici arrivano nell'URL: si accettano solo questi.
 *
 * @return array<string, array{type: string, text: string}>
 */
function e3p_logpilot_get_result_texts() {
    return array(
        'on_ok'        => array(
            'type' => 'success',
            'text' => __( 'Debug logging is now on. Remember to turn it off when you are done: logs can contain sensitive data.', 'logpilot' ),
        ),
        'off_ok'       => array(
            'type' => 'success',
            'text' => __( 'Debug logging is now off.', 'logpilot' ),
        ),
        'confirm'      => array(
            'type' => 'warning',
            'text' => __( 'Nothing was changed: tick the confirmation box first.', 'logpilot' ),
        ),
        'logdir'       => array(
            'type' => 'error',
            'text' => __( 'Nothing was changed: LogPilot could not create the folder where the log is saved.', 'logpilot' ),
        ),
        'confirm_delete' => array(
            'type' => 'warning',
            'text' => __( 'Nothing was deleted: tick the confirmation box first.', 'logpilot' ),
        ),
        'old_deleted'  => array(
            'type' => 'success',
            'text' => __( 'The old wp-content/debug.log was deleted.', 'logpilot' ),
        ),
        'old_failed'   => array(
            'type' => 'error',
            'text' => __( 'The old wp-content/debug.log could not be deleted. Check its permissions or delete it by hand.', 'logpilot' ),
        ),
        'old_none'     => array(
            'type' => 'info',
            'text' => __( 'There was no wp-content/debug.log to delete.', 'logpilot' ),
        ),
        'email_sent'      => array(
            'type' => 'success',
            'text' => __( 'The excerpt was handed over to your site’s mail system. If it does not arrive, check the spam folder and your site’s e-mail setup.', 'logpilot' ),
        ),
        'email_failed'    => array(
            'type' => 'error',
            'text' => __( 'The e-mail could not be sent, so nothing was delivered. Most often the site cannot send e-mail: this is normal on a local test site, and on a live site an SMTP plugin or your host’s e-mail settings usually fix it. You can still download the log instead.', 'logpilot' ),
        ),
        'email_invalid'   => array(
            'type' => 'error',
            'text' => __( 'Nothing was sent: enter a valid e-mail address.', 'logpilot' ),
        ),
        'email_consent'   => array(
            'type' => 'warning',
            'text' => __( 'Nothing was sent: tick the confirmation box first.', 'logpilot' ),
        ),
        'email_none'      => array(
            'type' => 'error',
            'text' => __( 'Nothing was sent: that log is not available or has nothing to send.', 'logpilot' ),
        ),
        'download_none'   => array(
            'type' => 'error',
            'text' => __( 'Nothing was downloaded: that log is not available.', 'logpilot' ),
        ),
        'download_failed' => array(
            'type' => 'error',
            'text' => __( 'The log could not be read, so nothing was downloaded. Check its permissions.', 'logpilot' ),
        ),
        'email'        => array(
            'type' => 'error',
            'text' => __( 'Nothing was changed: enter a valid e-mail address for the reminder, or choose an automatic turn-off time.', 'logpilot' ),
        ),
        'unrecognized' => array(
            'type' => 'error',
            'text' => __( 'Nothing was changed: wp-config.php is not written in a form LogPilot recognizes.', 'logpilot' ),
        ),
        'selfcheck'    => array(
            'type' => 'error',
            'text' => __( 'Nothing was changed: LogPilot could not produce a safe version of wp-config.php.', 'logpilot' ),
        ),
        'read'         => array(
            'type' => 'error',
            'text' => __( 'Nothing was changed: wp-config.php could not be read.', 'logpilot' ),
        ),
        'not_writable' => array(
            'type' => 'error',
            'text' => __( 'Nothing was changed: wp-config.php (or its folder) is not writable.', 'logpilot' ),
        ),
        'syntax'       => array(
            'type' => 'error',
            'text' => __( 'Nothing was changed: the new wp-config.php would have a syntax error.', 'logpilot' ),
        ),
        'backup'       => array(
            'type' => 'error',
            'text' => __( 'Nothing was changed: the backup copy could not be created.', 'logpilot' ),
        ),
        'write'        => array(
            'type' => 'error',
            'text' => __( 'The new wp-config.php could not be written. The original file was kept.', 'logpilot' ),
        ),
        'verify'       => array(
            'type' => 'error',
            'text' => __( 'The written file did not pass the final check, so the original wp-config.php was restored from the backup.', 'logpilot' ),
        ),
    );
}

/**
 * Gestisce il pulsante (admin-post): solo manage_options, nonce, conferma esplicita.
 */
function e3p_logpilot_handle_toggle() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to do this.', 'logpilot' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'e3p_logpilot_toggle' );

    $on     = isset( $_POST['e3p_logpilot_action'] ) && 'on' === sanitize_key( wp_unslash( $_POST['e3p_logpilot_action'] ) );
    $result = '';

    // Scelta di spegnimento automatico o promemoria (solo all'accensione). Se non è valida non si cambia nulla.
    $choice = $on ? e3p_logpilot_read_choice() : array();

    if ( empty( $_POST['e3p_logpilot_confirm'] ) ) {
        $result = 'confirm';
    } elseif ( isset( $choice['error'] ) ) {
        $result = $choice['error'];
    } else {
        $path = e3p_logpilot_config_path();
        $src  = ( '' !== $path && is_readable( $path ) ) ? file_get_contents( $path ) : false;
        if ( false === $src ) {
            $result = 'read';
        } else {
            // Accendendo, il log va in una cartella con nome non indovinabile (#41). Si prepara prima di scrivere
            // wp-config.php, così se non si riesce non si cambia nulla.
            $loc      = null;
            $log_path = null;
            if ( $on ) {
                $loc = e3p_logpilot_setup_log_location();
                if ( null === $loc ) {
                    $result = 'logdir';
                } else {
                    $log_path = $loc['file'];
                }
            }

            $applied = ( '' === $result ) ? e3p_logpilot_config_apply( $src, $on, $log_path ) : array( 'error' => $result );
            if ( '' !== $applied['error'] ) {
                $result = $applied['error'];
            } else {
                // Il backup è il file com'era prima di accendere: si rifà a ogni accensione e, spegnendo,
                // solo se non esiste ancora (per esempio se il debug era già acceso prima di LogPilot).
                $make_backup = $on || ! file_exists( e3p_logpilot_config_backup_path( $path ) );
                $result      = e3p_logpilot_config_write( $path, $applied['src'], $make_backup );
                if ( '' === $result ) {
                    $result = $on ? 'on_ok' : 'off_ok';
                    // Acceso: si pianifica lo spegnimento o il promemoria, e si controlla se i log si possono
                    // scaricare dal web. Spento: si toglie ogni pianificazione.
                    if ( $on ) {
                        e3p_logpilot_start_schedule( $choice );
                        $checks = e3p_logpilot_run_checks( $loc );
                    } else {
                        e3p_logpilot_clear_schedule();
                    }
                }
            }
        }
    }

    $args = array(
        'page'            => 'logpilot',
        'e3p_logpilot_rs' => $result,
    );
    if ( isset( $checks ) ) {
        $args['e3p_logpilot_pr'] = $checks['folder'];
        $args['e3p_logpilot_od'] = $checks['old'];
    }
    wp_safe_redirect( add_query_arg( $args, admin_url( 'tools.php' ) ) );
    exit;
}
add_action( 'admin_post_e3p_logpilot_toggle', 'e3p_logpilot_handle_toggle' );

/**
 * Righe da mettere a mano in wp-config.php, per accendere o per spegnere.
 *
 * @param bool $on True per accendere.
 * @return string
 */
function e3p_logpilot_manual_lines( $on ) {
    $value = $on ? 'true' : 'false';
    return "define( 'WP_DEBUG', " . $value . " );\n"
        . "define( 'WP_DEBUG_LOG', " . $value . " );\n"
        . "define( 'WP_DEBUG_DISPLAY', false );";
}

/**
 * Spiegazione per chi non può usare il pulsante perché wp-config.php non si legge o non si scrive:
 * perché succede e a chi rivolgersi per sistemarlo.
 */
function e3p_logpilot_render_manual_help() {
    echo '<p class="description">' . esc_html__( 'This usually happens when the file is set to read-only, belongs to another user on the server, or your hosting provider locks it for security. Nothing is wrong with your site.', 'logpilot' ) . '</p>';
    echo '<p>' . esc_html__( 'Who can help:', 'logpilot' ) . '</p><ul class="e3p-logpilot-help-list">';
    echo '<li>' . esc_html__( 'Your hosting provider’s support: they can change the file permissions or add the lines for you.', 'logpilot' ) . '</li>';
    echo '<li>' . esc_html__( 'The file manager in your hosting control panel, or an SFTP program: open wp-config.php, add the lines above the line “That’s all, stop editing!”, and save.', 'logpilot' ) . '</li>';
    echo '<li>' . esc_html__( 'The person who manages the site, if it is not you.', 'logpilot' ) . '</li>';
    echo '</ul>';
}

/**
 * Blocco "come dovrebbe essere scritto perché il pulsante funzioni".
 *
 * @param bool $on True se il debug è acceso ora (si mostrano i valori da usare per tornare a spento).
 */
function e3p_logpilot_render_expected_format( $on ) {
    echo '<p>' . esc_html__( 'For the button to work, these three lines must be written like this in wp-config.php: one per line, at the top level of the file (not inside an "if" block), above the line "That\'s all, stop editing!", and not defined anywhere else in the file.', 'logpilot' ) . '</p>';
    echo '<pre class="e3p-logpilot-code">' . esc_html( e3p_logpilot_manual_lines( false ) ) . '</pre>';
    echo '<p class="description">' . esc_html__( 'This is the "off" state. After you fix the file, reload this page: the button will appear.', 'logpilot' ) . '</p>';
    if ( $on ) {
        echo '<p class="description">' . esc_html__( 'Debug logging is on right now. To turn it off by hand, change true to false in the first two lines.', 'logpilot' ) . '</p>';
    }
}

/**
 * Sezione "Debug logging" in cima alla pagina di LogPilot.
 */
function e3p_logpilot_render_debug_section() {
    $texts  = e3p_logpilot_get_result_texts();
    $active = defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;

    echo '<div class="e3p-logpilot-box e3p-logpilot-friendly' . ( $active ? ' is-on' : '' ) . '">';
    echo '<div class="e3p-logpilot-head"><span class="e3p-logpilot-head-icon dashicons dashicons-search" aria-hidden="true"></span><div>';
    echo '<h2>' . esc_html__( 'Find your error - check your logs', 'logpilot' ) . '</h2>';
    echo '<p class="e3p-logpilot-subtitle">' . esc_html__( 'Use this box to turn logging on easily, and let WordPress write down what goes wrong.', 'logpilot' ) . '</p>';
    echo '</div></div>';

    // Esito dell'ultima azione: solo codici noti.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $code = isset( $_GET['e3p_logpilot_rs'] ) ? sanitize_key( wp_unslash( $_GET['e3p_logpilot_rs'] ) ) : '';
    // Gli esiti di "scarica" ed "email" compaiono nella riga delle azioni, accanto ai pulsanti premuti.
    if ( isset( $texts[ $code ] ) && ! e3p_logpilot_is_action_result( $code ) ) {
        printf(
            '<div class="notice notice-%1$s inline"><p>%2$s</p></div>',
            esc_attr( $texts[ $code ]['type'] ),
            esc_html( $texts[ $code ]['text'] )
        );
    }

    echo '<p><span class="e3p-logpilot-status"><span class="dashicons dashicons-' . ( $active ? 'yes-alt' : 'marker' ) . '" aria-hidden="true"></span>';
    echo esc_html( $active ? __( 'Logging is on', 'logpilot' ) : __( 'Logging is off', 'logpilot' ) );
    echo '</span></p>';
    echo '<p>' . esc_html(
        $active
            ? __( 'WordPress is writing the problems it meets into a log file, and you can read them below. When you are done, turn logging off: a log can contain private information.', 'logpilot' )
            : __( 'To find out what is going wrong, turn logging on, repeat the action that causes the problem, then come back here to see what was written down.', 'logpilot' )
    ) . '</p>';
    echo '<p class="description"><strong>' . esc_html__( 'This switch only controls the WordPress log (debug.log). The PHP error log belongs to your server: LogPilot shows it below when it exists, but it cannot turn it on.', 'logpilot' ) . '</strong></p>';

    $path = e3p_logpilot_config_path();
    $src  = ( '' !== $path && is_readable( $path ) ) ? file_get_contents( $path ) : false;

    if ( false === $src ) {
        echo '<p>' . esc_html__( 'LogPilot could not read wp-config.php, so it cannot change it. To turn logging on or off, these lines have to be set by hand:', 'logpilot' ) . '</p>';
        echo '<pre class="e3p-logpilot-code">' . esc_html( e3p_logpilot_manual_lines( ! $active ) ) . '</pre>';
        e3p_logpilot_render_manual_help();
        echo '</div>';
        return;
    }

    $analysis = e3p_logpilot_config_analyze( $src );

    if ( ! $analysis['ok'] ) {
        e3p_logpilot_render_problems( $analysis['problems'] );
        e3p_logpilot_render_expected_format( $active );
        echo '</div>';
        return;
    }

    $state = e3p_logpilot_config_state( $analysis );

    // Avvisi su come erano le cose prima di LogPilot.
    if ( $state['was_on'] ) {
        echo '<div class="e3p-logpilot-problems"><p><span class="dashicons dashicons-warning" aria-hidden="true"></span> ';
        echo esc_html(
            $active
                ? __( 'Warning: debug logging was already on in wp-config.php before LogPilot changed it. If you turn it off here, LogPilot sets it to false.', 'logpilot' )
                : __( 'Note: debug logging was already on in wp-config.php before LogPilot first changed it. LogPilot has set it to false, so it is off now.', 'logpilot' )
        );
        echo '</p></div>';
    } elseif ( $state['unmanaged_on'] ) {
        echo '<div class="e3p-logpilot-problems"><p><span class="dashicons dashicons-warning" aria-hidden="true"></span> ';
        echo esc_html__( 'Warning: debug logging was turned on in wp-config.php, not by LogPilot. If you turn it off here, LogPilot sets WP_DEBUG and WP_DEBUG_LOG to false in that file.', 'logpilot' );
        echo '</p></div>';
    }

    if ( ! is_writable( $path ) || ! is_writable( dirname( $path ) ) ) {
        echo '<p>' . esc_html__( 'wp-config.php (or its folder) is not writable, so LogPilot cannot change it. To turn logging on or off, these lines have to be set by hand:', 'logpilot' ) . '</p>';
        echo '<pre class="e3p-logpilot-code">' . esc_html( e3p_logpilot_manual_lines( ! $active ) ) . '</pre>';
        e3p_logpilot_render_manual_help();
        echo '</div>';
        return;
    }

    // Anteprima: le righe che risulteranno nel file.
    // Accendendo, WP_DEBUG_LOG prenderà il percorso del log di LogPilot: l'anteprima lo mostra (senza creare nulla).
    $preview = e3p_logpilot_config_apply( $src, ! $active, $active ? null : e3p_logpilot_plan_log_location()['file'] );
    if ( '' !== $preview['error'] ) {
        echo '<p>' . esc_html( $texts['selfcheck']['text'] ) . '</p></div>';
        return;
    }
    $result_lines = array();
    foreach ( e3p_logpilot_config_names() as $name ) {
        $c = $preview['analysis']['constants'][ $name ];
        if ( ! empty( $c['found'] ) ) {
            $result_lines[] = $preview['analysis']['lines'][ $c['index'] ];
        }
    }

    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="e3p_logpilot_toggle" />';
    echo '<input type="hidden" name="e3p_logpilot_action" value="' . esc_attr( $active ? 'off' : 'on' ) . '" />';
    wp_nonce_field( 'e3p_logpilot_toggle' );

    // Acceso: cosa è pianificato. Spento: la scelta dello spegnimento automatico, prima di accendere.
    if ( $active ) {
        e3p_logpilot_render_schedule_status( $texts );
    } else {
        e3p_logpilot_render_schedule_fields();
    }

    echo '<p><label><input type="checkbox" name="e3p_logpilot_confirm" value="1" required /> ';
    echo esc_html__( 'Yes, change the setting for me (LogPilot edits wp-config.php and keeps a safety copy).', 'logpilot' ) . '</label></p>';
    echo '<p><button type="submit" class="button button-primary">' . esc_html( $active ? __( 'Turn logging off', 'logpilot' ) : __( 'Turn logging on', 'logpilot' ) ) . '</button></p>';

    // Dettagli per chi li vuole: le righe che finiranno nel file e le garanzie.
    echo '<details class="e3p-logpilot-details"><summary><span class="dashicons dashicons-editor-code" aria-hidden="true"></span>' . esc_html__( 'Technical details', 'logpilot' ) . '</summary>';
    echo '<p>' . esc_html__( 'After the change, these lines will be in wp-config.php:', 'logpilot' ) . '</p>';
    echo '<pre class="e3p-logpilot-code">' . ( $result_lines ? esc_html( implode( "\n", $result_lines ) ) : esc_html__( '(no debug lines)', 'logpilot' ) ) . '</pre>';
    echo '<p class="description">' . esc_html__( 'WP_DEBUG_DISPLAY is always kept off, so errors are never shown on the pages of your site. When you turn logging on, a copy of wp-config.php as it was before is saved as wp-config-logpilot-backup.php in the same folder.', 'logpilot' ) . '</p>';
    echo '</details>';
    echo '</form>';

    // Dove si salva il log, controllo di protezione e vecchio debug.log (ha i suoi moduli: sta fuori da questo).
    e3p_logpilot_render_location_section( $active );
    echo '</div>';
}

/**
 * Elenco dei motivi per cui wp-config.php non è nella forma riconosciuta.
 *
 * @param array $problems Problemi dell'analisi.
 */
function e3p_logpilot_render_problems( $problems ) {
    echo '<div class="e3p-logpilot-problems"><p><span class="dashicons dashicons-warning" aria-hidden="true"></span> ';
    echo esc_html__( 'LogPilot did not change anything: wp-config.php is not written in a form it can safely edit.', 'logpilot' ) . '</p><ul>';
    foreach ( $problems as $problem ) {
        echo '<li>';
        switch ( $problem['code'] ) {
            case 'syntax':
                echo esc_html__( 'The file has a PHP syntax error.', 'logpilot' );
                break;
            case 'duplicate':
                printf(
                    /* translators: %s: constant name */
                    esc_html__( '%s is defined more than once.', 'logpilot' ),
                    '<code>' . esc_html( $problem['name'] ) . '</code>'
                );
                break;
            case 'anchor':
                echo esc_html__( 'The line "That\'s all, stop editing!" was not found.', 'logpilot' );
                break;
            default:
                printf(
                    /* translators: 1: constant name, 2: line number */
                    esc_html__( '%1$s (line %2$s) is inside a condition, shares a line with other code, or has a value other than true or false.', 'logpilot' ),
                    '<code>' . esc_html( $problem['name'] ) . '</code>',
                    esc_html( (string) (int) $problem['line'] )
                );
        }
        echo '</li>';
    }
    echo '</ul></div>';
}
