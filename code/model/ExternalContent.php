<?php

/**
 * A class that provides methods for working with external content, aware of
 * the nature of compound IDs and when to load data from SilverStripe to 
 * facilitate loading from other sources. 
 * 
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD License http://silverstripe.org/bsd-license
 *
 */
class ExternalContent {
	/**
	 * The format that externalised content can be referenced by
	 * 
	 * @var string
	 */
	const ID_FORMAT = "/(\d+)(\|.*)?/";

	const DEFAULT_CLASS = 'ExternalContentSource';

	/**
	 * Get the actual object based on a composite ID
	 * 
	 * Don't really want to use a static, but SS's data object
	 * retrieval system doesn't really provide a nice override
	 * mechanism other than useCustomObject
	 * 
	 * @param String $id
	 * 			The compound ID to get a data object for
	 * 
	 * @return DataObject
	 */
	public static function getDataObjectFor($id) {
		if ($id == 'root') {
			return null;
		}

		$obj = null;

		if (preg_match(self::ID_FORMAT, $id, $matches)) {
			$id = $matches[1];
			$composed = isset($matches[2]) ? trim($matches[2], '|') : null;
			$obj = DataObject::get_by_id(self::DEFAULT_CLASS, $id);
			if ($composed && $obj) {
				$obj = $obj->getObject($composed);
			}
		} else {
			
		}

		return $obj;
	}

}