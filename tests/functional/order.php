<?php
/**
 * Checks on the order a browser checkout just placed, and a real sandbox refund of it.
 *
 *   wp eval-file order.php paid <label>      the newest test order was paid through Moneris
 *   wp eval-file order.php refund <label>    refund part of it through Moneris (sandbox)
 *   wp eval-file order.php logs <label>      the gateway log hides the card and the token
 *
 * @package WPHEKA_Gateway_Moneris
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/lib.php';

/**
 * Render a tab as the admin user.
 *
 * @param string $tab Tab.
 * @return string
 */
function wpheka_moneris_t_render_as_admin( $tab ) {
	wp_set_current_user( get_user_by( 'login', 'admin' )->ID );

	return wpheka_moneris_t_render( $tab );
}

$wpheka_moneris_t_step  = isset( $args[0] ) ? $args[0] : '';
$wpheka_moneris_t_label = isset( $args[1] ) ? $args[1] : '';
$wpheka_moneris_t_order = current(
	wc_get_orders(
		array(
			'billing_email' => 'zz-mon-guest@example.com',
			'limit'         => 1,
			'orderby'       => 'id',
			'order'         => 'DESC',
		)
	)
);

if ( ! $wpheka_moneris_t_order ) {
	wpheka_moneris_t_check( "$wpheka_moneris_t_label: an order was placed", false, 'no test order found' );
	return;
}

if ( 'paid' === $wpheka_moneris_t_step ) {
	$wpheka_moneris_t_notes = wp_list_pluck( wc_get_order_notes( array( 'order_id' => $wpheka_moneris_t_order->get_id() ) ), 'content' );
	wpheka_moneris_t_check( "$wpheka_moneris_t_label: paid with Moneris", 'moneris' === $wpheka_moneris_t_order->get_payment_method(), $wpheka_moneris_t_order->get_payment_method() );
	wpheka_moneris_t_check( "$wpheka_moneris_t_label: order is processing (virtual product, paid)", in_array( $wpheka_moneris_t_order->get_status(), array( 'processing', 'completed' ), true ), $wpheka_moneris_t_order->get_status() );
	wpheka_moneris_t_check( "$wpheka_moneris_t_label: Moneris transaction ID stored", '' !== (string) $wpheka_moneris_t_order->get_transaction_id() );
	wpheka_moneris_t_check( "$wpheka_moneris_t_label: total 12.34 CAD", '12.34' === (string) $wpheka_moneris_t_order->get_total() && 'CAD' === $wpheka_moneris_t_order->get_currency(), $wpheka_moneris_t_order->get_total() . ' ' . $wpheka_moneris_t_order->get_currency() );
	wpheka_moneris_t_check( "$wpheka_moneris_t_label: payment note added", (bool) preg_grep( '/^Moneris payment complete \(Transaction ID: /', $wpheka_moneris_t_notes ), wpheka_moneris_t_dump( $wpheka_moneris_t_notes ) );
	wpheka_moneris_t_check( "$wpheka_moneris_t_label: shows on the Transactions tab", false !== strpos( wpheka_moneris_t_render_as_admin( 'transactions' ), 'data-order="' . $wpheka_moneris_t_order->get_id() . '"' ) );
	return;
}

if ( 'refund' === $wpheka_moneris_t_step ) {
	// The free gateway refunds from the day after the payment: move the paid date back a day.
	$wpheka_moneris_t_order->set_date_paid( time() - DAY_IN_SECONDS - HOUR_IN_SECONDS );
	$wpheka_moneris_t_order->save();
	$wpheka_moneris_t_refund = wc_create_refund(
		array(
			'order_id'       => $wpheka_moneris_t_order->get_id(),
			'amount'         => '2.00',
			'reason'         => 'ZZ MON suite',
			'refund_payment' => true,
		)
	);
	$wpheka_moneris_t_ok     = $wpheka_moneris_t_refund instanceof WC_Order_Refund;
	wpheka_moneris_t_check( "$wpheka_moneris_t_label: previous-day refund accepted by Moneris", $wpheka_moneris_t_ok, is_wp_error( $wpheka_moneris_t_refund ) ? $wpheka_moneris_t_refund->get_error_message() : '' );
	if ( $wpheka_moneris_t_ok ) {
		$wpheka_moneris_t_order = wc_get_order( $wpheka_moneris_t_order->get_id() );
		$wpheka_moneris_t_notes = wp_list_pluck( wc_get_order_notes( array( 'order_id' => $wpheka_moneris_t_order->get_id() ) ), 'content' );
		wpheka_moneris_t_check( "$wpheka_moneris_t_label: refund recorded as sent through Moneris", $wpheka_moneris_t_refund->get_refunded_payment() );
		wpheka_moneris_t_check( "$wpheka_moneris_t_label: refund note added", (bool) preg_grep( '/^Amount Refunded: 2/', $wpheka_moneris_t_notes ), wpheka_moneris_t_dump( $wpheka_moneris_t_notes ) );
		delete_transient( WPHEKA_Gateway_Moneris_Admin_Page::STATS_TRANSIENT );
		$wpheka_moneris_t_stats = WPHEKA_Gateway_Moneris_Admin_Page::stats();
		wpheka_moneris_t_check( "$wpheka_moneris_t_label: refund counted in the stats", $wpheka_moneris_t_stats['refunds'] >= 1 && $wpheka_moneris_t_stats['refunded'] >= 2, wpheka_moneris_t_dump( $wpheka_moneris_t_stats ) );
	}
	return;
}

if ( 'logs' === $wpheka_moneris_t_step ) {
	$wpheka_moneris_t_dir  = trailingslashit( WC_LOG_DIR );
	$wpheka_moneris_t_text = '';
	foreach ( (array) glob( $wpheka_moneris_t_dir . 'wpheka-gateway-moneris-*.log' ) as $wpheka_moneris_t_file ) {
		$wpheka_moneris_t_text .= (string) file_get_contents( $wpheka_moneris_t_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local log file.
	}
	wpheka_moneris_t_check( "$wpheka_moneris_t_label: the gateway logged the purchase", false !== strpos( $wpheka_moneris_t_text, 'Purchase Request' ) );
	wpheka_moneris_t_check( "$wpheka_moneris_t_label: log never holds the full card number", false === strpos( $wpheka_moneris_t_text, '4242424242424242' ) );
	wpheka_moneris_t_check( "$wpheka_moneris_t_label: log masks the card to its last four digits", false !== strpos( $wpheka_moneris_t_text, '************4242' ) );
	wpheka_moneris_t_check( "$wpheka_moneris_t_label: log never holds the API token", false === strpos( $wpheka_moneris_t_text, 'yesguy' ) );
}

