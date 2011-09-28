<?php

class ExternalTreeDropdownField extends TreeDropdownField {

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
	public function __construct($name, $title = null, $sourceObject = 'Group', $keyField = 'ID', $labelField = 'Title', $showChildren=true) {
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
		if ($ID && ExternalContentAdmin::isValidId($ID)) {

			$obj = ExternalContent::getDataObjectFor($ID);
			$isSubTree = true;

			if (!$obj) {
				throw new Exception(
						"TreeDropdownField->tree(): the object #$ID of type $this->sourceObject could not be found"
				);
			}
		} else {
			if ($this->baseID) {
				$obj = ExternalContent::getDataObjectFor($this->baseID);
			}

			if (!$this->baseID || !$obj)
				$obj = singleton($this->sourceObject);
		}

		if ($this->filterCallback) {
			$obj->setMarkingFilterFunction($this->filterCallback);
		} elseif ($this->sourceObject == 'Folder') {
			$obj->setMarkingFilter('ClassName', 'Folder');
		}

		$obj->markPartialTree(1, null);

		if ($forceValues = $this->value) {
			if (($values = preg_split('/,\s*/', $forceValues)) && count($values))
				foreach ($values as $value) {
					$obj->markToExpose($this->objectForKey($value));
				}
		}

		$eval = '"<li id=\"selector-' . $this->Name() . '-{$child->' . $this->keyField . '}\" class=\"$child->class"' .
				' . $child->markingClasses() . "\"><a rel=\"$child->ID\">" . $child->' . $this->labelField . ' . "</a>"';

		if ($isSubTree) {
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
		if ($this->keyField == 'ID') {
			return ExternalContent::getDataObjectFor($key);
		} else {
			return DataObject::get_one($this->sourceObject, "\"{$this->keyField}\" = '" . Convert::raw2sql($key) . "'");
		}
	}

}

?>