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
		add_filter('give-settings_tabs_array', [$this, 'addOurTab']); // Update: filter is now 'give-settings_tabs_array'; ref give main plugin, /includes/admin/class-admin-settings.php line 257
		add_action('give-settings_sections_civivip-give_page', [$this, 'displayTabPage']);
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

		
		$tabs['civivip-give'] = 'CiviCRM Integration (beta version)';
		return $tabs;
	}

	/**
	 * Template
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
				'permission' => current_user_can('administrator'),	//'edit_contributions')
			]
		);
		// HTML
		?>
	    <div class="card" id="civivipgive-sync-card">

	        <h2 class="title">
	        	<?= __('CiviCRM Integration Manual Sync', 'civivip-give'); ?>
	    	</h2>

			<!-- form scaffold for jQuery -->
			<!-- NB the Give tab we're using already includes a form element. -->
	            <label for="civivipgive-sync-submit">
	            	<?= __(
            			'Click to manually synchronize Give donations to CiviContribute.',
            			'civivip-give'
            		); ?>
	            </label>
                <input
                	name="civivipgive-sync-submit" id="civivipgive-sync-submit"
                	class="button button-primary button-hero"
                	type="submit" value="<?= __('Sync', 'civivip-give'); ?>"
            	>
            <!-- /form -->
			
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
