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
use \Pressbooks\Books;

class Imscc11 extends Export {

	/**
	 * Mandatory convert method, create $this->outputPath
	 *
	 * @return bool
	 */
	function convert() {
		// TODO: Implement convert() method.
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
