<?php
/**
 * Project: pressbooks-cc-export
 * Project Sponsor: BCcampus <https://bccampus.ca>
 * Copyright 2012-2017 Brad Payne <https://bradpayne.ca>
 * Date: 2017-11-20
 * Licensed under GPLv3, or any later version
 *
 * @author Brad Payne
 * @package Pressbooks_Cc_Export
 * @license https://www.gnu.org/licenses/gpl-3.0.txt
 * @copyright (c) 2012-2017, Brad Payne
 */

namespace BCcampus\Export\CC;


use \PressBooks;
use Pressbooks\Modules\Export\Epub\Epub201;
use \Pressbooks\Sanitize;
use \Pressbooks\HtmLawed;

class Imscc11 extends Epub201 {
	/**
	 * Temporary directory used to build IMSCC
	 *
	 * @var string
	 */
	protected $tmpDir;

	/**
	 * @var string
	 */
	protected $suffix = '.imscc';

	/**
	 *
	 * @var array
	 */
	protected $manifest = [];
	protected $fetchedMediaCache = [];

	/**
	 * Imscc11 constructor.
	 */
	function __construct() {
		if ( ! class_exists( '\PclZip' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/class-pclzip.php' );
		}

		$this->tmpDir = $this->createTmpDir();

		// HtmLawed: id values not allowed in input
		foreach ( $this->reservedIds as $val ) {
			$this->fixme[ $val ] = 1;
		}
	}

	/**
	 * Mandatory convert method, create $this->outputPath
	 *
	 * @return bool
	 */
	function convert() {
		// Sanity check

		if ( empty( $this->tmpDir ) || ! is_dir( $this->tmpDir ) ) {
			$this->logError( '$this->tmpDir must be set before calling convert().' );

			return false;
		}
		// Convert

		$metadata      = PressBooks\Book::getBookInformation();
		$book_contents = $this->preProcessBookContents( Pressbooks\Book::getBookContents() );

		// Set two letter language code
		if ( isset( $metadata['pb_language'] ) ) {
			list( $this->lang ) = explode( '-', $metadata['pb_language'] );
		}

		try {

			$this->createContainer();
			$this->createWebContent( $book_contents, $metadata );

		} catch ( \Exception $e ) {
			$this->logError( $e->getMessage() );

			return false;
		}

		$filename = $this->timestampedFileName( $this->suffix );
		if ( ! $this->zipImscc( $filename ) ) {
			return false;
		}
		$this->outputPath = $filename;

		return true;
	}

	protected function createWebContent( $book_contents, $metadata ) {

		// Reset manifest
		$this->manifest = [];

		$this->createFrontMatter( $book_contents, $metadata );
//		$this->createPartsAndChapters( $book_contents, $metadata );
//		$this->createBackMatter( $book_contents, $metadata );

	}

	protected function createFrontMatter( $book_contents, $metadata ) {

		echo "<pre>";
		print_r( get_defined_vars() );
		echo "</pre>";
		die();

	}

	/**
	 * Nearly Verbatim from class-epub201.php in pressbooks v4.4.0
	 * @copyright Pressbooks
	 *
	 * @param \DOMDocument $doc
	 *
	 * @return \DOMDocument
	 */
	protected function scrapeAndKneadImages( \DOMDocument $doc ) {

		$fullpath = $this->tmpDir . '/webcontent/assets';

		$images = $doc->getElementsByTagName( 'img' );
		foreach ( $images as $image ) {
			/** @var \DOMElement $image */
			// Fetch image, change src
			$url      = $image->getAttribute( 'src' );
			$filename = $this->fetchAndSaveUniqueImage( $url, $fullpath );
			if ( $filename ) {
				// Replace with new image
				$image->setAttribute( 'src', 'assets/' . $filename );
			} else {
				// Tag broken image
				$image->setAttribute( 'src', "{$url}#fixme" );
			}
		}

		return $doc;
	}

	/**
	 * Tidy HTML
	 * Verbatim from class-epub3.php in pressbooks v4.4.0
	 * @copyright Pressbooks
	 *
	 * @param string $html
	 *
	 * @return string
	 */
	protected function tidy( $html ) {

		// Venn diagram join between XTHML + HTML5 Deprecated Attributes
		//
		// Our $spec is artisanally hand crafted based on squinting very hard while reading the following docs:
		//
		//  + 2.3 - Extra HTML specifications using the $spec parameter
		//  + 3.4.6 -  Transformation of deprecated attributes
		//  + 3.3.2  - Tag-transformation for better compliance with standards
		//  + HTML5 - Deprecated Tags & Attributes
		//
		// That is we do not remove deprecated attributes that are already transformed by htmLawed
		//
		// More info:
		//  + http://www.bioinformatics.org/phplabware/internal_utilities/htmLawed/beta/htmLawed_README.htm
		//  + http://www.tutorialspoint.com/html5/html5_deprecated_tags.htm

		$config = [
			'valid_xhtml'        => 1,
			'no_deprecated_attr' => 2,
			'unique_ids'         => 'fixme-',
			'hook'               => '\Pressbooks\Sanitize\html5_to_epub3',
			'tidy'               => - 1,
			'make_tag_strict'    => 2,
			'comment'            => 1,
		];

		$spec = '';
		$spec .= 'a=,-charset,-coords,-rev,-shape;';
		$spec .= 'area=-nohref;';
		$spec .= 'col=-align,-char,-charoff,-valign,-width;';
		$spec .= 'colgroup=-align,-char,-charoff,-valign,-width;';
		$spec .= 'div=-align;';
		$spec .= 'iframe=-align,-frameborder,-longdesc,-marginheight,-marginwidth,-scrolling;';
		$spec .= 'img=-longdesc;';
		$spec .= 'link=-charset,-rev,-target;';
		$spec .= 'menu=-compact;';
		$spec .= 'object=-archive,-classid,-codebase,-codetype,-declare,-standby;';
		$spec .= 'param=-type,-valuetype;';
		$spec .= 't=-abbr,-axis;';
		$spec .= 'table=-border,-cellpadding,-frame,-rules;';
		$spec .= 'tbody=-align,-char,-charoff,-valign;';
		$spec .= 'td=-axis,-abbr,-align,-char,-charoff,-scope,-valign;';
		$spec .= 'tfoot=-align,-char,-charoff,-valign;';
		$spec .= 'th=-align,-char,-charoff,-valign;';
		$spec .= 'thead=-align,-char,-charoff,-valign;';
		$spec .= 'tr=-align,-char,-charoff,-valign;';
		$spec .= 'ul=-type;';

		// Reset on each htmLawed invocation
		unset( $GLOBALS['hl_Ids'] );
		if ( ! empty( $this->fixme ) ) {
			$GLOBALS['hl_Ids'] = $this->fixme;
		}

		$html = HtmLawed::filter( $html, $config, $spec );

		return $html;
	}

	/**
	 *
	 */
	protected function createContainer() {
		mkdir( $this->tmpDir . '/webcontent' );
		mkdir( $this->tmpDir . '/webcontent/assets' );

	}


	protected function zipImscc( $filename ) {
		// TODO: add functionality
	}

	/**
	 * Mandatory validate method, check the sanity of $this->outputPath
	 *
	 * @return bool
	 */
	function validate() {
		// TODO: Implement validate() method.
	}

	/**
	 * Nearly verbatim from class-epub3.php in pressbooks v4.4.0
	 * @copyright Pressbooks
	 *
	 * @param \DOMDocument $doc
	 *
	 * @return \DOMDocument
	 */
	protected function scrapeAndKneadMedia( \DOMDocument $doc ) {
		$fullpath = $this->tmpDir . '/webcontent/assets';
		$tags     = [ 'source', 'audio', 'video' ];

		foreach ( $tags as $tag ) {

			$sources = $doc->getElementsByTagName( $tag );
			foreach ( $sources as $source ) {
				/** @var $source \DOMElement */
				if ( ! empty( $source->getAttribute( 'src' ) ) ) {
					// Fetch the audio file
					$url      = $source->getAttribute( 'src' );
					$filename = $this->fetchAndSaveUniqueMedia( $url, $fullpath );

					if ( $filename ) {
						// Change src to new relative path
						$source->setAttribute( 'src', 'assets/' . $filename );
					} else {
						// Tag broken media
						$source->setAttribute( 'src', "{$url}#fixme" );
					}
				}
			}
		}

		return $doc;
	}

	/**
	 * Nearly Verbatim from class-epub3.php in pressbooks v4.4.0
	 * @copyright Pressbooks
	 *
	 * @param $url
	 * @param $fullpath
	 *
	 * @return array|mixed|string
	 */
	protected function fetchAndSaveUniqueMedia( $url, $fullpath ) {

		if ( isset( $this->fetchedMediaCache[ $url ] ) ) {
			return $this->fetchedMediaCache[ $url ];
		}

		$response = wp_remote_get( $url, [ 'timeout' => $this->timeout ] );

		// WordPress error?
		if ( is_wp_error( $response ) ) {
			try {
				// protocol relative urls handed to wp_remote_get will fail
				// try adding a protocol
				$protocol_relative = wp_parse_url( $url );
				if ( ! isset( $protocol_relative['scheme'] ) ) {
					if ( true === is_ssl() ) {
						$url = 'https:' . $url;
					} else {
						$url = 'http:' . $url;
					}
				}
				$response = wp_remote_get( $url, [ 'timeout' => $this->timeout ] );
				if ( is_wp_error( $response ) ) {
					throw new \Exception( 'Bad URL: ' . $url );
				}
			} catch ( \Exception $exc ) {
				$this->fetchedImageCache[ $url ] = '';
				error_log( '\BCcampus\Export\Imscc wp_error on wp_remote_get() - ' . $response->get_error_message() . ' - ' . $exc->getMessage() );

				return '';
			}
		}

		// Basename without query string
		$filename = explode( '?', basename( $url ) );
		$filename = array_shift( $filename );
		$filename = explode( '#', $filename )[0]; // Remove trailing anchors
		$filename = sanitize_file_name( urldecode( $filename ) );
		$filename = Sanitize\force_ascii( $filename );

		$tmp_file = \Pressbooks\Utility\create_tmp_file();
		file_put_contents( $tmp_file, wp_remote_retrieve_body( $response ) );

		if ( ! \Pressbooks\Media\is_valid_media( $tmp_file, $filename ) ) {
			$this->fetchedMediaCache[ $url ] = '';

			return ''; // Not a valid media type
		}

		// Check for duplicates, save accordingly
		if ( ! file_exists( "$fullpath/$filename" ) ) {
			copy( $tmp_file, "$fullpath/$filename" );
		} elseif ( md5( file_get_contents( $tmp_file ) ) !== md5( file_get_contents( "$fullpath/$filename" ) ) ) {
			$filename = wp_unique_filename( $fullpath, $filename );
			copy( $tmp_file, "$fullpath/$filename" );
		}

		$this->fetchedMediaCache[ $url ] = $filename;

		return $filename;
	}

}
