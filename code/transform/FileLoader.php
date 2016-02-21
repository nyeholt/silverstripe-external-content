<?php

/**
 * A class for uploading files directly from the filesystem where we don't
 * care about is_uploaded_file
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD License http://silverstripe.org/bsd-license
 */
class FileLoader extends Upload {

	public function validate($tmpFile) {
		return true;
	}

}
