<?php
/*
 * Plugin Name:  BccPilot
 * Description:  Choose which WordPress notifications get a hidden Bcc copy, sent to an address of your choice. No generic email filtering: only the notifications you pick.
 * Text Domain:  bccpilot
 * Version:      1.0.0
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
        'plugin_update'  => array(
            'hook'        => 'auto_plugin_update_email',
            'type'        => 'email',
            'default'     => true,
        ),
        'theme_update'   => array(
            'hook'        => 'auto_theme_update_email',
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
        'plugin_update' => array(
            'label'       => __( 'Plugin automatic updates', 'bccpilot' ),
            'description' => __( 'Result of automatic plugin updates.', 'bccpilot' ),
        ),
        'theme_update' => array(
            'label'       => __( 'Theme automatic updates', 'bccpilot' ),
            'description' => __( 'Result of automatic theme updates.', 'bccpilot' ),
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

    $email = isset( $input['email'] ) ? sanitize_email( wp_unslash( $input['email'] ) ) : '';
    if ( '' !== $email && ! is_email( $email ) ) {
        add_settings_error( 'e3p_bccpilot_settings', 'e3p_bccpilot_invalid_email', __( 'The email address is not valid.', 'bccpilot' ) );
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

function e3p_bccpilot_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $settings      = e3p_bccpilot_get_settings();
    $notifications = e3p_bccpilot_get_notifications();
    $texts         = e3p_bccpilot_get_notification_texts();
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <?php settings_errors( 'e3p_bccpilot_settings' ); ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'e3p_bccpilot_group' ); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="e3p_bccpilot_email"><?php esc_html_e( 'Copy recipient', 'bccpilot' ); ?></label></th>
                    <td>
                        <input type="email" id="e3p_bccpilot_email" name="e3p_bccpilot_settings[email]" class="regular-text" value="<?php echo esc_attr( $settings['email'] ); ?>">
                        <p class="description"><?php esc_html_e( 'This address will receive a hidden copy (Bcc) of the emails selected below. Leave it empty to disable the plugin.', 'bccpilot' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Emails to copy', 'bccpilot' ); ?></th>
                    <td>
                        <fieldset>
                            <?php foreach ( $notifications as $id => $notification ) : ?>
                                <label>
                                    <input type="checkbox" name="e3p_bccpilot_settings[notifications][]" value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( $id, $settings['notifications'], true ) ); ?>>
                                    <?php echo esc_html( isset( $texts[ $id ]['label'] ) ? $texts[ $id ]['label'] : $id ); ?>
                                </label>
                                <p class="description"><?php echo esc_html( isset( $texts[ $id ]['description'] ) ? $texts[ $id ]['description'] : '' ); ?></p>
                                <br>
                            <?php endforeach; ?>
                        </fieldset>
                        <p class="description"><?php esc_html_e( 'Only these WordPress system emails are copied. Other emails of the site, such as WooCommerce orders, are never copied.', 'bccpilot' ); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
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
