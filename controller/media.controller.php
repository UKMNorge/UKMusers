<?php
require_once('UKM/innslag.class.php');
require_once('UKM/inc/password.inc.php');

$site_type = get_option('site_type');
$is_lokalmonstring = $site_type == 'kommune';

$users = array();
$m = new monstring(get_option('pl_id'));
$innslag = $m->innslag_btid();
#echo 'site-type: '.get_option('site_type');
/*echo '<pre>';
var_dump($innslag);
echo '</pre>';
*/
# A list of p_ids already added, so a person won't happen multiple times
$ignoreList = array();
$TWIGdata['users'] = array();
foreach($innslag as $band_type => $bands) {
	if ($band_type == 2 || $band_type == 5) {
		foreach ($bands as $band) {
			$inn = new innslag($band['b_id']);
			// Hent kun videresendte på fylkesnivå
			if (get_option('site_type') == 'fylke' ) {
				$inn->videresendte($m->g('pl_id'));
			} 
			$deltakere = $inn->personObjekter();
			/*echo '<br><b>Deltakere</b>:';
			var_dump($deltakere);
			echo '<br>';*/

			foreach( $deltakere as $deltaker ) {
				if (in_array($deltaker->g('p_id'), $ignoreList) ) {
					continue;
				}
				$user = new UKMuser();
				$p_id = $deltaker->g('p_id');
				$email = $deltaker->g('p_email');
				
				if( !$user->findByPID( $p_id ) ) {
					// Foreslå brukernavn basert på fornavn.etternavn
					$username = $user->getSuggestedUsername($p_id);
					if( $user->findByUsernameAndEmail( $username, $email ) ) {
						$user->updatePID( $p_id );	
					} else {
						
						// Hvis brukernavnet ikke er ledig, finn neste ledige
						if( !$user->isUsernameAvailable( $username ) ) {
							$i=0;
							while( !$user->isUsernameAvailable( $username.$i ) ) {
								$i++;
							}
							$username = $username.$i;
						}
						
						// Hvis e-posten ikke er ledig, finn neste ledige
						if( !$user->isEmailAvailable( $email ) ) {
							$email = $p_id.'@deltaker.ukm.no';
						}
			
						// Opprett ny bruker med garantert ledig p_id, brukernavn og epost
						$user->create( $p_id, $username, $email, 'nettredaksjon' );
					}
				}

				
				if ($user->valid()) {
					$ignoreList[] = $user->p_id;
					// Nå som vi har en bruker med all info, sjekk at brukeren har rettigheter til denne bloggen
					if( !$user->hasRightsToBlog( ) ) {
						$user->addToBlog( $blog_id );
					}					
				
					if (isset($_GET['upgrade'] ) && $user->wp_id == $_GET['upgrade'] ) {
						$user->upgrade();
					}
					if( isset( $_GET['downgrade'] ) && $user->wp_id == $_GET['downgrade'] ) {
						$user->downgrade();
					}
	
					$TWIGdata['users'][ $user->first_name .' '. $user->last_name ] = $user;
				}
			}
		}
	}
}

foreach($TWIGdata['users'] as $u) {
	$TWIGdata['errors'] = $u->getErrors();
}