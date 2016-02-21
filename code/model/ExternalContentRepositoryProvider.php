<?php

/**
 * Indicates that the given object provides access to a remote repository
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD License http://silverstripe.org/bsd-license
 *
 */
interface ExternalContentRepositoryProvider {

	/**
	 * Retrieve a reference to the object that communicates with
	 * the remote repository.
	 *
	 * @return mixed
	 */
	public function getRemoteRepository();

	/**
	 * Get a reference to a remote object based on its ID. The $id
	 * variable refers to the ID as stored in the remote system; any translation
	 * needed should be performed by this method's implementation (for example,
	 * the IDs of some content items may have characters that are invalid
	 * in in SilverStripe; check the AlfrescoContentSource for an example of
	 * how this can be handled.
	 *
	 * @param String $id
	 * 			The ID in the remote system
	 * @return ExternalContentItem
	 */
	public function getObject($id);
}
