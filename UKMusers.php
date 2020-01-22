<?php
/* 
Plugin Name: UKMusers
Plugin URI: http://www.github.com/UKMNorge/UKMusers
Description: Genererer innlogging for nettredaksjon og arrangører
Author: UKM Norge / M Mandal / A Hustad
Version: 3.0 
Author URI: http://www.github.com/UKMNorge
*/

require_once("UKM/Autoloader.php");

use UKMNorge\Wordpress\User as WordpressUser;
use UKMNorge\Wordpress\LoginToken;
use UKMNorge\Database\SQL\Query;
use UKMNorge\Arrangement\Arrangement;
use UKMNorge\File\Excel;
use UKMNorge\Innslag\Typer\Typer;
use UKMNorge\Innslag\Personer\Person;
use UKMNorge\Wordpress\Blog;
use UKMNorge\Wordpress\WriteUser;
use UKMNorge\Innslag\Typer\Type;

#require_once('UKMuser.class.php');
require_once('UKM/wp_modul.class.php');

class UKMusers extends UKMWPmodul
{
    public static $action = 'userlist';
    public static $path_plugin = null;

    public static function hook()
    {
        add_action('admin_menu', ['UKMusers', 'meny'], 300);
    }

    public static function meny()
    {
        // Knapp i menyen
        $page_deltakerbruker = add_submenu_page(
            'UKMdeltakere', # TODO: Endre til Nettside når du vet hvilken slug det er 
            'Brukere',
            'Brukere',
            'editor',
            'UKMusers_brukere_admin',
            ['UKMusers', 'renderAdmin']
        );

        add_action(
            'admin_print_styles-' . $page_deltakerbruker,
            ['UKMusers', 'scriptsandstyles']
        );
    }

    public static function scriptsandstyles()
    {
        wp_enqueue_script('WPbootstrap3_js');
        wp_enqueue_style('WPbootstrap3_css');
    }

    /**
     * Logger inn en bruker programmatisk. 
     * Sjekker token mot token-tabell og at forespurt bruker finnes i whitelist.
     * 
     * OBS: Krever at siden "Autologin" er opprettet i Wordpress! Denne må ha UKMviseng = delta_autologin, og URL ukm.no/autologin.
     * Dette er linket til funksjonalitet i UKMresponsive.
     * 
     * @param Int $wp_id - Wordpress ID fra ukm_delta_wp_user-tabell
     * @return bool True hvis login OK; false hvis ikke. UKMresponsive tar seg av brukerhåndtering ved feilet innlogging (redirect til Delta).
     * 
     * Test URL: https://ukm.dev/autologin/?wp_id=1&token_id=1&token=22
     */
    public static function loginFromDelta(Int $wp_id, Int $token_id, String $secret)
    {

        // Sjekk token
        try {
            if ($wp_id != LoginToken::use($token_id, $secret)) {
                # Token already used? Does not exist? Etc.
                return false;
            }
        } catch (Exception $e) {
            # Token crashed
            return false;
        }

        // Sjekk at bruker-IDen finnes i ukm_delta_wp_user-tabell - så folk ikke kan autologge inn til UKM Norge-brukerne etc.
        $sql = new Query("SELECT `wp_id` FROM ukm_delta_wp_user WHERE `wp_id` = '#wp_id'", ['wp_id' => $wp_id]);

        $wordpressUserId = $sql->getField();
        if (NULL == $wordpressUserId) {
            die("Failed to find wordpress user in whitelist.");
            return false;
        }

        // Finn bruker fra ID
        $user = WordpressUser::loadById(intval($wordpressUserId));

        if (is_user_logged_in()) {
            wp_logout();
        }

        add_filter('authenticate', 'allow_programmatic_login', 10, 3);    // hook in earlier than other callbacks to short-circuit them
        $user = wp_signon(array('user_login' => $user->getUsername()));
        remove_filter('authenticate', 'allow_programmatic_login', 10, 3);

        if (is_a($user, 'WP_User')) {
            wp_set_current_user($user->ID, $user->user_login);

            if (is_user_logged_in()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Oppretter wordpress-innlogging for deltakerbrukere for et gitt arrangement.
     * Dersom participant'en har en wordpress-bruker (for p_id), legger vi den bare til en ny blogg.
     * Hvis ikke oppretter vi også wordpress-brukeren, og legger den til i delta_wp_user.
     * 
     * @param Arrangement $place
     * @return Array<Innslag> - Alle innslagene som er jobbet med, uten duplikater. Personer er merket med eventuelle feil i midlertidig lagring i attr.
     */
    public static function createLoginsForParticipantsInArrangement(Arrangement $place)
    {
        # Finn alle medie-innslag
        $medieInnslag = $place->getInnslag()->getAllByType(Typer::getByKey("nettredaksjon"));
        # Finn alle arrangør-innslag
        $arrangorInnslag = $place->getInnslag()->getAllByType(Typer::getByKey("arrangor"));
        # Slå sammen de to innslagtypene
        $innslagListe = array_merge($medieInnslag, $arrangorInnslag);
        # Lag en duplikatliste over IDer for å skippe innslag som dukker opp gang nr. 2
        $duplikatListe = array();
        # Filtrert liste er listen over innslag som skal returneres.
        $filtrerteInnslag = array();

        # Arrangør- og medieinnslag har kun èn deltaker, så vi slipper å loope hele getAll().
        foreach ($innslagListe as $innslag) {
            $person = $innslag->getPersoner()->getSingle();
            # Skip om vi allerede har jobbet med denne personen ila denne loopen.
            if (in_array($person->getId(), $duplikatListe)) {
                continue;
            }
            $duplikatListe[] = $person->getId();
            # Legg til innslaget i filtrert liste. Kunne vært løst mer elegant om vi hadde compare-funksjoner
            $filtrerteInnslag[] = $innslag;

            # Try/Catch for å fange errors, men likevel få prøve neste innslag på lista. Har ikke detaljerte feilmeldinger (ie catcher alle exceptions under) pga fare for spaghetti. Blæ.
            try {
                # Se om denne personen har en wordpress-bruker basert på p_id
                $user = null;
                try {
                    $user = WordpressUser::loadByParticipant($person->getId());
                } catch (Exception $e) {
                    try {
                        if( empty( $person->getEpost() ) ) {
                            throw $e;
                        }
                        $user = WordpressUser::loadByEmail( $person->getEpost() );
                        WriteUser::linkWpParticipant( $user->getId(), $person->getId() );
                    } catch( Exception $another_e ) {
                        # Ingen bruker, prøv å opprett èn i stedet.
                        # Vi prøver med 'deltaker_XX' som brukernavn, der participant
                        $username = "deltaker_" . $person->getId();
                        $user = WriteUser::createParticipantUser($username, $person->getEpost(), $person->getFornavn(), $person->getEtternavn(), $person->getMobil(), $person->getId());
                        $user = WriteUser::save($user, false);
                        $person->setAttr('ukmusers_status', 'success')->setAttr('ukmusers_message', "Opprettet ny bruker for " . $person->getNavn() . ".");
                    }
                }

                # Har vi ikke fått bruker til nå gir vi opp:
                if (get_class($user) != WordpressUser::class) {
                    # Sett en advarsel om denne brukeren.
                    $person->setAttr('ukmusers_status', 'danger')->setAttr('ukmusers_message', "Klarte ikke å opprette arrangørsystem-bruker for " . $person->getNavn() . " (id " . $person->getId() . ").");
                    continue;
                }

                # Sjekk også at brukeren har en rolle på hoved-bloggen for å kunne se support-siden.
                if ( !Blog::harHovedbloggBruker($user, 'subscriber') ) {
                    # Brukeren mangler relasjon til bloggen eller er inaktiv, prøv å legg den til.
                    Blog::leggTilHovedbloggBruker( $user );
                    $person->setAttr('ukmusers_status', 'success')->setAttr('ukmusers_message', "Koblet brukeren til hovedbloggen.");
                }

                # Mangler vi fortsatt relasjon til hovedbloggen, gir vi opp:
                if( !Blog::harHovedbloggBruker( $user ) ) {
                    $person->setAttr('ukmusers_status', 'danger')->setAttr('ukmusers_message', "Klarte ikke å koble brukeren til hovedbloggen. De vil derfor ikke kunne åpne 'Brukerstøtte'-siden. Kontakt support for hjelp.");
                }

                if( WordpressUser::erAktiv($user->getId()) ) {
                    # Alt er OK og vi kan gå til neste i listen.
                    continue;
                }

                # Hvis brukeren ikke er aktiv prøver vi å aktivere den ( de fleste brukerne er blitt deaktivert av Marius :'( )
                WriteUser::aktiver($user);
                WriteUser::save($user);
                // Hvis vi lagret OK, er brukeren klar til bruk. Wohoo!
                #$person->setAttr('ukmusers_status', 'success')->setAttr('ukmusers_message', 'Bruker '.$person->getId().' (wp_id '.$user->getId().') lagt til blogg '.get_current_blog_id().' med rolle '.$rolle.'.');
            } catch (Exception $e) {
                $person->setAttr('ukmusers_status', 'danger')->setAttr('ukmusers_message', 'Klarte ikke å opprette Wordpress-bruker for ' . $person->getNavn() . ' (id: ' . $person->getId() . '.');
                continue;
            }
        }

        return $filtrerteInnslag;
    }
}

/**
 * An 'authenticate' filter callback that authenticates the user using only the username.
 *
 * To avoid potential security vulnerabilities, this should only be used in the context of a programmatic login,
 * and unhooked immediately after it fires.
 * 
 * @param WP_User $user
 * @param string $username
 * @param string $password
 * @return bool|WP_User a WP_User object if the username matched an existing user, or false if it didn't
 */
function allow_programmatic_login($user, $username, $password)
{
    return get_user_by('login', $username);
}

UKMusers::init(__DIR__);
## HOOK MENU AND SCRIPTS
if (is_admin()) {
    UKMusers::hook();
}

function UKMusers_brukere_admin()
{
    UKMusers::administrerDeltaBrukere();
}
