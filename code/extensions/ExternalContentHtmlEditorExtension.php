<?php

/**
 * Extension to include external content options when inserting links/images etc
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class ExternalContentHtmlEditorExtension extends Extension {

	public function updateLinkForm(Form $form) {
		Requirements::javascript(ExternalContentAdmin::$directory . "/javascript/external_tiny_mce_improvements.js");
		
		$fields = $form->Fields();
		$fields->replaceField('LinkType', $options = new OptionsetField(
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
				));
		$fields->insertAfter(
			$tree = new ExternalTreeDropdownField(
				'externalcontent', 
				_t('ExternalHtmlEditorField.EXTERNAL_CONTENT', 'External Content'),
				'ExternalContentSource', 
				'Link()'
			), 
			'file'
		);

		// Explicitly set the form on new fields so the hierarchy can be traversed.
		$tree->setForm($form);
		$options->setForm($form);
	}
}
