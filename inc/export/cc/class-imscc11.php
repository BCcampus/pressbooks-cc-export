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

use \Pressbooks;
use \Pressbooks\Modules\Export\Epub\Epub3;
use \Pressbooks\Sanitize;
use \Masterminds\HTML5;

class Imscc11 extends Epub3 {

	/**
	 * @var string
	 */
	protected $suffix = '.imscc';

	/**
	 * @var string
	 */
	protected $filext = 'html';

	/**
	 * @var string
	 */
	protected $dir = __DIR__;

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
			$this->createManifest( $book_contents, $metadata );

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

	/**
	 * @param $book_contents
	 * @param $metadata
	 */
	protected function createWebContent( $book_contents, $metadata ) {

		// Reset manifest
		$this->manifest = [];

		$this->createFrontMatter( $book_contents, $metadata );
		$this->createPartsAndChapters( $book_contents, $metadata );
		$this->createBackMatter( $book_contents, $metadata );

	}

	/**
	 *
	 */
	protected function createContainer() {
		mkdir( $this->tmpDir . '/OEBPS' );
		mkdir( $this->tmpDir . '/OEBPS/assets' );

	}

	/**
	 * Nearly verbatim from class-epub3.php in pressbooks v4.4.0
	 * @copyright Pressbooks
	 *
	 * Override load template function
	 * Switch path from /epub201 to / when possible.
	 *
	 * @param string $path
	 * @param array $vars (optional)
	 *
	 * @return string
	 * @throws \Exception
	 */
	protected function loadTemplate( $path, array $vars = [] ) {

		$search  = '/templates/epub201/';
		$replace = '/templates/';

		$pos = strpos( $path, $search );
		if ( false !== $pos ) {
			$new_path = substr_replace( $path, $replace, $pos, strlen( $search ) );
			if ( file_exists( $new_path ) ) {
				$path = $new_path;
			}
		}

		return parent::loadTemplate( $path, $vars );
	}

	/**
	 * Nearly verbatim from class-epub201.php from pressbooks 4.4.0
	 * Only Eliminated Mobi Hack
	 * @copyright Pressbooks
	 *
	 * Pummel the HTML into IMSCC compatible dough.
	 *
	 * @param string $html
	 * @param string $type front-matter, part, chapter, back-matter, ...
	 * @param int $pos (optional) position of content, used when creating filenames like: chapter-001, chapter-002, ...
	 *
	 * @return string
	 */
	protected function kneadHtml( $html, $type, $pos = 0 ) {

		$doc = new HTML5( [ 'disable_html_ns' => true ] ); // Disable default namespace for \DOMXPath compatibility
		$dom = $doc->loadHTML( $html );

		// Download images, change to relative paths
		$dom = $this->scrapeAndKneadImages( $dom );

		// Download audio files, change to relative paths
		$dom = $this->scrapeAndKneadMedia( $dom );

		// Deal with <a href="">, <a href=''>, and other mutations
		$dom = $this->kneadHref( $dom, $type, $pos );

		// Make sure empty tags (e.g. <b></b>) don't get turned into self-closing versions by adding an empty text node to them.
		$xpath = new \DOMXPath( $dom );
		while ( ( $nodes = $xpath->query( '//*[not(text() or node() or self::br or self::hr or self::img)]' ) ) && $nodes->length > 0 ) {
			foreach ( $nodes as $node ) {
				/** @var \DOMElement $node */
				$node->appendChild( new \DOMText( '' ) );
			}
		}

		// Remove srcset attributes because responsive images aren't a thing in the EPUB world.
		$srcsets = $xpath->query( '//img[@srcset]' );
		foreach ( $srcsets as $srcset ) {
			/** @var \DOMElement $srcset */
			$srcset->removeAttribute( 'srcset' );
		}

		// If you are storing multi-byte characters in XML, then saving the XML using saveXML() will create problems.
		// Ie. It will spit out the characters converted in encoded format. Instead do the following:
		$html = $dom->saveXML( $dom->documentElement );

		// Remove auto-created <html> <body> and <!DOCTYPE> tags.
		$html = \Pressbooks\Sanitize\strip_container_tags( $html );

		return $html;
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

	protected function createManifest( $book_contents, $metadata ) {

		if ( empty( $this->manifest ) ) {
			throw new \Exception( '$this->manifest cannot be empty. Did you forget to call $this->createWebContent() ?' );
		}

		// Vars
		$vars = [
			'manifest' => $this->manifest,
			'lang'     => $this->lang,
		];

		$vars['do_copyright_license'] = Sanitize\sanitize_xml_attribute(
			wp_strip_all_tags( $this->doCopyrightLicense( $metadata ), true )
		);

		// Sanitize metadata for usage in XML template
		foreach ( $metadata as $key => $val ) {
			$metadata[ $key ] = Sanitize\sanitize_xml_attribute( $val );
		}
		$vars['meta'] = $metadata;

		echo "<pre>";
		print_r( get_defined_vars() );
		echo "</pre>";
		die();

		// Put contents
		file_put_contents(
			$this->tmpDir . '/imsmanifest.xml',
			$this->loadTemplate( $this->dir . '/templates/manifest.php', $vars )
		);
	}


}
