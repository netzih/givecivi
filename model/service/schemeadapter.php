<?php namespace CivivipGive\Model\Service;

use CivivipGive\Model\Service\Interpreter;
use CivivipGive\Model\Give\Subscription;
use CivivipGive\Model\Civi\Recurrence;
use CivivipGive\Model\Give\Donor;

/**
 * SchemeAdapter Class
 *
 * This "adapter" hydrates arrays patterned after what Civi* needs
 * with the corresponding elements from results of Give queries.
 *
 * @copyright 	Copyright (c) 2018, Wanna Pixel, Inc.
 * @license 	https://opensource.org/licenses/gpl-license 	GNU Public License
 * 
 * @since 		1.0
 */
class SchemeAdapter {

	/**
	 * Hydrate CiviContribute-related keys with appropriate Give values. See controller
	 * classes for discussion of how returned array is consumed.
	 *
	 * @since 	1.0
	 * 
	 * @param 	Give_Payment object 	$payment 	results of one of the Donation Class query methods
	 * 
	 * @return 	array
	 */
	public static function donation(\Give_Payment $payment)
	{
		$payment_meta = $payment->payment_meta;
		$user_info = $payment->user_info;

		$subscriptions = new Subscription();
		$recurrences = new Recurrence();

		if (isset($payment_meta['subscription_id']) && $payment_meta['subscription_id'] !== '') {
			$subscription = $subscriptions->fetch($payment_meta['subscription_id']);
			if ($subscription !== 'Failed to fetch') {
				$recurrence_id = $recurrences->fetch($subscription['trxn_id']);
			}
		}
		// If form has no title, assign the form ID as it's title
		if ( empty( $payment_meta['_give_payment_form_title'] ) ) {
			$payment_meta['_give_payment_form_title'] = 'ID: ' . $payment_meta['_give_payment_form_id'];
		}
		// Extract billing address from payment meta if sync is enabled
		$billing_address = [];
		$sync_addresses = give_get_option('civivipgive_sync_addresses', 'enabled');
		if ($sync_addresses === 'enabled' && !empty($payment_meta['_give_donor_billing_address1'])) {
			$billing_address = [
				'street_address'	=> $payment_meta['_give_donor_billing_address1'] ?? '',
				'supplemental_address_1' => $payment_meta['_give_donor_billing_address2'] ?? '',
				'city'				=> $payment_meta['_give_donor_billing_city'] ?? '',
				'state_province'	=> $payment_meta['_give_donor_billing_state'] ?? '',
				'postal_code'		=> $payment_meta['_give_donor_billing_zip'] ?? '',
				'country'			=> $payment_meta['_give_donor_billing_country'] ?? '',
			];
			// Remove empty values
			$billing_address = array_filter($billing_address);
		}

		// Extract Stripe payment processor data if enabled
		$stripe_data = [];
		$stripe_link = give_get_option('civivipgive_stripe_link', 'disabled');
		$gateway = $payment_meta['_give_payment_gateway'] ?? '';

		if ($stripe_link === 'enabled' && strpos($gateway, 'stripe') !== false) {
			// GiveWP stores Stripe data in various meta fields depending on version
			// Try to get the charge ID (needed for refunds in CiviCRM)
			$charge_id = $payment_meta['_give_stripe_charge_id'] ??
			             $payment_meta['_stripe_charge_id'] ??
			             $payment_meta['_give_payment_transaction_id'] ?? '';

			// If we have a Payment Intent ID (pi_xxx) instead of Charge ID (ch_xxx),
			// retrieve the charge ID from Stripe API
			if (!empty($charge_id) && strpos($charge_id, 'pi_') === 0) {
				$actual_charge_id = self::getChargeFromPaymentIntent($charge_id, $payment_meta);
				if (!empty($actual_charge_id) && strpos($actual_charge_id, 'ch_') === 0) {
					$charge_id = $actual_charge_id;
				}
			}

			if (!empty($charge_id)) {
				$stripe_data['charge_id'] = $charge_id;
				$stripe_data['gateway'] = $gateway;
			}
		}

		// Determine transaction ID based on payment gateway
		// For Stripe: use actual Stripe charge ID for refund capability
		// For others: use Give internal tracking ID
		$give_tracking_id = 'give-' . $payment->key . '-' . $payment->ID;
		$transaction_id = $give_tracking_id;
		$invoice_id = '';

		if (!empty($stripe_data['charge_id'])) {
			// Swap: Stripe charge ID becomes trxn_id (for refunds), Give ID goes to invoice_id
			$transaction_id = $stripe_data['charge_id'];
			$invoice_id = $give_tracking_id;
		}

		return [
			// Donor related
			'donor info' => [
				'contact_type'				=> 'Individual',	// Give doesn't track this datum, yet CiviCRM requires it.
				'email' 					=> $user_info['email'] ?? '',	// CiviCRM "dedupe" rules generally make use of this
				'external_identifier'		=> 'give-' . $payment->donor_id,
				'first_name'				=> $user_info['first_name'] ?? '',
				'last_name'					=> $user_info['last_name'] ?? '',
				'source'					=> 'Give',
				'_wp_user_id'				=> $payment->user_id ?? 0,
				'_billing_address'			=> $billing_address,	// Custom field for our use
			],
			// For use by controllers
			'meta' => [
				'_give_subscription_id'		=> $payment_meta['subscription_id'] ?? '',
				'_stripe_data'				=> $stripe_data,	// Stripe processor data
				'_give_tracking_id'			=> $give_tracking_id,	// Always store Give ID for lookups
			],
			// Donation related
			'cancel_date' 				=> $payment->status_nicename === 'Refunded' ? $payment->post_modified : '',
			'contribution_recur_id'		=> $recurrence_id ?? '',
			'contribution_source' 		=> 'Give - ' . $payment_meta['_give_payment_form_title'],
			'contribution_status_id'	=> Interpreter::paymentStatus($payment->status_nicename),
			'currency' 					=> $payment_meta['currency'] ?? 'USD',
			'financial_type_id'			=> 'Donation',	// Give has no equivalent but CiviContribute requires this.
			'is_pay_later' 				=> ($payment_meta['_give_payment_gateway'] ?? '') === 'offline' ? 1 : 0,
			'is_test' 					=> (($payment_meta['_give_payment_mode'] ?? '') === 'test') ? 1 : 0,
			'payment_instrument_id'		=> Interpreter::paymentInstrument($payment_meta['_give_payment_gateway'] ?? 'manual'),
			'receive_date' 				=> $payment->date,
			'total_amount' 				=> number_format($payment_meta['_give_payment_total'], 2),
			'trxn_id' 					=> $transaction_id,	// Stripe charge ID for Stripe payments, Give tracking ID for others
			'invoice_id'				=> $invoice_id,	// Give tracking ID for Stripe payments, empty for others
			// 'contribution_id' is added by the model upon store, if action is update
			// 'contact_id' is added by the model upon store, if applicable
		];
	}
	/**
	 * Hydrate CiviContribute-related keys with appropriate Recurring Donations
	 * values. NB possible values for period and frequency can vary with payment gateway.
	 *
	 * @since 	1.0
	 * 
	 * @param 	array 	$subscription 	results of one of the Subscription Class query methods
	 * 
	 * @return 	array
	 */
	public static function subscription(array $subscription)
	{
		return [
			'amount' 				=> number_format($subscription['recurring_amount'] ?? 0, 2),
			'auto_renew' 			=> ($subscription['bill_times'] ?? '0') !== '0' ? 0 : 1,
			'financial_type_id' 	=> 'Donation',	// Give has no equivalent but CiviContribute requires this.
			'frequency_unit' 		=> $subscription['period'] ?? 'month',	// day week month year
			'frequency_interval'	=> $subscription['frequency'] ?? 1,	// 1 2 3 4 5 6 (in Give; unlimited in Civi)
			'installments' 			=> ($subscription['bill_times'] ?? '0') !== '0' ? $subscription['bill_times'] : '',	// 0 indicates perpetual
			'start_date' 			=> $subscription['created'] ?? date('Y-m-d H:i:s'),
			// For use by controllers
			'trxn_id' 				=> 'give-' . ($subscription['transaction_id'] ?? '') . '-' . ($subscription['id'] ?? ''),
		];
	}

	/**
	 * Special case: Due to unreliability of CiviCRM API, we "check our work" using
	 * e.g. Contribution@testShape. This method processes a raw CiviContribute
	 * record into testable shape, viz., the same key names, and values in the same
	 * formats and of the same types, as a processed Give donation.
	 *
	 * @since 	1.0
	 * 
	 * @param 	array 	$subscription 	results of one of the Subscription Class query methods
	 * 
	 * @return 	array
	 */
	public static function contribution(array $contribution)
	{
		$contrib = $contribution['values'][$contribution['id'] ];

		// Get payment instrument label from ID
		$payment_instrument_label = 'Credit Card'; // default
		try {
			civicrm_initialize();
			$result = civicrm_api3(
				'OptionValue',
				'get',
				[
					'option_group_id' => 'payment_instrument',
					'value' => $contrib['payment_instrument_id']
				]
			);
			if (is_array($result) && isset($result['id'])) {
				$payment_instrument_label = $result['values'][$result['id']]['label'];
			}
		} catch (\CiviCRM_API3_Exception $e) {
			Debug::log($e->getMessage() );	// unreliable
		}

		return [
			'donor info'				=> intval($contrib['contact_id']),
			'cancel_date' 				=> ($contrib['cancel_date'] === ''	// date_create('') would be present date
											? ''
											: date_format(date_create($contrib['cancel_date']), 'Y-m-d H:i:s') ),
			'contribution_recur_id'		=> $contrib['contribution_recur_id'],
			'contribution_source' 		=> (isset($contrib['contribution_source'])
											? $contrib['contribution_source']
											: $contrib['source']),
			'contribution_status_id'	=> (isset($contrib['contribution_status'])
											? $contrib['contribution_status']
											: Interpreter::contribStatus($contrib['contribution_status_id']) ),
			'currency' 					=> $contrib['currency'],
			'financial_type_id'			=> ($contrib['financial_type_id'] === '1'
											? 'Donation'
											: 'error'),
			'is_pay_later' 				=> intval($contrib['is_pay_later']),
			'is_test' 					=> intval($contrib['is_test']),
			'payment_instrument_id'		=> $payment_instrument_label,
			'receive_date' 				=> date_format(date_create($contrib['receive_date']), 'Y-m-d H:i:s'),
			'total_amount' 				=> number_format($contrib['total_amount'], 2),
			'trxn_id' 					=> $contrib['trxn_id'],
			'invoice_id'				=> $contrib['invoice_id'] ?? '',
		];
	}

	/**
	 * Get Stripe secret key from GiveWP settings.
	 *
	 * @since 	0.6.0
	 *
	 * @param 	array 	$payment_meta 	Payment metadata from GiveWP
	 * @param 	bool 	$test_mode 		Whether to get test or live key
	 *
	 * @return 	string 	Secret key or empty string if not found
	 */
	protected static function getStripeSecretKey($payment_meta, $test_mode)
	{
		// Try standard GiveWP setting names
		$possible_key_names = $test_mode
			? ['stripe_test_secret_key', 'give_stripe_test_secret_key', '_give_stripe_test_secret_key']
			: ['stripe_live_secret_key', 'give_stripe_live_secret_key', '_give_stripe_live_secret_key'];

		foreach ($possible_key_names as $key_name) {
			$secret_key = give_get_option($key_name, '');
			if (!empty($secret_key)) {
				return $secret_key;
			}
		}

		// Try account-specific settings from _give_stripe_get_all_accounts
		$account_slug = $payment_meta['_give_stripe_account_slug'] ?? give_get_option('_give_stripe_default_account', '');
		if (!empty($account_slug)) {
			$all_accounts = give_get_option('_give_stripe_get_all_accounts', []);
			if (isset($all_accounts[$account_slug]) && is_array($all_accounts[$account_slug])) {
				$account_data = $all_accounts[$account_slug];
				$key_name = $test_mode ? 'test_secret_key' : 'live_secret_key';

				if (isset($account_data[$key_name]) && !empty($account_data[$key_name])) {
					return $account_data[$key_name];
				}
			}
		}

		return '';
	}

	/**
	 * Get Charge ID from Stripe Payment Intent.
	 *
	 * When GiveWP stores a Payment Intent ID instead of a Charge ID,
	 * we need to query Stripe to get the actual Charge ID for refunds.
	 *
	 * @since 	0.6.0
	 *
	 * @param 	string 	$payment_intent_id 	Stripe Payment Intent ID (pi_xxx)
	 * @param 	array 	$payment_meta 		Payment metadata from GiveWP
	 *
	 * @return 	string|null 	Charge ID or null if not found
	 */
	protected static function getChargeFromPaymentIntent($payment_intent_id, $payment_meta)
	{
		// Check if Stripe SDK is available (GiveWP loads it)
		if (!class_exists('\Stripe\Stripe')) {
			return null;
		}

		try {
			$test_mode = ($payment_meta['_give_payment_mode'] ?? '') === 'test';
			$secret_key = self::getStripeSecretKey($payment_meta, $test_mode);

			if (empty($secret_key)) {
				return null;
			}

			// Initialize Stripe and retrieve the Payment Intent
			\Stripe\Stripe::setApiKey($secret_key);
			$intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);

			// Get the charge ID from the latest charge
			if (isset($intent->latest_charge) && !empty($intent->latest_charge)) {
				return $intent->latest_charge;
			}

			// Fallback: check charges array
			if (isset($intent->charges->data) && count($intent->charges->data) > 0) {
				return $intent->charges->data[0]->id;
			}

			return null;

		} catch (\Exception $e) {
			Debug::log('Error retrieving Charge ID from Stripe: ' . $e->getMessage());
			return null;
		}
	}
}
