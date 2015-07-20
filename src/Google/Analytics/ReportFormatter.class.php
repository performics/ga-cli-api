<?php
namespace Google\Analytics;

class ReportFormatter {
    /* This report formatter writes its output as a simple CSV table. */
    protected $_fileName;
    protected $_fileHandle;
    protected $_email;
    protected $_bytesWritten = 0;
    // This property will increment with each call to $this->writeMetadata()
    protected $_reportIndex = 0;
    protected $_reportCount;
    protected $_separator = '-----';
    
    public function __destruct() {
        if ($this->_fileHandle) {
            fclose($this->_fileHandle);
            // No need to keep the file if it's empty
            if (!$this->_bytesWritten && $this->_fileName) {
                unlink($this->_fileName);
            }
        }
    }
    
    /**
     * Writes data to the file handle. If the argument is an array, it is
     * written using fputcsv(); otherwise it is written using fwrite() and
     * terminated with PHP_EOL.
     *
     * @param array, string $data
     */
    protected function _write($data) {
        if (!$this->_fileHandle) {
            throw new LogicException(
                'Cannot write report data before a file has been set.'
            );
        }
        if (is_array($data)) {
            $this->_bytesWritten += fputcsv($this->_fileHandle, $data);
        }
        else {
            $this->_bytesWritten += fwrite($this->_fileHandle, $data . PHP_EOL);
        }
    }
    
    /**
     * Opens up a handle to the given file name and prepares to receive data.
     *
     * @param string $fileName
     */
    public function setFileName($fileName) {
        $this->_fileHandle = fopen($fileName, 'wb');
        if (!$this->_fileHandle) {
            throw new RuntimeException(
                'Unable to open ' . $fileName . ' for writing.'
            );
        }
        $this->_fileName = $fileName;
    }
    
    /**
     * Creates a temporary file in the given directory and prepares to receive
     * data.
     *
     * @param string $dir
     */
    public function openTempFile($dir) {
        $tempFile = tempnam($dir, 'ga');
        if (!$tempFile) {
            throw new RuntimeException('Unable to create temporary file.');
        }
        $this->setFileName($tempFile);
    }
    
    /**
     * Sets a new separator, which will be written between individual report
     * instances in the file.
     *
     * @param string $separator
     */
    public function setSeparator($separator) {
        $this->_separator = (string)$separator;
    }
    
    /**
     * Sets the total number of reports to be written by this instance.
     *
     * @param int $count
     */
    public function setReportCount($count) {
        if (!is_int($count)) {
            throw new InvalidArgumentException(
                'The report count must be an integer.'
            );
        }
        $this->_reportCount = $count;
    }
    
    /**
     * Writes a set of metadata to precede a report.
     *
     * @param string $description
     * @param Google\Analytics\ProfileSummary $profile
     * @param DateTime $startDate
     * @param DateTime $endDate
     */
    public function writeMetadata(
        $description,
        ProfileSummary $profile,
        \DateTime $startDate,
        \DateTime $endDate
    ) {
        if ($this->_bytesWritten) {
            $this->_write($this->_separator);
        }
        $this->_write(sprintf(
            'Report %s%s',
            ++$this->_reportIndex,
            $this->_reportCount ? (' of ' . $this->_reportCount) : ''
        ));
        $this->_write(array('Description:', $description));
        $this->_write(array('Profile:', $profile->getName()));
        $this->_write(array('Start date:', $startDate->format('Y-m-d')));
        $this->_write(array('End date:', $endDate->format('Y-m-d')));
        // This is to put an empty line between the metadata and the data
        $this->_write('');
    }
    
    /**
     * @param array $headers
     */
    public function writeHeaders(array $headers) {
        $this->_write($headers);
    }
    
    /**
     * @param array $row
     */
    public function writeRow(array $row) {
        $this->_write($row);
    }
    
    /**
     * @return string
     */
    public function getFileName() {
        return $this->_fileName;
    }
    
    /**
     * @return int
     */
    public function getBytesWritten() {
        return $this->_bytesWritten;
    }
    
    /**
     * @return string
     */
    public function getSeparator() {
        return $this->_separator;
    }
}
?>