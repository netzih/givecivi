<?php namespace CivivipGive\Model\Give;

use CivivipGive\Model\Service\SchemeAdapter;
use CivivipGive\Model\Service\Debug;

if ( ! defined('ABSPATH') ) { exit; }

/**
 * Subscription Class
 *
 * This model retrieves data in re Give subscriptions
 * (recurring payments) and processes them for use as
 * CiviContribute Recurring contributions. It is essentially
 * a wrapper for Give_Subscriptions_DB Class methods:
 * 		1. get_by
 * 		2. get_subscriptions
 *
 * @copyright 	Copyright (c) 2018, Wanna Pixel, Inc.
 * @license 	https://opensource.org/licenses/gpl-license 	GNU Public License
 * 
 * @since 		1.0
 */
class Subscription {
	/**
	 * Retrieve and process a subscription by subscription ID; then decorate
	 * related donation (payment) with same.
	 *
	 * NB: Our guards to this point have been testing for a missing (if no Add-on)
	 * or empty (if no related subscription) subscription_id meta field in a
	 * donation. However, a donation will continue to comprise this field even
	 * after a subscription has been deleted; therefore we perform one last test
	 * below to avoid a fatal error in the SchemeAdapter Class.
	 *
	 * @since 	1.0
	 *
	 * @param 	int 	$id 	Give subscription ID
	 * 
	 * @return 	array
	 */
	public function fetch($id)
	{
		if (defined('GIVE_RECURRING_VERSION') ) {
			$give_subscriptions = new \Give_Subscriptions_DB;
			$subscription = $give_subscriptions->get_by('id', $id);
		}

		if ($subscription) {	// See NB above.
			return $this->process($subscription);
		}

		return "Failed to fetch"; Debug::err_log('Give CiviCRM failed to fetch subscription ' . $id);
	}

	/**
	* Adapt results into an array consumable by CiviContribute.
	*
	* @since 1.0
	*
	* @param 	array 	$subscription	raw result of query method above
	*
	* @return 	array
	*/
	protected function process($subscription)
	{
		return SchemeAdapter::subscription(get_object_vars($subscription) );
	}
}
