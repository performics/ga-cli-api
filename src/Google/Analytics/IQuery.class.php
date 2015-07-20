<?php
namespace Google\Analytics;

interface IQuery extends \Iterator {
    /**
     * @return string
     */
    public function getEmailSubject();
	
	/**
	 * This method should return a string that describes what iteration the
	 * query is currently on.
	 *
	 * @return string
	 */
	public function iteration();
}
?>