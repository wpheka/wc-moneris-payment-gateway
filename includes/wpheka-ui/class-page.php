<?php
/**
 * WPHEKA admin UI kit: the page shell.
 *
 * Header, tabs, stats, side cards, the "More from WPHEKA" row, footer, save bar and
 * toast. Every plugin screen is built from these, so the screens match.
 *
 * @package WPHEKA_Gateway_Moneris\AdminUI
 */

namespace WPHEKA_Gateway_Moneris\AdminUI;

defined( 'ABSPATH' ) || exit;

/**
 * Page.
 */
class Page {

	/**
	 * Kit version, used in asset handles and URLs.
	 */
	const VERSION = '1.0.0';

	/**
	 * Register the kit's CSS and JS. Call on admin_enqueue_scripts for the plugin's own screen only.
	 *
	 * @param string $prefix   Handle prefix, unique to the plugin.
	 * @param string $base_url URL of the kit's assets folder, with a trailing slash.
	 */
	public static function enqueue( $prefix, $base_url ) {
		$handle = $prefix . '-wpheka-ui';
		wp_enqueue_style( $handle, $base_url . 'admin-ui.css', array(), self::VERSION );
		wp_enqueue_script( $handle, $base_url . 'admin-ui.js', array(), self::VERSION, true );
		wp_localize_script(
			$handle,
			'wphekaUiL10n',
			array(
				/* translators: %s: number of minutes. */
				'minute'  => __( '%s minute', 'wpheka-gateway-moneris' ),
				/* translators: %s: number of minutes. */
				'minutes' => __( '%s minutes', 'wpheka-gateway-moneris' ),
				/* translators: %s: number of hours. */
				'hour'    => __( '%s hour', 'wpheka-gateway-moneris' ),
				/* translators: %s: number of hours. */
				'hours'   => __( '%s hours', 'wpheka-gateway-moneris' ),
				/* translators: %s: number of days. */
				'day'     => __( '%s day', 'wpheka-gateway-moneris' ),
				/* translators: %s: number of days. */
				'days'    => __( '%s days', 'wpheka-gateway-moneris' ),
			)
		);
	}

	/**
	 * Keep WordPress's notices off a kit screen: the page shows its own with notices(),
	 * printed in place, so nothing appears at the top and then moves once the page loads.
	 * Call on the screen's load-{hook} action. Every other admin screen is untouched.
	 *
	 * Nothing hooked to the admin notice actions prints here, the plugin's own included.
	 * A notice another plugin prints some other way is hidden by the kit's stylesheet,
	 * which loads in the page head, so it never shows even for a moment.
	 */
	public static function quiet_notices() {
		// in_admin_header runs after every plugin has hooked its notices and before any prints.
		add_action(
			'in_admin_header',
			static function () {
				foreach ( array( 'admin_notices', 'all_admin_notices', 'network_admin_notices', 'user_admin_notices' ) as $hook ) {
					remove_all_actions( $hook );
				}
			},
			PHP_INT_MAX
		);
		add_filter(
			'admin_body_class',
			static function ( $classes ) {
				return $classes . ' w-quiet-notices';
			}
		);
	}

	/**
	 * The page's own notices, printed where they stay. Call after open().
	 *
	 * @param array[] $notices Each { id, kind (info, warn, error or success), text (limited HTML: a, strong, code, br), link [ label, url ] (opens in a new tab), dismiss (URL that records the dismissal; the notice is removed at once and the URL fetched in the background) }.
	 */
	public static function notices( array $notices ) {
		if ( ! $notices ) {
			return;
		}
		$allowed = array(
			'a'      => array(
				'href'   => array(),
				'target' => array(),
				'rel'    => array(),
			),
			'strong' => array(),
			'code'   => array(),
			'br'     => array(),
		);
		$icons   = array(
			'info'    => 'info',
			'warn'    => 'warn',
			'error'   => 'warn',
			'success' => 'check',
		);

		echo '<div class="w-notices">';
		foreach ( $notices as $n ) {
			$n    = wp_parse_args(
				$n,
				array(
					'id'      => '',
					'kind'    => 'info',
					'text'    => '',
					'link'    => array(),
					'dismiss' => '',
				)
			);
			$kind = isset( $icons[ $n['kind'] ] ) ? $n['kind'] : 'info';
			$id   = '' !== $n['id'] ? $n['id'] : 'w-notice-' . md5( $n['text'] );

			echo '<div class="w-notice w-notice--' . esc_attr( $kind ) . '" id="' . esc_attr( $id ) . '" role="' . ( 'error' === $kind ? 'alert' : 'status' ) . '">';
			echo Icons::get( $icons[ $kind ], '2' ) . '<p>' . wp_kses( $n['text'], $allowed ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
			if ( $n['link'] || $n['dismiss'] ) {
				echo '<span class="w-notice-actions">';
				if ( $n['link'] ) {
					echo '<a class="w-btn w-btn--secondary w-btn--sm" href="' . esc_url( $n['link'][1] ) . '" target="_blank" rel="noopener">' . esc_html( $n['link'][0] ) . '<span class="w-sr"> ' . esc_html__( '(opens in a new tab)', 'wpheka-gateway-moneris' ) . '</span></a>';
				}
				if ( $n['dismiss'] ) {
					echo '<a class="w-close" data-w-dismiss="#' . esc_attr( $id ) . '" href="' . esc_url( $n['dismiss'] ) . '" aria-label="' . esc_attr__( 'Dismiss this notice', 'wpheka-gateway-moneris' ) . '">' . Icons::get( 'xcircle' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
				}
				echo '</span>';
			}
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Open the page: header with title, links, call to action and tabs.
	 *
	 * @param array $args Header settings: icon (URL), name, version, badges (each [ text, kind ] with kind '', 'mint' or 'violet'), subtitle, crumb, links (each [ label, url, icon ]), cta ([ label, url, icon, style ] with style 'pro' or 'ghost') and tabs (each [ label, url, current, count, alert ]).
	 */
	public static function open( array $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'icon'     => '',
				'name'     => '',
				'version'  => '',
				'badges'   => array(),
				'subtitle' => '',
				'crumb'    => '',
				'links'    => array(),
				'cta'      => array(),
				'tabs'     => array(),
			)
		);

		echo '<div class="wpheka-ui"><header class="w-hero"><div class="w-hero-inner"><div class="w-topline">';
		echo '<span class="w-brand">' . Icons::logo() . 'WPHEKA'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		if ( $args['crumb'] ) {
			echo ' <span class="w-crumb">/ ' . esc_html( $args['crumb'] ) . '</span>';
		}
		echo '</span><nav class="w-toplinks" aria-label="' . esc_attr__( 'Help', 'wpheka-gateway-moneris' ) . '">';
		foreach ( $args['links'] as $link ) {
			echo '<a href="' . esc_url( $link[1] ) . '" target="_blank" rel="noopener">' . Icons::get( isset( $link[2] ) ? $link[2] : 'book' ) . '<span class="w-lbl">' . esc_html( $link[0] ) . '</span><span class="w-sr"> ' . esc_html__( '(opens in a new tab)', 'wpheka-gateway-moneris' ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon is static SVG.
		}
		echo '</nav></div><div class="w-title-row"><div class="w-title">';
		if ( $args['icon'] ) {
			echo '<img class="w-title-icon" src="' . esc_url( $args['icon'] ) . '" alt="" width="56" height="56">';
		}
		echo '<div><h1>' . esc_html( $args['name'] ) . '<span class="w-badges">';
		if ( $args['version'] ) {
			echo '<span class="w-badge">v' . esc_html( $args['version'] ) . '</span>';
		}
		foreach ( $args['badges'] as $badge ) {
			echo '<span class="w-badge' . ( empty( $badge[1] ) ? '' : ' w-badge--' . esc_attr( $badge[1] ) ) . '">' . esc_html( $badge[0] ) . '</span>';
		}
		echo '</span></h1>';
		if ( $args['subtitle'] ) {
			echo '<p>' . esc_html( $args['subtitle'] ) . '</p>';
		}
		echo '</div></div>';
		if ( $args['cta'] ) {
			$cta = $args['cta'];
			echo '<a class="w-btn ' . ( 'pro' === $cta[3] ? 'w-btn--pro' : 'w-btn--ghost-inv' ) . '" href="' . esc_url( $cta[1] ) . '" target="_blank" rel="noopener">' . Icons::get( $cta[2], '2' ) . esc_html( $cta[0] ) . '<span class="w-sr"> ' . esc_html__( '(opens in a new tab)', 'wpheka-gateway-moneris' ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon is static SVG.
		}
		echo '</div>';
		if ( $args['tabs'] ) {
			echo '<nav class="w-tabs" aria-label="' . esc_attr( $args['name'] ) . '">';
			foreach ( $args['tabs'] as $tab ) {
				$tab = array_pad( $tab, 5, '' );
				echo '<a class="w-tab" href="' . esc_url( $tab[1] ) . '"' . ( $tab[2] ? ' aria-current="page"' : '' ) . '>' . esc_html( $tab[0] );
				if ( '' !== (string) $tab[3] && 0 !== (int) $tab[3] ) {
					echo '<span class="w-count' . ( $tab[4] ? ' w-count--alert' : '' ) . '">' . esc_html( number_format_i18n( (int) $tab[3] ) ) . '</span>';
				}
				echo '</a>';
			}
			echo '</nav>';
		}
		echo '</div></header>';
		// WordPress prints admin notices after the first heading inside .wrap; give them a home in our width.
		echo '<div class="wrap w-wp-notices"><h1 class="w-sr">' . esc_html( $args['name'] ) . '</h1></div>';
		echo '<div class="w-page">';
	}

	/**
	 * The stats strip.
	 *
	 * @param array[] $stats Each { label, icon, value, meta, up, mint }; value may hold <small>.
	 */
	public static function stats( array $stats ) {
		echo '<section class="w-stats" aria-label="' . esc_attr__( 'At a glance', 'wpheka-gateway-moneris' ) . '">';
		foreach ( $stats as $s ) {
			$s = wp_parse_args(
				$s,
				array(
					'label' => '',
					'icon'  => 'chart',
					'value' => '',
					'meta'  => '',
					'up'    => false,
					'mint'  => false,
				)
			);
			echo '<div class="w-stat"><p class="w-stat-label"><span class="w-ico' . ( $s['mint'] ? ' w-ico--mint' : '' ) . '">' . Icons::get( $s['icon'], '2' ) . '</span>' . esc_html( $s['label'] ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon is static SVG.
			echo '<p class="w-stat-value">' . wp_kses( $s['value'], array( 'small' => array() ) ) . '</p>';
			if ( $s['meta'] ) {
				echo '<p class="w-stat-meta' . ( $s['up'] ? ' is-up' : '' ) . '">' . esc_html( $s['meta'] ) . '</p>';
			}
			echo '</div>';
		}
		echo '</section>';
	}

	/**
	 * Section navigation with the settings search box.
	 *
	 * @param array[] $links Each [ section id, icon, label ].
	 * @param bool    $find  Show the search box.
	 */
	public static function nav( array $links, $find = true ) {
		echo '<nav class="w-nav" aria-label="' . esc_attr__( 'Settings sections', 'wpheka-gateway-moneris' ) . '">';
		if ( $find ) {
			echo '<div class="w-find">' . Icons::get( 'search', '2' ) . '<input type="search" placeholder="' . esc_attr__( 'Search settings', 'wpheka-gateway-moneris' ) . '" aria-label="' . esc_attr__( 'Search settings', 'wpheka-gateway-moneris' ) . '" data-w-find><kbd aria-hidden="true">/</kbd></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon is static SVG.
		}
		foreach ( $links as $i => $link ) {
			echo '<a class="w-nav-link' . ( 0 === $i ? ' is-active' : '' ) . '" href="#' . esc_attr( $link[0] ) . '">' . Icons::get( $link[1] ) . esc_html( $link[2] ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon is static SVG.
		}
		echo '</nav>';
	}

	/**
	 * A card asking for a review on WordPress.org.
	 *
	 * @param string $title Heading.
	 * @param string $text  One or two lines.
	 * @param string $url   Review URL.
	 */
	public static function review_card( $title, $text, $url ) {
		echo '<div class="w-procard"><h3>' . Icons::get( 'star', '2' ) . esc_html( $title ) . '</h3><p>' . esc_html( $text ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon is static SVG.
		echo '<a class="w-btn w-btn--pro w-btn--block" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Leave a review', 'wpheka-gateway-moneris' ) . '<span class="w-sr"> ' . esc_html__( '(opens in a new tab)', 'wpheka-gateway-moneris' ) . '</span></a>';
		echo '<small>' . esc_html__( 'Takes about a minute on WordPress.org', 'wpheka-gateway-moneris' ) . '</small></div>';
	}

	/**
	 * The "Need a hand?" card.
	 *
	 * @param array[] $links Each [ label, url, icon ].
	 */
	public static function help_card( array $links ) {
		$arrow = Icons::get( 'arrow', '2', 'w-arrow' );
		echo '<div class="w-card w-helpcard"><h3>' . esc_html__( 'Need a hand?', 'wpheka-gateway-moneris' ) . '</h3>';
		foreach ( $links as $link ) {
			echo '<a class="w-helplink" href="' . esc_url( $link[1] ) . '" target="_blank" rel="noopener">' . Icons::get( $link[2] ) . esc_html( $link[0] ) . '<span class="w-sr"> ' . esc_html__( '(opens in a new tab)', 'wpheka-gateway-moneris' ) . '</span>' . $arrow . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icons are static SVG.
		}
		echo '</div>';
	}

	/**
	 * Close the page: the plugin row, footer, save bar and toast.
	 *
	 * @param array $args Footer settings: slug (this plugin's wordpress.org slug, left out of the row), icons_url (with a trailing slash), short_name, review_url, form (the settings form id, when the page has one), saved (show the "Settings saved" toast) and error (a message saying why a save failed: shown in a red toast that stays until dismissed, and announced to screen readers; it replaces the saved toast).
	 */
	public static function close( array $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'slug'       => '',
				'icons_url'  => '',
				'short_name' => '',
				'review_url' => '',
				'form'       => '',
				'saved'      => false,
				'error'      => '',
			)
		);

		echo '<section class="w-more" aria-labelledby="w-more-t"><div class="w-more-head"><div><h2 id="w-more-t">' . esc_html__( 'More from WPHEKA', 'wpheka-gateway-moneris' ) . '</h2>';
		echo '<p>' . esc_html__( 'WooCommerce plugins trusted since 2017, with 64,000+ downloads.', 'wpheka-gateway-moneris' ) . '</p></div>';
		echo '<div class="w-more-tools"><button type="button" class="w-iconbtn" data-w-prev aria-label="' . esc_attr__( 'Previous plugins', 'wpheka-gateway-moneris' ) . '">' . Icons::get( 'arrow', '2.2' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon is static SVG.
		echo '<button type="button" class="w-iconbtn" data-w-next aria-label="' . esc_attr__( 'Next plugins', 'wpheka-gateway-moneris' ) . '">' . Icons::get( 'arrow', '2.2' ) . '</button></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon is static SVG.
		echo '<div class="w-more-body"><div class="w-track">';
		foreach ( Catalogue::items() as $item ) {
			if ( $item['slug'] === $args['slug'] ) {
				continue;
			}
			$icon = isset( $item['icon'] ) ? $item['icon'] : $item['slug'];
			echo '<article class="w-plugin"><div class="w-plugin-head"><img src="' . esc_url( $args['icons_url'] . $icon . '.png' ) . '" alt="" width="48" height="48" loading="lazy"><div><h3>' . esc_html( $item['name'] ) . '</h3><div class="w-plugin-cat">' . esc_html( $item['cat'] ) . '</div></div></div>';
			echo '<p>' . esc_html( $item['text'] ) . '</p><div class="w-plugin-foot">';
			echo empty( $item['paid'] ) ? '<span class="w-price">' . esc_html__( 'Free', 'wpheka-gateway-moneris' ) . '</span>' : '<span class="w-price w-price--paid">' . esc_html( $item['paid'] ) . '</span>';
			echo Catalogue::action( $item, $args['slug'] ) . '</div></article>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		}
		echo '</div><div class="w-dots" data-w-dots aria-hidden="true"></div></div></section>';

		$link = array(
			'a' => array(
				'href'   => array(),
				'target' => array(),
				'rel'    => array(),
			),
		);
		/* translators: %s: WPHEKA link. */
		$made = sprintf( __( 'Made with care by %s · WooCommerce plugins since 2017', 'wpheka-gateway-moneris' ), '<a href="https://www.wpheka.com/" target="_blank" rel="noopener">WPHEKA</a>' );
		echo '<footer class="w-foot"><span>' . wp_kses( $made, $link ) . '</span>';
		if ( $args['review_url'] ) {
			/* translators: %s: plugin name. */
			echo '<span>' . esc_html( sprintf( __( 'Enjoying %s?', 'wpheka-gateway-moneris' ), $args['short_name'] ) ) . ' <a href="' . esc_url( $args['review_url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Rate it', 'wpheka-gateway-moneris' ) . ' <span class="w-stars" aria-hidden="true">★★★★★</span><span class="w-sr">' . esc_html__( 'five stars on WordPress.org (opens in a new tab)', 'wpheka-gateway-moneris' ) . '</span></a></span>';
		}
		echo '</footer></div>';

		if ( $args['form'] ) {
			echo '<div class="w-savebar" role="region" aria-label="' . esc_attr__( 'Unsaved changes', 'wpheka-gateway-moneris' ) . '"><span class="w-savebar-text">' . esc_html__( 'Unsaved changes', 'wpheka-gateway-moneris' ) . '</span>';
			echo '<button type="reset" form="' . esc_attr( $args['form'] ) . '" class="w-btn w-btn--ghost-inv w-btn--sm" data-w-discard>' . esc_html__( 'Discard', 'wpheka-gateway-moneris' ) . '</button>';
			echo '<button type="submit" form="' . esc_attr( $args['form'] ) . '" class="w-btn w-btn--primary w-btn--sm">' . esc_html__( 'Save changes', 'wpheka-gateway-moneris' ) . '</button></div>';
		}
		if ( '' !== (string) $args['error'] ) {
			// A failed save must never be followed by "Settings saved".
			$args['saved'] = false;
			echo '<div class="w-toast w-toast--error is-shown" role="alert">';
			echo '<span class="w-toast-err">' . Icons::get( 'warn', '2' ) . '</span><span class="w-toast-msg">' . esc_html( $args['error'] ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon is static SVG.
			echo '<button type="button" class="w-toast-close" data-w-toast-close aria-label="' . esc_attr__( 'Dismiss', 'wpheka-gateway-moneris' ) . '">' . Icons::get( 'xcircle', '2' ) . '</button></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon is static SVG.
		}
		echo '<div class="w-toast' . ( $args['saved'] ? ' is-shown' : '' ) . '" role="status" aria-live="polite">';
		if ( $args['saved'] ) {
			echo '<span class="w-toast-ok">' . Icons::get( 'check', '3' ) . '</span>' . esc_html__( 'Settings saved', 'wpheka-gateway-moneris' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon is static SVG.
		}
		echo '</div></div>';
	}
}
