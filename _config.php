<?php

define('EXTERNALCONTENT', 'external-content');

Director::addRules(60, array(
	'extadmin' => 'ExternalContentAdmin',
	'extcon' => 'ExternalContentPage_Controller',
));

// enable this for older versions  of 2.4 and disable the extension
// Object::useCustomClass('HtmlEditorField_Toolbar', 'ExternalHtmlEditorField_Toolbar');

Object::add_extension('HtmlEditorField_Toolbar', 'ExternalContentHtmlEditorExtension');

set_include_path(dirname(__FILE__).'/thirdparty'.PATH_SEPARATOR.get_include_path());