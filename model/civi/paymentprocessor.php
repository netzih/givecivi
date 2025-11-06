<?php namespace CivivipGive\Model\Civi;

use CivivipGive\Model\Service\Debug;

if ( ! defined('ABSPATH') ) { exit; }

/**
 * PaymentProcessor Class
 *
 * This model handles CiviCRM payment processor operations, including
 * finding and caching payment processor IDs for linking contributions.
 *
 * @copyright 	Copyright (c) 2018, Wanna Pixel, Inc.
 * @license 	https://opensource.org/licenses/gpl-license 	GNU Public License
 *
 * @since 		0.6.0
 */
class PaymentProcessor {

	/**
	 * Cache for payment processor IDs to avoid repeated API calls
	 *
	 * @var array
	 */
	private static $processor_cache = [];

	/**
	 * Get the CiviCRM payment processor ID for Stripe.
	 *
	 * This method searches for an active Stripe payment processor in CiviCRM.
	 * Results are cached to avoid repeated API calls.
	 *
	 * @since 	0.6.0
	 *
	 * @return 	int|null 	Payment processor ID or null if not found
	 */
	public static function getStripeProcessorId()
	{
		// Check cache first
		if (isset(self::$processor_cache['stripe'])) {
			return self::$processor_cache['stripe'];
		}

		$processor_id = null;

		try {
			civicrm_initialize();

			// Try to find Stripe payment processor
			// CiviCRM uses class_name to identify processor types
			$result = civicrm_api3(
				'PaymentProcessor',
				'get',
				[
					'class_name' => 'Payment_Stripe',
					'is_active' => 1,
					'is_test' => 0,
					'sequential' => 1,
				]
			);

			if (is_array($result) && isset($result['values']) && !empty($result['values'])) {
				$processor_id = $result['values'][0]['id'] ?? null;
			}

			// If not found, try alternate Stripe class names
			if ($processor_id === null) {
				$alternate_names = [
					'com.drastikbydesign.payment.stripe',
					'org.civicrm.payment.stripe',
				];

				foreach ($alternate_names as $class_name) {
					$result = civicrm_api3(
						'PaymentProcessor',
						'get',
						[
							'class_name' => $class_name,
							'is_active' => 1,
							'is_test' => 0,
							'sequential' => 1,
						]
					);

					if (is_array($result) && isset($result['values']) && !empty($result['values'])) {
						$processor_id = $result['values'][0]['id'] ?? null;
						break;
					}
				}
			}

		} catch (\CiviCRM_API3_Exception $e) {
			Debug::log('Error fetching Stripe payment processor: ' . $e->getMessage());
		}

		// Cache the result (even if null, to avoid repeated failed lookups)
		self::$processor_cache['stripe'] = $processor_id;

		return $processor_id;
	}

	/**
	 * Clear the payment processor cache.
	 * Useful after payment processor configuration changes.
	 *
	 * @since 	0.6.0
	 */
	public static function clearCache()
	{
		self::$processor_cache = [];
	}
}
