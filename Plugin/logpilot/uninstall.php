<?php
// Eseguito da WordPress alla disinstallazione.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Scritture nel database: l'opzione dello spegnimento automatico e del promemoria (#40) e i suoi eventi di WP-Cron.
delete_option( 'e3p_logpilot_settings' );
wp_clear_scheduled_hook( 'e3p_logpilot_auto_off' );
wp_clear_scheduled_hook( 'e3p_logpilot_remind' );

// Scrittura su disco: la copia di sicurezza di wp-config.php (accanto a wp-config.php), creata prima di ogni
// modifica, si rimuove qui. Le righe di debug in wp-config.php con il commento "LogPilot" non si toccano:
// sono scelte dell'utente.
foreach ( array( ABSPATH, dirname( ABSPATH ) . '/' ) as $e3p_logpilot_dir ) {
    $e3p_logpilot_backup = $e3p_logpilot_dir . 'wp-config-logpilot-backup.php';
    if ( is_file( $e3p_logpilot_backup ) ) {
        wp_delete_file( $e3p_logpilot_backup );
    }
}

// Cartella dei log (#41): nome ricavato dai salt del sito, come in includes/log-location.php. I log possono
// contenere dati sensibili e la protezione non sarebbe più garantita, quindi si cancella la cartella con tutto
// il suo contenuto (che è solo ciò che crea LogPilot). Si cerca fuori dal sito e in wp-content.
$e3p_logpilot_folder = 'e3p-logpilot-' . substr( wp_hash( 'e3p-logpilot-log' ), 0, 16 );
foreach ( array( dirname( untrailingslashit( wp_normalize_path( ABSPATH ) ) ), wp_normalize_path( WP_CONTENT_DIR ) ) as $e3p_logpilot_base ) {
    $e3p_logpilot_logdir = $e3p_logpilot_base . '/' . $e3p_logpilot_folder;
    if ( ! is_dir( $e3p_logpilot_logdir ) || is_link( $e3p_logpilot_logdir ) ) {
        continue;
    }
    $e3p_logpilot_items = scandir( $e3p_logpilot_logdir );
    foreach ( false === $e3p_logpilot_items ? array() : $e3p_logpilot_items as $e3p_logpilot_item ) {
        $e3p_logpilot_path = $e3p_logpilot_logdir . '/' . $e3p_logpilot_item;
        if ( '.' !== $e3p_logpilot_item && '..' !== $e3p_logpilot_item && is_file( $e3p_logpilot_path ) ) {
            wp_delete_file( $e3p_logpilot_path );
        }
    }
    // Solo se è vuota: se dentro c'è altro, resta.
    rmdir( $e3p_logpilot_logdir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removes the log folder created by this plugin.
}
