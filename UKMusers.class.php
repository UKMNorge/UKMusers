<?php
class UKMuser {
	var $table = 'ukm_wp_deltakerbrukere';
	var $debug = true;

	public function __construct( $deltakerObject, $type ) {
		$deltakerObject->loadGEO();
		$this->deltakerObject = $deltakerObject;
		$this->type = $type;

		$this->p_id = $deltakerObject->get('p_id');
		$this->firstname = trim( $deltakerObject->get('p_firstname') );
		$this->lastname = trim( $deltakerObject->get('p_lastname') );
		
		$this->_username();
		$this->_email( $deltakerObject );
		$this->_wp_role();
		$this->_password();

		$this->title    = $deltakerObject->get('instrument');
		$this->description = $deltakerObject->get('p_firstname') . ' ' . $deltakerObject->get('p_lastname')
							 . ' er ' . $deltakerObject->alder() . ' gammel og kommer fra ' 
							 . $deltakerObject->get('kommune') . ' i ' . $deltakerObject->get('fylke');
	}
	
	private function _password() {
		$sql = new SQL("SELECT `password`
						FROM `#table`
						WHERE `username` = '#username'
						AND `p_id` = '#pid'",
					array('table' => $this->table,
						  'username' => $this->username,
						  'pid' => $this->p_id)
					);
		#echo $sql->debug();
		$this->password = $sql->run('field','password');
	}
	
	
	private function _username() {
		$clean_firstname = mb_strtolower( str_replace(' ', '.', $this->firstname ) );
		$clean_lastname = mb_strtolower( str_replace(' ', '.', $this->lastname ) );
		
		$clean_firstname = $this->_clean( $clean_firstname );
		$clean_lastname = $this->_clean( $clean_lastname );
		
		$this->username = $clean_firstname.'.'.$clean_lastname;
	}
	
	private function _clean( $string ) {
		$string = str_replace('æ', 'a', $string);
		$string = str_replace('ø', 'o', $string);
		$string = str_replace('å', 'a', $string);
		
		return $string;
	}
	
	private function _email( $deltakerObject ) {
		$email = $deltakerObject->get('p_email');
		
		if( empty( $email ) ) {
			$this->email = $deltakerObject->get('p_id') . '@deltaker.ukm.no';
		} else {
			$this->email = $email;
		}
	}
	
	
	public function wp_username_exists( $username ) {
		return username_exists( $username );
	}
	
	public function wp_username_is_mine() {
		$wp_user_id = $this->wp_username_exists( $this->username );
		
		if( $wp_user_id ) {
			$wp_user_participant_id = get_user_meta( $wp_user_id, 'p_id', true );
			if( $wp_user_participant_id == $this->p_id ) {
				return true;
			}
			if (is_super_admin() && $this->debug )
				echo '<b>Brukernavnet finnes, men tilhører ikke denne brukeren.</b><br>';
			return false;
		}
		if (is_super_admin() && $this->debug )
			echo '<b>Brukernavnet finnes ikke.</b><br>';
		return false;
	}

	// public function wp_username_is_mine() {
	//     $wp_user_id = $this->wp_username_exists( $this->username );

	//     if( $wp_user_id ) {
	//         $wp_user_participant_id = get_user_meta( $wp_user_id, 'p_id', true );
	//         if( $wp_user_participant_id == $this->p_id ) {
	//             return true;
	//         }

	//         $sql = new SQL("SELECT `p_id` FROM `smartukm_participant`
	//                         WHERE `p_firstname` = '#firstname'
	//                         AND `p_lastname` = '#lastname'
	//                         AND `p_email` = '#email'
	//                         ORDER BY `p_id` DESC",
	//                         array(  'firstname' => $this->deltakerObject->get('p_firstname'),
	//                                 'lastname' => $this->deltakerObject->get('p_lastname'),
	//                                 'email' => $this->deltakerObject->get('p_email'));
	//         $res = $sql->run();
	//         if( $res ) {
	//             while( $r = mysql_fetch_assoc( $res ) ) {
	//                 if( $r['p_id'] == $wp_user_participant_id ) {
	//                     $this->_wp_set_new_p_id( $this->p_id );
	//                     return true;
	//                 }
	//             }
	//         }
	//     }
	//     return false;
	// }
	
	public function wp_email_exists( $email ) {
		return email_exists( $email );
	}
	
	public function wp_useremail_is_mine() {
		$wp_user_id = $this->wp_email_exists( $this->email );
		
		if( $wp_user_id ) {
			$wp_user_participant_id = get_user_meta( $wp_user_id, 'p_id', true );

			if( $wp_user_participant_id == $p_id ) {
				return true;
			}
		}
		return false;
	}

	public function wp_user_is_member_of_blog($wp_id) {
		global $blog_id;
		if (!is_user_member_of_blog($blog_id, $wp_id)) {
			if (is_super_admin() && $this->debug) {
				echo '<b>Bruker er ikke medlem av rett blogg.</b><br>';
			}
			return false;
		}
		return $blog_id;
	}
	
	public function wp_user_create( ) {
		if(is_super_admin() && $this->debug )
			echo '<h3>create: '. $this->username .' </h3>';
		$username_exists = $this->wp_username_exists( $this->username );
		$this->wp_id = $username_exists;
		$useremail_exists= $this->wp_email_exists( $this->email );

		// echo '<br>Feilsøking for bruker: '.$this->username.'<br>';
		// var_dump($this);
		// echo '<br>username_exists: '.$username_exists.'<br>';
		// echo 'useremail_exists: '.$useremail_exists.'<br>';
		// echo 'wp_username_is_mine '.$this->wp_username_is_mine($this->username).'<br>';
		// echo 'wp_useremail_is_mine '.$this->wp_useremail_is_mine($this->email).'<br>';
		// echo 'p_id: '.$this->p_id.'<br>';

		if (!$username_exists && $useremail_exists) {
			// E-post finnes i WP, men brukernavnet gjør det ikke
		}
		
		$user = $this->_findUser($this->p_id);
		$this->password = $this->_checkForUser('password');
		if(is_super_admin() && $this->debug) {
			echo 'user: ';
			var_dump($user);
			echo '<br>';
		}
		
		// TODO: FIKS DENNE!
		// BURDE IKKE KUN SJEKKE DETTE; MEN OGSÅ SJEKKE AT p_ID FRA deltakerObject stemmer med notert p_id
		// Hvis vi har en bruker i tabellen
		if ($user) {
			// Sørg for at brukeren har rettigheter til denne bloggen
			$blog = $this->wp_user_is_member_of_blog($this->wp_id);
			if (!$blog) {
				// Dette SKAL oppdatere $this->wp_role, men det skjer ikke alltid??
				$this->_wp_role();
				if (is_super_admin() && $this->debug ) {
					global $blog_id;
					echo 'Type: '.$this->type.'<br>';
					echo 'ID: '.$this->wp_id.'<br>';
					echo 'Role: '.$this->wp_role.'<br>';
					echo 'Blogg-ID: '.$blog_id.'<br>';
				}
				$this->_doWP_add_to_blog();
				// add_user_to_blog($blog_id, $wp_id, $this->wp_role);
			}
			// Sjekker om brukernavn finnes i WP og tilhører denne p_id
			if ($username_exists && $this->wp_username_is_mine($this->username) ) {
				// TRENGS IKKE? Oppdater lokalt objekt
				if (is_super_admin() && $this->debug )
					echo '<b>Ting funker, returnerer tidlig.</b><br>';
				// Returner ID
				return $username_exists;
			}
		}
		// Dersom bruker-iden ikke finnes i databasen, men brukernavnet og e-posten gjør det:
		$old = $this->_checkForUser();
		// $this->password = $this->_checkForUser('password');
		if (is_super_admin() && $this->debug )
			echo 'old: '.$old.'<br>';
		if ($old) {
			if (is_super_admin() && $this->debug )
				echo '<b>Brukerdata finnes i tabellen.</b><br>';
			// Hent ny ID
			$new_p = $this->p_id;
			//$new_p = $this->_checkSmartForUser();
			if (is_super_admin() && $this->debug )
				echo 'new_p: '.$new_p.'<br>';
			// Dersom brukeren har ny ID
			if ($new_p && ($new_p != $old)) {
				// Oppdater brukeren i ukm_wp_deltakerbrukere med ny p_id
				if (is_super_admin() && $this->debug ) {
					echo '<b>Bruker-ID i tabellen er utdatert.</b><br>';
					echo '_updateLocalId: '.$this->_updateLocalId($old, $new_p).'<br>';
				}
				#$this->_updateLocalId($old, $new_p);
				$this->p_id = $new_p;
				if (is_super_admin() && $this->debug )
					echo '$this->p_id: '.$this->p_id.'<br>';
				#$this->password = $this->_password();
				if (is_super_admin() && $this->debug )
					echo '$this->password: '.$this->password.'<br>';

				// Gjennomfør WP-update
				$this->_doWP_user_update();
				return $this->wp_id;
			}
			else {
				// Brukeren har samme ID, men finnes ikke i WP
				if (is_super_admin() && $this->debug )
					echo '<b>Brukeren har samme ID, men finnes ikke i WP. Kjører _doWP_user_create()</b><br>';
				$this->_doWP_user_create();

				return $this->wp_id;
			}
		}
		// Brukerdata finnes ikke i tabellen eller WP, så opprett ny rad begge steder
		if (is_super_admin() && $this->debug )
			echo 'Kjører _doWP_user_create()<br>';
		$this->_doWP_user_create( );

		// // Returner brukerID hvis brukernavn finnes, og det tilhører denne P_ID
		// // Finn neste ledige brukernavn hvis dette er tatt
		// if( $username_exists ) {
		// 	$test_username_count = 0;
		// 	while( $username_exists && !$this->wp_username_is_mine( $this->username ) ) {
		// 		$test_username_count++;
		// 		$username_exists = $this->wp_username_exists( $this->username . $test_username_count );
		// 	}
		// 	if( $username_exists ) {
		// 		$this->wp_id = $username_exists;
		// 		$this->_doWP_user_update();
		// 		return $username_exists;
		// 	}

		// 	$this->username .= $test_username_count;
		// }
		
		// if( $useremail_exists && $this->wp_useremail_is_mine() ) {
		// 	echo 'E-postadressen finnes. og tilhører denne brukeren.<br />';
		// 	return $useremail_exists;
		// }
	
	}

	

	private function _checkSmartForUser() {
		$qry = new SQL("SELECT `p_id` FROM  `smartukm_participant` 
						WHERE 	`p_firstname` = '#firstname'
						AND 	`p_lastname` = '#lastname'
						AND 	`p_email`	 = '#email'
						AND 	`p_id` = '#pid'
						ORDER BY `p_id` DESC;", 
						array(	'pid' => $this->p_id,
								'firstname' => $this->firstname, 
								'lastname'=> $this->lastname,
								'email' => $this->email) 
						);
		if (is_super_admin() && $this->debug )
			echo $qry->debug();
		return $qry->run('field', 'p_id');
	}
	private function _checkForUser($field = 'p_id') {
		$qry = new SQL("SELECT `#field` FROM  `#table` 
						WHERE 	`username` = '#username'
						AND 	`email` = '#email';", 
					array( 	'field' => $field,
							'table' => $this->table,
							'username' => $this->username, 	
							'email' => $this->email) 
						);
		if (is_super_admin() && $this->debug )
			echo $qry->debug();
		return $qry->run('field', $field);
	}

	private function _findUser($p_id) {
		$qry = new SQL("SELECT * FROM `#table`
						WHERE 	`p_id` = '#pid';",
						array(	'table' => $this->table,
								'pid' => $p_id) 
					);
		if (is_super_admin() && $this->debug )
			echo $qry->debug();
		return $qry->run('array');
	}

	private function _updateLocalId($old, $new) {
		$qry = new SQLins($this->table, array('p_id' => $old));
		$qry->add('p_id', $new);
		if (is_super_admin() && $this->debug )
			echo $qry->debug();
		return $res = $qry->run();
	}

	private function _doWP_user_create($password = null) {
		if (!$password) 
			$this->password = UKM_ordpass();

		$wp_user_id = wp_create_user( $this->username, $this->password, $this->email );
		
		// IF IS ERROR
		if( is_object( $wp_user_id ) ) {
			USER_CREATE_ERROR( $wp_user_id );
			if(is_super_admin() && $this->debug ) {
				echo '<b>FAILED TO CREATE USER</b><br>';
			}
			return ;
		}

		// Oppdater $this->wp_role
		$this->_wp_role();

		// if (is_super_admin() ) 
		// 	echo 'Setter wp_id og legger brukeren til bloggen<br>';
		$this->wp_id = $wp_user_id;
		$this->_doWP_add_to_blog();
		$this->_doWP_user_update();
	}
	
	private function _doWP_add_to_blog() {
		global $blog_id;
		
		$add = add_user_to_blog( $blog_id, $this->wp_id, $this->wp_role );
		if(is_super_admin() && $this->debug ) {
			if ($add === true) {
				echo 'Lagt brukeren til blogg '.$blog_id.'<br>';
			}
			elseif (get_class($add) == 'WP_Error') {
				echo 'Feilet å legge til brukeren til blogg '.$blog_id.'<br>';		
				var_dump($add);	
				echo '<br>';
			}
			else {
				echo '<b>Ukjent feil!</b><br>';
			}
		}

	}
	
	private function _wp_role() {
		switch( $this->type ) {
			case 'nettredaksjon':
				$this->wp_role = 'contributor';
				break;
			case 'arrangor':
				$this->wp_role = 'ukm_produsent';
				break;
			default:
				$this->wp_role = 'subscriber';
				break;
		}
		
		$user_id = $this->wp_username_exists( $this->username );
		if( $user_id ) {
			$user_data = get_userdata( $user_id );
			$role = $user_data->roles[0];
			if (is_super_admin() && $this->debug ) {
				echo 'Role in _wp_role: '.$role.'<br>';
				var_dump($role);
				echo '<br>';
			}
			if (!$role) 
				$role = 'contributor';
			$this->wp_role = $role;
		}
	}
	
	public function upgrade( ) {
		switch( $this->type ) {
			case 'nettredaksjon':
				$this->wp_role = 'author';
				break;
			case 'arrangor':
				$this->wp_role = 'editor';
				break;
		}
		$this->_doWP_add_to_blog();
	}
	public function downgrade( ) {
		switch( $this->type ) {
			case 'nettredaksjon':
				$this->wp_role = 'contributor';
				break;
			case 'arrangor':
				$this->wp_role = 'ukm_produsent';
				break;
		}
		$this->_doWP_add_to_blog();
	}
	
	private function _doWP_user_update() {
		global $blog_id;
		if( empty( $this->password ) ) {
			$this->password = UKM_ordpass();
			wp_set_password( $this->password, $this->wp_id );
		}
		


		update_user_meta( $this->wp_id, 'p_id', $this->p_id );
		wp_update_user( array('ID' => $this->wp_id, 'description' => $this->description, 'role' => $this->wp_role ));
		
		if (!is_user_member_of_blog($this->wp_id, $blog_id) ) {
			// Burde også sjekke at rettigheter stemmer
			$this->_doWP_add_to_blog();

			// $add = add_user_to_blog($blog_id, $this->wp_id, $this->wp_role);
			// if(is_super_admin() && $this->debug ) {
			// 	if ($add === true) {
			// 		echo 'Lagt brukeren til blogg '.$blog_id.'<br>';
			// 	}
			// 	elseif (get_class($add) == 'WP_Error') {
			// 		echo 'Feilet å legge til brukeren til blogg '.$blog_id.'<br>';		
			// 		var_dump($add);	
			// 		echo '<br>';
			// 	}
			// }
		}

		$test = new SQL("SELECT `p_id` FROM `#table` WHERE `p_id` = '#pid'", array('table'=>$this->table, 'pid'=>$this->p_id));
		$res = $test->run('field','p_id');
		
		if( is_numeric( $res ) ) {
			$sql = new SQLins('ukm_wp_deltakerbrukere', array('p_id' => $this->p_id ));
		} else {
			$sql = new SQLins('ukm_wp_deltakerbrukere');
		}
		$sql->add('p_id', $this->p_id);
		$sql->add('username', $this->username);
		$sql->add('email', $this->email);
		$sql->add('password', $this->password);
		$sql->add('wp_id', $this->wp_id);
		
		if (is_super_admin() && $this->debug )
			echo $sql->debug();
		$sql->run();

	}
}

function USER_CREATE_ERROR( $usererror ) {
	global $userErrors;
	$userErrors[] = $usererror;
}
