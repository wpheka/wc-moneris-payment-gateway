<?php
/**
 * WPHEKA admin UI kit: settings rows.
 *
 * Each method prints one row of a settings card with a real form control, so the
 * form posts without JavaScript. Help text accepts links and <code> only.
 *
 * @package WPHEKA_Gateway_Moneris\AdminUI
 */

namespace WPHEKA_Gateway_Moneris\AdminUI;

defined( 'ABSPATH' ) || exit;

/**
 * Fields.
 */
class Fields {

	/**
	 * Counter for generated ids.
	 *
	 * @var int
	 */
	private static $n = 0;

	/**
	 * A unique id.
	 *
	 * @return string
	 */
	private static function id() {
		return 'w-f' . ( ++self::$n );
	}

	/**
	 * Allowed HTML in help text.
	 *
	 * @return array
	 */
	private static function help_html() {
		return array(
			'a'      => array(
				'href'   => array(),
				'target' => array(),
				'rel'    => array(),
			),
			'code'   => array(),
			'strong' => array(),
			'br'     => array(),
		);
	}

	/**
	 * Label and help block for a row.
	 *
	 * @param string $label Label.
	 * @param string $help  Help (limited HTML).
	 * @param string $control Control id, or '' for a group label.
	 * @return string
	 */
	private static function head( $label, $help, $control ) {
		$out = $control ? '<label class="w-label" for="' . esc_attr( $control ) . '">' . esc_html( $label ) . '</label>' : '<span class="w-label">' . esc_html( $label ) . '</span>';
		if ( '' !== (string) $help ) {
			$out .= '<p class="w-help"' . ( $control ? ' id="' . esc_attr( $control ) . '-h"' : '' ) . '>' . wp_kses( $help, self::help_html() ) . '</p>';
		}

		return '<div>' . $out . '</div>';
	}

	/**
	 * Start a section.
	 *
	 * @param string $id    Anchor id.
	 * @param string $title Title.
	 * @param string $desc  Description (limited HTML).
	 * @param string $card  Extra card class, for example 'w-list' for a list of items.
	 */
	public static function section_start( $id, $title, $desc = '', $card = '' ) {
		echo '<section class="w-section" id="' . esc_attr( $id ) . '" aria-labelledby="' . esc_attr( $id ) . '-t"><div class="w-section-head"><h2 id="' . esc_attr( $id ) . '-t">' . esc_html( $title ) . '</h2>';
		if ( $desc ) {
			echo '<p>' . wp_kses( $desc, self::help_html() ) . '</p>';
		}
		echo '</div><div class="w-card' . ( $card ? ' ' . esc_attr( $card ) : '' ) . '">';
	}

	/**
	 * End a section.
	 */
	public static function section_end() {
		echo '</div></section>';
	}

	/**
	 * Start rows that only apply while a switch is on.
	 *
	 * @param string $id Id the switch points at with `controls`.
	 */
	public static function sub_start( $id ) {
		echo '<div class="w-sub" id="' . esc_attr( $id ) . '">';
	}

	/**
	 * End dependent rows.
	 */
	public static function sub_end() {
		echo '</div>';
	}

	/**
	 * A switch. Posts `off` when unticked, so a cleared box is saved as off.
	 * With `off` set to null nothing posts when unticked, as WooCommerce's settings API
	 * expects: it saves any posted checkbox value, "no" included, as "yes".
	 *
	 * @param array $a { name, checked, label, help, controls, disabled, on, off }.
	 */
	public static function switch_row( array $a ) {
		$a  = wp_parse_args(
			$a,
			array(
				'name'     => '',
				'checked'  => false,
				'label'    => '',
				'help'     => '',
				'controls' => '',
				'disabled' => false,
				'on'       => 'yes',
				'off'      => 'no',
			)
		);
		$id = self::id();
		echo '<div class="w-row">' . self::head( $a['label'], $a['help'], $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in head().
		echo '<span class="w-switch">';
		if ( $a['name'] && ! $a['disabled'] && null !== $a['off'] ) {
			echo '<input type="hidden" name="' . esc_attr( $a['name'] ) . '" value="' . esc_attr( $a['off'] ) . '">';
		}
		echo '<input type="checkbox" role="switch" id="' . esc_attr( $id ) . '"' . ( $a['name'] ? ' name="' . esc_attr( $a['name'] ) . '"' : '' ) . ' value="' . esc_attr( $a['on'] ) . '"' . checked( $a['checked'], true, false ) . disabled( $a['disabled'], true, false );
		if ( $a['help'] ) {
			echo ' aria-describedby="' . esc_attr( $id ) . '-h"';
		}
		if ( $a['controls'] ) {
			echo ' data-w-controls="' . esc_attr( $a['controls'] ) . '" aria-controls="' . esc_attr( $a['controls'] ) . '"';
		}
		echo '><span></span></span></div>';
	}

	/**
	 * Chips: a multiple choice shown as toggle pills.
	 *
	 * @param array $a { name (without []), options value => label, selected [], label, help, search (placeholder of a box that narrows a long list; the list then scrolls) }.
	 */
	public static function chips_row( array $a ) {
		$a  = wp_parse_args(
			$a,
			array(
				'name'     => '',
				'options'  => array(),
				'selected' => array(),
				'label'    => '',
				'help'     => '',
				'search'   => '',
			)
		);
		$id = self::id();
		echo '<div class="w-row w-row--stack">' . self::head( $a['label'], $a['help'], '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in head().
		if ( $a['search'] ) {
			// No name, so it never posts; data-w-nodirty keeps typing in it from showing the save bar.
			echo '<div class="w-find w-chips-find">' . Icons::get( 'search', '2' ) . '<input type="search" placeholder="' . esc_attr( $a['search'] ) . '" aria-label="' . esc_attr( $a['search'] ) . '" aria-controls="' . esc_attr( $id ) . '" data-w-chipfind="' . esc_attr( $id ) . '" data-w-nodirty></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon is static SVG.
		}
		echo '<div class="w-chips' . ( $a['search'] ? ' w-chips--scroll' : '' ) . '" role="group" aria-label="' . esc_attr( $a['label'] ) . '" id="' . esc_attr( $id ) . '">';
		// An empty list still posts, so clearing every chip is saved.
		echo '<input type="hidden" name="' . esc_attr( $a['name'] ) . '[]" value="">';
		foreach ( $a['options'] as $value => $text ) {
			echo '<label class="w-chip"><input type="checkbox" name="' . esc_attr( $a['name'] ) . '[]" value="' . esc_attr( $value ) . '"' . checked( in_array( (string) $value, array_map( 'strval', (array) $a['selected'] ), true ), true, false ) . '>' . esc_html( $text ) . '</label>';
		}
		echo '</div></div>';
	}

	/**
	 * A number with a unit, optionally with a live "= 1 hour" hint for minutes.
	 *
	 * @param array $a { name, value, label, help, unit, min, max, step, minutes }.
	 */
	public static function number_row( array $a ) {
		$a  = wp_parse_args(
			$a,
			array(
				'name'    => '',
				'value'   => '',
				'label'   => '',
				'help'    => '',
				'unit'    => '',
				'min'     => '',
				'max'     => '',
				'step'    => 1,
				'minutes' => false,
			)
		);
		$id = self::id();
		echo '<div class="w-row w-row--top">' . self::head( $a['label'], $a['help'], $id ) . '<div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in head().
		echo '<div class="w-unit"><input type="number" id="' . esc_attr( $id ) . '" name="' . esc_attr( $a['name'] ) . '" value="' . esc_attr( $a['value'] ) . '"';
		foreach ( array( 'min', 'max', 'step' ) as $attr ) {
			if ( '' !== (string) $a[ $attr ] ) {
				echo ' ' . esc_attr( $attr ) . '="' . esc_attr( $a[ $attr ] ) . '"';
			}
		}
		echo $a['help'] ? ' aria-describedby="' . esc_attr( $id ) . '-h"' : '';
		echo $a['minutes'] ? ' data-w-minutes="' . esc_attr( $id ) . '-m"' : '';
		echo '>';
		if ( $a['unit'] ) {
			echo '<span>' . esc_html( $a['unit'] ) . '</span>';
		}
		echo '</div>';
		if ( $a['minutes'] ) {
			echo '<div class="w-unit-hint" id="' . esc_attr( $id ) . '-m" aria-live="polite"></div>';
		}
		echo '</div></div>';
	}

	/**
	 * A segmented choice (radio buttons).
	 *
	 * @param array $a { name, options value => label, value, label, help, stack (choices under the label, for long option names) }.
	 */
	public static function segmented_row( array $a ) {
		$a = wp_parse_args(
			$a,
			array(
				'name'    => '',
				'options' => array(),
				'value'   => '',
				'label'   => '',
				'help'    => '',
				'stack'   => false,
			)
		);
		echo '<div class="w-row' . ( $a['stack'] ? ' w-row--stack' : '' ) . '">' . self::head( $a['label'], $a['help'], '' ) . '<div class="w-seg" role="radiogroup" aria-label="' . esc_attr( $a['label'] ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in head().
		foreach ( $a['options'] as $value => $text ) {
			echo '<label><input type="radio" name="' . esc_attr( $a['name'] ) . '" value="' . esc_attr( $value ) . '"' . checked( (string) $a['value'], (string) $value, false ) . '><span>' . esc_html( $text ) . '</span></label>';
		}
		echo '</div></div>';
	}

	/**
	 * Option cards: several yes/no choices under one label.
	 *
	 * @param array $a { label, help, items: each { name, checked, title, text, on, off } }.
	 */
	public static function options_row( array $a ) {
		$a = wp_parse_args(
			$a,
			array(
				'label' => '',
				'help'  => '',
				'items' => array(),
			)
		);
		echo '<div class="w-row w-row--stack">' . self::head( $a['label'], $a['help'], '' ) . '<div class="w-options" role="group" aria-label="' . esc_attr( $a['label'] ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in head().
		foreach ( $a['items'] as $item ) {
			$item = wp_parse_args(
				$item,
				array(
					'name'    => '',
					'checked' => false,
					'title'   => '',
					'text'    => '',
					'on'      => 'yes',
					'off'     => 'no',
				)
			);
			echo '<label class="w-option"><input type="hidden" name="' . esc_attr( $item['name'] ) . '" value="' . esc_attr( $item['off'] ) . '"><input type="checkbox" name="' . esc_attr( $item['name'] ) . '" value="' . esc_attr( $item['on'] ) . '"' . checked( $item['checked'], true, false ) . '>';
			echo '<span><strong>' . esc_html( $item['title'] ) . '</strong>' . ( $item['text'] ? '<small>' . esc_html( $item['text'] ) . '</small>' : '' ) . '</span></label>';
		}
		echo '</div></div>';
	}

	/**
	 * A text field.
	 *
	 * @param array $a { name, value, label, help, placeholder, stack }.
	 */
	public static function text_row( array $a ) {
		$a  = wp_parse_args(
			$a,
			array(
				'name'        => '',
				'value'       => '',
				'label'       => '',
				'help'        => '',
				'placeholder' => '',
				'stack'       => false,
				'type'        => 'text',
			)
		);
		$id = self::id();
		echo '<div class="w-row' . ( $a['stack'] ? ' w-row--stack' : '' ) . '">' . self::head( $a['label'], $a['help'], $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in head().
		echo '<div><input class="w-input' . ( $a['stack'] ? '' : ' w-control-w' ) . '" type="' . esc_attr( $a['type'] ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $a['name'] ) . '" value="' . esc_attr( $a['value'] ) . '" placeholder="' . esc_attr( $a['placeholder'] ) . '"' . ( $a['help'] ? ' aria-describedby="' . esc_attr( $id ) . '-h"' : '' ) . '></div></div>';
	}

	/**
	 * A textarea.
	 *
	 * @param array $a { name, value, label, help, placeholder, rows }.
	 */
	public static function textarea_row( array $a ) {
		$a  = wp_parse_args(
			$a,
			array(
				'name'        => '',
				'value'       => '',
				'label'       => '',
				'help'        => '',
				'placeholder' => '',
				'rows'        => 4,
			)
		);
		$id = self::id();
		echo '<div class="w-row w-row--stack">' . self::head( $a['label'], $a['help'], $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in head().
		echo '<textarea class="w-textarea" id="' . esc_attr( $id ) . '" name="' . esc_attr( $a['name'] ) . '" rows="' . esc_attr( $a['rows'] ) . '" placeholder="' . esc_attr( $a['placeholder'] ) . '"' . ( $a['help'] ? ' aria-describedby="' . esc_attr( $id ) . '-h"' : '' ) . '>' . esc_textarea( $a['value'] ) . '</textarea></div>';
	}

	/**
	 * A select.
	 *
	 * @param array $a { name, options value => label, value, label, help }.
	 */
	public static function select_row( array $a ) {
		$a  = wp_parse_args(
			$a,
			array(
				'name'    => '',
				'options' => array(),
				'value'   => '',
				'label'   => '',
				'help'    => '',
			)
		);
		$id = self::id();
		echo '<div class="w-row">' . self::head( $a['label'], $a['help'], $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in head().
		echo '<select class="w-select w-control-w" id="' . esc_attr( $id ) . '" name="' . esc_attr( $a['name'] ) . '">';
		foreach ( $a['options'] as $value => $text ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( (string) $a['value'], (string) $value, false ) . '>' . esc_html( $text ) . '</option>';
		}
		echo '</select></div>';
	}

	/**
	 * A read-only status row with a pill.
	 *
	 * @param array $a { label, help, pill, kind: ok|warn|bad|info }.
	 */
	public static function info_row( array $a ) {
		$a = wp_parse_args(
			$a,
			array(
				'label' => '',
				'help'  => '',
				'pill'  => '',
				'kind'  => 'ok',
			)
		);
		echo '<div class="w-row">' . self::head( $a['label'], $a['help'], '' ) . '<span class="w-pill w-pill--' . esc_attr( $a['kind'] ) . '">' . esc_html( $a['pill'] ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in head().
	}

	/**
	 * A list row: icon, label, help, optional switch and link (for example an email).
	 *
	 * @param array $a { icon, label, help, name, checked, link_url, link_label }.
	 */
	public static function list_row( array $a ) {
		$a  = wp_parse_args(
			$a,
			array(
				'icon'       => 'mail',
				'label'      => '',
				'help'       => '',
				'name'       => '',
				'checked'    => false,
				'link_url'   => '',
				'link_label' => '',
			)
		);
		$id = self::id();
		echo '<div class="w-row"><span class="w-list-ico">' . Icons::get( $a['icon'] ) . '</span>' . self::head( $a['label'], $a['help'], $a['name'] ? $id : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG, escaped in head().
		if ( $a['name'] ) {
			echo '<span class="w-switch"><input type="hidden" name="' . esc_attr( $a['name'] ) . '" value="no"><input type="checkbox" role="switch" id="' . esc_attr( $id ) . '" name="' . esc_attr( $a['name'] ) . '" value="yes"' . checked( $a['checked'], true, false ) . '><span></span></span>';
		} else {
			echo '<span></span>';
		}
		if ( $a['link_url'] ) {
			echo '<a class="w-textlink" href="' . esc_url( $a['link_url'] ) . '">' . esc_html( $a['link_label'] ) . Icons::get( 'arrow', '2' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		}
		echo '</div>';
	}

	/**
	 * Read-only key/value rows, for information screens.
	 *
	 * @param array[] $rows Each { label, value, code (monospace value), note (a second, quieter line), pill: [ text, kind ok|warn|bad|info ] }.
	 */
	public static function kv( array $rows ) {
		echo '<dl class="w-kv">';
		foreach ( $rows as $row ) {
			$row   = wp_parse_args(
				$row,
				array(
					'label' => '',
					'value' => '',
					'code'  => false,
					'note'  => '',
					'pill'  => array(),
				)
			);
			$value = $row['code'] ? '<code>' . esc_html( $row['value'] ) . '</code>' : esc_html( $row['value'] );
			echo '<div><dt>' . esc_html( $row['label'] ) . '</dt><dd>' . $value; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			if ( '' !== (string) $row['note'] ) {
				echo '<small class="w-kv-note">' . esc_html( $row['note'] ) . '</small>';
			}
			echo '</dd><dd class="w-kv-state">';
			if ( $row['pill'] ) {
				echo '<span class="w-pill w-pill--' . esc_attr( isset( $row['pill'][1] ) ? $row['pill'][1] : 'info' ) . '">' . esc_html( $row['pill'][0] ) . '</span>';
			}
			echo '</dd></div>';
		}
		echo '</dl>';
	}

	/**
	 * A search box that narrows the key/value rows inside one element. Sections left with
	 * no match are hidden, and the element's kv_empty() card shows when nothing matches.
	 *
	 * @param string $target      Id of the element holding the rows.
	 * @param string $placeholder Placeholder and accessible name.
	 */
	public static function kv_find( $target, $placeholder ) {
		echo '<div class="w-find w-kv-find">' . Icons::get( 'search', '2' ) . '<input type="search" placeholder="' . esc_attr( $placeholder ) . '" aria-label="' . esc_attr( $placeholder ) . '" aria-controls="' . esc_attr( $target ) . '" data-w-kvfind="' . esc_attr( $target ) . '"></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon is static SVG.
	}

	/**
	 * The card shown inside a kv_find() target when the search matches nothing.
	 *
	 * @param string $title Heading.
	 * @param string $text  One line.
	 */
	public static function kv_empty( $title, $text ) {
		echo '<div class="w-card w-empty" data-w-kv-empty hidden><span class="w-ico w-ico--lg">' . Icons::get( 'search' ) . '</span><h3>' . esc_html( $title ) . '</h3><p>' . esc_html( $text ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
	}

	/**
	 * The "No settings match" card shown when a search finds nothing.
	 */
	public static function find_empty() {
		echo '<div class="w-card w-empty" data-w-find-empty hidden><span class="w-ico w-ico--lg">' . Icons::get( 'search' ) . '</span><h3>' . esc_html__( 'No settings match', 'wpheka-gateway-moneris' ) . '</h3><p>' . esc_html__( 'Try another word, or clear the search to see every setting.', 'wpheka-gateway-moneris' ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
	}
}
