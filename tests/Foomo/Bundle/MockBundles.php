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

use Foomo\JS\Bundle as JSBundle;
/**
 * @link www.foomo.org
 * @license www.gnu.org/licenses/lgpl.txt
 * @author Jan Halfar <jan@bestbytes.com>
 */
class MockBundles
{
	public static function getScript($name)
	{
		return __DIR__ . DIRECTORY_SEPARATOR . 'mock' . DIRECTORY_SEPARATOR . $name . '.js';
	}
	public static function foo()
	{
		return JSBundle::create('foo')
			->debug(true)
			->addJavascript(self::getScript('foo'))
		;

	}
	public static function bar()
	{
		return JSBundle::create('bar')
			->debug(true)
			->addJavascript(self::getScript('bar'))
			->addDependency(self::foo())
		;
	}
	public static function barMerged()
	{
		return JSBundle::create('barMerged')
			->debug(true)
			->addJavascript(self::getScript('bar'))
			->merge(self::foo())
		;
	}

	public static function fooBar()
	{
		return JSBundle::create('fooBar')
			->debug(true)
			->addJavascript(self::getScript('fooBar'))
			->addDependency(self::bar())
		;
	}

	public static function m1()
	{
		return JSBundle::create('m1')
			->addJavascript(self::getScript('m/m1'))
		;
	}

	public static function m2()
	{
		return JSBundle::create('m2')
			->addJavascript(self::getScript('m/m2'))
			->merge(self::m1())
		;
	}

	public static function m3()
	{
		return JSBundle::create('m3')
			->addJavascript(self::getScript('m/m3'))
			->merge(self::m2())
		;
	}

	public static function n1()
	{
		return JSBundle::create('n1')->addJavascript(self::getScript('n/n1'));
	}

	public static function n2()
	{
		return JSBundle::create('n2')->addJavascript(self::getScript('n/n2'));
	}

	public static function n12()
	{
		return JSBundle::create('n12')
			->addJavascript(self::getScript('n/n12'))
			->merge(self::n1())
			->merge(self::n2())
		;
	}

	public static function full()
	{
		return JSBundle::create('full')
			->addJavascript(self::getScript('full'))
			->addDependency(self::m3())
			->addDependency(self::n12())
		;
	}
}

