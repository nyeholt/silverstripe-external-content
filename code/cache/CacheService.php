<?php

/**
 * A simple cache service that uses a configurable CacheStore
 * for persisting items for a given length of time.
 *
 * Usage
 *
 * CacheService::inst()->get('mykey');
 * CacheService::inst()->store('mykey', {someobject}, 3600seconds);
 *
 *
 */
class CacheService {

	/**
	 * The cache store to use for actually putting and retrieving items from
	 *
	 * @var CacheStore
	 */
	protected $store = null;

	/**
	 * The type of store we're using for the cache
	 *
	 * @var string
	 */
	public static $store_type = 'FileBasedCacheStore';

	/**
	 * Cache for 1 hour by default
	 *
	 * @var int
	 */
	private $expiry = 3600;
	private $items = array();
	private static $instance;

	/**
	 * Get the instance
	 * @return CacheService
	 */
	public static function inst() {
		if (!self::$instance) {
			self::$instance = new CacheService();
		}
		return self::$instance;
	}

	protected function __construct() {
		$store = self::$store_type;
		$this->store = new $store;
	}

	public function configure($config) {
		$this->expiry = isset($config['expiry']) ? $config['expiry'] : $this->expiry;
	}

	/**
	 * Cache an item
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param int $expiry
	 * 			How many seconds to cache this object for (no value uses the configured default)
	 */
	public function store($key, $value, $expiry=0) {
		if ($expiry == 0) {
			$expiry = $this->expiry;
		}
		$entry = new CacheItem();

		$entry->value = serialize($value);
		$entry->expireAt = time() + $expiry;
		$data = serialize($entry);

		$this->store->store($key, $data);

		$this->items[$key] = $entry;
	}

	/**
	 * Gets a cached value for a given key
	 * @param String $key
	 * 			The key to retrieve data for
	 */
	public function get($key) {
		$entry = null;

		if (isset($this->items[$key])) {
			$entry = $this->items[$key];
		} else {
			$data = $this->store->get($key);
			if ($data) {
				$entry = unserialize($data);
			}
		}

		if (!$entry) {
			return $entry;
		}


		// if the expire time is in the future
		if ($entry->expireAt > time()) {
			return unserialize($entry->value);
		}


		// if we got to here, we need to expire the value
		$this->expire($key);
		return null;
	}

	/**
	 * Explicitly expire the given key
	 *
	 * @param $key
	 */
	public function expire($key) {
		unset($this->items[$key]);
		$this->store->delete($key);
	}

}

/**
 * A cache store definition
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 *
 */
interface CacheStore {

	/**
	 * Saves data with the given key at the particular value
	 *
	 * @param String $key
	 * 			The key for the data to be stored
	 * @param String $value
	 * 			The data being stored
	 */
	public function store($key, $value);

	/**
	 * Retrieve content from the cache.
	 *
	 * Returns null in case of missing data
	 *
	 * @param String $key
	 * 			The key for the data
	 *
	 * @return CacheItem
	 */
	public function get($key);

	/**
	 * Delete a given key from the cache
	 *
	 * @param String $key
	 */
	public function delete($key);
}

/**
 * A cache store that uses the filesystem for storing cached content
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 *
 */
class FileBasedCacheStore implements CacheStore {

	public static $cache_location = null;

	public function store($key, $data) {
		$location = $this->getDiskLocation($key);
		file_put_contents($location, $data);
	}

	public function get($key) {
		$data = null;
		$location = $this->getDiskLocation($key);
		if (file_exists($location)) {
			$data = file_get_contents($location);
		}
		return $data;
	}

	public function delete($key) {
		$location = $this->getDiskLocation($key);
		if (file_exists($location)) {
			unlink($location);
		}
	}

	private function getDiskLocation($key) {
		$name = md5($key);
		if (!self::$cache_location) {
			$cacheLocation = TEMP_FOLDER . '/cache_store';
		} else {
			$cacheLocation = self::$cache_location;
		}
		$dir = $cacheLocation . '/' . mb_substr($name, 0, 5);
		if (!is_dir($cacheLocation)) {
			mkdir($cacheLocation, 0777, true);
		}
		if (!is_dir($dir)) {
			mkdir($dir, 0777, true);
		}
		return $dir . '/' . $name;
	}

}

/**
 * Basic wrapper around items that need to be stored in the cache
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 *
 */
class CacheItem {

	public $value;
	public $expireAt;

}
