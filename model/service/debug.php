<?php namespace CivivipGive\Model\Service;

if ( ! defined('ABSPATH') ) { exit; }

/**
 * Debug Class
 *
 * 
 * @copyright 	Copyright (c) 2018, Wanna Pixel, Inc.
 * @license 	https://opensource.org/licenses/gpl-license 	GNU Public License
 * 
 * @since 		1.0
 */
class Debug {
	/**
	 * Log a message.
	 *
	 * @param	mixed 	$message
	 *
	 * @return	void
	 */
	public static function log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$debug_file = trailingslashit(CIVIVIPGIVE_TEMPDIR) . 'civivipgive_debug.log';
			// Ensure directory exists
			if (!file_exists(CIVIVIPGIVE_TEMPDIR)) {
				wp_mkdir_p(CIVIVIPGIVE_TEMPDIR);
			}
			// Convert arrays/objects to string
			if (is_array($message) || is_object($message)) {
				$message = print_r($message, true);
			}
			// Add timestamp
			$message = '[' . date('Y-m-d H:i:s') . '] ' . $message;
			file_put_contents( $debug_file, $message . PHP_EOL , FILE_APPEND | LOCK_EX );
		}
	}

	/**
	 * Log an error message.
	 *
	 * @param	mixed 	$message
	 *
	 * @return	void
	 */
	public static function err_log( $message ) {
		$log_file = trailingslashit(CIVIVIPGIVE_TEMPDIR) . 'civivipgive_errors.log';
		// Ensure directory exists
		if (!file_exists(CIVIVIPGIVE_TEMPDIR)) {
			wp_mkdir_p(CIVIVIPGIVE_TEMPDIR);
		}
		// Convert arrays/objects to string
		if (is_array($message) || is_object($message)) {
			$message = print_r($message, true);
		}
		// Add timestamp
		$message = '[' . date('Y-m-d H:i:s') . '] ' . $message;
		file_put_contents( $log_file, $message . PHP_EOL , FILE_APPEND | LOCK_EX );
	}
}
