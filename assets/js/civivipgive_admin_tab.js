/*globals jQuery:false ajaxurl:false civivipgive_jsparams:false*/
/*eslint-disable no-console*/
/*jshint multistr:true*/
/*!
 * Admin Tab jQuery (UI)
 *
 * @description:     the scripts to handle Ajax POST requests and a progress bar
 *
 * @copyright:   Copyright (c) 2018, Wanna Pixel, Inc.
 * @license:     https://opensource.org/licenses/gpl-license     GNU Public License
 * 
 * @since:       1.0
 */
jQuery(function ($) { 'use strict';
	var post_url = ajaxurl;	// Supplied by WordPress.
	var get_progress_url = civivipgive_jsparams.tempprogfileurl;	// Passed from PHP.
	var permission = civivipgive_jsparams.permission;
	var new_percent;
	var ticktock;
	var notice;

	/**
	 * Show a failure notice.
	 *
	 * @since: 	1.0
	 *
	 * @param: 	species 	string 	the type of error to display
	 */
	function civivipgiveShowNotice(species) {
		switch(species) {
			case 'no permission':	/*eslint-disable-line indent*/
				notice =	/*eslint-disable-line indent*/
					'<div class="notice notice-error">\
						<p><strong>You do not have permission to sync to CiviCRM.</strong> Please contact your WordPress administrator.</p>\
					</div>';
				break;	/*eslint-disable-line indent*/
			case 'ajax error':	/*eslint-disable-line indent*/
				notice =	/*eslint-disable-line indent*/
					'<div class="notice notice-error">\
						<p><strong>Synchronization failed.</strong> There has been a network error; please refresh the browser page and try again.</p>\
					</div>';
				break;	/*eslint-disable-line indent*/
		}
		$('.wp-header-end').after(notice);
	}

	/**
	 * Post to WordPress hook (see 'action' below) via Ajax.
	 *
	 * @since: 	1.0
	 */
	function civivipgiveSyncViaAjaxPost() {
		jQuery.ajax({
			url: post_url,
			method: 'POST',
			data: {
				action: 'civivipgive-sync'
			}
		})
			.fail(function () {
				civivipgiveTimer('stop');
				$('.progress-label').text('');
				$('#civivipgive-sync-progressbar').progressbar({value: 0});
				civivipgiveShowNotice('ajax error');
		});	/*eslint-disable-line indent*/
	}

	/**
	 * Get temporary file and consume contents via Ajax.
	 *
	 * @since: 	1.0
	 */
	function civivipgiveCheckProgressViaAjaxGet() {
		jQuery.ajax({
			url: get_progress_url,
			method: 'GET',
			success: function (resp) {
				console.log('Progress check succeeded.');
				console.log(resp);
				new_percent = parseInt(resp);
				if (new_percent != 0) {	// Variable is '0' by default.
					$('#civivipgive-sync-progressbar').progressbar({value: new_percent});
					$('.progress-label').text(new_percent + '%');
				}
			}
		})
			.fail(function () {
				console.log('Progress check failed');
		});	/*eslint-disable-line indent*/
	}

	/**
	 * Ping server repeatedly to track progress, until called again with 'stop'.
	 *
	 * @since: 	1.0
	 * 
	 * @param: 	string 	action 	whether to start or stop the timer
	 */
	function civivipgiveTimer(action) {
		if (action === 'start') {
			ticktock = window.setInterval(function () {
				civivipgiveCheckProgressViaAjaxGet();
			}, 500);
		}

		if (action === 'stop') {
			console.log('Stopped');
			window.clearInterval(ticktock);
		}
	}
 
	/**
	 * Main script; see inline comments for details.
	 *
	 * @since: 	1.0
	 */
	// Wind up the form.
	$('#give-mainform').submit(function (e) {	// Hoo boy.
		e.preventDefault();
		if (permission) {
			civivipgiveSyncViaAjaxPost();
		}
	});

	// Wind up the button.
	$('#civivipgive-sync-submit').click(function () {
		if (permission) {
			$('#civivipgive-sync-progressbar').progressbar({value: false});    // Set progress bar to make like a barberpole.
			$('.progress-label').text('Preparing to sync . . .');
			civivipgiveTimer('start');
		} else {
			civivipgiveShowNotice('no permission');
		}
	});

	// Initialize the progress bar.
	$('#civivipgive-sync-progressbar').progressbar({
		complete: function () {
			console.log('Progress complete');
			civivipgiveTimer('stop');
		}
	});
});
