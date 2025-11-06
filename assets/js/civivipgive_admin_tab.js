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
	// Wind up the button.
	$('#civivipgive-sync-submit').click(function () {
		if (permission) {
			$('#civivipgive-sync-progressbar').progressbar({value: false});    // Set progress bar to make like a barberpole.
			$('.progress-label').text('Preparing to sync . . .');

			// Start sync via Ajax
			civivipgiveSyncViaAjaxPost();

			// Start polling after a brief delay to allow the progress file to be created
			setTimeout(function() {
				civivipgiveTimer('start');
			}, 1000);
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

	// Stripe Payment Intent conversion button
	$('#civivipgive-stripe-convert-submit').click(function () {
		var $button = $(this);
		var $resultDiv = $('#civivipgive-stripe-convert-result');

		// Disable button and show loading state
		$button.prop('disabled', true).val('Converting...');
		$resultDiv.html('<p>Converting Payment Intent IDs to Charge IDs...</p>').show();

		// Make Ajax request
		jQuery.ajax({
			url: ajaxurl,
			method: 'POST',
			data: {
				action: 'civivipgive-stripe-convert'
			},
			success: function (response) {
				console.log('Stripe conversion response:', response);

				if (response.success) {
					var message = '<div class="notice notice-success inline">';
					message += '<p><strong>Conversion completed successfully!</strong></p>';
					message += '<ul>';
					message += '<li>Processed: ' + response.data.processed + '</li>';
					message += '<li>Updated: ' + response.data.updated + '</li>';
					message += '<li>Failed: ' + response.data.failed + '</li>';
					message += '</ul></div>';
					$resultDiv.html(message);
				} else {
					var errorMessage = '<div class="notice notice-error inline">';
					errorMessage += '<p><strong>Conversion failed:</strong> ' + response.data.message + '</p>';
					errorMessage += '</div>';
					$resultDiv.html(errorMessage);
				}
			},
			error: function (xhr, status, error) {
				console.log('Stripe conversion error:', error);
				var errorMessage = '<div class="notice notice-error inline">';
				errorMessage += '<p><strong>Network error:</strong> Please try again.</p>';
				errorMessage += '</div>';
				$resultDiv.html(errorMessage);
			},
			complete: function () {
				// Re-enable button
				$button.prop('disabled', false).val('Convert Payment Intents');
			}
		});
	});
});
