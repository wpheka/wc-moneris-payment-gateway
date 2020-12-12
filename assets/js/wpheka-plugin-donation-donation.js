( function( $ ) {
	$( 'button.notice-dismiss.wpheka-plugin-donation-button-notice-dismiss' ).click( function( e ) {
		e.preventDefault();
		$( this ).closest('.wpheka-plugin-donation-notice').slideUp();
		$.post( wpheka_gateway_moneris_donation_js.ajax_url, {
			action: 'wpheka_gateway_moneris_donation_dismiss_notice',
			nonce: wpheka_gateway_moneris_donation_js.nonce
		} );
	} );
} )( jQuery );