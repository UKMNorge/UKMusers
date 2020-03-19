<?php

use UKMNorge\Wordpress\User;
use UKMNorge\Wordpress\WriteUser;

require_once('UKM/Autoloader.php');

UKMusers::addResponseData('user_id', $_POST['user_id']);

if( !is_super_admin() ) {
    UKMusers::addResponseData('success', false);
} else {
    $user = new User(intval($_POST['user_id']));
    WriteUser::aktiver($user);
    UKMusers::addResponseData('success',true);
}