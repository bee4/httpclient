<?php

/**
 * This file is part of the bee4/httpclient package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Bee4 2014
 * @author	Stephane HULARD <s.hulard@chstudio.fr>
 * @package Bee4\Transport\Message\Request\Http
 */

namespace Bee4\Transport\Message\Request\Http;

use Bee4\Transport\Message\WithBodyTrait;

/**
 * HTTP DELETE Request object
 * @package Bee4\Transport\Message\Request\Http
 */
class Delete extends AbstractHttpRequest
{
	use WithBodyTrait;

	protected function prepare() {
		$this->options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
		$this->options[CURLOPT_POSTFIELDS] = $this->getBody();
	}
}