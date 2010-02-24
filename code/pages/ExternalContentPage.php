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
 * A Page type that lets you pull content through from any arbitrary
 * external content source. At some point soon, this might change to behave
 * more like a virtual page... Might be necessary in the future
 * 
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 *
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
	 * @return unknown_type
	 */
	public function ContentItem()
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
			if ($object && ($object instanceof ExternalContentItem)) {
				$template = 'ExternalContent_'.get_class($object).'_'.$object->getType();
				return $this->customise($object)->renderWith(array($template, 'ExternalContent_'.get_class($object), 'ExternalContent', 'Page'));
			}
		}

		echo "Template not found for ".($object ? $object->ID : '');
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