<?php 

defined('ABSPATH') || exit;

class Glint_Wishlist 
{
    private $table_name;

    public static function install() 
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'glint_wishlist';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            wishlist_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            session_key VARCHAR(32) DEFAULT NULL,
            user_id BIGINT(20) DEFAULT NULL,
            product_ids TEXT NOT NULL,
            PRIMARY KEY (wishlist_id),
            INDEX session_key (session_key),
            INDEX user_id (user_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function __construct() 
    {
        add_action('init', [$this, 'set_session_cookie']);
        
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'glint_wishlist';
        $this->init_hooks();
    }

    public function set_session_cookie() 
    {
        if (!isset($_COOKIE['glint_wishlist_key']) && !headers_sent()) {
            $session_key = $this->generate_session_key();
            setcookie('glint_wishlist_key', $session_key, [
                'expires' => time() + 30 * DAY_IN_SECONDS,
                'path' => COOKIEPATH,
                'domain' => COOKIE_DOMAIN,
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
    }

    private function init_hooks() {
        // Shortcodes
        add_shortcode('gto_wishlist_button', array($this, 'render_wishlist_button'));
        add_shortcode('gto_wishlist', array($this, 'render_wishlist'));
        
        // AJAX handlers
        add_action('wp_ajax_glint_wishlist_toggle', array($this, 'ajax_toggle_wishlist'));
        add_action('wp_ajax_nopriv_glint_wishlist_toggle', array($this, 'ajax_toggle_wishlist'));

        add_action('wp_ajax_glint_remove_from_wishlist', array($this, 'ajax_remove_wishlist'));
        add_action('wp_ajax_nopriv_glint_remove_from_wishlist', array($this, 'ajax_remove_wishlist'));
        
        // Merge wishlists when user logs in
        add_action('wp_login', array($this, 'merge_on_login'), 10, 2);
    }

    private function get_user_identifier() {
        $user_id = get_current_user_id();
        
        if ($user_id) {
            return [
                'user_id' => $user_id,
                'session_key' => null
            ];
        }

        $session_key = $_COOKIE['glint_wishlist_key'] ?? $this->generate_session_key();
    
        return [
            'user_id' => null,
            'session_key' => $session_key
        ];
    }

    private function generate_session_key() {
        return md5(uniqid(wp_rand(), true));
    }

    public function get_wishlist() {
        global $wpdb;
        $identifier = $this->get_user_identifier();
        
        if ($identifier['user_id']) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $this->table_name WHERE user_id = %d", 
                $identifier['user_id']
            ));
        } else {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $this->table_name WHERE session_key = %s", 
                $identifier['session_key']
            ));
        }
        
        if ($row) {
            return array_filter(explode(',', $row->product_ids));
        }
        
        return array();
    }

    private function get_wishlist_by_user_id($user_id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE user_id = %d", 
            $user_id
        ));
        
        return $row ? array_filter(explode(',', $row->product_ids)) : array();
    }

    private function get_wishlist_by_session_key($session_key) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE session_key = %s", 
            $session_key
        ));
        
        return $row ? array_filter(explode(',', $row->product_ids)) : array();
    }

    private function update_wishlist($user_id, $session_key, $product_ids) {
        global $wpdb;
        
        $data = array(
            'product_ids' => implode(',', $product_ids)
        );
        
        $format = array('%s');
        
        if ($user_id) {
            $where = array('user_id' => $user_id);
            $where_format = array('%d');
        } else {
            $where = array('session_key' => $session_key);
            $where_format = array('%s');
        }
        
        $wpdb->update($this->table_name, $data, $where, $format, $where_format);
    }

    private function delete_wishlist_by_session_key($session_key) {
        global $wpdb;
        $wpdb->delete(
            $this->table_name,
            array('session_key' => $session_key),
            array('%s')
        );
    }

    public function add_to_wishlist($product_id) {
        global $wpdb;
        $identifier = $this->get_user_identifier();
        $wishlist = $this->get_wishlist();
        
        if (!in_array($product_id, $wishlist)) {
            $wishlist[] = $product_id;
            $product_ids = implode(',', $wishlist);
            
            if ($identifier['user_id']) {
                // Check if user already has a wishlist
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $this->table_name WHERE user_id = %d", 
                    $identifier['user_id']
                ));
                
                if ($existing) {
                    $this->update_wishlist($identifier['user_id'], null, $wishlist);
                } else {
                    $wpdb->insert(
                        $this->table_name,
                        array(
                            'user_id' => $identifier['user_id'],
                            'product_ids' => $product_ids
                        ),
                        array('%d', '%s')
                    );
                }
            } else {
                // Check if session already has a wishlist
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $this->table_name WHERE session_key = %s", 
                    $identifier['session_key']
                ));
                
                if ($existing) {
                    $this->update_wishlist(null, $identifier['session_key'], $wishlist);
                } else {
                    $wpdb->insert(
                        $this->table_name,
                        array(
                            'session_key' => $identifier['session_key'],
                            'product_ids' => $product_ids
                        ),
                        array('%s', '%s')
                    );
                }
            }
            
            return true;
        }
        
        return false;
    }

    public function remove_from_wishlist($product_id) {
        $identifier = $this->get_user_identifier();
        $wishlist = $this->get_wishlist();
        
        if (($key = array_search($product_id, $wishlist)) !== false) {
            unset($wishlist[$key]);
            
            if ($identifier['user_id']) {
                $this->update_wishlist($identifier['user_id'], null, $wishlist);
            } else {
                $this->update_wishlist(null, $identifier['session_key'], $wishlist);
            }
            
            return true;
        }
        
        return false;
    }

    public function toggle_wishlist($product_id) {
        $wishlist = $this->get_wishlist();
        
        if (in_array($product_id, $wishlist)) {
            $this->remove_from_wishlist($product_id);
            return 'removed';
        } else {
            $this->add_to_wishlist($product_id);
            return 'added';
        }
    }

    public function merge_on_login($user_login, $user) {
        if (isset($_COOKIE['glint_wishlist_key'])) {
            $session_key = sanitize_text_field($_COOKIE['glint_wishlist_key']);
            $guest_wishlist = $this->get_wishlist_by_session_key($session_key);
            
            if (!empty($guest_wishlist)) {
                $user_wishlist = $this->get_wishlist_by_user_id($user->ID);
                $merged = array_unique(array_merge($user_wishlist, $guest_wishlist));
                
                // Update user wishlist
                $this->update_wishlist($user->ID, null, $merged);
                
                // Delete guest wishlist
                $this->delete_wishlist_by_session_key($session_key);
                
                // Clear the cookie
                setcookie('glint_wishlist_key', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
            }
        }
    }

    public function ajax_toggle_wishlist(){
        if (!isset($_POST['product_id']) || !is_numeric($_POST['product_id'])) {
            wp_send_json_error('Invalid product ID');
        }
        
        $product_id = intval($_POST['product_id']);
        $result = $this->toggle_wishlist($product_id);
        
        wp_send_json_success(array(
            'status' => $result,
            'count' => count($this->get_wishlist())
        ));
    }

    public function ajax_remove_wishlist(){
        if (!isset($_POST['product_id']) || !is_numeric($_POST['product_id'])) {
           wp_send_json_error('Invalid product ID');
        }
        $product_id = intval($_POST['product_id']);
        $result = $this->remove_from_wishlist($product_id);

        wp_send_json_success(array(
            'status' => $result,
            'count' => count($this->get_wishlist())
        ));
    }

    public function render_wishlist_button($atts) 
    {
        global $product;
        if (!$product) return '';

        $product_id = $product->get_id();
        $wishlist = $this->get_wishlist();
        $is_in_wishlist = in_array($product_id, $wishlist);
        
        wp_localize_script('glint-wishlist-script', 'glint_wishlist_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'add_text' => __('Add to Wishlist', 'glint-wishlist'),
            'remove_text' => __('Remove from Wishlist', 'glint-wishlist')
        ));
        
        ob_start();
        ?>
        <div class="glint-wishlist-button">
            <a href="#" class="wishlist-toggle <?php echo $is_in_wishlist ? 'in-wishlist' : ''; ?>" 
               data-product-id="<?php echo $product_id; ?>">
                <span class="icon">❤</span>
                <span class="text">
                    <?php echo $is_in_wishlist ? __('Remove from Wishlist', 'glint-wishlist') : __('Add to Wishlist', 'glint-wishlist'); ?>
                </span>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_wishlist() {
        $wishlist = $this->get_wishlist();
        
        if (empty($wishlist)) {
            return '<div class="glint-wishlist-empty">' . __('Your wishlist is empty', 'glint-wishlist') . '</div>';
        }

        wp_enqueue_script(
            'ajax-wishlist-remove-btn', 
            GLINT_WISHLIST_PLUGIN_URL . 'js/ajax-wishlist-remove-btn.js',
            array('jquery'), 
            filemtime(GLINT_WISHLIST_PLUGIN_URL . 'js/ajax-wishlist-remove-btn.js'),
            true
        );

        wp_localize_script('ajax-wishlist-remove-btn', 'glint_wishlist_remove_btn_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'empty_message' => __('Your wishlist is empty', 'glint-wishlist')
        ));
        
        
        ob_start();
        ?>
        <div class="glint-wishlist">
            <link rel="stylesheet" id="glint-wishlist-style" href="<?php echo plugin_dir_url(dirname(__FILE__)). 'css/style.css', false ?>" type="text/css">
            <ul class="glint-wishlist-products">
                <?php foreach ($wishlist as $product_id) : 
                    $product = wc_get_product($product_id);
                    if (!$product) continue;
                ?>
                    <li class="glint-wishlist-product">
                        <a href="<?php echo get_permalink($product_id); ?>">
                            <?php echo $product->get_image(); ?>
                            <div class="glint-wishlist-product-inner">
                                <h3><?php echo $product->get_name(); ?></h3>
                                <span class="price"><?php echo $product->get_price_html(); ?></span>
                            </div>
                        </a>
                        <a href="#" class="remove-from-wishlist" data-product-id="<?php echo $product_id; ?>">
                            <?php _e('Remove', 'glint-wishlist'); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }
}
