<?php

namespace Logger;

use Nette\Environment;
use Nette\Object;
use Nette\String;

/**
 * Filesystem-based implementation of ILogger.
 *
 * @version    0.6
 * @package    Logger
 * 
 * @author     Jan Smitka <jan@smitka.org>
 * @author     Martin Pecka <martin.pecka@clevis.cz>
 * @author     Matěj Humpál <finwe@finwe.info>
 * @copyright  Copyright (c) 2009-2010 Jan Smitka
 * @copyright  Copyright (c) 2009-2010 Martin Pecka
 * @copyright  Copyright (c) 2011 Matěj Humpál
 */
class FileLogger extends \Logger\AbstractLogger
{
	/**
	 * Mask of the log filename, it can contain strftime specifiers
	 *
	 * @var string
	 */
	private $filenameMask = 'log-%Y-%m-%d.log';

	/**
	 * The directory in which log files will reside
	 *
	 * @var string
	 */
	private $logDir = '%logDir%';

	/**
	 * Log files granularity in seconds, see setGranularity() for more information
	 *
	 * @var int seconds
	 */
	private $granularity = 0;

	/**
	 * Array of functions called after the message has been written
	 *
	 * @var array of function(FileLogger $logger, int $level, string $message); 
	 */
	public $onMessage = array();

	/**
	 * @var array of function(FileLogger $logger, string $fullName); Occurs before the new log file is created
	 */
	public $onLogFileCreated = array();

	/**
	 * @var string path to the current log file
	 */
	private $file;

	/**
	 * Creates instance of Filelogger. Sets options from parameter
	 *
	 * @param array $options
	 */
	public function __construct($options = array())
	{

		parent::__construct($options);

		if (isset($options['filenameMask'])) {
			$this->setFilenameMask($options['filenameMask']);
		}

		if (isset($options['logDir'])) {
			$this->setLogDir($options['logDir']);
		}

		if (isset($options['granularity'])) {
			$this->setGranularity((int) $options['granularity']);
		}

		if (isset($options['filenameMask'])) {
			$this->filenameMask = $options['filenameMask'];
		}

	}

	/**
	 * Returns the filename mask of log file.
	 * @return string
	 */
	public function getFilenameMask()
	{
		return $this->filenameMask;
	}


	/**
	 * Sets the filename mask for log files.
	 * You can use the strftime specifiers.
	 *
	 * @param string $filenameMask
	 * @see strftime()
	 */
	public function setFilenameMask($filenameMask)
	{
		$this->filenameMask = $filenameMask;
		$this->file = NULL;
	}


	/**
	 * Returns the directory path where log files reside.
	 *
	 * @return string with untranslated environment variables
	 */
	public function getLogDir()
	{
		return $this->logDir;
	}


	/**
	 * Sets the directory path where log files reside.
	 * You can use environment variables.
	 *
	 * @param string $logDir
	 * @see Environment::expand()
	 */
	public function setLogDir($logDir)
	{
		$this->logDir = $logDir;
		$this->file = NULL;
	}


	/**
	 * Returns log files granularity.
	 * @return int in seconds
	 */
	public function getGranularity()
	{
		return $this->granularity;
	}


	/**
	 * Sets log files granularity.
	 * Please note that real granularity is also determined by filename mask.
	 *
	 * When greater than 0, it defines a time span used for one log file.
	 * Eg. if you want to create two log files per day, you can define mask
	 * "%Y-%m-%d-%H" and set this to 43200 seconds (1/2 day), and the logs
	 * won't be created each hour, but each file will contain logs from
	 * 43200 seconds, resulting in a two files per day.
	 *
	 * @param int $granularity
	 * @throws InvalidArgumentException if the grannularity is not a non-negative number
	 */
	public function setGranularity($granularity)
	{
		if ($granularity < 0)
			throw new InvalidArgumentException('Granularity must be greater than or equal to 0.');

		$this->granularity = $granularity;
		$this->file = NULL;
	}

	/**
	 * Returns the full path to the current log file.
	 * @return string
	 * @throws InvalidStateException
	 */
	public function getFile()
	{
		if ($this->file === NULL) {
			// granularity calculations
			if ($this->granularity > 1) {
				$offset = 345600 - (int) date('Z');
				$timestamp = $offset + floor((time() - $offset) / $this->granularity) * $this->granularity;
			} else
				$timestamp = time();

			$this->file = ($path = Environment::expand($this->logDir))
				    . (String::endsWith($path, '/') ? '' : '/')
				    . strftime($this->filenameMask, $timestamp);
		}

		return $this->file;
	}

	/**
	 * @see Logger\ILogger::writeMessage()
	 */
	protected function writeMessage($level, $message)
	{
		if (!file_exists($this->getFile())) {
			$this->onLogFileCreated($this, $this->getFile());
		}

		// Please note that FILE_APPEND operation is atomic (tested):
		// http://us2.php.net/manual/en/function.file-put-contents.php
		if (!file_put_contents($this->getFile(), $message, FILE_APPEND))
			throw new \IOException('Write operation failed.');

		$this->onMessage($this, $level, $message);
		
	}

}
