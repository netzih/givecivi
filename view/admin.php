<?php namespace CivivipGive\View;

use CivivipGive\Control\Manual;
use CivivipGive\Control\Service\Progress;
use CivivipGive\View\Service\Notice;

if ( ! defined('ABSPATH') ) { exit; }

/**
 * Admin Class
 *
 * This view at present does little more than offer a submit to the user, on a
 * Give settings tab, fire Manual@sync when the submit is clicked, and manage a
 * progress bar to keep the user abreast of the synchronization.
 *
 * @copyright 	Copyright (c) 2018, Wanna Pixel, Inc.
 * @license 	https://opensource.org/licenses/gpl-license 	GNU Public License
 * 
 * @since 		1.0
 */
class Admin {
	function __construct()
	{
		add_filter('give-settings_tabs_array', [$this, 'addOurTab']);
		add_filter('give_get_sections_civivip-give', [$this, 'addSettingsSections']);
		add_filter('give_get_settings_civivip-give', [$this, 'addSettings']);
		add_action('wp_ajax_civivipgive-sync', [$this, 'blastOff']);
	}

	/**
	 * Add a tab to Give's Settings tabs.
	 *
	 * @param 	array 	$tabs returned by tabs_array filter
	 *
	 * @return 	array
	 */
	public function addOurTab($tabs)
	{
		$tabs['civivip-give'] = 'CiviCRM Integration';
		return $tabs;
	}

	/**
	 * Add sections to our settings tab.
	 *
	 * @since 	0.4.0
	 *
	 * @param 	array 	$sections
	 *
	 * @return 	array
	 */
	public function addSettingsSections($sections)
	{
		$sections['general'] = __('General Settings', 'civivip-give');
		$sections['payment-methods'] = __('Payment Method Mappings', 'civivip-give');
		$sections['manual-sync'] = __('Manual Sync', 'civivip-give');
		return $sections;
	}

	/**
	 * Add settings fields.
	 *
	 * @since 	0.4.0
	 *
	 * @param 	array 	$settings
	 *
	 * @return 	array
	 */
	public function addSettings($settings)
	{
		$current_section = give_get_current_setting_section();

		switch ($current_section) {
			case 'payment-methods':
				$settings = $this->getPaymentMethodSettings();
				break;
			case 'manual-sync':
				$settings = $this->getManualSyncSettings();
				break;
			case 'general':
			default:
				$settings = $this->getGeneralSettings();
				break;
		}

		return $settings;
	}

	/**
	 * Get general settings.
	 *
	 * @since 	0.4.0
	 *
	 * @return 	array
	 */
	protected function getGeneralSettings()
	{
		return [
			[
				'id' => 'civivipgive_general_settings',
				'type' => 'title',
			],
			[
				'name' => __('Enable Address Sync', 'civivip-give'),
				'desc' => __('Sync billing addresses from Give to CiviCRM contacts', 'civivip-give'),
				'id' => 'civivipgive_sync_addresses',
				'type' => 'radio_inline',
				'default' => 'enabled',
				'options' => [
					'enabled' => __('Enabled', 'civivip-give'),
					'disabled' => __('Disabled', 'civivip-give'),
				],
			],
			[
				'id' => 'civivipgive_general_settings',
				'type' => 'sectionend',
			],
		];
	}

	/**
	 * Get payment method mapping settings.
	 *
	 * @since 	0.4.0
	 *
	 * @return 	array
	 */
	protected function getPaymentMethodSettings()
	{
		$gateways = [
			'stripe' => 'Stripe',
			'stripe_ach' => 'Stripe ACH',
			'stripe_checkout' => 'Stripe Checkout',
			'stripe_apple_pay' => 'Stripe Apple Pay',
			'stripe_google_pay' => 'Stripe Google Pay',
			'paypal' => 'PayPal',
			'paypal_commerce' => 'PayPal Commerce',
			'paypal_standard' => 'PayPal Standard',
			'paypal_express' => 'PayPal Express',
			'offline' => 'Offline Donations',
			'manual' => 'Manual',
			'authorize' => 'Authorize.Net',
			'authorize_net' => 'Authorize.Net',
			'square' => 'Square',
			'braintree' => 'Braintree',
			'razorpay' => 'Razorpay',
			'paytm' => 'Paytm',
			'gocardless' => 'GoCardless',
			'mollie' => 'Mollie',
			'2checkout' => '2Checkout',
		];

		$civicrm_instruments = [
			'Credit Card' => 'Credit Card',
			'Debit Card' => 'Debit Card',
			'Cash' => 'Cash',
			'Check' => 'Check',
			'EFT' => 'EFT',
		];

		$settings = [
			[
				'id' => 'civivipgive_payment_method_settings',
				'type' => 'title',
				'title' => __('Payment Method Mappings', 'civivip-give'),
			],
			[
				'name' => __('Payment Method Mappings', 'civivip-give'),
				'desc' => __('Map GiveWP payment gateways to CiviCRM payment instruments. This determines how payment methods appear in CiviCRM.', 'civivip-give'),
				'type' => 'descriptive_text',
				'id' => 'civivipgive_payment_methods_desc',
			],
		];

		foreach ($gateways as $gateway_id => $gateway_name) {
			$settings[] = [
				'name' => $gateway_name,
				'desc' => sprintf(__('CiviCRM payment instrument for %s', 'civivip-give'), $gateway_name),
				'id' => 'civivipgive_payment_map_' . $gateway_id,
				'type' => 'select',
				'options' => $civicrm_instruments,
				'default' => $this->getDefaultPaymentInstrument($gateway_id),
			];
		}

		$settings[] = [
			'id' => 'civivipgive_payment_method_settings',
			'type' => 'sectionend',
		];

		return $settings;
	}

	/**
	 * Get default payment instrument for a gateway.
	 *
	 * @since 	0.4.0
	 *
	 * @param 	string 	$gateway
	 *
	 * @return 	string
	 */
	protected function getDefaultPaymentInstrument($gateway)
	{
		$defaults = [
			'stripe' => 'Credit Card',
			'stripe_ach' => 'EFT',
			'stripe_checkout' => 'Credit Card',
			'stripe_apple_pay' => 'Credit Card',
			'stripe_google_pay' => 'Credit Card',
			'paypal' => 'Credit Card',
			'paypal_commerce' => 'Credit Card',
			'paypal_standard' => 'Credit Card',
			'paypal_express' => 'Credit Card',
			'offline' => 'Check',
			'manual' => 'Cash',
			'authorize' => 'Credit Card',
			'authorize_net' => 'Credit Card',
			'square' => 'Credit Card',
			'braintree' => 'Credit Card',
			'razorpay' => 'Credit Card',
			'paytm' => 'Credit Card',
			'gocardless' => 'EFT',
			'mollie' => 'Credit Card',
			'2checkout' => 'Credit Card',
		];

		return $defaults[$gateway] ?? 'Credit Card';
	}

	/**
	 * Get manual sync settings.
	 *
	 * @since 	0.5.0
	 *
	 * @return 	array
	 */
	protected function getManualSyncSettings()
	{
		// Enqueue assets
		$this->enqueueManualSyncAssets();

		return [
			[
				'id' => 'civivipgive_manual_sync_settings',
				'type' => 'title',
				'title' => __('Manual Synchronization', 'civivip-give'),
			],
			[
				'name' => __('Sync Donations', 'civivip-give'),
				'desc' => $this->getManualSyncHTML(),
				'id' => 'civivipgive_manual_sync_desc',
				'type' => 'descriptive_text',
			],
			[
				'id' => 'civivipgive_manual_sync_settings',
				'type' => 'sectionend',
			],
		];
	}

	/**
	 * Get manual sync HTML.
	 *
	 * @since 	0.5.0
	 *
	 * @return 	string
	 */
	protected function getManualSyncHTML()
	{
		ob_start();
		?>
		<div style="margin-top: 10px;">
			<p><?php echo __('Click to manually synchronize all Give donations to CiviCRM.', 'civivip-give'); ?></p>
			<p>
				<input
					name="civivipgive-sync-submit"
					id="civivipgive-sync-submit"
					class="button button-primary"
					type="button"
					value="<?php echo esc_attr(__('Sync Now', 'civivip-give')); ?>"
				>
			</p>
			<!-- progressbar scaffold for jQuery -->
			<div id="civivipgive-sync-progressbar" role="progressbar" style="margin-top: 15px;">
				<div id="progress-label" class="progress-label"></div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Enqueue manual sync assets.
	 *
	 * @since 	0.5.0
	 */
	protected function enqueueManualSyncAssets()
	{
		$wp_scripts = new \WP_Scripts;
		wp_enqueue_style(
			'civivipgive-progressbar',
			'https://ajax.googleapis.com/ajax/libs/jqueryui/'
				. $wp_scripts->registered['jquery-ui-core']->ver
				. '/themes/smoothness/jquery-ui.css'
		);
		wp_enqueue_style(
			'civivipgive-admin-tab',
			CIVIVIPGIVE_URL . 'assets/css/civivipgive_admin_tab.css'
		);
		wp_enqueue_script(
			'civivipgive-admin-tab-sync',
			CIVIVIPGIVE_URL . 'assets/js/civivipgive_admin_tab.js',
			['jquery', 'jquery-ui-progressbar']
		);
		wp_localize_script(
			'civivipgive-admin-tab-sync',
			'civivipgive_jsparams', [
				'tempprogfileurl' => trailingslashit(CIVIVIPGIVE_TEMPDIR_URL) . 'civivipgive_percent.tmp',
				'permission' => current_user_can('administrator'),
			]
		);
	}

	/**
	 * React to user submit.
	 *
	 * For related admin_notices methods, see Notices@permissionsCheck and
	 * Notices@civiCrmFail.
	 * 
	 * @return 	void
	 */
	public function blastOff()
	{
		$exception_file = trailingslashit(CIVIVIPGIVE_TEMPDIR) . 'civivipgive_exception.tmp';
		$percent_file = trailingslashit(CIVIVIPGIVE_TEMPDIR) . 'civivipgive_percent.tmp';

		if (file_exists($exception_file) ) {
			unlink($exception_file);
		}

		if (current_user_can('administrator') ) {	//'edit_contributions') ) {	//UNFORTUNATELY CIVICIRM DOESN'T SUPPORT
			file_put_contents($percent_file, '0');
			$manual = new Manual;
			$manual->sync();
			update_option('civivipgive_firstsync', 0);
		}
		wp_die();
	}
}
