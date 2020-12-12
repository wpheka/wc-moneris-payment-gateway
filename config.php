<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MPG_Moneris_Payment_Gateway_Config {
    
    public function __construct() {
        $this->define_constants();
    }
    
    /**
     * Define constant if not already set.
     *
     * @param string      $name  Constant name.
     * @param string|bool $value Constant value.
     */
    private function define( $name, $value ) {
        if ( ! defined( $name ) ) {
                define( $name, $value );
        }
    }

    /**
     * Define Plugin Constants.
     */
    private function define_constants() {
        if( !function_exists('get_plugin_data') ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_data = get_plugin_data( dirname( __FILE__ ). '/wc-moneris-payment-gateway.php');

        if(!empty($plugin_data)) {
            $this->define( 'MPG_MONERIS_PAYMENT_GATEWAY_PLUGIN_DIR', plugin_dir_path( __FILE__ ));
            $this->define( 'MPG_MONERIS_PAYMENT_GATEWAY_PLUGIN_TOKEN', 'mpg-moneris-payment-gateway' );
            $this->define( 'MPG_MONERIS_PAYMENT_GATEWAY_TEXT_DOMAIN', $plugin_data['TextDomain'] );            
            $this->define( 'MPG_MONERIS_PAYMENT_GATEWAY_PLUGIN_VERSION', $plugin_data['Version'] );
            $this->define( 'MPG_MONERIS_PAYMENT_GATEWAY_PLUGIN_ICON', plugins_url( '/assets/images/logo.png', __FILE__ ));
        }
    }
}

new MPG_Moneris_Payment_Gateway_Config();