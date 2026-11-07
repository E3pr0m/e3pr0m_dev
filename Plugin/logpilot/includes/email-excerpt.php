<?php
/**
 * Estratto del log per email (#38).
 *
 * Il plugin invia solo la propria mail, con wp_mail() chiamato direttamente: non aggancia wp_mail né altri
 * filtri generici sulle mail del sito. L'indirizzo lo sceglie l'utente a ogni invio: l'email
 * dell'amministratore è solo una proposta nel campo e non si salva da nessuna parte. Il log può contenere dati
 * personali, quindi serve una conferma esplicita e, per default, si tolgono indirizzi, percorsi e IP.
 *
 * @package LogPilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Quante righe finali si possono inviare.
 *
 * @return int[]
 */
function e3p_logpilot_email_line_choices() {
	return array( 20, 50, 100 );
}

/**
 * Peso massimo del testo nella mail, in byte: oltre, si tiene la parte finale.
 *
 * @return int
 */
function e3p_logpilot_email_max_bytes() {
	/**
	 * Peso massimo del testo del log in una mail, in byte (tra 10 KB e 1 MB).
	 *
	 * @param int $bytes Valore predefinito: 100 KB.
	 */
	return min( MB_IN_BYTES, max( 10 * KB_IN_BYTES, (int) apply_filters( 'e3p_logpilot_email_max_bytes', 100 * KB_IN_BYTES ) ) );
}

/**
 * Ultime righe di un file, per la mail. Legge solo la coda del file.
 *
 * @param string $path     Percorso del log.
 * @param int    $lines    Numero di righe finali.
 * @param bool   $scrub    Se togliere indirizzi, percorsi e IP.
 * @return string Testo (vuoto se non c'è nulla da inviare).
 */
function e3p_logpilot_email_excerpt( $path, $lines, $scrub ) {
	// Le righe possono essere lunghissime (stack trace): si legge una coda più larga del tetto della mail.
	$tail = e3p_logpilot_read_tail( $path, 4 * e3p_logpilot_email_max_bytes() );
	if ( false === $tail || '' === $tail['content'] ) {
		return '';
	}
	$rows = preg_split( '/\r\n|\n|\r/', $tail['content'] );
	if ( $tail['truncated'] ) {
		array_shift( $rows ); // La prima riga della coda può essere a metà.
	}
	while ( $rows && '' === trim( (string) end( $rows ) ) ) {
		array_pop( $rows );
	}
	$text = implode( "\n", array_slice( $rows, -$lines ) );

	$max = e3p_logpilot_email_max_bytes();
	if ( strlen( $text ) > $max ) {
		$text = substr( $text, -$max );
		$nl   = strpos( $text, "\n" );
		$text = false === $nl ? $text : substr( $text, $nl + 1 ); // Riparte da una riga intera.
	}
	if ( $scrub ) {
		$text = e3p_logpilot_scrub( $text );
	}
	return $text;
}

/**
 * Invia l'estratto all'indirizzo indicato, poi torna al pannello con l'esito.
 */
function e3p_logpilot_handle_email() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'logpilot' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'e3p_logpilot_email' );

	$key   = isset( $_POST['e3p_logpilot_log'] ) ? sanitize_key( wp_unslash( $_POST['e3p_logpilot_log'] ) ) : '';
	$files = e3p_logpilot_get_log_files();
	if ( ! isset( $files[ $key ] ) || 'ok' !== $files[ $key ]['status'] ) {
		e3p_logpilot_download_redirect( 'email_none' );
	}

	$to = isset( $_POST['e3p_logpilot_to'] ) ? sanitize_email( wp_unslash( $_POST['e3p_logpilot_to'] ) ) : '';
	if ( ! is_email( $to ) ) {
		e3p_logpilot_download_redirect( 'email_invalid', $key );
	}
	if ( empty( $_POST['e3p_logpilot_consent'] ) ) {
		e3p_logpilot_download_redirect( 'email_consent', $key );
	}

	$lines = isset( $_POST['e3p_logpilot_lines'] ) ? absint( wp_unslash( $_POST['e3p_logpilot_lines'] ) ) : 0;
	if ( ! in_array( $lines, e3p_logpilot_email_line_choices(), true ) ) {
		$lines = 50;
	}
	$scrub = ! empty( $_POST['e3p_logpilot_scrub'] );

	$excerpt = e3p_logpilot_email_excerpt( $files[ $key ]['path'], $lines, $scrub );
	if ( '' === $excerpt ) {
		e3p_logpilot_download_redirect( 'email_none', $key );
	}

	$site    = preg_replace( '/\s+/', ' ', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
	$subject = sprintf(
		/* translators: 1: site name, 2: log name, e.g. "PHP error_log" */
		__( '[%1$s] LogPilot: excerpt of the %2$s', 'logpilot' ),
		$site,
		$files[ $key ]['label']
	);
	$body = sprintf(
		/* translators: 1: number of lines, 2: log name, 3: site address */
		__( "This is the end of the %2\$s (up to %1\$d lines), sent from %3\$s with LogPilot.\n", 'logpilot' ),
		$lines,
		$files[ $key ]['label'],
		home_url()
	);
	$body .= $scrub
		? __( "Web addresses, e-mail addresses, file paths and IP addresses were replaced with placeholders.\n", 'logpilot' )
		: __( "The text is sent as it is in the log: it may contain personal data.\n", 'logpilot' );
	$body .= "\n----------------------------------------\n" . $excerpt . "\n----------------------------------------\n";

	// wp_mail() chiamato direttamente: LogPilot non aggancia le mail del sito.
	$sent = wp_mail( $to, $subject, $body );

	e3p_logpilot_download_redirect( $sent ? 'email_sent' : 'email_failed', $key );
}
add_action( 'admin_post_e3p_logpilot_email', 'e3p_logpilot_handle_email' );

/**
 * Dice se un codice d'esito riguarda "scarica" o "email": quelli si mostrano nella riga delle azioni.
 *
 * @param string $code Codice dell'esito.
 * @return bool
 */
function e3p_logpilot_is_action_result( $code ) {
	return 0 === strpos( $code, 'email_' ) || 0 === strpos( $code, 'download_' );
}

/**
 * Riga delle azioni sul log che si sta leggendo: scarica ed email, una accanto all'altra.
 * Il modulo dell'email si apre sotto la riga con il suo pulsante.
 *
 * @param string $key  Chiave del log (`protected`, `debug`, `php`).
 * @param int    $size Peso del file in byte.
 */
function e3p_logpilot_render_actions( $key, $size ) {
	if ( $size <= 0 ) {
		return;
	}
	$cap = e3p_logpilot_download_cap();

	// Esito dell'ultima azione (scarica o email), se il codice nell'indirizzo è uno di quelli noti.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$code  = isset( $_GET['e3p_logpilot_rs'] ) ? sanitize_key( wp_unslash( $_GET['e3p_logpilot_rs'] ) ) : '';
	$texts = e3p_logpilot_get_result_texts();
	$open  = in_array( $code, array( 'email_invalid', 'email_consent', 'email_failed', 'email_none' ), true );

	echo '<div class="e3p-logpilot-actions" id="e3p-logpilot-actions">';
	echo '<div class="e3p-logpilot-actions-row">';
	e3p_logpilot_render_download( $key );
	echo '<button type="button" class="button e3p-logpilot-iconbtn e3p-logpilot-mail-toggle" aria-expanded="' . ( $open ? 'true' : 'false' ) . '" aria-controls="e3p-logpilot-mail-panel"><span class="dashicons dashicons-email-alt" aria-hidden="true"></span>' . esc_html__( 'Email an excerpt', 'logpilot' ) . '</button>';
	echo '</div>';
	if ( isset( $texts[ $code ] ) && e3p_logpilot_is_action_result( $code ) ) {
		printf(
			'<div class="notice notice-%1$s inline" role="%2$s"><p>%3$s</p></div>',
			esc_attr( $texts[ $code ]['type'] ),
			'success' === $texts[ $code ]['type'] ? 'status' : 'alert',
			esc_html( $texts[ $code ]['text'] )
		);
	}
	if ( $size > $cap ) {
		printf(
			/* translators: %s: maximum download size, e.g. "50 MB" */
			'<p class="description">' . esc_html__( 'This log is large: the download contains only its latest part (up to %s).', 'logpilot' ) . '</p>',
			esc_html( size_format( $cap ) )
		);
	}
	echo '<p class="description">' . esc_html__( 'The file may contain file paths, user names or other personal data. Keep it private and share it only with people you trust.', 'logpilot' ) . '</p>';
	e3p_logpilot_render_email_form( $key, $open );
	echo '</div>';
}

/**
 * Modulo "Email an excerpt": nascosto finché non si preme il pulsante della riga delle azioni.
 *
 * @param string $key  Chiave del log (`protected`, `debug`, `php`).
 * @param bool   $open Se il modulo parte aperto (dopo un errore, per riprovare).
 */
function e3p_logpilot_render_email_form( $key, $open = false ) {
	$admin = get_option( 'admin_email' ); // Solo una proposta nel campo, modificabile: non si salva nulla.

	echo '<div class="e3p-logpilot-mail" id="e3p-logpilot-mail-panel"' . ( $open ? '' : ' hidden' ) . '>';
	echo '<form method="post" data-sending="' . esc_attr__( 'Sending…', 'logpilot' ) . '" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="e3p_logpilot_email" />';
	echo '<input type="hidden" name="e3p_logpilot_log" value="' . esc_attr( $key ) . '" />';
	wp_nonce_field( 'e3p_logpilot_email' );

	echo '<p><label for="e3p-logpilot-to"><strong>' . esc_html__( 'Send it to', 'logpilot' ) . '</strong></label><br />';
	echo '<input type="email" id="e3p-logpilot-to" name="e3p_logpilot_to" class="regular-text" required value="' . esc_attr( is_string( $admin ) ? $admin : '' ) . '" /></p>';

	echo '<p><label for="e3p-logpilot-lines"><strong>' . esc_html__( 'How much of the log', 'logpilot' ) . '</strong></label><br />';
	echo '<select id="e3p-logpilot-lines" name="e3p_logpilot_lines">';
	foreach ( e3p_logpilot_email_line_choices() as $n ) {
		printf(
			'<option value="%1$d"%2$s>%3$s</option>',
			(int) $n,
			selected( 50, $n, false ),
			/* translators: %d: number of lines */
			esc_html( sprintf( _n( 'The last %d line', 'The last %d lines', $n, 'logpilot' ), $n ) )
		);
	}
	echo '</select></p>';

	echo '<p><label><input type="checkbox" name="e3p_logpilot_scrub" value="1" checked="checked" /> <span class="dashicons dashicons-privacy" aria-hidden="true"></span><strong>' . esc_html__( 'Protect my privacy (recommended):', 'logpilot' ) . '</strong> ' . esc_html__( 'replace web addresses, e-mail addresses, file paths and IP addresses with placeholders, like [email] or [path].', 'logpilot' ) . '</label></p>';
	echo '<p><label><input type="checkbox" name="e3p_logpilot_consent" value="1" required /> ' . esc_html__( 'I understand that a log can contain personal data and I want to send this excerpt to this address.', 'logpilot' ) . '</label></p>';

	echo '<p><button type="submit" class="button e3p-logpilot-iconbtn"><span class="dashicons dashicons-email-alt" aria-hidden="true"></span>' . esc_html__( 'Send the excerpt', 'logpilot' ) . '</button></p>';
	echo '<p class="description">' . esc_html__( 'The address is used only for this message and is not saved. If your site cannot send e-mail, the message will not arrive.', 'logpilot' ) . '</p>';
	echo '</form></div>';
}
