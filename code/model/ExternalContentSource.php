<?php

/**
 * A class that represents any kind of an external content source where the 
 * data can be represented in a tree state
 * 
 * ExternalContentSources are hierarchical in nature, and are tagged
 * with the 'Hierarchy' extension to enable them to be displayed in 
 * content trees without problem. Due to their nature though, some of the
 * hierarchy functionality is explicitly overridden to prevent DB 
 * access
 * 
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD License http://silverstripe.org/bsd-license
 *
 */
class ExternalContentSource extends DataObject {

	public static $db = array(
		'Name' => 'Text',
		'ShowContentInMenu' => 'Boolean', // should child items of this be seen in menus?
	);
	
	static $defaults = array(
		'ParentID' => '0'
	);
	static $extensions = array(
		"Hierarchy",
	);


	/**
	 * @var string - icon for cms tree
	 **/
	public static $icon = 'cms/images/treeicons/root.png';


	/**
	 * Get the object represented by an external ID
	 * 
	 * All external content sources must override this
	 * method by providing an implementation that looks up the content in 
	 * the remote data source and returns an ExternalContentItem subclass
	 * that wraps around that external data. 
	 * 
	 * @param String $objectId
	 * @return DataObject
	 */
	public function getObject($objectId) {
		throw new Exception("Child classes MUST provide an implementation of getObject()");
	}

	/**
	 * Gets the root item of this content source (used in templates if there's
	 * not one specified)
	 *  
	 * @return ExternalContentItem
	 */
	public function getRoot() {
		throw new Exception("Child classes MUST override this method");
	}

	/*
	 * The following overrides are mostly placeholders, content 
	 * sources aren't really referred to by URL directly 
	 */

	function Link($action = null) {
		return Director::baseURL() . $this->RelativeLink($action);
	}

	function RelativeLink($action = null) {
		return ExternalContentPage_Controller::URL_STUB . '/view/' . $this->ID;
	}

	function TreeTitle() {
		return $this->Name;
	}

	/**
	 * Child classes should provide connection details to the external 
	 * content source
	 * 
	 * @see sapphire/core/model/DataObject#getCMSFields($params)
	 * @return FieldSet
	 */
	public function getCMSFields() {
		$fields = parent::getCMSFields();

		$fields->removeByName('ParentID');
		$fields->addFieldToTab('Root.Main', new TextField('Name', _t('ExternalContentSource.NAME', 'Name')));
		$fields->addFieldToTab('Root.Main', new CheckboxField("ShowContentInMenu", _t('ExternalContentSource.SHOW_IN_MENUS', 'Show Content in Menus')));

		return $fields;
	}

	/**
	 * Override to replace Hierarchy::numChildren
	 * 
	 * This method should be overridden in child classes to 
	 * handle the functionality in a more efficient way. Doing
	 * things via the method implemented below will work, but 
	 * could cause several remote calls when it might be 
	 * better to just return 1 and let subsequent requests
	 * get more children. 
	 * 
	 * @return int
	 */
	public function numChildren() {
		return 1;
	}

	/**
	 * Get the content importer to use for importing content from 
	 * this external source
	 *
	 * The $target parameter lets the user specify a specific type of import,
	 * depending on where they've chosen to import to. 
	 * 
	 * @param String $target
	 * 			The type of the target we're importing to (SiteTree, File, User etc)
	 * 
	 * @return ExternalContentImporter
	 */
	public function getContentImporter($target=null) {
		return null;
	}

	/**
	 * Return an array of import locations that the importer for
	 * this content source supports. For example, an alfresco content
	 * importer may only support importing to the 'file' tree
	 * 
	 * Return an array of the following format ('false' entries can 
	 * be safely omitted)
	 * 
	 * array(
	 * 		'file' => true,
	 * 		'sitetree' => false,
	 * )
	 * 
	 * @return array
	 */
	public function allowedImportTargets() {
		return array();
	}

	/**
	 * Controls whether the user can create this content source.
	 *
	 * @return bool
	 */
	public function canCreate($member = null) {
		return true;
	}

	/**
	 * We flag external content as being editable so it's 
	 * accessible in the backend, but the individual 
	 * implementations will protect users from editing... for now
	 * 
	 * TODO: Fix this up to use proper permission checks
	 * 
	 * @see sapphire/core/model/DataObject#canEdit($member)
	 */
	public function canEdit($member = null) {
		return true;
	}

	/**
	 * Is this item viewable? 
	 * 
	 * Just proxy to the content source for now. Child implementations can
	 * override if needbe
	 * 
	 * @see sapphire/core/model/DataObject#canView($member)
	 */
	public function canView($member = null) {
		return true;
	}

	/**
	 * Returns whether or not this source can be imported, defaulting to true.
	 *
	 * @return bool
	 */
	public function canImport() {
		$importer =  $this->getContentImporter();
		return $importer != null;
	}

	/**
	 * Override to return the top level content items from the remote
	 * content source. 
	 * 
	 * Specific implementations should effectively query the remote
	 * source for all items that are children of the 'root' node. 
	 * 
	 * @param boolean $showAll
	 * @return DataObjectSet
	 */
	public function stageChildren($showAll = false) {
		// if we don't have an ID directly, we should load and return ALL the external content sources
		if (!$this->ID) {
			return DataObject::get('ExternalContentSource');
		}

		$children = new ArrayList();
		return $children;
	}

	/**
	 * Handle a children call by retrieving from stageChildren
	 */
	public function Children() {
		static $children;

		if (!$children) {
			$children = new ArrayList();
			$kids = $this->stageChildren();
			if ($kids) {
				foreach ($kids as $child) {
					if ($child->canView()) {
						$children->push($child);
					}
				}
			}
		}
		return $children;
	}

	/**
	 * Helper function to encode a remote ID that is safe to use within 
	 * silverstripe
	 * 
	 * @param $id
	 * 			The external content ID
	 * @return string
	 * 			A safely encoded ID
	 */
	public function encodeId($id) {
		return str_replace('=', '-', base64_encode($id));
	}

	/**
	 * Decode an ID encoded by the above encodeId method
	 * 
	 * @param String $id
	 * 			The encoded ID
	 * @return String
	 * 			A decoded ID
	 */
	public function decodeId($id) {
		$id = str_replace('-', '=', $id);
		return base64_decode($id);
	}


	/**
	 * Return the CSS classes to apply to this node in the CMS tree
	 *
	 * @return string
	 */
	function CMSTreeClasses() {
		$classes = sprintf('class-%s', $this->class);
		return $classes;
	}


	/**
	 * Return the CSS declarations to apply to nodes of this type in the CMS tree
	 *
	 * @return string
	 */
	function CMSTreeCSS() {
		return null;
	}


	/**
	 * getTreeTitle will return two <span> html DOM elements, an empty <span> with
	 * the class 'jstree-pageicon' in front, following by a <span> wrapping around its
	 * MenutTitle
	 *
	 * @return string a html string ready to be directly used in a template
	 */
	function getTreeTitle() {
		$treeTitle = sprintf(
			"<span class=\"jstree-pageicon\"></span><span class=\"item\">%s</span>",
			Convert::raw2xml(str_replace(array("\n","\r"),"",$this->Name))
		);
		return $treeTitle;
	}

}
