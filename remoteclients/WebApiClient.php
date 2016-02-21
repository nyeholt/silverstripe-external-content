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
 * An object that acts as a client to a web API of some sort.
 *
 * The WebApiClient takes in a mapping of method calls of the form
 *
 *
 * auth => Whether the user needs to be authenticted
 * url => The URL to call. This may have placeholders in it (marked as {enclosed} items) which will be replaced
 * 			by any arguments in the passed in args array that match these keys.
 * enctype => What encoding to use when calling this method (defaults to Zend_Http_Client::ENC_URLENCODED)
 * contentType => Whether to use a specific content type for the request (alfresco sometimes needs
 * 			a specific content type eg application/cmisquery+xml)
 * return => The expected response; this could be a single object (eg cmisobject) or a list of objects (cmislist)
 * 			alternatively, it could be raw XML. The returned item will have an implementation object looked
 * 			up the "returnHandlers" map, to see if it needs to be handled in a particular way, in which
 * 			case handling the returned value will be passed off to it.
 * params => The names of parameters that could be passed through in the 'args' map.
 * cache => Whether this method should be cached, and how long for. Only GET requests can be cached
 *
 * Note that the "callMethod" method is separated out into several submethods to provide for override
 * points for implementing a specific API
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 *
 */
class WebApiClient
{
	public static $cache_length = 1200;

	/**
	 * The base URL to use for all the calls
	 * @var String
	 */
	protected $baseUrl;

	public function setBaseUrl($u)
	{
		$this->baseUrl = $u;
	}

	public function getBaseUrl()
	{
		return $this->baseUrl;
	}

	protected $methods;

	/**
	 * The methods to call
	 *
	 * @param array $v
	 */
	public function setMethods($v)
	{
		$this->methods = $v;
	}

	/**
	 * An array of parameters that should ALWAYS be
	 * passed through on each request.
	 *
	 * @var array
	 */
	protected $globalParams = null;

	/**
	 * Sets the global parameters
	 */
	public function setGlobalParams($v)
	{
		$this->globalParams = $v;
	}

	/**
	 * Set a single param
	 *
	 * @param string $key
	 * @param mixed $value
	 */
	public function setGlobalParam($key, $value)
	{
		$this->globalParams[$key] = $value;
	}

	protected $returnHandlers = array();

	/**
	 * Adds a new return handler to the list of handlers.
	 *
	 * Handlers must implement the 'handleReturn' method,
	 * the result of which is returned to the caller
	 *
	 * @param $name
	 * 				The name of the handler, which should match the
	 * 				'return' param of the method definition
	 * @param $object
	 * 				The object which will handle the return
	 * @return array
	 */
	public function addReturnHandler($name, ReturnHandler $object)
	{
		$this->returnHandlers[$name] = $object;
	}

	/**
	 * Whether or not to persist cookies (eg in a login situation
	 *
	 * @var boolean
	 */
	protected $useCookies = false;

	public function setUseCookies($b)
	{
		$this->useCookies = $b;
	}

	protected $maintainSession = false;

	public function setMaintainSession($b)
	{
		$this->maintainSession = true;
	}

	/**
	 * Basic HTTP Auth details
	 *
	 * @var array
	 */
	protected $authInfo;

	public function setAuthInfo($username, $password)
	{
		$this->authInfo = array(
			'user' => $username,
			'pass' => $password,
		);
	}

	/**
	 * Create a new webapi client
	 *
	 */
	public function __construct($url, $methods=null, $globalParams=null)
	{
		$this->baseUrl = $url;
		$this->methods = $methods;
		$this->globalParams = $globalParams;

		$this->addReturnHandler('xml', new XmlReturnHandler());
		$this->addReturnHandler('dom', new DomReturnHandler());
		$this->addReturnHandler('json', new JsonReturnHandler());
	}

	public function __call($method, $args) {
		$arg = is_array($args) && count($args) ? $args[0] : null;
		return $this->callMethod($method, $arg);
	}

	/**
	 * Call a method with the passed in arguments
	 *
	 * @param String $method
	 * @param array $args - a mapping of argumentName => argumentValue
	 * @param array $getParams
	 *				Specific get params to add in
	 * @param array $postParams
	 *				Specific post params to append
	 * @return mixed
	 */
	public function callMethod($method, $args)
	{
		$methodDetails = isset($this->methods[$method]) ? $this->methods[$method] : null;
		if (!$methodDetails) {
			throw new Exception("$method does not have an appropriate mapping");
		}

		$body = null;

		// use the method params to try caching the results
		// need to add in the baseUrl we're connecting to, and any global params
		// because the cache might be connecting via different users, and the
		// different users will have different sessions. This might need to be
		// tweaked to handle separate user logins at a later point in time
		$uri = $this->baseUrl . (isset($methodDetails['url']) ? $methodDetails['url'] : '');
		// 	check for any replacements that are required
		if (preg_match_all('/{(\w+)}/', $uri, $matches)) {
			foreach ($matches[1] as $match) {
				if (isset($args[$match])) {
					$uri = str_replace('{'.$match.'}', $args[$match], $uri);
				}
			}
		}

		$cacheKey = md5($uri . $method . var_export($args, true) . var_export($this->globalParams, true));

		$requestType = isset($methodDetails['method']) ? $methodDetails['method'] : 'GET';
		$cache = isset($methodDetails['cache']) ? $methodDetails['cache'] : self::$cache_length;
		if (mb_strtolower($requestType) == 'get' && $cache) {
			$body = CacheService::inst()->get($cacheKey);
		}

		if (!$body) {


			// Note that case is important! Some servers won't respond correctly
			// to get or Get requests
			$requestType = isset($methodDetails['method']) ? $methodDetails['method'] : 'GET';

			$client = $this->getClient($uri);
			$client->setMethod($requestType);

			// set the encoding type
			$client->setEncType(isset($methodDetails['enctype']) ? $methodDetails['enctype'] : Zend_Http_Client::ENC_URLENCODED);

			$paramMethod = $requestType == 'GET' ? 'setParameterGet' : 'setParameterPost';
			if ($this->globalParams) {
				foreach ($this->globalParams as $key => $value) {
					$client->$paramMethod($key, $value);
				}
			}

			if (isset($methodDetails['params'])) {
				$paramNames = $methodDetails['params'];
				foreach ($paramNames as $index => $pname) {

					if (isset($args[$pname])) {
						$client->$paramMethod($pname, $args[$pname]);
					} else if (isset($args[$index])) {
						$client->$paramMethod($pname, $args[$index]);
					}
				}
			}

			if (isset($methodDetails['get'])) {
				foreach ($methodDetails['get'] as $k => $v) {
					$client->setParameterGet($k, $v);
				}
			}

			if (isset($methodDetails['post'])) {
				foreach ($methodDetails['post'] as $k => $v) {
					$client->setParameterPost($k, $v);
				}
			}

			if (isset($methodDetails['raw']) && $methodDetails['raw']) {
				$client->setRawData($args['raw_body']);
			}

			// request away
			$response = $client->request();

			if ($response->isSuccessful()) {
				$body = $response->getBody();
				if ($cache) {
					CacheService::inst()->store($cacheKey, $body, $cache);
				}
			} else {
				if ($response->getStatus() == 500) {
					error_log("Failure: ".$response->getBody());
					error_log(var_export($client, true));
				}
				throw new FailedRequestException("Failed executing $method: ".$response->getMessage()." for request to $uri (".$client->getUri(true).')', $response->getBody());
			}
		}

		$returnType = isset($methodDetails['return']) ? $methodDetails['return'] : 'raw';

		// see what we need to do with it
		if (isset($this->returnHandlers[$returnType])) {
			$handler = $this->returnHandlers[$returnType];
			return $handler->handleReturn($body);
		} else {
			return $body;
		}
	}

	/**
	 * Call a URL directly, without it being mapped to a configured web method.
	 *
	 * This differs from the above in that the caller already knows what
	 * URL is trying to be called, so we can bypass the business of mapping
	 * arguments all over the place.
	 *
	 * We still maintain the globalParams for this client though
	 *
	 * @param $url
	 * 			The URL to call
	 * @param $args
	 * 			Parameters to be passed on the call
	 * @return mixed
	 */
	public function callUrl($url, $args = array(), $returnType = 'raw', $requestType = 'GET', $cache = 300, $enctype = Zend_Http_Client::ENC_URLENCODED)
	{
		$body = null;
		// use the method params to try caching the results
		// need to add in the baseUrl we're connecting to, and any global params
		// because the cache might be connecting via different users, and the
		// different users will have different sessions. This might need to be
		// tweaked to handle separate user logins at a later point in time
		$cacheKey = md5($url . $requestType . var_export($args, true) . var_export($this->globalParams, true));

		$requestType = isset($methodDetails['method']) ? $methodDetails['method'] : 'GET';

		if (mb_strtolower($requestType) == 'get' && $cache) {
			$body = CacheService::inst()->get($cacheKey);
		}

		if (!$body) {
			$uri = $url;
			$client = $this->getClient($uri);
			$client->setMethod($requestType);
			// set the encoding type
			$client->setEncType($enctype);
			$paramMethod = 'setParameter'.$requestType;
			// make sure to add the alfTicket parameter
			if ($this->globalParams) {
				foreach ($this->globalParams as $key => $value) {
					$client->$paramMethod($key, $value);
				}
			}

			foreach ($args as $index => $pname) {
				$client->$paramMethod($index, $pname);
			}

			// request away
			$response = $client->request();

			if ($response->isSuccessful()) {
				$body = $response->getBody();
				if ($cache) {
					CacheService::inst()->store($cacheKey, $body, $cache);
				}
			} else {
				if ($response->getStatus() == 500) {
					error_log("Failure: ".$response->getBody());
					error_log(var_export($client, true));
				}
				throw new FailedRequestException("Failed executing $url: ".$response->getMessage()." for request to $uri (".$client->getUri(true).')', $response->getBody());
			}
		}

		// see what we need to do with it
		if (isset($this->returnHandlers[$returnType])) {
			$handler = $this->returnHandlers[$returnType];
			return $handler->handleReturn($body);
		} else {
			return $body;
		}
	}

	/**
	 * The HTTP Client being used during the life of this request
	 *
	 * @var Zend_Http_Client
	 */
	protected $httpClient = null;

	/**
	 * Create and return the http client, defined in a separate method
	 * for testing purposes
	 *
	 * @return Zend_Http_Client
	 */
	protected function getClient($uri)
	{
		// TODO For some reason the Alfresco client goes into an infinite loop when returning
		// the children of an item (when you call getChildren on the company home)
		// it returns itself as its own child, unless you recreate the client. It seems
		// to maintain all the request body... or something weird.
		if (!$this->httpClient || !$this->maintainSession) {
			$this->httpClient = new Zend_Http_Client($uri, array(
	    			'maxredirects' => 0,
	    			'timeout'      => 10
				)
			);

			if ($this->useCookies) {
				$this->httpClient->setCookieJar();
			}
		} else {
			$this->httpClient->setUri($uri);
		}

		// clear it out
		if ($this->maintainSession) {
			$this->httpClient->resetParameters();
		}

		if ($this->authInfo) {
			$this->httpClient->setAuth($this->authInfo['user'], $this->authInfo['pass']);
		}

		return $this->httpClient;
	}
}

class FailedRequestException extends Exception
{
	private $response;
	public function getResponse() { return $this->response; }

	public function __construct($message, $response)
	{
		$this->response = $response;

		parent::__construct($message);
	}
}

interface ReturnHandler
{
	/**
	 * Handle the processing of a return response in a particular manner
	 *
	 * @param $rawResponse
	 * @return mixed
	 */
	public function handleReturn($rawResponse);
}

/**
 * Return a SimpleXML object for an xmlobject response type
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 *
 */
class XmlReturnHandler implements ReturnHandler
{
	public function handleReturn($rawResponse)
	{
		$rawResponse = trim($rawResponse);
		if (strpos($rawResponse, '<?xml') === 0) {
			// get rid of the xml prolog
			$rawResponse = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $rawResponse);
		}

		return new SimpleXMLElement($rawResponse);
	}
}

class DomReturnHandler implements ReturnHandler
{
	public function handleReturn($rawResponse)
	{
		$rawResponse = trim($rawResponse);


		return new DOMDocument($rawResponse);
	}
}

/**
 * Return a stdClass by json_decoding raw data
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 */
class JsonReturnHandler implements ReturnHandler
{
	public function handleReturn($rawResponse)
	{
		return json_decode($rawResponse);
	}
}

?>