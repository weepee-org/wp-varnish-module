<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
class WPVarnish_Processor {
    const CATEGORY_PREFIX = 'cat_';
    const TAG_PREFIX = 'tag_';
    const CONTENT_TAG_HEADER = 'X-Content-Tags';
    const URL_HEADER = 'X-Url-To-Ban';

    private $purge_addr = '';

    public function __construct() {
        $env_variable_for_purge_ip = getenv('ENV_FOR_PURGE_IP');
        $env_variable_for_purge_port = getenv('ENV_FOR_PURGE_PORT');
        $purge_ip = getenv($env_variable_for_purge_ip);
        $purge_port = getenv($env_variable_for_purge_port);
        $this->purge_addr = $purge_ip . ':' . $purge_port;
    }

    public function purge_tags($tags_to_ban) {
        $response = wp_remote_request(
            'http://' . $this->purge_addr,
            '',
            array(
                'method' => 'PURGE',
                'headers' => array( static::CONTENT_TAG_HEADER => $tags_to_ban) 
            ) 
        );
        
        $this->handle_varnish_response($response);
        return;
    }

    public function purge_url($url) {
        $response = wp_remote_request(
            'http://' . $this->purge_addr,
            array(
                'method' => 'PURGE',
                'headers' => array( static::URL_HEADER => $url) 
            )
        );
        
        $this->handle_varnish_response($response);
        return;
    }
    
    private function handle_varnish_response($response) {
        
    }

}