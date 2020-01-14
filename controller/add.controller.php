<?php

# Sjekk om brukeren er relatert til denne bloggen.

use UKMNorge\Arrangement\Arrangement;
use UKMNorge\Wordpress\Blog;
use UKMNorge\Wordpress\User;

$arrangement = new Arrangement( intval(get_option('pl_id')));
$innslag = $arrangement->getInnslag()->get( intval($_GET['innslag']) );
$person = $innslag->getPersoner()->getSingle();
$user = $person->getWordpressBruker();

if (!Blog::harBloggBruker(get_current_blog_id(), $user)) {
    # Brukeren mangler relasjon til bloggen eller er inaktiv, prøv å legg den til.
    Blog::leggTilBruker(get_current_blog_id(), $user->getId(), User::getRolleForInnslagType($innslag->getType()));
    UKMusers::getFlashbag()->success(
        'Ga ' . $person->getNavn() . ' tilgang til arrangørsystemet'
    );
}

# Mangler vi fortsatt relasjon til bloggen, gir vi opp:
if (!Blog::harBloggBruker(get_current_blog_id(), $user)) {
    UKMusers::getFlashbag()->error(
        'Klarte ikke å koble arrangørsystem-brukeren til  ' . $person->getNavn() . 
        '. Kontakt support.'
    );
}