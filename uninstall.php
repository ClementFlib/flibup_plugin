<?php
/**
 * Désinstallation de Flib'Up.
 *
 * Ne supprime les données QUE si l'administrateur a explicitement activé
 * l'option correspondante. Par défaut, les données sont conservées.
 *
 * @package FlibUp
 */

// Sécurité : ce fichier n'est appelé que par WordPress lors de la suppression.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$flibup_settings = get_option( 'flibup_settings', array() );

$flibup_should_delete = is_array( $flibup_settings )
	&& ! empty( $flibup_settings['delete_data_on_uninstall'] );

if ( ! $flibup_should_delete ) {
	return;
}

$flibup_post_type = 'flibup_popup';

// Supprime toutes les pop-ups (et leurs métadonnées).
$flibup_posts = get_posts(
	array(
		'post_type'        => $flibup_post_type,
		'post_status'      => 'any',
		'numberposts'      => -1,
		'fields'           => 'ids',
		'suppress_filters' => true,
	)
);

if ( ! empty( $flibup_posts ) ) {
	foreach ( $flibup_posts as $flibup_id ) {
		wp_delete_post( (int) $flibup_id, true );
	}
}

// Supprime les options du plugin.
delete_option( 'flibup_settings' );

// Supprime les transients du plugin.
delete_transient( 'flibup_active_popups_v1' );
delete_transient( 'flibup_github_release' );

// Nettoyage complémentaire des métadonnées orphelines éventuelles.
global $wpdb;
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\\_flibup\\_%'"
);
