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

/**
 * Tests for the WebApiClient
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 *
 */
class WebApiClientTest extends SapphireTest
{
	/**
	 * Dummy web methods to use
	 *
	 * @var array
	 */
	static $methods = array(
		'firstMethod' => array(
			'auth' => '',
			'contentType' => 'text/plain',
			'url' => '/path/to',
			'params' => array('arg', 'another'),
			'enctype' => Zend_Http_Client::ENC_URLENCODED,
			'return' => 'raw',
			'cache' => 0
		),
		'secondMethod' => array(
			'url' => '/path/{replacement}/to',
			'params' => array('arg', 'another'),
			'return' => 'json',
			'cache' => 0
		)
	);

	const SERVICE_URL = 'http://localhost/service';

	public function testMakeRequest()
	{
		$client = $this->getMock('Zend_Http_Client', array('request'));
		$response = $this->getMock('Zend_Http_Response', array('isSuccessful', 'getBody'), array(200, array()));

		// set up the fake response
		$response->expects($this->once())
				->method('isSuccessful')
				->will($this->returnValue(true));

		$response->expects($this->once())
				->method('getBody')
				->will($this->returnValue('rawbody'));

		$client->expects($this->once())
				->method('request')
				->will($this->returnValue($response));

		// now bind it into the api client
		$api = new DummyApiClient(self::SERVICE_URL, self::$methods, null, $client);

		$return = $api->callMethod('firstMethod', array());

		// make sure that the relevant things on the client are set and that
		// our return value is as expected
		$this->assertEquals('rawbody', $return);
	}

	// Tests whether the client is recreated when we want it to be
	public function testHttpClientState()
	{
		$api = new DummyApiClient(self::SERVICE_URL, self::$methods, null);

		$client = $api->returnClient(self::SERVICE_URL);
		$other = $api->returnClient(self::SERVICE_URL);

		// they shouldn't be the same
		$this->assertFalse($client === $other);

		// set it to be a sticky client
		$api->setMaintainSession(true);

		$client = $api->returnClient(self::SERVICE_URL);
		$this->assertTrue($client === $other);
	}

	public function testClientUriReset()
	{
		$api = new DummyApiClient(self::SERVICE_URL, self::$methods, null);
		$api->setMaintainSession(true);

		$client1 = $api->returnClient('http://uri1');
		$c1uri = $client1->getUri();

		$client2 = $api->returnClient('http://uri2');
		$c2uri = $client2->getUri();

		$this->assertFalse($c1uri == $c2uri);
	}
}


class DummyApiClient extends WebApiClient
{
	protected $clientMock;

	public function __construct($url, $methods=null, $globalParams=null, $mock=null)
	{
		$this->clientMock = $mock;
		parent::__construct($url, $methods, $globalParams);
	}

	protected function getClient($uri)
	{
		if (!$this->clientMock) {
			return parent::getClient($uri);
		}
		return $this->clientMock;
	}

	public function returnClient($uri)
	{
		return $this->getClient($uri);
	}
}

?>