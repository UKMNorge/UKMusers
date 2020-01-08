<?php
/* 
Plugin Name: UKMusers
Plugin URI: http://www.ukm-norge.no
Description: Genererer passordliste for nettredaksjon og arrangører
Author: UKM Norge / M Mandal 
Version: 2.0 
Author URI: http://www.ukm-norge.no
*/

require_once("UKM/Autoloader.php");
use UKMNorge\Wordpress\User as WordpressUser;
use UKMNorge\Wordpress\LoginToken;
use UKMNorge\Database\SQL\Query;

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
    
    /**
     * Logger inn en bruker programmatisk. 
     * Sjekker token mot token-tabell og at forespurt bruker finnes i whitelist.
     * 
     * OBS: Krever at siden "Autologin" er opprettet i Wordpress! Denne må ha UKMviseng = delta_autologin, og URL ukm.no/autologin.
     * 
     * @param Int $wp_id - Wordpress ID fra ukm_delta_wp_user-tabell
     * @return bool True if the login was successful; false if it wasn't
     * 
     * Test URL: https://ukm.dev/autologin/?wp_id=1&token_id=1&token=22
     */
    public static function loginFromDelta( Int $wp_id, Int $token_id, String $secret ) {
        
        // Sjekk token
        try {
            if( $wp_id != LoginToken::use($token_id, $secret) ) {
                # Token already used? Does not exist? Etc.
                return false;
            }
        } catch (Exception $e) {
            # Token crashed
            return false;
        }

        // Sjekk at bruker-IDen finnes i ukm_delta_wp_user-tabell - så folk ikke kan autologge inn til UKM Norge-brukerne etc.
        $sql = new Query("SELECT `wp_id` FROM ukm_delta_wp_user WHERE `wp_id` = '#wp_id'", ['wp_id' => $wp_id]);
        
        $wordpressUserId = $sql->getField();
        if ( NULL == $wordpressUserId ) {
            die("Failed to find wordpress user in whitelist.");
            return false;
        }

        // Finn bruker fra ID
        $user = WordpressUser::loadById($wordpressUserId);

        if ( is_user_logged_in() ) {
            wp_logout();
        }
        
        add_filter( 'authenticate', 'allow_programmatic_login', 10, 3 );	// hook in earlier than other callbacks to short-circuit them
        $user = wp_signon( array( 'user_login' => $user->getUsername() ) );
        remove_filter( 'authenticate', 'allow_programmatic_login', 10, 3 );
        
        if ( is_a( $user, 'WP_User' ) ) {
            wp_set_current_user( $user->ID, $user->user_login );
            
            if ( is_user_logged_in() ) {
                return true;
            }
        }

        die("Nothing matches");
        return false;
    }    
}

/**
 * An 'authenticate' filter callback that authenticates the user using only the username.
 *
 * To avoid potential security vulnerabilities, this should only be used in the context of a programmatic login,
 * and unhooked immediately after it fires.
 * 
 * @param WP_User $user
 * @param string $username
 * @param string $password
 * @return bool|WP_User a WP_User object if the username matched an existing user, or false if it didn't
 */
function allow_programmatic_login( $user, $username, $password ) {
    return get_user_by( 'login', $username );
}

UKMusers::init( __DIR__ );
## HOOK MENU AND SCRIPTS
if(is_admin()) {
    UKMusers::hook();
}


//	$TWIGdata['is_super_admin'] = is_super_admin();
