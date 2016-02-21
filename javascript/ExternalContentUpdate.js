/**
 * Configuration for the left hand tree
 */
if(typeof SiteTreeHandlers == 'undefined') SiteTreeHandlers = {};
// SiteTreeHandlers.parentChanged_url = 'admin/assets/ajaxupdateparent';
// SiteTreeHandlers.orderChanged_url = 'admin/assets/ajaxupdatesort';
SiteTreeHandlers.controller_url = 'admin/external-content';
SiteTreeHandlers.loadPage_url = SiteTreeHandlers.controller_url + '/getitem';
SiteTreeHandlers.loadTree_url = SiteTreeHandlers.controller_url + '/getsubtree';
SiteTreeHandlers.showRecord_url = SiteTreeHandlers.controller_url + '/show/';


var _HANDLER_FORMS = {
	addpage : 'addpage_options',
	deletepage : 'Form_DeleteItemsForm',
	sortitems : 'sortitems_options'
};

/**
 * Set up save folder name action
 */
if (CMSRightForm) {
	CMSRightForm.prototype.loadURLFromServer = function(url) {
			var urlParts = url.match( /ID=(\d+)(|.*)?/ );
			var id = urlParts ? urlParts[1] : null;

			if( !url.match( /^https?:\/\/.*/ ) )
				url = document.getElementsByTagName('base')[0].href + url;

			new Ajax.Request( url + '&ajax=1', {
				asynchronous : true,
				onSuccess : function( response ) {
					$('Form_EditForm').successfullyReceivedPage(response,id);
				},
				onFailure : function(response) {
					alert(response.responseText);
					errorMessage('error loading page',response);
				}
			});
	};

	CMSRightForm.prototype.successfullyReceivedPage = function(response,pageID) {
		var loadingNode = $('sitetree').loadingNode;

		// not needed with compound IDs
		//if( loadingNode && pageID && parseInt( $('sitetree').getIdxOf( loadingNode ) ) != pageID ) {
			// return;
		// }

		// must wait until the javascript has finished
		document.body.style.cursor = 'wait';

		this.loadNewPage(response.responseText);

		var subform;
		if(subform = $('Form_MemberForm')) subform.close();
		if(subform = $('Form_SubForm')) subform.close();

		if(this.elements.ID) {
			this.notify('PageLoaded', this.elements.ID.value);
		}

		if(this.receivingID) {
			// Treenode might not exist if that part of the tree is closed
			var treeNode = loadingNode ? loadingNode : $('sitetree').getTreeNodeByIdx(this.receivingID);
			if(treeNode) {
				$('sitetree').setCurrentByIdx(treeNode.getIdx());
				treeNode.removeNodeClass('loading');
			}
			statusMessage('');
		}

		// must wait until the javascript has finished
		document.body.style.cursor = 'default';

	};

	CMSRightForm.prototype.getPageFromServer = function(id, treeNode) {
			if(id) {
				this.receivingID = id;

				// Treenode might not exist if that part of the tree is closed
				if(!treeNode) treeNode = $('sitetree').getTreeNodeByIdx(id);

				if(treeNode) {
					$('sitetree').loadingNode = treeNode;
					treeNode.addNodeClass('loading');
					url = treeNode.aTag.href + (treeNode.aTag.href.indexOf('?')==-1?'?':'&') + 'ajax=1';
				}
				if(SiteTreeHandlers.loadPage_url) {
					var sep = (SiteTreeHandlers.loadPage_url.indexOf('?') == -1) ? '?' : '&';
					url = SiteTreeHandlers.loadPage_url + sep + 'ID=' + id;
				}

				// used to set language in CMSMain->init()
				var lang = $('LangSelector') ? $F('LangSelector') : null;
				if(lang) {
				  url += '&locale='+lang;
				}

				statusMessage("loading...");
				this.loadURLFromServer(url);
			} else {
				throw("getPageFromServer: Bad page  ID: " + id);
			}
	};
}

// CMSRightForm.applyTo('#Form_EditForm', 'right');

/**
 * Add File Action
 */
addfolder = Class.create();
addfolder.applyTo('#addpage');
addfolder.prototype = {
	initialize: function () {
		Observable.applyTo($('Form_CreateProviderForm'));
		$('Form_CreateProviderForm').onsubmit = this.form_submit;
	},

	onclick : function() {
		statusMessage('Creating new provider...');
		this.form_submit();
		return false;
	},

	form_submit : function() {
		var st = $('sitetree');

		$('Form_CreateProviderForm').elements.ParentID.value = st.getIdxOf(st.firstSelected());
		Ajax.SubmitForm('Form_CreateProviderForm', null, {
			onSuccess : this.onSuccess,
			onFailure : this.showAddPageError
		});
		return false;
	},
	onSuccess: function(response) {
		Ajax.Evaluator(response);
		// Make it possible to drop files into the new folder
		DropFileItem.applyTo('#sitetree li');
	},
	showAddPageError: function(response) {
		errorMessage('Error adding provider', response);
	}
}


/**
 * Delete folder action
 */
deletefolder = {
	button_onclick : function() {
		if(treeactions.toggleSelection(this)) {
			deletefolder.o1 = $('sitetree').observeMethod('SelectionChanged', deletefolder.treeSelectionChanged);
			deletefolder.o2 = $('Form_DeleteItemsForm').observeMethod('Close', deletefolder.popupClosed);

			addClass($('sitetree'),'multiselect');

			deletefolder.selectedNodes = { };

			var sel = $('sitetree').firstSelected()
			if(sel) {
				var selIdx = $('sitetree').getIdxOf(sel);
				deletefolder.selectedNodes[selIdx] = true;
				sel.removeNodeClass('current');
				sel.addNodeClass('selected');
			}
		}
		return false;
	},

	treeSelectionChanged : function(selectedNode) {
		var idx = $('sitetree').getIdxOf(selectedNode);

		if(selectedNode.selected) {
			selectedNode.removeNodeClass('selected');
			selectedNode.selected = false;
			deletefolder.selectedNodes[idx] = false;

		} else {
			selectedNode.addNodeClass('selected');
			selectedNode.selected = true;
			deletefolder.selectedNodes[idx] = true;
		}

		return false;
	},

	popupClosed : function() {
		removeClass($('sitetree'),'multiselect');
		$('sitetree').stopObserving(deletefolder.o1);
		$('Form_DeleteItemsForm').stopObserving(deletefolder.o2);

		for(var idx in deletefolder.selectedNodes) {
			if(deletefolder.selectedNodes[idx]) {
				node = $('sitetree').getTreeNodeByIdx(idx);
				if(node) {
					node.removeNodeClass('selected');
					node.selected = false;
				}
			}
		}
	},

	form_submit : function() {
		var csvIDs = "";
		for(var idx in deletefolder.selectedNodes) {
			var selectedNode = $('sitetree').getTreeNodeByIdx(idx);
			var link = selectedNode.getElementsByTagName('a')[0];

			if(deletefolder.selectedNodes[idx] && ( !Element.hasClassName( link, 'contents' ) || confirm( "Are you sure you want to remove '" + link.firstChild.nodeValue + "'" ) ) )
				csvIDs += (csvIDs ? "," : "") + idx;
		}

		if(csvIDs) {
			$('Form_DeleteItemsForm').elements.csvIDs.value = csvIDs;

			statusMessage('deleting providers');

			Ajax.SubmitForm('Form_DeleteItemsForm', null, {
				onSuccess : deletefolder.submit_success,
				onFailure : function(response) {
					errorMessage('Error deleting pages', response);
				}
			});

		} else {
			alert("Please select at least 1 page.");
		}

		return false;
	},

	submit_success: function(response) {
		Ajax.Evaluator(response);
		treeactions.closeSelection($('deletepage'));
	}
}

Behaviour.register({
	'#Form_EditForm_Files': {
		removeFile : function(fileID) {
			var record;
			if(record = $('record-' + fileID)) {
				record.parentNode.removeChild(record);
			}
		}
	},

	'#Form_EditForm_Files a.deletelink' : {
		onclick : function(event) {
			ajaxLink(this.href);
			Event.stop(event);
			return false;
		}
	},

	'#Form_EditForm' : {
		changeDetection_fieldsToIgnore : {
			'Files[]' : true
		}
	}
});

/**
 * We don't want hitting the enter key in the name field
 * to submit the form.
 */
 Behaviour.register({
 	'#Form_EditForm_Name' : {
 		onkeypress : function(event) {
 			event = (event) ? event : window.event;
 			var kc = event.keyCode ? event.keyCode : event.charCode;
 			if(kc == 13) {
 				return false;
 			}
 		}
 	}
 });

/**
 * Initialisation function to set everything up
 */
appendLoader(function () {
	// Set up delete page
	Observable.applyTo($('Form_DeleteItemsForm'));
	if($('deletepage')) {
		$('deletepage').onclick = deletefolder.button_onclick;
		// $('deletepage').onclick = function() { Element.show('Form_DeleteItemsForm'); return false; };
		$('Form_DeleteItemsForm').onsubmit = deletefolder.form_submit;
		Element.hide('Form_DeleteItemsForm');
	}

});

function refreshAsset() {
	frames[0].location.reload(0);
	frames[1].location.reload(1);
}

