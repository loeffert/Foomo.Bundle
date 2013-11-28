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

namespace Foomo\Bundle\Dependency;

use Foomo\Bundle\AbstractBundle;
use Foomo\Bundle\Dependency;

/**
 * @link www.foomo.org
 * @license www.gnu.org/licenses/lgpl.txt
 * @author Jan Halfar <jan@bestbytes.com>
 */
class Manager
{
	const DEPENDENCY_MODEL_RESOLUTION_LIMIT = 100000;
	public static function getDependencyList(AbstractBundle $bundle, &$list = array())
	{
		foreach($bundle->dependencies as $dep) {
			$list[$dep->bundle->name] = $dep;
			self::getDependencyList($dep->bundle, $list);
		}
		return $list;
	}
	/**
	 * @return Dependency[]
	 */
	public static function getSortedDependencies(AbstractBundle $bundle)
	{
		$sortedDependencies = array();
		$i = 0;
		$dependencies = self::getDependencyList($bundle);
		while(count($sortedDependencies) < count($dependencies)) {
			$dependenciesToAddInOrder = self::getDependenciesSatisfiedByDependencies($sortedDependencies, $dependencies);
			foreach($dependenciesToAddInOrder as $dependencyToAddInOrder) {
				$found = false;
				foreach($sortedDependencies as $sortedDependency) {
					if($sortedDependency->bundle->name == $dependencyToAddInOrder->bundle->name) {
						$found = true;
						break;
					}
				}
				if(!$found) {
					$sortedDependencies[] = $dependencyToAddInOrder;
				}
			}
			if($i > self::DEPENDENCY_MODEL_RESOLUTION_LIMIT) {
				trigger_error('can not resolve dependencies', E_USER_ERROR);
			}
			$i ++;
		}
		return $sortedDependencies;
	}

	/**
	 * @param Dependency[] $satisfyingDependencies
	 * @param Dependency[] $dependencies
	 *
	 * @return Dependency[]
	 */
	public static function getDependenciesSatisfiedByDependencies(array $satisfyingDependencies, array $dependencies)
	{
		$ret = array();
		foreach($dependencies as $dependency) {
			if(self::bundleIsSatisfiedWithDependencies($dependency->bundle, $satisfyingDependencies)) {
				$ret[] = $dependency;
			}
		}
		return $ret;
	}

	public static function bundleIsSatisfiedWithDependencies(AbstractBundle $bundle, array $dependencies)
	{
		foreach($bundle->dependencies as $dependency) {
			$found = false;
			foreach($dependencies as $satisfyingDependency) {
				if($satisfyingDependency->bundle->name == $dependency->bundle->name) {
					$found = true;
					break;
				}
			}
			if(!$found) {
				return false;
			}
		}
		return true;
	}
}
