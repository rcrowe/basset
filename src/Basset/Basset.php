<?php namespace Basset;

use Closure;
use Illuminate\Filesystem;
use Illuminate\Config\Repository;

class Basset {

	/**
	 * Config repository instance.
	 * 
	 * @var Illuminate\Config\Repository
	 */
	protected $config;

	/**
	 * Filesystem instance.
	 * 
	 * @var Illuminate\Filesystem
	 */
	protected $files;

	/**
	 * Illuminate environment.
	 * 
	 * @var string
	 */
	protected $environment;

	/**
	 * Array of asset collections.
	 * 
	 * @var array
	 */
	protected $collections = array();

	/**
	 * Create a new Basset instance.
	 * 
	 * @param  Illuminate\Filesystem  $files
	 * @param  Illuminate\Config\Respository  $config
	 * @return void
	 */
	public function __construct(Filesystem $files, Repository $config, $environment)
	{
		$this->config = $config;
		$this->files = $files;
		$this->environment = $environment;

		$this->registerCollections();
	}

	/**
	 * Show the assets for a given collection.
	 * 
	 * @param  string  $collection
	 * @return string
	 */
	public function show($collection)
	{
		list($collection, $extension) = explode('.', $collection);

		if ($this->hasCollection($collection))
		{
			$collection = $this->collection($collection);

			$group = ($extension == 'css' ? 'style' : 'script');

			// Determine what course of action will be taken depending on the applications environment.
			// By default if within the production environment Basset will attempt to serve static assets.
			// If, however, Basset is unable to locate the static assets it will default to raw HTML.
			$environment = $this->config['basset.production_environment'];

			if ($collection->isCompiled($group))
			{
				if ($environment === true or $this->environment == $environment or is_null($environment) and in_array($environment, array('prod', 'production')))
				{
					$base = trim(str_replace(array('public', 'public_html', 'htdocs'), '', $this->config['basset.compiling_path']), '/');

					return new Html($group, $extension, path($base.'/'.$collection->getCompiledName($group)));
				}
			}

			// Spin through each of the assets for the particular group and store the raw HTML response.
			$response = array();

			foreach ($collection->getAssets($group) as $asset)
			{
				$response[] = new Html($asset->getGroup(), $asset->getExtension(), path($this->config['basset.handles'].'/'.$asset->getRelativePath()));
			}

			return implode(PHP_EOL, $response);
		}

		return "<!-- Basset could not find collection: {$collection} -->";
	}

	/**
	 * Create a new collection or edit an existing collection instance.
	 * 
	 * @param  string  $name
	 * @param  Closure  $callback
	 * @return Basset\Collection
	 */
	public function collection($name, Closure $callback = null)
	{
		if ( ! isset($this->collections[$name]))
		{
			$this->collections[$name] =  new Collection($name, $this->files, $this->config);
		}

		if (is_callable($callback))
		{
			$callback($this->collections[$name]);
		}

		return $this->collections[$name];
	}

	/**
	 * Determine if a collection exists.
	 * 
	 * @param  string  $name
	 * @return bool
	 */
	public function hasCollection($name)
	{
		return isset($this->collections[$name]);
	}

	/**
	 * Get all of the collections.
	 * 
	 * @return array
	 */
	public function getCollections()
	{
		return $this->collections;
	}

	/**
	 * Register collections from the configuration array.
	 * 
	 * @return void
	 */
	public function registerCollections()
	{
		foreach ($this->config['basset.collections'] as $name => $callback)
		{
			$this->collection($name, $callback);
		}
	}

}