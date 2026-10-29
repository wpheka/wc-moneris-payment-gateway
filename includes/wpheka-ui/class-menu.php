<?php
/**
 * WPHEKA admin UI kit: the shared WPHEKA admin menu.
 *
 * Every WPHEKA plugin puts its page under one top-level "WPHEKA" menu. This is the
 * same contract as wpheka-framework's Admin\Menu (slug, label, capability filter,
 * position and the removed mirror item), written for PHP 7.4 so plugins that do
 * not bundle the framework share the menu with those that do, whichever loads first.
 *
 * @package WPHEKA_Gateway_Moneris\AdminUI
 */

namespace WPHEKA_Gateway_Moneris\AdminUI;

defined( 'ABSPATH' ) || exit;

/**
 * Menu.
 */
class Menu {

	/**
	 * Slug of the shared parent. Never change it: every WPHEKA plugin uses it.
	 */
	const PARENT_SLUG = 'wpheka_plugin_panel';

	/**
	 * Label of the shared parent.
	 */
	const PARENT_LABEL = 'WPHEKA';

	/**
	 * Register the shared parent if no WPHEKA plugin has yet. Call on admin_menu.
	 *
	 * @param string $icon Menu icon URL.
	 * @return bool Whether the parent exists after this call.
	 */
	public static function ensure_parent( $icon ) {
		global $admin_page_hooks;

		if ( isset( $admin_page_hooks[ self::PARENT_SLUG ] ) ) {
			return true;
		}
		if ( ! (bool) apply_filters( 'wpheka_plugin_panel_menu_page_show', true ) ) {
			return false;
		}

		$capability = apply_filters( 'wpheka_plugin_panel_menu_page_capability', 'manage_options' );
		$capability = is_string( $capability ) && '' !== $capability ? $capability : 'manage_options';
		$position   = apply_filters( 'wpheka_plugins_menu_item_position', '55.5' );

		// The parent is a container: WordPress points its link at the first submenu the user may open.
		add_menu_page( self::PARENT_LABEL, self::PARENT_LABEL, $capability, self::PARENT_SLUG, '', $icon, $position );

		// WordPress mirrors a parent as its own first submenu ("WPHEKA" twice). Removed last,
		// once every plugin has added its page.
		add_action(
			'admin_menu',
			static function () {
				remove_submenu_page( self::PARENT_SLUG, self::PARENT_SLUG );
			},
			PHP_INT_MAX
		);

		return true;
	}

	/**
	 * Add a page under the shared parent.
	 *
	 * A user who cannot open the parent but can open this page still sees the WPHEKA
	 * menu: WordPress moves the parent link to the first submenu they may open.
	 *
	 * @param string   $page_title Page title.
	 * @param string   $menu_title Menu label (may hold a count badge).
	 * @param string   $capability Capability for this page.
	 * @param string   $slug       Page slug.
	 * @param callable $callback   Renders the page.
	 * @param string   $icon       Menu icon URL, used if this call creates the parent.
	 * @return string|false Page hook suffix, or false.
	 */
	public static function add_page( $page_title, $menu_title, $capability, $slug, $callback, $icon ) {
		if ( ! self::ensure_parent( $icon ) ) {
			return false;
		}

		return add_submenu_page( self::PARENT_SLUG, $page_title, $menu_title, $capability, $slug, $callback );
	}
}
