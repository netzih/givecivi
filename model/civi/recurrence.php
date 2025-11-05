<?php namespace CivivipGive\Model\Civi;

use CivivipGive\Model\Service\Debug;

if ( ! defined('ABSPATH') ) { exit; }

/**
 * Recurrence Class
 *
 * This model primarily stores Give donation (payment) data into CiviContribute as
 * Recurrence records, using the CiviCRM PHP API; due to the unreliability of the API,
 * various checks are also implemented.
 * 
 * @copyright 	Copyright (c) 2018, Wanna Pixel, Inc.
 * @license 	https://opensource.org/licenses/gpl-license 	GNU Public License
 * 
 * @since 		1.0
 */
class Recurrence {
	/**
	 * Attempt to create a CiviContribute Recurring record. A Recurring record
	 * is a sort of roadmap for a donor's ongoing relationship with the
	 * recipient, in essence providing time intervals at and a duration
	 * within which the recipient can expect further donations. As donations
	 * are in fact made, the roadmap is updated. (In CiviContribute's schema,
	 * this updating takes the simple form of a foreign key to the roadmap stored
	 * in each such donation.)
	 * 
	 * Thus a Recurring record is brief, but we do append the transaction ID (Give: key)
	 * of the "primary payment" (the Give donation with which a Give Subscription
	 * was initiated) in order to have a way to recover the CiviContribute Recurring
	 * record's ID (for use as the foreign key described above.)
	 * 
	 * @since 	1.0
	 *
	 * @param 	array 	$subscription 	a processed Give subscription
	 * @param 	int 	$contact_id		the CiviCRM Contact ID of the donor
	 * 
	 * @return 	int|string
	 */
	public function store(array $subscription, $contact_id)
	{
		$result_exists = $this->fetch($subscription['trxn_id']);
		if ( $result_exists !== 'Failed to fetch' ) {
			return $result_exists;
		}

		try {
			civicrm_initialize();
			$result = civicrm_api3(
				'ContributionRecur',
				'create',
				$subscription + ['contact_id' => $contact_id]
			);
		} catch (\CiviCRM_API3_Exception $e) {
			Debug::log($e->getMessage() );	// unreliable
		}
		if ( isset( $result ) && is_array( $result ) && isset( $result['id'] ) ) {
			return $result['id'];
		}

		return 'Failed to store'; Debug::err_log('Give CiviCRM failed to store:' . var_export($subscription, true) );
	}

	/**
	 * Retrieve a CiviContribute Recurring record's ID by its transaction ID
	 * (based on Give subscription's "primary payment" key), for use in the Contribution
	 * Class's store method and in the SchemeAdapter service's donation method.
	 *
	 * @since 	1.0
	 *
	 * @param 	string 	$key 	a Give donation key (CiviContribute: trxn_id)
	 * 
	 * @return 	int|string
	 */
	public function fetch($key)
	{
		try{
			civicrm_initialize();
			$result = civicrm_api3(
				'ContributionRecur',
				'get',
				['trxn_id' => $key]
			);
		} catch (\CiviCRM_API3_Exception $e) {
			Debug::log($e->getMessage() );	// unreliable
		}

		if(is_array($result) && isset($result['id']) ) {
			return $result['id'];
		}

		return 'Failed to fetch'; Debug::err_log('Give CiviCRM failed to fetch recurrence ' . $key);
	}
}
