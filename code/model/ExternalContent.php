<?php
/**

Copyright (c) 2009, SilverStripe Australia Limited - www.silverstripe.com.au
All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the 
      documentation and/or other materials provided with the distribution.
    * Neither the name of SilverStripe nor the names of its contributors may be used to endorse or promote products derived from this software 
      without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE 
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE 
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE 
GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, 
STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY 
OF SUCH DAMAGE.
 
*/

/**
 * A class that provides methods for working with external content, aware of
 * the nature of compound IDs and when to load data from SilverStripe to 
 * facilitate loading from other sources. 
 * 
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 *
 */
class ExternalContent
{
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
	public static function getDataObjectFor($id)
	{
		if ($id == 'root') return null;

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


?>