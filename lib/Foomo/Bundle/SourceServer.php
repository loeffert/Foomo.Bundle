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

use Foomo\Config;
use Foomo\Modules\Manager;

/**
 * @link www.foomo.org
 * @license www.gnu.org/licenses/lgpl.txt
 */
abstract class SourceServer
{
	/**
	 * fixes the generated source map, so that it references the source server
	 *
	 * @param string $filenameCompiled tsc outfile
	 * @param array $sourceMapping
	 */
	public static function fixSourcemap($filenameCompiled, $moduleName)
	{
		$mapFile = $filenameCompiled . '.map';
		$map = json_decode(file_get_contents($mapFile));
		$newSources = array();
		foreach($map->sources as $src) {
			$newSources[] = self::mapSource($src, $filenameCompiled, $moduleName);
		}
		$map->sources = $newSources;
		$map->file = basename($filenameCompiled);
		file_put_contents($mapFile, json_encode($map));
	}
	private static function mapSource($src, $filenameCompiled, $moduleName)
	{
		static $moduleDir = null;
		if(is_null($moduleDir)) {
			$moduleDir = Config::getModuleDir();
		}
		$originalSrc = $src;
		$testSrc = realpath($src);
		if($testSrc !== false) {
			// absolute filename
			$src = $testSrc;
		} else {
			// relative path
			$src = realpath(dirname($filenameCompiled) . DIRECTORY_SEPARATOR . $src);
		}
		if($src === false) {
			trigger_error('could not find src ' . $src . ' in ' . dirname($filenameCompiled));
		}
		if(substr($src, 0, strlen($moduleDir)) === $moduleDir) {
			// convention match
			$parts = explode(DIRECTORY_SEPARATOR, substr($src, strlen($moduleDir) + 1));
			$calledClass = get_called_class();
			$rootDir = $calledClass::getModuleRootFolder();
			if(count($parts) > 2 && $parts[1] == $rootDir) {
				unset($parts[1]);
				return \Foomo\Config::getHtdocsPath($moduleName) . '/sourceServer.php/' . implode('/', $parts);
			} else {
				return $originalSrc;
			}
		} else {
			return $originalSrc;
		}
	}
	public static function resolveSource($path)
	{
		$parts = explode('/', $path);
		if(count($parts) > 1) {
			$moduleName = $parts[0];
			if(Manager::isModuleEnabled($moduleName)) {
				$calledClass = get_called_class();
				$rootDir = $calledClass::getModuleRootFolder();
				$filename = Config::getModuleDir($moduleName) . DIRECTORY_SEPARATOR . $rootDir;
				if(file_exists($filename) == is_dir($filename)) {
					unset($parts[0]);
					$filename = realpath($filename) . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts);
					if($filename == realpath($filename)) {
						return $filename;
					} else {
						trigger_error('somebody is trying to bullshit us and trying to exit the ' . $rootDir . ' dir for module ' . $moduleName . ' != ' . realpath($filename), E_USER_WARNING);
						return null;
					}
				} else {
					trigger_error('module has no ' . self::getModuleRootFolder() . ' dir', E_USER_WARNING);
					return null;
				}
			}
		} else {
			trigger_error('illegal path to resolve');
			return null;
		}
		return null;
	}

	public static function run()
	{
		if(!Config::isProductionMode()) {
			$path = substr($_SERVER['REQUEST_URI'], strlen($_SERVER['SCRIPT_NAME']) + 1);
			$sourceFilename = self::resolveSource($path);
			if(file_exists($sourceFilename)) {
				$calledClass = get_called_class();
				header('Content-Type: ' . ($calledClass::getMimetype($sourceFilename)));
				echo file_get_contents($sourceFilename);
			} else {
				trigger_error('could not resolve typescript source', E_USER_WARNING);
				echo '// source not found';
			}
		} else {
			echo '// no sources in prod mode';
		}
	}
	abstract public static function getMimetype($filename);
	abstract public static function getModuleRootFolder();
}