<?php

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('WPVarnish_Admin')) {
    class WPVarnish_Admin
    {
        private $varnish_processor;

        public function __construct()
        {
            $this->init_hooks();
            return;
        }

        private function init_hooks()
        {
            add_action('transition_post_status',  array($this, 'process_post_actions'), 10, 3);
            add_action('edit_terms', array( $this, 'process_term_edit' ), 10, 2);
            add_action('delete_term', array( $this, 'process_term_delete' ), 10, 3);
            add_action('wp_update_comment_count', array( $this, 'process_post_related_actions' ), 10, 3);
            add_action('delete_attachment', array( $this, 'process_post_related_actions' ), 10, 3);
            add_action('admin_bar_menu', array( $this, 'add_varnish_purge_all_option' ), 99);
            add_action('init', array( $this, 'add_varnish_purge_all_listener' ));
            add_action('admin_menu', array( $this, 'add_varnish_settings_page'), 69);
            add_action('admin_init', array($this, 'update_varnish_settings'));

            return;
        }

        public function process_post_actions($new_status, $old_status, $post)
        {
            $accepted_new_statuses = array('publish', 'trash');
            if (in_array($new_status, $accepted_new_statuses) && $old_status == 'publish') {
                $this->varnish_processor->purge_tags($post->post_type . '_' . $post->ID);
            } elseif ($new_status == 'publish' && $old_status != 'publish') {
                $this->varnish_processor->purge_url('/');
            }
            return;
        }

        public function process_term_edit($term_id, $taxonomy)
        {
            $prefix = $this->get_prefix($taxonomy);
            $this->varnish_processor->purge_tags($prefix . $term_id);
            return;
        }

        public function process_term_delete($term_id, $tt_id, $taxonomy, $deleted_term)
        {
            $prefix = $this->get_prefix($taxonomy);
            $this->varnish_processor->purge_tags($prefix . $term_id, false);
            return;
        }

        private function get_prefix($taxonomy)
        {
            $prefix = WPVarnish_Processor::CATEGORY_PREFIX;
            if ($taxonomy == 'post_tag') {
                $prefix = WPVarnish_Processor::TAG_PREFIX;
            }

            return $prefix;
        }

        public function process_post_related_actions($post_id)
        {
            $this->varnish_processor->purge_tags('post_' . $post_id);
            return;
        }

        public function setVarnishProcessor($varnish_processor)
        {
            $this->varnish_processor = $varnish_processor;
        }

        public function add_varnish_purge_all_option($admin_bar)
        {
            $admin_bar->add_menu(array(
                'id'    => 'purge-all',
                'title' => 'Purge All (Varnish)',
                'href'  => wp_nonce_url(add_query_arg('purge_all', 1), 'purge-all')
            ));
            return;
        }

        public function add_varnish_purge_all_listener()
        {
            if (isset($_GET['purge_all'])) {
                $this->varnish_processor->purge_all();
            }
            return;
        }

        public function add_varnish_settings_page()
        {
            add_submenu_page(
                'tools.php',
                'Varnish',
                'Varnish',
                'manage_options',
                'varnish_settings_slug',
                array($this, 'get_settings_page_contents')
            );

            return;
        }

        public function get_settings_page_contents()
        {
            ?>
<div class="wrap">
    <h1>Varnish settings</h1>
    <form method="post" action="options.php">
        <?php settings_fields('weepee-varnish-settings'); ?>
        <?php do_settings_sections('weepee-varnish-settings'); ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Use environment variables to fetch host and port?</th>
                <td>
                    <select name="varnish_use_env">
                        <option value="0" <?php if (get_option('varnish_use_env') == 0): ?>selected<?php endif; ?>>No</option>
                        <option value="1" <?php if (get_option('varnish_use_env') == 1): ?>selected<?php endif; ?>>Yes</option>
                    </select>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Varnish host IP:</th>
                <td>
                    <input type="text" name="varnish_host_ip" 
                           value="<?php echo get_option('varnish_host_ip'); ?>" placeholder="IP or env var containing"/>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Varnish host Port:</th>
                <td>
                    <input type="text" name="varnish_host_port" 
                           value="<?php echo get_option('varnish_host_port'); ?>" placeholder="Port or env var containing"/>
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div>
<?php

                                                      return;
        }

        public function update_varnish_settings()
        {
            register_setting('weepee-varnish-settings', 'varnish_use_env');
            register_setting('weepee-varnish-settings', 'varnish_host_ip');
            register_setting('weepee-varnish-settings', 'varnish_host_port');
            return;
        }
    }
}
