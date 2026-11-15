<?php
// Eseguito da WordPress alla disinstallazione.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Scritture nel database: l'opzione dello spegnimento automatico e del promemoria (#40) e i suoi eventi di WP-Cron.
delete_option( 'e3p_errorlight_settings' );
wp_clear_scheduled_hook( 'e3p_errorlight_auto_off' );
wp_clear_scheduled_hook( 'e3p_errorlight_remind' );

// Cartella dei log (#41): nome ricavato dai salt del sito, come in includes/log-location.php. I log possono
// contenere dati sensibili e la protezione non sarebbe più garantita, quindi si cancella la cartella con tutto
// il suo contenuto (che è solo ciò che crea ErrorLight). Sta dentro la cartella degli upload.
$e3p_errorlight_folder = 'e3p-errorlight-' . substr( wp_hash( 'e3p-errorlight-log' ), 0, 16 );
$e3p_errorlight_uploads = wp_upload_dir( null, false );
if ( empty( $e3p_errorlight_uploads['error'] ) && ! empty( $e3p_errorlight_uploads['basedir'] ) ) {
    $e3p_errorlight_logdir = untrailingslashit( wp_normalize_path( $e3p_errorlight_uploads['basedir'] ) ) . '/' . $e3p_errorlight_folder;
    if ( ! is_dir( $e3p_errorlight_logdir ) || is_link( $e3p_errorlight_logdir ) ) {
        return;
    }
    $e3p_errorlight_items = scandir( $e3p_errorlight_logdir );
    foreach ( false === $e3p_errorlight_items ? array() : $e3p_errorlight_items as $e3p_errorlight_item ) {
        $e3p_errorlight_path = $e3p_errorlight_logdir . '/' . $e3p_errorlight_item;
        if ( '.' !== $e3p_errorlight_item && '..' !== $e3p_errorlight_item && is_file( $e3p_errorlight_path ) ) {
            wp_delete_file( $e3p_errorlight_path );
        }
    }
    // Solo se è vuota: se dentro c'è altro, resta.
    rmdir( $e3p_errorlight_logdir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removes the log folder created by this plugin.
}
