<?php
/*
Plugin Name: BEA ACF JSON save/load
Plugin URI: https://github.com/BeAPI/acf-save-json
Description: Choose folder for ACF save auto json field_group
Author: https://beapi.fr
Version: 1.0.1
Author URI: https://beapi.fr
*/

add_action( 'acf/render_field_group_settings', 'bea_acf_json_render_field_group_settings' );

/**
 * Add the param to the field group for the path.
 *
 * @param array $field_group : The ACF Field Group.
 *
 */
function bea_acf_json_render_field_group_settings( $field_group ) {
	acf_render_field_wrap( array(
		'label'        => __( 'Choose path save json (relative to WP_CONTENT_DIR)', 'acf' ),
		'instructions' => __( 'ex : plugins/ or themes/xxx/', 'acf' ),
		'type'         => 'text',
		'name'         => 'save_json',
		'prefix'       => 'acf_field_group',
		'value'        => $field_group['save_json'],
	) );
}

add_filter( 'acf/settings/save_json', 'bea_acf_json_save_point' );

/**
 * On ACF save write the JSON file.
 *
 *
 * @param string $path
 *
 * @return string
 */
function bea_acf_json_save_point( $path ) {

	if ( ! isset( $_POST['acf_field_group']['save_json'] ) ) {
		return $path;
	}

	$path_save_json_by_field_group = $_POST['acf_field_group']['save_json'];

	if ( empty( $path_save_json_by_field_group ) ) {
		return $path;
	}

	// remove trailing slash.
	$path = untrailingslashit( $path_save_json_by_field_group );

	if ( false !== strpos( $path, '/content' ) ) {
		$path = str_replace( '/content', '', $path );
	}

	if ( '/' !== substr( $path, 0, 1 ) ) {
		$path = '/' . $path;
	}

	// Make dir if does not exist.
	if ( ! file_exists( WP_CONTENT_DIR . $path ) ) {
		wp_mkdir_p( WP_CONTENT_DIR . $path );
	}

	$paths_save_acf_json = get_option( 'paths_save_acf_json' );
	if ( empty( $paths_save_acf_json ) ) {
		update_option( 'paths_save_acf_json', array( WP_CONTENT_DIR . $path ) );
	} else {
		update_option( 'paths_save_acf_json', array_merge( $paths_save_acf_json, array( WP_CONTENT_DIR . $path ) ) );
	}

	return WP_CONTENT_DIR . $path;
}

add_action( 'plugins_loaded', 'bea_acf_json_after_plugins_loaded' );
/**
 * Add basic filter.
 *
 */
function bea_acf_json_after_plugins_loaded() {
	add_filter( 'acf/settings/load_json', 'bea_acf_json_load' );
}

/**
 * Launch the plugin on acf load json.
 * Append the
 */

/**
 * Add the path to the loaded json
 *
 * @param array $paths : the ACF existing PAth
 *
 * @return array
 */
function bea_acf_json_load( $paths ) {
	$paths_save_acf_json = get_option( 'paths_save_acf_json' );

	if ( $paths_save_acf_json ) {
		foreach ( $paths_save_acf_json as $path ) {
			$paths[] = $path;
		}
	}

	return $paths;
}
