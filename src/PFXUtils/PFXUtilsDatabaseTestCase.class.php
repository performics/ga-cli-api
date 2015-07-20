<?php
class PFXUtilsDatabaseTestCase extends TestHelpers\DatabaseTestCase {
    /**
     * Tests PFXUtils::lookupEntityID().
     */
    public function testLookupEntityID() {
        self::$_dbConn->exec(<<<EOF
CREATE TABLE pfxutils_test_autoincrement (
  `id` int unsigned AUTO_INCREMENT PRIMARY KEY,
  `data` varchar(8),
  `data_2` varchar(8) CHARACTER SET utf8 COLLATE utf8_bin,
  KEY `data_index` (`data`),
  KEY `data_2_index` (`data_2`)
)
EOF
);
        $expectedID = 0;
        $id = PFXUtils::lookupEntityID(
            self::$_dbConn,
            'foo',
            'data',
            'pfxutils_test_autoincrement'
        );
        $this->assertSame(1, $id);
        /* Running this again should return the same ID and not alter the size
        of the table. */
        $id = PFXUtils::lookupEntityID(
            self::$_dbConn,
            'foo',
            'data',
            'pfxutils_test_autoincrement'
        );
        $this->assertSame(++$expectedID, $id);
        $this->assertEquals($expectedID, self::$_dbConn->query(
            'SELECT COUNT(*) FROM pfxutils_test_autoincrement'
        )->fetchColumn());
        /* Make sure the column we expect to be populated is populated, and the
        other one is empty. */
        $this->assertEquals(
            array('data' => 'foo', 'data_2' => null),
            self::$_dbConn->query(
                'SELECT data, data_2 FROM pfxutils_test_autoincrement WHERE id = ' . $id
            )->fetch(PDO::FETCH_ASSOC)
        );
        // We can populate a different column if we want
        $id = PFXUtils::lookupEntityID(
            self::$_dbConn,
            'foo',
            'data_2',
            'pfxutils_test_autoincrement'
        );
        $this->assertSame(++$expectedID, $id);
        $this->assertEquals(
            array('data' => null, 'data_2' => 'foo'),
            self::$_dbConn->query(
                'SELECT data, data_2 FROM pfxutils_test_autoincrement WHERE id = ' . $id
            )->fetch(PDO::FETCH_ASSOC)
        );
        /* We can prevent the insertion that would normally take place from
        happening. */
        $this->assertNull(PFXUtils::lookupEntityID(
            self::$_dbConn,
            'bar',
            'data',
            'pfxutils_test_autoincrement',
            TEST_DB_DBNAME,
            'id',
            true
        ));
        $this->assertEquals($expectedID, self::$_dbConn->query(
            'SELECT COUNT(*) FROM pfxutils_test_autoincrement'
        )->fetchColumn());
        /* Make sure the right thing happens when we pass data that's longer
        than what the column can hold. */
        $data = 'abcdefghi';
        $id = PFXUtils::lookupEntityID(
            self::$_dbConn,
            $data,
            'data',
            'pfxutils_test_autoincrement'
        );
        $this->assertSame(++$expectedID, $id);
        /* The method should account for the truncation before doing the lookup
        so that we get the same ID back the second time. */
        $this->assertSame($expectedID, PFXUtils::lookupEntityID(
            self::$_dbConn,
            $data,
            'data',
            'pfxutils_test_autoincrement'
        ));
        $this->assertEquals(substr($data, 0, 8), self::$_dbConn->query(
            'SELECT data FROM pfxutils_test_autoincrement WHERE id = ' . $id
        )->fetchColumn());
        /* What if the byte that would normally be truncated is in the
        middle of a multibyte character? */
        if (extension_loaded('mbstring')) {
            // Make sure the internal encoding matches this file
            mb_internal_encoding('UTF-8');
            $mbstring = true;
        }
        else {
            $mbstring = false;
        }
        $data = 'abcdefg公';
        $id = PFXUtils::lookupEntityID(
            self::$_dbConn,
            $data,
            'data',
            'pfxutils_test_autoincrement'
        );
        $this->assertSame(++$expectedID, $id);
        if ($mbstring) {
            /* Without mbstring, this assertion will fail, because the string
            will be internally truncated to 8 characters, leaving a mystery
            byte that causes the lookup to fail and thus causes an insert of a
            new row. */
            $this->assertSame($expectedID, PFXUtils::lookupEntityID(
                self::$_dbConn,
                $data,
                'data',
                'pfxutils_test_autoincrement'
            ));
            $expectedLength = 7;
        }
        else {
            $expectedLength = 8;
        }
        $this->assertEquals(
            substr($data, 0, $expectedLength),
            self::$_dbConn->query(
                'SELECT data FROM pfxutils_test_autoincrement WHERE id = ' . $id
            )->fetchColumn()
        );
        /* MySQL will use case-insensitive comparison and discard trailing
        whitespace when looking up a value in a column that doesn't use binary
        collation. */
        if (substr(TEST_DB_DSN, 0, 6) == 'mysql:') {
            $this->assertSame(1, PFXUtils::lookupEntityID(
                self::$_dbConn,
                'Foo',
                'data',
                'pfxutils_test_autoincrement'
            ));
            $this->assertSame(1, PFXUtils::lookupEntityID(
                self::$_dbConn,
                'Foo  ',
                'data',
                'pfxutils_test_autoincrement'
            ));
            $this->assertSame(++$expectedID, PFXUtils::lookupEntityID(
                self::$_dbConn,
                'Foo',
                'data_2',
                'pfxutils_test_autoincrement'
            ));
            $this->assertSame(++$expectedID, PFXUtils::lookupEntityID(
                self::$_dbConn,
                'Foo  ',
                'data_2',
                'pfxutils_test_autoincrement'
            ));
        }
        self::$_dbConn->exec('DROP TABLE pfxutils_test_autoincrement');
    }
    
    /**
     * Tests PFXUtils::getDBConn().
     */
    public function testGetDBConn() {
        $db = PFXUtils::getDBConn(TEST_DB_DSN, TEST_DB_USER, TEST_DB_PASSWORD);
        $this->assertInstanceOf('PDO', $db);
        // The DB connection should throw exceptions on errors
        $this->assertThrows(
            'PDOException',
            array($db, 'query'),
            array('SELECT foo FROM bar')
        );
        /* Subsequent calls with the same credentials and attributes should
        return the same connection instance. */
        $this->assertSame($db, PFXUtils::getDBConn(
            TEST_DB_DSN, TEST_DB_USER, TEST_DB_PASSWORD
        ));
        $db2 = PFXUtils::getDBConn(
            TEST_DB_DSN,
            TEST_DB_USER,
            TEST_DB_PASSWORD,
            array(PDO::ATTR_CASE => PDO::CASE_LOWER)
        );
        $this->assertNotSame($db, $db2);
        $this->assertSame($db2, PFXUtils::getDBConn(
            TEST_DB_DSN,
            TEST_DB_USER,
            TEST_DB_PASSWORD,
            array(PDO::ATTR_CASE => PDO::CASE_LOWER)
        ));
    }
    
    /**
     * Tests PFXUtils::beginTransactionSafe().
     */
    public function testBeginTransactionSafe() {
        // Our database connection shouldn't be in a transaction yet
        $this->assertTrue(PFXUtils::beginTransactionSafe(self::$_dbConn));
        // And now it is, so it shouldn't begin another one
        $this->assertFalse(PFXUtils::beginTransactionSafe(self::$_dbConn));
        self::$_dbConn->commit();
        $this->assertTrue(PFXUtils::beginTransactionSafe(self::$_dbConn));
        self::$_dbConn->commit();
    }
}
?>
