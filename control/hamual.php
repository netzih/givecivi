<?php namespace CivivipGive\Control;

use CivivipGive\Model\Give\Donation;
use CivivipGive\Model\Give\Subscription;
use CivivipGive\Model\Civi\Contact;
use CivivipGive\Model\Civi\Contribution;
use CivivipGive\Model\Civi\Recurrence;
use CivivipGive\Control\Service\Fix;
use CivivipGive\Model\Service\Debug;

if ( ! defined('ABSPATH') ) { exit; }

/**
 * Hamual Class
 *
 * From Latin 'hamus,' "hook." This controller reacts to the following
 * Give hooks:
 * 		/* From /give/includes/payments/functions.php:
 *   	give_insert_payment
  * 		/* From /give/includes/admin/payments/actions.php:
 * 		give_updated_edited_donation
 * 		/* From /give/includes/admin/payments/class-payments-table.php:
 * 		give_payments_table_do_bulk_action
 * 		/* From /give-recurring/includes/give-subscriptions-db.php:
 * 		give_subscription_inserted
 *  and then calls methods of the applicable models to
 * 		1. Retrieve a Give donation (payment)
 * 		2. Commit retrieved donor-related data as a CiviCRM Contact
 * 		3. Commit donation data as a CiviContribute record
 * 	or
 * 		1. Retrieve a Give subscription
 * 		2. Commit subscription data to CiviContribute as a Recurring record
 *
 * Test-mode Give donations are ignored, but can be sync'ed by the Manual Class.
 * 
 * @copyright 	Copyright (c) 2018, Wanna Pixel, Inc.
 * @license 	https://opensource.org/licenses/gpl-license 	GNU Public License
 * 
 * @since 		1.0
 */
class Hamual {
	protected $donations;
	protected $subscriptions;
	protected $contacts;
	protected $contributions;
	protected $recurrences;
	protected $fixes;

	function __construct()
	{
		$this->donations = new Donation;
		$this->subscriptions = new Subscription;
		$this->contacts = new Contact;
		$this->contributions = new Contribution;
		$this->recurrences = new Recurrence;
		$this->fixes = new Fix;

		add_action('give_insert_payment', [$this, 'checkForCheque'], 10, 2);	// params: payment->ID, payment_data
		add_action('give_update_payment_status', [$this, 'syncDonationToContrib'], 10, 3);	// params: $this->ID, $status, $old_status
		add_action('give_updated_edited_donation', [$this, 'syncDonationToContrib']);	// param: payment_id
		add_action('give_payments_table_do_bulk_action', [$this, 'checkAction'], 10, 2);	// params: id, this->current_action
		add_action('give_subscription_inserted', [$this, 'syncSubscriptionToRecur'], 10, 2);	// params: subscription_id, data
	}

	/**
	 * It's a long story. The give_insert_payment hook might seem like a good time
	 * at which to fire our sync method; however, _all_ payments at this time are
	 * provisionally marked as pending by Give. Later, Give updates the status
	 * appropriately, usually to "Completed" -- but by this time our sync method
	 * would already have registered the Contribition with status "Pending"
	 * (incomplete transaction), which is not only inaccurate but messes up CiviCRM
	 * (even longer story). So instead we use the give_update_payment_status hook;
	 * however, this hooknever checks on payment with a "gateway" of "offline",
	 * i.e., payments by check, leaving the job up to the Give editor who materially
	 * receives the check.
	 * 
	 * Thus this method, to check whether a payment is by check and intervene.
	 * The give_insert_payment hook's nasty habit of filing everything as pending
	 * is appropriate in this particular case (pay later).
	 *
	 * @since 	1.0
	 * 
	 * @param 	int 	$payment_id 	returned by hook
	 * @param 	array 	$data 			returned by hook
	 * @return 	void
	 */
	public function checkForCheque($payment_id, $data) {
		if ( isset( $data['gateway'] ) && $data['gateway'] === 'offline' ) {
			$this->syncDonationToContrib($payment_id);
		}
	}

	/**
	 * Cull bulk actions to which we react.
	 *
	 * @since 	1.0
	 * 
	 * @param 	int 	$payment_id 	returned by hook
	 * @param 	string 	$action 		returned by hook
	 * 
	 * @return 	void
	 */
	public function checkAction($payment_id, $action) {
		if ($action === 'delete' || $action === 'resend-receipt') {
			return;
		}
		$this->syncDonationToContrib($payment_id);
	}

	/**
	 * React to Give hooks by gathering donation data and attempting to record them
	 * in CiviContribute.
	 *
	 * @since 	1.0
	 *
	 * @param 	mixed 	$payment_id 	returned by hook
	 * 
	 * @return 	void
	 */
	public function syncDonationToContrib($payment_id, ...$args)
	{
		set_time_limit(300);

		$donation = $this->donations->fetch($payment_id);

		$contact_id = $this->contacts->store($donation['donor info']);

		$result = $this->contributions->store($donation, $contact_id);

		if ($result === 'Failed to store') {
			Debug::err_log('Give CiviCRM failed to auto-sync:' . var_export($subscription, true) );
		}

		if (stristr($result, 'Failed to pass') ) {
			if ($this->fixes->tryAll($contribution_id) ) {
				Debug::err_log(
					'Give CiviCRM auto-sync detected a malformed record in CiviContribute:'
					. var_export($subscription, true)
				);
			}
		}

	}

	/**
	 * React to Give Recurring Donations Add-on by gathering subscription data and
	 * attempting to record them in CiviContribute.
	 *
	 * @since 	1.0
	 *
	 * @param 	int 	$subscription_id 	returned by hook
	 * @param 	array 	$data 				returned by hook (see give-recurring/give-subscription@create)
	 * 
	 * @return 	void
	 */
	public function syncSubscriptionToRecur($subscription_id, $data)
	{
		set_time_limit(300);

		$subscription = $this->subscriptions->fetch($subscription_id);
		$parent_donation = $this->donations->fetch($data['parent_payment_id']);
		$contact_id = $this->contacts->store($parent_donation['donor info']);
		
		$result = $this->recurrences->store($subscription, $contact_id);
		
		if ( $result === 'Failed to store' ) {
			Debug::err_log( 'Give CiviCRM failed to auto-sync:' . var_export( $subscription, true ) );
		}
	}
}
