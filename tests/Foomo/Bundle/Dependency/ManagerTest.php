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

use Foomo\Bundle\Dependency;
use Foomo\Bundle\MockBundles;

/**
 * @link www.foomo.org
 * @license www.gnu.org/licenses/lgpl.txt
 * @author Jan Halfar <jan@bestbytes.com>
 */
class ManagerTest extends \PHPUnit_Framework_TestCase
{
	public function testGetDependencyList()
	{
		// Manager::getDependencyList(MockBundles::fooBar());
		$actual = array();
		foreach(Manager::getDependencyList(MockBundles::fooBar()) as $dependency) {
			$actual[] = $dependency->bundle->name;
		}
		sort($actual);
		$this->assertEquals(array('bar', 'foo'), $actual, serialize($actual));

	}

	public function testGetSortedDependencies()
	{
		$bundleFooBar = MockBundles::fooBar();
		$expected = array(
			$bundleFooBar->dependencies['bar']->bundle->dependencies['foo'],
			$bundleFooBar->dependencies['bar']
		);
		$actual = Manager::getSortedDependencies($bundleFooBar);
		$this->assertEquals($expected, $actual);
	}

	public function testGetSortedDependenciesFull()
	{
		$bundleFull = MockBundles::full();

		$result = Manager::getSortedDependencies($bundleFull);

		$actual = array();
		$i = 0;
		foreach ($result as $dependency) {
			$actual[$dependency->bundle->name] = $i++;
		}

		// the actual order is not guaranteed, therefore check the order for the known dependencies
		$rules = array(
			'm2' => array('m1'),
			'm3' => array('m1', 'm2'),
			'n12' => array('n1', 'n2'),
		);
		foreach ($rules as $y => $deps) {
			foreach ($deps as $x) {
				$this->assertTrue($actual[$x] < $actual[$y], "bundle $x should come before bundle $y because $y depends upon $x");
			}
		}
	}

	public function testGetDependenciesSatisfiedByDependencies()
	{
		$bundleFoo = MockBundles::foo();
		$bundleBar = MockBundles::bar();

		$allDeps = array(
			$dependencyFoo = new Dependency($bundleFoo, Dependency::TYPE_LINK),
			$dependencyBar = new Dependency($bundleBar, Dependency::TYPE_LINK)
		);

		$dependencies = Manager::getDependenciesSatisfiedByDependencies(array(), $allDeps);

		$expected = array($dependencyFoo);
		$this->assertEquals($expected, $dependencies);

		$dependencies = Manager::getDependenciesSatisfiedByDependencies($dependencies, $allDeps);

		$expected = array($dependencyFoo, $dependencyBar);
		$this->assertEquals($expected, $dependencies);

	}

	public function testBundleIsSatisfiedWithDependencies()
	{
		$this->assertTrue(Manager::bundleIsSatisfiedWithDependencies(MockBundles::foo(), array()));
		$this->assertFalse(Manager::bundleIsSatisfiedWithDependencies(MockBundles::bar(), array()));
		$this->assertTrue(Manager::bundleIsSatisfiedWithDependencies(MockBundles::bar(), array(new Dependency(MockBundles::foo(), Dependency::TYPE_LINK))));
	}
}
