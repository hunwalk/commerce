<?php

/**
 * Craft Builder
 */
class Builder
{
	private $_sourceBaseDir;
	private $_finalBaseDir = '/www/eh21814/commerce/';
	private $_tempDir;

	private $_args;
	private $_startTime;


	/**
	 * The first argument is expected to be the build number.
	 *
	 * @param $args
	 *
	 * @throws Exception
	 */
	public function __construct($args)
	{
		$this->_args = array_merge(array(
			'build'         => '1',
			'version'       => '.05',
			'track'         => 'stable',
		), $args);


		$this->_sourceBaseDir = str_replace('\\', '/', realpath(__DIR__.'/../..')).'/';
		$this->_tempDir = $this->_sourceBaseDir.UtilsHelper::UUID();

		UtilsHelper::createDir($this->_tempDir);

		date_default_timezone_set('UTC');
		$this->_releaseDate = new DateTime(null, new \DateTimeZone('UTC'));
	}

	public function run()
	{
		$this->_startTime = UtilsHelper::getBenchmarkTime();

		$this->copyFiles();
		$this->updateVersionBuild();
		$this->processFiles();
		$this->cleanDestinationDirectories();
		$this->zipIt();

		$totalTime = UtilsHelper::getBenchmarkTime() - $this->_startTime;
		echo PHP_EOL.'Execution Time: '.$totalTime.' seconds.'.PHP_EOL;
	}

	/**
	 *
	 */
	protected function copyFiles()
	{
		echo ('Copying code from '.$this->_sourceSourceDir.'docs to '.$this->_args['destdir'].PHP_EOL);
		UtilsHelper::copyDirectory($this->_sourceBaseDir.'docs', $this->_tempDir);
		echo ('Finished copying code from '.$this->_sourceSourceDir.'docs to '.$this->_args['destdir'].PHP_EOL.PHP_EOL);

		echo ('Copying code from '.$this->_sourceSourceDir.'exampletemplates to '.$this->_args['destdir'].PHP_EOL);
		UtilsHelper::copyDirectory($this->_sourceBaseDir.'exampletemplates', $this->_tempDir);
		echo ('Finished copying code from '.$this->_sourceSourceDir.'exampletemplates to '.$this->_args['destdir'].PHP_EOL.PHP_EOL);

		echo ('Copying code from '.$this->_sourceSourceDir.'plugins to '.$this->_args['destdir'].PHP_EOL);
		UtilsHelper::copyDirectory($this->_sourceBaseDir.'plugins', $this->_tempDir);
		echo ('Finished copying code from '.$this->_sourceSourceDir.'plugins to '.$this->_args['destdir'].PHP_EOL.PHP_EOL);

		echo ('Copying file from '.$this->_sourceSourceDir.'CHANGELOG.md to '.$this->_args['destdir'].PHP_EOL);
		UtilsHelper::copyFile($this->_sourceBaseDir.'CHANGELOG.md', $this->_tempDir);
		echo ('Finished copying file from '.$this->_sourceSourceDir.'CHANGELOG.md to '.$this->_args['destdir'].PHP_EOL.PHP_EOL);

		echo ('Copying file from '.$this->_sourceSourceDir.'LICENSE.md to '.$this->_args['destdir'].PHP_EOL);
		UtilsHelper::copyFile($this->_sourceBaseDir.'LICENSE.md', $this->_tempDir);
		echo ('Finished copying file from '.$this->_sourceSourceDir.'LICENSE.md to '.$this->_args['destdir'].PHP_EOL.PHP_EOL);

		echo ('Copying file from '.$this->_sourceSourceDir.'README.md to '.$this->_args['destdir'].PHP_EOL);
		UtilsHelper::copyFile($this->_sourceBaseDir.'README.md', $this->_tempDir);
		echo ('Finished copying file from '.$this->_sourceSourceDir.'README.md to '.$this->_args['destdir'].PHP_EOL.PHP_EOL);

		echo ('Deleting file '.$this->_args['destdir'].'composer.json'.PHP_EOL);
		unlink($this->_sourceBaseDir.'composer.json');

		echo ('Deleting file '.$this->_args['destdir'].'composer.lock'.PHP_EOL);
		unlink($this->_sourceBaseDir.'composer.lock');

		echo ('Deleting file '.$this->_args['destdir'].'codeception.yml'.PHP_EOL);
		unlink($this->_sourceBaseDir.'codeception.yml');
	}

	/**
	 *
	 */
	protected function cleanDestinationDirectories()
	{
		// Delete aspnet_client folder.
		echo ('Deleting aspnet_client folder from '.$this->_args['destdir'].'public/aspnet_client'.PHP_EOL);
		UtilsHelper::purgeDirectory($this->_args['destdir'].'public/aspnet_client');
		if (file_exists($this->_args['destdir'].'public/aspnet_client'))
		{
			rmdir($this->_args['destdir'].'public/aspnet_client');
		}
		echo ('Finished deleting aspnet_client folder from '.$this->_args['destdir'].'public/aspnet_client'.PHP_EOL.PHP_EOL);

		$dsStores = UtilsHelper::findFiles($this->_tempDir, array('fileTypes' => array('DS_Store'), 'level' => -1));

		echo ('Found '.count($dsStores).' DS_Store files. Nuking them.'.PHP_EOL);
		foreach ($dsStores as $dsStore)
		{
			unlink($dsStore);
		}
		echo ('Done nuking DS_Store files'.PHP_EOL.PHP_EOL);
	}

	/**
	 *
	 */
	protected function processFiles()
	{
		echo ('Beginning to process app files'.PHP_EOL.PHP_EOL);
		$extensions = array('html', 'txt', 'scss', 'css', 'js', 'php', 'config', '');
		$allFiles = UtilsHelper::dirContents($this->_args['destdir'], $extensions);

		foreach ($allFiles as $file)
		{
			if (is_file($file))
			{
				if ($this->_excludePathSegments($file))
				{
					$this->processFile($file);
				}
			}
		}

		echo ('Finished processing app files'.PHP_EOL.PHP_EOL);
	}

	/**
	 * @param $file
	 */
	protected function processFile($file)
	{
		echo ('Processing '.$file.'... ');

		$contents = $newContents = file_get_contents($file);

		// Normalize newlines
		$newContents = str_replace("\r\n", "\n", $newContents);
		$newContents = str_replace("\r", "\n", $newContents);

		$extension = pathinfo($file, PATHINFO_EXTENSION);

		$newContents = $this->_parseComments($newContents, $extension);
		$this->_saveContents($newContents, $contents, $file);

		echo (PHP_EOL);
	}

	/**
	 *
	 */
	protected function updateVersionBuild()
	{
		$path = $this->_tempDir.'plugins/MarketPlugin.php';
		echo 'Loading the contents of MarketPlugin.php: '.$path.PHP_EOL;
		$contents = file_get_contents($path);

		$variables = array(
			'9.9'                 => $this->_args['version'],
			'9999'                => $this->_args['build'],
		);

		$newContents = str_replace(
			array_keys($variables),
			array_values($variables),
			$contents
		);

		echo 'Saving config/main.php'.PHP_EOL;
		file_put_contents($path, $newContents);
		echo 'Done.'.PHP_EOL.PHP_EOL;
	}

	protected function zipIt()
	{
		echo 'Zipping '.$this->_tempDir.PHP_EOL;
		UtilsHelper::zipDir($this->_tempDir, 'Commerce-'.$this->_args['version'].'.'.$this->_args['build'].'.zip');
		echo 'Done zipping '.$this->_tempDir.PHP_EOL.PHP_EOL;

		if (!file_exists($this->_finalBaseDir.$this->_args['version']))
		{
			echo 'Creating '.$this->_finalBaseDir.$this->_args['version'].PHP_EOL;
			UtilsHelper::createDir($this->_finalBaseDir.$this->_args['version']);
			echo 'Done creating '.$this->_finalBaseDir.$this->_args['version'].PHP_EOL.PHP_EOL;
		}

		if (!file_exists($this->_finalBaseDir.$this->_args['version'].'/'.$this->_args['version'].'.'.$this->_args['build']))
		{
			echo 'Creating '.$this->_finalBaseDir.$this->_args['version'].'/'.$this->_args['version'].'.'.$this->_args['build'].PHP_EOL;
			UtilsHelper::createDir($this->_finalBaseDir.$this->_args['version'].'/'.$this->_args['version'].'.'.$this->_args['build']);
			echo 'Done creating '.$this->_finalBaseDir.$this->_args['version'].'/'.$this->_args['version'].'.'.$this->_args['build'].PHP_EOL.PHP_EOL;
		}

		echo 'Copying '.$this->_tempDir.'Commerce-'.$this->_args['version'].'.'.$this->_args['build'].'.zip to '.$this->_finalBaseDir.$this->_args['version'].'/'.$this->_args['version'].'.'.$this->_args['build'].PHP_EOL;
		UtilsHelper::copyFile($this->_tempDir.'Commerce-'.$this->_args['version'].'.'.$this->_args['build'].'.zip', $this->_finalBaseDir.$this->_args['version'].'/'.$this->_args['version'].'.'.$this->_args['build']);
		echo 'Done copying '.$this->_tempDir.'Commerce-'.$this->_args['version'].'.'.$this->_args['build'].'.zip to '.$this->_finalBaseDir.$this->_args['version'].'/'.$this->_args['version'].'.'.$this->_args['build'].PHP_EOL;
	}

	/**
	 * Removes // <!-- HIDE --> ... <!-- end HIDE --> comments
	 *
	 * @param $contents
	 * @param $extension
	 * @return mixed
	 */
	private function _parseComments($contents, $extension)
	{
		if ($extension == 'html')
		{
			$commentStyles = array(
				array('<!--', '-->'),
				array('{#', '#}'),
			);
		}
		else
		{
			$commentStyles = array(
				array('/*', '*/'),
			);
		}

		foreach ($commentStyles as $style)
		{
			$ld = preg_quote($style[0], '/');
			$rd = preg_quote($style[1], '/');
			$contents = preg_replace("/[\t ]*{$ld}\s+HIDE\s+{$rd}[\t ]*\n(.*?\n)[\t ]*{$ld}\s+end HIDE\s+{$rd}[\t ]*\n/s", '', $contents);
		}

		return $contents;
	}

	/**
	 * @param $newContents
	 * @param $oldContents
	 * @param $file
	 */
	private function _saveContents($newContents, $oldContents, $file)
	{
		if ($newContents != $oldContents)
		{
			echo ('Saving... ');
			file_put_contents($file, $newContents);
			echo ('Done.');
		}
		else
		{
			echo ('No changes.');
		}
	}

	/**
	 * @param string $path
	 * @param array $extraTests
	 * @return bool
	 */
	private function _excludePathSegments($path, $extraTests = array())
	{
		$path = str_replace('\\', '/', $path);

		if (strpos($path, '/vendor/') !== false)
		{
			return false;
		}

		foreach ($extraTests as $test)
		{
			if (strpos($path, $test) !== false)
			{
				return false;
			}
		}

		return true;
	}
}
