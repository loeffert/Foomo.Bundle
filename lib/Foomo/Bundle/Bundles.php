<?php

/*
 * This file is part of the foomo Opensource Framework.
 *
 * The foomo Opensource Framework is free software: you can redistribute it
 * and/or modify it under the terms of the GNU Lesser General Public License as
 * published  by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * The foomo Opensource Framework is distributed in the hope that it will
 * be useful, but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License along with
 * the foomo Opensource Framework. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Foomo\Bundle;

use Foomo\Config;
use Foomo\JS;
use Foomo\Sass;
use Foomo\Less;
use Foomo\TypeScript;

/**
 * @link    www.foomo.org
 * @license www.gnu.org/licenses/lgpl.txt
 */
class Bundles
{
	// --------------------------------------------------------------------------------------------
	// ~ Public static methods
	// --------------------------------------------------------------------------------------------

	/**
	 * Add a cached bundle to the HTML Doc
	 *
	 * @param string   $name
	 * @param string   $moduleName
	 * @param string[] $data
	 * @param string[] $services
	 */
	public static function addBundleToDoc($name, $moduleName, array $data = [], array $services = [])
	{
		Compiler::addBundleToDoc(
			get_called_class() . '::getBundle',
			[$name, $moduleName, $data, $services],
			null,
			static::isDebug()
		);
	}

	/**
	 * Add multiple bundles merged together to the HTML Doc
	 *
	 * @param string   $name
	 * @param array   $bundles
	 */
	public static function addMergedBundleToDoc($name, array $bundles)
	{
		Compiler::addBundleToDoc(
			get_called_class() . '::getMergedBundle',
			[$name, $bundles],
			null,
			static::isDebug()
		);
	}

	/**
	 * Returns a bundle looking for resources under
	 *
	 * /js/NAME.js
	 * /typescript/libs/NAME
	 * /typescript/apps/NAME
	 * /sass/NAME.scss
	 *
	 * @param string           $name
	 * @param string           $moduleName
	 * @param string[]         $data
	 * @param string[]         $services
	 * @param AbstractBundle[] $dependencies
	 * @return AbstractBundle
	 * @throws \Exception
	 */
	public static function getBundle($name, $moduleName, array $data = [], array $services = [], array $dependencies = [])
	{
		# create unique name
		\Foomo\Timer::addMarker('getting bundle');

		# create TypeScript apps bundle
		$bundle = static::getTypeScriptBundle($name, $moduleName, $data);

		# create TypeScript libs bundle
		if (is_null($bundle)) {
			$bundle = static::getTypeScriptBundle($name, $moduleName, $data, 'libs');
		} else if (null != $deb = static::getTypeScriptBundle($name, $moduleName, $data, 'libs')) {
			$bundle->merge($deb);
		}

		# merge services
		if (is_null($bundle)) {
			$bundle = static::getJSServicesBundle($name, $services);
		} else if (null != $deb = static::getJSServicesBundle($name, $services)) {
			$bundle->merge($deb);
		}

		# create or add JS bundle
		if (is_null($bundle)) {
			$bundle = static::getJSBundle($name, $moduleName);
		} else if (null != $deb = static::getJSBundle($name, $moduleName)) {
			$bundle->merge($deb);
		}

		# create or add SASS bundle
		if (is_null($bundle)) {
			$bundle = static::getSASSBundle($name, $moduleName);
		} else if (null != $deb = static::getSASSBundle($name, $moduleName)) {
			$bundle->addDependency($deb);
		}

		# create or add LESS bundle
		if (is_null($bundle)) {
			$bundle = static::getLESSBundle($name, $moduleName);
		} else if (null != $deb = static::getLESSBundle($name, $moduleName)) {
			$bundle->addDependency($deb);
		}

		if (!is_null($bundle)) {
			$bundle->addDependencies($dependencies);
		}

		return $bundle;
	}

	/**
	 * Return bundles called via `getBundle`
	 *
	 * @param string $name
	 * @param array  $bundles
	 * @return AbstractBundle
	 */
	public static function getMergedBundle($name, array $bundles)
	{
		/* @var $ret AbstractBundle */
		$ret = null;
		foreach ($bundles as $params) {
			if (null != $bundle = call_user_func_array(get_called_class() . '::getBundle', $params)) {
				if (is_null($ret)) {
					$ret = $bundle;
				} else {
					$ret->merge($bundle);
				}
			}
		}
		if (!static::isDebug()) {
			$ret->name = $name;
		}
		return $ret;
	}

	/**
	 * Returns a typescript bundle while looking for
	 *
	 * /typescript/TYPE/NAME
	 *
	 * @param string $name
	 * @param string $moduleName
	 * @param array  $data
	 * @param string $type
	 * @return \Foomo\Bundle\AbstractBundle
	 */
	public static function getTypeScriptBundle($name, $moduleName, array $data = [], $type = 'apps')
	{
		# get bundle directory
		$filename = '/typescript/' . $type . '/' . $name . '/bundle.ts.tpl';

		if (null != $resolvedFilename = static::resolveFilename($filename, $moduleName)) {
			# get resolved module name
			$moduleName = static::resolveModule($filename, $moduleName);

			# get template dir
			$templatesDir = dirname($resolvedFilename) . '/templates';
			$templatesRenderer = new TypeScript\TemplateRenderer(
				$templatesDir . '.ts',
				\Foomo\Bundle\Module::getBaseDir('templates') . '/BackboneTemplates.tpl',
				(object) ['module' => $moduleName . '.Apps.' . ucfirst($name)]
			);

			# build bundle
			$bundle = TypeScript\Bundle::create("$name-ts-$type", dirname($resolvedFilename))
				->writeTypeDefinition(static::isDebug())
				->target(TypeScript::TARGET_ES5)
				->preProcessWithData($data)
				->debug(static::isDebug());

			if (file_exists($templatesDir)) {
				$bundle->lookForTemplates($templatesDir, $templatesRenderer);
			}

			return $bundle;
		} else {
			return null;
		}
	}

	/**
	 * Returns a JS Bundle while looking for
	 *
	 * /js/NAME.js
	 *
	 * @param string $name
	 * @param string $moduleName
	 * @return AbstractBundle|null
	 * @throws \Exception
	 */
	public static function getJSBundle($name, $moduleName)
	{
		$filename = '/js/' . $name . '.js';
		if (null != $resolvedFilename = static::resolveFilename($filename, $moduleName)) {
			return JS\Bundle::create("$name-js")
				->addJavaScripts([$resolvedFilename])
				->debug(static::isDebug());
		} else {
			return null;
		}
	}

	/**
	 * Returns a merged JS Bundle of services
	 *
	 * @param string   $name
	 * @param string[] $services
	 * @return AbstractBundle|null
	 * @throws \Exception
	 */
	public static function getJSServicesBundle($name, array $services)
	{
		$files = [];
		$baseDir = \Foomo\Services\Module::getHtdocsVarDir('js');

		foreach ($services as $service) {
			$filename = $baseDir . DIRECTORY_SEPARATOR . $service . '.js';
			if (file_exists($filename)) {
				$files[] = $filename;
			}
		}

		if (!empty($files)) {
			return JS\Bundle::create("$name-js-service")
				->addJavaScripts($files)
				->debug(static::isDebug());
		} else {
			return null;
		}
	}

	/**
	 * Returns a SASS Bundle while looking for
	 *
	 * /sass/NAME.scss
	 *
	 * @param string $name
	 * @param string $moduleName
	 * @return AbstractBundle|null
	 * @throws \Exception
	 */
	public static function getSASSBundle($name, $moduleName)
	{
		$filename = '/sass/' . $name . '.scss';
		if (null != $resolvedFilename = static::resolveFilename($filename, $moduleName)) {
			return Sass\Bundle::create("$name-sass", $resolvedFilename)
				->debug(static::isDebug());
		} else {
			return null;
		}
	}

	/**
	 * Returns a LESS Bundle while looking for
	 *
	 * /less/NAME.less
	 *
	 * @param string $name
	 * @param string $moduleName
	 * @return AbstractBundle|null
	 * @throws \Exception
	 */
	public static function getLESSBundle($name, $moduleName)
	{
		$filename = '/less/' . $name . '.less';
		if (null != $resolvedFilename = static::resolveFilename($filename, $moduleName)) {
			return Less\Bundle::create("$name-less", $resolvedFilename)
				->debug(static::isDebug());
		} else {
			return null;
		}
	}

	/**
	 * Global debug state
	 *
	 * @return bool
	 */
	public static function isDebug()
	{
		return (!Config::isProductionMode());
	}

	// --------------------------------------------------------------------------------------------
	// ~ Protected static methods
	// --------------------------------------------------------------------------------------------

	/**
	 * Resolve the filename by checking if the root module has it defined
	 *
	 * @param string $filename
	 * @param string $moduleName
	 * @return null|string
	 * @throws \Exception
	 */
	protected static function resolveFilename($filename, $moduleName)
	{
		if (null != $module = static::resolveModule($filename, $moduleName)) {
			$paths = static::getResolvePaths($moduleName);
			return $paths[$module] . $filename;
		} else {
			return null;
		}
	}

	/**
	 * Resolve the filename by checking if the root module has it defined
	 *
	 * @param string $filename
	 * @param string $moduleName
	 * @return null|string
	 * @throws \Exception
	 */
	protected static function resolveModule($filename, $moduleName)
	{
		$paths = static::getResolvePaths($moduleName);
		foreach ($paths as $module => $path) {
			if (file_exists($path . $filename)) {
				return $module;
			}
		}
		return null;
	}

	/**
	 * @param $moduleName
	 * @return array
	 * @throws \Exception
	 */
	protected static function getResolvePaths($moduleName)
	{
		$rootModule = Module::getRootModuleClass();
		if ($moduleName == $rootModule) {
			return [$rootModule];
		} else {
			return [
				Module::getRootModule() => $rootModule::getBaseDir(),
				$moduleName             => Config::getModuleDir($moduleName),
			];
		}
	}
}
