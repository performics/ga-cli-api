<?php
namespace OAuth;

class Service implements IService {
	private static $_DB_STATEMENTS = array();
	protected static $_SETTINGS = array(
		'OAUTH_DB_DSN' => null,
		'OAUTH_DB_USER' => null,
		'OAUTH_DB_PASSWORD' => null,
		'PFX_CA_BUNDLE' => null
	);
	protected static $_SETTING_TESTS = array(
		'OAUTH_DB_DSN' => '?string',
		'OAUTH_DB_USER' => '?string',
		'OAUTH_DB_PASSWORD' => '?string',
		'PFX_CA_BUNDLE' => '?file'
	);
	private static $_staticPropsReady = false;
	private static $_dbConn;
	private $_authorizer;
	private $_token;
	private $_refreshToken;
	private $_expirationTime;
	private $_tokenType;
	private $_tokenScope;
	
	/**
	 * Instantiates a new object with the specified authorizer. The
	 * authorizer's job is to make the necessary requests to obtain an OAuth
	 * token; this class' job is to store that token in the database, and
	 * hopefully fetch it for you from the database the next time you need it
	 * so you don't have to actually connect to the OAuth server. This class'
	 * getToken() method handles the refreshing of tokens when they expire in a
	 * transparent manner.
	 *
	 * @param OAuth\AbstractAuthorizationModule $authorizer
	 */
	public function __construct(AbstractAuthorizationModule $authorizer) {
		try {
			if (!self::$_staticPropsReady) {
				self::_initStaticProperties();
			}
			$this->_authorizer = $authorizer;
			if (self::$_dbConn) { 
				// Look up any cached token that may exist
				$this->_getCachedToken();
			}
		} catch (\Exception $e) {
			if ($e instanceof Exception) {
				throw $e;
			}
			throw new RuntimeException(
				'Caught error during initialization.', null, $e
			);
		}
	}
	
	/**
	 * Dispatches to methods that handle various requirements of a first
	 * instantiation.
	 */
	protected static function _initStaticProperties() {
		self::_validateSettings();
		// The database functionality is optional
		if (OAUTH_DB_DSN) {
			self::$_dbConn = \PFXUtils::getDBConn(
				OAUTH_DB_DSN, OAUTH_DB_USER, OAUTH_DB_PASSWORD
			);
			self::_initDBStatements();
			self::_destroyExpiredTokens();
		}
		self::$_staticPropsReady = true;
	}
	
	/**
	 * Ensures required settings are set and valid.
	 */
	private static function _validateSettings() {
		try {
			\PFXUtils::validateSettings(
				self::$_SETTINGS, self::$_SETTING_TESTS
			);
		} catch (\UnexpectedValueException $e) {
			throw new UnexpectedValueException(
				'Caught error while validating settings.', null, $e
			);
		}
	}
	
	/**
	 * Initializes reusable database statements.
	 */
	private static function _initDBStatements() {
		$q = <<<EOF
SELECT *
FROM oauth_effective_tokens
WHERE hash = :hash
EOF;
		self::$_DB_STATEMENTS['select'] = self::$_dbConn->prepare($q);
		$q = <<<EOF
INSERT INTO oauth_effective_tokens
(hash, token, refresh_token, expiration_time, token_type, token_scope)
VALUES
(:hash, :token, :refresh_token, :expiration_time, :token_type, :token_scope)
ON DUPLICATE KEY UPDATE
hash = VALUES(hash),
token = VALUES(token),
refresh_token = VALUES(refresh_token),
expiration_time = VALUES(expiration_time),
token_type = VALUES(token_type),
token_scope = VALUES(token_scope)
EOF;
		self::$_DB_STATEMENTS['insert'] = self::$_dbConn->prepare($q);
		$q = 'DELETE FROM oauth_effective_tokens WHERE hash = :hash';
		self::$_DB_STATEMENTS['delete'] = self::$_dbConn->prepare($q);
	}
	
	/**
	 * Removes any tokens that have passed their expiration dates.
	 */
	private static function _destroyExpiredTokens() {
		$q = <<<EOF
DELETE FROM oauth_effective_tokens
WHERE expiration_time IS NOT NULL
AND expiration_time < UNIX_TIMESTAMP()
EOF;
		self::$_dbConn->exec($q);
	}
	
	/**
	 * Retrieves a cached token from the database.
	 */
	private function _getCachedToken() {
		try {
			$stmt = self::$_DB_STATEMENTS['select'];
			$stmt->bindValue(
				':hash', $this->_authorizer->getHash(), \PDO::PARAM_STR
			);
			$stmt->execute();
			$stmt->bindColumn('token', $token, \PDO::PARAM_STR);
			$stmt->bindColumn('refresh_token', $refreshToken, \PDO::PARAM_STR);
			/* Apparently when you bind a column to PDO::PARAM_INT and it
			contains a null value, it gets cast to 0. This doesn't happen when
			binding as a string type. That's why I'm leaving the type unbound
			here and casting as an int later (which you have to do if you don't
			want it to be a string). */
			$stmt->bindColumn('expiration_time', $expirationDate);
			$stmt->bindColumn('token_type', $tokenType, \PDO::PARAM_STR);
			$stmt->bindColumn('token_scope', $tokenScope, \PDO::PARAM_STR);
			while ($stmt->fetch(\PDO::FETCH_BOUND)) {
				$this->_token = $token;
				$this->_expirationTime =
					$expirationDate === null ? null : (int)$expirationDate;
				$this->_refreshToken = $refreshToken;
				$this->_tokenType = $tokenType;
				$this->_tokenScope = $tokenScope;
			}
		} catch (\PDOException $e) {
			throw new RuntimeException(
				'Caught error while retrieving cached token.', null, $e
			);
		}
	}
	
	/**
	 * Sets this instance's token to null and deletes any record for this
	 * instance from the database.
	 */
	public function destroyToken() {
		$this->_token = null;
		$this->_expirationTime = null;
		$this->_refreshToken = null;
		$this->_tokenType = null;
		$this->_tokenScope = null;
		if (self::$_dbConn) {
			try {
				$stmt = self::$_DB_STATEMENTS['delete'];
				$stmt->bindValue(
					':hash', $this->_authorizer->getHash(), \PDO::PARAM_STR
				);
				$stmt->execute();
			} catch (\PDOException $e) {
				throw new RuntimeException(
					'Caught database error while removing cached token.',
					null,
					$e
				);
			}
		}
	}
	
	/**
	 * Caches the token from the authorizer, if there is one.
	 */
	public function cacheToken() {
		if (!self::$_dbConn) {
			throw new BadMethodCallException(
				'Cannot cache an OAuth token without the use of a database.'
			);
		}
		if ($this->_token === null) {
			throw new RuntimeException(
				'There is no OAuth token available to cache. Did the ' .
				'authorization process run?'
			);
		}
		try {
			$stmt = self::$_DB_STATEMENTS['insert'];
			$stmt->bindValue(
				':hash', $this->_authorizer->getHash(), \PDO::PARAM_STR
			);
			$stmt->bindValue(':token', $this->_token, \PDO::PARAM_STR);
			$stmt->bindValue(
				':refresh_token', $this->_refreshToken, \PDO::PARAM_STR
			);
			$stmt->bindValue(
				':expiration_time', $this->_expirationTime, \PDO::PARAM_INT
			);
			$stmt->bindValue(
				':token_type', $this->_tokenType, \PDO::PARAM_STR
			);
			$stmt->bindValue(
				':token_scope', $this->_tokenScope, \PDO::PARAM_STR
			);
			$stmt->execute();
		} catch (\PDOException $e) {
			throw new RuntimeException(
				'Caught database error while caching token.', null, $e
			);
		}
	}
	
	/**
	 * Returns the OAuth token after first obtaining it if necessary, unless
	 * the argument is false. External applications should avoid storing the
	 * return value of this method in a variable and instead just call this
	 * method whenever the token is needed, as this allows for the handling
	 * of token expiration dates to work properly.
	 *
	 * @param boolean $autoAuth = true
	 * @return string
	 */
	public function getToken($autoAuth = true) {
		if ($this->_expirationTime !== null &&
		    time() >= $this->_expirationTime)
		{
			$this->destroyToken();
		}
		if ($this->_token === null && $autoAuth) {
			$this->_authorizer->authorize();
			$this->_token = $this->_authorizer->getParser()->getToken();
			$parser = $this->_authorizer->getParser();
			$this->_expirationTime = $parser->getExpirationTime();
			$this->_refreshToken = $parser->getRefreshToken();
			$this->_tokenType = $parser->getTokenType();
			/* The authorization module may be responsible for identifying the
			scope, rather than the response parser. */
			$this->_tokenScope = $this->_authorizer->getTokenScope();
			if ($this->_tokenScope === null) {
				$this->_tokenScope = $parser->getTokenScope();
			}
			if (self::$_dbConn) {
				$this->cacheToken();
			}
		}
		return $this->_token;
	}
	
	/**
	 * @return int
	 */
	public function getExpirationTime() {
		return $this->_expirationTime;
	}
}
?>