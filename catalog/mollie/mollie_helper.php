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
	const VERSION = "1.2";

	const DB_PAYMENTS_TABLE = "mollie";

	public $protocol;
	public $host;
	public $path;

	protected static $api;

	protected static $db;

	protected static $STATUSES = array(
		"open"       => MODULE_PAYMENT_MOLLIE_OPEN_ORDER_STATUS_ID,
		"pending"    => MODULE_PAYMENT_MOLLIE_PENDING_ORDER_STATUS_ID,
		"cancelled"	 => MODULE_PAYMENT_MOLLIE_CANCELLED_ORDER_STATUS_ID,
		"expired"    => MODULE_PAYMENT_MOLLIE_EXPIRED_ORDER_STATUS_ID,
		"paid"		 => MODULE_PAYMENT_MOLLIE_PAID_ORDER_STATUS_ID,
		"paidout"	 => MODULE_PAYMENT_MOLLIE_PAID_ORDER_STATUS_ID,
		"refunded"   => MODULE_PAYMENT_MOLLIE_PAID_ORDER_STATUS_ID,
	);

	protected static $STATUS_NOTIFY = array(
		"open"       => MODULE_PAYMENT_MOLLIE_OPEN_ORDER_STATUS_NOTIFY,
		"pending"    => MODULE_PAYMENT_MOLLIE_PENDING_ORDER_STATUS_NOTIFY,
		"cancelled"	 => MODULE_PAYMENT_MOLLIE_CANCELLED_ORDER_STATUS_NOTIFY,
		"expired"    => MODULE_PAYMENT_MOLLIE_EXPIRED_ORDER_STATUS_NOTIFY,
		"paid"		 => MODULE_PAYMENT_MOLLIE_PAID_ORDER_STATUS_NOTIFY,
		"paidout"	 => MODULE_PAYMENT_MOLLIE_PAID_ORDER_STATUS_NOTIFY,
		"refunded"   => MODULE_PAYMENT_MOLLIE_PAID_ORDER_STATUS_NOTIFY,
	);

	protected static $STATUS_MESSAGES = array(
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

		if (isset($_GET['mollie_action']))
		{
			// Call the action, if an $action_action method exists. Prevents exposing private methods. Note we should NEVER use $_GET['action'], as there's an osCommerce setting that forces sessions for GET actions. Our
			// API does not accept sessions for webhook calls.
			if (method_exists($this, $_GET['mollie_action'] . "_action"))
			{
				call_user_func(array($this, $_GET['mollie_action'] . "_action"));
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

				self::$api->addVersionString("MollieosCommerce/" . self::VERSION);
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
	public function pay_action ()
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
	public function return_page_action ()
	{
		$status = $this->get_order_status($_GET['order_id']);

		if ($status == "paid")
		{
			// Cleanup cart and session.
			global $cart;
			$cart->reset(TRUE);

			tep_session_unregister("sendto");
			tep_session_unregister("billto");
			tep_session_unregister("shipping");
			tep_session_unregister("payment");
			tep_session_unregister("comments");

			// Show success page.
			header("Location: /checkout_success.php");
		}
		else 
		{
			// Send back to cart.
			header("Location: /shopping_cart.php");
		}
	}

	/**
	 * Fired when Mollie calls the webhook (i.e. when Mollie has a status update).
	 */
	public function webhook_action ()
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

			$this->update_status($id, $payment->status);
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
		return $this->protocol . "://" . $this->host . $this->path . "/mollie.php?mollie_action=return_page&order_id=" . $order_id;
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
		return $this->protocol . "://" . $this->host . $this->path . "/mollie.php?mollie_action=webhook&order_id=" . $order_id;
	}

	/**
	 * Map an API status to an osCommerce status. Uses static::$STATUSES, falls back to default osCommerce statuses.
	 *
	 * @param $status_name
	 *
	 * @return int|NULL
	 */
	protected static function get_status_id ($status_name)
	{
		if (isset(static::$STATUSES[$status_name]) && static::$STATUSES[$status_name])
		{
			return static::$STATUSES[$status_name];
		}

		if (in_array($status_name, array("open", "pending")))
		{
			$native_status_name = "Pending";
		}
		elseif (in_array($status_name, array("paid", "paidout", "refunded")))
		{
			$native_status_name = "Processing";
		}
		else
		{
			return NULL;
		}

		$status_query = tep_db_query("SELECT orders_status_id FROM " . TABLE_ORDERS_STATUS . " WHERE orders_status_name = '" . $native_status_name . "'");
		$status       = tep_db_fetch_array($status_query);

		return intval($status['orders_status_id']);
	}

	/**
	 * Adds a message to the order history table.
	 *
	 * @param int $order_id osCommerce order ID
	 * @param int $status   Mollie status
	 * @param int $customer_notified If customer is notified by e-mail
	 */
	public function add_order_history ($order_id, $status, $customer_notified = 0)
	{
		tep_db_query("
			INSERT INTO orders_status_history (orders_id, orders_status_id, comments, date_added, customer_notified)
			VALUES ('" . $order_id . "', '" . static::get_status_id($status) . "', '" . static::$STATUS_MESSAGES[$status] . "', now(), ".($customer_notified >= 1 ? 1 : 0).")
		");
	}

	/**
	 * Get the current order status
	 * @param int $order_id The internal ID of the order
	 * @return string the found status or failed
	 */
	public function get_order_status ($order_id)
	{
		$query = tep_db_query("SELECT status FROM " . self::DB_PAYMENTS_TABLE . " WHERE osc_order_id = '" . tep_db_input($order_id) . "' AND status = 'paid'");
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
		$query = tep_db_query("SELECT payment_id FROM " . self::DB_PAYMENTS_TABLE . " WHERE osc_order_id = '".tep_db_input($order_id)."'");
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
		$query = tep_db_query("SELECT osc_order_id FROM " . self::DB_PAYMENTS_TABLE . " WHERE payment_id = '".tep_db_input($transaction_id)."'");
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
		$query = tep_db_query("SELECT status FROM " . self::DB_PAYMENTS_TABLE . " WHERE payment_id = '" . tep_db_input($transaction_id) . "' AND status = 'paid'");
		$rows  = tep_db_fetch_array($query);

		if ($rows > 0)
		{
			tep_db_query("UPDATE " . self::DB_PAYMENTS_TABLE . " SET status = '" . $status . "' WHERE payment_id = '" . $transaction_id . "' AND osc_order_id = '" . $order_id . "'");
		}
		else
		{
			tep_db_query("INSERT INTO " . self::DB_PAYMENTS_TABLE . " (payment_id, status, osc_order_id) VALUES('" . $transaction_id . "', '" . $status . "', '" . $order_id . "')");
		}
	}

	/**
	 * Updates the order status and sends emails if enabled
	 *
	 * @param int $order_id 		 Internal order id
	 * @param string $payment_status Mollie payment status
	 */
	public function update_status($order_id, $payment_status)
	{
		$new_status_id = static::get_status_id($payment_status);

		$order_updated = false;
		$check_status_query = tep_db_query("select customers_name, customers_email_address, orders_status, date_purchased from " . TABLE_ORDERS . " where orders_id = '" . (int)$order_id . "'");
		$check_status = tep_db_fetch_array($check_status_query);
		$customer_notified = '0';

		if ( $new_status_id && $check_status['orders_status'] != $new_status_id ) 
		{
			if ( $this->get_status_notify($payment_status) ) 
			{
				require_once dirname(__FILE__) . "/../admin/includes/languages/english/orders.php";
				require_once dirname(__FILE__) . "/../admin/includes/filenames.php";
				$internal_status = $this->get_internal_status_name_from_id($new_status_id);
				$email = STORE_NAME . "\n" . EMAIL_SEPARATOR . "\n" . EMAIL_TEXT_ORDER_NUMBER . ' ' . $order_id . "\n" . EMAIL_TEXT_INVOICE_URL . ' ' . tep_href_link(FILENAME_CATALOG_ACCOUNT_HISTORY_INFO, 'order_id=' . $order_id, 'SSL') . "\n" . EMAIL_TEXT_DATE_ORDERED . ' ' . tep_date_long($check_status['date_purchased']) . "\n\n" . sprintf(EMAIL_TEXT_STATUS_UPDATE, $internal_status);
				tep_mail($check_status['customers_name'], $check_status['customers_email_address'], EMAIL_TEXT_SUBJECT, $email, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
				$customer_notified = '1';
			}

			// Only update order status when order_status is different then the saved order_status
			tep_db_query("UPDATE orders SET orders_status = '" . $new_status_id . "', last_modified = now() WHERE orders_id = " . intval($order_id));
			$this->add_order_history($order_id, $payment_status, $customer_notified);
		}
	}

	/**
	 * Retrieve notify setting attached to status
	 *
	 * @param $status_name
	 * @return bool|true
	 */
	protected static function get_status_notify ($status_name)
	{
		if (isset(static::$STATUS_NOTIFY[$status_name]) && static::$STATUS_NOTIFY[$status_name])
		{
			return static::$STATUS_NOTIFY[$status_name] == "True";
		}

		// fallback, default: send notification Only when status is 'Paid'
		return $status_name == "paid";
	}

	/**
	 * Get osCommerce internal status name from id
	 *
	 * @param int $id
	 * @return string osCommerce status name
	 */
	protected static function get_internal_status_name_from_id ($id) 
	{
		$query = tep_db_query("SELECT orders_status_name FROM ". TABLE_ORDERS_STATUS . " WHERE orders_status_id = ". (int) $id);
		$status = tep_db_fetch_array($query);
		return $status['orders_status_name'];
	}
}
