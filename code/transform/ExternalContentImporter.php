<?php

/**
 * Implements ExternalContentImporter ?
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD License http://silverstripe.org/bsd-license
 *
 */
abstract class ExternalContentImporter extends Object {

	protected $contentTransforms = array();
	protected $params = array();

	private static $use_queue = true;

	/**
	 * @return array
	 */
	public function getParams() {
		return $this->params;
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
	public function import($contentItem, $target, $includeParent = false, $includeChildren = true, $duplicateStrategy='Overwrite', $params = array()) {
		$this->runOnImportStart();
		$this->params = $params;

		// if the queuedjobs module exists, use that
		$queuedVersion = 'Queued' . get_class($this);
		if ($this->config()->use_queue && ClassInfo::exists('QueuedJob') && ClassInfo::exists($queuedVersion)) {
			$importer = new $queuedVersion(
							$contentItem,
							$target,
							$includeParent,
							$includeChildren,
							$duplicateStrategy,
							$params);

			$service = singleton('QueuedJobService');
			$service->queueJob($importer);
			return $importer;
		}

		$children = null;
		if ($includeParent) {
			// Get the children of a particular node
			$children = new ArrayList();
			$children->push($contentItem);
		} else {
			$children = $contentItem->stageChildren();
		}

		$this->importChildren($children, $target, $includeChildren, $duplicateStrategy);
		$this->runOnImportEnd();
		return true;
	}

	/**
	 * Execute the importing of several children
	 *
	 * @param DataObjectSet $children
	 * @param SiteTree $parent
	 */
	protected function importChildren($children, $parent, $includeChildren, $duplicateStrategy) {
		if (!$children) {
			return;
		}

		// get the importer to use, import, then see if there's any
		foreach ($children as $child) {
			$pageType = $this->getExternalType($child);
			if (isset($this->contentTransforms[$pageType])) {
				$transformer = $this->contentTransforms[$pageType];
				$result = $transformer->transform($child, $parent, $duplicateStrategy);

				$this->extend('onAfterImport', $result);

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

	/**
	 * Allow subclasses to run custom logic immediately prior to import start.
	 * Not declared abstract so method can be optionally defined on subclasses.
	 */
	public function runOnImportStart() {
	}

	/**
	 * Allow subclasses to run custom logic immediately after to import end.
	 * Not declared abstract so method can be optionally defined on subclasses.
	 */
	public function runOnImportEnd() {
	}
}
