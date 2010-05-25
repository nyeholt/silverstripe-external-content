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

define('EXTERNALCONTENT', 'external-content');

/**
 * Backend administration pages for the external content module
 * 
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 *
 */
class ExternalContentAdmin extends LeftAndMain
{


	/**
	 * The URL format to get directly to this controller
	 * @var unknown_type
	 */
	const URL_STUB = 'extadmin';

	/**
	 * URL segment used by the backend 
	 * 
	 * @var string
	 */
	static $url_segment = EXTERNALCONTENT;

	static $url_rule = '$Action//$ID';

	static $menu_title = 'External Content';

	public static $tree_class = 'ExternalContentSource';
	
	static $allowed_actions = array(
		'addprovider',
		'deleteprovider',
		'deletemarked',
		'CreateProviderForm',
		'DeleteItemsForm',
		'getsubtree',
		'save',
		'migrate',
		'download',
		'view'
	);

	/**
	 * Set up the controller, in particular, re-sync the File database with the assets folder./
	 */
	function init() {
		parent::init();

		Requirements::javascript('external-content/javascript/ExternalContent.js');
		Requirements::javascript('external-content/javascript/ExternalContent.jquery.js');

		Requirements::javascript(CMS_DIR . "/javascript/CMSMain_upload.js");
		Requirements::javascript(CMS_DIR . "/javascript/Upload.js");
		Requirements::javascript(CMS_DIR . "/thirdparty/swfupload/swfupload.js");

		Requirements::javascript(THIRDPARTY_DIR . "/greybox/AmiJS.js");
		Requirements::javascript(THIRDPARTY_DIR . "/greybox/greybox.js");
		Requirements::css(THIRDPARTY_DIR . "/greybox/greybox.css");
	}

	/**
	 * Overridden to properly output a value and end, instead of
	 * letting further headers (X-Javascript-Include) be output
	 */
	public function pageStatus() {
		// If no ID is set, we're merely keeping the session alive
		if (!isset($_REQUEST['ID'])) {
			echo '{}';
			return ;
		}

		parent::pageStatus();
	}

	/**
	 * Return fake-ID "root" if no ID is found (needed for creating providers... ?)
	 * 
	 * Copied from AssetAdmin, not sure exactly what this is needed for
	 */
	public function currentPageID() {
		if(isset($_REQUEST['ID']) && preg_match(ExternalContent::ID_FORMAT, $_REQUEST['ID']))	{
			return $_REQUEST['ID'];
		} elseif (preg_match(ExternalContent::ID_FORMAT, $this->urlParams['ID'])) {
			return $this->urlParams['ID'];
		} elseif(strlen(Session::get("{$this->class}.currentPage"))) {
			return Session::get("{$this->class}.currentPage");
		} else {
			return "root";
		}
	}

	/**
	 * Custom currentPage() method to handle opening the 'root' folder
	 */
	public function currentPage() {
		$id = $this->currentPageID();
		if(preg_match(ExternalContent::ID_FORMAT, $id)) {
			
			return ExternalContent::getDataObjectFor($id);
		} else if($id == 'root') {
			return singleton($this->stat('tree_class'));
		}
	}

	/**
	 * Return the edit form
	 * @see cms/code/LeftAndMain#EditForm()
	 */
	public function EditForm() {
		HtmlEditorField::include_js();

		$cur = $this->currentPageID();
		if ($cur) {
			$record = $this->currentPage();
			if(!$record) return false;
			if($record && !$record->canView()) return Security::permissionFailure($this);
		}

		if ($this->hasMethod('getEditForm')) {
			return $this->getEditForm($this->currentPageID());
		}

		return false;
	}

	/**
	 * Is the passed in ID a valid
	 * format? 
	 * 
	 * @return boolean
	 */
	public static function isValidId($id)
	{
		return preg_match(ExternalContent::ID_FORMAT, $id);
	}

	/**
	 * Action to migrate a selected object through to SS
	 * 
	 * @param HtTP_Request $request
	 */
	public function migrate($request)
	{
		$migrationTarget = isset($request['MigrationTarget']) ? $request['MigrationTarget'] : '';
		$fileMigrationTarget = isset($request['FileMigrationTarget']) ? $request['FileMigrationTarget'] : '';
		$includeSelected = isset($request['IncludeSelected']) ? $request['IncludeSelected'] : 0;
		$includeChildren = isset($request['IncludeChildren']) ? $request['IncludeChildren'] : 0;
		
		$duplicates = isset($request['DuplicateMethod']) ? $request['DuplicateMethod'] : ExternalContentTransformer::DS_OVERWRITE;

		$selected =  isset($request['ID']) ? $request['ID'] : 0;

		$result = array(
			'message' => "Invalid request",
			'status' => false
		);

		if ($selected && ($migrationTarget || $fileMigrationTarget)) {
			// get objects and start stuff
			$target = null;
			$targetType = 'SiteTree';
			if ($migrationTarget) {
				$target = DataObject::get_by_id('SiteTree', $migrationTarget);
			} else {
				$targetType = 'File';
				$target = DataObject::get_by_id('File', $fileMigrationTarget); 
			}

			$from = ExternalContent::getDataObjectFor($selected);
			if ($from instanceof ExternalContentSource) {
				$selected = false;
			}
			$importer = null;
			
			$importer = $from->getContentImporter($targetType);
			
			if ($importer) {
				$importer->import($from, $target, $includeSelected, $includeChildren, $duplicates);
			}
			$result['message'] = "Starting import to ".$target->Title;
			$result['status'] = true;
		}

		echo Convert::raw2json($result);
	}

	/**
	 * Return the form that displays the details of a folder, including a file list and fields for editing the folder name.
	 */
	function getEditForm($id) {
		$record = null;
		if($id && $id != "root") {
			$record = ExternalContent::getDataObjectFor($id);
		} else {
		}

		if($record) {
			$fields = $record->getCMSFields();
			
			$migrate = false;
			// create the 'migrate' tab if it's an external item
			if ($record instanceof ExternalContentItem || $record instanceof ExternalContentSource) {
				$migrate = true;
				$allowedTypes = $record->allowedImportTargets();
				if (isset($allowedTypes['sitetree'])) {
					$fields->addFieldToTab('Root.Import', new TreeDropdownField("MigrationTarget", _t('ExternalContent.MIGRATE_TARGET', 'Page to import into'), 'SiteTree'));
				}
	
				if (isset($allowedTypes['file'])) {
					$fields->addFieldToTab('Root.Import', new TreeDropdownField("FileMigrationTarget", _t('ExternalContent.FILE_MIGRATE_TARGET', 'Folder to import into'), 'Folder'));
				}

				$fields->addFieldToTab('Root.Import', new CheckboxField("IncludeSelected", _t('ExternalContent.INCLUDE_SELECTED', 'Include Selected Item in Import')));
				$fields->addFieldToTab('Root.Import', new CheckboxField("IncludeChildren", _t('ExternalContent.INCLUDE_CHILDREN', 'Include Child Items in Import'), true));
				
				$duplicateOptions = array(
					ExternalContentTransformer::DS_OVERWRITE => ExternalContentTransformer::DS_OVERWRITE,
					ExternalContentTransformer::DS_DUPLICATE => ExternalContentTransformer::DS_DUPLICATE,
					ExternalContentTransformer::DS_SKIP => ExternalContentTransformer::DS_SKIP,
				);

				$fields->addFieldToTab('Root.Import', new OptionsetField("DuplicateMethod", _t('ExternalContent.DUPLICATES', 'Select how duplicate items should be handled'), $duplicateOptions));

				$migrateButton = '<p><input type="submit" id="Form_EditForm_Migrate" name="action_migrate" value="'._t('ExternalContent.IMPORT', 'Start Importing').'" /></p>';
				$fields->addFieldToTab('Root.Import', new LiteralField('migrate', $migrateButton));
			}

			$fields->push($hf = new HiddenField("ID"));
			$hf->setValue($id);

			$fields->push($hf = new HiddenField("Version"));
			$hf->setValue(1);

			$actions = new FieldSet();
			// Only show save button if not 'assets' folder
			if($record->canEdit() && !($record instanceof ExternalContentItem)) {
				$actions = new FieldSet(
					new FormAction('save',_t('ExternalContent.SAVE','Save'))
				);
			}
			
			$form = new Form($this, "EditForm", $fields, $actions);
			if($record->ID) {
				$form->loadDataFrom($record);
			} else {
				$form->loadDataFrom(array(
					"ID" => "root",
					"URL" => Director::absoluteBaseURL() . self::$url_segment,
				));
			}

			if(!$record->canEdit()) {
				$form->makeReadonly();
			}

			return $form;
		} else {
			// Create a dummy form
			$fields = new FieldSet();
			return new Form($this, "EditForm", $fields, new FieldSet());
		}
	}
	
	/**
	 * Return the entire site tree as a nested UL.
	 * @return string HTML for site tree
	 */
	public function SiteTreeAsUL() {
		$obj = singleton('ExternalContentSource');
		$number = $obj->markPartialTree(1, null);
		
		if($p = $this->currentPage()) $obj->markToExpose($p);

		$titleEval = '"<li id=\"record-$child->ID\" class=\"$child->class" . $child->markingClasses() .  ($extraArg->isCurrentPage($child) ? " current" : "") . "\">" . ' .
			'"<a href=\"" . Controller::join_links(substr($extraArg->Link(),0,-1), "show", $child->ID) . "\" class=\" contents\" >" . $child->Title . "</a>" ';

		$this->generateTreeStylingJS();
		
		$siteTreeList = $obj->getChildrenAsUL(
			'',
			$titleEval,
			$this,
			true,
			'AllChildrenIncludingDeleted',
			'numChildren',
			true,
			1
		);

		// Wrap the root if needs be
		$rootLink = $this->Link() . 'show/root';
		$baseUrl = Director::absoluteBaseURL() . self::$url_segment;
		if(!isset($rootID)) {
			$siteTree = "<ul id=\"sitetree\" class=\"tree unformatted\"><li id=\"record-root\" class=\"Root\"><a href=\"$rootLink\"><strong>All Connectors</strong></a>"
			. $siteTreeList . "</li></ul>";
		}

		return $siteTree;
	}

	/**
	 * Returns a subtree of items underneath the given folder.
	 *
	 * We do our own version of returning tree data here - SilverStripe's base functionality is just too greedy
	 * with data for this to be happy.
	 */
	public function getsubtree() {
		$obj = ExternalContent::getDataObjectFor($_REQUEST['ID']);  //  DataObject::get_by_id('ExternalContentSource', $_REQUEST['ID']);

		if(isset($_GET['debug_profile'])) Profiler::mark("ExternalContentAdmin", "getsubtree");
		$siteTreeList = '';
		try {
			$children = $obj->stageChildren();
			if ($children) {
				foreach ($children as $child) {
					$siteTreeList .= '<li id="record-'.$child->ID.'" class="'.$child->class .' unexpanded closed">' .
					'<a href="' . Controller::join_links(substr($this->Link(),0,-1), "show", $child->ID) . '" class=" contents">' . $child->Title . '</a>';
				}
			}
		} catch (Exception $e) {
			singleton('ECUtils')->log("Failed creating tree: ".$e->getMessage(), SS_Log::ERR);
			singleton('ECUtils')->log($e->getTraceAsString(), SS_Log::ERR);
		}
		if(isset($_GET['debug_profile'])) Profiler::unmark("ExternalContentAdmin", "getsubtree");

		return $siteTreeList;
	}
	
	/**
	 * Stolen from CMSMain and changed to include our custom object
	 * classes
	 */
	public function generateTreeStylingJS() {
		$classes = ClassInfo::subclassesFor('DataObject');
		foreach($classes as $class) {
			$obj = singleton($class);
			if($obj instanceof HiddenClass) continue;
			if($icon = $obj->stat('icon')) $iconInfo[$class] = $icon;
		}
		$iconInfo['BrokenLink'] = 'cms/images/treeicons/brokenlink';


		$js = "var _TREE_ICONS = [];\n";

		foreach($iconInfo as $class => $icon) {
			
			// SiteTree::$icon can be set to array($icon, $option)
			// $option can be "file" or "folder" to force the icon to always be the file or the folder form
			$option = null;
			if(is_array($icon)) list($icon, $option) = $icon;

			$fileImage = ($option == "folder") ? $icon . '-openfolder.gif' : $icon . '-file.gif';
			$openFolderImage = $icon . '-openfolder.gif';
			if(!Director::fileExists($openFolderImage) || $option = "file") $openFolderImage = $fileImage;
			$closedFolderImage = $icon . '-closedfolder.gif';
			if(!Director::fileExists($closedFolderImage) || $option = "file") $closedFolderImage = $fileImage;

			$js .= <<<JS
				_TREE_ICONS['$class'] = {
					fileIcon: '$fileImage',
					openFolderIcon: '$openFolderImage',
					closedFolderIcon: '$closedFolderImage'
				};
JS;
		}

		Requirements::customScript($js);
	}
	
	public function getitem() {
		$this->setCurrentPageID($_REQUEST['ID']);
		SSViewer::setOption('rewriteHashlinks', false);

		if(isset($_REQUEST['ID'])) {
			$record = ExternalContent::getDataObjectFor($_REQUEST['ID']);
			if($record && !$record->canView()) return Security::permissionFailure($this);
		}

		$form = $this->EditForm();
		
		if ($form) {
			return $form->formHtmlContent();
		}
		
		return '';
	}
	
	/**
	 * Get the form used to create a new provider
	 * 
	 * @return Form
	 */
	public function CreateProviderForm()
	{
		$providerClasses = ClassInfo::subclassesFor(self::$tree_class);
		// remove the external content source abstract class
		array_shift($providerClasses);
		
		$fields = new FieldSet(
			new HiddenField("ParentID"),
			new HiddenField("Locale", 'Locale', Translatable::get_current_locale()),
			new DropdownField("ProviderType", "", $providerClasses)
		);

		$actions = new FieldSet(
			new FormAction("addprovider", _t('ExternalContent.CREATE',"Create"))
		);

		return new Form($this, "CreateProviderForm", $fields, $actions);
	}
	
	/**
	 * Add a new provider (triggered by the ExternalContentAdmin_left template)
	 * 
	 * @return unknown_type
	 */
	public function addprovider()
	{
		// Providers are ALWAYS at the root
		$parent = 0;

		$name = (isset($_REQUEST['Name'])) ? basename($_REQUEST['Name']) : _t('ExternalContent.NEWCONNECTOR',"New Connector");

		$type = $_REQUEST['ProviderType'];
		$providerClasses = ClassInfo::subclassesFor(self::$tree_class);
		
		if (!in_array($type, $providerClasses)) {
			throw new Exception("Invalid connector type");
		}

		$parentObj = null;

		// Create object
		$p = new $type();
		$p->ParentID = $parent;
		$p->Name = $p->Title = $name;
		$p->write();

		if(isset($_REQUEST['returnID'])) {
			return $p->ID;
		} else {
			return $this->returnItemToUser($p);
		}
	}
	
	/**
	 * Copied from AssetAdmin... 
	 * 
	 * @return Form
	 */
	function DeleteItemsForm() {
		$form = new Form(
			$this,
			'DeleteItemsForm',
			new FieldSet(
				new LiteralField('SelectedPagesNote',
					sprintf('<p>%s</p>', _t('ExternalContentAdmin.SELECT_CONNECTORS','Select the connectors that you want to delete and then click the button below'))
				),
				new HiddenField('csvIDs')
			),
			new FieldSet(
				new FormAction('deleteprovider', _t('ExternalContentAdmin.DELCONNECTORS','Delete the selected connectors'))
			)
		);

		$form->addExtraClass('actionparams');

		return $form;
	}
	
	/**
	 * Delete a folder
	 */
	public function deleteprovider() {
		$script = '';
		$ids = split(' *, *', $_REQUEST['csvIDs']);
		$script = '';

		if(!$ids) return false;

		foreach($ids as $id) {
			if(is_numeric($id)) {
				$record = ExternalContent::getDataObjectFor($id);
				if($record) {
					$script .= $this->deleteTreeNodeJS($record);
					$record->delete();
					$record->destroy();
				}
			}
		}

		$size = sizeof($ids);
		if($size > 1) {
		  $message = $size.' '._t('AssetAdmin.FOLDERSDELETED', 'folders deleted.');
		} else {
		  $message = $size.' '._t('AssetAdmin.FOLDERDELETED', 'folder deleted.');
		}

		$script .= "statusMessage('$message');";
		echo $script;
	}
}


?>