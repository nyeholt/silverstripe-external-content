<?php

/**
 * A Page type that lets you pull content through from any arbitrary
 * external content source. At some point soon, this might change to behave
 * more like a virtual page... Might be necessary in the future
 * 
 * 
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license http://silverstripe.org/bsd-license/
 */
class ExternalContentPage extends Page {

	private static $db = array(
		'ExternalContentRoot' => 'Varchar(128)',
	);
	private static $has_one = array(
			// 'ExternalContent' => 'ExternalContentSource'
	);

	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->removeFieldFromTab('Root.Main', 'Content');
		$fields->addFieldToTab('Root.Main', new ExternalTreeDropdownField('ExternalContentRoot', _t('ExternalContentPage.CONTENT_SOURCE', 'External Content Source'), 'ExternalContentSource'));

		return $fields;
	}

	/**
	 * When linking to this external content page, return a URL that'll let
	 * you view the external content item directly
	 * 
	 * (non-PHPdoc)
	 * @see sapphire/core/model/SiteTree#Link($action)
	 */
	public function RelativeLink($action = null) {
		$remoteObject = $this->ContentItem();
		if (!is_string($action)) {
			$action = null;
		}
		if ($remoteObject) {
			return $this->LinkFor($remoteObject, $action ? $action : 'view');
		}
		return parent::RelativeLink($action);
	}
	
	public function LinkFor($remoteObject, $action = null) {
		$link = parent::RelativeLink();
		$id = $remoteObject->ID;
		// otherwise, we're after $this link (view) plus id
		return Controller::join_links($link, $action, $id);
	}

	/**
	 * Cache the requested item so that repeated calls
	 * to this method doesn't make a bunch of extra requests
	 * 
	 * @var ExternalContentItem
	 */
	private $requestedItem;
	
	public function setRequestedItem($item) {
		$this->requestedItem = $item;
	}

	/**
	 * Get the external content item
	 * 
	 * @return DataObject
	 */
	public function ContentItem($what='k') {
		if ($this->requestedItem) {
			return $this->requestedItem;
		}

		if (!$this->ExternalContentRoot) {
			return null;
		}

		// See if an item was requested in the url directly
		$id = isset($_REQUEST['item']) ? $_REQUEST['item'] : null;

		if (!$id) {
			$id = $this->ExternalContentRoot;
		}

		$remoteObject = ExternalContent::getDataObjectFor($id);

		if ($remoteObject) {
			$this->requestedItem = $remoteObject;
			return $this->requestedItem;
		}

		return null;
	}

	/**
	 * Return the children of this external content item as my children
	 *
	 * @return ArrayList
	 */
	public function Children() {
		$item = $this->ContentItem();
		return $item ? $item->stageChildren() : new ArrayList();
	}

}

/**
 * Contains methods for interacting with external content on the frontend
 * 
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 *
 */
class ExternalContentPage_Controller extends Page_Controller {
	const URL_STUB = 'extcon';

	private static $allowed_actions = array(
		'view',
		'download',
	);

	public function init() {
		parent::init();
	}

	/**
	 * Display an item. 
	 * 
	 * @param HTTP_Request $request
	 * @return String
	 */
	public function view($request) {
		$object = null;
		if ($id = $request->param('ID')) {
			$object = ExternalContent::getDataObjectFor($id);
			if ($object instanceof ExternalContentSource) {
				$object = $object->getRoot();
			}

			if ($object && ($object instanceof ExternalContentItem || $object instanceof ExternalContentSource)) {
				$this->data()->setRequestedItem($object);
				$type = $object instanceof ExternalContentItem ? $object->getType() : 'source';
				$template = 'ExternalContent_' . get_class($object) . '_' . $type;
				
				$viewer = new SSViewer(array($template, 'ExternalContent_' . get_class($object), 'ExternalContent', 'Page'));
				$action = 'view';
				$this->extend('updateViewer', $action, $viewer);
				
				return $this->customise($object)->renderWith($viewer);
			}
		}

		echo "Template not found for " . ($object ? get_class($object) . ' #' . $object->ID : '');
	}

	/**
	 * Called to download this content item and stream it directly to the browser
	 * 
	 * @param HTTP_Request $request
	 */
	public function download($request) {
		if ($request->param('ID')) {
			$object = ExternalContent::getDataObjectFor($request->param('ID'));
			if ($object && $object instanceof ExternalContentItem) {
				$object->streamContent();
			}
		}
	}
}
