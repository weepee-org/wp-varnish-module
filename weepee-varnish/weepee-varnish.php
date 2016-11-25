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
        private $varnish_processor;
        
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
            $this->varnish_processor = new WPVarnish_Processor;
            $this->init_hooks();
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

        private function init_hooks(){
            add_action( 'admin_bar_menu', array( $this, 'add_varnish_purge_option' ), 99 );
            add_action( 'shutdown', array( $this, 'purge_listener' ) );   
        }

        public function add_varnish_purge_option($admin_bar){
            $admin_bar->add_menu( array(
                'id'	=> 'purge-all',
                'title' => 'Purge All (Varnish)',
                'href'  => wp_nonce_url( add_query_arg('purge_all', 1), 'purge-all')
            ));
        }

        public function purge_listener() {
            if ( isset($_GET['purge_all']) && check_admin_referer('purge-all') ) {
                $this->varnish_processor->purge_all();
            }
        }

    }

}

function WPVRNSH()
{
    return WPVarnish::instance();
}

$GLOBALS['WPVarnish'] = WPVRNSH();