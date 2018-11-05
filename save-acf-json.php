<?php
/*
Plugin Name: BEA ACF Save JSON/PHP
Plugin URI: https://github.com/BeAPI/acf-save-json
Description: Choose folder for ACF save auto json field_group
Author: https://beapi.fr
Version: 1.0.3
Author URI: https://beapi.fr

 ----

 Copyright 2016 BE API Technical team (human@beapi.fr)

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

class BEA_ACF_SAVE_JSON_PHP {

	private static $json_file_name;

	public function __construct() {
		add_action( 'wp_loaded', array( __CLASS__, 'replace_acf_field_group_hooks' ) );
		add_action( 'acf/render_field_group_settings', array( __CLASS__, 'acf_json_render_field_group_settings' ) );
		add_filter( 'acf/settings/save_json', array( __CLASS__, 'acf_json_save_point' ), 99 );
		add_filter( 'acf/settings/save_json', array( __CLASS__, 'acf_save_php' ) );
		add_action( 'plugins_loaded', array( __CLASS__, 'acf_json_after_plugins_loaded' ) );
	}

	public static function replace_acf_field_group_hooks() {
		remove_action( 'acf/update_field_group', array( acf()->json, 'update_field_group' ), 10, 1 );
		remove_action( 'acf/duplicate_field_group', array( acf()->json, 'update_field_group' ), 10, 1 );
		remove_action( 'acf/untrash_field_group', array( acf()->json, 'update_field_group' ), 10, 1 );

		add_action( 'acf/update_field_group', array( __CLASS__, 'acf_update_field_group' ), 10, 1 );
		add_action( 'acf/duplicate_field_group', array( __CLASS__, 'acf_update_field_group' ), 10, 1 );
		add_action( 'acf/untrash_field_group', array( __CLASS__, 'acf_update_field_group' ), 10, 1 );
	}

	/**
	 * Add the param to the field group for the path.
	 *
	 * @param array $field_group : The ACF Field Group.
	 *
	 */
	public static function acf_json_render_field_group_settings( $field_group ) {
		acf_render_field_wrap( array(
			'label'        => __( 'Choose path save json (relative to WP_CONTENT_DIR)', 'acf' ),
			'instructions' => __( 'ex : themes/acf/json/filename<strong>.json</strong>', 'acf' ),
			'type'         => 'text',
			'name'         => 'save_json',
			'placeholder'  => 'path/.../acf-group-file-name.php',
			'prefix'       => 'acf_field_group',
			'value'        => $field_group['save_json'],
		) );

		acf_render_field_wrap( array(
			'label'        => __( 'Choose path save php (relative to WP_CONTENT_DIR)', 'acf' ),
			'instructions' => __( 'ex : themes/acf/php/filename<strong>.php</strong>', 'acf' ),
			'type'         => 'text',
			'name'         => 'save_php',
			'placeholder'  => 'path/.../acf-group-file-name.php',
			'prefix'       => 'acf_field_group',
			'value'        => $field_group['save_php'],
		) );
	}

	/**
	 * On ACF save write the JSON file.
	 *
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	public static function acf_json_save_point( $path ) {

		$pathinfo = self::get_path( 'json' );

		if ( ! $pathinfo ) {
			return $path;
		}
		$path = $pathinfo['dirname'];
		// Make dir if does not exist.
		if ( ! file_exists( WP_CONTENT_DIR . $path ) ) {
			wp_mkdir_p( WP_CONTENT_DIR . $path );
		}

		return WP_CONTENT_DIR . $path;
	}

	public static function acf_save_php( $path ) {
		$php_path  = self::get_path( 'php' );
		$json_path = self::get_path( 'json' );

		$field_group = acf_get_field_groups()[0];

		$field_group['save_php']  = $php_path['rendered_path'];
		$field_group['save_json'] = $json_path['rendered_path'];

		self::acf_export_php( $field_group, WP_CONTENT_DIR . $php_path['origin'], $php_path['filename'] . '.' . $php_path['extension'] );

		return WP_CONTENT_DIR . $php_path['origin'];
	}

	/**
	 * Add basic filter.
	 *
	 */
	public static function acf_json_after_plugins_loaded() {
		add_filter( 'acf/settings/load_json', array( __CLASS__, 'acf_json_load' ) );
	}

	/**
	 * Add the path to the loaded json
	 *
	 * @param array $paths : the ACF existing PAth
	 *
	 * @return array
	 */
	public static function acf_json_load( $paths ) {
		$groups = acf_get_field_groups();

		if ( is_file( WP_CONTENT_DIR . $groups[0]['save_json'] ) ) {
			$paths[] = WP_CONTENT_DIR . $groups[0]['save_json'];
		}

		return $paths;

	}

	/*
	 * Export PHP
	 */
	public static function acf_export_php( $field_group, $path, $file_name ) {
		// replace
		$str_replace = array(
			"  "         => "\t",
			"'!!__(!!\'" => "__('",
			"!!\', !!\'" => "', '",
			"!!\')!!'"   => "')"
		);

		$preg_replace = array(
			'/([\t\r\n]+?)array/' => 'array',
			'/[0-9]+ => array/'   => 'array'
		);

		// load fields
		$field_group['fields'] = acf_get_fields( $field_group );

		// prepare for export
		$field_group = acf_prepare_field_group_for_export( $field_group );

		$path = substr( $path, 0, strrpos( $path, '/' ) );
		// code
		$code = var_export( $field_group, true );

		// change double spaces to tabs
		$code = str_replace( array_keys( $str_replace ), array_values( $str_replace ), $code );

		// correctly formats "=> array("
		$code = preg_replace( array_keys( $preg_replace ), array_values( $preg_replace ), $code );

		// echo
		$string = "<?php \nif( function_exists('acf_add_local_field_group') ):" . "\r\n" . "\r\nacf_add_local_field_group({$code});" . "\r\n" . "\r\nendif;";

		// check secure path
		if ( strpos( realpath( $path ), WP_CONTENT_DIR ) === false ) {
			return;
		}

		//make dir if it doesn't exist
		if ( wp_mkdir_p( $path ) ) {
			file_put_contents( $path . '/' . $file_name, $string, LOCK_EX );
		}
	}


	public static function acf_update_field_group( $field_group ) {
		// validate
		if ( ! acf_get_setting( 'json' ) ) {
			return;
		}

		$json_path = self::get_path( 'json' );
		$php_path  = self::get_path( 'php' );

		$field_group['save_php']  = $php_path['rendered_path'];
		$field_group['save_json'] = $json_path['rendered_path'];

		$field_group['key'] = $json_path['filename'];
		// get fields
		$field_group['fields'] = acf_get_fields( $field_group );

		// save file
		acf_write_json_field_group( $field_group );
	}

	/**
	 * verify path type, slashes and put data in a pathinfo array
	 *
	 * @param $type (json | php)
	 *
	 * @return bool|array
	 * @author Aymene Bourafai
	 */
	private static function get_path( $type ) {
		if ( 'json' == $type ) {
			$path = acf_get_field_groups()[0]['save_json'];
		} elseif ( 'php' == $type ) {
			$path = acf_get_field_groups()[0]['save_php'];
		} else {
			return false;
		}

		// remove trailing slash.
		$path = untrailingslashit( $path );

		if ( false !== strpos( $path, '/content' ) ) {
			$path = str_replace( '/content', '', $path );
		}

		if ( '/' !== substr( $path, 0, 1 ) ) {
			$path = '/' . $path;
		}


		$pathinfo = pathinfo( $path );

		$pathinfo['origin']        = $path;
		$pathinfo['extension']     = $type;
		$pathinfo['filename']      = sanitize_file_name( $pathinfo['filename'] );
		$pathinfo['full']          = WP_CONTENT_DIR . $path;
		$pathinfo['rendered_path'] = $pathinfo['dirname'] . '/' . $pathinfo['filename'] . '.' . $pathinfo['extension'];

		return $pathinfo;
	}

}

new BEA_ACF_SAVE_JSON_PHP();
