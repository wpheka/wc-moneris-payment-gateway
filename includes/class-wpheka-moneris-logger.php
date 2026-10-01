<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Log all things!
 *
 * @since 1.9
 * @version 1.9
 */
class WPHEKA_Moneris_Logger {

	public static $logger;
	const WPHEKA_MONERIS_LOG_FILENAME = 'wpheka-gateway-moneris';

	/**
	 * Utilize WC logger class
	 *
	 * @since 1.9
	 * @version 1.9
	 */
	public static function log( $message, $start_time = null, $end_time = null ) {
		if ( ! class_exists( 'WC_Logger' ) ) {
			return;
		}

		if ( apply_filters( 'wpheka_moneris_logging', true, $message ) ) {
			if ( empty( self::$logger ) ) {
				self::$logger = wc_get_logger();
			}

			if ( ! is_null( $start_time ) ) {

				// Real Unix timestamps, so the subtraction below is a true
				// duration. current_time( 'timestamp' ) is shifted by the site's
				// UTC offset and is not one.
				$formatted_start_time = self::format_time( $start_time );
				$end_time             = is_null( $end_time ) ? time() : $end_time;
				$formatted_end_time   = self::format_time( $end_time );
				$elapsed_time         = round( abs( $end_time - $start_time ) / 60, 2 );

				$log_entry  = "\n" . '====Moneris Version: ' . WPHEKA_MONERIS_VERSION . '====' . "\n";
				$log_entry .= '====Start Log ' . $formatted_start_time . '====' . "\n" . $message . "\n";
				$log_entry .= '====End Log ' . $formatted_end_time . ' (' . $elapsed_time . ')====' . "\n\n";

			} else {
				$log_entry  = "\n" . '====Moneris Version: ' . WPHEKA_MONERIS_VERSION . '====' . "\n";
				$log_entry .= '====Start Log====' . "\n" . $message . "\n" . '====End Log====' . "\n\n";

			}

			self::$logger->debug( $log_entry, array( 'source' => self::WPHEKA_MONERIS_LOG_FILENAME ) );
		}
	}

	/**
	 * Format a Unix timestamp in the site's time zone.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	private static function format_time( $timestamp ) {
		$format = get_option( 'date_format' ) . ' g:ia';

		// wp_date() needs WordPress 5.3; this plugin still declares 4.9.
		if ( function_exists( 'wp_date' ) ) {
			return wp_date( $format, $timestamp );
		}

		return date_i18n( $format, $timestamp + (int) ( (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
	}
}
