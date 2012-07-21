<?php namespace Basset;

use URL;
use File;
use Bundle;

class Asset {

	public $name;

	public $file;

	public $dependencies = array();

	public $external = false;

	public $updated = 0;

	public $directory = null;

	public $url = null;

	/**
	 * Create a new Basset\Asset instance.
	 *
	 * @param  string  $name
	 * @param  string  $file
	 * @param  array   $dependencies
	 * @return void
	 */
	public function __construct($name, $file, $dependencies)
	{
		$this->name = $name;

		$this->file = $file;

		$this->dependencies = (array) $dependencies;

		$this->url = URL::to_asset(null);
	}

	/**
	 * Checks if the asset exists within the given directory.
	 * 
	 * @param  string  $directory
	 * @return bool
	 */
	public function exists($directory)
	{
		dd($this->is('less'));
		if(str_contains($this->file, '::') or !parse_url($this->file, PHP_URL_SCHEME))
		{
			$this->directory = $directory;

			if(is_null($directory))
			{
				$this->directory = path('public');
			}

			// If the asset is prefixed with a bundle identifier then we'll navigate to the assets directory.
			if(str_contains($this->file, '::'))
			{
				list($bundle, $file) = explode('::', $this->file);

				$this->directory .= trim(Bundle::assets($bundle), '/');

				$this->file = $file;

				$this->url .= trim(Bundle::assets($bundle), '/') . '/';
			}

			// If the directory that was provided initially is empty and no directory separator
			// is in the file name we'll default to the CSS or JS directory within the public
			// directory.
			if(is_null($directory) and !str_contains($this->file, '/'))
			{
				$this->directory .= DS . File::extension($this->file);

				$this->url .= File::extension($this->file) . '/';
			}

			$this->directory = realpath($this->directory);

			$this->url .= $this->file;

			if(!file_exists($this->directory . DS . $this->file))
			{
				return false;
			}
		}
		else
		{
			$this->external = true;
		}

		return true;
	}

	/**
	 * If the asset is external or internal.
	 * 
	 * @return bool
	 */
	public function external()
	{
		return $this->external;
	}

	/**
	 * Gets the contents of an asset and if it's a stylesheet it is run through the
	 * URI rewriter to correct any ill-formed directories.
	 *
	 * @param  array   $symlinks
	 * @param  string  $document_root
	 * @return string
	 */
	public function get($symlinks = array(), $document_root = '')
	{
		$failed = PHP_EOL . '/* Basset could not find asset [' . $this->directory . DS . $this->file . '] */' . PHP_EOL;

		$contents = @file_get_contents($this->directory . DS . $this->file);

		if(empty($contents))
		{
			return $failed;
		}

		if($this->is('styles'))
		{
			$contents = Vendor\URIRewriter::rewrite($contents, dirname($this->directory .DS . $this->file), $document_root, $symlinks);
		}

		if($this->is('less') && Config::get('less.php'))
		{
			$less = new Vendor\lessc;
			$less->importDir = $this->directory;

			$contents = $less->parse($contents);
		}

		return $contents . PHP_EOL;
	}

	/**
	 * Checks if the asset is part of a group based on its extension.
	 * 
	 * @param  string  $group
	 * @return void
	 */
	public function is($group)
	{
		$extensions = array(
			'css'  => 'styles',
			'less' => 'styles',
			'js'   => 'scripts'
		);

		if(!array_key_exists($extension = File::extension($this->file), $extensions))
		{
			return false;
		}

		return $group == $extensions[$extension];
	}

}