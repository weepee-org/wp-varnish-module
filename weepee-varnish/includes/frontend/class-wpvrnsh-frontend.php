<?php

if (! defined('ABSPATH')) {
    exit;
}
if (! class_exists('WPVarnish_Frontend')) {
    class WPVarnish_Frontend
    {
        private $varnish_processor;
        
        public function __construct()
        {
            $this->init_hooks();
            return;
        }

        private function init_hooks()
        {
            add_action('template_redirect', array( $this, 'add_content_tags_header' ));
            add_action('admin_bar_menu', array( $this, 'add_page_purge_option' ), 99);
            add_action('shutdown', array( $this, 'purge_listener' ));
            add_action('wp_update_comment_count', array( $this, 'process_comment_add' ), 10, 3);
            return;
        }

        public function add_content_tags_header()
        {
            global $wp_query;
            $posts = $wp_query->posts;
            foreach ($posts as $post) {
                $category_ids = wp_get_post_categories($post->ID);
                $tag_ids = wp_get_post_tags($post->ID, array( 'fields' => 'ids' ));

                $this->content_tags[] = $post->post_type . '_' . $post->ID;
                $this->process_object_to_tag($category_ids, WPVarnish_Processor::CATEGORY_PREFIX);
                $this->process_object_to_tag($tag_ids, WPVarnish_Processor::TAG_PREFIX);
            }
            header(WPVarnish_Processor::CONTENT_TAG_HEADER . ': ' . implode(',', array_unique($this->content_tags)));

            return;
        }

        private function process_object_to_tag($object_ids, $prefix)
        {
            if (!empty($object_ids)) {
                foreach ($object_ids as $object_id) {
                    $this->content_tags[] = $prefix . $object_id;
                }
            }
            return;
        }

        public function add_page_purge_option($admin_bar)
        {
            $admin_bar->add_menu(array(
                'id'    => 'purge-page',
                'title' => 'Purge Page (Varnish)',
                'href'  => wp_nonce_url(add_query_arg('purge_current_page', 1), 'purge-current-page')
            ));
            return;
        }

        public function purge_listener()
        {
            if (isset($_GET['purge_current_page']) && check_admin_referer('purge-current-page')) {
                global $wp;
                $current_url = $_SERVER['REQUEST_URI'];
                $current_url_filtered = substr($current_url, 0, strrpos($current_url, '?'));
                $this->varnish_processor->purge_url($current_url_filtered);
            }
            return;
        }
        
        public function process_comment_add($post_id, $new, $old)
        {
            $this->varnish_processor->purge_tags('post_' . $post_id);
            return;
        }
        
        public function setVarnishProcessor($varnish_processor)
        {
            $this->varnish_processor = $varnish_processor;
        }
    }
}
