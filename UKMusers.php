<?php
/* 
Plugin Name: UKMusers
Plugin URI: http://www.ukm-norge.no
Description: Genererer passordliste for nettredaksjon og arrangører
Author: UKM Norge / M Mandal 
Version: 2.0 
Author URI: http://www.ukm-norge.no
*/

require_once('UKMuser.class.php');
require_once('UKM/wp_modul.class.php');

class UKMusers extends UKMWPmodul {
    public static $action = 'snart';
    public static $path_plugin = null;
    
    public static function hook() {
        add_action('admin_menu', ['UKMusers','meny'], 300);
    }

    public static function meny() {
        /*
        $page = add_submenu_page(
            'UKMmonstring',
            'Administratorer',
            'Administratorer',
            'editor',
            'UKMusers',
            ['UKMusers','renderAdmin'],
            95
		);
		add_action(
			'admin_print_styles-' . $page,
			['UKMusers','scriptsandstyles']
        );
        */
    }

    public static function scriptsandstyles() {	
        wp_enqueue_script('WPbootstrap3_js');
        wp_enqueue_style('WPbootstrap3_css');
    }    
    
}

UKMusers::init( __DIR__ );
## HOOK MENU AND SCRIPTS
if(is_admin()) {
    UKMusers::hook();
}


//	$TWIGdata['is_super_admin'] = is_super_admin();
