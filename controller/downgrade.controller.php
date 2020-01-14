<?php

use UKMNorge\Arrangement\Arrangement;
use UKMNorge\Wordpress\User;
use UKMNorge\Wordpress\WriteUser;

$arrangement = new Arrangement(intval(get_option('pl_id')));
$innslag = $arrangement->getInnslag()->get(intval($_GET['innslag']));
$person = $innslag->getPersoner()->getSingle();
$user = $person->getWordpressBruker();

# Nedgrader brukeren
try {
    WriteUser::nedgraderBruker(
        $user,
        get_current_blog_id(),
        User::getRolleForInnslagType($innslag->getType())
    );
    UKMusers::getFlashbag()->success($person->getNavn() .' har nå fått nedgradert tilgang til arrangørsystemet');
} catch (Exception $e) {
    UKMusers::getFlashbag()->error('Klarte ikke å nedgradere ' . $user->getNavn());
}
