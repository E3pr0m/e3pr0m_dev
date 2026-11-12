<?php
/**
 * Posto del log (#41): cartella con nome non indovinabile dentro la cartella degli upload (wp_upload_dir()),
 * con regole di protezione; verifica con una richiesta al sito stesso; controllo ed eliminazione del vecchio
 * wp-content/debug.log.
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
function e3p_logbeacon_log_token() {
    return substr( wp_hash( 'e3p-logbeacon-log' ), 0, 16 );
}

/**
 * Nome della cartella dei log.
 *
 * @return string
 */
function e3p_logbeacon_log_folder_name() {
    return 'e3p-logbeacon-' . e3p_logbeacon_log_token();
}

/**
 * Cartella degli upload del sito (percorso e indirizzo), o null se WordPress non la sa stabilire.
 *
 * @return array{dir: string, url: string}|null
 */
function e3p_logbeacon_uploads() {
    $uploads = wp_upload_dir( null, false );
    if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) || empty( $uploads['baseurl'] ) ) {
        return null;
    }
    return array(
        'dir' => untrailingslashit( wp_normalize_path( $uploads['basedir'] ) ),
        'url' => untrailingslashit( $uploads['baseurl'] ),
    );
}

/**
 * Dove andrebbe il log: in una cartella con nome non indovinabile dentro gli upload. Non crea nulla.
 *
 * @return array{dir: string, file: string}
 */
function e3p_logbeacon_plan_log_location() {
    $uploads = e3p_logbeacon_uploads();
    $base    = $uploads ? $uploads['dir'] : untrailingslashit( wp_normalize_path( WP_CONTENT_DIR ) ) . '/uploads';
    $dir     = $base . '/' . e3p_logbeacon_log_folder_name();

    return array(
        'dir'  => $dir,
        'file' => $dir . '/debug.log',
    );
}

/**
 * Crea la cartella con le regole che negano l'accesso dal web (Apache e IIS) e un index.php vuoto. Nginx ignora
 * questi file: per questo c'è la verifica con la richiesta di prova.
 *
 * @param array{dir: string, file: string} $loc Posto scelto.
 * @return bool True se è tutto a posto.
 */
function e3p_logbeacon_prepare_log_location( $loc ) {
    if ( null === e3p_logbeacon_uploads() ) {
        return false;
    }
    if ( ! wp_mkdir_p( $loc['dir'] ) || ! is_writable( $loc['dir'] ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Checks real write access to wp-config.php or the log folder before changing anything.
        return false;
    }

    $files = array(
        'index.php'  => "<?php\n// Silence is golden.\n",
        '.htaccess'  => "# Written by LogBeacon: nobody can open the logs from a web address.\n"
            . "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n",
        'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n<system.webServer>\n<security>\n<authorization>\n"
            . "<remove users=\"*\" roles=\"\" verbs=\"\" />\n<add accessType=\"Deny\" users=\"*\" />\n</authorization>\n</security>\n</system.webServer>\n</configuration>\n",
    );
    foreach ( $files as $name => $content ) {
        if ( strlen( $content ) !== file_put_contents( $loc['dir'] . '/' . $name, $content ) ) {
            return false;
        }
    }
    return true;
}

/**
 * Sceglie e prepara il posto del log.
 *
 * @return array{dir: string, file: string}|null Posto pronto, o null se non si riesce.
 */
function e3p_logbeacon_setup_log_location() {
    $loc = e3p_logbeacon_plan_log_location();
    return e3p_logbeacon_prepare_log_location( $loc ) ? $loc : null;
}

/**
 * File di log di LogBeacon già presenti.
 *
 * @return string[]
 */
function e3p_logbeacon_existing_log_files() {
    $loc = e3p_logbeacon_plan_log_location();
    // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- open_basedir.
    return @is_file( $loc['file'] ) ? array( $loc['file'] ) : array();
}

/**
 * Una richiesta HTTP al sito stesso. Risposta con codice e corpo, o null se la richiesta non riesce.
 *
 * @param string $url    Indirizzo.
 * @param string $method GET o HEAD.
 * @return array{code: int, body: string}|null
 */
function e3p_logbeacon_http_probe( $url, $method = 'GET' ) {
    $response = wp_remote_request(
        $url,
        array(
            'method'      => $method,
            'timeout'     => 4,
            'redirection' => 0,
            'sslverify'   => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress filter, applied on purpose for the loopback request to this same site.
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
 * Verifica se i log si possono scaricare dal web. Scrive un file di controllo nella cartella degli upload (per
 * essere sicuri che l'indirizzo della richiesta corrisponda alla cartella) e un file di prova nella cartella dei
 * log, li chiede al sito e li cancella subito.
 *
 * @param array{dir: string, file: string} $loc Posto del log nuovo.
 * @return array{folder: string, old: string} `folder`: protected, exposed, unknown. `old`: none, protected, exposed, unknown.
 */
function e3p_logbeacon_run_checks( $loc ) {
    $result = array(
        'folder' => 'unknown',
        'old'    => 'unknown',
    );

    $old_file = wp_normalize_path( WP_CONTENT_DIR ) . '/debug.log';
    $old_here = is_file( $old_file );
    if ( ! $old_here ) {
        $result['old'] = 'none';
    }
    $uploads = e3p_logbeacon_uploads();
    if ( null === $uploads ) {
        return $result;
    }

    // File di controllo nella cartella degli upload (dove i file di un plugin possono stare): se il sito non lo
    // serve, l'indirizzo non corrisponde alla cartella e nessun "403" o "404" può essere interpretato.
    $marker  = 'e3p-logbeacon-probe-' . wp_generate_password( 12, false );
    $control = $uploads['dir'] . '/e3p-logbeacon-control-' . wp_generate_password( 8, false ) . '.txt';
    if ( false === file_put_contents( $control, $marker ) ) {
        return $result;
    }
    $answer = e3p_logbeacon_http_probe( $uploads['url'] . '/' . basename( $control ) );
    wp_delete_file( $control );
    if ( ! $answer || 200 !== $answer['code'] || false === strpos( $answer['body'], $marker ) ) {
        return $result;
    }

    $probe = $loc['dir'] . '/probe.txt';
    if ( is_dir( $loc['dir'] ) && false !== file_put_contents( $probe, $marker ) ) {
        $answer = e3p_logbeacon_http_probe( $uploads['url'] . '/' . basename( $loc['dir'] ) . '/probe.txt' );
        wp_delete_file( $probe );
        if ( $answer ) {
            if ( 200 === $answer['code'] && false !== strpos( $answer['body'], $marker ) ) {
                $result['folder'] = 'exposed';
            } elseif ( in_array( $answer['code'], array( 401, 403, 404 ), true ) ) {
                $result['folder'] = 'protected';
            }
        }
    }

    if ( $old_here ) {
        $answer = e3p_logbeacon_http_probe( content_url( '/debug.log' ), 'HEAD' );
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
function e3p_logbeacon_get_check_texts() {
    return array(
        'folder' => array(
            'protected' => array(
                'type' => 'success',
                'text' => __( 'Checked: your server refuses to hand out the files in the log folder.', 'logbeacon' ),
            ),
            'exposed'   => array(
                'type' => 'error',
                'text' => __( 'Warning: the log folder can be opened from a web address, so anyone who guesses its name could download your logs. Your server probably ignores the protection rules LogBeacon writes (this is common on Nginx). Ask your hosting provider to block access to this folder.', 'logbeacon' ),
            ),
            'unknown'   => array(
                'type' => 'warning',
                'text' => __( 'LogBeacon could not check whether the log folder is protected: the site did not answer its own test request. This is common on shared hosting and does not mean the folder is exposed. The folder has a secret name and protection rules, but this has not been verified.', 'logbeacon' ),
            ),
        ),
        'old'    => array(
            'none'      => array(
                'type' => 'info',
                'text' => __( 'There is no old debug.log in wp-content.', 'logbeacon' ),
            ),
            'protected' => array(
                'type' => 'success',
                'text' => __( 'Checked: the old wp-content/debug.log cannot be downloaded from a web address.', 'logbeacon' ),
            ),
            'exposed'   => array(
                'type' => 'error',
                'text' => __( 'Warning: the old wp-content/debug.log can be downloaded by anyone who opens its web address. Delete it with the button below.', 'logbeacon' ),
            ),
            'unknown'   => array(
                'type' => 'warning',
                'text' => __( 'LogBeacon could not check whether the old wp-content/debug.log can be downloaded.', 'logbeacon' ),
            ),
        ),
    );
}

/**
 * Pulsante "Check": controlla il posto del log e il vecchio debug.log, poi torna al pannello con l'esito.
 */
function e3p_logbeacon_handle_check() {
    if ( ! current_user_can( e3p_logbeacon_capability() ) ) {
        wp_die( esc_html__( 'You do not have permission to do this.', 'logbeacon' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'e3p_logbeacon_check' );

    $loc    = e3p_logbeacon_plan_log_location();
    $checks = e3p_logbeacon_run_checks( $loc );

    wp_safe_redirect(
        add_query_arg(
            array(
                'page'            => 'logbeacon',
                'e3p_logbeacon_pr' => $checks['folder'],
                'e3p_logbeacon_od' => $checks['old'],
            ),
            admin_url( 'tools.php' )
        )
    );
    exit;
}
add_action( 'admin_post_e3p_logbeacon_check', 'e3p_logbeacon_handle_check' );

/**
 * Elimina il vecchio wp-content/debug.log. Solo quel file, con conferma esplicita.
 */
function e3p_logbeacon_handle_delete_old() {
    if ( ! current_user_can( e3p_logbeacon_capability() ) ) {
        wp_die( esc_html__( 'You do not have permission to do this.', 'logbeacon' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'e3p_logbeacon_delete_old' );

    $file = wp_normalize_path( WP_CONTENT_DIR ) . '/debug.log';
    if ( empty( $_POST['e3p_logbeacon_confirm_delete'] ) ) {
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
                'page'            => 'logbeacon',
                'e3p_logbeacon_rs' => $result,
            ),
            admin_url( 'tools.php' )
        )
    );
    exit;
}
add_action( 'admin_post_e3p_logbeacon_delete_old', 'e3p_logbeacon_handle_delete_old' );

/**
 * Come controllare a mano che la cartella dei log non si possa scaricare, quando il controllo automatico non riesce
 * (il sito non sa chiamare sé stesso: succede su molti hosting condivisi).
 *
 * @param string $name Nome della cartella dei log.
 */
function e3p_logbeacon_render_manual_check( $name ) {
    $uploads = e3p_logbeacon_uploads();
    $url     = ( $uploads ? $uploads['url'] : content_url( '/uploads' ) ) . '/' . $name . '/debug.log';
    echo '<details class="e3p-logbeacon-details" open><summary><span class="dashicons dashicons-editor-help" aria-hidden="true"></span>' . esc_html__( 'How to check it yourself', 'logbeacon' ) . '</summary><ol>';
    echo '<li>' . esc_html__( 'Make sure the log exists: it appears in the list of logs above after WordPress writes its first message.', 'logbeacon' ) . '</li>';
    echo '<li>' . esc_html__( 'Open this address in a private browser window:', 'logbeacon' ) . ' <code class="e3p-logbeacon-problem-line">' . esc_html( $url ) . '</code></li>';
    echo '<li>' . esc_html__( 'A “Forbidden” (403) or “Not found” (404) page means the folder is protected. If you can read the log, turn logging off and ask your hosting provider to block access to this folder.', 'logbeacon' ) . '</li>';
    echo '</ol></details>';
}

/**
 * Parte del pannello: dove viene salvato il log, controllo di protezione e vecchio debug.log.
 *
 * @param bool $active True se il debug è acceso adesso.
 */
function e3p_logbeacon_render_location_section( $active ) {
    $check_texts = e3p_logbeacon_get_check_texts();
    $name        = e3p_logbeacon_log_folder_name();

    // Dove scrive WordPress adesso (se è acceso) e dove scriverebbe con LogBeacon.
    $current = '';
    if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
        $current = is_string( WP_DEBUG_LOG ) ? wp_normalize_path( WP_DEBUG_LOG ) : wp_normalize_path( WP_CONTENT_DIR ) . '/debug.log';
    }
    $ours_now = $active && false !== strpos( $current, '/' . $name . '/' );

    echo '<div class="e3p-logbeacon-location"><h3><span class="dashicons dashicons-shield-alt" aria-hidden="true"></span>' . esc_html__( 'Where your log is saved', 'logbeacon' ) . '</h3>';

    if ( $ours_now ) {
        echo '<p>' . esc_html__( 'In a folder with a secret name inside your uploads folder, with rules that block access from the web.', 'logbeacon' ) . '</p>';
    } elseif ( $active ) {
        echo '<div class="e3p-logbeacon-problems"><p><span class="dashicons dashicons-warning" aria-hidden="true"></span> ';
        echo esc_html__( 'The log is in the usual place, wp-content/debug.log: it is the first address anyone would try. Turn logging off and on again here to save it in a protected folder.', 'logbeacon' );
        echo '</p></div>';
    } else {
        echo '<p>' . esc_html__( 'When you turn logging on, the log is saved in a folder with a secret name inside your uploads folder, with rules that block access from the web.', 'logbeacon' ) . '</p>';
    }

    // Esiti dell'ultimo controllo (solo codici noti).
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    foreach ( array(
        'e3p_logbeacon_pr' => 'folder',
        'e3p_logbeacon_od' => 'old',
    ) as $param => $group ) {
        $code = isset( $_GET[ $param ] ) ? sanitize_key( wp_unslash( $_GET[ $param ] ) ) : '';
        if ( isset( $check_texts[ $group ][ $code ] ) ) {
            printf(
                '<div class="notice notice-%1$s inline"><p>%2$s</p></div>',
                esc_attr( $check_texts[ $group ][ $code ]['type'] ),
                esc_html( $check_texts[ $group ][ $code ]['text'] )
            );
            if ( 'folder' === $group && 'unknown' === $code ) {
                e3p_logbeacon_render_manual_check( $name );
            }
        }
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="e3p-logbeacon-inline-form">';
    echo '<input type="hidden" name="action" value="e3p_logbeacon_check" />';
    wp_nonce_field( 'e3p_logbeacon_check' );
    echo '<button type="submit" class="button e3p-logbeacon-iconbtn"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>' . esc_html__( 'Check that my logs cannot be downloaded', 'logbeacon' ) . '</button>';
    echo '</form>';

    // Vecchio debug.log: il primo indirizzo che chiunque proverebbe.
    $old = wp_normalize_path( WP_CONTENT_DIR ) . '/debug.log';
    if ( is_file( $old ) && $old !== $current ) {
        $size = filesize( $old );
        echo '<div class="e3p-logbeacon-old"><p><span class="dashicons dashicons-media-text" aria-hidden="true"></span>';
        printf(
            /* translators: %s: file size, e.g. "1.2 MB" */
            esc_html__( 'There is an old debug.log in wp-content (%s). That file name is the first thing people try to download.', 'logbeacon' ),
            esc_html( false !== $size ? size_format( $size ) : '—' )
        );
        echo '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="e3p_logbeacon_delete_old" />';
        wp_nonce_field( 'e3p_logbeacon_delete_old' );
        echo '<p><label><input type="checkbox" name="e3p_logbeacon_confirm_delete" value="1" required /> ' . esc_html__( 'Yes, delete wp-content/debug.log permanently.', 'logbeacon' ) . '</label></p>';
        echo '<p><button type="submit" class="button e3p-logbeacon-iconbtn"><span class="dashicons dashicons-trash" aria-hidden="true"></span>' . esc_html__( 'Delete debug.log', 'logbeacon' ) . '</button></p>';
        echo '</form></div>';
    }

    echo '</div>';
}
