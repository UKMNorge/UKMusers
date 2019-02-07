<?php
require_once('UKM/innslag.class.php');
require_once('UKM/inc/password.inc.php');

global $blog_id;

// All p_ids added. Avoid duplicates in list
$added = [];
$users = [];
$errors = [];

$monstring = new monstring_v2( get_option('pl_id') );
$alle_innslag = $monstring->getInnslag()::filterByType( 
    innslag_typer::getByName( DELTAKERBRUKER_TYPE ), 
    $monstring->getInnslag()->getAll()
);

foreach( $alle_innslag as $innslag ) {
    foreach( $innslag->getPersoner()->getAll() as $person ) {
        if( in_array( $person->getId(), $added ) ) {
            continue;
        }

        // Rar måte å gjøre det på, men kan altså opprette tomt objekt
        $user = new UKMuser();
        $epost = $person->getEpost();
        // Prøv å autoloade fra PID
        if( !$user->findByPID( $person->getId() ) ) {
            // Foreslå brukernavn basert på fornavn.etternavn
            $username = $user->getSuggestedUsername( $person->getId() );

            if( $user->findAndUpdateByUsernameAndEmail() ) {
                // never mind. Ting skjer automagisk i UKMusers.class
            }
            elseif( $user->findByUsernameAndEmail( $username, $person->getEpost() ) ) {
                $user->updatePID( $person->getId() );	
            } else {
                // Hvis brukernavnet ikke er ledig, finn neste ledige
                if( !$user->isUsernameAvailable( $username ) ) {
                    $i=1;
                    while( !$user->isUsernameAvailable( $username.$i ) ) {
                        $i++;
                    }
                    $username = $username.$i;
                }
                // Hvis e-posten ikke er ledig, finn neste ledige
                if( !$user->isEmailAvailable( $person->getEpost() ) ) {
                    $epost = $person->getId().'@deltaker.ukm.no';
                }
                // Opprett ny bruker med garantert ledig p_id, brukernavn og epost
                $user->create( $person->getId(), $username, $epost, 'nettredaksjon' );
            }
        }
        
        // Hvis en av metodene over funket, har vi nå et gyldig user-objekt
        if ($user->valid()) {
            $added[] = $person->getId();
            // Nå som vi har en bruker med all info, sjekk at brukeren har rettigheter til denne bloggen
            if( !$user->hasRightsToBlog( $blog_id ) ) {
                $added = $user->addToBlog( $blog_id, DELTAKERBRUKER_DNG );
                if (!$added) {
                    echo '<div class="alert alert-danger">'
                        .'Klarte ikke legge ny bruker til blogg, kontakt support med infoen under!<br />'
                        . var_export( $user->errors, true )
                        .'</div>';
                }
            }					
        
            if (isset($_GET['upgrade'] ) && $user->wp_id == $_GET['upgrade'] ) {
                $user->upgrade( DELTAKERBRUKER_UPG );
            }
            if( isset( $_GET['downgrade'] ) && $user->wp_id == $_GET['downgrade'] ) {
                $user->downgrade( DELTAKERBRUKER_DNG );
            }

            $users[ $user->first_name .' '. $user->last_name ] = $user;
        }
        // Vi har ikke en gyldig bruker. Vis error
        else {
            $errors[] = $user->getErrors();
        }
    }
}

UKMusers::addViewData('users', $users);
UKMusers::addViewData('errors', $errors);