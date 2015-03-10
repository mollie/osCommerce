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

class Mollie_Helper
{
	public $protocol;
	public $host;
	public $path;

	protected static $api;

	protected static $db;

	public $statuses = array(
		"open"       => MODULE_PAYMENT_MOLLIE_OPEN_ORDER_STATUS_ID,
		"pending"    => MODULE_PAYMENT_MOLLIE_PENDING_ORDER_STATUS_ID,
		"cancelled"	 => MODULE_PAYMENT_MOLLIE_CANCELLED_ORDER_STATUS_ID,
		"expired"    => MODULE_PAYMENT_MOLLIE_EXPIRED_ORDER_STATUS_ID,
		"paid"		 => MODULE_PAYMENT_MOLLIE_PAID_ORDER_STATUS_ID,
		"paidout"	 => MODULE_PAYMENT_MOLLIE_PAID_ORDER_STATUS_ID,
		"refunded"   => MODULE_PAYMENT_MOLLIE_PAID_ORDER_STATUS_ID,
	);

	public $status_messages = array(
		"open"      => "Mollie: open",
		"pending"   => "Mollie: pending",
		"cancelled" => "Mollie: cancelled",
		"expired"   => "Mollie: expired",
		"paid"      => "Mollie: paid",
		"paidout"   => "Mollie: paid",
		"refunded"  => "Mollie: refunded",
	);

	public function __construct ()
	{
		// Protocol, host and path are used to create the return_page and webhook URLs.
		$this->protocol = isset($_SERVER['HTTPS']) && strcasecmp("off", $_SERVER['HTTPS']) !== 0 ? "https" : "http";
		$this->host     = $_SERVER['HTTP_HOST'];
		$this->path     = dirname(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $_SERVER['PHP_SELF']);

		if (isset($_GET['action']))
		{
			if (method_exists($this, $_GET['action']))
			{
				call_user_func(array($this, $_GET['action']));
			}
		}
	}

	/**
	 * Get the API statically.
	 *
	 * @return Mollie_API_Client
	 */
	public static function get_api ()
	{
		if (self::$api === NULL)
		{
			try
			{
				self::$api = new Mollie_API_Client;
				self::$api->setApiKey(MODULE_PAYMENT_MOLLIE_API_KEY);

				if (defined("PROJECT_VERSION"))
				{
					self::$api->addVersionString(PROJECT_VERSION);
				}
			}
			catch (Mollie_API_Exception $e)
			{
				// Without a connection to Mollie we cannot proceed with anything.
				die($e->getMessage());
			}
		}

		return self::$api;
	}

	/**
	 * Process a payment. Redirect customer to Mollie.
	 */
	public function pay ()
	{
		try
		{
			$order_id = $_GET['osc_order_id'];

			$payment = self::get_api()->payments->create(array(
				"amount"       => $this->get_order_total($order_id),
				"method"       => isset($_GET['method']) ? $_GET['method'] : NULL,
				"description"  => MODULE_PAYMENT_MOLLIE_PAYMENT_DESCRIPTION . " " . $order_id,
				"redirectUrl"  => $this->get_return_url($order_id),
				"metadata"     => array("order_id" => $order_id),
				"webhookUrl"   => $this->get_webhook_url($order_id),
				"issuer"       => !empty($_GET['issuer']) ? $_GET['issuer'] : NULL
			));

			$this->log($payment->id, $payment->status, $order_id);

			header("Location: " . $payment->getPaymentUrl());
		}
		catch (Mollie_API_Exception $e)
		{
			echo "API call failed: " . htmlspecialchars($e->getMessage());
		}
	}

	/**
	 * Fired when customer finishes payment, either by cancelling or completing the payment.
	 */
	public function return_page ()
	{
		$status = $this->get_order_status($_GET['order_id']);

		if ($status == "paid")
		{
			header("Location: /checkout_success.php");
		}
		else 
		{
			header("Location: /account_history_info.php?order_id=" . $_GET['order_id']);
		}
	}

	/**
	 * Fired when Mollie calls the webhook (i.e. when Mollie has a status update).
	 */
	public function webhook ()
	{
		try
		{
			$id = isset($_GET['order_id']) ? $_GET['order_id'] : 0;

			if (empty($id))
			{
				header("HTTP/1.0 404 Not Found");
				die("No order ID received");
			}

			$transaction_id = $this->get_transaction_id_from_order_id($id);
			$payment  = self::get_api()->payments->get($transaction_id);

			$this->add_order_history($id, $payment->status);

			tep_db_query("UPDATE orders SET orders_status = '" . $this->statuses[$payment->status] . "' WHERE orders_id = '" . $id . "'");

			$this->log($transaction_id, $payment->status, $id);
		}
		catch (Mollie_API_Exception $e)
		{
			echo "API call failed: " . htmlspecialchars($e->getMessage());
		}

		die("OK");
	}

	/**
	 * Generates the return URL.
	 *
	 * @param int $order_id
	 *
	 * @return string
	 */
	public function get_return_url ($order_id)
	{
		return $this->protocol . "://" . $this->host . $this->path . "/mollie.php?action=return_page&order_id=" . $order_id;
	}

	/**
	 * Generates the webhook URL.
	 *
	 * @param int $order_id
	 *
	 * @return string
	 */
	public function get_webhook_url ($order_id)
	{
		return $this->protocol . "://" . $this->host . $this->path . "/mollie.php?action=webhook&order_id=" . $order_id;
	}

	/**
	 * Adds a message to the order history table.
	 *
	 * @param int $order_id osCommerce order ID
	 * @param int $status   Mollie status
	 */
	public function add_order_history ($order_id, $status)
	{
		tep_db_query("
			INSERT INTO orders_status_history (orders_id, orders_status_id, comments,date_added)
			VALUES ('" . $order_id . "', '" . $this->statuses[$status] . "', '" . $this->status_messages[$status] . "', now())
		");
	}

	/**
	 * Get the current order status
	 * @param int $order_id The internal ID of the order
	 * @return string the found status or failed
	 */
	public function get_order_status ($order_id)
	{
		$query = tep_db_query("SELECT status FROM mollie WHERE osc_order_id = '".tep_db_input($order_id)."' and status = 'paid'");
		$row   = tep_db_fetch_array($query);

		return $row ? "paid" : "failed";
	}

	/**
	 * Get the transaction ID from an order.
	 *
	 * @param int $order_id
	 *
	 * @return int
	 */
	public function get_transaction_id_from_order_id ($order_id)
	{
		$query = tep_db_query("SELECT payment_id FROM mollie WHERE osc_order_id = '".tep_db_input($order_id)."'");
		$row   = tep_db_fetch_array($query);

		return $row ? $row['payment_id'] : 0;
	}

	/**
	 * Get the order ID for a transaction.
	 *
	 * @param int $transaction_id
	 *
	 * @return int
	 */
	public function get_order_id_from_transaction_id ($transaction_id)
	{
		$query = tep_db_query("SELECT osc_order_id FROM mollie WHERE payment_id = '".tep_db_input($transaction_id)."'");
		$row   = tep_db_fetch_array($query);

		return $row ? $row['osc_order_id'] : 0;
	}

	/**
	 * Get the total order amount in EUR.
	 *
	 * @param int $order_id
	 *
	 * @return float
	 */
	public function get_order_total ($order_id)
	{
		// Global currencies handler.
		global $currencies;

		// Current currency as a three letter string.
		global $currency;

		$total = 0;

		$query = tep_db_query("SELECT value FROM orders_total WHERE class = 'ot_total' AND orders_id = '".tep_db_input($order_id)."'");
		$row   = tep_db_fetch_array($query);

		if ($row)
		{
			$total = floatval($row['value']);
		}

		// Get the order's currency value, relative to the store's default currency value.
		$query = tep_db_query("SELECT currency, currency_value FROM orders WHERE orders_id = '".tep_db_input($order_id)."'");
		$row   = tep_db_fetch_array($query);

		// Convert the total amount to the store's default currency if possible.
		if ($row && $row['currency'] !== $currency)
		{
			$order_to_default = floatval($row['currency_value']);

			if ($order_to_default > 0)
			{
				$total *= $order_to_default;
			}
		}

		// Now convert the total amount to EUR.
		$default_to_eur = floatval($currencies->get_value("EUR"));

		if ($default_to_eur > 0)
		{
			$total *= $default_to_eur;
		}

		return $total;
	}

	/**
	 * Writes the order and/or transaction to the database.
	 *
	 * @param string $transaction_id Mollie transaction ID
	 * @param string $status         Internal status
	 * @param int    $order_id       osCommerce order ID
	 */
	public function log ($transaction_id, $status, $order_id)
	{
		// See if we should update an existing order or insert a new one.
		$query = tep_db_query("SELECT status FROM mollie WHERE payment_id = '" . tep_db_input($transaction_id) . "' AND status = 'paid'");
		$rows  = tep_db_fetch_array($query);

		if ($rows > 0)
		{
			tep_db_query("UPDATE mollie SET status = '" . $status . "' WHERE payment_id = '" . $transaction_id . "' AND osc_order_id = '" . $order_id . "'");
		}
		else
		{
			tep_db_query("INSERT INTO mollie (payment_id, status, osc_order_id) VALUES('" . $transaction_id . "', '" . $status . "', '" . $order_id . "')");
		}
	}
}
