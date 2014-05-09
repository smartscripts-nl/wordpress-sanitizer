<?php
// ================== CONFIGURATIE VAN WORDPRESS SANITIZER HIER ==============



interface iConfigurator {

	function get_options ();
}



/** Via deze class worden de opties van wordpressSanitizer ingesteld (dependency injection pattern)
 *
 * Class wordpressSanitizerConfigurator
 *
 * Hier aangeroepen:
 * @see wordpressSanitizer::init()
 *
 * @package WordPressSanitizer
 */
class wordpressSanitizerConfiguration implements iConfigurator {

	/** Deze methode - aangeroepen in wordpressSanitizer::init() - levert een array aan met instellingen voor wordpressUtilties. Voor systematisch werken is het het beste als die opties enkel via onderstaande methode worden gedaan.
	 *
	 * @see wordpressSanitizer::init()
	 *
	 * @return array */
	public function get_options () {

		$rewritemap = WPTHEMEFOLDER . "/rewritemap/isadmin.map";

		//use the rewritemap used for .htaccess also for PHP:
		$users = file_get_contents($rewritemap);
		$success = preg_match_all('#(\S+)\s+(\S+)#', $users, $out);

		if ($success) {
			$ipnumbers = $out[1];
			$names = $out[2];
			$count = count($names);

			$users = Array();
			for ($x = 0; $x < $count; $x++) {
				$users[$names[$x]] = $ipnumbers[$x];
			}
		}
		else {
			$users = Array();
		}

		return Array(

			/** wil je de caching uitschakelen, zet onderstaande variabele dan op true in plaats van false:
			 * @var bool */
			"servercache_disable" => false,


			/** de namen van de users en hun ipnummers voor wie de cache wipe button zichtbaar mag zijn. Deze gegevens worden ingelezen uit users.txt.
			 * @var array */
			"users"  => $users,

			/** The path to the rewritemap isadmin.map in the folder /rewritemap in the themefolder.
			 * @var array */
			"rewritemap"  => $rewritemap,

			/** WordPress Sanitizer al of niet uitschakelen (tbv testen en vergelijken)
			 * @var bool */
			"disable_wordpress_sanitizer" => false,

			/** for how many days is the server cache valid? :
			 * @var int */
			"days" => 3,

			/** supply the name of your WordPress-thema here:
			 * @var string */
			"theme_name" => "coloursole",

			/**
			 * if true (in stead of false) for admins there will be displayed an icon/button in de pages with which the server cache can be deleted
			 * @var bool */
			"development_mode" => true,

			/**
			 * Whether to format/beautify the HTML of the pages or not (if true, there is a small chance you will run into trouble if you HTML is very complex or not entirely valid)
			 * @var bool */
			"format_html" => true,

			/** Indien deze property true is, worden de javascripts op de pagina verwijderd. Is hier echter false ingesteld, dan worden de javascripts ongemoeid gelaten.
			 * @var bool */
			"remove_javascripts" => true,

			/** |-gescheiden lijst van deeltermen uit namen van javascriptbestanden die niet uit de pagina verwijderd mogen worden. De scripts die hier worden opgegeven worden op álle pagina's beschermd.
			 * @var string */
			"protected_resources" => "html5",

			/** Lijst van javascripts die op welke pagina's (|-gescheiden) gehandhaafd moeten worden. Alle andere javascript zijn overbodig (plugins willen nogal eens javascripts plaatsen op pagina's waar die helemaal niet gebruikt worden) en kunnen dus verwijderd worden.
			 *
			 * @var array */
			"javascripts_required_per_page" => Array (

				//format: "deelterm_javascript_bestand" => "deeltermen_paginanamen_die_dit_script_nodig_hebben",

				"jquery" => "contact|test-3",
				"frm_js" => "contact",
				"formidable" => "contact",
				"maps\\.google" => "test-3",
				"wpgm" => "test-3",
			),
		);
	}
}

