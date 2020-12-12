<?php
/**
 * WPHEKA Plugin Deactivation Tracker
 *
 * @class       WPHEKA_Deactivation_Tracker
 * @version     1.7.8
 * @category    Class
 * @author      WPHEKA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPHEKA_Deactivation_Tracker {

	/**
	 * URL to the API endpoint
	 *
	 * @var string
	 */
	private static $api_url = 'https://www.wpheka.com/wp-json/wpheka/v1/plugins/feedback';

	/**
	 * Tracker ID
	 *
	 * @var string
	 */
	private static $tracker_id = '9b066a9d2d76c93ee14c52fbbc6c93cba6b28bb6b1d477f097e4da0c7a69ba51';

	/**
	 * Deactivation Modal
	 *
	 * @var string
	 */
	private static $deactivation_modal = 'wpheka-moneris-deactivation-modal';

	/**
	 * Hook into cron event.
	 */
	public static function init() {

		// plugin deactivate actions.
		add_action( 'plugin_action_links_' . plugin_basename( WPHEKA_MONERIS_MAIN_FILE ), array( __CLASS__, 'plugin_action_links' ) );
		add_action( 'admin_footer', array( __CLASS__, 'deactivate_scripts' ) );
		add_action( 'wp_ajax_wpheka_moneris_submit_deactivation', array( __CLASS__, 'send_tracking_deactivation' ) );
	}

	/**
	 * send tracking deactivation data.
	 *
	 * @param boolean $override
	 */
	public static function send_tracking_deactivation() {

		if ( empty( $_POST['deactivation_domain'] ) ) {
			wp_send_json_error( array( 'error' => __( 'Something went wrong. Please try again later.', 'wpheka-gateway-moneris' ) ) );
			wp_die( -1 );
		}

		$feedback_url = self::$api_url;

		$deactivation_domain = isset( $_POST['deactivation_domain'] ) ? sanitize_text_field( wp_unslash( $_POST['deactivation_domain'] ) ) : '';
		$deactivation_license_key = isset( $_POST['deactivation_license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['deactivation_license_key'] ) ) : '';

		$email = isset( $_POST['deactivation_email'] ) ? filter_var( $_POST['deactivation_email'], FILTER_SANITIZE_EMAIL ) : '';

		$reason_id = isset( $_POST['reason_id'] ) ? sanitize_text_field( wp_unslash( $_POST['reason_id'] ) ) : '';
		$reason_info = isset( $_POST['reason_info'] ) ? sanitize_text_field( wp_unslash( $_POST['reason_info'] ) ) : '';

		if ( empty( $reason_info ) ) {
			$deactivation_reason  = empty( $reason_id ) ? '' : $reason_id;
		} else {
			$deactivation_reason  = $reason_info;
		}

		wp_remote_post(
			$feedback_url,
			array(
				'timeout' => 30,
				'body' => array(
					'plugin' => 'WC Moneris Payment Gateway',
					'deactivation_reason' => $deactivation_reason,
					'deactivation_domain' => $deactivation_domain,
					'deactivation_license_key' => $deactivation_license_key,
					'email' => $email,
				),
			)
		);

		wp_send_json_success();

		wp_die();
	}

	/**
	 * Hook into action links and modify the deactivate link
	 *
	 * @param  array $links
	 *
	 * @return array
	 */
	public static function plugin_action_links( $links ) {

		if ( array_key_exists( 'deactivate', $links ) ) {
			$links['deactivate'] = str_replace( '<a', '<a class="' . self::$tracker_id . '-deactivate-link"', $links['deactivate'] );
		}

		return $links;
	}

	/**
	 * Handle the plugin deactivation feedback
	 *
	 * @return void
	 */
	public static function deactivate_scripts() {
		global $pagenow;

		if ( 'plugins.php' != $pagenow ) {
			return;
		}

		$license_key = 'Free';
		$license_domain = get_site_url();
		$license_email = get_option( 'admin_email' );

		$deactivation_modal_class = '.' . self::$deactivation_modal;
		$deactivation_modal_id = self::$tracker_id . '-' . self::$deactivation_modal;

		$reasons = array(
			array(
				'id'          => 'could-not-understand',
				'text'        => 'I couldn\'t understand how to make it work',
				'type'        => 'textarea',
				'placeholder' => 'Would you like us to assist you?',
			),
			array(
				'id'          => 'found-better-plugin',
				'text'        => 'I found a better plugin',
				'type'        => 'text',
				'placeholder' => 'Which plugin?',
			),
			array(
				'id'          => 'not-have-that-feature',
				'text'        => 'The plugin is great, but I need specific feature that you don\'t support',
				'type'        => 'textarea',
				'placeholder' => 'Could you tell us more about that feature?',
			),
			array(
				'id'          => 'is-not-working',
				'text'        => 'The plugin is not working',
				'type'        => 'textarea',
				'placeholder' => 'Could you tell us a bit more whats not working?',
			),
			array(
				'id'          => 'looking-for-other',
				'text'        => 'It\'s not what I was looking for',
				'type'        => '',
				'placeholder' => '',
			),
			array(
				'id'          => 'did-not-work-as-expected',
				'text'        => 'The plugin didn\'t work as expected',
				'type'        => 'textarea',
				'placeholder' => 'What did you expect?',
			),
			array(
				'id'          => 'other',
				'text'        => 'Other',
				'type'        => 'textarea',
				'placeholder' => 'Could you tell us a bit more?',
			),
		);

		?>

		<div class="<?php echo esc_attr( self::$deactivation_modal ); ?>" id="<?php echo $deactivation_modal_id; ?>">
			<div class="<?php echo esc_attr( self::$deactivation_modal ); ?>-wrap">
				<div class="<?php echo esc_attr( self::$deactivation_modal ); ?>-header">
					<h3><?php echo esc_html( 'If you have a moment, please let us know why you are deactivating:', 'wpheka-gateway-moneris' ); ?></h3>
				</div>

				<div class="<?php echo esc_attr( self::$deactivation_modal ); ?>-body">
					<ul class="reasons">
						<?php foreach ( $reasons as $reason ) { ?>
							<li data-type="<?php echo esc_attr( $reason['type'] ); ?>" data-placeholder="<?php echo esc_attr( $reason['placeholder'] ); ?>">
								<label><input type="radio" name="deactivation_reason" value="<?php echo esc_attr( $reason['text'] ); ?>"> <?php echo esc_html( $reason['text'] ); ?></label>
							</li>
						<?php } ?>
					</ul>
					<input type="hidden" name="deactivation_domain" value="<?php echo esc_attr( $license_domain ); ?>">

					<input type="hidden" name="deactivation_license_key" value="<?php echo esc_attr( $license_key ); ?>">

					<input type="hidden" name="email" value="<?php echo esc_attr( $license_email ); ?>">
				</div>

				<div class="<?php echo esc_attr( self::$deactivation_modal ); ?>-footer">
					<a href="#" class="dont-bother-me"><?php echo esc_html( 'I rather wouldn\'t say', 'wpheka-gateway-moneris' ); ?></a>
					<button class="button-secondary"><?php echo esc_html( 'Submit & Deactivate', 'wpheka-gateway-moneris' ); ?></button>
					<button class="button-primary"><?php echo esc_html( 'Cancel', 'wpheka-gateway-moneris' ); ?></button>
				</div>
			</div>
		</div>

		<style type="text/css">
			<?php echo $deactivation_modal_class; ?> {
				position: fixed;
				z-index: 99999;
				top: 0;
				right: 0;
				bottom: 0;
				left: 0;
				background: rgba(0,0,0,0.5);
				display: none;
			}

			<?php echo $deactivation_modal_class; ?>.modal-active {
				display: block;
			}

			<?php echo $deactivation_modal_class; ?>-wrap {
				width: 475px;
				position: relative;
				margin: 10% auto;
				background: #fff;
			}

			<?php echo $deactivation_modal_class; ?>-header {
				border-bottom: 1px solid #eee;
				padding: 8px 20px;
			}

			<?php echo $deactivation_modal_class; ?>-header h3 {
				line-height: 150%;
				margin: 0;
			}

			<?php echo $deactivation_modal_class; ?>-body {
				padding: 5px 20px 20px 20px;
			}

			<?php echo $deactivation_modal_class; ?>-body .reason-input {
				margin-top: 5px;
				margin-left: 20px;
			}

			<?php echo $deactivation_modal_class; ?>-body textarea, <?php echo $deactivation_modal_class; ?>-body input[type="text"]{
				width: 100%;
			}

			<?php echo $deactivation_modal_class; ?>-footer {
				border-top: 1px solid #eee;
				padding: 12px 20px;
				text-align: right;
			}
		</style>

		<script type="text/javascript">
			(function($) {
				$(function() {
					var modal = $( '#<?php echo $deactivation_modal_id; ?>' );
					var deactivateLink = '';

					$( '#the-list' ).on('click', 'a.<?php echo self::$tracker_id; ?>-deactivate-link', function(e) {
						e.preventDefault();

						modal.addClass('modal-active');
						deactivateLink = $(this).attr('href');
						modal.find('a.dont-bother-me').attr('href', deactivateLink).css('float', 'left');
					});

					modal.on('click', 'button.button-primary', function(e) {
						e.preventDefault();

						modal.removeClass('modal-active');
					});

					modal.on('click', 'input[type="radio"]', function () {
						var parent = $(this).parents('li:first');

						modal.find('.reason-input').remove();

						var inputType = parent.data('type'),
							inputPlaceholder = parent.data('placeholder'),
							reasonInputHtml = '<div class="reason-input">' + ( ( 'text' === inputType ) ? '<input type="text" size="40" />' : '<textarea rows="5" cols="45"></textarea>' ) + '</div>';

						if ( inputType !== '' ) {
							parent.append( $(reasonInputHtml) );
							parent.find('input, textarea').attr('placeholder', inputPlaceholder).focus();
						}
					});

					modal.on('click', 'button.button-secondary', function(e) {
						e.preventDefault();

						var button = $(this);

						if ( button.hasClass('disabled') ) {
							return;
						}

						var $radio = $( 'input[type="radio"]:checked', modal );

						var $deactivation_domain = $( 'input[name="deactivation_domain"]', modal );
						var $deactivation_license_key = $( 'input[name="deactivation_license_key"]', modal );
						var $deactivation_email = $( 'input[name="email"]', modal );

						var $selected_reason = $radio.parents('li:first'),
							$input = $selected_reason.find('textarea, input[type="text"]');

						$.ajax({
							url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
							type: 'POST',
							data: {
								action: 'wpheka_moneris_submit_deactivation',
								reason_id: ( 0 === $radio.length ) ? 'none' : $radio.val(),
								reason_info: ( 0 !== $input.length ) ? $input.val().trim() : '',
								deactivation_domain: ( 0 !== $deactivation_domain.length ) ? $deactivation_domain.val().trim() : '',
								deactivation_license_key: ( 0 !== $deactivation_license_key.length ) ? $deactivation_license_key.val().trim() : '',
								deactivation_email: ( 0 !== $deactivation_email.length ) ? $deactivation_email.val().trim() : '',
							},
							beforeSend: function() {
								button.addClass('disabled');
								button.text('Processing...');
							},
							success: function( response ) {
								if ( response.success ) {
									window.location.href = deactivateLink;
								} else {
									window.alert( response.data.error );
									window.location.href = deactivateLink;
								}
							}
						});
					});
				});
			}(jQuery));
		</script>

		<?php
	}
}
WPHEKA_Deactivation_Tracker::init();
