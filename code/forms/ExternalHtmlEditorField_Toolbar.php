<?php

/**
 * Overridden toolbar that handles external content linking
 * 
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @deprecated
 *
 */
class ExternalHtmlEditorField_Toolbar extends RequestHandler {
	protected $controller, $name;
	
	function __construct($controller, $name) {
		parent::__construct();
		
		$this->controller = $controller;
		$this->name = $name;
	}
	
	/**
	 * Return a {@link Form} instance allowing a user to
	 * add links in the TinyMCE content editor.
	 *  
	 * @return Form
	 */
	function LinkForm() {
		Requirements::javascript(THIRDPARTY_DIR . "/behaviour.js");
		Requirements::javascript(ExternalContentAdmin::$directory . "/javascript/external_tiny_mce_improvements.js");

		$form = new Form(
			$this->controller,
			"{$this->name}/LinkForm", 
			new FieldSet(
				new LiteralField('Heading', '<h2><img src="cms/images/closeicon.gif" alt="' . _t('HtmlEditorField.CLOSE', 'close').'" title="' . _t('HtmlEditorField.CLOSE', 'close') . '" />' . _t('HtmlEditorField.LINK', 'Link') . '</h2>'),
				new OptionsetField(
					'LinkType',
					_t('HtmlEditorField.LINKTO', 'Link to'), 
					array(
						'internal' => _t('HtmlEditorField.LINKINTERNAL', 'Page on the site'),
						'external' => _t('HtmlEditorField.LINKEXTERNAL', 'Another website'),
						'anchor' => _t('HtmlEditorField.LINKANCHOR', 'Anchor on this page'),
						'email' => _t('HtmlEditorField.LINKEMAIL', 'Email address'),
						'file' => _t('HtmlEditorField.LINKFILE', 'Download a file'),
						'externalcontent' =>_t('HtmlEditorField.LINKEXTERNALCONTENT', 'External Content'),
					)
				),
				new TreeDropdownField('internal', _t('HtmlEditorField.PAGE', "Page"), 'SiteTree', 'ID', 'MenuTitle'),
				new TextField('external', _t('HtmlEditorField.URL', 'URL'), 'http://'),
				new EmailField('email', _t('HtmlEditorField.EMAIL', 'Email address')),
				new TreeDropdownField('file', _t('HtmlEditorField.FILE', 'File'), 'File', 'Filename'),
				new ExternalTreeDropdownField('externalcontent', _t('ExternalHtmlEditorField.EXTERNAL_CONTENT', 'External Content'), 'ExternalContentSource', 'Link()'),
				new TextField('Anchor', _t('HtmlEditorField.ANCHORVALUE', 'Anchor')),
				new TextField('LinkText', _t('HtmlEditorField.LINKTEXT', 'Link text')),
				new TextField('Description', _t('HtmlEditorField.LINKDESCR', 'Link description')),
				new CheckboxField('TargetBlank', _t('HtmlEditorField.LINKOPENNEWWIN', 'Open link in a new window?'))
			),
			new FieldSet(
				new FormAction('insert', _t('HtmlEditorField.BUTTONINSERTLINK', 'Insert link')),
				new FormAction('remove', _t('HtmlEditorField.BUTTONREMOVELINK', 'Remove link'))
			)
		);
		
		$form->loadDataFrom($this);
		
		return $form;
	}

	/**
	 * Return a {@link Form} instance allowing a user to
	 * add images to the TinyMCE content editor.
	 *  
	 * @return Form
	 */
	function ImageForm() {
		Requirements::javascript(THIRDPARTY_DIR . "/behaviour.js");
		Requirements::javascript(EXTERNALCONTENT . "/javascript/external_tiny_mce_improvements.js");
		Requirements::css('cms/css/TinyMCEImageEnhancement.css');
		Requirements::javascript('cms/javascript/TinyMCEImageEnhancement.js');
		Requirements::javascript(THIRDPARTY_DIR . '/SWFUpload/SWFUpload.js');
		Requirements::javascript(CMS_DIR . '/javascript/Upload.js');

		$form = new Form(
			$this->controller,
			"{$this->name}/ImageForm",
			new FieldSet(
				new LiteralField('Heading', '<h2><img src="cms/images/closeicon.gif" alt="' . _t('HtmlEditorField.CLOSE', 'close') . '" title="' . _t('HtmlEditorField.CLOSE', 'close') . '" />' . _t('HtmlEditorField.IMAGE', 'Image') . '</h2>'),
				new TreeDropdownField('FolderID', _t('HtmlEditorField.FOLDER', 'Folder'), 'Folder'),
				new LiteralField('AddFolderOrUpload',
					'<div style="clear:both;"></div><div id="AddFolderGroup" style="display: none">
						<a style="" href="#" id="AddFolder" class="link">' . _t('HtmlEditorField.CREATEFOLDER','Create Folder') . '</a>
						<input style="display: none; margin-left: 2px; width: 94px;" id="NewFolderName" class="addFolder" type="text">
						<a style="display: none;" href="#" id="FolderOk" class="link addFolder">' . _t('HtmlEditorField.OK','Ok') . '</a>
						<a style="display: none;" href="#" id="FolderCancel" class="link addFolder">' . _t('HtmlEditorField.FOLDERCANCEL','Cancel') . '</a>
					</div>
					<div id="PipeSeparator" style="display: none">|</div>
					<div id="UploadGroup" class="group" style="display: none; margin-top: 2px;">
						<a href="#" id="UploadFiles" class="link">' . _t('HtmlEditorField.UPLOAD','Upload') . '</a>
					</div>'
				),
				new TextField('getimagesSearch', _t('HtmlEditorField.SEARCHFILENAME', 'Search by file name')),
				new ThumbnailStripField('FolderImages', 'FolderID', 'getimages'),
				new TextField('AltText', _t('HtmlEditorField.IMAGEALTTEXT', 'Alternative text (alt) - shown if image cannot be displayed'), '', 80),
				new TextField('ImageTitle', _t('HtmlEditorField.IMAGETITLE', 'Title text (tooltip) - for additional information about the image')),
				new TextField('CaptionText', _t('HtmlEditorField.CAPTIONTEXT', 'Caption text')),
				new DropdownField(
					'CSSClass',
					_t('HtmlEditorField.CSSCLASS', 'Alignment / style'),
					array(
						'left' => _t('HtmlEditorField.CSSCLASSLEFT', 'On the left, with text wrapping around.'),
						'leftAlone' => _t('HtmlEditorField.CSSCLASSLEFTALONE', 'On the left, on its own.'),
						'right' => _t('HtmlEditorField.CSSCLASSRIGHT', 'On the right, with text wrapping around.'),
						'center' => _t('HtmlEditorField.CSSCLASSCENTER', 'Centered, on its own.'),
					)
				),
				new FieldGroup(_t('HtmlEditorField.IMAGEDIMENSIONS', 'Dimensions'),
					new TextField('Width', _t('HtmlEditorField.IMAGEWIDTHPX', 'Width'), 100),
					new TextField('Height', " x " . _t('HtmlEditorField.IMAGEHEIGHTPX', 'Height'), 100)
				)
			),
			new FieldSet(
				new FormAction('insertimage', _t('HtmlEditorField.BUTTONINSERTIMAGE', 'Insert image'))
			)
		);
		
		$form->disableSecurityToken();
		$form->loadDataFrom($this);
		
		return $form;
	}

	function FlashForm() {
		Requirements::javascript(THIRDPARTY_DIR . "/behaviour.js");
		Requirements::javascript(EXTERNALCONTENT . "/javascript/external_tiny_mce_improvements.js");
		Requirements::javascript(THIRDPARTY_DIR . '/SWFUpload/SWFUpload.js');
		Requirements::javascript(CMS_DIR . '/javascript/Upload.js');

		$form = new Form(
			$this->controller,
			"{$this->name}/FlashForm", 
			new FieldSet(
				new LiteralField('Heading', '<h2><img src="cms/images/closeicon.gif" alt="'._t('HtmlEditorField.CLOSE', 'close').'" title="'._t('HtmlEditorField.CLOSE', 'close').'" />'._t('HtmlEditorField.FLASH', 'Flash').'</h2>'),
				new TreeDropdownField("FolderID", _t('HtmlEditorField.FOLDER'), "Folder"),
				new TextField('getflashSearch', _t('HtmlEditorField.SEARCHFILENAME', 'Search by file name')),
				new ThumbnailStripField("Flash", "FolderID", "getflash"),
				new FieldGroup(_t('HtmlEditorField.IMAGEDIMENSIONS', "Dimensions"),
					new TextField("Width", _t('HtmlEditorField.IMAGEWIDTHPX', "Width"), 100),
					new TextField("Height", "x " . _t('HtmlEditorField.IMAGEHEIGHTPX', "Height"), 100)
				)
			),
			new FieldSet(
				new FormAction("insertflash", _t('HtmlEditorField.BUTTONINSERTFLASH', 'Insert Flash'))
			)
		);
		$form->loadDataFrom($this);
		return $form;
	}
}

?>