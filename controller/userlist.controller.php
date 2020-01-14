<?php

use UKMNorge\Arrangement\Arrangement;
use UKMNorge\Wordpress\User;
use UKMNorge\Wordpress\WriteUser;

$arrangement = new Arrangement(get_option('pl_id'));
UKMusers::addViewData('filtrerteInnslag', UKMusers::createLoginsForParticipantsInArrangement($arrangement));
UKMusers::addViewData('blog_id', get_current_blog_id());

if (isset($_GET['subaction']) ) {#} && isset($_GET['wp_bruker_id'])) {
    switch ($_GET['subaction']) {
        case 'add':
            UKMusers::include('controller/add.controller.php');
        break;
        case 'remove':
            UKMusers::include('controller/remove.controller.php');
        break;
        case 'upgrade':
            UKMusers::include('controller/upgrade.controller.php');
        break;
        case 'downgrade':
            UKMusers::include('controller/downgrade.controller.php');
        break;
    }
}
