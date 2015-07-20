<?php
function classLoader($className) {
	/* It is expected that all classes will reside either in subdirectories of
	the same directory that contains this file or in one of the directories
	enumerated in the php.ini include_path setting. It is further assumed that
	there will be subdirectories of one of these paths that are named after the
	base word identifying the class (i.e. all the files for the ScraperBase,
	Scraper, ScraperReader, etc classes would be found in the Scraper/
	subdirectory), or if the class's name contains one or more underscores,
	in the directory tree derived by converting the underscores to the
	local directory separator (i.e. Archive_Tar would be found in
	Archive/Tar/). Namespaced class names are resolved in the same way (i.e. a
	split on the '\' character).
	Although you could come at this from the other direction (i.e. building a
	string out of the class name passed to this function letter-by-letter and
	testing for the existence of a directory with that name), I am going the
	other way: listing all the subdirectories and seeing if any of them form a
	substring of the class name anchored at position 0. Last resort is to try
	looking for the class name in the classes directory itself. */
	//$paths = array(pathinfo(realpath(__FILE__), PATHINFO_DIRNAME));
	$paths = array(__DIR__);
	$paths = array_merge($paths, explode(':', ini_get('include_path')));
	$validExtensions = array('.class.php', '.php');
	/* Test for proper namespacing with \ as well as ad-hoc namespacing with _
	(they could coexist). */
	$hasSlash = strpos($className, '\\') !== false;
	$hasUnder = strpos($className, '_') !== false;
	$split = null;
	if ($hasSlash) {
		$split = explode('\\', $className);
	}
	if ($hasUnder) {
		if ($split) {
			// Attempt to split each element
			$oldSplit = $split;
			$split = array();
			foreach ($oldSplit as $piece) {
				$split = array_merge($split, explode('_', $piece));
			}
		}
		else {
			$split = explode('_', $className);
		}
	}
	if ($split) {
		// We may need this for later
		$rawClassName = $className;
		$className = array_pop($split);
		$relPath = implode(DIRECTORY_SEPARATOR, $split);
	}
	else {
		$relPath = '';
	}
	foreach ($paths as $path) {
		// Ensure no trailing slash, then attach $relPath
		$path = rtrim($path, DIRECTORY_SEPARATOR);
		if ($relPath) {
			$path .= DIRECTORY_SEPARATOR . $relPath;
		}
		// Skip if it's not a directory
		if (!is_dir($path)) {
			continue;
		}
		/* First try resolving the directory structure based on clues in the
		namespace. */
		$classFileBase = $path . DIRECTORY_SEPARATOR . $className;
		foreach ($validExtensions as $ext) {
			$classFile = $classFileBase . $ext;
			if (is_file($classFile)) {
				require_once($classFile);
				return;
			}
		}
		/* If that didn't work, see if we can find a path that looks like
		$className/$className.(class.)php. */
		$classFileBase .= DIRECTORY_SEPARATOR . $className;
		foreach ($validExtensions as $ext) {
			$classFile = $classFileBase . $ext;
			if (is_file($classFile)) {
				require_once($classFile);
				return;
			}
		}
		/* Next, try the directory/substring method on the root path's
		contents. If we get here and the class name contains underscores, then
		it's not a case of ad-hoc namespacing, so restore the original class
		name. */
		if ($hasUnder) {
			$className = $rawClassName;
		}
		if ($dirHandle = opendir($path)) {
			while (false !== ($file = readdir($dirHandle))) {
				$fullPath = $path . DIRECTORY_SEPARATOR . $file;
				if (!is_dir($fullPath) || $file == '.' || $file == '..') {
					continue;
				}
				if (strpos($className, $file) === 0) {
					foreach ($validExtensions as $ext) {
						$classFile = $fullPath . DIRECTORY_SEPARATOR
						           . $className . $ext;
						if (is_file($classFile)) {
							require_once($classFile);
							closedir($dirHandle);
							return;
						}
					}
				}
			}
			closedir($dirHandle);
		}
		// Last resort: try at path root level
		foreach ($validExtensions as $ext) {
			$classFile = $path . DIRECTORY_SEPARATOR . $className . $ext;
			if (is_file($classFile)) {
				require_once($classFile);
			}
		}
	}
}

spl_autoload_register('classLoader', true);
?>
