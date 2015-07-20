<?php
class MutexException extends Exception {}
class Mutex {
	const LOCK_METHOD_AUTO = 1;
	const LOCK_METHOD_MANUAL = 2;
	private static $_useSysV;
	/* Keep track of which lock IDs we got by doing a checksum on the class
	name versus the ones were passed directly so that we can alert about
	possible conflicts. */
	private static $_REGISTERED_KEY_LOCK_METHODS = array();
	private static $_lockFileBase;
	private static $_activeResources;
	private $_lockResource;
	private $_lockID;
	private $_acquired = false;
	
	/**
	 * Determine the nature of the environment and how we will perform the
	 * mutex.
	 *
	 * @param mixed $lockParam
	 */
	public function __construct($lockParam) {
		if (self::$_useSysV === null) {
			$osType = strtolower(substr(PHP_OS, 0, 3));
			// Cygwin has the SysV functions but they don't actually work
			if (!function_exists('sem_acquire') || $osType == 'cyg') {
				self::$_useSysV = false;
				self::$_lockFileBase = sys_get_temp_dir() .
					DIRECTORY_SEPARATOR . 'mutex.{LOCK_ID}.tmp';
			}
			else {
				self::$_useSysV = true;
			}
			self::$_activeResources = new SplQueue();
		}
		// If we were passed an object instance, get its class name
		if (is_object($lockParam)) {
			$lockParam = get_class($lockParam);
		}
		if (filter_var($lockParam, FILTER_VALIDATE_INT) !== false) {
			$lockMethod = self::LOCK_METHOD_MANUAL;
			$lockID = (int)$lockParam;
		}
		else {
			$lockMethod = self::LOCK_METHOD_AUTO;
			$lockID = crc32($lockParam);
		}
		if (isset(self::$_REGISTERED_KEY_LOCK_METHODS[$lockID]) &&
		    self::$_REGISTERED_KEY_LOCK_METHODS[$lockID] != $lockMethod)
		{
			throw new MutexException(
				'The mutex lock parameter "' . $lockParam . '" conflicts ' .
				'with an existing mutex.'
			);
		}
		self::$_REGISTERED_KEY_LOCK_METHODS[$lockID] = $lockMethod;
		$this->_lockID = $lockID;
		if (self::$_useSysV) {
			$this->_lockResource = sem_get($this->_lockID, 1, 0666, 1);
		}
		else {
			$lockFile = str_replace(
				'{LOCK_ID}', $this->_lockID, self::$_lockFileBase
			);
			$this->_lockResource = fopen($lockFile, 'a+b');
			// I'm not supporting the cleanup operation for real sempahores
			self::$_activeResources->enqueue($this->_lockResource);
			self::$_activeResources->enqueue($lockFile);
		}
		if (!$this->_lockResource) {
			throw new MutexException(
				'Failed to obtain lock resource for mutex.'
			);
		}
	}
	
	public function __destruct() {
		if ($this->_acquired) {
			$this->release();
		}
	}
	
	/**
	 * Destroys any Mutex files created during the lifetime of this process
	 * has no effect if the current environment is configured to use SysV
	 * semaphores). This should never be called in normal code and is strictly
	 * for cleanup in test environments.
	 */
	public static function destroyAll() {
		if (self::$_activeResources && !self::$_useSysV) {
			while (!self::$_activeResources->isEmpty()) {
				$resource = self::$_activeResources->dequeue();
				if (is_resource($resource)) {
					fclose($resource);
				}
				else {
					unlink($resource);
				}
			}
		}
	}
	
	/**
	 * Acquire a lock.
	 */
	public function acquire() {
		if (self::$_useSysV) {
			$result = sem_acquire($this->_lockResource);
		}
		else {
			$result = flock($this->_lockResource, LOCK_EX);
		}
		if (!$result) {
			throw new MutexException('Unable to acquire lock.');
		}
		$this->_acquired = true;
	}
	
	/**
	 * Release a lock.
	 */
	public function release() {
		if (self::$_useSysV) {
			$result = sem_release($this->_lockResource);
		}
		else {
			$result = flock($this->_lockResource, LOCK_UN);
		}
		if (!$result) {
			throw new MutexException('Unable to release lock.');
		}
		$this->_acquired = false;
	}
	
	public static function hasSysV() {
		return self::$_useSysV;
	}
	
	/**
	 * Indicates whether this instance has had a lock acquired. Note that it
	 * does NOT report whether another process has locked the same underlying
	 * resource; this is not possible with PHP.
	 *
	 * @return boolean
	 */
	public function isAcquired() {
		return $this->_acquired;
	}

	public function getLockID() {
		return $this->_lockID;
	}
}
?>
