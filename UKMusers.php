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
    public static $action = 'home';
    public static $path_plugin = null;
    
    public static function hook() {
        add_action('UKM_admin_menu', ['UKMusers','meny']);
        add_filter('UKM_admin_menu_conditions', ['UKMusers', 'meny_conditions']);
    }

    public static function meny() {
        UKM_add_menu_page(
            'content',
            'Deltakerbrukere',
            'Deltakerbrukere',
            'editor',
            'UKMusers',
            ['UKMusers','renderAdmin'],
            '//ico.ukm.no/user-blue-menu.png',
            95
        );
        UKM_add_scripts_and_styles( ['UKMusers', 'renderAdmin'], ['UKMusers','scriptsandstyles'] );
    }

    public static function meny_conditions( $_CONDITIONS ) {
        return array_merge( $_CONDITIONS, 
            ['UKMusers' => 'monstring_har_deltakere']
        );
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
