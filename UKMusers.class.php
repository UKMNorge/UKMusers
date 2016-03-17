<?php

### Deltakerbrukere v3
# Rewrite 06.03.2016
# Modulen integrerer deltakermodulen og Wordpress-systemet
# sånn at alle nettredaksjon- og arrangør-deltakere får 
# innlogging til arrangørsystemet med tilgang til ulike deler.
# Asgeir Stavik Hustad (@asgeirsh)
# asgeirsh@ukmmedia.no

class UKMuser {
	
	var $table = 'ukm_wp_deltakerbrukere';
	var $role = null;

	var $errors = array();

	# Variables to be used externally
	var $p_id = null;
	var $wp_id = null;
	var $username = null;
	var $email = null;
	var $password = null;
	var $description = null;

	# Variables fetched from participant_object
	var $first_name = null;
	var $last_name = null;

	var $valid = false;

	public function __construct() {

	}

	public function valid() {
		return $this->valid;
	}

	public function findByPID( $p_id ) {
		return $this->loadUserData($p_id);
	}

	public function findByUsernameAndEmail( $username, $email ) {
		# Sjekk om brukernavn OG e-post finnes i databasen.
		$qry = new SQL("SELECT * FROM `#table`
						WHERE `p_username` = '#username'
						AND `p_email` = '#epost'", 
					array(
						"table" => $this->table,
						"username" => $this->username, 
						"epost" => $this->email
						)
					);
		$localUser = $qry->run('array');
		if ($localUser) {
			$this->loadUserData($localUser['p_id']);
			return true;
		}
		return false;
	}

	public function updatePID( $p_id ) {
		// Når denne kjøres er data lastet inn, så $this->p_id er p_id som matcher brukernavn og e-post
		$qry = new SQLins($this->table, array('p_id' => $this->p_id));

		$qry->add('p_id', $p_id);
		$res = $qry->run();

		if($res == 1) {
			// Oppdatering funket, last inn brukerdata på nytt
			$this->loadUserData($p_id);
			return true;
		}
		else {
			throw new Exception('UKMusers: updatePID klarte ikke å oppdatere p_id fra '.$this->p_id.' til '. $p_id);
			return false;
		}
	}

	public function isUsernameAvailable( $username ) {
		$qry = new SQL("SELECT COUNT(*) FROM `#table`
						WHERE `username` = '#username'",
						array('table' => $this->table, 'username' => $username)
					);
		$count = $qry->run('field', 'COUNT(*)');

		if ($count == 0) {
			return true;
		}
		return false;
	}

	public function isEmailAvailable( $email ) {
		# Hvis e-post er tom vil den ikke funke.
		if (empty($email))
			return false;
		$qry = new SQL("SELECT COUNT(*) FROM `#table`
						WHERE `p_email` = '#email'",
						array('table' => $this->table, 'email' => $email)
					);
		$count = $qry->run('field', 'COUNT(*)');

		if ($count == 0) {
			return true;
		}
		return false;
	}

	public function create( $p_id, $username, $email, $type ) {
		require_once('UKM/inc/password.inc.php');
		$password = UKM_ordpass();

		//echo 'Input: '. $p_id .', '. $username.', '.$email.', '.$type.', '.$password;
		// Verify that all info is here
		global $blog_id;
		if ( empty($p_id) || empty($username) || empty($email) || empty($password) || empty($blog_id) ) {
			$this->errors[] = array('danger' => 'Data mangler, kan ikke opprette ny bruker (Brukernavn: '.$username.', p_id: '.$p_id.').');
			return false;
		}

		// Add to wordpress
		$wp_id = wp_create_user($username, $password, $email);
		/*echo '<br>wp_id: ';
		var_dump($wp_id);*/
		if (is_wp_error($wp_id)) {
			$this->errors[] = array('danger' => 'Klarte ikke opprette Wordpress-bruker!');
			return false;
		}

		// Add to database
		/*echo '<br>Fatal error skjer rett under denne';
		var_dump($p_id);
		var_dump($username);
		var_dump($email);
		var_dump($password);
		var_dump($wp_id);
		*/
		$qry = new SQLins('ukm_wp_deltakerbrukere');
		$qry->add('p_id', $p_id);
		$qry->add('username', $username);
		$qry->add('email', $email);
		$qry->add('password', $password);
		$qry->add('wp_id', $wp_id);

		$res = $qry->run();
		//echo '<br>Eller så gjorde det inte det';
		if ($res != 1) {
			$this->errors[] = array('danger' => 'Klarte ikke opprette ny lokal bruker! SQL: ' . $qry->debug() );
			return false;
		}

		$role = $this->_getRoleFromType($type);

		add_user_to_blog($wp_id, $blog_id, $role);
		update_user_meta( $wp_id, 'p_id', $p_id );

		$person = new person($p_id, false);
		$first_name = $person->g('p_firstname');
		$last_name = $person->g('p_lastname');
		$person->loadGEO();
		$description = $first_name . ' ' . $lastname
					. ' er ' . $person->alder() . ' år gammel og kommer fra ' 
					. $person->g('kommune') . ' i ' . $person->get('fylke');

		$updates['ID'] = $wp_id;
		$updates['role'] = $role;
		$updates['description'] = $description;
		$updates['first_name'] = $first_name;
		$updates['last_name'] = $last_name;
		wp_update_user($updates);

		$this->loadUserData($p_id);

		return true;
	}

	public function upgrade($role = null) {
		if (!$role)
			$role = $this->role;

		switch ($role) {
			case 'contributor':
				$new = 'author';
				break;
			case 'ukm_produsent':
				$new = 'editor';
				break;
			default:
				$new = null;
				break;
		}

		if (!$new) {
			$this->errors[] = array('danger' => 'Klarte ikke å oppgradere brukeren. Nåværende rolle er "'.$this->role.', prøvde å oppgradere til "'.$role.'".');
			return false;
		}
		if(!$this->wp_id) {

		}

		#echo '<br><h4>Upgrade</h4>';
		#echo '$this->wp_id: ';
		#echo $this->wp_id;
		#echo '.<br>Til rolle: '. $new;
		#echo '.<br> Result: ';
		$updates['ID'] = $this->wp_id;
		$updates['role'] = $new;
		$res = wp_update_user($updates);
		#var_dump($res);

		if (is_wp_error($res))
			return false;
		$this->role = $new;
		return true;
	}

	public function downgrade() {
		switch ($this->role) {
			case 'author':
				$new = 'contributor';
				break;
			case 'editor':
				$new = 'ukm_produsent';
				break;
			default:
				$new = null;
				break;
		}

		if (!$new) {
			$this->errors[] = array('danger' => 'Klarte ikke å oppgradere brukeren.');
			return false;
		}

		$updates['ID'] = $this->wp_id;
		$updates['role'] = $new;
		$res = wp_update_user($updates);
		#echo 'downgrade';
		#var_dump($res);
		if (is_wp_error($res))
			return false;
		$this->role = $new;
		return true;
	}

	public function hasRightsToBlog( $blog_id = null) {
		/*$blogs = get_blogs_of_user($this->p_id);
		foreach ($blogs as $blog) {
			if ($blog->userblog_id == $blog_id) {
				return true;
			}
		}
		return false;*/
		if (!$blog_id) 
			global $blog_id;
		return is_user_member_of_blog($this->wp_id, $blog_id);
	}

	# Function addToBlog()
	# Legger til brukeren representert i dette objektet i en wordpress-blogg.
	public function addToBlog( $blog_id = null, $role = null ) {
		if (!$blog_id) 
			global $blog_id;

		if(empty($this->role))
			$this->_set_role($role);

		if (empty($blog_id) || empty($this->wp_id) || empty($this->role) ) {
			$this->errors[] = array('danger' => 'Forsøkte å legge brukeren til en blogg med manglende data!');
			return false;
		}
	
		// brukeren har alltid en wordpress-id, det fikses i create eller load.
		$res = add_user_to_blog( $blog_id, $this->wp_id, $this->role );

		if (true === $res) {
			return true;
		}
		var_dump($res); // $res is WP_Error object
		return false;
	}

	# Function findLocalUser
	# Tar i mot p_id og finner en bruker i tabellen.
	# Returnerer true om alt gikk fint, false om bruker-IDen ikke finnes og en Exception hvis noe annet mangler eller feil skjer.
	public function findLocalUser($p_id) {
		$qry = new SQL("SELECT COUNT(*) FROM `#table`
						WHERE `p_id` = '#p_id'",
						array('table' => $this->table, 'p_id' => $p_id)
					);
		$count = $qry->run('field', 'COUNT(*)');
		if ($count != 1) 
			return false;

		$this->loadUserData($p_id);
		return true;
	}

	# Function loadUserData
	# Tar i mot p_id og henter ut data fra en bruker i tabellene og setter disse i klasseverdiene.
	# Returnerer true om alt gikk fint, false om noe data mangler eller feil skjer.
	public function loadUserData($p_id) {
		$qry = new SQL("SELECT * 
						FROM `#table`
						WHERE `p_id` = '#p_id'", 
						array('table' => $this->table, 'p_id' => $p_id)
					);

		$res = $qry->run('array');

		if(!$res) {
			return false;
		}

		$this->p_id = $p_id;
		$this->username = $res['username'];
		$this->email = $res['email'];
		$this->password = $res['password'];
		$this->wp_id = $res['wp_id'];

		// Hent rolle for denne bloggen
		$wp_user = new WP_User($this->wp_id);
		$this->role = $wp_user->roles[0];
		$this->description = get_user_meta($this->wp_id, 'description', true);

		## Fetch data from person-object
		$person = new person($p_id, false);
		$this->first_name = $person->g('p_firstname');
		$this->last_name = $person->g('p_lastname');

		$this->valid = true;
		return true;
	}

	# Function getErrors
	# Returnerer et array med liste over feil som skjedde under opprettingen av objektet.
	# Sortert på keys:
	#	info
	#	warning
	# 	danger
	# Info er mest for debug, warning kan ha effekt på brukeren, danger er kritiske feil som vil føre til problemer for enkeltbrukere. Bør varsles til bruker.
	# Loop gjennom alle i debug-modus, varsle om danger uansett
	public function getErrors() {
		return $this->errors;
	}

	public function getSuggestedUsername($p_id = null) {
		if (!$p_id) {
			$p_id = $this->p_id;
		}
		if (!$p_id) {
			$this->errors[] = array('danger' => 'Kan ikke foreslå brukernavn uten en p_id å laste navn fra.');
			return false;
		}
		$person = new person($p_id, false);
		$first_name = $person->g('p_firstname');
		$last_name = $person->g('p_lastname');

		// Ensure that names are loaded
		if (empty($first_name) || empty($last_name) ) {
			$this->errors[] = array('danger' => 'Kan ikke foreslå brukernavn uten å laste inn fornavn eller etternavn');
			return false;
		}

		$clean_firstname = mb_strtolower( str_replace(' ', '.', $first_name ) );
		$clean_lastname = mb_strtolower( str_replace(' ', '.', $last_name ) );
		
		$clean_firstname = $this->_clean( $clean_firstname );
		$clean_lastname = $this->_clean( $clean_lastname );
		
		$username = $clean_firstname.'.'.$clean_lastname;
		return $username;
	}

	private function _clean( $string ) {
		// TODO: Filtrer på flere rare bokstaver

		$string = str_replace('æ', 'a', $string);
		$string = str_replace('ø', 'o', $string);
		$string = str_replace('å', 'a', $string);
		
		return $string;
	}

	private function _set_role($type) {
		$this->role = $this->_getRoleFromType($type);
	}

	private function _getRoleFromType($type) {
		switch($type) {
			case 'nettredaksjon': 
				$role = 'contributor';
				break;
			case 'arrangor': 
				$role = 'ukm_produsent';
				break;
			default: 
				$role = 'contributor';
				// Varsle om at rolle-finning feilet og at contributor er satt
				$this->errors[] = array('danger' => 'UKMusers: Rolle feilet, satt contributor som default! $type er: '.$type);
				error_log('UKMusers: Rolle feilet, satt contributor som default! $type er: '.$type);
		}
		return $role;
	}

	private function _find_blog_id() {
		global $blog_id;

		if (!$blog_id) 
			throw new Exception('UKMusers: Fant ikke blogg-id');
		return $blog_id;
	}
}