<?php
// Eseguito da WordPress alla disinstallazione: rimuove l'unica opzione del plugin.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'e3p_amb_settings' );
