<?php

/**
 * An abstract class for a content importer that works within the queuedjobs module
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD License http://silverstripe.org/bsd-license
 */
abstract class QueuedExternalContentImporter extends AbstractQueuedJob {

	protected $contentTransforms = array();

	public function __construct($contentItem = null, $target = null, $includeParent = false, $includeChildren = true, $duplicateStrategy='overwrite', $params = array()
	) {
		if ($contentItem) {
			$this->sourceObjectID = $contentItem->ID;
			$this->targetObjectID = $target->ID;
			$this->targetObjectType = $target->ClassName;
			$this->includeParent = $includeParent;
			$this->includeChildren = $includeChildren;
			$this->duplicateStrategy = $duplicateStrategy;
			$this->params = $params;
		} else {
			// if there's no constructor params, it means we're executing
			$this->init();
		}
	}

	public function getTitle() {
		if ($this->sourceObjectID) {
			$source = ExternalContent::getDataObjectFor($this->sourceObjectID);
			return "External Content import from " . $source->Title;
		}

		return 'External content import';
	}

	/**
	 * By default jobs should just go into the default processing queue
	 *
	 * @return String
	 */
	public function getJobType() {
		$sourceObject = ExternalContent::getDataObjectFor($this->sourceObjectID);
		if (!$sourceObject) {
			$this->addMessage("ERROR: Source object $this->sourceObjectID cannot be found");
			return QueuedJob::QUEUED;
		}

		// go a couple levels deep and see how many items we're looking at
		if (!$this->includeChildren) {
			return QueuedJob::QUEUED;
		}

		$children = $sourceObject->stageChildren();
		if (!$children) {
			return QueuedJob::QUEUED;
		}
		$count = 1;

		foreach ($children as $child) {
			$count++;
			if ($count > 20) {
				$this->totalSteps = $count;
				return QueuedJob::QUEUED;
			}

			$subChildren = $child->stageChildren();
			if ($subChildren) {
				foreach ($subChildren as $sub) {
					$count++;
					if ($count > 20) {
						$this->totalSteps = $count;
						return QueuedJob::QUEUED;
					}
				}
			}
		}
		$this->totalSteps = $count;
		return QueuedJob::QUEUED;
	}

	/**
	 * @return array
	 */
	public function getParams() {
		return $this->params;
	}

	/**
	 */
	public function setup() {
		$remainingChildren = array();
		if ($this->includeParent) {
			$remainingChildren[] = new EC_SourceTarget($this->sourceObjectID, $this->targetObjectID, $this->targetObjectType);
		} else {
			$sourceObject = ExternalContent::getDataObjectFor($this->sourceObjectID);
			if ($sourceObject) {
				$children = $sourceObject->stageChildren();
				if ($children) {
					foreach ($children as $child) {
						$remainingChildren[] = new EC_SourceTarget($child->ID, $this->targetObjectID, $this->targetObjectType);
					}
				}
			}
		}

		$this->totalSteps = count($remainingChildren);
		$this->remainingChildren = $remainingChildren;
	}

	/**
	 * Lets process a single node, and collect its children
	 */
	public function process() {
		$remainingChildren = $this->remainingChildren;

		if (!count($remainingChildren)) {
			$this->isComplete = true;
			return;
		}

		$this->currentStep++;

		// lets process our first item
		$pair = array_shift($remainingChildren);
		$sourceObject = ExternalContent::getDataObjectFor($pair->sourceID);
		if (!$sourceObject) {
			$this->addMessage("Missing source object for " . $pair->sourceID, 'WARNING');
			$this->remainingChildren = $remainingChildren;
			return;
		}

		$targetObject = DataObject::get_by_id($pair->targetType, $pair->targetID);
		if (!$targetObject) {
			$this->addMessage("Missing target object for $pair->targetType $pair->sourceID", 'WARNING');
			$this->remainingChildren = $remainingChildren;
			return;
		}

		// lets do a single import first, then check the children and append them
		$pageType = $this->getExternalType($sourceObject);
		if (isset($this->contentTransforms[$pageType])) {
			$transformer = $this->contentTransforms[$pageType];

			$result = $transformer->transform($sourceObject, $targetObject, $this->duplicateStrategy);

			// if there's more, then transform them
			if ($this->includeChildren && $result && $result->children && count($result->children)) {
				foreach ($result->children as $child) {
					$remainingChildren[] = new EC_SourceTarget($child->ID, $result->page->ID, $result->page->ClassName);
					$this->totalSteps++;
				}
			}
		}

		$this->remainingChildren = $remainingChildren;

		if (!count($remainingChildren)) {
			$this->isComplete = true;
			return;
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

/**
 * Tuple class for storing details about future imports when necessary
 */
class EC_SourceTarget {

	public $sourceID;
	public $targetID;
	public $targetType;

	public function __construct($sid, $tid, $t) {
		$this->sourceID = $sid;
		$this->targetID = $tid;
		$this->targetType = $t;
	}

}
