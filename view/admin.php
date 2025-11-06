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
		add_action('give-settings_sections_civivip-give_page', [$this, 'displayTabPage']);
		add_filter('give_get_settings_civivip-give', [$this, 'addSettings']);
		add_action('admin_post_civivipgive_save_settings', [$this, 'saveSettings']);
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
	 * Save settings.
	 *
	 * @since 	0.5.0
	 */
	public function saveSettings()
	{
		// Verify nonce
		if (!isset($_POST['civivipgive_settings_nonce']) || !wp_verify_nonce($_POST['civivipgive_settings_nonce'], 'civivipgive_save_settings')) {
			wp_die(__('Security check failed', 'civivip-give'));
		}

		// Check user permissions (use administrator since manage_give_settings may not exist)
		if (!current_user_can('administrator')) {
			wp_die(__('You do not have permission to save these settings', 'civivip-give'));
		}

		// Save each setting
		$settings = $this->addSettings([]);
		foreach ($settings as $setting) {
			if (isset($setting['id']) && isset($_POST[$setting['id']])) {
				$value = sanitize_text_field($_POST[$setting['id']]);
				give_update_option($setting['id'], $value);
			}
		}

		// Redirect back to settings page with success message
		wp_redirect(add_query_arg([
			'page' => 'give-settings',
			'tab' => 'civivip-give',
			'settings-updated' => 'true'
		], admin_url('admin.php')));
		exit;
	}

	/**
	 * Display the settings page content.
	 *
	 * @since 	1.0
	 */
	public function displayTabPage()
	{
		// CSS and JavaScript
		$wp_scripts = new \WP_Scripts;
		wp_enqueue_style(
			'civivipgive-progressbar',
			'https://ajax.googleapis.com/ajax/libs/jqueryui/'
				. $wp_scripts->registered['jquery-ui-core']->ver
				. '/themes/smoothness/jquery-ui.css'
		);

		if (file_exists(CIVIVIPGIVE_DIR . 'assets/css/civivipgive_admin_tab.css')) {
			wp_enqueue_style(
				'civivipgive-admin-tab',
				CIVIVIPGIVE_URL . 'assets/css/civivipgive_admin_tab.css'
			);
		}

		if (file_exists(CIVIVIPGIVE_DIR . 'assets/js/civivipgive_admin_tab.js')) {
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

		// Get settings to render manually
		$settings = $this->addSettings([]);

		// Display success message if settings were saved
		if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
			?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?php echo __('Settings saved.', 'civivip-give'); ?></strong></p>
			</div>
			<?php
		}

		// Render settings form
		?>
		<form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
			<input type="hidden" name="action" value="civivipgive_save_settings">
			<?php wp_nonce_field('civivipgive_save_settings', 'civivipgive_settings_nonce'); ?>
			<table class="form-table">
				<?php
				foreach ($settings as $setting) {
				if ($setting['type'] === 'title') {
					?>
					<tr>
						<th colspan="2">
							<h2><?php echo esc_html($setting['name']); ?></h2>
							<?php if (!empty($setting['desc'])) { ?>
								<p class="description"><?php echo $setting['desc']; ?></p>
							<?php } ?>
						</th>
					</tr>
					<?php
				} elseif ($setting['type'] === 'sectionend') {
					// Just spacing
				} elseif ($setting['type'] === 'radio_inline') {
					$value = give_get_option($setting['id'], $setting['default']);
					?>
					<tr>
						<th scope="row">
							<label><?php echo esc_html($setting['name']); ?></label>
						</th>
						<td>
							<?php foreach ($setting['options'] as $key => $label) { ?>
								<label style="margin-right: 15px;">
									<input type="radio" name="<?php echo esc_attr($setting['id']); ?>" value="<?php echo esc_attr($key); ?>" <?php checked($value, $key); ?>>
									<?php echo esc_html($label); ?>
								</label>
							<?php } ?>
							<?php if (!empty($setting['desc'])) { ?>
								<p class="description"><?php echo $setting['desc']; ?></p>
							<?php } ?>
						</td>
					</tr>
					<?php
				} elseif ($setting['type'] === 'select') {
					$value = give_get_option($setting['id'], $setting['default']);
					?>
					<tr>
						<th scope="row">
							<label for="<?php echo esc_attr($setting['id']); ?>"><?php echo esc_html($setting['name']); ?></label>
						</th>
						<td>
							<select name="<?php echo esc_attr($setting['id']); ?>" id="<?php echo esc_attr($setting['id']); ?>">
								<?php foreach ($setting['options'] as $key => $label) { ?>
									<option value="<?php echo esc_attr($key); ?>" <?php selected($value, $key); ?>><?php echo esc_html($label); ?></option>
								<?php } ?>
							</select>
							<?php if (!empty($setting['desc'])) { ?>
								<p class="description"><?php echo $setting['desc']; ?></p>
							<?php } ?>
						</td>
					</tr>
					<?php
				} elseif ($setting['type'] === 'descriptive_text') {
					?>
					<tr>
						<th scope="row">
							<label><?php echo esc_html($setting['name']); ?></label>
						</th>
						<td>
							<?php echo $setting['desc']; ?>
						</td>
					</tr>
					<?php
				}
			}
			?>
		</table>

		<p class="submit">
			<input type="submit" class="button button-primary" value="<?php echo esc_attr(__('Save Changes', 'civivip-give')); ?>">
		</p>
		</form>

		<!-- Manual Sync Card -->
	    <div class="card" id="civivipgive-sync-card">

	        <h2 class="title">
	        	<?= __('CiviCRM Integration Manual Sync', 'civivip-give'); ?>
	    	</h2>

			<!-- form scaffold for jQuery -->
	            <label for="civivipgive-sync-submit">
	            	<?= __(
            			'Click to manually synchronize Give donations to CiviContribute.',
            			'civivip-give'
            		); ?>
	            </label>
                <input
                	name="civivipgive-sync-submit" id="civivipgive-sync-submit"
                	class="button button-primary button-hero"
                	type="button" value="<?= __('Sync', 'civivip-give'); ?>"
            	>

			<!-- progressbar scaffold for jQuery -->
	        <div id="civivipgive-sync-progressbar" role="progressbar">
	            <div id="progress-label" class="progress-label">
	            	<!-- value supplied by jQuery -->
	            </div>
	        </div>
	        <!-- /progressbar -->

	    </div>
 		<?php
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
		// General Settings Section
		$settings['civivipgive_general_title'] = [
			'id' => 'civivipgive_general_title',
			'name' => __('General Settings', 'civivip-give'),
			'type' => 'title',
		];

		$settings['civivipgive_sync_addresses'] = [
			'name' => __('Enable Address Sync', 'civivip-give'),
			'desc' => __('Sync billing addresses from Give to CiviCRM contacts', 'civivip-give'),
			'id' => 'civivipgive_sync_addresses',
			'type' => 'radio_inline',
			'default' => 'enabled',
			'options' => [
				'enabled' => __('Enabled', 'civivip-give'),
				'disabled' => __('Disabled', 'civivip-give'),
			],
		];

		$settings['civivipgive_general_end'] = [
			'id' => 'civivipgive_general_end',
			'type' => 'sectionend',
		];

		// Payment Method Mappings
		$settings['civivipgive_payment_title'] = [
			'id' => 'civivipgive_payment_title',
			'name' => __('Payment Method Mappings', 'civivip-give'),
			'desc' => __('Map GiveWP payment gateways to CiviCRM payment instruments.', 'civivip-give'),
			'type' => 'title',
		];

		$civicrm_instruments = [
			'Credit Card' => 'Credit Card',
			'Debit Card' => 'Debit Card',
			'Cash' => 'Cash',
			'Check' => 'Check',
			'EFT' => 'EFT',
		];

		$gateways = [
			'stripe' => 'Stripe',
			'paypal' => 'PayPal',
			'offline' => 'Offline Donations',
			'manual' => 'Manual',
		];

		foreach ($gateways as $gateway_id => $gateway_name) {
			$settings['civivipgive_payment_map_' . $gateway_id] = [
				'name' => $gateway_name,
				'desc' => sprintf(__('CiviCRM payment instrument for %s', 'civivip-give'), $gateway_name),
				'id' => 'civivipgive_payment_map_' . $gateway_id,
				'type' => 'select',
				'options' => $civicrm_instruments,
				'default' => ($gateway_id === 'offline') ? 'Check' : 'Credit Card',
			];
		}

		$settings['civivipgive_payment_end'] = [
			'id' => 'civivipgive_payment_end',
			'type' => 'sectionend',
		];

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
		return [
			[
				'id' => 'civivipgive_manual_sync_settings',
				'type' => 'title',
				'title' => __('Manual Synchronization', 'civivip-give'),
			],
			[
				'name' => __('Sync Now', 'civivip-give'),
				'desc' => __('Click the button below to manually synchronize all Give donations to CiviCRM. <br><br><button type="button" class="button button-primary" id="civivipgive-sync-submit">Sync Now</button><div id="civivipgive-sync-progressbar" role="progressbar" style="margin-top: 15px; display:none;"><div id="progress-label" class="progress-label"></div></div>', 'civivip-give'),
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
