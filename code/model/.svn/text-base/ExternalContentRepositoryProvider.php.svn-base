<?php
/**

Copyright (c) 2009, SilverStripe Australia PTY LTD - www.silverstripe.com.au
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
 
/**
 * Indicates that the given object provides access to a remote repository
 * 
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 *
 */
interface ExternalContentRepositoryProvider
{
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


?>