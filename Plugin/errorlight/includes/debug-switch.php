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
function e3p_errorlight_get_result_texts() {
    return array(
        'on_ok'        => array(
            'type' => 'success',
            'text' => __( 'Debug logging is now on. Remember to turn it off when you are done: logs can contain sensitive data.', 'errorlight' ),
        ),
        'off_ok'       => array(
            'type' => 'success',
            'text' => __( 'Debug logging is now off.', 'errorlight' ),
        ),
        'multisite'    => array(
            'type' => 'error',
            'text' => __( 'Nothing was changed: this site is part of a network, and wp-config.php is shared by every site in it.', 'errorlight' ),
        ),
        'confirm'      => array(
            'type' => 'warning',
            'text' => __( 'Nothing was changed: tick the confirmation box first.', 'errorlight' ),
        ),
        'logdir'       => array(
            'type' => 'error',
            'text' => __( 'Nothing was changed: ErrorLight could not create the folder where the log is saved.', 'errorlight' ),
        ),
        'email_sent'      => array(
            'type' => 'success',
            'text' => __( 'The excerpt was handed over to your site’s mail system. If it does not arrive, check the spam folder and your site’s e-mail setup.', 'errorlight' ),
        ),
        'email_failed'    => array(
            'type' => 'error',
            'text' => __( 'The e-mail could not be sent, so nothing was delivered. Most often the site cannot send e-mail: this is normal on a local test site, and on a live site an SMTP plugin or your host’s e-mail settings usually fix it. You can still download the log instead.', 'errorlight' ),
        ),
        'email_invalid'   => array(
            'type' => 'error',
            'text' => __( 'Nothing was sent: enter a valid e-mail address.', 'errorlight' ),
        ),
        'email_consent'   => array(
            'type' => 'warning',
            'text' => __( 'Nothing was sent: tick the confirmation box first.', 'errorlight' ),
        ),
        'email_none'      => array(
            'type' => 'error',
            'text' => __( 'Nothing was sent: that log is not available or has nothing to send.', 'errorlight' ),
        ),
        'download_none'   => array(
            'type' => 'error',
            'text' => __( 'Nothing was downloaded: that log is not available.', 'errorlight' ),
        ),
        'download_failed' => array(
            'type' => 'error',
            'text' => __( 'The log could not be read, so nothing was downloaded. Check its permissions.', 'errorlight' ),
        ),
        'email'        => array(
            'type' => 'error',
            'text' => __( 'Nothing was changed: enter a valid e-mail address for the reminder, or choose an automatic turn-off time.', 'errorlight' ),
        ),
        'unrecognized' => array(
            'type' => 'error',
            'text' => __( 'Nothing was changed: wp-config.php is not written in a form ErrorLight recognizes.', 'errorlight' ),
        ),
        'selfcheck'    => array(
            'type' => 'error',
            'text' => __( 'Nothing was changed: ErrorLight could not produce a safe version of wp-config.php.', 'errorlight' ),
        ),
        'read'         => array(
            'type' => 'error',
            'text' => __( 'Nothing was changed: wp-config.php could not be read.', 'errorlight' ),
        ),
        'not_writable' => array(
            'type' => 'error',
            'text' => __( 'Nothing was changed: wp-config.php is not writable.', 'errorlight' ),
        ),
        'syntax'       => array(
            'type' => 'error',
            'text' => __( 'Nothing was changed: the new wp-config.php would have a syntax error.', 'errorlight' ),
        ),
        'write'        => array(
            'type' => 'error',
            'text' => __( 'The new wp-config.php could not be written. The original file was kept.', 'errorlight' ),
        ),
        'verify'       => array(
            'type' => 'error',
            'text' => __( 'The written file did not pass the final check, so the original wp-config.php was restored to how it was.', 'errorlight' ),
        ),
        'restore_failed' => array(
            'type' => 'error',
            'text' => __( 'The written file did not pass the final check and the original wp-config.php could not be restored with certainty. Please open wp-config.php and check that it is complete; your hosting provider can restore it from a backup.', 'errorlight' ),
        ),
        'exposed'      => array(
            'type' => 'error',
            'text' => __( 'Nothing was changed: the log folder can be opened from a web address on your server, so the logs would not be private. Ask your hosting provider to block web access to the uploads/errorlight folder, then try again.', 'errorlight' ),
        ),
        'unverified'   => array(
            'type' => 'warning',
            'text' => __( 'Nothing was changed, and your site is not at risk. For your safety ErrorLight turns logging on only when it can confirm that the logs cannot be downloaded from the web, and on your server that check could not be completed.', 'errorlight' ),
        ),
        'busy'         => array(
            'type' => 'warning',
            'text' => __( 'Nothing was changed: wp-config.php was being changed by another request or had just changed. Please try again.', 'errorlight' ),
        ),
    );
}

/**
 * Gestisce il pulsante (admin-post): solo la capacità del plugin, nonce, conferma esplicita. Su multisito non modifica nulla.
 */
function e3p_errorlight_handle_toggle() {
    if ( ! current_user_can( e3p_errorlight_capability() ) ) {
        wp_die( esc_html__( 'You do not have permission to do this.', 'errorlight' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'e3p_errorlight_toggle' );

    $on     = isset( $_POST['e3p_errorlight_action'] ) && 'on' === sanitize_key( wp_unslash( $_POST['e3p_errorlight_action'] ) );
    $result = '';

    // Scelta di spegnimento automatico o promemoria (solo all'accensione). Se non è valida non si cambia nulla.
    $choice = $on ? e3p_errorlight_read_choice() : array();

    if ( is_multisite() ) {
        $result = 'multisite';
    } elseif ( empty( $_POST['e3p_errorlight_confirm'] ) ) {
        $result = 'confirm';
    } elseif ( isset( $choice['error'] ) ) {
        $result = $choice['error'];
    } else {
        $path = e3p_errorlight_config_path();
        $src  = ( '' !== $path && is_readable( $path ) ) ? file_get_contents( $path ) : false;
        if ( false === $src ) {
            $result = 'read';
        } else {
            // Accendendo, il log va in una cartella con nome non indovinabile (#41). Si prepara prima di scrivere
            // wp-config.php, così se non si riesce non si cambia nulla.
            $loc      = null;
            $log_path = null;
            if ( $on ) {
                $loc = e3p_errorlight_setup_log_location();
                if ( null === $loc ) {
                    $result = 'logdir';
                } else {
                    $log_path = $loc['file'];
                    // Si accende solo se il controllo conferma che la cartella dei log non è scaricabile. Se lo è
                    // (exposed) o se non si riesce a verificare (unknown), su qualsiasi server, non si accende.
                    $checks = e3p_errorlight_run_checks( $loc );
                    if ( 'exposed' === $checks['folder'] ) {
                        $result = 'exposed';
                    } elseif ( 'protected' !== $checks['folder'] ) {
                        $result = 'unverified';
                    }
                }
            }

            $applied = ( '' === $result ) ? e3p_errorlight_config_apply( $src, $on, $log_path ) : array( 'error' => $result );
            if ( '' !== $applied['error'] ) {
                $result = $applied['error'];
            } else {
                $result = e3p_errorlight_config_write( $path, $applied['src'], $src );
                if ( '' === $result ) {
                    $result = $on ? 'on_ok' : 'off_ok';
                    // Acceso: si pianifica lo spegnimento o il promemoria, e si controlla se i log si possono
                    // scaricare dal web. Spento: si toglie ogni pianificazione.
                    if ( $on ) {
                        e3p_errorlight_start_schedule( $choice );
                    } else {
                        e3p_errorlight_clear_schedule();
                    }
                }
            }
        }
    }

    $args = array(
        'page'            => 'errorlight',
        'e3p_errorlight_rs' => $result,
    );
    if ( isset( $checks ) ) {
        $args['e3p_errorlight_pr'] = $checks['folder'];
        $args['e3p_errorlight_od'] = $checks['old'];
        if ( '' !== $checks['why'] ) {
            $args['e3p_errorlight_wh'] = $checks['why'];
        }
    }
    wp_safe_redirect( add_query_arg( $args, admin_url( 'tools.php' ) ) . '#e3p-errorlight-debug' );
    exit;
}
add_action( 'admin_post_e3p_errorlight_toggle', 'e3p_errorlight_handle_toggle' );

/**
 * Righe da mettere a mano in wp-config.php, per accendere o per spegnere.
 *
 * @param bool $on True per accendere.
 * @return string
 */
function e3p_errorlight_manual_lines( $on ) {
    $value = $on ? 'true' : 'false';
    return "define( 'WP_DEBUG', " . $value . " );\n"
        . "define( 'WP_DEBUG_LOG', " . $value . " );\n"
        . "define( 'WP_DEBUG_DISPLAY', false );";
}

/**
 * Spiegazione per chi non può usare il pulsante perché wp-config.php non si legge o non si scrive:
 * perché succede e a chi rivolgersi per sistemarlo.
 */
function e3p_errorlight_render_manual_help() {
    echo '<p class="description">' . esc_html__( 'This usually happens when the file is set to read-only, belongs to another user on the server, or your hosting provider locks it for security. Nothing is wrong with your site.', 'errorlight' ) . '</p>';
    echo '<p>' . esc_html__( 'Who can help:', 'errorlight' ) . '</p><ul class="e3p-errorlight-help-list">';
    echo '<li>' . esc_html__( 'Your hosting provider’s support: they can change the file permissions or add the lines for you.', 'errorlight' ) . '</li>';
    echo '<li>' . esc_html__( 'The file manager in your hosting control panel, or an SFTP program: open wp-config.php, add the lines above the line “That’s all, stop editing!”, and save.', 'errorlight' ) . '</li>';
    echo '<li>' . esc_html__( 'The person who manages the site, if it is not you.', 'errorlight' ) . '</li>';
    echo '</ul>';
}

/**
 * Riquadro al posto del pulsante quando la protezione della cartella dei log non è confermata: cosa ha visto il
 * controllo, cosa può fare l'utente, un testo da inoltrare all'hosting e le righe da scrivere a mano.
 *
 * @param string $why     Codice del motivo (vuoto se non noto); si accettano solo i codici di e3p_errorlight_get_why_texts().
 * @param bool   $exposed True se il controllo ha scaricato il file di prova (la cartella è aperta).
 */
function e3p_errorlight_render_blocked_help( $why, $exposed ) {
    $whys = e3p_errorlight_get_why_texts();

    echo '<div class="e3p-errorlight-problems"><p><span class="dashicons dashicons-warning" aria-hidden="true"></span> ';
    echo esc_html__( 'The “Turn logging on” button is not available right now. Logs can contain private data, so ErrorLight enables logging only after it has confirmed that the log folder cannot be opened from a web address. Nothing was changed.', 'errorlight' );
    echo '</p>';
    if ( $exposed ) {
        echo '<p>' . esc_html( e3p_errorlight_get_check_texts()['folder']['exposed']['text'] ) . '</p>';
    } elseif ( isset( $whys[ $why ] ) ) {
        echo '<p>' . esc_html__( 'What ErrorLight saw:', 'errorlight' ) . ' ' . esc_html( $whys[ $why ] ) . '</p>';
    }
    echo '</div>';

    echo '<p>' . esc_html__( 'This depends on how your server is set up, and it can usually be fixed:', 'errorlight' ) . '</p>';
    echo '<ul class="e3p-errorlight-help-list">';
    echo '<li>' . esc_html__( 'Ask your hosting provider to allow your site to send requests to its own address (many hosts block them), or to block web access to the uploads/errorlight folder. You can copy the message below.', 'errorlight' ) . '</li>';
    echo '<li>' . esc_html__( 'If the site is behind a password, a firewall or a security service, allow requests from the server itself. WordPress reports the same problem in Tools → Site Health.', 'errorlight' ) . '</li>';
    echo '<li>' . esc_html__( 'When it is done, press “Check again”: if the check succeeds, the button comes back.', 'errorlight' ) . '</li>';
    echo '</ul>';

    echo '<details class="e3p-errorlight-details" open><summary><span class="dashicons dashicons-email" aria-hidden="true"></span>' . esc_html__( 'Message for your hosting provider', 'errorlight' ) . '</summary>';
    echo '<pre class="e3p-errorlight-code">' . esc_html__( "Hello, a WordPress plugin (ErrorLight) needs two things on my site:\n1. Requests from the server to my own site address (loopback HTTP requests) must be allowed.\n2. Web access to the folder wp-content/uploads/errorlight/ and all its subfolders must be denied (for example \"deny all\" in the server configuration).\nCould you check or enable them? Thank you.", 'errorlight' ) . '</pre>';
    echo '</details>';

    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="e3p-errorlight-inline-form">';
    echo '<input type="hidden" name="action" value="e3p_errorlight_check" />';
    wp_nonce_field( 'e3p_errorlight_check' );
    echo '<button type="submit" class="button e3p-errorlight-iconbtn"><span class="dashicons dashicons-update" aria-hidden="true"></span>' . esc_html__( 'Check again', 'errorlight' ) . '</button>';
    echo '</form>';

    echo '<details class="e3p-errorlight-details"><summary><span class="dashicons dashicons-editor-code" aria-hidden="true"></span>' . esc_html__( 'Or turn it on by hand', 'errorlight' ) . '</summary>';
    echo '<p>' . esc_html__( 'You can add these lines to wp-config.php yourself, above the line “That’s all, stop editing!”. Without the protected folder, WordPress saves the log in wp-content/debug.log, which is the first address anyone would try: turn logging off as soon as you are done.', 'errorlight' ) . '</p>';
    echo '<pre class="e3p-errorlight-code">' . esc_html( e3p_errorlight_manual_lines( true ) ) . '</pre>';
    echo '</details>';
}

/**
 * Blocco "come dovrebbe essere scritto perché il pulsante funzioni".
 *
 * @param bool $on True se il debug è acceso ora (si mostrano i valori da usare per tornare a spento).
 */
function e3p_errorlight_render_expected_format( $on ) {
    echo '<p>' . esc_html__( 'For the button to work, these three lines must be written like this in wp-config.php: one per line, at the top level of the file (not inside an "if" block), above the line "That\'s all, stop editing!", and not defined anywhere else in the file.', 'errorlight' ) . '</p>';
    echo '<pre class="e3p-errorlight-code">' . esc_html( e3p_errorlight_manual_lines( false ) ) . '</pre>';
    echo '<p class="description">' . esc_html__( 'This is the "off" state. After you fix the file, reload this page: the button will appear.', 'errorlight' ) . '</p>';
    if ( $on ) {
        echo '<p class="description">' . esc_html__( 'Debug logging is on right now. To turn it off by hand, change true to false in the first two lines.', 'errorlight' ) . '</p>';
    }
}

/**
 * Sezione "Debug logging" in cima alla pagina di ErrorLight.
 */
function e3p_errorlight_render_debug_section() {
    $texts  = e3p_errorlight_get_result_texts();
    $active = defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;

    echo '<div id="e3p-errorlight-debug" class="e3p-errorlight-box e3p-errorlight-friendly' . ( $active ? ' is-on' : '' ) . '">';
    echo '<div class="e3p-errorlight-head"><span class="e3p-errorlight-head-icon dashicons dashicons-search" aria-hidden="true"></span><div>';
    echo '<h2>' . esc_html__( 'Find your error - check your logs', 'errorlight' ) . '</h2>';
    echo '<p class="e3p-errorlight-subtitle">' . esc_html__( 'Use this box to turn logging on easily, and let WordPress write down what goes wrong.', 'errorlight' ) . '</p>';
    echo '</div></div>';

    // Esito dell'ultima azione: solo codici noti.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $code = isset( $_GET['e3p_errorlight_rs'] ) ? sanitize_key( wp_unslash( $_GET['e3p_errorlight_rs'] ) ) : '';
    // Gli esiti di "scarica" ed "email" compaiono nella riga delle azioni, accanto ai pulsanti premuti.
    // "unverified" ed "exposed" non si ripetono qui: li spiega il riquadro che prende il posto del pulsante.
    $in_box = ! $active && in_array( $code, array( 'unverified', 'exposed' ), true );
    if ( isset( $texts[ $code ] ) && ! $in_box && ! e3p_errorlight_is_action_result( $code ) ) {
        printf(
            '<div class="notice notice-%1$s inline"><p>%2$s</p></div>',
            esc_attr( $texts[ $code ]['type'] ),
            esc_html( $texts[ $code ]['text'] )
        );
    }

    echo '<p><span class="e3p-errorlight-status"><span class="dashicons dashicons-' . ( $active ? 'yes-alt' : 'marker' ) . '" aria-hidden="true"></span>';
    echo esc_html( $active ? __( 'Logging is on', 'errorlight' ) : __( 'Logging is off', 'errorlight' ) );
    echo '</span></p>';
    echo '<p>' . esc_html(
        $active
            ? __( 'WordPress is writing the problems it meets into a log file, and you can read them below. When you are done, turn logging off: a log can contain private information.', 'errorlight' )
            : __( 'To find out what is going wrong, turn logging on, repeat the action that causes the problem, then come back here to see what was written down.', 'errorlight' )
    ) . '</p>';
    echo '<p class="description"><strong>' . esc_html__( 'This switch only controls the WordPress log (debug.log). The PHP error log belongs to your server: ErrorLight shows it below when it exists, but it cannot turn it on.', 'errorlight' ) . '</strong></p>';

    // Su un multisito wp-config.php è comune a tutti i siti della rete: non si modifica da qui, si mostrano le righe a mano.
    if ( is_multisite() ) {
        echo '<p>' . esc_html__( 'This site is part of a network, and wp-config.php is shared by every site in it, so ErrorLight does not change it from here. To turn logging on or off for the whole network, these lines have to be set by hand:', 'errorlight' ) . '</p>';
        echo '<pre class="e3p-errorlight-code">' . esc_html( e3p_errorlight_manual_lines( ! $active ) ) . '</pre>';
        e3p_errorlight_render_manual_help();
        echo '</div>';
        return;
    }

    $path = e3p_errorlight_config_path();
    $src  = ( '' !== $path && is_readable( $path ) ) ? file_get_contents( $path ) : false;

    if ( false === $src ) {
        echo '<p>' . esc_html__( 'ErrorLight could not read wp-config.php, so it cannot change it. To turn logging on or off, these lines have to be set by hand:', 'errorlight' ) . '</p>';
        echo '<pre class="e3p-errorlight-code">' . esc_html( e3p_errorlight_manual_lines( ! $active ) ) . '</pre>';
        e3p_errorlight_render_manual_help();
        echo '</div>';
        return;
    }

    $analysis = e3p_errorlight_config_analyze( $src );

    if ( ! $analysis['ok'] ) {
        e3p_errorlight_render_problems( $analysis['problems'] );
        e3p_errorlight_render_expected_format( $active );
        echo '</div>';
        return;
    }

    $state = e3p_errorlight_config_state( $analysis );

    // Avvisi su come erano le cose prima di ErrorLight.
    if ( $state['was_on'] ) {
        echo '<div class="e3p-errorlight-problems"><p><span class="dashicons dashicons-warning" aria-hidden="true"></span> ';
        echo esc_html(
            $active
                ? __( 'Warning: debug logging was already on in wp-config.php before ErrorLight changed it. If you turn it off here, ErrorLight sets it to false.', 'errorlight' )
                : __( 'Note: debug logging was already on in wp-config.php before ErrorLight first changed it. ErrorLight has set it to false, so it is off now.', 'errorlight' )
        );
        echo '</p></div>';
    } elseif ( $state['unmanaged_on'] ) {
        echo '<div class="e3p-errorlight-problems"><p><span class="dashicons dashicons-warning" aria-hidden="true"></span> ';
        echo esc_html__( 'Warning: debug logging was turned on in wp-config.php, not by ErrorLight. If you turn it off here, ErrorLight sets WP_DEBUG and WP_DEBUG_LOG to false in that file.', 'errorlight' );
        echo '</p></div>';
    }

    if ( ! is_writable( $path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Checks real write access to wp-config.php or the log folder before changing anything.
        echo '<p>' . esc_html__( 'wp-config.php is not writable, so ErrorLight cannot change it. To turn logging on or off, these lines have to be set by hand:', 'errorlight' ) . '</p>';
        echo '<pre class="e3p-errorlight-code">' . esc_html( e3p_errorlight_manual_lines( ! $active ) ) . '</pre>';
        e3p_errorlight_render_manual_help();
        echo '</div>';
        return;
    }

    // Anteprima: le righe che risulteranno nel file.
    // Accendendo, WP_DEBUG_LOG prenderà il percorso del log di ErrorLight: l'anteprima lo mostra (senza creare nulla).
    $preview = e3p_errorlight_config_apply( $src, ! $active, $active ? null : ( e3p_errorlight_plan_log_location()['file'] ?? null ) );
    if ( '' !== $preview['error'] ) {
        echo '<p>' . esc_html( $texts['selfcheck']['text'] ) . '</p></div>';
        return;
    }
    $result_lines = array();
    foreach ( e3p_errorlight_config_names() as $name ) {
        $c = $preview['analysis']['constants'][ $name ];
        if ( ! empty( $c['found'] ) ) {
            $result_lines[] = $preview['analysis']['lines'][ $c['index'] ];
        }
    }

    // Se l'ultimo tentativo o controllo non ha confermato la protezione, il pulsante non c'è: al suo posto la
    // spiegazione, cosa chiedere all'hosting e le righe da scrivere a mano. Torna dopo un controllo riuscito.
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $last_rs = isset( $_GET['e3p_errorlight_rs'] ) ? sanitize_key( wp_unslash( $_GET['e3p_errorlight_rs'] ) ) : '';
    $last_pr = isset( $_GET['e3p_errorlight_pr'] ) ? sanitize_key( wp_unslash( $_GET['e3p_errorlight_pr'] ) ) : '';
    $why     = isset( $_GET['e3p_errorlight_wh'] ) ? sanitize_key( wp_unslash( $_GET['e3p_errorlight_wh'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    if ( ! $active && ( in_array( $last_rs, array( 'unverified', 'exposed' ), true ) || in_array( $last_pr, array( 'unknown', 'exposed' ), true ) ) ) {
        e3p_errorlight_render_blocked_help( $why, 'exposed' === $last_rs || 'exposed' === $last_pr );
        e3p_errorlight_render_location_section( $active, true );
        echo '</div>';
        return;
    }

    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="e3p_errorlight_toggle" />';
    echo '<input type="hidden" name="e3p_errorlight_action" value="' . esc_attr( $active ? 'off' : 'on' ) . '" />';
    wp_nonce_field( 'e3p_errorlight_toggle' );

    // Acceso: cosa è pianificato. Spento: la scelta dello spegnimento automatico, prima di accendere.
    if ( $active ) {
        e3p_errorlight_render_schedule_status( $texts );
    } else {
        e3p_errorlight_render_schedule_fields();
    }

    echo '<p><label><input type="checkbox" name="e3p_errorlight_confirm" value="1" required /> ';
    echo esc_html__( 'Yes, change the setting for me (ErrorLight edits wp-config.php, checks it and restores it if anything goes wrong).', 'errorlight' ) . '</label></p>';
    echo '<p><button type="submit" class="button button-primary">' . esc_html( $active ? __( 'Turn logging off', 'errorlight' ) : __( 'Turn logging on', 'errorlight' ) ) . '</button></p>';

    // Dettagli per chi li vuole: le righe che finiranno nel file e le garanzie.
    echo '<details class="e3p-errorlight-details"><summary><span class="dashicons dashicons-editor-code" aria-hidden="true"></span>' . esc_html__( 'Technical details', 'errorlight' ) . '</summary>';
    echo '<p>' . esc_html__( 'After the change, these lines will be in wp-config.php:', 'errorlight' ) . '</p>';
    echo '<pre class="e3p-errorlight-code">' . ( $result_lines ? esc_html( implode( "\n", $result_lines ) ) : esc_html__( '(no debug lines)', 'errorlight' ) ) . '</pre>';
    echo '<p class="description">' . esc_html__( 'WP_DEBUG_DISPLAY is always kept off, so errors are never shown on the pages of your site.', 'errorlight' ) . '</p>';
    echo '</details>';
    echo '</form>';

    // Dove si salva il log, controllo di protezione e vecchio debug.log (ha i suoi moduli: sta fuori da questo).
    e3p_errorlight_render_location_section( $active );
    echo '</div>';
}

/**
 * Elenco dei motivi per cui wp-config.php non è nella forma riconosciuta.
 *
 * @param array $problems Problemi dell'analisi.
 */
function e3p_errorlight_render_problems( $problems ) {
    echo '<div class="e3p-errorlight-problems"><p><span class="dashicons dashicons-warning" aria-hidden="true"></span> ';
    echo esc_html__( 'ErrorLight did not change anything: wp-config.php is not written in a form it can safely edit.', 'errorlight' ) . '</p><ul>';
    foreach ( $problems as $problem ) {
        echo '<li>';
        switch ( $problem['code'] ) {
            case 'syntax':
                echo esc_html__( 'The file has a PHP syntax error.', 'errorlight' );
                break;
            case 'duplicate':
                printf(
                    /* translators: %s: constant name */
                    esc_html__( '%s is defined more than once.', 'errorlight' ),
                    '<code>' . esc_html( $problem['name'] ) . '</code>'
                );
                break;
            case 'anchor':
                echo esc_html__( 'The line "That\'s all, stop editing!" was not found.', 'errorlight' );
                break;
            default:
                // Una sola causa per riga, quella che l'analisi ha trovato, e il testo della riga com'è scritta.
                $reasons = array(
                    /* translators: 1: constant name, 2: line number */
                    'nested'    => __( '%1$s (line %2$s) is inside a block of code, such as an “if”. ErrorLight cannot know which value would apply, so it does not touch it.', 'errorlight' ),
                    /* translators: 1: constant name, 2: line number */
                    'notdefine' => __( '%1$s (line %2$s) is not a plain define() line: for example it follows an “if”, or it only checks whether the constant exists.', 'errorlight' ),
                    /* translators: 1: constant name, 2: line number */
                    'value'     => __( '%1$s (line %2$s) has a value other than true or false.', 'errorlight' ),
                    /* translators: 1: constant name, 2: line number */
                    'sameline'  => __( '%1$s (line %2$s) shares a line with other code.', 'errorlight' ),
                    /* translators: 1: constant name, 2: line number */
                    'path'      => __( '%1$s (line %2$s) points to a log path that ErrorLight did not create, so it is left as it is.', 'errorlight' ),
                    /* translators: 1: constant name, 2: line number */
                    'format'    => __( '%1$s (line %2$s) is written in a way ErrorLight does not recognize.', 'errorlight' ),
                );
                $why = isset( $problem['reason'], $reasons[ $problem['reason'] ] ) ? $reasons[ $problem['reason'] ] : $reasons['format'];
                printf(
                    esc_html( $why ),
                    '<code>' . esc_html( $problem['name'] ) . '</code>',
                    esc_html( (string) (int) $problem['line'] )
                );
                if ( ! empty( $problem['text'] ) ) {
                    echo '<br /><code class="e3p-errorlight-problem-line">' . esc_html( $problem['text'] ) . '</code>';
                }
        }
        echo '</li>';
    }
    echo '</ul></div>';
}
