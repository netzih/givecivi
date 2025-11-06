<?php namespace CivivipGive\Model\Civi;

use CivivipGive\Control\Service\Fix;
use CivivipGive\Model\Service\Debug;

if ( ! defined('ABSPATH') ) { exit; }

/**
 * Contribution Class
 *
 * This model primarily stores Give donation (payment) data into CiviContribute as
 * Contribution records, using the CiviCRM PHP API; due to the unreliability of
 * the API, various checks are implemented.
 * 
 * @copyright 	Copyright (c) 2018, Wanna Pixel, Inc.
 * @license 	https://opensource.org/licenses/gpl-license 	GNU Public License
 * 
 * @since 		1.0
 */
class Contribution {
	/**
	 * Attempt to create / update a CiviContribute payment record. (With the present
	 * version of the API, updates are effected by "create". If no ID is passed, or
	 * if the ID is not matched by any existing record's, that is when the API actually
	 * creates.) NB that the summum bonum of the entire workflow is the method below,
	 * viz.: committing every last Give donation to CiviContribute -- and so that when
	 * this results in redundancy of CiviCRM Contact information, we count this as a
	 * necessary cost.
	 *
	 * This method can return:
	 * 		1. An already-existing and double-checked record's ID
	 * 		2a. A newly-created and double-checked record's ID
	 * 		2b. An updated and double-checked record's ID
	 * 		3. A message indicating failure to pass checks
	 * 		4. A general failure message
	 *
	 * @since 	1.0
	 *
	 * @param 	array 	$donation 			a processed Give donation
	 * @param 	int 	$contact_id			the CiviCRM Contact ID of the donor
	 * 
	 * @return 	int|string
	 */
	public function store(array $donation, $contact_id)
	{
		$result_exists = $this->fetch($donation['trxn_id']);

		// Extract Stripe data from meta before we check anything else
		$stripe_data = $donation['meta']['_stripe_data'] ?? [];

		if ($result_exists !== 'Failed to fetch') {
			$result_passes = Fix::testShape($donation, $contact_id, $result_exists);

			// If contribution exists and passes checks, update it with Stripe data if available
			if ($result_passes) {
				// Check if we need to add Stripe processor data to existing contribution
				if (!empty($stripe_data['charge_id'])) {
					$processor_id = PaymentProcessor::getStripeProcessorId();
					if ($processor_id !== null) {
						// Update existing contribution with Stripe processor data
						try {
							civicrm_initialize();
							$update_result = civicrm_api3(
								'Contribution',
								'create',
								[
									'id' => $result_exists['id'],
									'payment_processor_id' => $processor_id,
									'invoice_id' => $stripe_data['charge_id'],
								]
							);
							Debug::log("Updated existing contribution {$result_exists['id']} with Stripe processor ID {$processor_id} and charge {$stripe_data['charge_id']}");
						} catch (\CiviCRM_API3_Exception $e) {
							Debug::log('Error updating existing contribution with Stripe data: ' . $e->getMessage());
						}
					}
				}
				return $result_exists['id'];
			}
			$addendum = ['contribution_id' => $result_exists['id'] ];
		}

		$addendum = (isset($addendum)
					? $addendum + ['contact_id' => $contact_id]
					: ['contact_id' => $contact_id]);

		// Add Stripe payment processor if available for new/updated contributions
		if (!empty($stripe_data['charge_id'])) {
			$processor_id = PaymentProcessor::getStripeProcessorId();
			if ($processor_id !== null) {
				$addendum['payment_processor_id'] = $processor_id;
				// Store Stripe charge ID in invoice_id (keeping trxn_id for our internal tracking)
				$addendum['invoice_id'] = $stripe_data['charge_id'];
				Debug::log("Linking Stripe payment processor ID {$processor_id} with charge {$stripe_data['charge_id']}");
			} else {
				Debug::log('Stripe integration enabled but no Stripe payment processor found in CiviCRM');
			}
		}

		unset($donation['donor info']);
		unset($donation['meta']);
		$result = null;
		try {
			civicrm_initialize();
			$result = civicrm_api3(
				'Contribution',
				'create',
				$donation + $addendum
			);
		} catch (\CiviCRM_API3_Exception $e) {
			Debug::log($e->getMessage() );	// unreliable
		}

		$result_passes_redux = false;
		if(is_array($result) && isset($result['id']) ) {
			$result_passes_redux = Fix::testShape($donation, $contact_id, $result);
		}
		if ($result_passes_redux) {
			return $result['id'];
		}

		if ( ! $result_passes_redux && is_array($result) && isset($result['id'])) {
			return "Failed to pass {$result['id']}"; Debug::err_log('Give CiviCRM failed to pass:' . var_export($result, true) );
		}

		return 'Failed to store'; Debug::err_log('Give CiviCRM failed to store:' . var_export($donation, true) );
	}

	/**
	 * Retrieve a CiviContribute record by its transaction ID (based on
	 * Give: key), for use in $this->store.
	 *
	 * @since 	1.0
	 *
	 * @param 	string 	$key 	a Give donation key (CiviContribute: trxn_id)
	 * 
	 * @return 	array|string
	 */
	protected function fetch($key)
	{
		$result = null;
		try {
			civicrm_initialize();
			$result = civicrm_api3(
				'Contribution',
				'get',
				['trxn_id' => $key]
			);
		} catch (\CiviCRM_API3_Exception $e) {
			Debug::log($e->getMessage() );	// unreliable
		}

		if(is_array($result) && isset($result['id']) ) {
			return $result;
		}

		// Try test-mode records. In CiviCRM, there is no way to search for both
		// live and test records together.
		$test_result = null;
		try {
			civicrm_initialize();
			$test_result = civicrm_api3(
				'Contribution',
				'get',
				['trxn_id' => $key, 'contribution_test' => 1]
			);
		} catch (\CiviCRM_API3_Exception $e) {
			Debug::log($e->getMessage() );	// unreliable
		}

		if(is_array($test_result) && isset($test_result['id']) ) {
			return $test_result;
		}

		return 'Failed to fetch'; Debug::err_log('Give CiviCRM failed to fetch contribution ' . $key);
	}
}
