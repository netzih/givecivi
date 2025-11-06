<?php namespace CivivipGive\Control;

use CivivipGive\Model\Give\Donation;
use CivivipGive\Model\Give\Subscription;
use CivivipGive\Model\Civi\Contact;
use CivivipGive\Model\Civi\Recurrence;
use CivivipGive\Model\Civi\Contribution;
use CivivipGive\Control\Service\Fix;
use CivivipGive\Control\Service\Progress;
use CivivipGive\Model\Service\Debug;

if ( ! defined('ABSPATH') ) { exit; }

/**
 * Manual Class
 *
 * From Latin 'manus,' "hand." This controller accepts a form submit from the View,
 * and returns various notices and progress data to the View, as it uses applicable
 * models to
 * 		1. Retrieve all Give donations (payments)
 * 		3. Commit retrieved donor-related data to CiviCRM as Contacts
 * 			(which also retrieves Contact IDs for use as we)
 * 		4. Commit subscription data to CiviContribute as Recurring
 * 		5. Commit donation data to CiviContribute as Contributions
 *
 * Failures it attempts to fix, calling the Fix Class (at this time only in the
 * case of Contributions failures, as the critical data); and, if fixes fail as
 * well, the controller alerts the user via admin_notices.
 * 
 * @copyright 	Copyright (c) 2018, Wanna Pixel, Inc.
 * @license 	https://opensource.org/licenses/gpl-license 	GNU Public License
 * 
 * @since 		1.0
 */
class Manual {
	/**
	 * tracker for CiviCRM-related failures
	 * 
	 * @var array
	 */
	protected $naughty_list = [];

	protected $donations;
	protected $subscriptions;
	protected $contacts;
	protected $recurrences;
	protected $contributions;
	protected $fixes;

	function __construct()
	{
		$this->donations = new Donation;
		$this->subscriptions = new Subscription;
		$this->contacts = new Contact;
		$this->recurrences = new Recurrence;
		$this->contributions = new Contribution;
		$this->fixes = new Fix;
	}

	/**
	 * See Class description.
	 *
	 * @since 	1.0
	 *
	 * @return 	void
	 */
	public function sync()
	{
		$exception_file = trailingslashit(CIVIVIPGIVE_TEMPDIR) . 'civivipgive_exception.tmp';

		// Always delete exception file at start of sync to ensure clean state
		if (file_exists($exception_file)) {
			unlink($exception_file);
		}

		if (class_exists('\Give_Cache') ) { \Give_Cache::flush_cache(); }

		$all_donations = $this->donations->fetchAll();

		if (count($all_donations) === 0) {
			Progress::$amount = 100;
			Progress::countDown(100);
			return;
		}
		Progress::$amount = count($all_donations);

		foreach ($all_donations as $donation) {
			set_time_limit(300);

			$contact_id = $this->contacts->store($donation['donor info']);

			// Skip this donation if contact creation failed
			if (!is_numeric($contact_id) || $contact_id <= 0) {
				Debug::err_log('Failed to create/find contact for donation, skipping contribution. Donor email: ' . ($donation['donor info']['email'] ?? 'unknown'));
				array_push($this->naughty_list, 'contact_failed');
				Progress::countDown(1);
				continue;
			}

			$recurrence_id = $this->recurrencesStore($donation['meta']['_give_subscription_id'], $contact_id);

			$contribution_id = $this->contributions->store($donation, $contact_id);

			$this->checksAndFixes($contribution_id);

			Progress::countDown(1);
		}

		// Only write exception file if there are actual errors
		if ( ! empty($this->naughty_list) ) {
			file_put_contents($exception_file, count($this->naughty_list) );
		}

		Progress::$now = 0;
	}

	/**
	 * Determine whether a donation belongs to a subscription, and store as a
	 * CiviContribute recurring record if so.
	 * 
	 * @param 	int 	$subscription_id
	 * @param 	int 	$contact_id
	 * 
	 * @return 	mixed
	 */
	protected function recurrencesStore($subscription_id, $contact_id)
	{
		if ($subscription_id === '') {
			return;
		}

		$subscription = $this->subscriptions->fetch($subscription_id);

		if ($subscription === 'Failed to fetch') {
			return;
		}

		return $this->recurrences->store($subscription, $contact_id);
	}

	/**
	 * Peform some safeguards and workarounds, due to CiviCRM's unreliability.
	 * 
	 * @param 	int 	$contribution_id
	 * 
	 * @return 	void
	 */
	protected function checksAndFixes($contribution_id)
	{
		if ($contribution_id === 'Failed to store') {
			array_push(
				$this->naughty_list,
				$contribution_id
			);
			return;
		}

		if (stristr($contribution_id, 'Failed to pass') ) {
			$is_it_fixed = $this->fixes->tryAll(substr($contribution_id, 15) );
			if ($is_it_fixed) {
				return;
			}
			array_push(
				$this->naughty_list,
				$contribution_id
			);
		}
	}
}
