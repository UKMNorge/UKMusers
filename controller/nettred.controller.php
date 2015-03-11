<?php
require_once('UKM/innslag.class.php');
require_once('UKM/inc/password.inc.php');

$userErrors = array();

$users = array();
$m = new monstring(get_option('pl_id'));
$innslag = $m->innslag_btid();
foreach($innslag as $band_type => $bands) {
	
	if( $band_type == 2 OR $band_type == 5 ) { 
		foreach($bands as $band) {
			
			$inn = new innslag($band['b_id']);
			$inn->videresendte($m->g('pl_id'));
			$deltakere = $inn->personObjekter();
			
			foreach( $deltakere as $deltaker ) {
				$user = new UKMuser( $deltaker, 'nettredaksjon' );
				$user->wp_user_create();
				
				if( isset( $_GET['upgrade'] ) && $user->wp_id == $_GET['upgrade'] ) {
					$user->upgrade();
				}
				if( isset( $_GET['downgrade'] ) && $user->wp_id == $_GET['downgrade'] ) {
					$user->downgrade();
				}
				$TWIGdata['users'][ $user->firstname .' '. $user->lastname ] = $user;
			}
		}
	}
}

ksort( $TWIGdata['users'] );