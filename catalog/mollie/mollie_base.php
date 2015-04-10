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

abstract class Mollie_Base
{
	public $code;
	public $title;
	public $description;
	public $enabled;
	public $form_action_url;

	public $keys;
	public $api;

	public function __construct ()
	{
		$this->code        = $this->get_code();
		$this->title       = $this->get_title();
		$this->description = $this->get_description();
		$this->sort_order  = $this->get_sort_order();
		$this->enabled     = TRUE;

		require_once dirname(__FILE__) . "/mollie-api-php/src/Mollie/API/Autoloader.php";
		require_once dirname(__FILE__) . "/mollie_helper.php";
	}

	/**
	 * Return uppercase string with the name of the submodule as per the Mollie API. Used as suffix for submodule specific settings.
	 *
	 * @return string
	 */
	public abstract function get_method_name ();

	public function get_method ()
	{
		return constant("Mollie_API_Object_Method::" . $this->get_method_name());
	}

	/**
	 * Unique ID of the submodule. Should match the (extensionless) file name.
	 *
	 * @return string
	 */
	public function get_code ()
	{
		return "mollie_" . strtolower($this->get_method_name());
	}

	/**
	 * Load the language file if required language constants have not yet been defined.
	 */
	protected function load_language ()
	{
		global $language;

		if (!is_string($language) || !preg_match("/^[a-z]+$/", $language))
		{
			return;
		}

		$path = "/modules/payment/" . $this->get_code() . ".php";

		if (file_exists(DIR_FS_CATALOG_LANGUAGES . $language . $path))
		{
			require_once(DIR_FS_CATALOG_LANGUAGES . $language . $path);
		}

		require_once(DIR_FS_CATALOG_LANGUAGES . "english" . $path);
	}

	/**
	 * Title of the submodule as shown in the admin panel's payment module list.
	 *
	 * @return string
	 */
	public function get_title ()
	{
		return "Mollie - " . $this->get_module_title();
	}

	/**
	 * Title of the submodule.
	 * @return string
	 */
	public function get_module_title ()
	{
		if (!defined("MODULE_PAYMENT_MOLLIE_TEXT_TITLE_" . $this->get_method_name()))
		{
			$this->load_language();
		}

		return constant("MODULE_PAYMENT_MOLLIE_TEXT_TITLE_" . $this->get_method_name());
	}

	/**
	 * Description of the submodule.
	 *
	 * @return string
	 */
	public function get_description ()
	{
		if (!defined("MODULE_PAYMENT_MOLLIE_TEXT_DESCRIPTION_" . $this->get_method_name()))
		{
			$this->load_language();
		}

		return constant("MODULE_PAYMENT_MOLLIE_TEXT_DESCRIPTION_" . $this->get_method_name());
	}

	/**
	 * Output payment method code, title and optionally input fields. Called by checkout_payment.php.
	 *
	 * @return array
	 */
	public function selection ()
	{
		return array(
			"id"     => $this->get_code(),
			"module" => $this->get_module_title(),
		);
	}

	/**
	 * Return the order number of the submodule.
	 *
	 * @return string
	 */
	public function get_sort_order ()
	{
		$key = "MODULE_PAYMENT_MOLLIE_SORT_ORDER_" . $this->get_method_name();

		if (defined($key))
		{
			return constant($key);
		}

		return 0;
	}

	/**
	 * Enables javascript validation on order page.
	 *
	 * @return bool
	 */
	public function javascript_validation ()
	{
		return TRUE;
	}

	/**
	 * Enable method on order confirmation.
	 *
	 * @return bool
	 */
	public function pre_confirmation_check ()
	{
		return TRUE;
	}

	/**
	 * Gets the title and/or settings for the payment method.
	 *
	 * @return array
	 */
	public function confirmation ()
	{
		return array("title" => $this->get_module_title());
	}

	/**
	 * Process button.
	 *
	 * @return string
	 */
	public function process_button ()
	{
		return "";
	}

	/**
	 * Run the module before order processing.
	 *
	 * @return bool
	 */
	public function before_process ()
	{
		return FALSE;
	}

	/**
	 * After order processing (in checkout_process.php), we cleanout the cart and send the customer off to Mollie. Throws an exception if anything went wrong.
	 */
	public function after_process ()
	{
		global $customer_id;

		$query_order = tep_db_query("SELECT orders_id FROM " . TABLE_ORDERS . " WHERE customers_id = " . intval($customer_id) . " ORDER BY orders_id DESC LIMIT 1");
		$order       = tep_db_fetch_array($query_order);

		if (!empty($order))
		{
			// Get method URL parameter from session (before we clean up the session!).
			$method = $this->get_method();

			// Allow module to do something.
			$this->after_process_before_redirect();

			// Redirect to checkout.
			tep_redirect("mollie/mollie.php?mollie_action=pay&osc_order_id=" . $order['orders_id'] . "&method=" . $method);
		}

		// Throw an exception. If we don't, our customer will get redirected to checkout_success.php by osCommerce!
		throw new Exception("Could not find order. Unable to redirect to Mollie.");
		tep_exit();
	}

	/**
	 * Runs after cleaning the cart.
	 */
	public function after_process_before_redirect ()
	{}

	/**
	 * Show errors and backtrace.
	 */
	public function output_error ()
	{
		debug_print_backtrace();
		exit;
	}

	/**
	 * Determines whether a module is installed. Must return an integer greater than 0 to pass osCommerce's check.
	 *
	 * @return int
	 */
	public function check ()
	{
		$installed_modules = $this->get_installed_modules();

		if (in_array($this->get_code() . ".php", $installed_modules))
		{
			return 1;
		}

		return 0;
	}

	/**
	 * Runs when user installs a submodule. Adds Mollie configuration presets. Creates the Mollie table if needed. Adds our module to the global config table to support osCommerce 2.2. This is done automatically in 2.3+,
	 * but it doesn't hurt to do this now either way.
	 */
	public function install ()
	{
		// Install basic config if needed.
		$this->add_configuration("Mollie API key",      "MODULE_PAYMENT_MOLLIE_API_KEY",             "",        "Starts with live_ or test_");
		$this->add_configuration("Payment description", "MODULE_PAYMENT_MOLLIE_PAYMENT_DESCRIPTION", "Order #", "Prefix order number with a description");

		$this->add_configuration("Order status: open",      "MODULE_PAYMENT_MOLLIE_OPEN_ORDER_STATUS_ID",      "0", "The order has just been created",                                 "tep_cfg_pull_down_order_statuses(", "tep_get_order_status_name");
		$this->add_configuration("Order status: pending",   "MODULE_PAYMENT_MOLLIE_PENDING_ORDER_STATUS_ID",   "0", "The payment is pending",                                          "tep_cfg_pull_down_order_statuses(", "tep_get_order_status_name");
		$this->add_configuration("Order status: cancelled", "MODULE_PAYMENT_MOLLIE_CANCELLED_ORDER_STATUS_ID", "0", "The customer cancelled the payment",                              "tep_cfg_pull_down_order_statuses(", "tep_get_order_status_name");
		$this->add_configuration("Order status: expired",   "MODULE_PAYMENT_MOLLIE_EXPIRED_ORDER_STATUS_ID",   "0", "The payment expires after 15 minutes, except for bank transfers", "tep_cfg_pull_down_order_statuses(", "tep_get_order_status_name");
		$this->add_configuration("Order status: paid",      "MODULE_PAYMENT_MOLLIE_PAID_ORDER_STATUS_ID",      "0", "The payment has been completed",                                  "tep_cfg_pull_down_order_statuses(", "tep_get_order_status_name");

		// Install submodule specific options.
		$this->add_configuration("Sort order", "MODULE_PAYMENT_MOLLIE_SORT_ORDER_" . $this->get_method_name(), "1", "Display order of Mollie payment methods during checkout (lowest number first)");

		// Create Mollie payments table.
		tep_db_query("CREATE TABLE IF NOT EXISTS " . Mollie_Helper::DB_PAYMENTS_TABLE . " (
			payment_id varchar(255) NOT NULL,
			status varchar(30) NOT NULL,
			osc_order_id int(11) NOT NULL
		)");

		// Add our module to the list of installed modules if needed.
		$installed_modules = $this->get_installed_modules();

		if (!in_array($this->get_code() . ".php", $installed_modules))
		{
			$installed_modules[] = $this->get_code() . ".php";

			$this->set_installed_modules($installed_modules);
		}
	}

	/**
	 * Runs when user uninstalls a submodule. Removes our module from the global config table to support osCommerce 2.2. This is done automatically in 2.3+, but it doesn't hurt to do this now either way. The method does
	 * not remove Mollie's custom configurations at this point, making sure they are saved if the module is deactivated accidentally.
	 */
	public function remove ()
	{
		$installed_modules = $this->get_installed_modules();

		$updated_modules = array();

		foreach ($installed_modules as $module)
		{
			if ($module !== $this->get_code() . ".php")
			{
				$updated_modules[] = $module;
			}
		}

		$this->set_installed_modules($updated_modules);
	}

	/**
	 * Get a list of the installed payment methods (as filenames).
	 *
	 * @return array
	 */
	protected function get_installed_modules ()
	{
		$check_query = tep_db_query("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_INSTALLED'");
		$check       = tep_db_fetch_array($check_query);

		if (isset($check['configuration_value']))
		{
			return explode(";", $check['configuration_value']);
		}

		return array();
	}

	protected function set_installed_modules ($installed_modules)
	{
		tep_db_query("UPDATE " . TABLE_CONFIGURATION . " SET configuration_value = '" . implode(";", $installed_modules) . "' WHERE configuration_key = 'MODULE_PAYMENT_INSTALLED'");
	}

	/**
	 * Get all configuration keys.
	 */
	public function keys ()
	{
		if ($this->keys == NULL)
		{
			$this->keys = array(
				"MODULE_PAYMENT_MOLLIE_API_KEY",
				"MODULE_PAYMENT_MOLLIE_PENDING_ORDER_STATUS_ID",
				"MODULE_PAYMENT_MOLLIE_OPEN_ORDER_STATUS_ID",
				"MODULE_PAYMENT_MOLLIE_CANCELLED_ORDER_STATUS_ID",
				"MODULE_PAYMENT_MOLLIE_EXPIRED_ORDER_STATUS_ID",
				"MODULE_PAYMENT_MOLLIE_PAID_ORDER_STATUS_ID",
				"MODULE_PAYMENT_MOLLIE_PAYMENT_DESCRIPTION",
				"MODULE_PAYMENT_MOLLIE_SORT_ORDER_" . $this->get_method_name(),
			);
		}

		return $this->keys;
	}

	/**
	 * Add a setting to the database if it does not exist yet.
	 *
	 * @param string $title         Title of the option, as used in the admin panel
	 * @param string $key           Unique option key, uppercase by convention
	 * @param string $default_value Default value to use if the admin did not customize the setting yet
	 * @param string $description   Description shown below the title in the admin panel
	 * @param string $set_function  Runs before updating the setting value - should end with an opening parenthesis: eval('$set_value = '.$set_function.'"'.$submitted_value.'");')
	 * @param string $use_function  Runs when showing the setting value: $output_value = tep_call_function($use_function, $stored_value)
	 */
	protected function add_configuration ($title, $key, $default_value, $description, $set_function = NULL, $use_function = NULL)
	{
		// Do nothing if the key is already defined.
		$query = tep_db_query("SELECT COUNT(*) AS c FROM `" . TABLE_CONFIGURATION . "` WHERE `configuration_key` = '".$key."'");
		$array = tep_db_fetch_array($query);

		if (!empty($array['c']))
		{
			return;
		}

		$sql = "INSERT INTO `" . TABLE_CONFIGURATION . "`
			(`configuration_title`, `configuration_key`, `configuration_value`, `configuration_description`, `configuration_group_id`, `sort_order`, `date_added`, `set_function`, `use_function`)
			VALUES ('%s', '%s', '%s', '%s', '6', '100', now(), '%s', '%s')";

		tep_db_query(sprintf($sql, $title, $key, $default_value, $description, $set_function, $use_function));
	}
}
