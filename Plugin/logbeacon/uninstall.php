<?php
// Eseguito da WordPress alla disinstallazione.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Scritture nel database: l'opzione dello spegnimento automatico e del promemoria (#40) e i suoi eventi di WP-Cron.
delete_option( 'e3p_logbeacon_settings' );
wp_clear_scheduled_hook( 'e3p_logbeacon_auto_off' );
wp_clear_scheduled_hook( 'e3p_logbeacon_remind' );

// Cartella dei log (#41): nome ricavato dai salt del sito, come in includes/log-location.php. I log possono
// contenere dati sensibili e la protezione non sarebbe più garantita, quindi si cancella la cartella con tutto
// il suo contenuto (che è solo ciò che crea LogBeacon). Sta dentro la cartella degli upload.
$e3p_logbeacon_folder = 'e3p-logbeacon-' . substr( wp_hash( 'e3p-logbeacon-log' ), 0, 16 );
$e3p_logbeacon_uploads = wp_upload_dir( null, false );
if ( empty( $e3p_logbeacon_uploads['error'] ) && ! empty( $e3p_logbeacon_uploads['basedir'] ) ) {
    $e3p_logbeacon_logdir = untrailingslashit( wp_normalize_path( $e3p_logbeacon_uploads['basedir'] ) ) . '/' . $e3p_logbeacon_folder;
    if ( ! is_dir( $e3p_logbeacon_logdir ) || is_link( $e3p_logbeacon_logdir ) ) {
        return;
    }
    $e3p_logbeacon_items = scandir( $e3p_logbeacon_logdir );
    foreach ( false === $e3p_logbeacon_items ? array() : $e3p_logbeacon_items as $e3p_logbeacon_item ) {
        $e3p_logbeacon_path = $e3p_logbeacon_logdir . '/' . $e3p_logbeacon_item;
        if ( '.' !== $e3p_logbeacon_item && '..' !== $e3p_logbeacon_item && is_file( $e3p_logbeacon_path ) ) {
            wp_delete_file( $e3p_logbeacon_path );
        }
    }
    // Solo se è vuota: se dentro c'è altro, resta.
    rmdir( $e3p_logbeacon_logdir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removes the log folder created by this plugin.
}
