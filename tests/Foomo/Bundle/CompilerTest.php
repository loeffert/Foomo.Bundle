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
use Foomo\CliCall;
use Foomo\Modules\MakeResult;
use Foomo\JS\Bundle as JSBundle;

/**
 * @link www.foomo.org
 * @license www.gnu.org/licenses/lgpl.txt
 */
class CompilerTest extends \PHPUnit_Framework_TestCase
{

	public function setup()
	{
		\Foomo\JS\Module::make('clean', new MakeResult(\Foomo\JS\Module::NAME));
		if(!class_exists('Foomo\\JS\\Module')) {
			$this->markTestSkipped('need Foomo.JS to run');
		}
	}
	public function testCompileSimpleNoDeps()
	{
		$result = Compiler::compile(MockBundles::foo());
		$this->assertInstanceOf('Foomo\\Bundle\\Compiler\\Result', $result);
		$this->assertCount(1, $result->resources);
	}

	public function testCompileDeps()
	{
		$result = Compiler::compile(MockBundles::bar());
		$this->assertCount(2, $result->resources);
		$expected = array('foo', 'bar');
		$actual = array();
		foreach ($result->resources as $resource) {
			$actual[] = substr(basename($resource->file), 0, 3);
		}
		$this->assertEquals($expected, $actual);
	}

	public function testCompileDepsProd()
	{
		$result = Compiler::compile(MockBundles::barMerged()->debug(false));
		$this->assertCount(1, $result->resources);
		$jsResult = self::runJs($result->resources[0]->file);
		$expected = 'foo barfoo';
		$this->assertEquals($expected, $jsResult, "failed, '$expected' != '$jsResult'");
	}

	public function testFullDev()
	{
		$result = Compiler::compile(MockBundles::full()->debug(true));
		$this->assertCount(7, $result->resources);
		// some empty results expected since top level js files cannot be executed standalone without including the others
		$jsFiles = array();
		foreach (array('n2', 'n1', '', 'm1', '', '', '') as $i => $expected) {
			$jsResult = self::runJs($jsFile = $result->resources[$i]->file);
			$jsFiles[] = $jsFile;
			$this->assertEquals($expected, $jsResult, "failed, '$expected' != '$jsResult'");
		}
		$expected = 'n2 n1 n12n1n2 m1 m2m1 m3m2m1 fullm3m2m1n12n1n2';
		$jsResult = self::runJs($jsFiles);
		$this->assertEquals($expected, $jsResult, "'$expected' != '$jsResult'");
	}

	public function testFullProd()
	{
		$result = Compiler::compile(MockBundles::full()->debug(false));
		$this->assertCount(3, $result->resources);
		// empty result expected since top level js file cannot be executed standalone without including the others
		$jsFiles = array();
		foreach (array('n2 n1 n12n1n2', 'm1 m2m1 m3m2m1', '') as $i => $expected) {
			$jsResult = self::runJs($jsFile = $result->resources[$i]->file);
			$jsFiles[] = $jsFile;
			$this->assertEquals($expected, $jsResult, "failed, '$expected' != '$jsResult'");
		}
		$expected = 'n2 n1 n12n1n2 m1 m2m1 m3m2m1 fullm3m2m1n12n1n2';
		$jsResult = self::runJs($jsFiles);
		$this->assertEquals($expected, $jsResult, "'$expected' != '$jsResult'");
	}

	public function testLinkedFullProd()
	{
		$linkedFull = JSBundle::create('linkedFull')
			->debug(false)
			->addJavascript(MockBundles::getScript('foo'))
			->addDependency(MockBundles::full())
		;

		$result = Compiler::compile($linkedFull);
		$this->assertCount(4, $result->resources);
		// empty result expected since top level js file cannot be executed standalone without including the others
		$jsFiles = array();
		foreach (array('n2 n1 n12n1n2', 'm1 m2m1 m3m2m1', '', 'foo') as $i => $expected) {
			$jsResult = self::runJs($jsFile = $result->resources[$i]->file);
			$jsFiles[] = $jsFile;
			$this->assertEquals($expected, $jsResult, "failed, '$expected' != '$jsResult'");
		}
		$expected = 'n2 n1 n12n1n2 m1 m2m1 m3m2m1 fullm3m2m1n12n1n2 foo';
		$jsResult = self::runJs($jsFiles);
		$this->assertEquals($expected, $jsResult, "'$expected' != '$jsResult'");
	}

	public function testMergedFullProd()
	{
		$mergedFull = JSBundle::create('mergedFull')
			->debug(false)
			->addJavascript(MockBundles::getScript('foo'))
			->merge(MockBundles::full())
		;

		$result = Compiler::compile($mergedFull);
		$this->assertCount(1, $result->resources);
		$expected = 'n2 n1 n12n1n2 m1 m2m1 m3m2m1 fullm3m2m1n12n1n2 foo';
		$jsResult = self::runJs($result->resources[0]->file);
		$this->assertEquals($expected, $jsResult, "'$expected' != '$jsResult'");
	}

	private static function runJs($filenames)
	{
		if (is_array($filenames)) {
			$file = tempnam(\Foomo\Config::getTempDir(), '');
			foreach ($filenames as $filename) {
				file_put_contents($file, file_get_contents($filename), FILE_APPEND);
			}
		} else {
			$file = $filenames;
		}
		return trim(str_replace("\n", " ", CliCall::create('node', array($file))->execute()->stdOut));

		if (is_array($filenames)) {
			unlink($file);
		}
	}

}