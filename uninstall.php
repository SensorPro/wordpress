<?php
/**
 * Uninstall: remove all plugin data.
 *
 * @package SensorPro
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'sensorpro_settings' );
delete_transient( 'sensorpro_token' );
delete_transient( 'sensorpro_signin_block' );

global $wpdb;
// Remove per-IP rate-limit transients.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_sensorpro\\_rl\\_%' OR option_name LIKE '\\_transient\\_timeout\\_sensorpro\\_rl\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
