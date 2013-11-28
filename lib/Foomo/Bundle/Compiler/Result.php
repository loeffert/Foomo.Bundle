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

namespace Foomo\Bundle\Compiler;


/**
 * @link www.foomo.org
 * @license www.gnu.org/licenses/lgpl.txt
 */
class Result
{
	const MIME_TYPE_JS  = 'application/javascript';
	const MIME_TYPE_CSS = 'text/css';
	/**
	 * @var string
	 */
	public $mimeType;
	/**
	 * absolute paths to the js files
	 *
	 * typically many in debug and few in non debug
	 *
	 * @var string[]
	 */
	public $files = array();
	/**
	 * js URIs to be added to a HTML document
	 *
	 * typically many in debug and few in non debug
	 *
	 * @var string[]
	 */
	public $links = array();
}