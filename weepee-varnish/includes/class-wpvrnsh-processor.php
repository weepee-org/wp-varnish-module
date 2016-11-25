<?php

if (! defined('ABSPATH')) {
    exit;
}
if (! class_exists('WPVarnish_Processor')) {
    class WPVarnish_Processor
    {
        const CATEGORY_PREFIX = 'cat_';
        const TAG_PREFIX = 'tag_';
        const CONTENT_TAG_HEADER = 'X-Content-Tags';
        const URL_HEADER = 'X-Url-To-Ban';
        const PURGE_ALL_HEADER = 'X-Purge-All';

        private $error_message;
        private $is_connected = false;
        private $purge_addr;

        public function __construct()
        {
            $this->setPurgeAddrAndTest();
            $this->prepareMessages();
            return;
        }

        public function setPurgeAddrAndTest()
        {
            $option_host_ip = get_option('varnish_host_ip');
            $option_host_port = get_option('varnish_host_port');
            $option_use_env = get_option('varnish_use_env');

            if (isset($option_host_port) && isset($option_host_ip) && isset($option_use_env)) {
                $purge_ip = $option_host_ip;
                $purge_port = $option_host_port;

                if ($option_use_env) {
                    $purge_ip = getenv($option_host_ip);
                    $purge_port = getenv($option_host_port);
                }

                $this->purge_addr = $purge_ip . ':' . $purge_port;
                $test_result = $this->test_connection();
                if (is_wp_error($test_result)) {
                    add_action('admin_notices', function () {
                        echo '<div class="notice notice-error is-dismissible">
                              <p>Not connected to Varnish, please configure Varnish under Extra->Varnish</p>
                              </div>';
                    });
                } else {
                    $this->is_connected = true;
                    add_action('admin_notices', function () {
                        echo '<div class="notice notice-success is-dismissible">
                          <p>Connected to Varnish</p>
                          </div>';
                    });
                }
            }

            return;
        }

        private function prepareMessages()
        {
            if ($error_message = get_option('varnish_error_message')) {
                $this->error_message = $error_message;
                add_action('admin_notices', array($this, 'display_error_message'));
                delete_option('varnish_error_message');
            } elseif (get_option('varnish_success_message')) {
                add_action('admin_notices', array($this, 'display_success_message'));
                delete_option('varnish_success_message');
            }
        }

        public function purge_tags($tags_to_ban, $must_process_response = true)
        {
            if ($this->is_connected) {
                $response = wp_remote_request(
                    'http://' . $this->purge_addr,
                    array(
                        'method' => 'PURGE',
                        'headers' => array( static::CONTENT_TAG_HEADER => $tags_to_ban)
                    )
                );
                if ($must_process_response) {
                    $this->handle_varnish_response($response);
                }
            }
            return;
        }

        public function purge_url($url)
        {
            if ($this->is_connected) {
                $response = wp_remote_request(
                    'http://' . $this->purge_addr,
                    array(
                        'method' => 'PURGE',
                        'headers' => array( static::URL_HEADER => $url)
                    )
                );

                $this->handle_varnish_response($response);
            }
            return;
        }

        public function purge_all()
        {
            if ($this->is_connected) {
                $response = wp_remote_request(
                    'http://' . $this->purge_addr,
                    array(
                        'method' => 'PURGE',
                        'headers' => array( static::PURGE_ALL_HEADER => 1)
                    )
                );

                $this->handle_varnish_response($response);
            }
            return;
        }

        public function test_connection()
        {
            $response = wp_remote_request(
                'http://' . $this->purge_addr,
                array(
                    'method' => 'PURGE',
                    'headers' => array( static::URL_HEADER => '/dkflliaork')
                )
            );

            return $response;
        }

        private function handle_varnish_response($response)
        {
            if ($response['response']['code'] == '200') {
                add_option('varnish_success_message', 'true');
            } else {
                add_option('varnish_error_message', $response['response']['message']);
            }
            return;
        }

        public function display_error_message()
        {
            echo '<div class="notice notice-error is-dismissible">
                 <p>' . $this->error_message . '</p>
                 </div>';
        }

        public function display_success_message()
        {
            echo '<div class="notice notice-success is-dismissible">
                  <p>Varnish was updated</p>
                  </div>';
        }
    }
}
