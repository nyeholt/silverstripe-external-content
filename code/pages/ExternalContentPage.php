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
class ExternalContentPage extends Page
{
	public static $db = array(
		'ExternalContentRoot' => 'Varchar(128)',
	);

	public static $has_one = array(
		// 'ExternalContent' => 'ExternalContentSource'
	);

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->removeFieldFromTab('Root.Content.Main', 'Content');
		$fields->addFieldToTab('Root.Content.Main', new ExternalTreeDropdownField('ExternalContentRoot', _t('ExternalContentPage.CONTENT_SOURCE', 'External Content Source'), 'ExternalContentSource'));

		return $fields;
	}

	/**
	 * When linking to this external content page, return a URL that'll let
	 * you view the external content item directly
	 * 
	 * (non-PHPdoc)
	 * @see sapphire/core/model/SiteTree#Link($action)
	 */
	public function RelativeLink()
	{
		$remoteObject = $this->ContentItem();
		if (!$remoteObject) {
			
			return parent::RelativeLink();
		}
		return $remoteObject->RelativeLink();
	}

	/**
	 * Cache the requested item so that repeated calls
	 * to this method doesn't make a bunch of extra requests
	 * 
	 * @var ExternalContentItem
	 */
	private $requestedItem;
	
	/**
	 * Get the external content item
	 * 
	 * @return DataObject
	 */
	public function ContentItem($what='k')
	{
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
	 * @return DataObjectSet
	 */
	public function Children()
	{
		$item = $this->ContentItem();
		return $item ? $item->stageChildren() : new DataObjectSet();
	}
}

/**
 * Contains methods for interacting with external content on the frontend
 * 
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 *
 */
class ExternalContentPage_Controller extends Page_Controller
{
	const URL_STUB = 'extcon';

	public static $allowed_actions = array(
		'view',
		'download',
	);

	public function init()
	{
		parent::init();
	}
	
	/**
	 * Display an item. 
	 * 
	 * @param HTTP_Request $request
	 * @return String
	 */
	public function view($request)
	{
		$object = null;
		if ($request->param('ID')) {
			$object = ExternalContent::getDataObjectFor($request->param('ID'));
			if ($object instanceof ExternalContentSource) {
				$object = $object->getRoot();
			}

			if ($object && ($object instanceof ExternalContentItem || $object instanceof ExternalContentSource)) {
				$type = $object instanceof ExternalContentItem ? $object->getType() : 'source';
				$template = 'ExternalContent_'.get_class($object).'_'.$type;
				return $this->customise($object)->renderWith(array($template, 'ExternalContent_'.get_class($object), 'ExternalContent', 'Page'));
			}
		}

		echo "Template not found for ".($object ? get_class($object) . ' #'.$object->ID : '');
	}

	/**
	 * Called to download this content item and stream it directly to the browser
	 * 
	 * @param HTTP_Request $request
	 */
	public function download($request)
	{
		if ($request->param('ID')) {
			$object = ExternalContent::getDataObjectFor($request->param('ID'));
			if ($object && $object instanceof ExternalContentItem) {
				$object->streamContent();
			}	
		}
	}
}

?>