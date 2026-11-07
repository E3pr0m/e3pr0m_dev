<?php
/**
 * Posto del log (#41): cartella con nome non indovinabile, fuori dal sito se possibile, altrimenti in
 * wp-content con regole di protezione; verifica con una richiesta al sito stesso; controllo ed eliminazione
 * del vecchio wp-content/debug.log.
 *
 * Il nome della cartella deriva dai salt del sito: è sempre lo stesso, quindi non va salvato in nessun posto
 * (nessuna scrittura nel database) e si ritrova anche quando il debug è spento.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Parte non indovinabile del nome della cartella: 16 caratteri esadecimali ricavati dai salt del sito.
 * uninstall.php ripete lo stesso calcolo.
 *
 * @return string
 */
function e3p_logpilot_log_token() {
    return substr( wp_hash( 'e3p-logpilot-log' ), 0, 16 );
}

/**
 * Nome della cartella dei log.
 *
 * @return string
 */
function e3p_logpilot_log_folder_name() {
    return 'e3p-logpilot-' . e3p_logpilot_log_token();
}

/**
 * Un percorso sta dentro la radice web del server? Se non si riesce a stabilirlo si risponde di sì,
 * cioè si assume il caso peggiore.
 *
 * @param string $path Percorso (di una cartella che esiste).
 * @return bool
 */
function e3p_logpilot_inside_docroot( $path ) {
    $doc  = isset( $_SERVER['DOCUMENT_ROOT'] ) ? realpath( sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) ) : false;
    $real = realpath( $path );
    if ( false === $doc || false === $real ) {
        return true;
    }
    $doc  = strtolower( trailingslashit( wp_normalize_path( $doc ) ) );
    $real = strtolower( trailingslashit( wp_normalize_path( $real ) ) );
    return 0 === strpos( $real, $doc );
}

/**
 * Dove andrebbe il log: nella cartella sopra WordPress se è scrivibile e fuori dalla radice web, altrimenti
 * in wp-content. Non crea nulla.
 *
 * @param bool $force_inside True per scegliere wp-content comunque.
 * @return array{dir: string, file: string, outside: bool}
 */
function e3p_logpilot_plan_log_location( $force_inside = false ) {
    $name   = e3p_logpilot_log_folder_name();
    $parent = dirname( untrailingslashit( wp_normalize_path( ABSPATH ) ) );

    // Le @ evitano gli avvisi di open_basedir quando la cartella sopra non è accessibile a PHP.
    // phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
    $parent_ok = ! $force_inside && '' !== $parent && '.' !== $parent && @is_dir( $parent ) && @is_writable( $parent );
    // phpcs:enable WordPress.PHP.NoSilencedErrors.Discouraged

    if ( $parent_ok && ! e3p_logpilot_inside_docroot( $parent ) ) {
        $dir     = $parent . '/' . $name;
        $outside = true;
    } else {
        $dir     = wp_normalize_path( WP_CONTENT_DIR ) . '/' . $name;
        $outside = false;
    }

    return array(
        'dir'     => $dir,
        'file'    => $dir . '/debug.log',
        'outside' => $outside,
    );
}

/**
 * Crea la cartella e, se sta in wp-content, le regole che negano l'accesso dal web (Apache e IIS) e un index.php
 * vuoto. Nginx ignora questi file: per questo c'è la verifica con la richiesta di prova.
 *
 * @param array{dir: string, file: string, outside: bool} $loc Posto scelto.
 * @return bool True se è tutto a posto.
 */
function e3p_logpilot_prepare_log_location( $loc ) {
    if ( ! wp_mkdir_p( $loc['dir'] ) || ! is_writable( $loc['dir'] ) ) {
        return false;
    }

    $files = array( 'index.php' => "<?php\n// Silence is golden.\n" );
    if ( ! $loc['outside'] ) {
        $files['.htaccess'] = "# Written by LogPilot: nobody can open the logs from a web address.\n"
            . "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n";
        $files['web.config'] = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n<system.webServer>\n<security>\n<authorization>\n"
            . "<remove users=\"*\" roles=\"\" verbs=\"\" />\n<add accessType=\"Deny\" users=\"*\" />\n</authorization>\n</security>\n</system.webServer>\n</configuration>\n";
    }
    foreach ( $files as $name => $content ) {
        if ( strlen( $content ) !== file_put_contents( $loc['dir'] . '/' . $name, $content ) ) {
            return false;
        }
    }
    return true;
}

/**
 * Sceglie e prepara il posto del log. Se la cartella fuori dal sito non si riesce a preparare, ripiega su wp-content.
 *
 * @return array{dir: string, file: string, outside: bool}|null Posto pronto, o null se non si riesce.
 */
function e3p_logpilot_setup_log_location() {
    $loc = e3p_logpilot_plan_log_location();
    if ( e3p_logpilot_prepare_log_location( $loc ) ) {
        return $loc;
    }
    if ( $loc['outside'] ) {
        $loc = e3p_logpilot_plan_log_location( true );
        if ( e3p_logpilot_prepare_log_location( $loc ) ) {
            return $loc;
        }
    }
    return null;
}

/**
 * File di log di LogPilot già presenti (fuori dal sito o in wp-content).
 *
 * @return string[]
 */
function e3p_logpilot_existing_log_files() {
    $name  = e3p_logpilot_log_folder_name();
    $found = array();
    foreach ( array( dirname( untrailingslashit( wp_normalize_path( ABSPATH ) ) ), wp_normalize_path( WP_CONTENT_DIR ) ) as $base ) {
        $file = $base . '/' . $name . '/debug.log';
        // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- open_basedir.
        if ( @is_file( $file ) ) {
            $found[] = $file;
        }
    }
    return $found;
}

/**
 * Una richiesta HTTP al sito stesso. Risposta con codice e corpo, o null se la richiesta non riesce.
 *
 * @param string $url    Indirizzo.
 * @param string $method GET o HEAD.
 * @return array{code: int, body: string}|null
 */
function e3p_logpilot_http_probe( $url, $method = 'GET' ) {
    $response = wp_remote_request(
        $url,
        array(
            'method'      => $method,
            'timeout'     => 4,
            'redirection' => 0,
            'sslverify'   => apply_filters( 'https_local_ssl_verify', false ),
        )
    );
    if ( is_wp_error( $response ) ) {
        return null;
    }
    return array(
        'code' => (int) wp_remote_retrieve_response_code( $response ),
        'body' => (string) wp_remote_retrieve_body( $response ),
    );
}

/**
 * Verifica se i log si possono scaricare dal web. Scrive un file di controllo in wp-content (per essere sicuri
 * che l'indirizzo della richiesta corrisponda alla cartella) e un file di prova nella cartella dei log, li
 * chiede al sito e li cancella subito.
 *
 * @param array{dir: string, file: string, outside: bool} $loc Posto del log nuovo.
 * @return array{folder: string, old: string} `folder`: outside, protected, exposed, unknown. `old`: none, protected, exposed, unknown.
 */
function e3p_logpilot_run_checks( $loc ) {
    $result = array(
        'folder' => 'unknown',
        'old'    => 'unknown',
    );

    $old_file = wp_normalize_path( WP_CONTENT_DIR ) . '/debug.log';
    $old_here = is_file( $old_file );
    if ( ! $old_here ) {
        $result['old'] = 'none';
    }
    if ( $loc['outside'] ) {
        $result['folder'] = 'outside';
    }
    if ( 'outside' === $result['folder'] && 'none' === $result['old'] ) {
        return $result; // Niente da verificare con una richiesta.
    }

    // File di controllo in wp-content: se il sito non lo serve, l'indirizzo non corrisponde alla cartella
    // e nessun "403" o "404" può essere interpretato.
    $marker  = 'e3p-logpilot-probe-' . wp_generate_password( 12, false );
    $control = wp_normalize_path( WP_CONTENT_DIR ) . '/e3p-logpilot-control-' . wp_generate_password( 8, false ) . '.txt';
    if ( false === file_put_contents( $control, $marker ) ) {
        return $result;
    }
    $answer = e3p_logpilot_http_probe( content_url( '/' . basename( $control ) ) );
    wp_delete_file( $control );
    if ( ! $answer || 200 !== $answer['code'] || false === strpos( $answer['body'], $marker ) ) {
        return $result;
    }

    if ( ! $loc['outside'] ) {
        $probe = $loc['dir'] . '/probe.txt';
        if ( false !== file_put_contents( $probe, $marker ) ) {
            $answer = e3p_logpilot_http_probe( content_url( '/' . basename( $loc['dir'] ) . '/probe.txt' ) );
            wp_delete_file( $probe );
            if ( $answer ) {
                if ( 200 === $answer['code'] && false !== strpos( $answer['body'], $marker ) ) {
                    $result['folder'] = 'exposed';
                } elseif ( in_array( $answer['code'], array( 401, 403, 404 ), true ) ) {
                    $result['folder'] = 'protected';
                }
            }
        }
    }

    if ( $old_here ) {
        $answer = e3p_logpilot_http_probe( content_url( '/debug.log' ), 'HEAD' );
        if ( $answer ) {
            if ( 200 === $answer['code'] ) {
                $result['old'] = 'exposed';
            } elseif ( in_array( $answer['code'], array( 401, 403, 404 ), true ) ) {
                $result['old'] = 'protected';
            }
        }
    }

    return $result;
}

/**
 * Testi degli esiti del controllo (codice => tipo e testo). I codici arrivano nell'indirizzo: si accettano solo questi.
 *
 * @return array<string, array<string, array{type: string, text: string}>> Due gruppi: `folder` e `old`.
 */
function e3p_logpilot_get_check_texts() {
    return array(
        'folder' => array(
            'outside'   => array(
                'type' => 'success',
                'text' => __( 'The log is saved outside your website folder, so no web address can reach it.', 'logpilot' ),
            ),
            'protected' => array(
                'type' => 'success',
                'text' => __( 'Checked: your server refuses to hand out the files in the log folder.', 'logpilot' ),
            ),
            'exposed'   => array(
                'type' => 'error',
                'text' => __( 'Warning: the log folder can be opened from a web address, so anyone who guesses its name could download your logs. Your server probably ignores the protection rules LogPilot writes (this is common on Nginx). Ask your hosting provider to block access to this folder, or move the log outside your website folder.', 'logpilot' ),
            ),
            'unknown'   => array(
                'type' => 'warning',
                'text' => __( 'LogPilot could not check whether the log folder is protected (the site did not answer its own test request). The folder has a secret name and protection rules, but this has not been verified.', 'logpilot' ),
            ),
        ),
        'old'    => array(
            'none'      => array(
                'type' => 'info',
                'text' => __( 'There is no old debug.log in wp-content.', 'logpilot' ),
            ),
            'protected' => array(
                'type' => 'success',
                'text' => __( 'Checked: the old wp-content/debug.log cannot be downloaded from a web address.', 'logpilot' ),
            ),
            'exposed'   => array(
                'type' => 'error',
                'text' => __( 'Warning: the old wp-content/debug.log can be downloaded by anyone who opens its web address. Delete it with the button below.', 'logpilot' ),
            ),
            'unknown'   => array(
                'type' => 'warning',
                'text' => __( 'LogPilot could not check whether the old wp-content/debug.log can be downloaded.', 'logpilot' ),
            ),
        ),
    );
}

/**
 * Pulsante "Check": controlla il posto del log e il vecchio debug.log, poi torna al pannello con l'esito.
 */
function e3p_logpilot_handle_check() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to do this.', 'logpilot' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'e3p_logpilot_check' );

    $loc    = e3p_logpilot_plan_log_location();
    $checks = e3p_logpilot_run_checks( $loc );

    wp_safe_redirect(
        add_query_arg(
            array(
                'page'            => 'logpilot',
                'e3p_logpilot_pr' => $checks['folder'],
                'e3p_logpilot_od' => $checks['old'],
            ),
            admin_url( 'tools.php' )
        )
    );
    exit;
}
add_action( 'admin_post_e3p_logpilot_check', 'e3p_logpilot_handle_check' );

/**
 * Elimina il vecchio wp-content/debug.log. Solo quel file, con conferma esplicita.
 */
function e3p_logpilot_handle_delete_old() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to do this.', 'logpilot' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'e3p_logpilot_delete_old' );

    $file = wp_normalize_path( WP_CONTENT_DIR ) . '/debug.log';
    if ( empty( $_POST['e3p_logpilot_confirm_delete'] ) ) {
        $result = 'confirm_delete';
    } elseif ( ! is_file( $file ) || is_link( $file ) ) {
        $result = 'old_none';
    } else {
        wp_delete_file( $file );
        $result = is_file( $file ) ? 'old_failed' : 'old_deleted';
    }

    wp_safe_redirect(
        add_query_arg(
            array(
                'page'            => 'logpilot',
                'e3p_logpilot_rs' => $result,
            ),
            admin_url( 'tools.php' )
        )
    );
    exit;
}
add_action( 'admin_post_e3p_logpilot_delete_old', 'e3p_logpilot_handle_delete_old' );

/**
 * Parte del pannello: dove viene salvato il log, controllo di protezione e vecchio debug.log.
 *
 * @param bool $active True se il debug è acceso adesso.
 */
function e3p_logpilot_render_location_section( $active ) {
    $check_texts = e3p_logpilot_get_check_texts();
    $name        = e3p_logpilot_log_folder_name();

    // Dove scrive WordPress adesso (se è acceso) e dove scriverebbe con LogPilot.
    $current = '';
    if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
        $current = is_string( WP_DEBUG_LOG ) ? wp_normalize_path( WP_DEBUG_LOG ) : wp_normalize_path( WP_CONTENT_DIR ) . '/debug.log';
    }
    $ours_now = $active && false !== strpos( $current, '/' . $name . '/' );
    $plan     = e3p_logpilot_plan_log_location();

    echo '<div class="e3p-logpilot-location"><h3><span class="dashicons dashicons-shield-alt" aria-hidden="true"></span>' . esc_html__( 'Where your log is saved', 'logpilot' ) . '</h3>';

    if ( $ours_now ) {
        $outside_now = 0 !== strpos( $current, wp_normalize_path( WP_CONTENT_DIR ) . '/' );
        echo '<p>' . esc_html(
            $outside_now
                ? __( 'In a folder with a secret name outside your website folder. No web address can reach it.', 'logpilot' )
                : __( 'In a folder with a secret name inside wp-content, with rules that block access from the web.', 'logpilot' )
        ) . '</p>';
    } elseif ( $active ) {
        echo '<div class="e3p-logpilot-problems"><p><span class="dashicons dashicons-warning" aria-hidden="true"></span> ';
        echo esc_html__( 'The log is in the usual place, wp-content/debug.log: it is the first address anyone would try. Turn logging off and on again here to save it in a protected folder.', 'logpilot' );
        echo '</p></div>';
    } else {
        echo '<p>' . esc_html(
            $plan['outside']
                ? __( 'When you turn logging on, the log is saved in a folder with a secret name outside your website folder, where no web address can reach it.', 'logpilot' )
                : __( 'When you turn logging on, the log is saved in a folder with a secret name inside wp-content, with rules that block access from the web.', 'logpilot' )
        ) . '</p>';
    }

    // Esiti dell'ultimo controllo (solo codici noti).
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    foreach ( array(
        'e3p_logpilot_pr' => 'folder',
        'e3p_logpilot_od' => 'old',
    ) as $param => $group ) {
        $code = isset( $_GET[ $param ] ) ? sanitize_key( wp_unslash( $_GET[ $param ] ) ) : '';
        if ( isset( $check_texts[ $group ][ $code ] ) ) {
            printf(
                '<div class="notice notice-%1$s inline"><p>%2$s</p></div>',
                esc_attr( $check_texts[ $group ][ $code ]['type'] ),
                esc_html( $check_texts[ $group ][ $code ]['text'] )
            );
        }
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="e3p-logpilot-inline-form">';
    echo '<input type="hidden" name="action" value="e3p_logpilot_check" />';
    wp_nonce_field( 'e3p_logpilot_check' );
    echo '<button type="submit" class="button e3p-logpilot-iconbtn"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>' . esc_html__( 'Check that my logs cannot be downloaded', 'logpilot' ) . '</button>';
    echo '</form>';

    // Vecchio debug.log: il primo indirizzo che chiunque proverebbe.
    $old = wp_normalize_path( WP_CONTENT_DIR ) . '/debug.log';
    if ( is_file( $old ) && $old !== $current ) {
        $size = filesize( $old );
        echo '<div class="e3p-logpilot-old"><p><span class="dashicons dashicons-media-text" aria-hidden="true"></span>';
        printf(
            /* translators: %s: file size, e.g. "1.2 MB" */
            esc_html__( 'There is an old debug.log in wp-content (%s). That file name is the first thing people try to download.', 'logpilot' ),
            esc_html( false !== $size ? size_format( $size ) : '—' )
        );
        echo '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="e3p_logpilot_delete_old" />';
        wp_nonce_field( 'e3p_logpilot_delete_old' );
        echo '<p><label><input type="checkbox" name="e3p_logpilot_confirm_delete" value="1" required /> ' . esc_html__( 'Yes, delete wp-content/debug.log permanently.', 'logpilot' ) . '</label></p>';
        echo '<p><button type="submit" class="button e3p-logpilot-iconbtn"><span class="dashicons dashicons-trash" aria-hidden="true"></span>' . esc_html__( 'Delete debug.log', 'logpilot' ) . '</button></p>';
        echo '</form></div>';
    }

    echo '</div>';
}
