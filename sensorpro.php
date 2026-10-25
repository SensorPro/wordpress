<?php
/**
 * Plugin Name:       SensorPro Signup Forms
 * Plugin URI:        https://github.com/SensorPro/wordpress
 * Description:       Add a GDPR-friendly signup form to your site that adds contacts straight into your SensorPro account.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            SensorPro
 * Author URI:        https://sensorpro.net/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sensorpro
 *
 * @package SensorPro
 */

/*
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SENSORPRO_VERSION', '1.0.1' );
define( 'SENSORPRO_FILE', __FILE__ );
define( 'SENSORPRO_DIR', plugin_dir_path( __FILE__ ) );
define( 'SENSORPRO_URL', plugin_dir_url( __FILE__ ) );
define( 'SENSORPRO_OPTION', 'sensorpro_settings' );

require_once SENSORPRO_DIR . 'includes/class-sensorpro-api.php';
require_once SENSORPRO_DIR . 'includes/class-sensorpro-settings.php';
require_once SENSORPRO_DIR . 'includes/class-sensorpro-form.php';
require_once SENSORPRO_DIR . 'includes/class-sensorpro-widget.php';

/**
 * Default settings.
 *
 * @return array
 */
function sensorpro_default_settings() {
	return array(
		'api_base'        => 'https://apinie.sensorpro.net',
		'api_key'         => '',
		'organization'    => '',
		'api_user'        => '',
		'api_password'    => '',
		'tags'            => '',
		'import_name'     => 'WordPress',
		'send_welcome'    => 0,
		'show_first_name' => 1,
		'show_last_name'  => 1,
		'require_names'   => 0,
		'consent_enabled' => 1,
		'consent_text'    => __( 'Yes, I would like to receive emails from us. I can unsubscribe at any time using the link in every email.', 'sensorpro' ),
		'consent_field'   => '',
		'button_text'     => __( 'Subscribe', 'sensorpro' ),
		'success_message' => __( 'Thanks! You are on the list.', 'sensorpro' ),
		'redirect_url'    => '',
	);
}

/**
 * Get merged settings.
 *
 * @return array
 */
function sensorpro_get_settings() {
	$saved = get_option( SENSORPRO_OPTION, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return array_merge( sensorpro_default_settings(), $saved );
}

add_action(
	'plugins_loaded',
	function () {
		SensorPro_Settings::init();
		SensorPro_Form::init();
	}
);

add_action(
	'widgets_init',
	function () {
		register_widget( 'SensorPro_Widget' );
	}
);

add_action(
	'init',
	function () {
		// Lets the block editor preview use the same styles as the front end.
		wp_register_style( 'sensorpro-form-editor', SENSORPRO_URL . 'assets/css/sensorpro.css', array(), SENSORPRO_VERSION );
		register_block_type(
			SENSORPRO_DIR . 'blocks/sensorpro-form',
			array(
				'render_callback' => array( 'SensorPro_Form', 'render_block' ),
			)
		);
	}
);

register_activation_hook(
	__FILE__,
	function () {
		if ( false === get_option( SENSORPRO_OPTION ) ) {
			add_option( SENSORPRO_OPTION, sensorpro_default_settings(), '', false );
		}
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		$url = admin_url( 'options-general.php?page=sensorpro' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'sensorpro' ) . '</a>' );
		return $links;
	}
);
