<?php namespace CivivipGive\Control\Service;

if ( ! defined('ABSPATH') ) { exit; }

/**
 * Progress Class
 *
 * Track progress of Manual@sync and apprise front-end script via cookie.
 *
 * @copyright 	Copyright (c) 2018, Wanna Pixel, Inc.
 * @license 	https://opensource.org/licenses/gpl-license 	GNU Public License
 * 
 * @since 		1.0
 */
class Progress {
	/**
	 * total number of donations
	 *
	 * @since 	1.0
	 * 
	 * @var 	int
	 */
	public static $amount;

	/**
	 * number of donations left to sync
	 *
	 * @since 	1.0
	 * 
	 * @var 	int
	 */
	public static $now = 0;

	/**
	 * Calculate and write to a cookie the present percentage of progress.
	 * 
	 * @param 	int 	$increment 	
	 */
	public static function countDown($increment)
	{
		$percent_file = trailingslashit(CIVIVIPGIVE_TEMPDIR) . 'civivipgive_percent.tmp';
		
		self::$now = self::$now + $increment;
		$percent = self::$now / self::$amount * 100;

		file_put_contents($percent_file, $percent);
	}
}
