<?php

/**
 * Parent class for all ExternalContentItems. 
 * 
 * On construction, an ExternalContentItem subclass must load data from the 
 * remote repository through the appropriate API layer as returned by getRemoteRepository()
 * from the external content source. It is then up to the content item to 
 * store that data in a way that can be used by the rest of SilverStripe. 
 * 
 * For now, the ExternalContentItem provides the remoteProperties map for 
 * storing things, with __get and __set magic methods for retrieving values. 
 * Some implementations may choose to store the data in a separate object (for
 * example, the AlfrescoContentItem implementation simply stores things in its
 * contained CMIS object and maps back and forward from that). 
 * 
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD License http://silverstripe.org/bsd-license
 */
class ExternalContentItem extends DataObject {

	public static $db = array(
	);
	static $extensions = array(
		"Hierarchy",
	);


	/**
	 * @var string - icon for cms tree
	 **/
	public static $icon = null;


	protected $ownerId;

	/**
	 * The ID of this item in the remote system
	 * 
	 * @var mixed
	 */
	protected $externalId;

	public function setOwnerId($id) {
		$this->ownerId = $id;
	}

	/**
	 * The content source object that this item belongs to
	 * 
	 * @var ExternalContentSource
	 */
	protected $source;


	/**
	 * The child nodes of this item
	 * 
	 * @var ArrayList
	 */
	private $children;

	/**
	 * Create a new external content item. 
	 * 
	 * @param mixed $source
	 * 			The contentSource object this item was laoded through
	 * @param mixed $id
	 * 			The ID of the item in the remote system
	 * @param mixed $content
	 * 			A raw representation of the remote item. This allows for
	 * 			some systems loading up entire representations when you make 
	 * 			a call to 'getChildren', for example. 
	 */
	public function __construct($source=null, $id=null) {
		parent::__construct();
		if ($source) {
			$this->source = $source;
			$this->externalId = $id;
			// if we are here, then we have been created in context of a parent, which also
			// means there's a compound ID, so lets get that
			$this->ID = $this->source->ID . ExternalContent::ID_SEPARATOR . $this->source->encodeId($id);
			$this->ShowInMenus = $this->source->ShowContentInMenu;
			$this->init();
		}
	}
	
	/**
	 * Return the ID of the item in its remote system
	 *
	 * @return string
	 */
	public function getExternalId() {
		return $this->externalId;
	}

	/**
	 * Get the type of this external object. 
	 * 
	 * Child classes must implement this as a method for certain functionality
	 * to know what the remote object is 
	 * 
	 * @return String
	 */
	public function getType() {
		throw new Exception("Please implement " . get_class($this) . "::getType()");
	}

	/**
	 * Initialise this object based on its source object
	 */
	protected function init() {
		
	}

	/**
	 * Get the content source for this item
	 *
	 * @return ExternalContentSource
	 */
	public function getSource() {
		return $this->source;
	}

	/**
	 * Override to ensure exists handles things properly
	 *
	 * @return boolean
	 */
	public function exists() {
		return!is_null($this->ID);
	}

	/**
	 * Return a URL that simply links back to the externalcontentadmin
	 * class' 'view' action
	 * 
	 * @param $action
	 * @return String
	 */
	function Link($action = null) {
		return ExternalContentPage_Controller::URL_STUB . '/view/' . $this->ID;
	}

	/**
	 * Return a URL that simply links back to the externalcontentadmin
	 * class' 'view' action
	 * 
	 * @param $action
	 * @return String
	 */
	function RelativeLink($action = null) {
		return ExternalContentPage_Controller::URL_STUB . '/view/' . $this->ID;
	}

	/**
	 * Where this can be downloaded from
	 * 
	 * @return string
	 */
	public function DownloadLink() {
		// get the base URL, prepend with the external content 
		// controller /download action and add this object's id
		return ExternalContentPage_Controller::URL_STUB . '/download/' . $this->ID;
	}

	/**
	 * Get the importer for this content item
	 * 
	 * @return ExternalContentImporter
	 */
	public function getContentImporter($target=null) {
		return $this->source->getContentImporter($target);
	}

	/**
	 * Where can this content be imported to? 
	 * 
	 * @return array
	 */
	public function allowedImportTargets() {
		return $this->source->allowedImportTargets();
	}

	/**
	 * An overrideable method to return the arbitrary 'content' of this
	 * node. Child classes should implement their own version
	 */
	public function Content() {
		
	}

	/**
	 * Called to stream this content item (if it is streamable)
	 * 
	 */
	public function streamContent() {
		throw new Exception("This object cannot be streamed");
	}

	/**
	 * Always return at least one as we never know til we load
	 * whether this item has children or not
	 * 
	 * @return int
	 */
	public function numChildren() {
		return 1;
	}

	/**
	 * Overridden to load all children from a remote content
	 * source  instead of this node directly
	 * 
	 * @param boolean $showAll
	 * @return ArrayList
	 */
	public function stageChildren($showAll = false) {
		if ($this->Title != 'Content Root' && $this->source) {
			$children = new ArrayList();
			$item = new ExternalContentItem($this->source, $this->Title . '1');
			$item->Title = $this->Title . '1';
			$item->MenuTitle = $item->Title;

			$children->push($item);
			return $children;
		}
	}

	/**
	 * Handle a children call by retrieving from stageChildren
	 */
	public function Children() {
		if (!$this->children) {
			$this->children = new ArrayList();
			$kids = $this->stageChildren();
			if ($kids) {
				foreach ($kids as $child) {
					if ($child->canView()) {
						$this->children->push($child);
					}
				}
			}
		}
		return $this->children;
	}

	/**
	 * For now just show a field that says this can't be edited
	 * 
	 * @see sapphire/core/model/DataObject#getCMSFields($params)
	 */
	public function getCMSFields() {
		$fields = parent::getCMSFields();

		$fields->removeByName('ParentID');
		if (count($this->remoteProperties)) {
			$mapping = $this->editableFieldMapping();
			foreach ($this->remoteProperties as $name => $value) {
				$field = null;
				if (isset($mapping[$name])) {
					$field = $mapping[$name];
					if (is_string($field)) {
						$field = new $field($name, $this->fieldLabel($name), $value);
					}
				} else {
					$value = (string) $value;
					$field = new ReadonlyField($name, _t('ExternalContentItem.' . $name, $name), $value);
					
				}

				if ($field) {
					$fields->addFieldToTab('Root.Main', $field);
				}
			}
		}

		return $fields;
	}
	
	/**
	 * Return a mapping of remote field name => field type
	 * 
	 * Note that this also defines the list of fields on the remote object that can be edited by end users
	 * and is examined before a form save is triggered
	 * 
	 */
	public function editableFieldMapping() {
		return array();
	}
	
	/**
	 * Write back to the content source
	 */
	public function remoteWrite($member = null) {
		
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
		return $this->source->canEdit();
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
		return $this->source->canView();
	}

	/**
	 * Returns whether or not this source can be imported, defaulting to the
	 * linked source's permission.
	 *
	 * @return bool
	 */
	public function canImport() {
		return $this->source->canImport();
	}

	/**
	 * The list of properties loaded from a remote data source
	 * 
	 * @var array
	 */
	protected $remoteProperties;

	/**
	 * Overriding the default behaviour to not worry about how it 
	 * needs to work with the DB
	 * 
	 * @see sapphire/core/ViewableData#__set($property, $value)
	 */
	public function __set($prop, $val) {
		$this->remoteProperties[$prop] = $val;
	}

	/**
	 * Return from the parent object if it's not in here...
	 * 
	 * @see sapphire/core/ViewableData#__get($property)
	 */
	function __get($prop) {
		if (isset($this->remoteProperties[$prop])) {
			return $this->remoteProperties[$prop];
		}

		$val = parent::__get($prop);

		if (!$val) {

			if ($this->source) {
				// get it from there
				return $this->source->$prop;
			}
		}

		return $val;
	}

	/**
	 * Override to let remote objects figure out whether they have a 
	 * field or not
	 * 
	 * @see sapphire/core/model/DataObject#hasField($field)
	 */
	public function hasField($field) {
		return isset($this->remoteProperties[$field]);
	}

	/**
	 * Get all the remote properties
	 * 
	 * @return array
	 */
	public function getRemoteProperties() {
		return $this->remoteProperties;
	}

	/**
	 * Perform a search query on this data source
	 *
	 * @param $filter A filter expression of some kind, in SQL format.
	 * @param $sort A sort expression, in SQL format.
	 * @param $join A join expression.  May or may not be relevant.
	 * @param $limit A limit expression, either "(count)", or "(start), (count)"
	 */
	function instance_get($filter = "", $sort = "", $join = "", $limit = "", $containerClass = "ArrayList") {
		
	}

	/**
	 * Retrieve a single record from this data source
	 *
	 * @param $filter A filter expression of some kind, in SQL format.
	 * @param $sort A sort expression, in SQL format.
	 * @param $join A join expression.  May or may not be relevant.
	 * @param $limit A limit expression, either "(count)", or "(start), (count)"
	 */
	function instance_get_one($filter, $sort = "") {
		
	}
	

	/**
	 * Write the current object back to the database.  It should know whether this is a new object, in which case this would
	 * be an insert command, or if this is an existing object queried from the database, in which case thes would be 
	 */
	function write() {
		
	}

	/**
	 * Remove this object from the database.  Doesn't do anything if this object isn't in the database.
	 */
	function delete() {
		
	}

	/**
	 * Save content from a form into a field on this data object.
	 * Since the data comes straight from a form it can't be trusted and will need to be validated / escaped.'
	 */
	function setCastedField($fieldName, $val) {
		$mapping = $this->editableFieldMapping();
		if (isset($mapping[$fieldName])) {
			$this->$fieldName = $val;
		}
	}

	/**
	 * Return the CSS classes to apply to this node in the CMS tree
	 *
	 * @return string
	 */
	function CMSTreeClasses() {
		$classes = sprintf('class-%s', $this->class);
		// Ensure that classes relating to whether there are further nodes to download are included
		$classes .= $this->markingClasses();
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
		$title = $this->Title;
		if (!$title) {
			$title = $this->Name;
		}
		
		$treeTitle = sprintf(
			"<span class=\"jstree-pageicon\"></span><span class=\"item\">%s</span>",
			Convert::raw2xml(str_replace(array("\n","\r"),"", $title))
		);
		return $treeTitle;
	}
}
