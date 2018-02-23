<?php
/**
 * Plugin Name:     CC Export for Pressbooks
 * Plugin URI:      https://github.com/bccampus/pressbooks-cc-export
 * Description:     Common Cartridge Export for Pressbooks
 * Author:          Brad Payne
 * Author URI:      https://github.com/bdolor
 * Text Domain:     pressbooks-cc-export
 * Domain Path:     /languages
 * Version:         0.2.0-rc.1
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
	$min_pb_compatibility_version = '5.0.0-rc.1';

	if ( ! @include_once( WP_PLUGIN_DIR . '/pressbooks/compatibility.php' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div id="message" class="error fade"><p>' . __( 'CC Export cannot find a Pressbooks install.', 'pressbooks-cc-export' ) . '</p></div>';
		} );

	}
	if ( ! pb_meets_minimum_requirements() ) { // This PB function checks for both multisite, PHP and WP minimum versions.
		add_action( 'admin_notices', function () {
			echo '<div id="message" class="error fade"><p>' . __( 'Your PHP version may not be supported by PressBooks.', 'pressbooks-cc-export' ) . '</p></div>';
		} );

	}
	if ( ! version_compare( PB_PLUGIN_VERSION, $min_pb_compatibility_version, '>=' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div id="message" class="error fade"><p>' . __( 'CC Export requires Pressbooks 5.0.0 or greater.', 'pressbooks-cc-export' ) . '</p></div>';
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


/*
|--------------------------------------------------------------------------
| Add imscc export format to the latest exports list on front page of a book
|--------------------------------------------------------------------------
|
|
|
|
*/
add_filter( 'pb_latest_export_filetypes', function ( $filetypes ) {
	$filetypes['imscc11'] = '.imscc';

	return $filetypes;
} );

add_filter( 'pb_export_filetype_names', function ( $array ) {

	if ( ! isset( $array['imscc11'] ) ) {
		$array['imscc11'] = __( 'Common Cartridge', 'pressbooks-cc-export' );
	}

	return $array;
} );

/*
|--------------------------------------------------------------------------
| Add imscc icon to front page of a book
|--------------------------------------------------------------------------
|
|
|
|
*/
add_action( 'wp_enqueue_scripts', function () {
	// Load only on front page
	if ( is_front_page() ) {
		wp_enqueue_style( 'fp_icon_style', plugins_url( 'assets/styles/fp-icon-style.css', __FILE__ ) );
	}

	return;
} );

/*
|--------------------------------------------------------------------------
| Add imscc icon to the admin PB export page
|--------------------------------------------------------------------------
|
|
|
|
*/
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	// Load only on export page
	if ( $hook !== 'toplevel_page_pb_export' ) {
		return;
	}
	wp_enqueue_style( 'cc_icon_style', plugins_url( 'assets/styles/cc-icon-style.css', __FILE__ ) );
} );
