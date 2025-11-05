<?php namespace CivivipGive\View\Service;

/**
 * Notice Class
 *
 * This service registers certain methods with the admin_notices hook, viz., a check
 * of dependencies at activation, a check of user role at manual sync, and a response
 * to a CiviCRM_API3_Exception during manual sync. In addition, it registers methods
 * with two of our own hooks to track CiviCRM exceptions.
 *
 * NB most notices are actually thrown without recourse to hooks by:
 * 		assets/js/civivipgive_admin_tab.js
 *
 * @copyright 	Copyright (c) 2018, Wanna Pixel, Inc.
 * @license 	https://opensource.org/licenses/gpl-license 	GNU Public License
 * 
 * @since 		1.0
 */
class Notice {
	/**
	 * the acceptable levels for our dependencies
	 * 
	 * @var 	array
	 */
	protected $min_vers = [
		'php' => '5.6',
		'wp' => '4.9',
		'give' => '2.0',
		'civi' => ['legacy' => '4.6.38', 'latest' => '5.4'],
	];

	function __construct()
	{
		add_action('admin_notices', [$this, 'dependencyCheck']);
		add_action('admin_notices', [$this, 'civiCrmFail']);
		// License related
		add_action('admin_init', [$this, 'activateLicense']);
	}

	/**
	 * Verify dependencies exist and versions are supported, deactivate plugin as
	 * necessary and emit appropriate notices to the admin.
	 * 
	 * @return 	void
	 */
	public function dependencyCheck()
	{
		if (function_exists('phpversion') && version_compare(phpversion(), $this->min_vers['php'], '<') ) {
			deactivate_plugins(CIVIVIPGIVE_BASENAME);
			$this->dependencyFail('PHP version ' . $this->min_vers['php']);
			return;
		}

		global $wp_version;
		if (version_compare($wp_version, $this->min_vers['wp'], '<') ) {
			deactivate_plugins(CIVIVIPGIVE_BASENAME);
			$this->dependencyFail('WordPress version ' . $this->min_vers['wp']);
			return;
		}

		if ( ! defined('GIVE_VERSION') || version_compare(GIVE_VERSION, $this->min_vers['give'], '<') ) {
			deactivate_plugins(CIVIVIPGIVE_BASENAME);
			$this->dependencyFail('Give version ' . $this->min_vers['give']);
			return;
		}

		if ( ! defined('CIVICRM_PLUGIN_VERSION') ) {	// i.e., no CiviCRM
			deactivate_plugins(CIVIVIPGIVE_BASENAME);
			$this->dependencyFail('CiviCRM version ' . $this->min_vers['civi']['latest']);
			return;
		}
		civicrm_initialize();
		$civi_version = \CRM_Utils_System::version();
		if (version_compare($civi_version, $this->min_vers['civi']['legacy'], '<') ) { // i.e., too low for legacy support
			deactivate_plugins(CIVIVIPGIVE_BASENAME);
			$this->dependencyFail('CiviCRM version ' . $this->min_vers['civi']['legacy']);
			return;
		}
		if (version_compare($civi_version, $this->min_vers['civi']['legacy'], '=') ) {
			$this->dependencySuccess();
			return;
		}
		if (version_compare($civi_version, $this->min_vers['civi']['latest'], '<') ) {	// i.e., too low for latest
			deactivate_plugins(CIVIVIPGIVE_BASENAME);
			$this->dependencyFail('CiviCRM version ' . $this->min_vers['civi']['latest']);
			return;
		}

		$this->dependencySuccess();
	}

	/**
	 * Assemble and echo a notice encouraging a manual sync. Create a custom field
	 * to track whether first manual sync was peformed and, when so, suppress nag.
	 * 
	 * @return 	void
	 */
	public function dependencySuccess() {
		add_option('civivipgive_firstsync', 1);

		$message = __(
			'Now, to synchronize existing Give donations to CiviContribute, go to the <a href="edit.php?post_type=give_forms&page=give-settings&tab=civivip-give">settings page</a>.',
			'civivip-give'
		);

		if (get_option('civivipgive_firstsync', 1) == 1) {
			echo "<div class=\"notice notice-success\">
					<p>
						<strong>Give CiviCRM Integration is installed and active!</strong> {$message}
					</p>
				</div>";
		}
	}

	/**
	 * Assemble and echo an error message if dependency checks fail.
	 * 
	 * @param 	string 	$msg 	wording that varies in the message
	 * 
	 * @return 	void
	 */
	public function dependencyFail($msg)
	{
		$message = sprintf(
			__(
				"<strong>Activation error:</strong> Give CiviCRM Integration requires at least %s; please update or install as necessary, and try again.",
				'civivip-give'
			),
			$msg
		);

		if (current_user_can('activate_plugins') ) {
			echo "<div class=\"notice notice-error\">
					<p>
						$message
					</p>
				</div>";
		}
		if (isset($_GET['activate']) ) { unset($_GET['activate']); }
	}

	/**
	 * React to CiviCRM-related failures with error notices.
	 * 
	 * @return 	void
	 */
	public function civiCrmFail()
	{
		$exception_file = trailingslashit(CIVIVIPGIVE_TEMPDIR) . 'civivipgive_exception.tmp';

		if (file_exists($exception_file) && filesize($exception_file) ) {
			$number = file_get_contents($exception_file);
			echo "<div class=\"notice notice-error\">
					<p>
						<strong>Give CiviCRM Integration has detected a synchronization error.</strong> CiviCRM has failed to register {$number} donations accurately; see the error log (..plugin-dir/tmp/civivipgive_errors.log) for more information.
					</p>
				</div>";
		}
	}

	public function activateLicense()
	{
		$give_options = give_get_settings();

		if ( ! isset($give_options['give_civicrm_integration_license_key']) ) {
			return;
		}

		$license = $give_options['give_civicrm_integration_license_key'];

		$to_edd_api = [
			'edd_action' => 'activate_license',
			'license'    => $license,
			'item_id'    => CIVIVIPGIVE_EDD_SL_ITEM_ID,
			'url'        => home_url(),
		];

		$resp = wp_remote_post(
			CIVIVIPGIVE_EDD_SL_STORE_URL,
			[
				'timeout' => 20,
				'sslverify' => false,
				'body' => $to_edd_api,
			]
		);
		if (! isset($resp) || is_wp_error($resp)) {
			return;
		}
		
		$resp_data = json_decode(wp_remote_retrieve_body($resp) );

		if (isset($resp_data->success) ) {
			update_option(
				'give_civicrm_integration_license_active',
				$resp_data,
				'no'
			);			
		}
	}
}
