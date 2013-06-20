<?php
/**
 * Cache behavior class.
 *
 * @copyright     Copyright 2010, Jeremy Harris
 * @link          http://42pixels.com
 * @package       cacher
 * @subpackage    cacher.models.behaviors
 */

/**
 * Cache Behavior
 *
 * Auto-caches find results into the cache. Running an exact find again will
 * pull from the cache. Requires the CacherSource datasource.
 *
 * @package       cacher
 * @subpackage    cacher.models.behaviors
 */
class CacheBehavior extends ModelBehavior {

/**
 * Whether or not to cache this call's results
 *
 * @var boolean
 */
	public $cacheResults = false;

/**
 * Settings
 *
 * @var array
 */
	public $settings;

	public $_defaults = array(
		'config'        => 'default',
		'clearOnDelete' => true,
		'clearOnSave'   => true,
		'auto'          => false,
		'gzip'          => false
	);

/**
 * Sets up a connection using passed settings
 *
 * ### Config
 * - `config` The name of an existing Cache configuration to use. Default is 'default'
 * - `clearOnSave` Whether or not to delete the cache on saves
 * - `clearOnDelete` Whether or not to delete the cache on deletes
 * - `auto` Automatically cache or look for `'cache'` in the find conditions
 *		where the key is `true` or a duration
 *
 * @param Model $Model The calling model
 * @param array $config Configuration settings
 * @see Cache::config()
 */
	public function setup(Model $Model, $config = array()) {
		$this->settings[$Model->alias] = array_merge($this->_defaults, $config);

		if (!in_array($Model->alias . '-cache', ConnectionManager::sourceList())) {
			$ds = ConnectionManager::getDataSource($Model->useDbConfig);
			$this->settings[$Model->alias] += array(
				'original' => $Model->useDbConfig,
				'datasource' => 'Cacher.CacheSource'
			);
			$this->settings[$Model->alias] = array_merge($ds->config, $this->settings[$Model->alias]);
			ConnectionManager::create($Model->alias . '-cache', $this->settings[$Model->alias]);
		} else {
			$ds = ConnectionManager::getDataSource($Model->alias . '-cache');
			$ds->config = array_merge($ds->config, $this->settings[$Model->alias]);
		}
	}

/**
 * Intercepts find to use the caching datasource instead
 *
 * If `$query['cache']` is true, it will cache based on the setup settings
 * If `$query['cache']` is a duration, it will cache using the setup settings
 * and the new duration.
 *
 * @param Model $Model The calling model
 * @param array $query The query
 */
	public function beforeFind(Model $Model, $query) {
		if (Configure::read('Cache.disable') === true) {
			return $query;
		}
		$this->cacheResults = false;
		if (isset($query['cache'])) {
			if (is_string($query['cache'])) {
				Cache::config($this->settings[$Model->alias]['config'], array('duration' => $query['cache']));
				$this->cacheResults = true;
			} else {
				$this->cacheResults = (boolean)$query['cache'];
			}
			unset($query['cache']);
		}
		$this->cacheResults = $this->cacheResults || $this->settings[$Model->alias]['auto'];

		if ($this->cacheResults) {
			$Model->setDataSource($Model->alias . '-cache');
		}
		return $query;
	}

/**
 * Intercepts delete to use the caching datasource instead
 *
 * @param Model $Model The calling model
 */
	public function beforeDelete(Model $Model, $cascade = true) {
		if ($this->settings[$Model->alias]['clearOnDelete']) {
			$this->clearCache($Model);
		}
		return true;
	}

/**
 * Intercepts save to use the caching datasource instead
 *
 * @param Model $Model The calling model
 */
	public function beforeSave(Model $Model) {
		if ($this->settings[$Model->alias]['clearOnSave']) {
			$this->clearCache($Model);
		}
		return true;
	}

/**
 * Clears all of the cache for this model's find queries.
 *
 * @param Model $Model The calling model
 * @return boolean
 */
	public function clearCache(Model $Model) {
		$ds = ConnectionManager::getDataSource($Model->alias . '-cache');
		$success = $ds->clearModelCache($Model);
		return $success;
	}
}
