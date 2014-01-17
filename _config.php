<?php

// enable this for older versions  of 2.4 and disable the extension
// Object::useCustomClass('HtmlEditorField_Toolbar', 'ExternalHtmlEditorField_Toolbar');

// Disabled as it still has a problem:
// - loading javascript from the updateLinkForm call yields a blank form
//Object::add_extension('HtmlEditorField_Toolbar', 'ExternalContentHtmlEditorExtension');

set_include_path(dirname(__FILE__).'/thirdparty'.PATH_SEPARATOR.get_include_path());
