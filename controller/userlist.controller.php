<?php
use UKMNorge\Arrangement\Arrangement;
use UKMNorge\Wordpress\User;
use UKMNorge\Wordpress\WriteUser;

$arrangement = new Arrangement(get_option('pl_id'));
static::addViewData( 'filtrerteInnslag', static::createLoginsForParticipantsInArrangement($arrangement) );

if( isset($_GET['subaction']) && isset($_POST['wp_bruker_id']) ) {
    if( $_GET['subaction'] == "upgrade") {
        # Oppgrader brukeren
        $user = new User($_POST['wp_bruker_id']);
        try {
            WriteUser::upgradeUser($user);
        } catch( Exception $e ) {
            static::addFlash('danger', "Klarte ikke å oppgradere ".$user->getNavn());
        }
    } elseif( $_GET['subaction'] == "downgrade" ) {
        # Nedgrader brukeren
        $user = new User($_POST['wp_bruker_id']);
        try {
            WriteUser::downgradeUser($user);
        } catch( Exception $e ) {
            static::addFlash('danger', "Klarte ikke å nedgradere ".$user->getNavn());
        }
    }
}