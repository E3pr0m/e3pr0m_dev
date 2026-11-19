<?php
// Eseguito da WordPress alla disinstallazione.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Scritture nel database: l'opzione dello spegnimento automatico e del promemoria (#40) e i suoi eventi di WP-Cron.
delete_option( 'e3p_errorlight_settings' );
wp_clear_scheduled_hook( 'e3p_errorlight_auto_off' );
wp_clear_scheduled_hook( 'e3p_errorlight_remind' );

// Cartella del plugin negli upload (#41): `errorlight/`, con dentro la cartella dei log (nome ricavato dai salt del
// sito, come in includes/log-location.php), il file di controllo e il blocco di wp-config.php. I log possono
// contenere dati sensibili e la protezione non sarebbe più garantita, quindi si cancella tutto ciò che crea
// ErrorLight. Si toglie solo ciò che è un file o una cartella vuota, senza seguire collegamenti.
if ( ! function_exists( 'e3p_errorlight_uninstall_remove_dir' ) ) {
    /**
     * Cancella i file di una cartella (senza entrare nelle sottocartelle) e poi la cartella, se è vuota.
     *
     * @param string $dir Cartella.
     */
    function e3p_errorlight_uninstall_remove_dir( $dir ) {
        if ( ! is_dir( $dir ) || is_link( $dir ) ) {
            return;
        }
        $items = scandir( $dir );
        foreach ( false === $items ? array() : $items as $item ) {
            $path = $dir . '/' . $item;
            if ( '.' !== $item && '..' !== $item && is_file( $path ) ) {
                wp_delete_file( $path );
            }
        }
        // Solo se è vuota: se dentro c'è altro, resta.
        rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removes a folder created by this plugin.
    }
}

$e3p_errorlight_folder  = 'e3p-errorlight-' . substr( wp_hash( 'e3p-errorlight-log' ), 0, 16 );
$e3p_errorlight_uploads = wp_upload_dir( null, false );
if ( empty( $e3p_errorlight_uploads['error'] ) && ! empty( $e3p_errorlight_uploads['basedir'] ) ) {
    $e3p_errorlight_base = untrailingslashit( wp_normalize_path( $e3p_errorlight_uploads['basedir'] ) );
    e3p_errorlight_uninstall_remove_dir( $e3p_errorlight_base . '/errorlight/' . $e3p_errorlight_folder );
    e3p_errorlight_uninstall_remove_dir( $e3p_errorlight_base . '/errorlight' );
    // Posto delle versioni di prova, prima della cartella del plugin: direttamente negli upload.
    e3p_errorlight_uninstall_remove_dir( $e3p_errorlight_base . '/' . $e3p_errorlight_folder );
}
