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
use Foomo\Cache\Invalidator;
use Foomo\Modules\MakeResult;

/**
 * @link www.foomo.org
 * @license www.gnu.org/licenses/lgpl.txt
 */
class Module extends \Foomo\Modules\ModuleBase
{
	//---------------------------------------------------------------------------------------------
	// ~ Constants
	//---------------------------------------------------------------------------------------------

	/**
	 * the name of this module
	 *
	 */
	const NAME = 'Foomo.Bundle';
	const VERSION = '0.1.1';

	//---------------------------------------------------------------------------------------------
	// ~ Overriden static methods
	//---------------------------------------------------------------------------------------------

	/**
	 * Your module needs to be set up, before being used - this is the place to do it
	 */
	public static function initializeModule()
	{
	}

	/**
	 * Get a plain text description of what this module does
	 *
	 * @return string
	 */
	public static function getDescription()
	{
		return 'bundles for the web js, css';
	}

	/**
	 * get all the module resources
	 *
	 * @return \Foomo\Modules\Resource[]
	 */
	public static function getResources()
	{
		return array(
			\Foomo\Modules\Resource\Module::getResource('Foomo', self::VERSION),
			// get a run mode independent folder var/<runMode>/test
			// \Foomo\Modules\Resource\Fs::getVarResource(\Foomo\Modules\Resource\Fs::TYPE_FOLDER, 'test'),
			// and a file in it
			// \Foomo\Modules\Resource\Fs::getVarResource(\Foomo\Modules\Resource\Fs::TYPE_File, 'test' . DIRECTORY_SEPARATOR . 'someFile'),
			// request a cache resource
			// \Foomo\Modules\Resource\Fs::getCacheResource(\Foomo\Modules\Resource\Fs::TYPE_FOLDER, 'navigationLeaves'),
			// a database configuration
			// \Foomo\Modules\Resource\Config::getResource('yourModule', 'db')
		);
	}
	public static function cleanDir($dir, MakeResult $result)
	{
		$result->addEntry('cleaning js files in ' . $dir);
		foreach(new \DirectoryIterator($dir) as $fileInfo) {
			if($fileInfo->isFile() && substr($fileInfo->getFilename(), -3) == '.js') {
				if(unlink($fileInfo->getPathname())) {
					$result->addEntry('removed ' . $fileInfo->getFilename());
				} else {
					$result->addEntry('could not remove ' . $fileInfo->getFilename(), MakeResult\Entry::LEVEL_ERROR, false);
				}
			}
		}
	}

	public static function make($target, MakeResult $result)
	{
		switch($target) {
			case 'clean':
				self::cleanDir(self::getHtdocsVarDir(), $result);
				self::cleanDir(self::getVarDir(), $result);
				$result->addEntry('deleting bundle cache');
				\Foomo\Cache\Manager::invalidateWithQuery('Foomo\\Bundle\\Compiler::cachedCompileBundleUsingProvider', null, true, Invalidator::POLICY_DELETE);
				break;
			default:
				parent::make($target, $result);
		}
	}

}