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
 * Implements ExternalContentImporter ? 
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 *
 */
abstract class ExternalContentImporter
{
	protected $contentTransforms = array();
	
	public function __construct()
	{
	}

	/**
	 * Import from a content source to a particular target
	 *  
	 * @param ExternalContentItem $contentItem
	 * @param SiteTree $target
	 * @param boolean $includeParent
	 * 			Whether to include the selected item in the import or not
	 * @param String $duplicateStrategy
	 * 			How to handle duplication 
	 * @param array $params All parameters passed with the import request.
	 */
	public function import($contentItem, $target, $includeParent = false, 
		$includeChildren = true, $duplicateStrategy='overwrite', $params = array())
	{

		// if the queuedjobs module exists, use that
		$queuedVersion = 'Queued'.get_class($this);
		if (ClassInfo::exists('QueuedJob') && ClassInfo::exists($queuedVersion)) {
			$importer = new $queuedVersion($contentItem, $target, $includeParent, $includeChildren, $duplicateStrategy);
			singleton('QueuedJobService')->queueJob($importer);
			return;
		}

		$children = null;
		if ($includeParent) {
			// Get the children of a particular node
			$children = new DataObjectSet();
			$children->push($contentItem);
		} else {
			$children = $contentItem->stageChildren();
		}

		$this->importChildren($children, $target, $includeChildren, $duplicateStrategy);
	}

	/**
	 * Execute the importing of several children
	 * 
	 * @param DataObjectSet $children
	 * @param SiteTree $parent
	 */
	protected function importChildren($children, $parent, $includeChildren, $duplicateStrategy)
	{
		if (!$children) {
			return;
		}

		// get the importer to use, import, then see if there's any
		foreach ($children as $child) {
			$pageType = $this->getExternalType($child); 
			if (isset($this->contentTransforms[$pageType])) {
				$transformer = $this->contentTransforms[$pageType];
				$result = $transformer->transform($child, $parent, $duplicateStrategy);

				// if there's more, then transform them
				if ($includeChildren && $result && $result->children && count($result->children)) {
					// import the children
					$this->importChildren($result->children, $result->page, $includeChildren, $duplicateStrategy);
				}
			}
		}
	}
	
	/**
	 * Get the type of the item as far as the remote system 
	 * is concerned. This should match up with what is defined
	 * in the contentTransforms array
	 * 
	 * @return String 
	 * 			The type of the ExternalContentItem
	 */
	protected abstract function getExternalType($item);
}

?>