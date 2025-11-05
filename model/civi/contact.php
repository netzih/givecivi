<?php namespace CivivipGive\Model\Civi;

use CivivipGive\Model\Service\Debug;

if ( ! defined('ABSPATH') ) { exit; }

/**
 * Contact Class
 *
 * This model stores Give donor data into CiviCRM as CiviCRM Contact records,
 * using the CiviCRM PHP API.
 *
 * As all our models, due to bugs in and general unreliability of CiviCRM's
 * API, this implements multiple checks before and after a store.
 * 
 * @copyright 	Copyright (c) 2018, Wanna Pixel, Inc.
 * @license 	https://opensource.org/licenses/gpl-license 	GNU Public License
 * 
 * @since 		1.0
 */
class Contact {

	/**
	 * Attempt to create / update a CiviCRM Contact record. (With the present
	 * version of the API, updates are effected by "create".)
	 *
	 * @since 	1.0
	 *
	 * @param 	array 	$donor 	a processed Give donation's "donor info" element
	 *
	 * @return 	int|string
	 */
	public function store(array $donor)
	{
		// Extract billing address if present (custom field, not part of Contact API)
		$billing_address = $donor['_billing_address'] ?? [];
		unset($donor['_billing_address']);

		$result_exists = $this->fetch($donor);
		if ($result_exists !== 'Failed to fetch') {
			// If contact exists and we have billing address, update it
			if (!empty($billing_address)) {
				$this->storeAddress($result_exists, $billing_address);
			}
			return $result_exists;
		}

		try {
			civicrm_initialize();
			$result = civicrm_api3(
				'Contact',
				'create',
				$donor
			);
		} catch (\CiviCRM_API3_Exception $e) {
			Debug::log($e->getMessage() );	// unreliable
		}
		if(is_array($result) && isset($result['id']) ) {
			// If we created/updated contact and have billing address, store it
			if (!empty($billing_address)) {
				$this->storeAddress($result['id'], $billing_address);
			}
			return $result['id'];
		}

		return 'Failed to store'; Debug::err_log('Give CiviCRM failed to store:' . var_export($donor, true) );
	}

	/**
	 * Store or update billing address for a contact.
	 *
	 * @since 	0.4.0
	 *
	 * @param 	int 	$contact_id 		CiviCRM contact ID
	 * @param 	array 	$billing_address 	Address data
	 *
	 * @return 	int|string
	 */
	protected function storeAddress($contact_id, array $billing_address)
	{
		if (empty($billing_address)) {
			return 'No address data';
		}

		// Check if billing address already exists for this contact
		try {
			civicrm_initialize();
			$existing_address = civicrm_api3(
				'Address',
				'get',
				[
					'contact_id' => $contact_id,
					'location_type_id' => 'Billing',
				]
			);
		} catch (\CiviCRM_API3_Exception $e) {
			Debug::log($e->getMessage());
		}

		// Prepare address data
		$address_data = $billing_address + [
			'contact_id' => $contact_id,
			'location_type_id' => 'Billing',
		];

		// If address exists, add ID to update it
		if (isset($existing_address['id']) && $existing_address['count'] > 0) {
			$address_data['id'] = $existing_address['id'];
		}

		// Create or update address
		try {
			civicrm_initialize();
			$result = civicrm_api3(
				'Address',
				'create',
				$address_data
			);
		} catch (\CiviCRM_API3_Exception $e) {
			Debug::log($e->getMessage());
			return 'Failed to store address';
		}

		if (is_array($result) && isset($result['id'])) {
			return $result['id'];
		}

		return 'Failed to store address';
	}

	/**
	 * Attempt to get a CiviCRM Contact record: first, by WordPress user ID, which
	 * will work if ( a ) the donor was not anonymous and ( b ) the customer is
	 * sync'ing WordPress users to CiviCRM Contacts; second, by Give donor ID,
	 * which will work if we have created this Contact in the past.
	 *
	 * A Give anonymous donor is registered with a Give user ID of 0. This includes
	 * donors imported via CSV file. Thus a Give user ID of 0 cannot be unique,
	 * and we should use instead the donor ID.
	 * 		
	 * @param 	array 	$donor 	a processed Give donation's "donor info" element
	 * 
	 * @return 	int|string
	 */
	protected function fetch($donor)
	{
		if ($donor['_wp_user_id'] == 0) {	// See NB above. 
			try {
				civicrm_initialize();
				$result = civicrm_api3(
					'Contact',
					'get',
					['external_identifier' => $donor['external_identifier'] ]
				);
			} catch (\CiviCRM_API3_Exception $e) {
				Debug::log($e->getMessage() );	// unreliable
			}

			if(is_array($result) && isset($result['id']) ) {
				return $result['id'];
			}
		}

		try {
			civicrm_initialize();
			$user = civicrm_api3(
				'User',
				'get',
				['id' => $donor['_wp_user_id'] ]
			);
		} catch (\CiviCRM_API3_Exception $e) {
			Debug::log($e->getMessage() );	// unreliable
		}

		if(is_array($user) && isset($user['id']) ) {
			return $user['values'][$user['id'] ]['contact_id'];
		}

		// NEW FEATURE
		// 
		// If we find no related contacts, josh@wapix.co has asked that we attempt to 
		// guess if some previously-existing contact is the same person as our donor.
		// If we think we've guessed right, then we brand the result with our
		// "external identifier" for future retrieval (using API3, this means re-
		// creating the contact).
		
		// Guess, by email && last_name.
		try {
			civicrm_initialize();
			$guess_user = civicrm_api3(
				'User',
				'get',
				[
					'email' => $donor['email'],
					'last_name' => $donor['last_name'],
				]
			);
		} catch (\CiviCRM_API3_Exception $e) {
			Debug::log($e->getMessage() );	// unreliable
		}

		if(is_array($guess_user) && isset($guess_user['id']) ) {
			// Brand with "external identifier".
			try {
				civicrm_initialize();
				$user = civicrm_api3(
					'User',
					'create',
					$guess_user['values'] + ['external_identifier' => $donor['external_identifier'] ]
				);
			} catch (\CiviCRM_API3_Exception $e) {
				Debug::log($e->getMessage() );	// unreliable
			}
			// Return guess's ID.
			return $guess_user['values'][$guess_user['id'] ]['contact_id'];
		}


		return 'Failed to fetch';	// Debug::err_log('Give CiviCRM failed to fetch:' . var_export($donor, true) );
	}
}
