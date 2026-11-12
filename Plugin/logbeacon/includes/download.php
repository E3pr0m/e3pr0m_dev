<?php
/**
 * Scarico di un log (#37).
 *
 * Il log può stare fuori dalla radice web, quindi non c'è un link diretto al file: lo serve PHP.
 * Il percorso non viene mai dall'utente: arriva solo la chiave di un log già noto al plugin
 * (`e3p_logbeacon_get_log_files()`), solo con la capacità del plugin (`manage_options`, su multisito `manage_network_options`) e nonce.
 *
 * @package LogBeacon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Peso massimo che si scarica. Oltre, si scarica la sola parte finale (le righe più recenti).
 *
 * @return int Byte.
 */
function e3p_logbeacon_download_cap() {
	/**
	 * Peso massimo di un download, in byte (minimo 1 MB). Il file viene letto a blocchi: il limite serve
	 * a non tenere occupata la richiesta per troppo tempo.
	 *
	 * @param int $bytes Valore predefinito: 50 MB.
	 */
	return max( MB_IN_BYTES, (int) apply_filters( 'e3p_logbeacon_download_max_bytes', 50 * MB_IN_BYTES ) );
}

/**
 * Ritorna al pannello con l'esito di un'operazione (codice dell'elenco dei messaggi).
 *
 * @param string $result Codice dell'esito.
 * @param string $key    Chiave del log da riaprire (facoltativa).
 */
function e3p_logbeacon_download_redirect( $result, $key = '' ) {
	$args = array(
		'page'            => 'logbeacon',
		'e3p_logbeacon_rs' => $result,
	);
	if ( '' !== $key ) {
		$args['log'] = $key; // Si torna allo stesso log, non al primo dell'elenco.
	}
	// Il frammento fa scorrere la pagina fino alla riga delle azioni, dove compare l'esito.
	wp_safe_redirect( add_query_arg( $args, admin_url( 'tools.php' ) ) . '#e3p-logbeacon-actions' );
	exit;
}

/**
 * Serve il log scelto come file da scaricare.
 */
function e3p_logbeacon_handle_download() {
	if ( ! current_user_can( e3p_logbeacon_capability() ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'logbeacon' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'e3p_logbeacon_download' );

	$key   = isset( $_POST['e3p_logbeacon_log'] ) ? sanitize_key( wp_unslash( $_POST['e3p_logbeacon_log'] ) ) : '';
	$files = e3p_logbeacon_get_log_files();
	if ( ! isset( $files[ $key ] ) || 'ok' !== $files[ $key ]['status'] ) {
		e3p_logbeacon_download_redirect( 'download_none' );
	}

	$path   = $files[ $key ]['path'];
	$size   = filesize( $path );
	$handle = ( false !== $size && $size > 0 ) ? fopen( $path, 'rb' ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streams the log in blocks; WP_Filesystem would load it whole into memory.
	if ( false === $handle ) {
		e3p_logbeacon_download_redirect( 'download_failed', $key );
	}

	// Oltre il tetto: solo la parte finale, a partire da una riga intera.
	$cap       = e3p_logbeacon_download_cap();
	$start     = 0;
	$truncated = $size > $cap;
	if ( $truncated ) {
		fseek( $handle, $size - $cap );
		fgets( $handle, MB_IN_BYTES ); // Scarta la riga rimasta a metà.
		$start = ftell( $handle );
		if ( false === $start || $start >= $size ) {
			$start = $size - $cap;
			fseek( $handle, $start );
		}
	}
	$length = $size - $start;

	$name = sprintf(
		'logbeacon-%1$s%2$s-%3$s.log',
		'php' === $key ? 'error_log' : 'debug-log',
		$truncated ? '-latest' : '',
		wp_date( 'Y-m-d-His' )
	);

	// Niente buffer né compressione: il file va a blocchi, senza tenerlo in memoria.
	while ( ob_get_level() > 0 ) {
		ob_end_clean();
	}
	nocache_headers();
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $name . '"' );
	header( 'Content-Length: ' . $length );
	header( 'X-Content-Type-Options: nosniff' );

	// Si leggono esattamente i byte annunciati: il log può crescere mentre lo si scarica.
	$left = $length;
	while ( $left > 0 && ! feof( $handle ) ) {
		$chunk = fread( $handle, min( 65536, $left ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Streams the log in blocks; WP_Filesystem would load it whole into memory.
		if ( false === $chunk || '' === $chunk ) {
			break;
		}
		echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- File di testo da scaricare, non HTML.
		$left -= strlen( $chunk );
		flush();
	}
	fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Streams the log in blocks; WP_Filesystem would load it whole into memory.
	exit;
}
add_action( 'admin_post_e3p_logbeacon_download', 'e3p_logbeacon_handle_download' );

/**
 * Pulsante di scarico, per la riga delle azioni sotto il riquadro del log che si sta leggendo.
 *
 * @param string $key Chiave del log (`protected`, `debug`, `php`).
 */
function e3p_logbeacon_render_download( $key ) {
	echo '<form class="e3p-logbeacon-download" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="e3p_logbeacon_download" />';
	echo '<input type="hidden" name="e3p_logbeacon_log" value="' . esc_attr( $key ) . '" />';
	wp_nonce_field( 'e3p_logbeacon_download' );
	echo '<button type="submit" class="button e3p-logbeacon-iconbtn"><span class="dashicons dashicons-download" aria-hidden="true"></span>' . esc_html__( 'Download this log', 'logbeacon' ) . '</button>';
	echo '</form>';
}
