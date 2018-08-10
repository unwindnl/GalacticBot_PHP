<?php

// Super simple json file storage class - which also includes an locking mechanism
class Storage
{

	private $filename, $handle = null;

	private $data = null;

	function __construct($filename)
	{
		$this->filename = $filename;
		$this->data = [];
	}

	function lock()
	{
		if (!is_file($this->filename))
			@touch($this->filename);
			
		$this->handle = fopen($this->filename, "r+");

		if (!$this->handle)
			return false;

		if (flock($this->handle, LOCK_EX | LOCK_NB))
		{
			$this->data = (Array)@json_decode(fread($this->handle, filesize($this->filename)));

			if (!$this->data)
				$this->data = [];

			return true;
		}

		return false;
	}

	function get($name)
	{
		if (isset($this->data[$name]))
			return $this->data[$name];

		return null;
	}

	function set($name, $value)
	{
		$this->data[$name] = $value;
	}

	function unlock()
	{
		ftruncate($this->handle, 0);

		fseek($this->handle, 0, SEEK_SET);
		
		fputs($this->handle, json_encode($this->data));

		flock($this->handle, LOCK_UN);

		fclose($this->handle);
	}

}

