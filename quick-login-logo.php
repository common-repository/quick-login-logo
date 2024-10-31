<?php

/**
 * Quick Login Logo
 *
 * @link https://exorank.com
 *
 * @author ExoRank
 * @version 1.0
 * @package Quick_Login_Logo
 */

/*
Plugin Name: Quick Login Logo
Plugin URI: https://exorank.com
Description: Change your login logo.
Version: 1.0
Author: ExoRank
Author URI: https://exorank.com/freya
Text Domain: quick-login-logo
Domain Path: /languages/
License: EULA
*/


//Enable the plugin for the init hook, but only if WP is loaded. Calling this php file directly will do nothing.
if(defined('ABSPATH') && defined('WPINC')) {
    add_action("wp_loaded",array("QuickLoginLogo","init"));
}

/**
 * Main class for Quick Login Logo, does it all.
 *
 * @package quick_login_logo
 * @todo Uninstall plugin hook
 * @todo I18n Support
 */

class QuickLoginLogo
{
    /**
     * @const VERSION The current plugin version
     */
    const VERSION = '1.0';

    /**
     * @const QuickURL Link to Quickweb site
     */
    const QuickURL = 'https://exorank.com';

    /**
     * Fire up the plugin and register them hooks
     */
    public static function init()
    {
        load_plugin_textdomain('quick-login-logo', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        add_action('admin_menu', array('QuickLoginLogo', 'registerAdminMenu'));
        add_filter('plugin_action_links', array('QuickLoginLogo', 'registerPluginSettingsLink'),10,2);
        add_action('wp_ajax_getImageData', array('QuickLoginLogo', 'getImageData'));
        add_action('wp_ajax_displayPreviewImg', array('QuickLoginLogo', 'displayPreviewImg'));
        add_action('login_head', array('QuickLoginLogo', 'replaceLoginLogo'));
        add_filter('login_headerurl', array('QuickLoginLogo', 'replaceLoginUrl'));
        add_filter("login_headertitle", array('QuickLoginLogo', 'replaceLoginTitle'));
        register_uninstall_hook(self::getBaseName(), array('QuickLoginLogo', 'uninstall'));

        //Load only on plugin admin page
        if (isset($_GET['page']) && $_GET['page'] == self::getBaseName()) {
            add_action('admin_enqueue_scripts', array('QuickLoginLogo', 'myAdminScriptsAndStyles'));
        }
    }
    /**
     * Load scripts and styles for plugin admin page
     */
    public static function myAdminScriptsAndStyles()
    {
        wp_register_style('quick-login-logo', self::getPluginDir() . '/quick-login-logo-min.css', array(), self::VERSION);
        wp_register_script('quick-login-logo', self::getPluginDir() . '/quick-login-logo-min.js', array('jquery','media-upload','thickbox','underscore'), self::VERSION);

        wp_enqueue_media();
        wp_enqueue_style('quick-login-logo');
        wp_enqueue_script('quick-login-logo');
    }

    /**
     * Setup admin menu and add options page
     */
    public static function registerAdminMenu()
    {
        if (function_exists('add_options_page')) {
            $page_title = __('Quick Login Logo Settings', 'quick-login-logo');
            $menu_title = 'Quick Login Logo';
            $capability = 'manage_options';
            $menu_slug = self::getBaseName();
            $function = array('QuickLoginLogo','showOptionsPage');

            add_options_page($page_title, $menu_title, $capability, $menu_slug, $function);
        }
    }

    /**
     * Add settings link to plugin page
     *
     * @param array $links Array of plugin option links
     * @param string $file Handle to plugin filename
     * @return array Modified list of plugin option links
     */
    public static function registerPluginSettingsLink($links, $file)
    {
        $this_plugin = self::getBaseName();

        if ($file == $this_plugin) {
            $settings_link = '<a href="' . admin_url() . 'options-general.php?page=' . $this_plugin . '">' . __('Settings', 'quick-login-logo') . '</a>';
            array_unshift($links, $settings_link);
        }

        return $links;
    }

    /**
     * Generate the HTML to display the plugin settings page
     *
     * @TODO seperate presentation logic
     */
    public static function showOptionsPage()
    {
        ?>

        <div class="wrap quick-login-logo">
            <?php screen_icon('edit-pages'); ?>
            <h2>Quick Login Logo</h2>

            <div class="updated fade update-status">
                <p><strong><?php _e('Settings Saved', 'quick-login-logo'); ?></strong></p>
            </div>

            <p><?php printf(__('by %1$s from %2$s', 'quick-login-logo'), '<strong>Alex Rogers</strong>', '<strong><a href="http://www.Quickweb.com.au" title="Quickweb web design and development">Quickweb.com.au</a></strong>'); ?></p>

            <h3><?php _e('How it Works', 'quick-login-logo'); ?></h3>
            <ol>
                <li><?php _e('Upload Logo By Clicking On The Upload Button', 'quick-login-logo'); ?></li>
                <li><?php _e('Select your desired image size and click "insert into post".', 'quick-login-logo'); ?></li>
                <li><?php _e('Finished!', 'quick-login-logo'); ?></li>
            </ol>
            <form class="inputfields">
                <input id="upload-input" type="text" size="36" name="upload image" class="upload-image" value="" />
                <input id="upload-button" type="button" value="<?php _e('Upload Image', 'quick-login-logo'); ?>" class="upload-image" />
                <?php wp_nonce_field('quick_login_logo_action','quick_login_logo_nonce'); ?>
            </form>
            <div class="img-holder">
                <p><?php _e('Here is the preview of your selected image', 'quick-login-logo'); ?></p>
                <div class="img-preview"></div>
            </div>
        </div>

        <?php
    }

    /**
     * Replace the login logo on wp-admin
     */
    public static function replaceLoginLogo()
    {
        $img_data = get_option('quick_login_logo');

        // use https for background-image if on ssl
        if (is_ssl()) {
            $img_data['src'] = preg_replace( "/^http:/i", "https:", $img_data['src'] );
        }

        if ($img_data) {
            $style = '<style type="text/css">';
            $style .= sprintf('.login h1 a { background: transparent url("%s") no-repeat center top; background-size:%spx %spx; height: %spx; width:auto; }', $img_data['src'], $img_data['width'], $img_data['height'], $img_data['height']);
			$style .= '</style>';
            $style .= "\r\n" . '<!-- Quick Login Logo ' . self::VERSION . ' ' . self::QuickURL . ' -->' . "\r\n";
            echo $style;
        }
    }

    /**
     * Retrieve the img data via AJAX and save as wordpress option
     */
    public static function getImageData()
    {
        if (!empty($_POST) && check_admin_referer('quick_login_logo_action','quick_login_logo_nonce')) {
            if (current_user_can('manage_options')) {
                // sanitize inputs
                $img_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
                $img_size = filter_input(INPUT_POST, 'size', FILTER_SANITIZE_STRING);

                // get the img at the correct size
                $img = wp_get_attachment_image_src($img_id, $img_size);

                // save src + attribs in the DB
                $img_data['id'] = $img_id;
                $img_data['src'] = $img[0];
                $img_data['width'] = $img[1];
                $img_data['height'] = $img[2];

                update_option('quick_login_logo', $img_data);

                $returnval = json_encode(array('src' => $img_data['src'], 'id' => $img_data['id']));
                wp_die($returnval);
            }
        }
    }

    /**
     * Display the currently set login logo img
     */
    public static function displayPreviewImg()
    {
        if (!empty($_POST) && check_admin_referer('quick_login_logo_action','quick_login_logo_nonce')) {
            if (current_user_can('manage_options')) {
                $img_data = get_option('quick_login_logo');
                if ($img_data) {
                    $returnval = json_encode(array('src' => $img_data['src'], 'id' => $img_data['id']));
                }
                else {
                    $returnval = false;
                }
                wp_die($returnval);
            }
        }
    }

    /**
     * Remove saved options on uninstall
     */
    public static function uninstall()
    {
        if (!current_user_can('activate_plugins')) {
            wp_die("I\'m afraid I can\' do that.");
        }

        check_admin_referer('bulk-plugins');

        delete_option('quick_login_logo');
    }

    /**
     * Retrieve the Home URL
     *
     * @return string Home URL
     */
    public static function replaceLoginUrl()
    {
        return home_url();
    }

    /**
     * Retrieve the Site Description
     *
     * @return string Site Description
     */
    public static function replaceLoginTitle()
    {
        return get_bloginfo('description');
    }

    /**
     * Retrieve the unique plugin basename
     *
     * @return string Plugin basename
     */
    public static function getBaseName()
    {
        return plugin_basename(__FILE__);
    }

    /**
     * Retrieve the URL to the plugin basename
     *
     * @return string Plugin basename URL
     */
    public static function getPluginDir()
    {
        return WP_PLUGIN_URL . '/' . dirname(plugin_basename(__FILE__));
    }
}

?>