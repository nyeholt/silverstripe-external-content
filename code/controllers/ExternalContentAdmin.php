<?php

/**
 * Backend administration pages for the external content module
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD License http://silverstripe.org/bsd-license
 */
class ExternalContentAdmin extends LeftAndMain implements CurrentPageIdentifier, PermissionProvider{
	/**
	 * The URL format to get directly to this controller
	 * @var unknown_type
	 */
	const URL_STUB = 'extadmin';

	/**
	 * The directory that the module is assuming it's installed in to.
	 */
	static $directory = 'external-content';

	/**
	 * URL segment used by the backend
	 *
	 * @var string
	 */
	private static $url_segment = 'external-content';
	private static $url_rule = '$Action//$ID';
	private static $menu_title = 'External Content';
	private static $tree_class = 'ExternalContentSource';
	private static $allowed_actions = array(
		'addprovider',
		'deleteprovider',
		'deletemarked',
		'CreateProviderForm',
		'DeleteItemsForm',
		'getsubtree',
		'save',
		'migrate',
		'download',
		'view',
		'treeview',
		'EditForm',
		'AddForm',
		'updateSources',
		'updatetreenodes'
	);

	public function init(){
		parent::init();
		Requirements::css(CMS_DIR . '/css/screen.css');
		Requirements::customCSS($this->generatePageIconsCss());
		Requirements::css(self::$directory . '/css/external-content-admin.css');
		Requirements::javascript(self::$directory . '/javascript/external-content-admin.js');
		Requirements::javascript(self::$directory . '/javascript/external-content-reload.js');
	}


	/**
	 * Overridden to properly output a value and end, instead of
	 * letting further headers (X-Javascript-Include) be output
	 */
	public function pageStatus() {
		// If no ID is set, we're merely keeping the session alive
		if (!isset($_REQUEST['ID'])) {
			echo '{}';
			return;
		}

		parent::pageStatus();
	}

	/**
	 *
	 * If there's no ExternalContentSource ID available from Session or Request data then instead of
	 * LeftAndMain::currentPageID() returning just `null`, "extend" its range to use the first sub-class
	 * of {@link ExternalContentSource} the system can find, either via config or introspection.
	 *
	 * @return number | null
	 */
	public function getCurrentPageID() {
		if(!$id = $this->currentPageID()) {
			// Try an id from an ExternalContentSource Subclass
			$defaultSources = ClassInfo::getValidSubClasses('ExternalContentSource');
			array_shift($defaultSources);
			// Use one if defined in config, otherwise use first one found through reflection
			$defaultSourceConfig = Config::inst()->get('ExternalContentSource', 'default_source');
			if($defaultSourceConfig) {
				$class = $defaultSourceConfig;
			}
			else if(isset($defaultSources[0])) {
				$class = $defaultSources[0];
			}
			else {
				$class = null;
			}

			if($class && $source = DataObject::get($class)->first()) {
				return $source->ID;
			}
			return null;
		}
		return $id;
	}
    
    public function currentPageID() {
		if($this->getRequest()->requestVar('ID') && preg_match(ExternalContent::ID_FORMAT, $this->getRequest()->requestVar('ID')))	{
			return $this->getRequest()->requestVar('ID');
		} elseif (isset($this->urlParams['ID']) && preg_match(ExternalContent::ID_FORMAT, $this->urlParams['ID'])) {
			return $this->urlParams['ID'];
		} elseif(Session::get($this->sessionNamespace() . ".currentPage")) {
			return Session::get($this->sessionNamespace() . ".currentPage");
		} else {
			return null;
		}
	}

	/**
	 *
	 * Custom currentPage() method to handle opening the 'root' folder
	 *
	 * @return DataObject
	 */
	public function currentPage() {
		$id = $this->getCurrentPageID();
		if (preg_match(ExternalContent::ID_FORMAT, $id)) {
			return ExternalContent::getDataObjectFor($id);
		}
		if ($id == 'root') {
			return singleton($this->stat('tree_class'));
		}
	}


	/**
	 * Is the passed in ID a valid
	 * format?
	 *
	 * @return boolean
	 */
	public static function isValidId($id) {
		return preg_match(ExternalContent::ID_FORMAT, $id);
	}


	/**
	 * Action to migrate a selected object through to SS
	 *
	 * @param array $request
	 */
	public function migrate($request) {
		$migrationTarget 		= isset($request['MigrationTarget']) ? $request['MigrationTarget'] : '';
		$fileMigrationTarget 	= isset($request['FileMigrationTarget']) ? $request['FileMigrationTarget'] : '';
		$includeSelected 		= isset($request['IncludeSelected']) ? $request['IncludeSelected'] : 0;
		$includeChildren 		= isset($request['IncludeChildren']) ? $request['IncludeChildren'] : 0;
		$duplicates 			= isset($request['DuplicateMethod']) ? $request['DuplicateMethod'] : ExternalContentTransformer::DS_OVERWRITE;
		$selected 				= isset($request['ID']) ? $request['ID'] : 0;

		if(!$selected){
			$messageType = 'bad';
			$message = _t('ExternalContent.NOITEMSELECTED', 'No item selected to import.');
		}

		if(!$migrationTarget || !$fileMigrationTarget){
			$messageType = 'bad';
			$message = _t('ExternalContent.NOTARGETSELECTED', 'No target to import to selected.');
		}

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

			if (isset($request['Repeat']) && $request['Repeat'] > 0) {
				$job = new ScheduledExternalImportJob($request['Repeat'], $from, $target, $includeSelected, $includeChildren, $targetType, $duplicates, $request);
				singleton('QueuedJobService')->queueJob($job);
			} else {
				$importer = null;
				$importer = $from->getContentImporter($targetType);

				if ($importer) {
					$importer->import($from, $target, $includeSelected, $includeChildren, $duplicates, $request);
				}
			}

			$messageType = 'good';
			$message = _t('ExternalContent.CONTENTMIGRATED', 'Import Successful.');
		}

		Session::set("FormInfo.Form_EditForm.formError.message",$message);
		Session::set("FormInfo.Form_EditForm.formError.type", $messageType);

		return $this->getResponseNegotiator()->respond($this->request);
	}

	/**
	 * Return the record corresponding to the given ID.
	 *
	 * Both the numeric IDs of ExternalContentSource records and the composite IDs of ExternalContentItem entries
	 * are supported.
	 *
	 * @param  string $id The ID
	 * @return Dataobject The relevant object
	 */
	public function getRecord($id) {
		if(is_numeric($id)) {
			return parent::getRecord($id);
		} else {
			return ExternalContent::getDataObjectFor($id);
		}
	}


		/**
	 * Return the edit form
	 * @see cms/code/LeftAndMain#EditForm()
	 */
	public function EditForm($request = null) {
		HtmlEditorField::include_js();

		$cur = $this->getCurrentPageID();
		if ($cur) {
			$record = $this->currentPage();
			if (!$record)
				return false;
			if ($record && !$record->canView())
				return Security::permissionFailure($this);
		}

		if ($this->hasMethod('getEditForm')) {
			return $this->getEditForm($this->getCurrentPageID());
		}

		return false;
	}


	/**
	 * Return the form for editing
	 */
	function getEditForm($id = null, $fields = null) {
		$record = null;

		if(!$id){
			$id = $this->getCurrentPageID();
		}

		if ($id && $id != "root") {
			$record = $this->getRecord($id);
		}

		if ($record) {
			$fields = $record->getCMSFields();

			// If we're editing an external source or item, and it can be imported
			// then add the "Import" tab.
			$isSource = $record instanceof ExternalContentSource;
			$isItem = $record instanceof ExternalContentItem;

			if (($isSource || $isItem) && $record->canImport()) {
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

				$fields->addFieldToTab('Root.Import', new OptionsetField(
						"DuplicateMethod",
						_t('ExternalContent.DUPLICATES', 'Select how duplicate items should be handled'),
						$duplicateOptions,
						$duplicateOptions[ExternalContentTransformer::DS_SKIP]
					)
				);

				if (class_exists('QueuedJobDescriptor')) {
					$repeats = array(
						0		=> 'None',
						300		=> '5 minutes',
						900		=> '15 minutes',
						1800	=> '30 minutes',
						3600	=> '1 hour',
						33200	=> '12 hours',
						86400	=> '1 day',
						604800	=> '1 week',
					);
					$fields->addFieldToTab('Root.Import', new DropdownField('Repeat', 'Repeat import each ', $repeats));
				}

				$migrateButton = FormAction::create('migrate', _t('ExternalContent.IMPORT', 'Start Importing'))
					->setAttribute('data-icon', 'arrow-circle-double')
					->setUseButtonTag(true);

				$fields->addFieldToTab('Root.Import', new LiteralField('MigrateActions', "<div class='Actions'>{$migrateButton->forTemplate()}</div>"));
			}

			$fields->push($hf = new HiddenField("ID"));
			$hf->setValue($id);

			$fields->push($hf = new HiddenField("Version"));
			$hf->setValue(1);

			$actions = new FieldList();

			$actions = CompositeField::create()->setTag('fieldset')->addExtraClass('ss-ui-buttonset');
			$actions = new FieldList($actions);

			// Only show save button if not 'assets' folder
			if ($record->canEdit()) {
				$actions->push(
					FormAction::create('save',_t('ExternalContent.SAVE','Save'))
						->addExtraClass('ss-ui-action-constructive')
						->setAttribute('data-icon', 'accept')
						->setUseButtonTag(true)
				);
			}

			if($isSource && $record->canDelete()){
				$actions->push(
					FormAction::create('delete',_t('ExternalContent.DELETE','Delete'))
						->addExtraClass('delete ss-ui-action-destructive')
						->setAttribute('data-icon', 'decline')
						->setUseButtonTag(true)
				);
			}




			$form = new Form($this, "EditForm", $fields, $actions);
			if ($record->ID) {
				$form->loadDataFrom($record);
			} else {
				$form->loadDataFrom(array(
					"ID" => "root",
					"URL" => Director::absoluteBaseURL() . self::$url_segment,
				));
			}

			if (!$record->canEdit()) {
				$form->makeReadonly();
			}

		} else {
			// Create a dummy form
			$fields = new FieldList();
			$form = new Form($this, "EditForm", $fields, new FieldList());
		}

		$form->addExtraClass('cms-edit-form center ss-tabset ' . $this->BaseCSSClasses());
		$form->setTemplate($this->getTemplatesWithSuffix('_EditForm'));
		$form->setAttribute('data-pjax-fragment', 'CurrentForm');
		$this->extend('updateEditForm', $form);

		return $form;
	}


		/**
	 * Get the form used to create a new provider
	 *
	 * @return Form
	 */
	public function AddForm() {
		$classes = ClassInfo::subclassesFor(self::$tree_class);
		array_shift($classes);

		foreach ($classes as $key => $class) {
			if (!singleton($class)->canCreate())
				unset($classes[$key]);
			$classes[$key] = FormField::name_to_label($class);
		}

		$fields = new FieldList(
			new HiddenField("ParentID"),
			new HiddenField("Locale", 'Locale', i18n::get_locale()),
			$type = new DropdownField("ProviderType", "", $classes)
		);
		$type->setAttribute('style', 'width:150px');

		$actions = new FieldList(
			FormAction::create("addprovider", _t('ExternalContent.CREATE', "Create"))
				->addExtraClass('ss-ui-action-constructive')->setAttribute('data-icon', 'accept')
				->setUseButtonTag(true)
		);

		$form = new Form($this, "AddForm", $fields, $actions);

		$form->addExtraClass('cms-edit-form ' . $this->BaseCSSClasses());
		$this->extend('updateEditForm', $form);

		return $form;
	}

	/**
	 * Add a new provider (triggered by the ExternalContentAdmin_left template)
	 *
	 * @return unknown_type
	 */
	public function addprovider() {
		// Providers are ALWAYS at the root
		$parent = 0;

		$name = (isset($_REQUEST['Name'])) ? basename($_REQUEST['Name']) : _t('ExternalContent.NEWCONNECTOR', "New Connector");

		$type = $_REQUEST['ProviderType'];
		$providerClasses = ClassInfo::subclassesFor(self::$tree_class);

		if (!in_array($type, $providerClasses)) {
			throw new Exception("Invalid connector type");
		}

		$parentObj = null;

		// Create object
		$record = new $type();
		$record->ParentID = $parent;
		$record->Name = $record->Title = $name;

		// if (isset($_REQUEST['returnID'])) {
		// 	return $p->ID;
		// } else {
		// 	return $this->returnItemToUser($p);
		// }

		try {
			$record->write();
		} catch(ValidationException $ex) {
			$form->sessionMessage($ex->getResult()->message(), 'bad');
			return $this->getResponseNegotiator()->respond($this->request);
		}

		singleton('CMSPageEditController')->setCurrentPageID($record->ID);

		Session::set(
			"FormInfo.Form_EditForm.formError.message",
			sprintf(_t('ExternalContent.SourceAdded', 'Successfully created %s'), $type)
		);
		Session::set("FormInfo.Form_EditForm.formError.type", 'good');

		$msg = "New " . FormField::name_to_label($type) . " created";
		$this->response->addHeader('X-Status', rawurlencode(_t('ExternalContent.PROVIDERADDED', $msg)));
		return $this->getResponseNegotiator()->respond($this->request);
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
						new FieldList(
								new LiteralField('SelectedPagesNote',
										sprintf('<p>%s</p>', _t('ExternalContentAdmin.SELECT_CONNECTORS', 'Select the connectors that you want to delete and then click the button below'))
								),
								new HiddenField('csvIDs')
						),
						new FieldList(
								new FormAction('deleteprovider', _t('ExternalContentAdmin.DELCONNECTORS', 'Delete the selected connectors'))
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

		if (!$ids)
			return false;

		foreach ($ids as $id) {
			if (is_numeric($id)) {
				$record = ExternalContent::getDataObjectFor($id);
				if ($record) {
					$script .= $this->deleteTreeNodeJS($record);
					$record->delete();
					$record->destroy();
				}
			}
		}

		$size = sizeof($ids);
		if ($size > 1) {
			$message = $size . ' ' . _t('AssetAdmin.FOLDERSDELETED', 'folders deleted.');
		} else {
			$message = $size . ' ' . _t('AssetAdmin.FOLDERDELETED', 'folder deleted.');
		}

		$script .= "statusMessage('$message');";
		echo $script;
	}


	public function getCMSTreeTitle(){
		return 'Connectors';
	}

	public function LinkTreeView() {
		return $this->Link('treeview');
	}


	/**
	 * @return String HTML
	 */
	public function treeview($request) {
		return $this->renderWith($this->getTemplatesWithSuffix('_TreeView'));
	}

	public function SiteTreeAsUL() {
		$html = $this->getSiteTreeFor($this->stat('tree_class'), null, null, 'NumChildren');
		$this->extend('updateSiteTreeAsUL', $html);
		return $html;
	}

	/**
	 * Get a subtree underneath the request param 'ID'.
	 * If ID = 0, then get the whole tree.
	 */
	public function getsubtree($request) {
		$html = $this->getSiteTreeFor(
			'ExternalContentItem',
			$request->getVar('ID'),
			null,
			'NumChildren',
			null,
			$request->getVar('minNodeCount')
		);

		// Trim off the outer tag
		$html = preg_replace('/^[\s\t\r\n]*<ul[^>]*>/','', $html);
		$html = preg_replace('/<\/ul[^>]*>[\s\t\r\n]*$/','', $html);

		return $html;
	}


 	/**
	 * Include CSS for page icons. We're not using the JSTree 'types' option
	 * because it causes too much performance overhead just to add some icons.
	 *
	 * @return String CSS
	 */
	public function generatePageIconsCss() {
		$css = '';

		$sourceClasses 	= ClassInfo::subclassesFor('ExternalContentSource');
		$itemClasses 	= ClassInfo::subclassesFor('ExternalContentItem');
		$classes 		= array_merge($sourceClasses, $itemClasses);

		foreach($classes as $class) {
			$obj = singleton($class);
			$iconSpec = $obj->stat('icon');

			if(!$iconSpec) continue;

			// Legacy support: We no longer need separate icon definitions for folders etc.
			$iconFile = (is_array($iconSpec)) ? $iconSpec[0] : $iconSpec;

			// Legacy support: Add file extension if none exists
			if(!pathinfo($iconFile, PATHINFO_EXTENSION)) $iconFile .= '-file.gif';

			$iconPathInfo = pathinfo($iconFile);

			// Base filename
			$baseFilename = $iconPathInfo['dirname'] . '/' . $iconPathInfo['filename'];
			$fileExtension = $iconPathInfo['extension'];

			$selector = ".page-icon.class-$class, li.class-$class > a .jstree-pageicon";

			if(Director::fileExists($iconFile)) {
				$css .= "$selector { background: transparent url('$iconFile') 0 0 no-repeat; }\n";
			} else {
				// Support for more sophisticated rules, e.g. sprited icons
				$css .= "$selector { $iconFile }\n";
			}

		}

		$css .= "li.type-file > a .jstree-pageicon { background: transparent url('framework/admin/images/sitetree_ss_pageclass_icons_default.png') 0 0 no-repeat; }\n}";

		return $css;
	}

	/**
	 * Save the content source/item.
	 */
	public function save($urlParams, $form) {

		// Retrieve the record.

		$record = null;
		if (isset($urlParams['ID'])) {
			$record = ExternalContent::getDataObjectFor($urlParams['ID']);
		}
		if (!$record) {
			return parent::save($urlParams, $form);
		}

		if ($record->canEdit()) {
			// lets load the params that have been sent and set those that have an editable mapping
			if ($record->hasMethod('editableFieldMapping')) {
				$editable = $record->editableFieldMapping();
				$form->saveInto($record, array_keys($editable));
				$record->remoteWrite();
			} else {
				$form->saveInto($record);
				$record->write();
			}

			// Set the form response.

			$this->response->addHeader('X-Status', rawurlencode(_t('LeftAndMain.SAVEDUP', 'Saved.')));
		} else {
			$this->response->addHeader('X-Status', rawurlencode(_t('LeftAndMain.SAVEDUP', 'You don\'t have write access.')));
		}
		return $this->getResponseNegotiator()->respond($this->request);
	}

	/**
	 * Delete the content source/item.
	 */
	public function delete($data, $form) {
		$className = $this->stat('tree_class');

		$record = DataObject::get_by_id($className, Convert::raw2sql($data['ID']));
		if($record && !$record->canDelete()) return Security::permissionFailure();
		if(!$record || !$record->ID) $this->httpError(404, "Bad record ID #" . (int)$data['ID']);
		$type = $record->ClassName;
		$record->delete();

		Session::set("FormInfo.Form_EditForm.formError.message", "Deleted $type");
		Session::set("FormInfo.Form_EditForm.formError.type", 'bad');

		$this->response->addHeader('X-Status', rawurlencode(_t('LeftAndMain.DELETED', 'Deleted.')));
		return $this->getResponseNegotiator()->respond(
			$this->request,
			array('currentform' => array($this, 'EmptyForm'))
		);
	}

	/**
	 * Retrieve the updated source list, used in an AJAX request to update the current view.
	 * @return String
	 */
	public function updateSources() {

		$HTML = $this->treeview($this->request)->value;
		return preg_replace('/^\s+|\n|\r|\s+$/m', '', $HTML);
	}


	public function updatetreenodes($request) {
		// noop
		return parent::updatetreenodes($request);
	}

}