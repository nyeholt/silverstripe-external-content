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
