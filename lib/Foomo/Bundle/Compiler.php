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

/**
 * @link www.foomo.org
 * @license www.gnu.org/licenses/lgpl.txt
 */
class Compiler
{
	/**
	 * @param mixed $bundleProvider
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
			return Proxy::call(__CLASS__, 'cachedCompileBundleUsingProvider', array($bundleProvider, $bundleProviderArguments));
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
		switch($result->mimeType) {
			case Compiler\Result::MIME_TYPE_JS:
				$doc->addJavascriptsToBody($result->links);
				break;
			case Compiler\Result::MIME_TYPE_CSS:
				$doc->addStylesheets($result->links);
				break;
		}
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
	/**
	 * @param AbstractBundle $bundle
	 *
	 * @return Compiler\Result
	 */
	public static function compile(AbstractBundle $bundle)
	{
		$dependencies = Dependency\Manager::getSortedDependencies($bundle);
		$dependencies[] = $topLevel = new Dependency($bundle, Dependency::TYPE_LINK);
		foreach ($dependencies as $dependency) {
			//Timer::start($timerAction = 'compile ' . $dependency->bundle->name);
			$dependency->compile();
			//Timer::stop($timerAction);
		}
		self::build($topLevel, $bundle->debug);

		// if something has to be merged, do it now
		for ($i = 0; $i < count($topLevel->result->files); $i++) {
			$files = $topLevel->result->files[$i];
			if (is_array($files)) {

				switch($topLevel->result->mimeType) {
					case Compiler\Result::MIME_TYPE_JS:
						$suffix = '.min.js';
						break;
					case Compiler\Result::MIME_TYPE_CSS:
						$suffix = '.min.css';
						break;
					default:
						trigger_error('can not merge mimeType: ' . $topLevel->result->mimeType, E_USER_ERROR);
				}

				$name = 'merged-' . $topLevel->result->links[$i] . '-' . md5(implode('-', $files));
				$basename =  $name . $suffix;

				$filename = \Foomo\Bundle\Module::getHtdocsVarDir() . DIRECTORY_SEPARATOR . $basename;
				if (!file_exists($filename)) {
					$newContents = call_user_func_array(array(get_class($topLevel->bundle), 'mergeFiles'), array($files, $bundle->debug));
					$oldContents = '';
					if(file_exists($filename)) {
						$oldContents = file_get_contents($filename);
					}
					if($oldContents != $newContents) {
						file_put_contents($filename, $newContents);
					}
				}

				$topLevel->result->files[$i] = $filename;
				$topLevel->result->links[$i] = \Foomo\Bundle\Module::getHtdocsVarBuildPath($basename);
			}
		}
		return $topLevel->result;
	}

	public static function build(Dependency $dependency, $debug)
	{
		foreach ($dependency->bundle->dependencies as $parentDependency) {
			self::build($parentDependency, $debug);
			if (
				$parentDependency->result->mimeType == $dependency->result->mimeType &&
				$parentDependency->type == Dependency::TYPE_MERGE &&
				!$debug
			) {
				$merged = self::flattenArray($parentDependency->result->files);
				array_pop($dependency->result->links);                // remove the link as well
				$lastItem = array_pop($dependency->result->files);
				if (is_array($lastItem)) {
					$lastJs = array_pop($lastItem);
					$merged = array_merge($merged, $lastItem);
					$merged[] = $lastJs;
				} else {
					$merged[] = $lastItem;
				}
				$dependency->result->files[] = $merged;
				$dependency->result->links[] = $dependency->bundle->name;
			} else {
				// link
				$dependency->result->files = array_merge($parentDependency->result->files, $dependency->result->files);
				$dependency->result->links = array_merge($parentDependency->result->links, $dependency->result->links);
			}
		}
	}

	/**
	 * @param mixed[] $a array of arrays
	 * @return string[]
	 */
	private static function flattenArray($a)
	{
		$res = array();
		foreach ($a as $item) {
			if (is_array($item)) {
				$res = array_merge($res, self::flattenArray($item));
			} else {
				$res[] = $item;
			}
		}
		return $res;
	}
}