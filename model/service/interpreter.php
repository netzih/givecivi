<?php namespace CivivipGive\Model\Service;

/**
 * Interpreter Class
 *
 * This service acts as a dictionary to track equivalent
 * vocabulary in Civi* vs. in Give / Give Recurring.
 *
 * @copyright 	Copyright (c) 2018, Wanna Pixel, Inc.
 * @license 	https://opensource.org/licenses/gpl-license 	GNU Public License
 * 
 * @since 		1.0
 */
class Interpreter {

	/**
	 * Provide CiviContribute equivalents to Give statuses.
	 *
	 * @since 	1.0
	 * 
	 * @param 	string 	$status 	e.g., Pending or Completed
	 * 
	 * @return 	array
	 */
	public static function paymentStatus($status)
	{
		$dictionary = [
			'Abandoned' => 'Cancelled',
			'Cancelled' => 'Cancelled',
			'Complete' => 'Completed',
			'Failed' => 'Failed',
			'Pending' => 'Pending',
			'Pre-Approved' => 'In Progress',
			'Processing' => 'In Progress',
			'Refunded' => 'Refunded',
			'Revoked' => 'Cancelled',
			'Renewal' => 'Completed',	// NB
		];

		return $dictionary[$status];
	}

	/**
	 * Convert CiviContribute status IDs to CiviContribute statuses.
	 *
	 * @since 	1.0
	 * 
	 * @param 	string 	$status_id
	 * 
	 * @return 	array
	 */
	public static function contribStatus($status_id)
	{
		$dictionary = [
			'1' => 'Completed',
			'2' => 'Pending',
			'3' => 'Cancelled',
			'4' => 'Failed',
			'5' => 'In Progress',
			'7' => 'Refunded',
		];

		return $dictionary[$status_id];
	}

	/**
	 * Provide CiviContribute equivalents to Give currency terms.
	 *
	 * @since 	1.0
	 *
	 * @param 	string 	$region 	e.g., USD or JPY (yen)
	 *
	 * @return 	array
	 */
	public static function currency($region)
	{
		// Currently not in use, as both systems seem to adhere to
		// standard abbreviations.
	}

	/**
	 * Map GiveWP payment gateways to CiviCRM payment instruments.
	 *
	 * @since 	0.4.0
	 *
	 * @param 	string 	$gateway 	GiveWP payment gateway (e.g., 'stripe', 'paypal', 'offline')
	 *
	 * @return 	string 	CiviCRM payment instrument name
	 */
	public static function paymentInstrument($gateway)
	{
		// Common GiveWP gateway to CiviCRM payment instrument mappings
		$dictionary = [
			'stripe' => 'Credit Card',
			'stripe_ach' => 'EFT',
			'stripe_checkout' => 'Credit Card',
			'stripe_apple_pay' => 'Credit Card',
			'stripe_google_pay' => 'Credit Card',
			'paypal' => 'Credit Card',
			'paypal_commerce' => 'Credit Card',
			'paypal_standard' => 'Credit Card',
			'paypal_express' => 'Credit Card',
			'offline' => 'Check',
			'manual' => 'Cash',
			'authorize' => 'Credit Card',
			'authorize_net' => 'Credit Card',
			'square' => 'Credit Card',
			'braintree' => 'Credit Card',
			'razorpay' => 'Credit Card',
			'paytm' => 'Credit Card',
			'gocardless' => 'EFT',
			'mollie' => 'Credit Card',
			'2checkout' => 'Credit Card',
		];

		// Return mapped value or default to 'Credit Card' if gateway not recognized
		return isset($dictionary[$gateway]) ? $dictionary[$gateway] : 'Credit Card';
	}
}
