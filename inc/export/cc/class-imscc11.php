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

use \PressBooks\Modules\Export\Export;
use \PressBooks;
use \Pressbooks\Sanitize;
use \Pressbooks\HtmLawed;

class Imscc11 extends Export {
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

		echo "<pre>";
		print_r( get_defined_vars() );
		echo "</pre>";
		die();
//		$this->createFrontMatter( $book_contents, $metadata );
//		$this->createPartsAndChapters( $book_contents, $metadata );
//		$this->createBackMatter( $book_contents, $metadata );

	}

	/**
	 * Making it xml friendly
	 * Copied verbatim from class-epub201.php in pressbooks
	 * @copyright Pressbooks
	 *
	 * @param $book_contents
	 *
	 * @return mixed
	 */
	protected function preProcessBookContents( $book_contents ) {

		// We need to change global $id for shortcodes, the_content, ...
		global $id;
		$old_id = $id;

		// Do root level structures first.
		foreach ( $book_contents as $type => $struct ) {

			if ( preg_match( '/^__/', $type ) ) {
				continue; // Skip __magic keys
			}

			foreach ( $struct as $i => $val ) {

				if ( isset( $val['post_content'] ) ) {
					$id                                           = $val['ID'];
					$book_contents[ $type ][ $i ]['post_content'] = $this->preProcessPostContent( $val['post_content'] );
				}
				if ( isset( $val['post_title'] ) ) {
					$book_contents[ $type ][ $i ]['post_title'] = Sanitize\sanitize_xml_attribute( $val['post_title'] );
				}
				if ( isset( $val['post_name'] ) ) {
					$book_contents[ $type ][ $i ]['post_name'] = $this->preProcessPostName( $val['post_name'] );
				}

				if ( 'part' === $type ) {

					// Do chapters, which are embedded in part structure
					foreach ( $book_contents[ $type ][ $i ]['chapters'] as $j => $val2 ) {

						if ( isset( $val2['post_content'] ) ) {
							$id                                                             = $val2['ID'];
							$book_contents[ $type ][ $i ]['chapters'][ $j ]['post_content'] = $this->preProcessPostContent( $val2['post_content'] );
						}
						if ( isset( $val2['post_title'] ) ) {
							$book_contents[ $type ][ $i ]['chapters'][ $j ]['post_title'] = Sanitize\sanitize_xml_attribute( $val2['post_title'] );
						}
						if ( isset( $val2['post_name'] ) ) {
							$book_contents[ $type ][ $i ]['chapters'][ $j ]['post_name'] = $this->preProcessPostName( $val2['post_name'] );
						}
					}
				}
			}
		}

		$id = $old_id;

		return $book_contents;
	}

	/**
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	protected function preProcessPostContent( $content ) {

		$content = apply_filters( 'the_content', $content );
		$content = $this->tidy( $content );

		return $content;
	}

	/**
	 * Tidy HTML
	 * Copied verbatim from class-epub201.php in pressbooks
	 *
	 * @param string $html
	 *
	 * @return string
	 */
	protected function tidy( $html ) {

		// Make XHTML 1.1 strict using htmlLawed

		$config = [
			'valid_xhtml'        => 1,
			'no_deprecated_attr' => 2,
			'unique_ids'         => 'fixme-',
			'deny_attribute'     => 'itemscope,itemtype,itemref,itemprop',
			'hook'               => '\Pressbooks\Sanitize\html5_to_xhtml11',
			'tidy'               => - 1,
			'comment'            => 1,
		];

		// Reset on each htmLawed invocation
		unset( $GLOBALS['hl_Ids'] );
		if ( ! empty( $this->fixme ) ) {
			$GLOBALS['hl_Ids'] = $this->fixme;
		}

		return HtmLawed::filter( $html, $config );
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
}
