<?php

/**
 * Copyright (c) 2015, Mollie B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * - Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE.
 *
 * @category    Mollie
 * @package     Mollie
 * @license     Berkeley Software Distribution License (BSD-License 2) http://www.opensource.org/licenses/bsd-license.php
 * @author      Mollie B.V. <info@mollie.nl>
 * @copyright   Mollie B.V.
 * @link        https://www.mollie.nl
 */

require_once dirname(__FILE__) . "/../../../mollie/mollie_base.php";

class Mollie_IDEAL extends Mollie_Base
{
	public function __construct ()
	{
		parent::__construct();

		if (isset($_POST['ideal_id']))
		{
			$_SESSION['ideal_id'] = $_POST['ideal_id'];
		}
	}

	public function get_method_name ()
	{
		return "IDEAL";
	}

	public function get_method ()
	{
		$method = parent::get_method();

		if (isset($_SESSION['ideal_id']))
		{
			$method .= "&issuer=".$_SESSION['ideal_id'];
		}

		return $method;
	}

	public function selection ()
	{
		$methods = array();

		try
		{
			$issuers = Mollie_Helper::get_api()->issuers->all();

			foreach ($issuers as $issuer)
			{
				if ($issuer->method == Mollie_API_Object_Method::IDEAL)
				{
					$methods[] = array(
						"id"   => $issuer->id,
						"text" => $issuer->name
					);
				}
			}
		}
		catch (Mollie_API_Exception $e)
		{
			echo __METHOD__ . " said: " . $e->getMessage();
		}

		$selection = parent::selection();

		return array_merge($selection, array(
			"fields" => array(array("field" => tep_draw_pull_down_menu("ideal_id", $methods, "", 'onchange="jQuery(\'input[name=payment][value=' . $this->get_code() . ']\').click()"'))),
		));
	}

	public function after_process_before_redirect ()
	{
		parent::after_process_before_redirect();

		if (isset($_SESSION['ideal_id']))
		{
			unset($_SESSION['ideal_id']);
		}
	}
}
