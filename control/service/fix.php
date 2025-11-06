<?php namespace CivivipGive\Control\Service;

use CivivipGive\Model\Give\Donation;
use CivivipGive\Model\Civi\Contribution;
use CivivipGive\Model\Service\SchemeAdapter;
use CivivipGive\Model\Service\Debug;

if ( ! defined('ABSPATH') ) { exit; }

/**
 * Fix Class
 *
 * Due to an abundance of bugs in CiviCRM, especially its PHP API, and unreliable
 * exception handling (a loose term here, since CiviCRM_API3_Exception is not even
 * an extension of the Exception Core Class), and brought to a head by the sad
 * truth that the CiviCRM version constant offers no patch level to test against
 * and require, this class offers workarounds in the form of tests and fixes.
 * 
 * @copyright 	Copyright (c) 2018, Wanna Pixel, Inc.
 * @license 	https://opensource.org/licenses/gpl-license 	GNU Public License
 * 
 * @since 		1.0
 */
class Fix {
	/**
	 * index of methods below that directly fix
	 * 
	 * @var array
	 */
	protected $fixes = [
		'pendingToRefunded',
	];

	protected $donations;
	protected $contributions;

	function __construct()
	{
		$this->donations = new Donation;
		$this->contributions = new Contribution;
	}

	/**
	 * Compare the result of a CiviContribute API call with the original donation.
	 * This is critical due to the API's unreliability, so that, first, we wish to
	 * safeguard against unnecessary uses of the API (e.g., not to update unless
	 * required), and, after a use of the API, we are forced to "check our work"
	 * explicitly because the API sometimes fails to accurately record our data
	 * or even alters data (sometimes in a way that breaks CiviCRM).
	 *
	 * In future similar tests may be written for Contacts and CiviContribute
	 * "recurring" records, but Contributions are this plugin's raison d'être
	 * as surely the most critical data to preserve.
	 *
	 * @since 	1.0
	 *
	 * @param 	array 	$donation 		a processed Give donation
	 * @param 	array 	$contribution 	a raw CiviContribute record
	 * 
	 * @return 	boolean
	 */
	public static function testShape($donation, $contact_id, $contribution)
	{
		//unset($donation['donor info']);
		unset($donation['meta']);
		$donation['donor info'] = intval($contact_id);	// $addendum = ['donor info' => $contact_id];
		$donat = $donation;	// + $addendum;

		$contrib = SchemeAdapter::contribution($contribution);

		asort($donat); asort($contrib);

		Debug::log('Fix::testShape - $donat:' . PHP_EOL . var_export($donat, true) );
		Debug::log('Fix::testShape - $contrib:' . PHP_EOL . var_export($contrib, true) );

		return $donat == $contrib;
	}

	/**
	 * 
	 * @param 	int 	$contribution_id
	 * 
	 * @return 	boolean
	 */
	public function tryAll($contribution_id)
	{
		foreach ($this->fixes as $fix) {
			$did_it_work = $this->{$fix}($contribution_id);

			if ($did_it_work) {
				return true;
			}
			if( ! isset( $contribution_id ) || empty( $contribution_id ) ) {
				$contribution_id = 'NOT FOUND';
			}
			Debug::err_log('Give CiviCRM failed to fix: ' . $fix . '; contrib ID: ' . $contribution_id);
		}
		return false;
	}

	/**
	 * Fix a CiviCRM bug that overwrites Give donations' "Refunded" status
	 * with "Pending: Incomplete Transaction".
	 * 
	 * CiviCRM then prevents attempts (programmatic or manual) to change this
	 * faux status to "Refunded." The workaround is first to change the status to
	 * "Completed", a status from which CiviCRM does allow the change to "Refunded."
	 * 
	 * NB there is a critical difference between some of the keys and values in a
	 * raw Contribution array and a processed donation array, q.v. below.
	 * 
	 * @param 	int 	$contribution_id
	 * 
	 * @return 	boolean
	 */
	protected function pendingToRefunded($contribution_id)
	{
		try {
			civicrm_initialize();
			$contribution = civicrm_api3(
				'Contribution',
				'get',
				['id' => $contribution_id]
			);
		} catch (\CiviCRM_API3_Exception $e) {
			Debug::log($e->getMessage() );	// unreliable
			return false;
		}

		if ( ( ! is_array($contribution) ) || ( ! isset($contribution['id']) ) ) {
			return false;
		}

		$contribution = $contribution['values'][$contribution['id'] ];	// shortcut

		if (
			isset($contribution['contribution_status_id'])
			&& $contribution['contribution_status_id'] !== '2'	// i.e., Pending
		) {
			return false;
		}

		if (
			isset($contribution['contribution_status'])
			&& $contribution['contribution_status'] !== 'Pending'
		) {
			return false;
		}

		// For Stripe donations, trxn_id contains the Charge ID (ch_xxx)
		// We need to use invoice_id which contains the Give tracking ID
		$lookup_key = $contribution['trxn_id'];

		// Log what we're working with for debugging
		Debug::log("pendingToRefunded - Contrib ID: {$contribution_id}, trxn_id: " . ($contribution['trxn_id'] ?? 'empty') . ", invoice_id: " . ($contribution['invoice_id'] ?? 'empty'));

		if (!empty($contribution['invoice_id']) &&
		    (strpos($contribution['trxn_id'], 'ch_') === 0 || strpos($contribution['trxn_id'], 'pi_') === 0)) {
			$lookup_key = $contribution['invoice_id'];
			Debug::log("pendingToRefunded - Using invoice_id for lookup: {$lookup_key}");
		}

		$donation = $this->donations->fetchByKey($lookup_key);

		// Check if donation was found
		if (!$donation || !is_array($donation)) {
			Debug::log("pendingToRefunded - Failed to fetch donation for key: {$lookup_key}");
			return false;
		}

		if (!isset($donation['contribution_status_id']) || $donation['contribution_status_id'] !== 'Refunded') {
			Debug::log("pendingToRefunded - Donation status is not Refunded: " . ($donation['contribution_status_id'] ?? 'empty'));
			return false;
		}

		$donation['contribution_status_id'] = 'Completed';
		$result_prep = $this->contributions->store($donation, $contribution['contact_id']);

		if ($result_prep === 'Failed to store' || stristr($result_prep, 'Failed to pass') ) {
			return false;
		}

		$donation['contribution_status_id'] = 'Refunded';
		$result = $this->contributions->store($donation, $contribution['contact_id']);

		if ($result === 'Failed to store' || stristr($result, 'Failed to pass') ) {
			return false;
		}

		return true;
	}
}
