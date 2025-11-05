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
			file_put_contents( $debug_file, $message . PHP_EOL , FILE_APPEND | LOCK_EX );
		}
	}
	public static function err_log( $message ) {
		$log_file = trailingslashit(CIVIVIPGIVE_TEMPDIR) . 'civivipgive_errors.log';
		file_put_contents( $log_file, $message . PHP_EOL , FILE_APPEND | LOCK_EX );
	}
}
