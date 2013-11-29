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

namespace Foomo\Bundle\Compiler\Result;


/**
 * @link www.foomo.org
 * @license www.gnu.org/licenses/lgpl.txt
 */
class Resource
{
	const MIME_TYPE_JS  = 'application/javascript';
	const MIME_TYPE_CSS = 'text/css';
	/**
	 * @var string
	 */
	public $mimeType;
	/**
	 * absolute path to the js file
	 *
	 * @var string
	 */
	public $file;
	/**
	 * js URI to be added to a HTML document
	 *
	 * @var string
	 */
	public $link;

	/**
	 * @param string $mimeType one of self::MIME_TYPE_...
	 * @param string $file
	 * @param string $link
	 * @return Resource
	 */
	public static function create($mimeType, $file, $link)
	{
		$ret = new self;
		$ret->mimeType = $mimeType;
		$ret->file = $file;
		$ret->link = $link;
		return $ret;
	}
}