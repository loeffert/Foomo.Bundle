<?php

/*
 * This file is part of the foomo Opensource Framework.
 *
 * The foomo Opensource Framework is free software: you can redistribute it
 * and/or modify it under the terms of the GNU Lesser General Public License as
 * published  by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * The foomo Opensource Framework is distributed in the hope that it will
 * be useful, but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License along with
 * the foomo Opensource Framework. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Foomo\Bundle;

use AbstractBundle as Bundle;
use Foomo\Cache\Proxy;
use Foomo\Config;
use Foomo\HTMLDocument;
use Foomo\Timer;

/**
 * @link www.foomo.org
 * @license www.gnu.org/licenses/lgpl.txt
 */
class Compiler
{

	//------------------------------------------------------------------------------------------------------------------
	// public api
	//------------------------------------------------------------------------------------------------------------------

	/**
	 * @param string $bundleProvider
	 * @param array $bundleProviderArguments
	 * @param bool $debug
	 * @return Compiler\Result|mixed
	 */
	public static function compileAndCache($bundleProvider, array $bundleProviderArguments = array(), $debug = null)
	{
		if(is_null($debug)) {
			$debug = !Config::isProductionMode();
		}
		if($debug) {
			return self::compileBundleUsingProvider($bundleProvider, $bundleProviderArguments);
		} else {
			return Proxy::call(
				__CLASS__,
				'cachedCompileBundleUsingProvider',
				array($bundleProvider, $bundleProviderArguments)
			);
		}
	}
	/**
	 * @param string $bundleProvider string class::method
	 * @param array $bundleProviderArguments
	 * @param HTMLDocument $doc
	 * @param bool $debug
	 * @throws \InvalidArgument
	 */
	public static function addBundleToDoc($bundleProvider, array $bundleProviderArguments = array(), HTMLDocument $doc = null, $debug = null)
	{
		if(is_null($doc)) {
			$doc = HTMLDocument::getInstance();
		}
		$result = self::compileAndCache($bundleProvider, $bundleProviderArguments, $debug);
		$jsLinks = array();
		$cssLinks = array();
		foreach($result->resources as $resource) {
			switch($resource->mimeType) {
				case Compiler\Result\Resource::MIME_TYPE_JS:
					$jsLinks[] = $resource->link;
					break;
				case Compiler\Result\Resource::MIME_TYPE_CSS:
					$cssLinks[] = $resource->link;
					break;
			}
		}
		$doc
			->addStylesheets($cssLinks)
			->addJavascriptsToBody($jsLinks)
		;

	}

	public static function compileToJSBundle($bundleProvider, array $bundleProviderArguments = array(), $debug = null)
	{
		$scripts = array();
		$styleSheets = array();
		foreach(self::compileAndCache($bundleProvider, $bundleProviderArguments, $debug)->resources as $resource) {
			if($resource->mimeType == Compiler\Result\Resource::MIME_TYPE_JS) {
				$scripts[] = $resource->link;
			} else if($resource->mimeType == Compiler\Result\Resource::MIME_TYPE_CSS) {
				$styleSheets[] = $resource->link;
			}
		}
		return (object) array(
			'scripts' => $scripts,
			'styleSheets' => $styleSheets
		);
	}

	/**
	 * @param string $bundleProvider
	 * @param array $bundleProviderArguments
	 *
	 * @return Compiler\Result
	 *
	 * @Foomo\Cache\CacheResourceDescription
	 */
	public static function cachedCompileBundleUsingProvider($bundleProvider, array $bundleProviderArguments = array())
	{
		return self::compileBundleUsingProvider($bundleProvider, $bundleProviderArguments);
	}

	/**
	 * @param string $bundleProvider
	 * @param array $bundleProviderArguments
	 *
	 * @return Compiler\Result
	 */
	private static function compileBundleUsingProvider($bundleProvider, array $bundleProviderArguments = array())
	{
		return self::compile(call_user_func_array(explode('::', $bundleProvider), $bundleProviderArguments));
	}

	//------------------------------------------------------------------------------------------------------------------
	// private implementation
	//------------------------------------------------------------------------------------------------------------------

	/**
	 * @param AbstractBundle $bundle
	 *
	 * @return Compiler\Result
	 */
	public static function compile(AbstractBundle $bundle)
	{
		static $cache = array();
		$cacheKey = md5($bundle->getFingerPrint());
		if(!isset($cache[$cacheKey])) {
			Timer::start(__METHOD__);

			foreach($bundle->references as $reference) {
				$result = new Compiler\Result;
				$reference->compile($result);
			}


			$dependencies = Dependency\Manager::getSortedDependencies($bundle);
			$dependencies[] = $topLevel = new Dependency($bundle, Dependency::TYPE_LINK);
			$mergers = array(
				Compiler\Result\Resource::MIME_TYPE_CSS => null,
				Compiler\Result\Resource::MIME_TYPE_JS => null
			);
			$mergersFound = 0;
			foreach ($dependencies as $dependency) {
				if(count($mergers) > $mergersFound) {
					foreach($mergers as $mimeType => $merger) {
						if(is_null($merger) && call_user_func_array(array(get_class($dependency->bundle), 'canMerge'), array($mimeType))) {
							$mergers[$mimeType] = $dependency->bundle;
							$mergersFound ++;
						}
					}
				}
				$dependency->compile();
			}
			self::build($topLevel, $bundle->debug);
			// if something has to be merged, do it now
			for ($i = 0; $i < count($topLevel->result->resources); $i++) {
				$resources = $topLevel->result->resources[$i];
				if (is_array($resources)) {
					$mimeFiles = array();
					foreach($resources['resources'] as $resource) {
						if(!isset($mimeFiles[$resource->mimeType])) {
							$mimeFiles[$resource->mimeType] = array();
						}
						$mimeFiles[$resource->mimeType][] = $resource->file;
					}
					foreach($mimeFiles as $mimeType => $files) {
						switch($mimeType) {
							case Compiler\Result\Resource::MIME_TYPE_JS:
								$suffix = '.min.js';
								break;
							case Compiler\Result\Resource::MIME_TYPE_CSS:
								$suffix = '.min.css';
								break;
							default:
								trigger_error('can not merge mimeType: ' . $topLevel->result->mimeType, E_USER_ERROR);
						}

						$name = 'merged-' . $resources['name'] . '-' . md5(implode('-', $files));
						$basename =  $name . $suffix;

						$filename = \Foomo\Bundle\Module::getHtdocsVarDir() . DIRECTORY_SEPARATOR . $basename;
						if (!file_exists($filename)) {
							$newContents = call_user_func_array(array(get_class($mergers[$mimeType]), 'mergeFiles'), array($files, $bundle->debug));
							$oldContents = '';
							if(file_exists($filename)) {
								$oldContents = file_get_contents($filename);
							}
							if($oldContents != $newContents) {
								file_put_contents($filename, $newContents);
							}
						}
						$topLevel->result->resources[$i] = Compiler\Result\Resource::create(
							$mimeType,
							$filename,
							\Foomo\Bundle\Module::getHtdocsVarBuildPath($basename)
						);
					}
				}
			}
			Timer::stop(__METHOD__);
			$cache[$cacheKey] = $topLevel->result;
		}
		return $cache[$cacheKey];
	}

	public static function build(Dependency $dependency, $debug)
	{
		foreach ($dependency->bundle->dependencies as $parentDependency) {
			self::build($parentDependency, $debug);
			if (
				$parentDependency->type == Dependency::TYPE_MERGE &&
				!$debug
			) {
				// merge
				$merged = array(
					'resources' => self::flattenArray($parentDependency->result->resources),
					'name' => $dependency->bundle->name
				);
				$lastItem = array_pop($dependency->result->resources);
				if (is_array($lastItem)) {
					$lastResource = array_pop($lastItem['resources']);
					$merged['resources'] = array_merge($merged['resources'], $lastItem['resources']);
					$merged['resources'][] = $lastResource;
				} else if(is_object($lastItem)) {
					$merged['resources'][] = $lastItem;
				}
				$dependency->result->resources[] = $merged;
			} else {
				// link
				$dependency->result->resources = array_merge($parentDependency->result->resources, $dependency->result->resources);
			}
		}
	}

	private static function getLastResource($resourceArray)
	{
		$cleanResources = array();
		$lastResource = null;
		foreach($resourceArray as $resource) {
			if(!is_string($resource)) {
				$lastResource = $resource;
			}
		}
		return $lastResource;
	}
	/**
	 * @param mixed[] $a array of arrays
	 * @return string[]
	 */
	private static function flattenArray($a)
	{
		$res = array();
		if(isset($a['resources'])) {
			$a = $a['resources'];
		}
		foreach ($a as $key => $item) {
			if (is_array($item)) {
				$res = array_merge($res, self::flattenArray($item));
			} else {
				$res[] = $item;
			}
		}
		return $res;
	}
}