<?php
/**
 * Plugin Name:     Pressbooks Cc Export
 * Plugin URI:      https://github.com/bccampus/pressbooks-cc-export
 * Description:     Common Cartridge Export for Pressbooks
 * Author:          bdolor
 * Author URI:      https://github.com/bdolor
 * Text Domain:     pressbooks-cc-export
 * Domain Path:     /languages
 * Version:         0.1.0
 * License:         GPL-3.0+
 * Tags: pressbooks, OER, publishing, common cartridge, imscc
 * Network: True
 *
 * @package         Pressbooks_Cc_Export
 */


if ( ! defined( 'ABSPATH' ) ) {
	return;
}


/*
|--------------------------------------------------------------------------
| Constants
|--------------------------------------------------------------------------
|
|
|
|
*/
if ( ! defined( 'PCE_PLUGIN_DIR' ) ) {
	define( 'PCE_PLUGIN_DIR', __DIR__ . '/' );
}

// Must have trailing slash!
if ( ! defined( 'PB_PLUGIN_DIR' ) ) {
	define( 'PB_PLUGIN_DIR', WP_PLUGIN_DIR . '/pressbooks/' );
}


/*
|--------------------------------------------------------------------------
| Minimum requirements before either PB or PCE objects are instantiated
|--------------------------------------------------------------------------
|
|
|
|
*/
add_action( 'init', function () {
	$min_pb_compatibility_version = '4.0.0';

	if ( ! @include_once( WP_PLUGIN_DIR . '/pressbooks/compatibility.php' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div id="message" class="error fade"><p>' . __( 'CC Export cannot find a Pressbooks install.', 'pressbooks-cc-export' ) . '</p></div>';
		} );

	} elseif ( ! pb_meets_minimum_requirements() ) { // This PB function checks for both multisite, PHP and WP minimum versions.
		add_action( 'admin_notices', function () {
			echo '<div id="message" class="error fade"><p>' . __( 'Your PHP version may not be supported by PressBooks.', 'pressbooks-cc-export' ) . '</p></div>';
		} );

	} elseif ( ! version_compare( PB_PLUGIN_VERSION, $min_pb_compatibility_version, '>=' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div id="message" class="error fade"><p>' . __( 'CC Export requires Pressbooks 4.0.0 or greater.', 'pressbooks-cc-export' ) . '</p></div>';
		} );
	}

} );


/*
|--------------------------------------------------------------------------
| autoload classes
|--------------------------------------------------------------------------
|
|
|
|
*/
require PCE_PLUGIN_DIR . 'autoloader.php';

// Load Composer Dependencies
if ( file_exists( $composer = PCE_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once( $composer );
}

/*
|--------------------------------------------------------------------------
| Hook into the pb matrix
|--------------------------------------------------------------------------
|
|
|
|
*/
add_filter( 'pb_export_formats', function ( $formats ) {
	$formats['exotic']['imscc11'] = __( 'Common Cartridge (v1.1)', 'pressbooks-cc-export' );

	return $formats;
} );

add_filter( 'pb_active_export_modules', function ( $modules ) {
	if ( isset( $_POST['export_formats']['imscc11'] ) ) {
		$modules[] = '\BCcampus\Export\CC\Imscc11';
	}

	return $modules;

} );
