<?php
require_once('UKM/innslag.class.php');
require_once('UKM/inc/password.inc.php');

$site_type = get_option('site_type');
$is_lokalmonstring = $site_type == 'kommune';

$users = array();
$m = new monstring(get_option('pl_id'));
$innslag = $m->innslag_btid();
#echo 'site-type: '.get_option('site_type');

# A list of p_ids already added, so a person won't happen multiple times
$ignoreList = array();
$TWIGdata['users'] = array();
foreach($innslag as $band_type => $bands) {
	if ($band_type == 8 || $band_type == 9) {
		foreach ($bands as $band) {
			$inn = new innslag($band['b_id']);
			// Hent kun videresendte på fylkesnivå
			if (get_option('site_type') == 'fylke' ) {
				$inn->videresendte($m->g('pl_id'));
			} 
			$deltakere = $inn->personObjekter();
/*			echo '<br><h4>Feilsøking</h4>';
			echo '<b>Deltakere</b><br>';
			var_dump($deltakere);
			echo '<br>';
*/
			foreach( $deltakere as $deltaker ) {
				if (in_array($deltaker->g('p_id'), $ignoreList) ) {
					continue;
				}
				$user = new UKMuser();
				$p_id = $deltaker->g('p_id');
				$email = $deltaker->g('p_email');
					
/*				echo '<b>Lager bruker:</b><br>';
				var_dump($p_id);
				var_dump($email);
*/				if( !$user->findByPID( $p_id ) ) {
					//echo '<br><b>Fant ikke en ferdig bruker med denne p_id.</b>';
					// Foreslå brukernavn basert på fornavn.etternavn
					$username = $user->getSuggestedUsername($p_id);
					//echo '<br>Foreslått brukernavn: '.$username;

					if( $user->findByUsernameAndEmail( $username, $email ) ) {
						$user->updatePID( $p_id );	
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
						if( !$user->isEmailAvailable( $email ) ) {
							$email = $p_id.'@deltaker.ukm.no';
						}
				
						//echo '<br>E-post-adresse: '.$email;
						// Opprett ny bruker med garantert ledig p_id, brukernavn og epost
						//echo '<br><b>Oppretter ny bruker:</b><br>';
						$user->create( $p_id, $username, $email, 'arrangor' );
					}
				}
				#echo '<br><b>Bruker:</b><br>';
				#var_dump($user);
				if ($user->valid()) {
					$ignoreList[] = $user->p_id;
					// Nå som vi har en bruker med all info, sjekk at brukeren har rettigheter til denne bloggen
					#echo '<br>Har rettigheter til blogg: '; var_dump($user->hasRightsToBlog($blog_id));
					if( !$user->hasRightsToBlog( $blog_id ) ) {
						$added = $user->addToBlog( $blog_id, 'arrangor' );
						if (!$added) {
							echo '<div class="alert alert-danger">Klarte ikke legge ny bruker til blogg, kontakt support med infoen under!<br>';
							var_dump($user).'</div>';
							echo '</div>';
						}
					}					
				
					if (isset($_GET['upgrade'] ) && $user->wp_id == $_GET['upgrade'] ) {
						$user->upgrade();
					}
					if( isset( $_GET['downgrade'] ) && $user->wp_id == $_GET['downgrade'] ) {
						$user->downgrade();
					}
	
					$TWIGdata['users'][ $user->first_name .' '. $user->last_name ] = $user;
				} else {
					$TWIGdata['errors'][] = $user->getErrors();
				}
			}
		}
	}
}

