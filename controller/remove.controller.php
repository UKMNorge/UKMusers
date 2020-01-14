<?php

# Sjekk om brukeren er relatert til denne bloggen.

use UKMNorge\Arrangement\Arrangement;
use UKMNorge\Wordpress\Blog;
use UKMNorge\Wordpress\User;

$arrangement = new Arrangement( intval(get_option('pl_id')));
$innslag = $arrangement->getInnslag()->get( intval($_GET['innslag']) );
$person = $innslag->getPersoner()->getSingle();
$user = $person->getWordpressBruker();

if ( Blog::harBloggBruker(get_current_blog_id(), $user)) {
    # Brukeren mangler relasjon til bloggen eller er inaktiv, prøv å legg den til.
    try {
        Blog::fjernBruker(get_current_blog_id(), $user->getId());
    } catch( Exception $e ) {
        if( $e->getCode() !== 171006 ) {
            throw $e;
        }
    }
    UKMusers::getFlashbag()->success(
        'Fjernet ' . $person->getNavn() . ' sin tilgang til arrangørsystemet'
    );
}

# Mangler vi fortsatt relasjon til bloggen, gir vi opp:
if ( Blog::harBloggBruker(get_current_blog_id(), $user)) {
    UKMusers::getFlashbag()->error(
        'Klarte ikke å fjerne ' . $person->getNavn() . ' sin tilgang til arrangørsystemet! ' .
        'Kontakt <a href="mailto:support@ukm.no?subject=Fjern bruker ' . $user->getId() . ' fra ' . get_current_blog_id() . '">UKM Norge support</a>'
    );
}