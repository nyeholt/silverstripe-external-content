<?php

/**
 * Interface defining a transformer from an external content item
 * to an internal silverstripe page
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD License http://silverstripe.org/bsd-license
 *
 */
interface ExternalContentTransformer {
	const DS_OVERWRITE = 'Overwrite';
	const DS_DUPLICATE = 'Duplicate';
	const DS_SKIP = 'Skip';

	/**
	 * Transforms a given item, creating a new object underneath
	 * the parent object.
	 * @param $item
	 * 			The object to transform
	 * @param $parentObject
	 * 			The object to create any new pages underneath
	 * @param $duplicateStrategy
	 * 			How to handle duplicates when importing
	 *
	 * @return TransformResult
	 * 			The new page
	 */
	public function transform($item, $parentObject, $duplicateStrategy);
}

/**
 * Class to encapsulate the result of a transformation
 *
 * Contains
 *
 * page - The created page
 * children - A DataObjectSet containing those children
 * 			  that can still be used for additional tranforms
 * 			  This allows some chidlren to be filtered out (eg dependant pages)
 * 			  and loaded by the new page type instead
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 *
 */
class TransformResult {

	public function __construct($page, $children) {
		$this->page = $page;
		$this->children = $children;
	}

	/**
	 * @var SiteTree
	 */
	public $page;

	/**
	 * @var DataObjectSet
	 */
	public $children;

}
