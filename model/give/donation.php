<?php namespace CivivipGive\Model\Give;

use CivivipGive\Model\Service\SchemeAdapter;

if ( ! defined('ABSPATH') ) { exit; }

/**
 * Donation Class
 *
 * This model retrieves data in re Give donations (payments)
 * and processes them for use as CiviContribute contributions.
 * Donations can be retrieved:
 * 		1. all
 * 		2. by donation ID
 * 	 	3. by key (trxn_id)
 * 	It is essentially a wrapper for methods of the Give_Payments_Query
 * 	class, specifically:
 * 		give_get_payments
 * 		give_get_payment_by
 *
 * @copyright 	Copyright (c) 2018, Wanna Pixel, Inc.
 * @license 	https://opensource.org/licenses/gpl-license 	GNU Public License
 * 
 * @since 		1.0
 */
class Donation {

	/**
	 * Retrieve and process all donations.
	 *
	 * While there would be some value in supporting a filter by which Give Test Mode payments
	 * could be included or excluded from this method, unfortunately bugs in Give, e.g. that payments
	 * for Recurring Donations have no mode and are excluded when _any_ mode is specified, made
	 * this infeasible.
	 *
	 * @since 	1.0
	 * 
	 * @return 	array
	 */
	public function fetchAll()
	{
		$all_donations = [];
		// Without the following, payments assigned a status by an Add-on
		// (e.g., Recurring Donations' "give_subscription") would not be fetched.
		$enhanced_stata = apply_filters('give_recount_donors_donation_statuses', give_get_payment_status_keys() );

		$donation_posts = give_get_payments([
			'number' => -1,
//			'mode' => $mode,
			'status' => $enhanced_stata,
		]);

		foreach ($donation_posts as $post) {
			$donation = $this->fetch($post->ID);
			if ($donation === null) {
				break;
			}
			array_push(
				$all_donations,
				$donation
			);
		}

		return $all_donations;
	}

	/**
	 * Retrieve and process a donation by donation ID.
	 *
	 * @since 	1.0
	 *
	 * @param 	int		$id 	a Give donation (payment) ID
	 *
	 * @return 	array|void
	 */
	public function fetch($id)
	{
		$donation = give_get_payment_by('id', $id);

		if (
			$donation->user_info['email'] === ''
			|| $donation->payment_meta['_give_payment_total'] == 0
		) {
			return; Debug::log('Fetch aborted due to empty email or $0 amount.');
		}

		return $this->process($donation);
	}

	/**
	 * Retrieve and process a donation by key. In our use cases, this will be a
	 * CiviContribute "trxn_id" or "invoice_id" field, which looks like this:
	 * 		give-{Give key}-{Give donation ID}
	 *
	 * @since 	1.0
	 *
	 * @param 	string 	$trxn_id 	comprises Give key as second segment
	 *
	 * @return 	array|null
	 */
	public function fetchByKey($trxn_id)
	{
		$parts = explode('-', $trxn_id);

		// Validate format: should have at least 3 parts (give-{key}-{id})
		if (count($parts) < 3 || $parts[0] !== 'give') {
			return null;
		}

		$key = $parts[1];

		$donation = give_get_payment_by('key', $key);

		if (!$donation) {
			return null;
		}

		return $this->process($donation);
	}

	/**
	* Adapt results into an array consumable by CiviContribute.
	*
	* @since 1.0
	*
	* @param 	Give_Payment object 	$donation	raw results of a query method above
	*
	* @return 	array
	*/
	protected function process($donation)
	{
		return SchemeAdapter::donation($donation);
	}
}
