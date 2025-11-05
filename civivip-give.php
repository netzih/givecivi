<?php namespace CivivipGive;
/**
 * Plugin Name:     Give CiviCRM Integration
 * Plugin URI:      https://civivip.com/give-civicrm/
 * Description:     Sync Give donations with CiviCRM contributions. Includes billing address sync, payment method mappings, and recurring donations support.
 * Author:          Wanna Pixel, Inc.
 * Author URI:      https://wapix.co/
 * Text Domain:     civivip-give
 * Domain Path:     /languages
 * Version:         0.5.0
 *
 * This product is provided "as is, where is" and with all faults. Seller makes no
 * warranties of any kind whatsoever, express, implied, oral, written, or otherwise,
 * including, without limitation, warranties as to non-infringement, title, patent,
 * merchantability, or fitness for a particular purpose, or warranties arising by
 * custom, trade usage, promise, example, or description; all of which warranties
 * are expressly disclaimed hereby.
 */
/**
 * Main Class
 *
 * NB: See Notices Service for dependency check - related properties and methods.
 * 
 * @copyright 	Copyright (c) 2018, Wanna Pixel, Inc.
 * @license 	https://opensource.org/licenses/gpl-license 	GNU Public License
 *
 * @since 	1.0
 */
final class CivivipGive
{
	function __construct()
	{
		require_once __DIR__ . '/lib/autoloader.php';
		include_once __DIR__ . '/lib/vendor/EDD_SL_Plugin_Updater.php';	// licensing related
		$this->constants();
		add_action('plugins_loaded', [$this, 'init']);
		register_activation_hook(__FILE__, [$this, 'activate']);
		register_deactivation_hook(__FILE__, [$this, 'deactivate']);
	}

	/**
	 * Set up constants.
	 *
	 * @since 	1.0
	 *
	 * @return 	void
	 */
	private function constants()
	{
		if ( ! defined('CIVIVIPGIVE_VERSION') ) {
			define('CIVIVIPGIVE_VERSION', '0.5.0');
		}
		if ( ! defined('CIVIVIPGIVE_FILE') ) {
			define('CIVIVIPGIVE_FILE', __FILE__);
		}
		if ( ! defined('CIVIVIPGIVE_DIR') ) {
			define('CIVIVIPGIVE_DIR', plugin_dir_path(__FILE__) );
		}
		if ( ! defined('CIVIVIPGIVE_URL') ) {
			define('CIVIVIPGIVE_URL', plugin_dir_url(__FILE__) );
		}
		if ( ! defined('CIVIVIPGIVE_BASENAME') ) {
			define('CIVIVIPGIVE_BASENAME', plugin_basename(__FILE__) );
		}
		if ( ! defined('CIVIVIPGIVE_TEMPDIR') ) {
			define('CIVIVIPGIVE_TEMPDIR', trailingslashit(plugin_dir_path(__FILE__) ) . 'tmp');
		}
		if ( ! defined('CIVIVIPGIVE_TEMPDIR_URL') ) {
			define('CIVIVIPGIVE_TEMPDIR_URL', trailingslashit(plugin_dir_url(__FILE__) ) . 'tmp');
		}
		// Licensing related
		if ( ! defined('CIVIVIPGIVE_EDD_SL_STORE_URL') ) {
			define('CIVIVIPGIVE_EDD_SL_STORE_URL', 'https://shop.civivip.com');
		}
		if ( ! defined('CIVIVIPGIVE_EDD_SL_ITEM_ID') ) {
			define('CIVIVIPGIVE_EDD_SL_ITEM_ID', 1483);
		}
	}

	/**
	 * Instantiate some basic classes, as each registers hooks or filters.
	 *
	 * @since 	1.0
	 *
	 * @return 	void
	 */
	public function init()
	{
		new View\Admin;
		new View\Service\Notice;
		new Control\Hamual;
		$this->setUpLicensing();
	}

	/**
	 * Implement Easy Digital ownloads - related functionality.
	 *
	 * @since 	1.0
	 *
	 * @return 	void
	 */
	public function setUpLicensing()
	{
		if(class_exists('Give_License') ) {
			new \Give_License(
				CIVIVIPGIVE_FILE,
				'CiviCRM Integration',
				CIVIVIPGIVE_VERSION,
				'Wanna Pixel, Inc.',
				'give_civicrm_integration_license_key',
				CIVIVIPGIVE_EDD_SL_STORE_URL,
				CIVIVIPGIVE_EDD_SL_STORE_URL,
				trailingslashit(CIVIVIPGIVE_EDD_SL_STORE_URL) . 'checkout/purchase-history'
			);
		}

		if(class_exists('PluginUpdater') ) {	// See lib/vendor/EDD_SL_Plugin_Updater.php
			$give_options = give_get_settings();
			if (isset($give_options['give_civicrm_integration_license_key']) ) {
				$license = $give_options['give_civicrm_integration_license_key'];
				new \PluginUpdater(
					CIVIVIPGIVE_EDD_SL_STORE_URL,
					CIVIVIPGIVE_FILE,
					[
						'version' 	=> CIVIVIPGIVE_VERSION,
						'license' 	=> $license,
						'item_id' 	=> CIVIVIPGIVE_EDD_SL_ITEM_ID,
						'author' 	=> 'Wanna Pixel, Inc.',
						'url' 		=> home_url(),
					]
				);
			}
		}
	}

	/**
	 * Create a custom CiviCRM payment method.
	 *
	 * @since 	1.0
	 *
	 * @return 	void
	 */
	public function activate()
	{
		try {
			civicrm_initialize();
			$result = civicrm_api3(
				'OptionValue',
				'get',
				[
					'option_group_id' 	=> 'payment_instrument',
					'name' 				=> 'See Give',
				]
			);
		} catch (\CiviCRM_API3_Exception $e) {
			Debug::log($e->getMessage() );	// unreliable
		}

		if (is_array($result) && ( ! isset($result['id']) ) ) {	// i.e., NO existing 'See Give'
			try {
				civicrm_initialize();
				civicrm_api3(
					'OptionValue',
					'create',
					[
						'option_group_id' 	=> 'payment_instrument',
						'name' 				=> 'See Give',
						'is_active' 		=> 1,
					]
				);
			} catch (\CiviCRM_API3_Exception $e) {
				Debug::log($e->getMessage() );	// unreliable
			}
		}
	}

	public function deactivate()
	{
		delete_option('civivipgive_firstsync');
/*		if (file_exists(trailingslashit(CIVIVIPGIVE_TEMPDIR) . 'civivipgive_exception.tmp') ) {
			unlink(trailingslashit(CIVIVIPGIVE_TEMPDIR) . 'civivipgive_exception.tmp');
		}
		if (file_exists(trailingslashit(CIVIVIPGIVE_TEMPDIR) . 'civivipgive_percent.tmp') ) {
			unlink(trailingslashit(CIVIVIPGIVE_TEMPDIR) . 'civivipgive_percent.tmp');
		}
*/	}
}

if ( ! class_exists('CivivipGive') ) {
	new CivivipGive;
}
