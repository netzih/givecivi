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
}
