<?php
class SharedMemoryException extends Exception {}
class SharedMemory {
	private static $_INTERNAL_VARIABLES = array('_proccount', '_varcount');
	private static $_UNIT_SEPARATOR;
	private static $_RECORD_SEPARATOR;
	private static $_useSysV;
	private static $_registeredVars = array();
	private static $_registeredVarLookup = array();
	private static $_shmFileBase;
	private static $_activeShmResources;
	private $_segmentKey;
	private $_mutex;
	private $_shmResource;
	// Remaining properties only used in non-SysV context
	private $_shmFile;
	private $_shmData = array();
	/* I have found that it is possible, when doing shared memory cleanup in
	the destructors of objects that use SharedMemory instances, to run into a
	situation where the SharedMemory instance's destructor has run already at
	the point that the other object tries to ask it for data (see
	http://stackoverflow.com/questions/14096588/in-which-order-are-objects-destructed-in-php
	for corroboration that this can happen). This class' destructor sets this
	property to true so that callers can interrogate the instance about whether
	it's still up and running. */
	private $_destroyed = false;
	
	/**
	 * Determines information about the run environment and creates the shared
	 * memory segment (an actual SysV shared memory segment on Unix, otherwise
	 * a file).
	 *
	 * @param Mutex $mutex
	 * @param int $segSize
	 */
	public function __construct(Mutex $mutex, $segSize = null) {
		$this->_mutex = $mutex;
		if (self::$_useSysV === null) {
			self::$_useSysV = $this->_mutex->hasSysV();
			if (!self::$_useSysV) {
				self::$_shmFileBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR
				                    . 'shm.{SEGMENT_KEY}.tmp';
				self::$_UNIT_SEPARATOR = chr(31);
				self::$_RECORD_SEPARATOR = chr(30);
			}
			self::$_activeShmResources = new SplQueue();
		}
		// We share the segment key with the mutex that governs access to it
		$this->_segmentKey = $this->_mutex->getLockID();
		if (!$segSize) {
			// Default to 10000 bytes
			$segSize = 10000;
		}
		else {
			/* Otherwise, allocate the extra space we'll need to store the
			attached process count and variable count. Assume a three-digit
			number of each (should be way more than enough). */
			$segSize += self::getRequiredBytes(array('100000'));
		}
		if (self::$_useSysV) {
			$this->_shmResource = shm_attach($this->_segmentKey, $segSize);
		}
		else {
			$this->_shmFile = str_replace(
				'{SEGMENT_KEY}', $this->_segmentKey, self::$_shmFileBase
			);
			$this->_shmResource = fopen($this->_shmFile, 'a+b');
		}
		if (!$this->_shmResource) {
			throw new SharedMemoryException(
				'Failed to obtain shared memory resource.'
			);
		}
		/* Add the resource to the queue before the file name (if any); that
		way if we need to clean up, we can iterate through the queue and know
		that we will have closed any open file handles before deleting the
		corresponding file. */
		self::$_activeShmResources->enqueue($this->_shmResource);
		if ($this->_shmFile) {
			self::$_activeShmResources->enqueue($this->_shmFile);
		}
		/* Keep track of the number of processes (or rather, the number of
		SharedMemory instances across all processes) attached to this memory
		segment so we can remove it when they all disappear. */
		/* This is somewhat of a misnomer; we're not counting processes, we are
		counting instances across all processes. */
		$this->addToVar('_proccount', 1);
		if (!$this->hasVar('_varcount')) {
			$this->putVar('_varcount', 0);
		}
	}
	
	/**
	 * Decrement the attached process count. If this is the last process
	 * attached to the shared memory segment, and its variables have all been
	 * removed, destroy it.
	 */
	public function __destruct() {
		$useLocalMutex = !$this->_mutex->isAcquired();
		if ($useLocalMutex) {
			$this->_mutex->acquire();
		}
		$processes = $this->getVar('_proccount');
		/* For whatever reason, I'm seeing _varcount end up as a negative value
		in some circumstances. */
		if ((int)$processes === 1 && $this->getVar('_varcount') < 1) {
			if (self::$_useSysV) {
				shm_remove($this->_shmResource);
			}
			else {
				fclose($this->_shmResource);
				unlink($this->_shmFile);
			}
		}
		else {
			$this->addToVar('_proccount', -1);
		}
		$this->_destroyed = true;
		if ($useLocalMutex) {
			$this->_mutex->release();
		}
	}
	
	/**
	 * Register a variable name. This uses a CRC32 hash to derive a key that is
	 * usable within SysV functions, which means that the possibility of a
	 * collision is pretty high. However, it should be good enough these
	 * purposes, as long as we don't start making extremely wide use of shared
	 * memory.
	 *
	 * @param string $varName
	 */
	private function _registerVarName($varName) {
		try {
			if (array_key_exists($varName, self::$_registeredVarLookup)) {
				return;
			}
			$useLocalMutex = !$this->_mutex->isAcquired();
			if ($useLocalMutex) {
				$this->_mutex->acquire();
			}
			$varHash = crc32($varName);
			if (array_key_exists($varHash, self::$_registeredVars)) {
				if ($useLocalMutex) {
					$this->_mutex->release();
				}
				throw new SharedMemoryException(
					'Got hash collision when registering ' . $varName . '.'
				);
			}
			self::$_registeredVars[$varHash] = $varName;
			self::$_registeredVarLookup[$varName] = $varHash;
			if ($useLocalMutex) {
				$this->_mutex->release();
			}
		} catch (MutexException $e) {
			throw new SharedMemoryException(
				'Caught error while registering variable name.', null, $e
			);
		}
	}
	
	/**
	 * Reads data out of the shared memory file and indexes it $this->_shmData.
	 * This is only necessary on environments that don't support SysV. It is
	 * very far from being high-performing, but the non-SysV branches in these
	 * methods are basically only here to let me run things in a test
	 * environment, so it doesn't matter.
	 */
	private function _readSharedData() {
		if (self::$_useSysV) {
			return;
		}
		if (!is_resource($this->_shmResource)) {
			trigger_error(
				'Attempted to read shared memory data from a closed file',
				E_USER_WARNING
			);
			return;
		}
		$this->_shmData = array();
		rewind($this->_shmResource);
		$bytes = '';
		while (!feof($this->_shmResource)) {
			$bytes .= fread($this->_shmResource, 1024);
		}
		$records = explode(self::$_RECORD_SEPARATOR, $bytes);
		foreach ($records as $record) {
			if ($record) {
				$data = explode(self::$_UNIT_SEPARATOR, $record);
				$this->_shmData[$data[0]] = unserialize($data[1]);
			}
		}
	}
	
	/**
	 * Stores the contents of $this->_shmData into the file handle in
	 * $this->_shmResource. This is the complement to $this->_readSharedData().
	 * Returns false if there were any failures while writing the data.
	 *
	 * @return boolean
	 */
	private function _writeSharedData() {
		if (self::$_useSysV) {
			return;
		}
		rewind($this->_shmResource);
		ftruncate($this->_shmResource, 0);
		$rVal = true;
		foreach ($this->_shmData as $key => $val) {
			$res = fwrite(
				$this->_shmResource,
				$key . self::$_UNIT_SEPARATOR . serialize($val) .
					self::$_RECORD_SEPARATOR
			);
			if (!$res) {
				$rVal = false;
			}
		}
		return $rVal;
	}
	
	/**
	 * Calculates the minimum number of bytes necessary for the storage of the
	 * values passed in the argument array.
	 *
	 * @param array $vals
	 * @return int
	 */
	public static function getRequiredBytes(array $vals) {
		/* According to a user comment on 
		http://us2.php.net/manual/en/function.shm-attach.php, the amount of
		bytes needed for shared memory is equal to 24 bytes per variable, plus
		the serialized variable size, aligned by four bytes, plus sixteen extra
		bytes (I assume that means total, not per variable). The commenter
		claims that updates of existing variables require that each variable's
		allocated size be doubled; that didn't seem to be true on our earlier
		Ubuntu 11.10 server, but on our current 12.04 instance, it appears we
		do have to apply some padding (because of the fact that the old one was
		32 bits and this one is 64, I'm sure). I think 50% extra should do the
		trick. */
		$bytes = 16;
		foreach ($vals as $val) {
			$sSize = strlen(serialize($val));
			$bytes += 24 + $sSize + 4 - $sSize % 4;
		}
		return round($bytes * 1.5);
	}
	
	/**
	 * Destroys any shared memory resources created during the lifetime of this
	 * process, without regard to whether they contain any data. This method
	 * should never be called in typical code but is useful for cleanup in
	 * some testing situations where we have shared memory resources keyed on
	 * the names of dynamically-generated classes.
	 */
	public static function destroyAll() {
		if (self::$_activeShmResources) {
			while (!self::$_activeShmResources->isEmpty()) {
				$resource = self::$_activeShmResources->dequeue();
				if (is_resource($resource)) {
					if (self::$_useSysV) {
						shm_remove($resource);
					}
					else {
						fclose($resource);
					}
				}
				else {
					unlink($resource);
				}
			}
		}
	}
	
	/**
	 * Checks whether a variable exists in shared memory.
	 *
	 * @param string $varName
	 * @return boolean
	 */
	public function hasVar($varName) {
		$this->_registerVarName($varName);
		$useLocalMutex = !$this->_mutex->isAcquired();
		if ($useLocalMutex) {
			$this->_mutex->acquire();
		}
		$varKey = self::$_registeredVarLookup[$varName];
		if (self::$_useSysV) {
			$res = shm_has_var($this->_shmResource, $varKey);
		}
		else {
			$this->_readSharedData();
			$res = array_key_exists($varKey, $this->_shmData);
		}
		if ($useLocalMutex) {
			$this->_mutex->release();
		}
		return $res;
	}
	
	/**
	 * Adds a variable to shared memory.
	 *
	 * @param string $varName
	 * @param mixed $varValue
	 */
	public function putVar($varName, $varValue) {
		if (is_null($varValue)) {
			throw new SharedMemoryException(
				'Storage of null value in shared memory not permitted.'
			);
		}
		$this->_registerVarName($varName);
		$useLocalMutex = !$this->_mutex->isAcquired();
		if ($useLocalMutex) {
			$this->_mutex->acquire();
		}
		$varKey = self::$_registeredVarLookup[$varName];
		$isNew = $this->hasVar($varName);
		if (self::$_useSysV) {
			$res = shm_put_var($this->_shmResource, $varKey, $varValue);
		}
		else {
			$this->_readSharedData();
			$this->_shmData[$varKey] = $varValue;
			$res = $this->_writeSharedData();
		}
		if ($res && !$isNew && !in_array($varName, self::$_INTERNAL_VARIABLES))
		{
			$this->addToVar('_varcount', 1);
		}
		if ($useLocalMutex) {
			$this->_mutex->release();
		}
		if (!$res) {
			throw new SharedMemoryException(
				'Failed to store value "' . $varValue . '" in shared memory ' .
				'for variable name "' . $varName . '".'
			);
		}
	}
	
	/**
	 * Retrieves the value of a variable from shared memory. If that variable
	 * is not in shared memory, returns the value of the second argument
	 * instead.
	 *
	 * @param string $varName
	 * @param mixed $default = null
	 * @return mixed
	 */
	public function getVar($varName, $default = null) {
		// The $this->hasVar() call takes care of the variable registration
		if (!$this->hasVar($varName)) {
			return $default;
		}
		$useLocalMutex = !$this->_mutex->isAcquired();
		if ($useLocalMutex) {
			$this->_mutex->acquire();
		}
		$varKey = self::$_registeredVarLookup[$varName];
		if (self::$_useSysV) {
			$val = shm_get_var($this->_shmResource, $varKey);
		}
		else {
			$this->_readSharedData();
			$val = $this->_shmData[$varKey];
		}
		if ($useLocalMutex) {
			$this->_mutex->release();
		}
		return $val;
	}
	
	/**
	 * Removes a variable from shared memory.
	 *
	 * @param string $varName
	 */
	public function removeVar($varName) {
		$this->_registerVarName($varName);
		$useLocalMutex = !$this->_mutex->isAcquired();
		if ($useLocalMutex) {
			$this->_mutex->acquire();
		}
		$varKey = self::$_registeredVarLookup[$varName];
		if (self::$_useSysV) {
			$res = shm_remove_var($this->_shmResource, $varKey);
		}
		else {
			$this->_readSharedData();
			unset($this->_shmData[$varKey]);
			$res = $this->_writeSharedData();
		}
		$this->addToVar('_varcount', -1);
		if ($useLocalMutex) {
			$this->_mutex->release();
		}
		if (!$res) {
			throw new SharedMemoryException(
				'Got error when attempting to remove shared variable "' .
				$varName . '".'
			);
		}
	}
	
	/**
	 * Performs in-place addition on the variable in stored memory. This avoids
	 * the need to manage mutual exclusion externally. If the variable has not
	 * yet been initialized, it will be initialized as 0 prior to performing
	 * the addition. If the variable has been initialized and its value is not
	 * a number, a SharedMemoryException will be thrown.
	 *
	 * @param string $varName
	 * @param int $operand
	 */
	public function addToVar($varName, $operand) {
		if (!is_numeric($operand)) {
			throw new SharedMemoryException(
				'Both operands must be numeric in order to perform arithmetic.'
			);
		}
		$useLocalMutex = !$this->_mutex->isAcquired();
		if ($useLocalMutex) {
			$this->_mutex->acquire();
		}
		try {
			$val = $this->getVar($varName, 0);
			if (!is_numeric($val)) {
				throw new SharedMemoryException(
					'Both operands must be numeric in order to perform arithmetic.'
				);
			}
			$this->putVar($varName, $val + $operand);
		} catch (SharedMemoryException $e) {
			if ($useLocalMutex) {
				$this->_mutex->release();
			}
			throw $e;
		}
		if ($useLocalMutex) {
			$this->_mutex->release();
		}
	}
	
	/**
	 * @return int
	 */
	public function getSegmentKey() {
		return $this->_segmentKey;
	}
	
	/**
	 * @return boolean
	 */
	public function isDestroyed() {
		return $this->_destroyed;
	}
}
?>