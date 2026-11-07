<?php
/**
 * Spegnimento automatico del debug acceso dal pulsante, oppure promemoria periodico via email (#40).
 *
 * Scritture nel database (uniche di LogPilot): l'opzione `e3p_logpilot_settings` e gli eventi di WP-Cron
 * `e3p_logpilot_auto_off` e `e3p_logpilot_remind`. Si creano quando si accende dal pulsante e si tolgono quando
 * si spegne, alla disattivazione e in uninstall.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Durate proposte per lo spegnimento automatico (minuti => etichetta). La preselezionata è 1440 (24 ore).
 *
 * @return array<int, string>
 */
function e3p_logpilot_duration_choices() {
    return array(
        60    => __( '1 hour', 'logpilot' ),
        360   => __( '6 hours', 'logpilot' ),
        1440  => __( '24 hours', 'logpilot' ),
        4320  => __( '3 days', 'logpilot' ),
        10080 => __( '7 days', 'logpilot' ),
    );
}

/**
 * Intervalli proposti per il promemoria (giorni => etichetta).
 *
 * @return array<int, string>
 */
function e3p_logpilot_remind_choices() {
    return array(
        1 => __( 'every day', 'logpilot' ),
        3 => __( 'every 3 days', 'logpilot' ),
        7 => __( 'every 7 days', 'logpilot' ),
    );
}

/**
 * Impostazioni salvate, con i valori predefiniti. `mode` è vuoto, `auto` o `remind`.
 *
 * @return array{mode: string, since: int, off_at: int, days: int, email: string, failed: string}
 */
function e3p_logpilot_get_schedule() {
    $saved = get_option( 'e3p_logpilot_settings', array() );
    if ( ! is_array( $saved ) ) {
        $saved = array();
    }
    return wp_parse_args(
        $saved,
        array(
            'mode'   => '',
            'since'  => 0,
            'off_at' => 0,
            'days'   => 0,
            'email'  => '',
            'failed' => '',
        )
    );
}

/**
 * True se il debug è acceso adesso (WP_DEBUG e WP_DEBUG_LOG).
 *
 * @return bool
 */
function e3p_logpilot_debug_active() {
    return defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
}

/**
 * Toglie impostazioni ed eventi pianificati. Si chiama quando si spegne e alla disattivazione.
 */
function e3p_logpilot_clear_schedule() {
    wp_clear_scheduled_hook( 'e3p_logpilot_auto_off' );
    wp_clear_scheduled_hook( 'e3p_logpilot_remind' );
    delete_option( 'e3p_logpilot_settings' );
}

/**
 * Salva la scelta fatta all'accensione e pianifica l'evento.
 *
 * @param array{mode: string, minutes: int, days: int, email: string} $choice Scelta già controllata.
 */
function e3p_logpilot_start_schedule( $choice ) {
    e3p_logpilot_clear_schedule();

    $now      = time();
    $settings = array(
        'mode'   => $choice['mode'],
        'since'  => $now,
        'off_at' => 0,
        'days'   => 0,
        'email'  => '',
        'failed' => '',
    );

    if ( 'auto' === $choice['mode'] ) {
        $settings['off_at'] = $now + $choice['minutes'] * MINUTE_IN_SECONDS;
        wp_schedule_single_event( $settings['off_at'], 'e3p_logpilot_auto_off' );
    } else {
        $settings['days']  = $choice['days'];
        $settings['email'] = $choice['email'];
        wp_schedule_single_event( $now + $choice['days'] * DAY_IN_SECONDS, 'e3p_logpilot_remind' );
    }

    update_option( 'e3p_logpilot_settings', $settings );
}

/**
 * Legge e controlla la scelta inviata dal modulo di accensione. Chiamata dopo la verifica del nonce.
 *
 * @return array{mode: string, minutes: int, days: int, email: string}|array{error: string}
 */
function e3p_logpilot_read_choice() {
    // phpcs:disable WordPress.Security.NonceVerification.Missing -- il nonce è già stato controllato da chi chiama.
    $minutes = isset( $_POST['e3p_logpilot_minutes'] ) ? absint( wp_unslash( $_POST['e3p_logpilot_minutes'] ) ) : 1440;
    $days    = isset( $_POST['e3p_logpilot_remind_days'] ) ? absint( wp_unslash( $_POST['e3p_logpilot_remind_days'] ) ) : 3;
    $email   = isset( $_POST['e3p_logpilot_email'] ) ? sanitize_email( wp_unslash( $_POST['e3p_logpilot_email'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Missing

    if ( 0 === $minutes ) {
        if ( ! isset( e3p_logpilot_remind_choices()[ $days ] ) ) {
            $days = 3;
        }
        if ( ! is_email( $email ) ) {
            return array( 'error' => 'email' );
        }
        return array(
            'mode'    => 'remind',
            'minutes' => 0,
            'days'    => $days,
            'email'   => $email,
        );
    }

    if ( ! isset( e3p_logpilot_duration_choices()[ $minutes ] ) ) {
        $minutes = 1440;
    }
    return array(
        'mode'    => 'auto',
        'minutes' => $minutes,
        'days'    => 0,
        'email'   => '',
    );
}

/**
 * Spegne il debug in wp-config.php con le stesse protezioni del pulsante (nessuna richiesta di conferma:
 * il consenso è stato dato accendendo con lo spegnimento automatico).
 *
 * @return string Vuota se è andato bene, altrimenti il codice dell'errore (vedi e3p_logpilot_get_result_texts()).
 */
function e3p_logpilot_turn_off_now() {
    $path = e3p_logpilot_config_path();
    $src  = ( '' !== $path && is_readable( $path ) ) ? file_get_contents( $path ) : false;
    if ( false === $src ) {
        return 'read';
    }
    $applied = e3p_logpilot_config_apply( $src, false );
    if ( '' !== $applied['error'] ) {
        return $applied['error'];
    }
    $make_backup = ! file_exists( e3p_logpilot_config_backup_path( $path ) );
    return e3p_logpilot_config_write( $path, $applied['src'], $make_backup );
}

/**
 * Spegne il debug se la scadenza scelta è passata. Si chiama da WP-Cron e a ogni apertura dell'amministrazione,
 * perché WP-Cron parte solo quando il sito riceve visite.
 */
function e3p_logpilot_run_auto_off() {
    $settings = e3p_logpilot_get_schedule();
    if ( 'auto' !== $settings['mode'] || $settings['off_at'] <= 0 || time() < $settings['off_at'] ) {
        return;
    }

    // Segna la scadenza come presa in carico prima di agire, così due richieste insieme non lo fanno due volte.
    $settings['off_at'] = 0;
    update_option( 'e3p_logpilot_settings', $settings );

    if ( ! e3p_logpilot_debug_active() ) {
        // Già spento a mano: niente da fare.
        e3p_logpilot_clear_schedule();
        return;
    }

    $result = e3p_logpilot_turn_off_now();
    if ( '' === $result ) {
        e3p_logpilot_clear_schedule();
        return;
    }

    // Non è riuscito: il pannello lo dice, il debug resta acceso, e si avvisa l'indirizzo dell'amministratore
    // impostato in WordPress (dichiarato nel pannello, come per le notifiche di sistema di WordPress).
    // La scadenza è stata presa in carico una volta sola, quindi la mail parte una volta sola.
    $settings['failed'] = $result;
    update_option( 'e3p_logpilot_settings', $settings );
    e3p_logpilot_send_failure_notice( $result );
}

/**
 * Avvisa l'amministratore che lo spegnimento automatico non è riuscito e il debug è ancora acceso.
 * Solo mail di LogPilot (wp_mail() chiamato direttamente); il testo non contiene parti del log.
 *
 * @param string $code Codice dell'errore (vedi e3p_logpilot_get_result_texts()).
 */
function e3p_logpilot_send_failure_notice( $code ) {
    $to = (string) get_option( 'admin_email' );
    if ( ! is_email( $to ) ) {
        return;
    }
    $texts  = e3p_logpilot_get_result_texts();
    $reason = isset( $texts[ $code ] ) ? $texts[ $code ]['text'] : '';
    $site   = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

    /* translators: %s: site name */
    $subject = sprintf( __( '[%s] LogPilot could not turn debug logging off', 'logpilot' ), $site );
    $body    = sprintf(
        /* translators: 1: site name, 2: reason, 3: link to the LogPilot page */
        __( "The automatic turn-off of debug logging did not work on %1\$s, so logging is still on.\n\n%2\$s\n\nThe log can contain private information. Turn logging off here, or edit wp-config.php by hand:\n%3\$s", 'logpilot' ),
        $site,
        $reason,
        admin_url( 'tools.php?page=logpilot' )
    );
    wp_mail( $to, $subject, $body );
}

/**
 * Invia il promemoria e pianifica il prossimo. Solo mail di LogPilot, con wp_mail() chiamato direttamente.
 * Il testo non contiene parti del log.
 */
function e3p_logpilot_send_reminder() {
    $settings = e3p_logpilot_get_schedule();
    if ( 'remind' !== $settings['mode'] || ! is_email( $settings['email'] ) ) {
        return;
    }
    if ( ! e3p_logpilot_debug_active() ) {
        e3p_logpilot_clear_schedule();
        return;
    }

    $site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
    /* translators: %s: site name */
    $subject = sprintf( __( '[%s] Debug logging is still on', 'logpilot' ), $site );
    $body    = sprintf(
        /* translators: 1: site name, 2: how long logging has been on, e.g. "3 days", 3: link to the LogPilot page */
        __( "Debug logging has been on for %2\$s on %1\$s.\n\nThe log can contain private information, so turn it off when you no longer need it. You can do it here:\n%3\$s\n\nYou asked LogPilot to remind you about this.", 'logpilot' ),
        $site,
        human_time_diff( (int) $settings['since'], time() ),
        admin_url( 'tools.php?page=logpilot' )
    );
    wp_mail( $settings['email'], $subject, $body );

    wp_schedule_single_event( time() + max( 1, (int) $settings['days'] ) * DAY_IN_SECONDS, 'e3p_logpilot_remind' );
}

add_action( 'e3p_logpilot_auto_off', 'e3p_logpilot_run_auto_off' );
add_action( 'e3p_logpilot_remind', 'e3p_logpilot_send_reminder' );
add_action( 'admin_init', 'e3p_logpilot_run_auto_off' );

/**
 * Campi dello spegnimento automatico, nel modulo di accensione.
 */
function e3p_logpilot_render_schedule_fields() {
    echo '<fieldset class="e3p-logpilot-schedule"><legend><span class="dashicons dashicons-clock" aria-hidden="true"></span>' . esc_html__( 'Turn logging off automatically', 'logpilot' ) . '</legend>';
    echo '<p><label for="e3p-logpilot-minutes">' . esc_html__( 'After', 'logpilot' ) . '</label> ';
    echo '<select name="e3p_logpilot_minutes" id="e3p-logpilot-minutes">';
    foreach ( e3p_logpilot_duration_choices() as $minutes => $label ) {
        echo '<option value="' . esc_attr( (string) $minutes ) . '"' . selected( 1440, $minutes, false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '<option value="0">' . esc_html__( 'Don’t turn it off, remind me', 'logpilot' ) . '</option>';
    echo '</select></p>';
    // Se lo spegnimento fallisce l'avviso va all'indirizzo dell'amministratore di WordPress: lo si dice prima.
    echo '<p class="description e3p-logpilot-auto-note" id="e3p-logpilot-auto-note">';
    printf(
        /* translators: %s: site administrator e-mail address */
        esc_html__( 'If the automatic turn-off ever fails, LogPilot sends an e-mail to the site administrator address (%s).', 'logpilot' ),
        '<strong>' . esc_html( (string) get_option( 'admin_email' ) ) . '</strong>'
    );
    echo '</p>';

    echo '<div id="e3p-logpilot-remind-fields" class="e3p-logpilot-remind">';
    echo '<p><label for="e3p-logpilot-remind-days">' . esc_html__( 'Remind me', 'logpilot' ) . '</label> ';
    echo '<select name="e3p_logpilot_remind_days" id="e3p-logpilot-remind-days">';
    foreach ( e3p_logpilot_remind_choices() as $days => $label ) {
        echo '<option value="' . esc_attr( (string) $days ) . '"' . selected( 3, $days, false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select></p>';
    // L'indirizzo dell'amministratore è solo una proposta, modificabile; non c'è nessun indirizzo nel codice.
    echo '<p><label for="e3p-logpilot-email">' . esc_html__( 'Send the reminder to', 'logpilot' ) . '</label> ';
    echo '<input type="email" name="e3p_logpilot_email" id="e3p-logpilot-email" class="regular-text" value="' . esc_attr( (string) get_option( 'admin_email' ) ) . '" /></p>';
    echo '<p class="description">' . esc_html__( 'Logging stays on until you turn it off. LogPilot only uses this address to send the reminder.', 'logpilot' ) . '</p>';
    echo '</div>';
    echo '</fieldset>';
}

/**
 * Cosa è pianificato, mostrato quando il debug è acceso dal pulsante.
 *
 * @param array<string, array{type: string, text: string}> $texts Testi degli esiti.
 */
function e3p_logpilot_render_schedule_status( $texts ) {
    $settings = e3p_logpilot_get_schedule();
    if ( '' === $settings['mode'] ) {
        return;
    }

    $format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

    if ( '' !== $settings['failed'] ) {
        $reason = isset( $texts[ $settings['failed'] ] ) ? $texts[ $settings['failed'] ]['text'] : '';
        echo '<div class="e3p-logpilot-problems"><p><span class="dashicons dashicons-warning" aria-hidden="true"></span> ';
        echo esc_html__( 'The automatic turn-off could not change wp-config.php, so logging is still on. Turn it off with the button below, or by hand.', 'logpilot' );
        if ( '' !== $reason ) {
            echo ' ' . esc_html( $reason );
        }
        echo '</p></div>';
    } elseif ( 'auto' === $settings['mode'] && $settings['off_at'] > 0 ) {
        echo '<p class="e3p-logpilot-schedule-status"><span class="dashicons dashicons-clock" aria-hidden="true"></span><span>';
        printf(
            /* translators: 1: date and time, 2: time left, e.g. "5 hours" */
            esc_html__( 'It will turn off automatically on %1$s (in %2$s).', 'logpilot' ),
            '<strong>' . esc_html( wp_date( $format, $settings['off_at'] ) ) . '</strong>',
            esc_html( human_time_diff( time(), $settings['off_at'] ) )
        );
        echo '</span></p>';
    } elseif ( 'remind' === $settings['mode'] ) {
        $next = wp_next_scheduled( 'e3p_logpilot_remind' );
        echo '<p class="e3p-logpilot-schedule-status"><span class="dashicons dashicons-email" aria-hidden="true"></span><span>';
        printf(
            /* translators: 1: reminder interval, e.g. "every 3 days", 2: e-mail address */
            esc_html__( 'Logging will stay on. LogPilot reminds you %1$s at %2$s.', 'logpilot' ),
            esc_html( isset( e3p_logpilot_remind_choices()[ (int) $settings['days'] ] ) ? e3p_logpilot_remind_choices()[ (int) $settings['days'] ] : '' ),
            '<strong>' . esc_html( $settings['email'] ) . '</strong>'
        );
        if ( $next ) {
            echo ' ';
            printf(
                /* translators: %s: date and time of the next reminder */
                esc_html__( 'Next reminder: %s.', 'logpilot' ),
                esc_html( wp_date( $format, $next ) )
            );
        }
        echo '</span></p>';
    }

    if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
        echo '<p class="description">' . esc_html__( 'WP-Cron is disabled on this site. The automatic turn-off then happens when someone opens the admin area after the set time, and reminders need your server to run wp-cron.php regularly.', 'logpilot' ) . '</p>';
    }
}
