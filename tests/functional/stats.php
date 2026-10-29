<?php
/**
 * The stats strip, the Transactions tab and the getting-started steps, against seeded
 * orders on the order storage WooCommerce uses now (run.sh runs it with HPOS on and off).
 *
 * Nothing is sent to Moneris: refunds are recorded the way WooCommerce records a gateway
 * refund, without calling the gateway. Every order made here is deleted at the end.
 *
 * Run by run.sh:  wp eval-file .../tests/functional/stats.php <mode>
 *
 * @package WPHEKA_Gateway_Moneris
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/lib.php';

$wpheka_moneris_t_mode = isset( $args[0] ) ? $args[0] : 'hpos';
$wpheka_moneris_t_hpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
wpheka_moneris_t_check( "stats ($wpheka_moneris_t_mode): storage is " . ( $wpheka_moneris_t_hpos ? 'HPOS' : 'posts' ), ( 'hpos' === $wpheka_moneris_t_mode ) === $wpheka_moneris_t_hpos );

wp_set_current_user( get_user_by( 'login', 'admin' )->ID );
$wpheka_moneris_t_before = wpheka_moneris_t_option();
wpheka_moneris_t_set_option( array_merge( wpheka_moneris_t_live_settings(), array( 'sandbox' => 'yes' ) ) );
delete_transient( WPHEKA_Gateway_Moneris_Admin_Page::STATS_TRANSIENT );
delete_user_meta( get_current_user_id(), WPHEKA_Gateway_Moneris_Admin_Page::DISMISS_META );

$wpheka_moneris_t_currency = get_woocommerce_currency();

// A status another plugin adds after payment, as Backorder Split adds Backordered.
register_post_status( 'wc-zz-backordered', array( 'label' => 'ZZ Backordered', 'public' => false, 'exclude_from_search' => false, 'show_in_admin_all_list' => true ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- test status.
add_filter(
	'wc_order_statuses',
	static function ( $statuses ) {
		$statuses['wc-zz-backordered'] = 'ZZ Backordered';
		return $statuses;
	}
);
$GLOBALS['wpheka_moneris_t_made'] = array();

/**
 * Make one order.
 *
 * @param string $method   Payment method.
 * @param string $status   Status.
 * @param float  $total    Total.
 * @param int    $days_ago Days since it was created.
 * @param string $currency Currency ('' for the store's).
 * @return WC_Order
 */
function wpheka_moneris_t_order( $method, $status, $total, $days_ago = 0, $currency = '', $paid = true ) {
	$order = wc_create_order();
	$order->set_payment_method( $method );
	$order->set_currency( '' !== $currency ? $currency : get_woocommerce_currency() );
	$order->set_total( $total );
	$order->set_date_created( time() - $days_ago * DAY_IN_SECONDS );
	$order->set_transaction_id( 'moneris' === $method && $paid ? 'zz-txn-' . wp_rand( 1000, 9999 ) : '' );
	$order->update_meta_data( '_zz_mon_test', 1 );
	$order->set_status( $status );
	$order->save();
	// WooCommerce sets a paid date itself when an order reaches processing or completed;
	// set it (or clear it) explicitly so every case says what it means.
	$order->set_date_paid( $paid ? time() - $days_ago * DAY_IN_SECONDS : null );
	$order->save();
	$GLOBALS['wpheka_moneris_t_made'][] = $order->get_id();

	return $order;
}

/**
 * Record a refund the way WooCommerce does after a gateway refund (or a manual one).
 *
 * @param WC_Order $order    Order.
 * @param float    $amount   Amount.
 * @param bool     $gateway  Sent through the gateway.
 * @param int      $days_ago Days since it was made.
 */
function wpheka_moneris_t_refund( $order, $amount, $gateway, $days_ago = 0 ) {
	$refund = wc_create_refund(
		array(
			'order_id'       => $order->get_id(),
			'amount'         => $amount,
			'refund_payment' => false,
		)
	);
	$refund->set_refunded_payment( $gateway );
	$refund->set_date_created( time() - $days_ago * DAY_IN_SECONDS );
	$refund->save();
	$GLOBALS['wpheka_moneris_t_made'][] = $refund->get_id();
}

// What the store already holds (earlier checkouts in the suite may have left Moneris orders):
// every expectation below is this plus what the test adds.
$wpheka_moneris_t_base  = WPHEKA_Gateway_Moneris_Admin_Page::stats();

// While nothing changes, the numbers come from the hour-long cache.
$wpheka_moneris_t_key   = ( $wpheka_moneris_t_hpos ? 'hpos' : 'posts' ) . '-' . get_woocommerce_currency();
$wpheka_moneris_t_fake  = array( 'payments' => 999, 'paid' => 1.0, 'refunds' => 0, 'refunded' => 0.0 ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- short.
set_transient( WPHEKA_Gateway_Moneris_Admin_Page::STATS_TRANSIENT, array( $wpheka_moneris_t_key => $wpheka_moneris_t_fake ), HOUR_IN_SECONDS );
wpheka_moneris_t_check( "stats ($wpheka_moneris_t_mode): read from the cache while nothing changes", $wpheka_moneris_t_fake === WPHEKA_Gateway_Moneris_Admin_Page::stats() );
delete_transient( WPHEKA_Gateway_Moneris_Admin_Page::STATS_TRANSIENT );
$wpheka_moneris_t_empty = ! wc_get_orders(
	array(
		'payment_method' => 'moneris',
		'limit'          => 1,
		'return'         => 'ids',
	)
);
if ( $wpheka_moneris_t_empty ) {
	wpheka_moneris_t_check( "stats ($wpheka_moneris_t_mode): zero with no orders", array( 'payments' => 0, 'paid' => 0.0, 'refunds' => 0, 'refunded' => 0.0 ) === $wpheka_moneris_t_base, wpheka_moneris_t_dump( $wpheka_moneris_t_base ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- short.
	wpheka_moneris_t_check( "transactions ($wpheka_moneris_t_mode): empty state with no orders", false !== strpos( wpheka_moneris_t_render( 'transactions' ), 'No Moneris orders yet.' ) );
	wpheka_moneris_t_check( "getting started ($wpheka_moneris_t_mode): 1 of 3 before a test payment (credentials set, sandbox on)", false !== strpos( wpheka_moneris_t_render( 'settings' ), '>1/3<' ) );
}

// Seed. Counted (paid through Moneris in 30 days): a, b, c, g. Refund counted: 10.00 on a.
$wpheka_moneris_t_a = wpheka_moneris_t_order( 'moneris', 'processing', 100 );
$wpheka_moneris_t_b = wpheka_moneris_t_order( 'moneris', 'completed', 50.5 );
$wpheka_moneris_t_c = wpheka_moneris_t_order( 'moneris', 'refunded', 20 );
wpheka_moneris_t_order( 'moneris', 'failed', 30, 0, '', false );
wpheka_moneris_t_order( 'moneris', 'pending', 40, 0, '', false );
$wpheka_moneris_t_f = wpheka_moneris_t_order( 'cod', 'processing', 70 );
wpheka_moneris_t_order( 'moneris', 'processing', 60, 40 );
wpheka_moneris_t_order( 'moneris', 'processing', 0 );
wpheka_moneris_t_order( 'moneris', 'processing', 25, 0, 'CAD' === $wpheka_moneris_t_currency ? 'USD' : 'CAD' );
$wpheka_moneris_t_g = wpheka_moneris_t_order( 'moneris', 'zz-backordered', 18 );
$wpheka_moneris_t_t = wpheka_moneris_t_order( 'moneris', 'processing', 33 );
$wpheka_moneris_t_t->set_status( 'trash' );
$wpheka_moneris_t_t->save();
wpheka_moneris_t_refund( $wpheka_moneris_t_a, 10, true );
wpheka_moneris_t_refund( $wpheka_moneris_t_a, 3, true, 40 );
wpheka_moneris_t_refund( $wpheka_moneris_t_b, 5, false );
wpheka_moneris_t_refund( $wpheka_moneris_t_f, 7, true );

// New orders and status changes cleared the cached numbers.
wpheka_moneris_t_check( "stats ($wpheka_moneris_t_mode): new orders cleared the cache", false === get_transient( WPHEKA_Gateway_Moneris_Admin_Page::STATS_TRANSIENT ) );
$wpheka_moneris_t_stats = WPHEKA_Gateway_Moneris_Admin_Page::stats();
wpheka_moneris_t_check( "stats ($wpheka_moneris_t_mode): 4 Moneris payments in 30 days (paid date set, any status incl. a plugin's own, not trashed, store currency, above zero)", $wpheka_moneris_t_base['payments'] + 4 === $wpheka_moneris_t_stats['payments'], wpheka_moneris_t_dump( $wpheka_moneris_t_stats ) );
wpheka_moneris_t_check( "stats ($wpheka_moneris_t_mode): payments total 188.50", abs( $wpheka_moneris_t_base['paid'] + 188.5 - $wpheka_moneris_t_stats['paid'] ) < 0.001, wpheka_moneris_t_dump( $wpheka_moneris_t_stats ) );
wpheka_moneris_t_check( "stats ($wpheka_moneris_t_mode): 1 refund sent through Moneris in 30 days", $wpheka_moneris_t_base['refunds'] + 1 === $wpheka_moneris_t_stats['refunds'], wpheka_moneris_t_dump( $wpheka_moneris_t_stats ) );
wpheka_moneris_t_check( "stats ($wpheka_moneris_t_mode): refunded 10.00", abs( $wpheka_moneris_t_base['refunded'] + 10 - $wpheka_moneris_t_stats['refunded'] ) < 0.001, wpheka_moneris_t_dump( $wpheka_moneris_t_stats ) );

$wpheka_moneris_t_html = wpheka_moneris_t_render( 'settings' );
wpheka_moneris_t_check( "stats strip ($wpheka_moneris_t_mode): shows the payment count and the Sandbox mode", false !== strpos( $wpheka_moneris_t_html, number_format_i18n( $wpheka_moneris_t_stats['payments'] ) . ' payments in the last 30 days' ) && false !== strpos( $wpheka_moneris_t_html, 'Test cards only.' ) );
wpheka_moneris_t_check( "getting started ($wpheka_moneris_t_mode): 2 of 3 after a Moneris order", false !== strpos( $wpheka_moneris_t_html, '>2/3<' ) );

// Transactions: Moneris orders only, newest first, with refunds and links.
$wpheka_moneris_t_html = wpheka_moneris_t_render( 'transactions' );
preg_match_all( '/<tr data-order="(\d+)"/', $wpheka_moneris_t_html, $wpheka_moneris_t_rows );
$wpheka_moneris_t_rows = array_map( 'intval', $wpheka_moneris_t_rows[1] );
// The Moneris orders this test made, in the order the tab lists them.
$wpheka_moneris_t_ours = array_values( array_intersect( $wpheka_moneris_t_rows, $GLOBALS['wpheka_moneris_t_made'] ) );
wpheka_moneris_t_check( "transactions ($wpheka_moneris_t_mode): lists the 9 Moneris orders, no other gateway or trash", 9 === count( $wpheka_moneris_t_ours ) && ! in_array( $wpheka_moneris_t_t->get_id(), $wpheka_moneris_t_rows, true ) && ! in_array( $wpheka_moneris_t_f->get_id(), $wpheka_moneris_t_rows, true ), wpheka_moneris_t_dump( $wpheka_moneris_t_rows ) );
wpheka_moneris_t_check( "transactions ($wpheka_moneris_t_mode): newest first (the 40-day-old order is last of them)", ! empty( $wpheka_moneris_t_ours ) && $GLOBALS['wpheka_moneris_t_made'][6] === end( $wpheka_moneris_t_ours ), wpheka_moneris_t_dump( $wpheka_moneris_t_ours ) );
wpheka_moneris_t_check( "transactions ($wpheka_moneris_t_mode): links to the order screen", false !== strpos( $wpheka_moneris_t_html, esc_url( $wpheka_moneris_t_a->get_edit_order_url() ) ) );
wpheka_moneris_t_check( "transactions ($wpheka_moneris_t_mode): shows the transaction ID", false !== strpos( $wpheka_moneris_t_html, '<code>' . esc_html( $wpheka_moneris_t_a->get_transaction_id() ) . '</code>' ) );
wpheka_moneris_t_check( "transactions ($wpheka_moneris_t_mode): shows 13.00 refunded on the first order", (bool) preg_match( '/data-order="' . $wpheka_moneris_t_a->get_id() . '".*?13\.00/s', $wpheka_moneris_t_html ) );

wpheka_moneris_t_check( "transactions ($wpheka_moneris_t_mode): a paid order in another plugin's status shows as paid", (bool) preg_match( '/data-order="' . $wpheka_moneris_t_g->get_id() . '".*?w-pill--ok/s', $wpheka_moneris_t_html ) );

// A new payment clears the cached stats, so it shows at once.
WPHEKA_Gateway_Moneris_Admin_Page::stats();
$wpheka_moneris_t_n = wpheka_moneris_t_order( 'moneris', 'pending', 9, 0, '', false );
$wpheka_moneris_t_n->payment_complete( 'zz-txn-now' );
wpheka_moneris_t_check( "stats ($wpheka_moneris_t_mode): a completed payment clears the cache", false === get_transient( WPHEKA_Gateway_Moneris_Admin_Page::STATS_TRANSIENT ) );
wpheka_moneris_t_check( "stats ($wpheka_moneris_t_mode): and is counted straight away", $wpheka_moneris_t_base['payments'] + 5 === WPHEKA_Gateway_Moneris_Admin_Page::stats()['payments'] );
WPHEKA_Gateway_Moneris_Admin_Page::stats();
wpheka_moneris_t_refund( $wpheka_moneris_t_n, 1, true );
wc_get_order( $wpheka_moneris_t_n->get_id() )->update_status( 'refunded' );
wpheka_moneris_t_check( "stats ($wpheka_moneris_t_mode): a refund or status change clears the cache", false === get_transient( WPHEKA_Gateway_Moneris_Admin_Page::STATS_TRANSIENT ) );

// Clean up.
foreach ( array_reverse( $GLOBALS['wpheka_moneris_t_made'] ) as $wpheka_moneris_t_id ) {
	$wpheka_moneris_t_o = wc_get_order( $wpheka_moneris_t_id );
	if ( $wpheka_moneris_t_o ) {
		$wpheka_moneris_t_o->delete( true );
	}
}
delete_transient( WPHEKA_Gateway_Moneris_Admin_Page::STATS_TRANSIENT );
wpheka_moneris_t_set_option( $wpheka_moneris_t_before );
$wpheka_moneris_t_left = wc_get_orders(
	array(
		'meta_key' => '_zz_mon_test', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- test cleanup check.
		'limit'    => -1,
		'return'   => 'ids',
	)
);
wpheka_moneris_t_check( "stats ($wpheka_moneris_t_mode): test orders deleted", array() === $wpheka_moneris_t_left, wpheka_moneris_t_dump( $wpheka_moneris_t_left ) );
wpheka_moneris_t_check( "stats.php ($wpheka_moneris_t_mode): ran to the end", true );
