<?php
/*
Plugin Name: GTO Wishlist
Description: A plugin for GTO/CHT, allow user to add product to wishlist.
Version: 1.0
Author: Kael
*/

// Prevent direct access to the file
if (!defined('ABSPATH')) {
    exit;
}

define('GLINT_WISHLIST_VERSION', '1.0');//for cache control, when update this number, the cache updated
define('GLINT_WISHLIST_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GLINT_WISHLIST_PLUGIN_URL', plugin_dir_url(__FILE__));

// Installation hook
register_activation_hook(__FILE__, 'glint_wishlist_install');

function glint_wishlist_install() {
    require_once GLINT_WISHLIST_PLUGIN_DIR . 'includes/class-glint-wishlist.php';
    Glint_Wishlist::install();
}

// Initialize the plugin
add_action('plugins_loaded', 'glint_wishlist_init');

function glint_wishlist_init() {
    // Load the main class
    require_once GLINT_WISHLIST_PLUGIN_DIR . 'includes/class-glint-wishlist.php';
    
    // Initialize
    if (class_exists('Glint_Wishlist')) {
        new Glint_Wishlist();
    }
}

function glint_wishlist_style_and_script() 
{
    // Only load on product pages
    if (!is_product()) return;

    // Enqueue CSS
    wp_enqueue_style(
        'glint-wishlist-style',  
        plugins_url('css/style.css', __FILE__),
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'css/style.css')
    );

    // Enqueue JS with jQuery dependency
    wp_enqueue_script(
        'glint-wishlist-js', 
        plugins_url('js/ajax-wishlist.js', __FILE__),
        array('jquery'), 
        filemtime(plugin_dir_path(__FILE__) . 'js/ajax-wishlist.js'),
        true
    );

    // Localize the script with the correct handle
    wp_localize_script(
        'glint-wishlist-js', 
        'glint_wishlist_vars', 
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('glint_wishlist_nonce')
        )
    );
}
add_action('wp_enqueue_scripts', 'glint_wishlist_style_and_script');