<?php

/**
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class ScheduledExternalImportJob extends AbstractQueuedJob {

	const MIN_REPEAT = 300;

	public function __construct($repeat = null, $contentItem = null, $target = null, $includeParent = false, $includeChildren = true, $targetType = null, $duplicateStrategy='Overwrite', $params = array()) {
		if ($contentItem) {
			$this->sourceObjectID = $contentItem->ID;
			$this->setObject($target);

			$this->includeParent = $includeParent;
			$this->includeChildren = $includeChildren;
			$this->duplicateStrategy = $duplicateStrategy;
			$this->targetType = $targetType;

			$this->params = $params;
			$repeat = (int) $repeat;
			if ($repeat > 0) {
				$this->repeat = $repeat < self::MIN_REPEAT ? self::MIN_REPEAT : $repeat;
			} else {
				$this->repeat = 0;
			}

			$this->totalSteps = 1;
		}
	}

	protected $source;

	public function getSource() {
		if ($this->sourceObjectID && !$this->source) {
			$this->source = ExternalContent::getDataObjectFor($this->sourceObjectID);

		}
		return $this->source;
	}

	public function getTitle() {
		return "Scheduled import from " . $this->getSource()->Title;
	}

	public function getSignature() {
		return parent::getSignature();
	}

	public function process() {
		$source = $this->getSource();
		$target = $this->getObject();

		if ($source && $target) {
			$externalSource = $source instanceof ExternalContentItem ? $source->getSource() : $source;

			$importer = null;
			$importer = $externalSource->getContentImporter($this->targetType);

			if ($importer) {
				$importer->import($source, $target, $this->includeParent, $this->includeChildren, $this->duplicateStrategy, $this->params);
			}

			if ($this->repeat) {
				$job = new ScheduledExternalImportJob($this->repeat, $source, $target, $this->includeParent, $this->includeChildren, $this->targetType, $this->duplicateStrategy, $this->params);
				singleton('QueuedJobService')->queueJob($job, date('Y-m-d H:i:s', time() + $this->repeat));
			}
		}

		$this->currentStep++;
		$this->isComplete = true;
	}
}
