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
function e3p_logbeacon_get_result_texts() {
    return array(
        'on_ok'        => array(
            'type' => 'success',
            'text' => __( 'Debug logging is now on. Remember to turn it off when you are done: logs can contain sensitive data.', 'logbeacon' ),
        ),
        'off_ok'       => array(
            'type' => 'success',
            'text' => __( 'Debug logging is now off.', 'logbeacon' ),
        ),
        'multisite'    => array(
            'type' => 'error',
            'text' => __( 'Nothing was changed: this site is part of a network, and wp-config.php is shared by every site in it.', 'logbeacon' ),
        ),
        'confirm'      => array(
            'type' => 'warning',
            'text' => __( 'Nothing was changed: tick the confirmation box first.', 'logbeacon' ),
        ),
        'logdir'       => array(
            'type' => 'error',
            'text' => __( 'Nothing was changed: LogBeacon could not create the folder where the log is saved.', 'logbeacon' ),
        ),
        'confirm_delete' => array(
            'type' => 'warning',
            'text' => __( 'Nothing was deleted: tick the confirmation box first.', 'logbeacon' ),
        ),
        'old_deleted'  => array(
            'type' => 'success',
            'text' => __( 'The old wp-content/debug.log was deleted.', 'logbeacon' ),
        ),
        'old_failed'   => array(
            'type' => 'error',
            'text' => __( 'The old wp-content/debug.log could not be deleted. Check its permissions or delete it by hand.', 'logbeacon' ),
        ),
        'old_none'     => array(
            'type' => 'info',
            'text' => __( 'There was no wp-content/debug.log to delete.', 'logbeacon' ),
        ),
        'email_sent'      => array(
            'type' => 'success',
            'text' => __( 'The excerpt was handed over to your site’s mail system. If it does not arrive, check the spam folder and your site’s e-mail setup.', 'logbeacon' ),
        ),
        'email_failed'    => array(
            'type' => 'error',
            'text' => __( 'The e-mail could not be sent, so nothing was delivered. Most often the site cannot send e-mail: this is normal on a local test site, and on a live site an SMTP plugin or your host’s e-mail settings usually fix it. You can still download the log instead.', 'logbeacon' ),
        ),
        'email_invalid'   => array(
            'type' => 'error',
            'text' => __( 'Nothing was sent: enter a valid e-mail address.', 'logbeacon' ),
        ),
        'email_consent'   => array(
            'type' => 'warning',
            'text' => __( 'Nothing was sent: tick the confirmation box first.', 'logbeacon' ),
        ),
        'email_none'      => array(
            'type' => 'error',
            'text' => __( 'Nothing was sent: that log is not available or has nothing to send.', 'logbeacon' ),
        ),
        'download_none'   => array(
            'type' => 'error',
            'text' => __( 'Nothing was downloaded: that log is not available.', 'logbeacon' ),
        ),
        'download_failed' => array(
            'type' => 'error',
            'text' => __( 'The log could not be read, so nothing was downloaded. Check its permissions.', 'logbeacon' ),
        ),
        'email'        => array(
            'type' => 'error',
            'text' => __( 'Nothing was changed: enter a valid e-mail address for the reminder, or choose an automatic turn-off time.', 'logbeacon' ),
        ),
        'unrecognized' => array(
            'type' => 'error',
            'text' => __( 'Nothing was changed: wp-config.php is not written in a form LogBeacon recognizes.', 'logbeacon' ),
        ),
        'selfcheck'    => array(
            'type' => 'error',
            'text' => __( 'Nothing was changed: LogBeacon could not produce a safe version of wp-config.php.', 'logbeacon' ),
        ),
        'read'         => array(
            'type' => 'error',
            'text' => __( 'Nothing was changed: wp-config.php could not be read.', 'logbeacon' ),
        ),
        'not_writable' => array(
            'type' => 'error',
            'text' => __( 'Nothing was changed: wp-config.php (or its folder) is not writable.', 'logbeacon' ),
        ),
        'syntax'       => array(
            'type' => 'error',
            'text' => __( 'Nothing was changed: the new wp-config.php would have a syntax error.', 'logbeacon' ),
        ),
        'write'        => array(
            'type' => 'error',
            'text' => __( 'The new wp-config.php could not be written. The original file was kept.', 'logbeacon' ),
        ),
        'verify'       => array(
            'type' => 'error',
            'text' => __( 'The written file did not pass the final check, so the original wp-config.php was restored to how it was.', 'logbeacon' ),
        ),
    );
}

/**
 * Gestisce il pulsante (admin-post): solo la capacità del plugin, nonce, conferma esplicita. Su multisito non modifica nulla.
 */
function e3p_logbeacon_handle_toggle() {
    if ( ! current_user_can( e3p_logbeacon_capability() ) ) {
        wp_die( esc_html__( 'You do not have permission to do this.', 'logbeacon' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'e3p_logbeacon_toggle' );

    $on     = isset( $_POST['e3p_logbeacon_action'] ) && 'on' === sanitize_key( wp_unslash( $_POST['e3p_logbeacon_action'] ) );
    $result = '';

    // Scelta di spegnimento automatico o promemoria (solo all'accensione). Se non è valida non si cambia nulla.
    $choice = $on ? e3p_logbeacon_read_choice() : array();

    if ( is_multisite() ) {
        $result = 'multisite';
    } elseif ( empty( $_POST['e3p_logbeacon_confirm'] ) ) {
        $result = 'confirm';
    } elseif ( isset( $choice['error'] ) ) {
        $result = $choice['error'];
    } else {
        $path = e3p_logbeacon_config_path();
        $src  = ( '' !== $path && is_readable( $path ) ) ? file_get_contents( $path ) : false;
        if ( false === $src ) {
            $result = 'read';
        } else {
            // Accendendo, il log va in una cartella con nome non indovinabile (#41). Si prepara prima di scrivere
            // wp-config.php, così se non si riesce non si cambia nulla.
            $loc      = null;
            $log_path = null;
            if ( $on ) {
                $loc = e3p_logbeacon_setup_log_location();
                if ( null === $loc ) {
                    $result = 'logdir';
                } else {
                    $log_path = $loc['file'];
                }
            }

            $applied = ( '' === $result ) ? e3p_logbeacon_config_apply( $src, $on, $log_path ) : array( 'error' => $result );
            if ( '' !== $applied['error'] ) {
                $result = $applied['error'];
            } else {
                $result = e3p_logbeacon_config_write( $path, $applied['src'] );
                if ( '' === $result ) {
                    $result = $on ? 'on_ok' : 'off_ok';
                    // Acceso: si pianifica lo spegnimento o il promemoria, e si controlla se i log si possono
                    // scaricare dal web. Spento: si toglie ogni pianificazione.
                    if ( $on ) {
                        e3p_logbeacon_start_schedule( $choice );
                        $checks = e3p_logbeacon_run_checks( $loc );
                    } else {
                        e3p_logbeacon_clear_schedule();
                    }
                }
            }
        }
    }

    $args = array(
        'page'            => 'logbeacon',
        'e3p_logbeacon_rs' => $result,
    );
    if ( isset( $checks ) ) {
        $args['e3p_logbeacon_pr'] = $checks['folder'];
        $args['e3p_logbeacon_od'] = $checks['old'];
    }
    wp_safe_redirect( add_query_arg( $args, admin_url( 'tools.php' ) ) );
    exit;
}
add_action( 'admin_post_e3p_logbeacon_toggle', 'e3p_logbeacon_handle_toggle' );

/**
 * Righe da mettere a mano in wp-config.php, per accendere o per spegnere.
 *
 * @param bool $on True per accendere.
 * @return string
 */
function e3p_logbeacon_manual_lines( $on ) {
    $value = $on ? 'true' : 'false';
    return "define( 'WP_DEBUG', " . $value . " );\n"
        . "define( 'WP_DEBUG_LOG', " . $value . " );\n"
        . "define( 'WP_DEBUG_DISPLAY', false );";
}

/**
 * Spiegazione per chi non può usare il pulsante perché wp-config.php non si legge o non si scrive:
 * perché succede e a chi rivolgersi per sistemarlo.
 */
function e3p_logbeacon_render_manual_help() {
    echo '<p class="description">' . esc_html__( 'This usually happens when the file is set to read-only, belongs to another user on the server, or your hosting provider locks it for security. Nothing is wrong with your site.', 'logbeacon' ) . '</p>';
    echo '<p>' . esc_html__( 'Who can help:', 'logbeacon' ) . '</p><ul class="e3p-logbeacon-help-list">';
    echo '<li>' . esc_html__( 'Your hosting provider’s support: they can change the file permissions or add the lines for you.', 'logbeacon' ) . '</li>';
    echo '<li>' . esc_html__( 'The file manager in your hosting control panel, or an SFTP program: open wp-config.php, add the lines above the line “That’s all, stop editing!”, and save.', 'logbeacon' ) . '</li>';
    echo '<li>' . esc_html__( 'The person who manages the site, if it is not you.', 'logbeacon' ) . '</li>';
    echo '</ul>';
}

/**
 * Blocco "come dovrebbe essere scritto perché il pulsante funzioni".
 *
 * @param bool $on True se il debug è acceso ora (si mostrano i valori da usare per tornare a spento).
 */
function e3p_logbeacon_render_expected_format( $on ) {
    echo '<p>' . esc_html__( 'For the button to work, these three lines must be written like this in wp-config.php: one per line, at the top level of the file (not inside an "if" block), above the line "That\'s all, stop editing!", and not defined anywhere else in the file.', 'logbeacon' ) . '</p>';
    echo '<pre class="e3p-logbeacon-code">' . esc_html( e3p_logbeacon_manual_lines( false ) ) . '</pre>';
    echo '<p class="description">' . esc_html__( 'This is the "off" state. After you fix the file, reload this page: the button will appear.', 'logbeacon' ) . '</p>';
    if ( $on ) {
        echo '<p class="description">' . esc_html__( 'Debug logging is on right now. To turn it off by hand, change true to false in the first two lines.', 'logbeacon' ) . '</p>';
    }
}

/**
 * Sezione "Debug logging" in cima alla pagina di LogBeacon.
 */
function e3p_logbeacon_render_debug_section() {
    $texts  = e3p_logbeacon_get_result_texts();
    $active = defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;

    echo '<div class="e3p-logbeacon-box e3p-logbeacon-friendly' . ( $active ? ' is-on' : '' ) . '">';
    echo '<div class="e3p-logbeacon-head"><span class="e3p-logbeacon-head-icon dashicons dashicons-search" aria-hidden="true"></span><div>';
    echo '<h2>' . esc_html__( 'Find your error - check your logs', 'logbeacon' ) . '</h2>';
    echo '<p class="e3p-logbeacon-subtitle">' . esc_html__( 'Use this box to turn logging on easily, and let WordPress write down what goes wrong.', 'logbeacon' ) . '</p>';
    echo '</div></div>';

    // Esito dell'ultima azione: solo codici noti.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $code = isset( $_GET['e3p_logbeacon_rs'] ) ? sanitize_key( wp_unslash( $_GET['e3p_logbeacon_rs'] ) ) : '';
    // Gli esiti di "scarica" ed "email" compaiono nella riga delle azioni, accanto ai pulsanti premuti.
    if ( isset( $texts[ $code ] ) && ! e3p_logbeacon_is_action_result( $code ) ) {
        printf(
            '<div class="notice notice-%1$s inline"><p>%2$s</p></div>',
            esc_attr( $texts[ $code ]['type'] ),
            esc_html( $texts[ $code ]['text'] )
        );
    }

    echo '<p><span class="e3p-logbeacon-status"><span class="dashicons dashicons-' . ( $active ? 'yes-alt' : 'marker' ) . '" aria-hidden="true"></span>';
    echo esc_html( $active ? __( 'Logging is on', 'logbeacon' ) : __( 'Logging is off', 'logbeacon' ) );
    echo '</span></p>';
    echo '<p>' . esc_html(
        $active
            ? __( 'WordPress is writing the problems it meets into a log file, and you can read them below. When you are done, turn logging off: a log can contain private information.', 'logbeacon' )
            : __( 'To find out what is going wrong, turn logging on, repeat the action that causes the problem, then come back here to see what was written down.', 'logbeacon' )
    ) . '</p>';
    echo '<p class="description"><strong>' . esc_html__( 'This switch only controls the WordPress log (debug.log). The PHP error log belongs to your server: LogBeacon shows it below when it exists, but it cannot turn it on.', 'logbeacon' ) . '</strong></p>';

    // Su un multisito wp-config.php è comune a tutti i siti della rete: non si modifica da qui, si mostrano le righe a mano.
    if ( is_multisite() ) {
        echo '<p>' . esc_html__( 'This site is part of a network, and wp-config.php is shared by every site in it, so LogBeacon does not change it from here. To turn logging on or off for the whole network, these lines have to be set by hand:', 'logbeacon' ) . '</p>';
        echo '<pre class="e3p-logbeacon-code">' . esc_html( e3p_logbeacon_manual_lines( ! $active ) ) . '</pre>';
        e3p_logbeacon_render_manual_help();
        echo '</div>';
        return;
    }

    $path = e3p_logbeacon_config_path();
    $src  = ( '' !== $path && is_readable( $path ) ) ? file_get_contents( $path ) : false;

    if ( false === $src ) {
        echo '<p>' . esc_html__( 'LogBeacon could not read wp-config.php, so it cannot change it. To turn logging on or off, these lines have to be set by hand:', 'logbeacon' ) . '</p>';
        echo '<pre class="e3p-logbeacon-code">' . esc_html( e3p_logbeacon_manual_lines( ! $active ) ) . '</pre>';
        e3p_logbeacon_render_manual_help();
        echo '</div>';
        return;
    }

    $analysis = e3p_logbeacon_config_analyze( $src );

    if ( ! $analysis['ok'] ) {
        e3p_logbeacon_render_problems( $analysis['problems'] );
        e3p_logbeacon_render_expected_format( $active );
        echo '</div>';
        return;
    }

    $state = e3p_logbeacon_config_state( $analysis );

    // Avvisi su come erano le cose prima di LogBeacon.
    if ( $state['was_on'] ) {
        echo '<div class="e3p-logbeacon-problems"><p><span class="dashicons dashicons-warning" aria-hidden="true"></span> ';
        echo esc_html(
            $active
                ? __( 'Warning: debug logging was already on in wp-config.php before LogBeacon changed it. If you turn it off here, LogBeacon sets it to false.', 'logbeacon' )
                : __( 'Note: debug logging was already on in wp-config.php before LogBeacon first changed it. LogBeacon has set it to false, so it is off now.', 'logbeacon' )
        );
        echo '</p></div>';
    } elseif ( $state['unmanaged_on'] ) {
        echo '<div class="e3p-logbeacon-problems"><p><span class="dashicons dashicons-warning" aria-hidden="true"></span> ';
        echo esc_html__( 'Warning: debug logging was turned on in wp-config.php, not by LogBeacon. If you turn it off here, LogBeacon sets WP_DEBUG and WP_DEBUG_LOG to false in that file.', 'logbeacon' );
        echo '</p></div>';
    }

    if ( ! is_writable( $path ) || ! is_writable( dirname( $path ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Checks real write access to wp-config.php or the log folder before changing anything.
        echo '<p>' . esc_html__( 'wp-config.php (or its folder) is not writable, so LogBeacon cannot change it. To turn logging on or off, these lines have to be set by hand:', 'logbeacon' ) . '</p>';
        echo '<pre class="e3p-logbeacon-code">' . esc_html( e3p_logbeacon_manual_lines( ! $active ) ) . '</pre>';
        e3p_logbeacon_render_manual_help();
        echo '</div>';
        return;
    }

    // Anteprima: le righe che risulteranno nel file.
    // Accendendo, WP_DEBUG_LOG prenderà il percorso del log di LogBeacon: l'anteprima lo mostra (senza creare nulla).
    $preview = e3p_logbeacon_config_apply( $src, ! $active, $active ? null : e3p_logbeacon_plan_log_location()['file'] );
    if ( '' !== $preview['error'] ) {
        echo '<p>' . esc_html( $texts['selfcheck']['text'] ) . '</p></div>';
        return;
    }
    $result_lines = array();
    foreach ( e3p_logbeacon_config_names() as $name ) {
        $c = $preview['analysis']['constants'][ $name ];
        if ( ! empty( $c['found'] ) ) {
            $result_lines[] = $preview['analysis']['lines'][ $c['index'] ];
        }
    }

    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="e3p_logbeacon_toggle" />';
    echo '<input type="hidden" name="e3p_logbeacon_action" value="' . esc_attr( $active ? 'off' : 'on' ) . '" />';
    wp_nonce_field( 'e3p_logbeacon_toggle' );

    // Acceso: cosa è pianificato. Spento: la scelta dello spegnimento automatico, prima di accendere.
    if ( $active ) {
        e3p_logbeacon_render_schedule_status( $texts );
    } else {
        e3p_logbeacon_render_schedule_fields();
    }

    echo '<p><label><input type="checkbox" name="e3p_logbeacon_confirm" value="1" required /> ';
    echo esc_html__( 'Yes, change the setting for me (LogBeacon edits wp-config.php, checks it and restores it if anything goes wrong).', 'logbeacon' ) . '</label></p>';
    echo '<p><button type="submit" class="button button-primary">' . esc_html( $active ? __( 'Turn logging off', 'logbeacon' ) : __( 'Turn logging on', 'logbeacon' ) ) . '</button></p>';

    // Dettagli per chi li vuole: le righe che finiranno nel file e le garanzie.
    echo '<details class="e3p-logbeacon-details"><summary><span class="dashicons dashicons-editor-code" aria-hidden="true"></span>' . esc_html__( 'Technical details', 'logbeacon' ) . '</summary>';
    echo '<p>' . esc_html__( 'After the change, these lines will be in wp-config.php:', 'logbeacon' ) . '</p>';
    echo '<pre class="e3p-logbeacon-code">' . ( $result_lines ? esc_html( implode( "\n", $result_lines ) ) : esc_html__( '(no debug lines)', 'logbeacon' ) ) . '</pre>';
    echo '<p class="description">' . esc_html__( 'WP_DEBUG_DISPLAY is always kept off, so errors are never shown on the pages of your site.', 'logbeacon' ) . '</p>';
    echo '</details>';
    echo '</form>';

    // Dove si salva il log, controllo di protezione e vecchio debug.log (ha i suoi moduli: sta fuori da questo).
    e3p_logbeacon_render_location_section( $active );
    echo '</div>';
}

/**
 * Elenco dei motivi per cui wp-config.php non è nella forma riconosciuta.
 *
 * @param array $problems Problemi dell'analisi.
 */
function e3p_logbeacon_render_problems( $problems ) {
    echo '<div class="e3p-logbeacon-problems"><p><span class="dashicons dashicons-warning" aria-hidden="true"></span> ';
    echo esc_html__( 'LogBeacon did not change anything: wp-config.php is not written in a form it can safely edit.', 'logbeacon' ) . '</p><ul>';
    foreach ( $problems as $problem ) {
        echo '<li>';
        switch ( $problem['code'] ) {
            case 'syntax':
                echo esc_html__( 'The file has a PHP syntax error.', 'logbeacon' );
                break;
            case 'duplicate':
                printf(
                    /* translators: %s: constant name */
                    esc_html__( '%s is defined more than once.', 'logbeacon' ),
                    '<code>' . esc_html( $problem['name'] ) . '</code>'
                );
                break;
            case 'anchor':
                echo esc_html__( 'The line "That\'s all, stop editing!" was not found.', 'logbeacon' );
                break;
            default:
                // Una sola causa per riga, quella che l'analisi ha trovato, e il testo della riga com'è scritta.
                $reasons = array(
                    /* translators: 1: constant name, 2: line number */
                    'nested'    => __( '%1$s (line %2$s) is inside a block of code, such as an “if”. LogBeacon cannot know which value would apply, so it does not touch it.', 'logbeacon' ),
                    /* translators: 1: constant name, 2: line number */
                    'notdefine' => __( '%1$s (line %2$s) is not a plain define() line: for example it follows an “if”, or it only checks whether the constant exists.', 'logbeacon' ),
                    /* translators: 1: constant name, 2: line number */
                    'value'     => __( '%1$s (line %2$s) has a value other than true or false.', 'logbeacon' ),
                    /* translators: 1: constant name, 2: line number */
                    'sameline'  => __( '%1$s (line %2$s) shares a line with other code.', 'logbeacon' ),
                    /* translators: 1: constant name, 2: line number */
                    'path'      => __( '%1$s (line %2$s) points to a log path that LogBeacon did not create, so it is left as it is.', 'logbeacon' ),
                    /* translators: 1: constant name, 2: line number */
                    'format'    => __( '%1$s (line %2$s) is written in a way LogBeacon does not recognize.', 'logbeacon' ),
                );
                $why = isset( $problem['reason'], $reasons[ $problem['reason'] ] ) ? $reasons[ $problem['reason'] ] : $reasons['format'];
                printf(
                    esc_html( $why ),
                    '<code>' . esc_html( $problem['name'] ) . '</code>',
                    esc_html( (string) (int) $problem['line'] )
                );
                if ( ! empty( $problem['text'] ) ) {
                    echo '<br /><code class="e3p-logbeacon-problem-line">' . esc_html( $problem['text'] ) . '</code>';
                }
        }
        echo '</li>';
    }
    echo '</ul></div>';
}
