<?php

/**
 * Plugin Name: BEA ACF Save JSON/PHP
 * Plugin URI: https://github.com/BeAPI/acf-save-json
 * Description: Choose folder for ACF save auto json field_group
 * Author: https://beapi.fr
 * Version: 1.1.0
 * Author URI: https://beapi.fr
 *
 * ----
 *
 * Copyright 2016 BE API Technical team (human@beapi.fr)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */
class BEA_ACF_SAVE_JSON_PHP {

	/**
	 * The current JSON loaded.
	 *
	 * @var array $current_json ;
	 */
	private static $current_json;

	/**
	 * The current PHP loaded.
	 *
	 * @var array $current_php ;
	 */
	private static $current_php;

	/**
	 * The current ACF Group loaded.
	 *
	 * @var array $current_group ;
	 */
	private static $current_group;

	public function __construct() {
		add_action( 'wp_loaded', array( __CLASS__, 'replace_acf_field_group_hooks' ) );
		add_action( 'acf/render_field_group_settings', array( __CLASS__, 'acf_json_render_field_group_settings' ) );

		add_action( 'plugins_loaded', array( __CLASS__, 'acf_json_after_plugins_loaded' ) );
	}

	/**
	 * Remove hooks from.
	 */
	public static function replace_acf_field_group_hooks() {
		add_action( 'acf/update_field_group', array( __CLASS__, 'acf_update_field_group' ), 15, 1 );
		add_action( 'acf/duplicate_field_group', array( __CLASS__, 'acf_update_field_group' ), 15, 1 );
		add_action( 'acf/untrash_field_group', array( __CLASS__, 'acf_update_field_group' ), 15, 1 );
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
			'placeholder'  => 'path/acf-group-file-name.json',
			'prefix'       => 'acf_field_group',
			'value'        => $field_group['save_json'],
		) );

		acf_render_field_wrap( array(
			'label'        => __( 'Choose path save php (relative to WP_CONTENT_DIR)', 'acf' ),
			'instructions' => __( 'ex : themes/acf/php/filename<strong>.php</strong>', 'acf' ),
			'type'         => 'text',
			'name'         => 'save_php',
			'placeholder'  => 'path/acf-group-file-name.php',
			'prefix'       => 'acf_field_group',
			'value'        => $field_group['save_php'],
		) );
	}

	/**
	 * Redefine the ACF path folder to save our file.
	 *
	 * @param string $path : the path where to save the file.
	 *
	 * @return string
	 */
	public static function acf_json_save_point( $path ) {

		// Check Current json.
		if ( empty( self::$current_json ) || empty( self::$current_group ) ) {
			return $path;
		}

		// Make dir if does not exist.
		if ( ! file_exists( self::$current_json['full_dirname'] ) ) {
			wp_mkdir_p( self::$current_json['full_dirname'] );
		}

		return self::$current_json['full_dirname'];
	}

	/**
	 * Add the custom ACF files to the list of files to load.
	 */
	public static function acf_json_after_plugins_loaded() {
		add_filter( 'acf/settings/load_json', array( __CLASS__, 'acf_json_load' ) );
	}

	/**
	 * Add the path to the loaded json
	 *
	 * @param array $paths : the ACF existing Path.
	 *
	 * @return array
	 */
	public static function acf_json_load( $paths ) {
		$groups = acf_get_field_groups();

		foreach ( $groups as $group ) {
			if ( ! isset( $group['save_json'] ) ) {
				continue;
			}

			if ( is_file( trailingslashit( WP_CONTENT_DIR ) . $group['save_json'] ) ) {
				$paths[] = trailingslashit( WP_CONTENT_DIR ) . $group['save_json'];
			}
		}

		return $paths;
	}

	/*
	 * Export PHP
	 */
	private static function acf_export_php() {
		// Extracted FROM : \ACF_Admin_Tool_Export::html_generate.
		// replace
		$str_replace  = array(
			"  "         => "\t",
			"'!!__(!!\'" => "__('",
			"!!\', !!\'" => "', '",
			"!!\')!!'"   => "')",
		);
		$preg_replace = array(
			'/([\t\r\n]+?)array/' => 'array',
			'/[0-9]+ => array/'   => 'array',
		);

		$field_group = self::$current_group;

		// Load fields for this group field.
		$field_group['fields'] = acf_get_fields( $field_group );

		// Prepare for export.
		$field_group = acf_prepare_field_group_for_export( $field_group );

		// Expose code.
		$code = var_export( $field_group, true );

		// change double spaces to tabs.
		$code = str_replace( array_keys( $str_replace ), array_values( $str_replace ), $code );

		// correctly formats "=> array(".
		$code = preg_replace( array_keys( $preg_replace ), array_values( $preg_replace ), $code );

		// Add if around the ACF fields.
		$string = "<?php \nif( function_exists('acf_add_local_field_group') ):" . "\r\n" . "\r\nacf_add_local_field_group({$code});" . "\r\n" . "\r\nendif;";

		// Check secure path.
		if ( false === strpos( realpath( self::$current_php['full_dirname'] ), WP_CONTENT_DIR ) ) {
			return false;
		}
		// Make dir if it doesn't exist.
		if ( wp_mkdir_p( self::$current_php['full_dirname'] ) ) {
			return file_put_contents( self::$current_php['full'], $string, LOCK_EX );
		}

		return false;
	}

	/**
	 * On ACF field group saving, save the PHP and JSON files.
	 *
	 * @param array $field_group : The field group to use.
	 *
	 * @author Nicolas JUEN
	 * @author Aymene BOURAFAI
	 */
	public static function acf_update_field_group( $field_group ) {
		/**
		 * Reset groups and configs.
		 */
		self::$current_json  = null;
		self::$current_php   = null;
		self::$current_group = null;

		// validate.
		if ( ! acf_get_setting( 'json' ) ) {
			return;
		}

		$json_path = self::get_path( 'json', $field_group );
		$php_path  = self::get_path( 'php', $field_group );

		/**
		 * Set the paths and groups.
		 */
		self::$current_json  = $json_path;
		self::$current_php   = $php_path;
		self::$current_group = $field_group;

		$field_group['save_php']  = $php_path['rendered_path'];
		$field_group['save_json'] = $json_path['rendered_path'];

		$field_group['key'] = $json_path['filename'];

		// get fields.
		$field_group['fields'] = acf_get_fields( $field_group );

		/**
		 * JSON.
		 */
		add_filter( 'acf/settings/save_json', array( __CLASS__, 'acf_json_save_point' ), 99 );
		// save file.
		acf_write_json_field_group( $field_group );
		remove_filter( 'acf/settings/save_json', array( __CLASS__, 'acf_json_save_point' ), 99 );

		/**
		 * PHP.
		 */
		self::acf_export_php();
	}

	/**
	 * Verify path type, slashes and put data in a pathinfo array.
	 *
	 * @param string $type (json | php) : the fieldgroup data type to get from.
	 * @param array $fieldgroup : the fieldgroup to get the information from.
	 *
	 * @return bool|array
	 * @author Aymene Bourafai
	 */
	private static function get_path( $type, $fieldgroup ) {

		/**
		 * Check our keys are set.
		 */
		if ( empty( $fieldgroup['save_php'] ) && empty( $fieldgroup['save_json'] ) ) {
			return false;
		}

		$path = false;

		switch ( $type ) {
			case 'json':
				$path = $fieldgroup['save_json'];
				break;
			case 'php':
				$path = $fieldgroup['save_php'];
				break;
		}

		if ( empty( $path ) ) {
			return false;
		}

		// remove trailing slash and content dir.
		$path = untrailingslashit( str_replace( WP_CONTENT_DIR, '', $path ) );

		$pathinfo = pathinfo( $path );
		/**
		 * Enrich data.
		 */
		$pathinfo['full_dirname']  = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $pathinfo['dirname'];
		$pathinfo['origin']        = $path;
		$pathinfo['extension']     = $type;
		$pathinfo['filename']      = sanitize_file_name( $pathinfo['filename'] );
		$pathinfo['full']          = trailingslashit( WP_CONTENT_DIR ) . $path;
		$pathinfo['rendered_path'] = $pathinfo['dirname'] . DIRECTORY_SEPARATOR . $pathinfo['filename'] . '.' . $pathinfo['extension'];

		return $pathinfo;
	}
}

new BEA_ACF_SAVE_JSON_PHP();
