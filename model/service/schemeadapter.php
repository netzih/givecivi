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
			if ( ! $subscription = 'Failed to fetch') {
				$recurrence_id = $recurrences->fetch($subscription['trxn_id']);
			}
		}
		// If form has no title, assign the form ID as it's title
		if ( empty( $payment_meta['_give_payment_form_title'] ) ) {
			$payment_meta['_give_payment_form_title'] = 'ID: ' . $payment_meta['_give_payment_form_id'];
		}
		return [
			// Donor related
			'donor info' => [
				'contact_type'				=> 'Individual',	// Give doesn't track this datum, yet CiviCRM requires it.
				'email' 					=> $user_info['email'],	// CiviCRM "dedupe" rules generally make use of this
				'external_identifier'		=> 'give-' . $payment->donor_id,
				'first_name'				=> $user_info['first_name'],
				'last_name'					=> $user_info['last_name'],
				'source'					=> 'Give',
				'_wp_user_id'				=> $payment->user_id,
			],
			// For use by controllers
			'meta' => [
				'_give_subscription_id'		=> (isset($payment_meta['subscription_id'])
												? $payment_meta['subscription_id']
												: ''),
			],
			// Donation related
			'cancel_date' 				=> (	// i.e., refund date
											$payment->status_nicename === 'Refunded'
											? $payment->post_modified
											: ''),
			'contribution_recur_id'		=> (isset($recurrence_id)
											? $recurrence_id
											: ''),
			'contribution_source' 		=> 'Give - ' . $payment_meta['_give_payment_form_title'],
			'contribution_status_id'	=> Interpreter::paymentStatus($payment->status_nicename),
			'currency' 					=> $payment_meta['currency'],
			'financial_type_id'			=> 'Donation',	// Give has no equivalent but CiviContribute requires this.
			'is_pay_later' 				=> ($payment_meta['_give_payment_gateway'] === 'offline'
											? 1
											: 0),
			'is_test' 					=> (isset($payment_meta['_give_payment_mode']) && $payment_meta['_give_payment_mode'] === 'test'
											? 1
											: 0),
			'payment_instrument_id'		=> 'See Give', 	// CiviCRM defaults to 'Check', which would be inaccurate
			'receive_date' 				=> $payment->date,
			'total_amount' 				=> number_format($payment_meta['_give_payment_total'], 2),
			'trxn_id' 					=> 'give-' . $payment->key . '-' . $payment->ID,	// NB key alone would not be unique
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
			'amount' 				=> number_format($subscription['recurring_amount'], 2),
			'auto_renew' 			=> ($subscription['bill_times'] !== '0'
										?: '1'),
			'financial_type_id' 	=> 'Donation',	// Give has no equivalent but CiviContribute requires this.
			'frequency_unit' 		=> $subscription['period'],	// day week month year
			'frequency_interval'	=> $subscription['frequency'],	// 1 2 3 4 5 6 (in Give; unlimited in Civi)
			'installments' 			=> ($subscription['bill_times'] !== '0'
										?: ''),	// 0 indicates perpetual
			'start_date' 			=> $subscription['created'],	//WILL WE NEED TO USE SOMETHING LIKE CARBON?
			// For use by controllers
			'trxn_id' 				=> 'give-' . $subscription['transaction_id'] . '-' . $subscription['id'],
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
		// Fetch the 'See Give' payment method's ID, since this will vary by installation.
		try {
			civicrm_initialize();
			$result = civicrm_api3(
				'OptionValue',
				'get',
				['name' => 'See Give']
			);
		} catch (\CiviCRM_API3_Exception $e) {
			Debug::log($e->getMessage() );	// unreliable
		}
		if(is_array($result) && isset($result['id']) ) {
			$payment_instrument_id = $result['values'][$result['id'] ]['value'];
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
			'payment_instrument_id'		=> ($contrib['payment_instrument_id'] == $payment_instrument_id
											? 'See Give'
											: 'error'),
			'receive_date' 				=> date_format(date_create($contrib['receive_date']), 'Y-m-d H:i:s'),
			'total_amount' 				=> number_format($contrib['total_amount'], 2),
			'trxn_id' 					=> $contrib['trxn_id'],
		];
	}
}
