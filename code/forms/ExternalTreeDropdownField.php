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
 
class ExternalTreeDropdownField extends TreeDropdownField
{
	/**
	 * Should children be selectable when this tree is shown
	 * 
	 * @var boolean
	 */
	protected $showChildren;
	
	/**
	 * @param string $name the field name
	 * @param string $title the field label
	 * @param string $souceClass the class to display in the tree, must have the "Hierachy" extension.
	 * @param string $keyField to field on the source class to save as the field value (default ID).
	 * @param string $labelField the field name to show as the human-readable value on the tree (default Title).
	 * @param boolean $showChildren whether children should be selectable
	 */
	public function __construct($name, $title = null, $sourceObject = 'Group', $keyField = 'ID', $labelField = 'Title', $showChildren=true)
	{
		parent::__construct($name, $title, $sourceObject, $keyField, $labelField);
		$this->showChildren = $showChildren;
	}
	
	/**
	 * Override to allow for compound IDs 
	 * 
	 * @param mixed $ID
	 */
	public function setTreeBaseID($ID) {
		$this->baseID = $ID;
	}
	
/**
	 * Get the whole tree of a part of the tree via an AJAX request.
	 *
	 * @param SS_HTTPRequest $request
	 * @return string
	 */
	public function tree(SS_HTTPRequest $request) {
		$isSubTree = false;
		
		$ID = $request->param('ID');
		if($ID && ExternalContentAdmin::isValidId($ID)) {

			$obj       = ExternalContent::getDataObjectFor($ID);
			$isSubTree = true;
			
			if(!$obj) {
				throw new Exception (
					"TreeDropdownField->tree(): the object #$ID of type $this->sourceObject could not be found"
				);
			}
		} else {
			if($this->baseID) {
				$obj = ExternalContent::getDataObjectFor($this->baseID);
			}
			
			if(!$this->baseID || !$obj) $obj = singleton($this->sourceObject);
		}

		if($this->filterCallback) {
			$obj->setMarkingFilterFunction($this->filterCallback);
		} elseif($this->sourceObject == 'Folder') {
			$obj->setMarkingFilter('ClassName', 'Folder');
		}
		
		$obj->markPartialTree(1, null);
		
		if($forceValues = $this->value) {
			if(($values = preg_split('/,\s*/', $forceValues)) && count($values)) foreach($values as $value) {
				$obj->markToExpose($this->objectForKey($value));
			}
		}

		$eval = '"<li id=\"selector-' . $this->Name() . '-{$child->' . $this->keyField . '}\" class=\"$child->class"' .
				' . $child->markingClasses() . "\"><a rel=\"$child->ID\">" . $child->' . $this->labelField . ' . "</a>"';
		
		if($isSubTree) {
			return substr(trim($obj->getChildrenAsUL('', $eval, null, true)), 4, -5);
		}
		
		return $obj->getChildrenAsUL('class="tree"', $eval, null, true);
	}
	
	/**
	 * Get the object where the $keyField is equal to a certain value
	 *
	 * @param string|int $key
	 * @return DataObject
	 */
	protected function objectForKey($key) {
		if($this->keyField == 'ID') {
			return ExternalContent::getDataObjectFor($key);
		} else {
			return DataObject::get_one($this->sourceObject, "\"{$this->keyField}\" = '" . Convert::raw2sql($key) . "'");
		}
	}
}


?>