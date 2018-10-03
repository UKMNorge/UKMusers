<?php
/* 
Plugin Name: UKMusers
Plugin URI: http://www.ukm-norge.no
Description: Genererer passordliste for nettredaksjon og arrangører
Author: UKM Norge / M Mandal 
Version: 1.0 
Author URI: http://www.ukm-norge.no
*/
require_once('UKM/inc/twig-admin.inc.php');
require_once('UKMusers.class.php');

## HOOK MENU AND SCRIPTS
if(is_admin()) {
	add_action('UKM_admin_menu', 'UKMusers_menu');
	add_filter('UKM_admin_menu_conditions', 'UKMusers_menu_conditions');
}
function UKMusers_menu_conditions( $_CONDITIONS ) {
	return array_merge( $_CONDITIONS, 
		['UKMusers' => 'monstring_har_deltakere']
	);
}

function UKMusers_menu() {
	UKM_add_menu_page('content','Deltakerbrukere', 'Deltakerbrukere', 'editor', 'UKMusers', 'UKMusers', '//ico.ukm.no/user-blue-menu.png',95);
	UKM_add_scripts_and_styles( 'UKMusers', 'UKMusers_scriptsandstyles' );	

}

function UKMusers() {
	$TWIGdata = array();
	
	$_GET['action'] == isset( $_GET['action'] ) ? $_GET['action'] : 'home';
	$TWIGdata['is_super_admin'] = is_super_admin();
	$TWIGdata['tab_active'] = $_GET['action'];

	switch( $_GET['action'] ) {
		case 'nettred':
			require_once('controller/media.controller.php');
			echo TWIG('nettred.twig.html', $TWIGdata, dirname(__FILE__));
			break;
		case 'arrangor':
			require_once('controller/arrangor.controller.php');
			echo TWIG('arrangor.twig.html', $TWIGdata, dirname(__FILE__));
			break;
		default:
			require_once('controller/home.controller.php');
			echo TWIG('home.twig.html', $TWIGdata, dirname(__FILE__));
			break;
	}
}

function UKMusers_scriptsandstyles() {
	wp_enqueue_style('UKMwp_dashboard_css', plugin_dir_url( __FILE__ ) .'/UKMusers.css');
	
	wp_enqueue_script('WPbootstrap3_js');
	wp_enqueue_script('UKMusers', plugin_dir_url(__FILE__) . '/UKMusers.jquery.js');
	wp_enqueue_style('WPbootstrap3_css');
}