<?php
/*
 * Plugin Name:  BccPilot
 * Description:  Choose which WordPress notifications get a hidden Bcc copy, sent to an address of your choice. No generic email filtering: only the notifications you pick.
 * Text Domain:  bccpilot
 * Version:      1.1.0
 * Plugin URI:   https://www.e3pr0m.com/plugin/bccpilot/
 * Author:       E3pr0m
 * Author URI:   https://www.e3pr0m.com
 * Requires at least: 5.2
 * Requires PHP: 7.4
 * Domain Path:  /languages
 * License:      GPLv2 or later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Catalogo delle notifiche che si possono copiare in Bcc.
 *
 * Ogni voce corrisponde al filtro di UNA singola notifica: non si aggancia mai
 * `wp_mail`, così le mail di WooCommerce e le altre mail del sito restano fuori.
 *
 * Chiavi di ogni voce:
 * - hook:    filtro WordPress della notifica;
 * - type:    'email' se il filtro riceve l'array in stile wp_mail (to, subject,
 *            message, headers), 'headers' se riceve solo gli header;
 * - default: true se la voce è attiva finché l'utente non salva le impostazioni.
 *
 * Il filtro `e3p_bccpilot_notifications` permette a un plugin esterno di aggiungere voci.
 *
 * @return array
 */
function e3p_bccpilot_get_notifications() {
    $notifications = array(
        'recovery_mode'  => array(
            'hook'        => 'recovery_mode_email',
            'type'        => 'email',
            'default'     => true,
        ),
        'core_update'    => array(
            'hook'        => 'auto_core_update_email',
            'type'        => 'email',
            'default'     => true,
        ),
        'plugin_theme_update' => array(
            'hook'        => 'auto_plugin_theme_update_email',
            'type'        => 'email',
            'default'     => true,
        ),
        'update_debug'   => array(
            'hook'        => 'automatic_updates_debug_email',
            'type'        => 'email',
            'default'     => true,
        ),
        'new_user'       => array(
            'hook'        => 'wp_new_user_notification_email_admin',
            'type'        => 'email',
            'default'     => false,
        ),
        'comment_moderation' => array(
            'hook'        => 'comment_moderation_headers',
            'type'        => 'headers',
            'default'     => false,
        ),
    );

    $filtered = apply_filters( 'e3p_bccpilot_notifications', $notifications );

    return is_array( $filtered ) ? $filtered : $notifications;
}

/**
 * Testi mostrati nella schermata per ogni voce del catalogo.
 *
 * Tenuti separati dal catalogo perché le traduzioni non si possono chiamare
 * prima di `init`, mentre i filtri vanno agganciati già in `plugins_loaded`
 * (la mail di errore critico può partire prima di `init`).
 * Il filtro `e3p_bccpilot_notification_texts` permette di aggiungere i testi delle voci esterne.
 *
 * @return array id => array( label, description )
 */
function e3p_bccpilot_get_notification_texts() {
    $texts = array(
        'recovery_mode' => array(
            'label'       => __( 'Critical error (recovery mode)', 'bccpilot' ),
            'description' => __( 'The "Your Site is Experiencing a Technical Issue" email.', 'bccpilot' ),
        ),
        'core_update' => array(
            'label'       => __( 'WordPress automatic updates', 'bccpilot' ),
            'description' => __( 'Result of automatic core updates.', 'bccpilot' ),
        ),
        'plugin_theme_update' => array(
            'label'       => __( 'Plugin and theme automatic updates', 'bccpilot' ),
            'description' => __( 'Result of automatic plugin and theme updates.', 'bccpilot' ),
        ),
        'update_debug' => array(
            'label'       => __( 'Automatic updates debug', 'bccpilot' ),
            'description' => __( 'Debug email about automatic updates.', 'bccpilot' ),
        ),
        'new_user' => array(
            'label'       => __( 'New user registration', 'bccpilot' ),
            'description' => __( 'Notice sent to the administrator when a new user registers.', 'bccpilot' ),
        ),
        'comment_moderation' => array(
            'label'       => __( 'Comment awaiting moderation', 'bccpilot' ),
            'description' => __( 'Notice sent when a comment needs to be approved.', 'bccpilot' ),
        ),
    );

    $filtered = apply_filters( 'e3p_bccpilot_notification_texts', $texts );

    return is_array( $filtered ) ? $filtered : $texts;
}

/**
 * Impostazioni salvate, con i valori di default per ciò che manca.
 *
 * @return array { email: string, notifications: string[] }
 */
function e3p_bccpilot_get_settings() {
    $defaults = array(
        'email'         => '',
        'notifications' => array(),
    );
    foreach ( e3p_bccpilot_get_notifications() as $id => $notification ) {
        if ( ! empty( $notification['default'] ) ) {
            $defaults['notifications'][] = $id;
        }
    }

    $saved = get_option( 'e3p_bccpilot_settings', null );
    if ( ! is_array( $saved ) ) {
        return $defaults;
    }

    return array(
        'email'         => isset( $saved['email'] ) ? (string) $saved['email'] : '',
        'notifications' => isset( $saved['notifications'] ) && is_array( $saved['notifications'] ) ? $saved['notifications'] : array(),
    );
}

/**
 * Indirizzo che riceve la copia. Stringa vuota se non c'è un indirizzo valido.
 *
 * @return string
 */
function e3p_bccpilot_get_email() {
    $settings = e3p_bccpilot_get_settings();

    return is_email( $settings['email'] ) ? $settings['email'] : '';
}

/**
 * Aggiunge l'header Bcc a un insieme di header (stringa o array).
 *
 * @param string|array $headers Header esistenti.
 * @return array
 */
function e3p_bccpilot_append_bcc( $headers ) {
    $email = e3p_bccpilot_get_email();

    if ( ! is_array( $headers ) ) {
        $headers = array_filter( explode( "\n", str_replace( "\r\n", "\n", (string) $headers ) ) );
    }
    if ( '' !== $email ) {
        $headers[] = 'Bcc: ' . $email;
    }

    return $headers;
}

/**
 * Callback per i filtri che ricevono l'array in stile wp_mail.
 *
 * @param array $email to, subject, message, headers.
 * @return array
 */
function e3p_bccpilot_add_bcc( $email ) {
    if ( ! is_array( $email ) ) {
        return $email;
    }
    $email['headers'] = e3p_bccpilot_append_bcc( isset( $email['headers'] ) ? $email['headers'] : array() );

    return $email;
}

/**
 * Callback per i filtri che ricevono solo gli header.
 *
 * @param string|array $headers Header esistenti.
 * @return array
 */
function e3p_bccpilot_add_bcc_headers( $headers ) {
    return e3p_bccpilot_append_bcc( $headers );
}

/**
 * Aggancia i filtri delle sole notifiche scelte. Senza un indirizzo valido
 * non aggancia nulla.
 */
function e3p_bccpilot_register_filters() {
    if ( '' === e3p_bccpilot_get_email() ) {
        return;
    }

    $settings      = e3p_bccpilot_get_settings();
    $notifications = e3p_bccpilot_get_notifications();

    foreach ( $settings['notifications'] as $id ) {
        if ( ! isset( $notifications[ $id ] ) ) {
            continue;
        }
        $callback = 'headers' === $notifications[ $id ]['type'] ? 'e3p_bccpilot_add_bcc_headers' : 'e3p_bccpilot_add_bcc';
        add_filter( $notifications[ $id ]['hook'], $callback );
    }
}
add_action( 'plugins_loaded', 'e3p_bccpilot_register_filters' );

/**
 * Pulisce i dati inviati dal modulo delle impostazioni.
 *
 * @param mixed $input Dati grezzi dal modulo.
 * @return array
 */
function e3p_bccpilot_sanitize_settings( $input ) {
    $input = is_array( $input ) ? $input : array();
    $clean = array(
        'email'         => '',
        'notifications' => array(),
    );

    $email = isset( $input['email'] ) && is_string( $input['email'] ) ? trim( sanitize_text_field( wp_unslash( $input['email'] ) ) ) : '';
    if ( '' !== $email && ! is_email( $email ) ) {
        // Indirizzo non valido: errore e si mantiene quello già salvato.
        add_settings_error( 'e3p_bccpilot_settings', 'e3p_bccpilot_invalid_email', __( 'That email address does not look valid, so it was not changed.', 'bccpilot' ) );
        $clean['email'] = e3p_bccpilot_get_settings()['email'];
    } else {
        $clean['email'] = $email;
    }

    if ( isset( $input['notifications'] ) && is_array( $input['notifications'] ) ) {
        $known = array_keys( e3p_bccpilot_get_notifications() );
        foreach ( $input['notifications'] as $id ) {
            $id = sanitize_key( $id );
            if ( in_array( $id, $known, true ) ) {
                $clean['notifications'][] = $id;
            }
        }
    }

    return $clean;
}

function e3p_bccpilot_register_settings() {
    register_setting(
        'e3p_bccpilot_group',
        'e3p_bccpilot_settings',
        array(
            'type'              => 'array',
            'sanitize_callback' => 'e3p_bccpilot_sanitize_settings',
        )
    );
}
add_action( 'admin_init', 'e3p_bccpilot_register_settings' );

function e3p_bccpilot_add_settings_page() {
    add_options_page(
        __( 'BccPilot', 'bccpilot' ),
        __( 'BccPilot', 'bccpilot' ),
        'manage_options',
        'bccpilot',
        'e3p_bccpilot_render_settings_page'
    );
}
add_action( 'admin_menu', 'e3p_bccpilot_add_settings_page' );

/**
 * Stili della pagina, solo nella pagina di BccPilot. Regole di aspetto: Brand/PAGINA-PLUGIN.md.
 *
 * @param string $hook_suffix Pagina admin corrente.
 */
function e3p_bccpilot_enqueue_styles( $hook_suffix ) {
    if ( 'settings_page_bccpilot' !== $hook_suffix ) {
        return;
    }

    $css = '
.e3p-bccpilot-band{position:relative;margin:12px 0 16px;padding:16px 24px;border-left:4px solid #28CA6F;border-radius:8px;background:radial-gradient(ellipse 80% 160% at 10% 0%,#3B4049 0%,#2B2F36 100%);color:#F1F3F5}
.e3p-bccpilot-wrap .e3p-bccpilot-band h1{display:flex;align-items:center;gap:12px;margin:0;padding:0;color:#F1F3F5;font-size:28px;font-weight:600;line-height:1.2}
.e3p-bccpilot-band img{width:48px;height:48px;flex:none}
.e3p-bccpilot-band .e3p-bccpilot-accent{color:#28CA6F}
.e3p-bccpilot-tagline{margin:4px 0 0 60px;font-size:14px;color:#dfe3e8}
.e3p-bccpilot-by{position:absolute;right:24px;bottom:15px;margin:0;font-size:12px;font-weight:300;color:#c5cbd3}
.e3p-bccpilot-by a{color:#F1F3F5;text-decoration:none;font-weight:300}
.e3p-bccpilot-by a span{color:#28CA6F}
.e3p-bccpilot-by a:hover,.e3p-bccpilot-by a:focus{text-decoration:underline}
.e3p-bccpilot-by .e3p-bccpilot-donate{color:#c5cbd3;text-decoration:none;font-weight:300}
.e3p-bccpilot-by .e3p-bccpilot-donate:hover,.e3p-bccpilot-by .e3p-bccpilot-donate:focus{text-decoration:underline}
@media (max-width:600px){.e3p-bccpilot-tagline{margin-left:0}.e3p-bccpilot-by{position:static;display:block;margin-top:8px}}
.e3p-bccpilot-step{margin:16px 0;padding:20px 24px 16px;background:#e5e8eb;border:1px solid #c3c4c7;border-left:4px solid #28CA6F;border-radius:8px}
.e3p-bccpilot-head{display:flex;align-items:center;gap:14px;margin-bottom:12px}
.e3p-bccpilot-head-icon{flex:none;box-sizing:content-box;width:28px;height:28px;padding:9px;border:1px solid #28CA6F;border-radius:50%;background:#eaf7ef;color:#28CA6F;font-size:28px;line-height:28px;text-align:center}
.e3p-bccpilot-head h2{margin:0;font-size:1.4em}
.e3p-bccpilot-subtitle{margin:2px 0 0;color:#50575e;font-size:14px}
.e3p-bccpilot-state{display:flex;flex-wrap:wrap;align-items:center;gap:6px 12px;margin:0 0 16px}
.e3p-bccpilot-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 12px 3px 8px;border-radius:999px;background:#eaf7ef;color:#22ac5e;font-weight:600}
.e3p-bccpilot-pill.is-off{background:#f0f0f1;color:#3c434a}
.e3p-bccpilot-pill .dashicons{color:inherit}
.e3p-bccpilot-field{display:flex;flex-direction:column;gap:4px;margin:0 0 8px}
.e3p-bccpilot-field label{font-weight:600}
.e3p-bccpilot-field input[type=email]{width:100%;max-width:380px}
.e3p-bccpilot-field .description,.e3p-bccpilot-step>.description{margin:0}
.e3p-bccpilot-list{margin:0;padding:0;list-style:none}
.e3p-bccpilot-list li{margin:0 0 12px}
.e3p-bccpilot-list label{display:flex;align-items:flex-start;gap:8px}
.e3p-bccpilot-list input[type=checkbox]{flex:none;margin:2px 0 0}
.e3p-bccpilot-wrap input[type=checkbox]:checked{background:#28CA6F;border-color:#28CA6F}
.e3p-bccpilot-wrap input[type=checkbox]:checked::before{content:url("data:image/svg+xml;utf8,%3Csvg%20xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27%20viewBox%3D%270%200%2020%2020%27%3E%3Cpath%20d%3D%27M14.83%204.89l1.34.94-5.81%208.38H9.02L5.78%209.67l1.34-1.25%202.57%202.4z%27%20fill%3D%27%231d2327%27%2F%3E%3C%2Fsvg%3E")}
.e3p-bccpilot-wrap input[type=checkbox]:focus{border-color:#28CA6F;box-shadow:0 0 0 1px #28CA6F}
.e3p-bccpilot-list strong{display:block}
.e3p-bccpilot-list .description{display:block;margin:2px 0 0}
.e3p-bccpilot-safe{display:flex;align-items:flex-start;gap:6px;margin:4px 0 0;color:#50575e}
.e3p-bccpilot-safe .dashicons{flex:none;width:18px;height:18px;font-size:18px;color:#28CA6F}
.e3p-bccpilot-test-form{margin:0}
.e3p-bccpilot-save{margin:16px 0 0;padding-top:16px;border-top:1px solid #dcdcde}
.e3p-bccpilot-wrap .button.e3p-bccpilot-testbtn.e3p-bccpilot-testbtn:not(:disabled):not(.disabled){background:#f6f7f7;border-color:#8c8f94;color:#1d2327}
.e3p-bccpilot-wrap .button.e3p-bccpilot-testbtn.e3p-bccpilot-testbtn:not(:disabled):not(.disabled):hover,.e3p-bccpilot-wrap .button.e3p-bccpilot-testbtn.e3p-bccpilot-testbtn:not(:disabled):not(.disabled):focus{background:#fff;border-color:#28CA6F;color:#1d2327}
.e3p-bccpilot-testbtn .dashicons{color:#28CA6F}
.e3p-bccpilot-test-form+.description{margin-top:8px}
.e3p-bccpilot-result{display:flex;align-items:flex-start;gap:6px;margin:0 0 12px;padding:8px 12px;border-radius:6px;background:#eaf7ef;border:1px solid #bfe3cc;border-left:4px solid #28CA6F;color:#1d2327}
.e3p-bccpilot-result .dashicons{flex:none;width:20px;height:20px;font-size:20px;color:#28CA6F}
.e3p-bccpilot-result.is-error{background:#fcf0f1;border-color:#e6b3b4;border-left-color:#b32d2e}
.e3p-bccpilot-result.is-error .dashicons{color:#b32d2e}
.e3p-bccpilot-wrap .button:not(.button-primary):not(:disabled):not(.disabled){color:#28CA6F;border-color:#28CA6F}
.e3p-bccpilot-wrap .button:not(.button-primary):not(:disabled):not(.disabled):hover,.e3p-bccpilot-wrap .button:not(.button-primary):not(:disabled):not(.disabled):focus{color:#22ac5e;border-color:#22ac5e;background:#f3faf6}
.e3p-bccpilot-wrap .button .dashicons{margin-right:4px}
.e3p-bccpilot-wrap .button.button-primary:not(:disabled):not(.disabled){background:#28CA6F;border-color:#28CA6F;color:#fff}
.e3p-bccpilot-wrap .button.button-primary:not(:disabled):not(.disabled):hover,.e3p-bccpilot-wrap .button.button-primary:not(:disabled):not(.disabled):focus{background:#22ac5e;border-color:#22ac5e;color:#fff}
';

    wp_register_style( 'e3p-bccpilot-admin', false, array(), false );
    wp_enqueue_style( 'e3p-bccpilot-admin' );
    wp_add_inline_style( 'e3p-bccpilot-admin', $css );
}
add_action( 'admin_enqueue_scripts', 'e3p_bccpilot_enqueue_styles' );

/**
 * Titolo di una scheda: icona in un cerchio, titolo e una riga di spiegazione.
 *
 * @param string $icon     Nome della Dashicon, senza prefisso.
 * @param string $title    Titolo (già tradotto).
 * @param string $subtitle Riga di spiegazione (già tradotta).
 */
function e3p_bccpilot_render_step_head( $icon, $title, $subtitle ) {
    echo '<div class="e3p-bccpilot-head"><span class="e3p-bccpilot-head-icon dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></span><div>';
    echo '<h2>' . esc_html( $title ) . '</h2>';
    echo '<p class="e3p-bccpilot-subtitle">' . esc_html( $subtitle ) . '</p>';
    echo '</div></div>';
}

/**
 * Stato del plugin per la pagina: attivo solo con un indirizzo valido e almeno una notifica scelta.
 *
 * @return array { active: bool, text: string }
 */
function e3p_bccpilot_get_state() {
    $email = e3p_bccpilot_get_email();
    if ( '' === $email ) {
        return array(
            'active' => false,
            'text'   => __( 'Add an email address and BccPilot will start copying the emails you choose.', 'bccpilot' ),
        );
    }

    $settings = e3p_bccpilot_get_settings();
    $known    = e3p_bccpilot_get_notifications();
    $count    = count( array_intersect( $settings['notifications'], array_keys( $known ) ) );
    if ( 0 === $count ) {
        return array(
            'active' => false,
            'text'   => __( 'Choose at least one email below and BccPilot will start copying it.', 'bccpilot' ),
        );
    }

    return array(
        'active' => true,
        /* translators: 1: number of kinds of email, 2: email address that receives the copy. */
        'text'   => sprintf( _n( 'A hidden copy of %1$d kind of email goes to %2$s.', 'A hidden copy of %1$d kinds of email goes to %2$s.', $count, 'bccpilot' ), $count, $email ),
    );
}

/**
 * Invia una mail di prova all'indirizzo salvato (pulsante "Send a test email").
 *
 * Usa wp_mail() chiamato direttamente, senza agganciare nulla: la mail di prova
 * non è una copia di altre mail e non passa dai filtri del plugin.
 * Esiti possibili (codici noti, mostrati nella pagina): sent, failed, noaddress.
 */
function e3p_bccpilot_handle_test_email() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to do this.', 'bccpilot' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'e3p_bccpilot_test_email' );

    $email = e3p_bccpilot_get_email();
    if ( '' === $email ) {
        $result = 'noaddress';
    } else {
        $site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
        /* translators: %s: site name. */
        $subject = sprintf( __( '[%s] BccPilot test email', 'bccpilot' ), $site );
        /* translators: %s: site address. */
        $message = sprintf( __( 'This is a test email sent by BccPilot from %s.', 'bccpilot' ), home_url() )
            . "\n\n"
            . __( 'If you can read it, this address can receive the copies you choose. It is not a copy of any other email.', 'bccpilot' );
        $result  = wp_mail( $email, $subject, $message ) ? 'sent' : 'failed';
    }

    wp_safe_redirect( add_query_arg( 'e3p_bccpilot_test', $result, admin_url( 'options-general.php?page=bccpilot' ) ) . '#e3p-bccpilot-test' );
    exit;
}
add_action( 'admin_post_e3p_bccpilot_test_email', 'e3p_bccpilot_handle_test_email' );

/**
 * Esito dell'ultima mail di prova, letto dall'indirizzo (solo codici noti).
 *
 * @return string sent, failed, noaddress oppure stringa vuota.
 */
function e3p_bccpilot_get_test_result() {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- è solo un codice d'esito tra valori noti, non modifica nulla.
    $code = isset( $_GET['e3p_bccpilot_test'] ) ? sanitize_key( wp_unslash( $_GET['e3p_bccpilot_test'] ) ) : '';

    return in_array( $code, array( 'sent', 'failed', 'noaddress' ), true ) ? $code : '';
}

function e3p_bccpilot_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $settings      = e3p_bccpilot_get_settings();
    $notifications = e3p_bccpilot_get_notifications();
    $texts         = e3p_bccpilot_get_notification_texts();
    $state         = e3p_bccpilot_get_state();
    ?>
    <div class="wrap e3p-bccpilot-wrap">
        <div class="e3p-bccpilot-band">
            <h1>
                <img src="<?php echo esc_url( plugins_url( 'assets/icon.svg', __FILE__ ) ); ?>" alt="">
                <span>Bcc<span class="e3p-bccpilot-accent">Pilot</span></span>
            </h1>
            <p class="e3p-bccpilot-tagline"><?php esc_html_e( 'Get a hidden copy of the WordPress emails that matter to you.', 'bccpilot' ); ?></p>
            <p class="e3p-bccpilot-by"><?php
            // Il nome del marchio non si traduce: il 3 e lo 0 sono in verde (Brand/PAGINA-PLUGIN.md).
            printf(
                /* translators: %s: link to the E3pr0m website (the author name). */
                esc_html__( 'by %s', 'bccpilot' ),
                '<a href="' . esc_url( 'https://www.e3pr0m.com/plugin/' ) . '" target="_blank" rel="noopener noreferrer">E<span>3</span>pr<span>0</span>m</a>'
            );
            ?> &middot; <a class="e3p-bccpilot-donate" href="<?php echo esc_url( 'https://github.com/sponsors/E3pr0m' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Support development', 'bccpilot' ); ?></a></p>
        </div>
        <hr class="wp-header-end" />

        <form method="post" action="options.php">
            <?php settings_fields( 'e3p_bccpilot_group' ); ?>

            <div class="e3p-bccpilot-step">
                <?php e3p_bccpilot_render_step_head( 'email-alt', __( 'Who gets the copy?', 'bccpilot' ), __( 'Choose the address that receives a hidden copy (Bcc) of the emails you pick below.', 'bccpilot' ) ); ?>
                <p class="e3p-bccpilot-state">
                    <span class="e3p-bccpilot-pill<?php echo $state['active'] ? '' : ' is-off'; ?>">
                        <span class="dashicons dashicons-<?php echo $state['active'] ? 'yes-alt' : 'minus'; ?>" aria-hidden="true"></span>
                        <?php echo $state['active'] ? esc_html__( 'Active', 'bccpilot' ) : esc_html__( 'Not active', 'bccpilot' ); ?>
                    </span>
                    <span><?php echo esc_html( $state['text'] ); ?></span>
                </p>
                <div class="e3p-bccpilot-field">
                    <label for="e3p_bccpilot_email"><?php esc_html_e( 'Email address that receives the copy', 'bccpilot' ); ?></label>
                    <input type="email" id="e3p_bccpilot_email" name="e3p_bccpilot_settings[email]" value="<?php echo esc_attr( $settings['email'] ); ?>">
                    <p class="description"><?php esc_html_e( 'The people who normally receive these emails do not see this address. Leave it empty to turn BccPilot off. Make sure whoever reads these copies is allowed to see their content.', 'bccpilot' ); ?></p>
                </div>
            </div>

            <div class="e3p-bccpilot-step">
                <?php e3p_bccpilot_render_step_head( 'list-view', __( 'Which emails do you want copied?', 'bccpilot' ), __( 'Tick the WordPress system emails you want a copy of.', 'bccpilot' ) ); ?>
                <ul class="e3p-bccpilot-list">
                    <?php foreach ( $notifications as $id => $notification ) : ?>
                        <li>
                            <label>
                                <input type="checkbox" name="e3p_bccpilot_settings[notifications][]" value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( $id, $settings['notifications'], true ) ); ?>>
                                <span>
                                    <strong><?php echo esc_html( isset( $texts[ $id ]['label'] ) ? $texts[ $id ]['label'] : $id ); ?></strong>
                                    <span class="description"><?php echo esc_html( isset( $texts[ $id ]['description'] ) ? $texts[ $id ]['description'] : '' ); ?></span>
                                </span>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="e3p-bccpilot-safe">
                    <span class="dashicons dashicons-shield" aria-hidden="true"></span>
                    <span><?php esc_html_e( 'Only the emails listed here are copied. Other emails of your site, such as WooCommerce orders, are never copied.', 'bccpilot' ); ?></span>
                </p>
                <div class="e3p-bccpilot-save">
                    <button type="submit" name="submit" id="submit" class="button button-primary e3p-bccpilot-testbtn"><span class="dashicons dashicons-saved" aria-hidden="true"></span><?php esc_html_e( 'Save Changes', 'bccpilot' ); ?></button>
                </div>
            </div>
        </form>

        <?php
        $test_result = e3p_bccpilot_get_test_result();
        $test_email  = e3p_bccpilot_get_email();
        ?>
        <div class="e3p-bccpilot-step" id="e3p-bccpilot-test">
            <?php e3p_bccpilot_render_step_head( 'yes-alt', __( 'Check that it works', 'bccpilot' ), __( 'Send yourself a test email to see that the address can receive messages.', 'bccpilot' ) ); ?>
            <?php if ( 'sent' === $test_result ) : ?>
                <p class="e3p-bccpilot-result" role="status">
                    <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                    <span><?php esc_html_e( 'The test email was sent. Check the inbox of that address, and the spam folder too.', 'bccpilot' ); ?></span>
                </p>
            <?php elseif ( 'failed' === $test_result ) : ?>
                <p class="e3p-bccpilot-result is-error" role="alert">
                    <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                    <span><?php esc_html_e( 'The test email could not be sent. Your site cannot send email right now, so neither the original emails nor their copies will leave it. Check the mail settings of your site or ask your host.', 'bccpilot' ); ?></span>
                </p>
            <?php elseif ( 'noaddress' === $test_result ) : ?>
                <p class="e3p-bccpilot-result is-error" role="alert">
                    <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                    <span><?php esc_html_e( 'There is no saved address to send the test to. Add one above and save first.', 'bccpilot' ); ?></span>
                </p>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="e3p-bccpilot-test-form">
                <input type="hidden" name="action" value="e3p_bccpilot_test_email">
                <?php wp_nonce_field( 'e3p_bccpilot_test_email' ); ?>
                <button type="submit" class="button e3p-bccpilot-testbtn"<?php disabled( '' === $test_email ); ?>>
                    <span class="dashicons dashicons-email-alt" aria-hidden="true"></span><?php esc_html_e( 'Send a test email', 'bccpilot' ); ?>
                </button>
            </form>
            <p class="description">
                <?php
                if ( '' === $test_email ) {
                    esc_html_e( 'Save an email address first: the test goes to the saved address.', 'bccpilot' );
                } else {
                    esc_html_e( 'The test goes to the saved address, not to what you typed and have not saved yet. It only shows that the address can receive email: it does not copy any real notification.', 'bccpilot' );
                }
                ?>
            </p>
        </div>
    </div>
    <?php
}

/**
 * Aggiunge il link "Settings" nella lista dei plugin.
 *
 * @param array $links Link già presenti.
 * @return array
 */
function e3p_bccpilot_action_links( $links ) {
    $url = admin_url( 'options-general.php?page=bccpilot' );
    array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'bccpilot' ) . '</a>' );

    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'e3p_bccpilot_action_links' );
