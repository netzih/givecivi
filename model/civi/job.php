<?php namespace CivivipGive\Model\Civi;

use CivivipGive\Model\Service\Debug;

if ( ! defined('ABSPATH') ) { exit; }

/**
 * Job Class
 *
 * This model provides CiviCRM scheduled job functionality, including
 * converting Stripe Payment Intent IDs to Charge IDs for refund support.
 *
 * @copyright 	Copyright (c) 2018, Wanna Pixel, Inc.
 * @license 	https://opensource.org/licenses/gpl-license 	GNU Public License
 *
 * @since 		0.6.0
 */
class Job {

	/**
	 * Convert Stripe Payment Intent IDs to Charge IDs in contributions.
	 *
	 * This scheduled job finds contributions with Payment Intent IDs (pi_xxx)
	 * as their trxn_id and converts them to Charge IDs (ch_xxx) using the
	 * Stripe API. This enables refund functionality from CiviCRM.
	 *
	 * @since 	0.6.0
	 *
	 * @return 	array 	Job result with counts of processed/updated contributions
	 */
	public static function convertStripePaymentIntents()
	{
		$results = [
			'is_error' => 0,
			'values' => [],
			'processed' => 0,
			'updated' => 0,
			'failed' => 0,
		];

		try {
			civicrm_initialize();

			// Get Stripe payment processor
			$processor_id = PaymentProcessor::getStripeProcessorId();
			if ($processor_id === null) {
				$results['is_error'] = 1;
				$results['error_message'] = 'No active Stripe payment processor found in CiviCRM';
				return $results;
			}

			// Get payment processor details including API keys
			$processor = civicrm_api3('PaymentProcessor', 'get', [
				'id' => $processor_id,
				'sequential' => 1,
			]);

			if (empty($processor['values'][0])) {
				$results['is_error'] = 1;
				$results['error_message'] = 'Could not retrieve Stripe payment processor details';
				return $results;
			}

			$processor_data = $processor['values'][0];

			// Get the appropriate API key (live vs test)
			$is_test = !empty($processor_data['is_test']);
			$api_key = $is_test
				? ($processor_data['password'] ?? '')
				: ($processor_data['password'] ?? '');

			if (empty($api_key)) {
				$results['is_error'] = 1;
				$results['error_message'] = 'Stripe API key not configured in payment processor';
				return $results;
			}

			// Check if Stripe SDK is available
			if (!class_exists('\Stripe\Stripe')) {
				$results['is_error'] = 1;
				$results['error_message'] = 'Stripe SDK not available';
				return $results;
			}

			// Find contributions with Payment Intent IDs
			$contributions = civicrm_api3('Contribution', 'get', [
				'payment_processor_id' => $processor_id,
				'sequential' => 1,
				'options' => ['limit' => 100], // Process 100 at a time
			]);

			foreach ($contributions['values'] as $contribution) {
				$trxn_id = $contribution['trxn_id'] ?? '';

				// Skip if not a Payment Intent ID
				if (empty($trxn_id) || strpos($trxn_id, 'pi_') !== 0) {
					continue;
				}

				$results['processed']++;

				try {
					// Initialize Stripe with processor's API key
					\Stripe\Stripe::setApiKey($api_key);

					// Retrieve the Payment Intent
					$intent = \Stripe\PaymentIntent::retrieve($trxn_id);

					// Get the charge ID
					$charge_id = null;
					if (isset($intent->latest_charge) && !empty($intent->latest_charge)) {
						$charge_id = $intent->latest_charge;
					} elseif (isset($intent->charges->data) && count($intent->charges->data) > 0) {
						$charge_id = $intent->charges->data[0]->id;
					}

					if (!empty($charge_id) && strpos($charge_id, 'ch_') === 0) {
						// Update contribution with Charge ID
						civicrm_api3('Contribution', 'create', [
							'id' => $contribution['id'],
							'trxn_id' => $charge_id,
						]);
						$results['updated']++;
					} else {
						$results['failed']++;
					}

				} catch (\Exception $e) {
					Debug::log("Error converting contribution {$contribution['id']}: " . $e->getMessage());
					$results['failed']++;
				}
			}

			$results['values'][] = "Processed {$results['processed']} Payment Intents, updated {$results['updated']}, failed {$results['failed']}";

		} catch (\CiviCRM_API3_Exception $e) {
			$results['is_error'] = 1;
			$results['error_message'] = $e->getMessage();
			Debug::log('Job error: ' . $e->getMessage());
		}

		return $results;
	}

	/**
	 * Register the scheduled job with CiviCRM.
	 * This should be called during plugin activation.
	 *
	 * @since 	0.6.0
	 */
	public static function registerScheduledJob()
	{
		try {
			civicrm_initialize();

			// Check if job already exists
			$existing = civicrm_api3('Job', 'get', [
				'api_entity' => 'CivivipGive',
				'api_action' => 'convertStripePaymentIntents',
				'sequential' => 1,
			]);

			if ($existing['count'] > 0) {
				return;
			}

			// Create the scheduled job
			civicrm_api3('Job', 'create', [
				'run_frequency' => 'Hourly',
				'name' => 'GiveCivi: Convert Stripe Payment Intents to Charge IDs',
				'description' => 'Converts Stripe Payment Intent IDs to Charge IDs for refund support',
				'api_entity' => 'CivivipGive',
				'api_action' => 'convertStripePaymentIntents',
				'is_active' => 1,
			]);

		} catch (\CiviCRM_API3_Exception $e) {
			// Silently fail - job registration is optional
		}
	}
}
