<?php
class MPG_Moneris_Payment_Gateway_Frontend {

	public function __construct() {
		//enqueue scripts
		add_action('wp_enqueue_scripts', array(&$this, 'frontend_scripts'));
		//enqueue styles
		add_action('wp_enqueue_scripts', array(&$this, 'frontend_styles'));

		add_action( 'mpg_Moneris_Payment_Gateway_frontend_hook', array(&$this, 'mpg_Moneris_Payment_Gateway_frontend_function'), 10, 2 );

	}

	function frontend_scripts() {
		global $MPG_Moneris_Payment_Gateway;
		$frontend_script_path = $MPG_Moneris_Payment_Gateway->plugin_url . 'assets/frontend/js/';
		$frontend_script_path = str_replace( array( 'http:', 'https:' ), '', $frontend_script_path );
		$pluginURL = str_replace( array( 'http:', 'https:' ), '', $MPG_Moneris_Payment_Gateway->plugin_url );
		$suffix 				= defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		
		// Enqueue your frontend javascript from here
	}

	function frontend_styles() {
		global $MPG_Moneris_Payment_Gateway;
		$frontend_style_path = $MPG_Moneris_Payment_Gateway->plugin_url . 'assets/frontend/css/';
		$frontend_style_path = str_replace( array( 'http:', 'https:' ), '', $frontend_style_path );
		$suffix 				= defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		// Enqueue your frontend stylesheet from here
	}
	
	function dc_mpg_Moneris_Payment_Gateway_frontend_function() {
	  // Do your frontend work here
	  
	}

}
