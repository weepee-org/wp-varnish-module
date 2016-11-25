<?php
/**
 * Plugin Name: Weepee Varnish
 * Plugin URI: https://www.weepee.io/
 * Description: A Varnish extension for Wordpress + Openshift
 * Version: 1.0
 * Author: Toon Van Dooren
 * Author URI: https://github.com/weepee-org
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WPVarnish'))
{
    final class WPVarnish
    {
        protected static $_instance = null;
        private $content_tags = array();

        public static function instance()
        {
            if (is_null(self::$_instance)) {
                self::$_instance = new self();
            }

            return self::$_instance;
        }
        
         public function __construct()
        {
            $this->init_includes();
            return;
        }

        private function init_includes()
        {
            include_once( 'includes/class-wpvrnsh-processor.php' );
            if ( $this->is_request( 'admin' ) ) {
                include_once( 'includes/admin/class-wpvrnsh-admin.php' );
            } elseif( $this->is_request( 'frontend' ) ) {
                include_once( 'includes/frontend/class-wpvrnsh-frontend.php' );
            }
        }

        private function is_request( $type ) {
            switch ( $type ) {
                case 'admin' :
                return is_admin();
                case 'frontend' :
                return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
            }
        }
        
    }

}

function WPVRNSH()
{
    return WPVarnish::instance();
}

$GLOBALS['WPVarnish'] = WPVRNSH();