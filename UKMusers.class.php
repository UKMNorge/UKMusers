<?php
class UKMuser {
	var $table = 'ukm_wp_deltakerbrukere';
	
	public function __construct( $deltakerObject, $type ) {
		$deltakerObject->loadGEO();
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
		}
		return false;
	}
	
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
	
	public function wp_user_create( ) {
#		echo '<h3>create: '. $this->username .' </h3>';
		$username_exists = $this->wp_username_exists( $this->username );
		$useremail_exists= $this->wp_email_exists( $this->email );

		// Returner brukerID hvis brukernavn finnes, og det tilhører denne P_ID
		// Finn neste ledige brukernavn hvis dette er tatt
		if( $username_exists ) {
			$test_username_count = 0;
			while( $username_exists && !$this->wp_username_is_mine( $this->username ) ) {
				$test_username_count++;
				$username_exists = $this->wp_username_exists( $this->username . $test_username_count );
			}
			if( $username_exists ) {
				$this->wp_id = $username_exists;
				$this->_doWP_user_update();
				return $username_exists;
			}

			$this->username .= $test_username_count;
		}
		
		if( $useremail_exists && $this->wp_useremail_is_mine() ) {
			echo 'E-postadressen finnes, og tilhører brukeren <br />';
			return $useremail_exists;
		}
		
		
		$this->_doWP_user_create( );
	}
	
	private function _doWP_user_create( ) {
		$this->password = UKM_ordpass();
		$wp_user_id = wp_create_user( $this->username, $this->password, $this->email );
		
		// IF IS ERROR
		if( is_object( $wp_user_id ) ) {
			USER_CREATE_ERROR( $wp_user_id );
			return ;
		}
		$this->wp_id = $wp_user_id;
		$this->_doWP_add_to_blog();
		$this->_doWP_user_update();
	}
	
	private function _doWP_add_to_blog() {
		global $blog_id;
		add_user_to_blog( $blog_id, $this->wp_id, $this->wp_role );
	}
	
	private function _wp_role() {
		switch( $this->type ) {
			case 'nettredaksjon':
				$this->wp_role = 'contributor';
				break;
			case 'arrangor':
				$this->wp_role = 'editor';
				break;
			default:
				$this->wp_role = 'subscriber';
				break;
		}
		
		$user_id = $this->wp_username_exists( $this->username );
		if( $user_id ) {
			$user_data = get_userdata( $user_id );
			$role = $user_data->roles[0];
			$this->wp_role = $role;
		}
	}
	
	public function upgrade( ) {
		if( $this->type == 'nettredaksjon' ) {
			$this->wp_role = 'author';
		}
		$this->_doWP_add_to_blog();
	}
	public function downgrade( ) {
		if( $this->type == 'nettredaksjon' ) {
			$this->wp_role = 'contributor';
		}
		$this->_doWP_add_to_blog();
	}
	
	private function _doWP_user_update() {
		if( empty( $this->password ) ) {
			$this->password = UKM_ordpass();
			wp_set_password( $this->password, $this->wp_id );
		}
		
		update_user_meta( $this->wp_id, 'p_id', $this->p_id );
		wp_update_user( array('ID' => $this->wp_id, 'description' => $this->description, 'role' => $this->wp_role ));
		
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
		
		$sql->run();

	}
}

function USER_CREATE_ERROR( $usererror ) {
	global $userErrors;
	$userErrors[] = $usererror;
}
