<?php


// ============================== DISCLAIMER ================================

/*
 * De scripts op deze pagina bevinden zich nog in bèta-stadium. Gebruik ervan is geheel voor eigen risico. SmartScripts is niet verantwoordelijk voor eventuele schade ontstaan door gebruik van deze scripts.
 *
 */


// ============================== CREDITS ========================

/*
 * htmlLawed (http://www.bioinformatics.org/phplabware/internal_utilities/htmLawed/beta/ ; adapted version by Alex Pot), for beautifying and correcting the HTML that WordPress outputs.
 * WordPress of course!
 * WordPress Sanitizer has been written bij Alex Pot of www.smartscripts.nl. A lot of time has been spent putting it all together. So if you plan to use WordPress Sanitizer, I humbly ask that you leave these credits intact.
 *
 * SmartScripts @copy 2014
 */



//=============================== INTERFACES ================================


//interfaces bij onderstaande classes (dat wil zeggen overzichten van hun publieke methoden)

interface iWordpressSanitizerCallbacks {

	static function active_page_mark ($matches);
}

interface iWordpressSanitizer {

	static function init(iConfigurator $configuration);

	static function servercache_start ();

	static function active_page_mark ($menu, $has_no_listitem = false);

	static function assert_is_admin ($only_return_user_list = false);

	static function message_display ($title = "", $message = "");

	static function servercache_delete($nomessage = false);

	static function html_sanitize_and_echo ();
}


//============================== EINDE INTERFACES ===========================








//============================ LOKAAL OF ONLINE? =============================


//lokaal en online moeten de afbeeldingen op andere manieren verwerkt worden (vanwege verschillende bestandspaden):
if (!defined("locaal")) {
	if (isset($_SERVER, $_SERVER['SERVER_NAME']) && (stristr($_SERVER['SERVER_NAME'], "localhost") || stristr($_SERVER['SERVER_NAME'], "local-"))) {
		define("locaal", true);
	}
	else {
		define("locaal", false);
	}
}



if (!defined("WPTHEMEFOLDER")) {
	define("WPTHEMEFOLDER", dirname(__FILE__));
}




// ======================== DE CLASSES VAN WORDPRESSANITIZER ==================



require_once(WPTHEMEFOLDER . "/wordpress-sanitizer-config.php");

/*in deze include kun je je eigen requires plaatsen (met require_once). Let op: dit bestand is niet ingesloten in het repository, moet je zelf aanmaken)

	Place your own custom require_once includes in this file. This file has not been included in the repository, you have to place it yourself.
*/
require_once(WPTHEMEFOLDER . "/custom-require-once.php");





/** Callbacks ten behoeve van WPsanitizer
 *
 * Class wordpressSanitizerCallbacks
 *
 * @package WordPressSanitizer
 */
class wordpressSanitizerCallbacks implements iWordpressSanitizerCallbacks {

	public static function active_page_mark ($matches) {
		$item = $matches[0];

		$has_list_tags = (stristr($item, "<li"));

		preg_match('#href\s*=\s*[\'"]([^\'">]+)#', $item, $out);
		$id = $out[1];

		$is_numeric = (preg_match('#^\d+$#', $id));

		if ($is_numeric) {
			// get_page_link() is een WordPress-functie:
			/** @noinspection PhpUndefinedFunctionInspection */
			$url = get_page_link($id);
		}

		//indien er geen numerieke id is gebruikt is de url reeds volledig door WordPress ingevuld en kan dus rechtstreeks gebruikt worden:
		else {
			$url = $id;
		}

		//verwijder absolute deel uit WordPress-links (anders geen match met $_SERVER['REQUEST_URI'] mogelijk):
		$url = preg_replace('#https?://[^/]+#i', "", $url, 1);

		$mark_active = preg_match('#' . $url . '/?$#', $_SERVER['REQUEST_URI']);

		$linktext = "";
		$success = preg_match('#<a[^>]*>(.*?)</a>#', $item, $out);
		if ($success) {
			$linktext = $out[1];
		}

		$class = "";
		$success = preg_match('#class\s*=\s*[\'"]([^\'"]+)#', $item, $out);
		if ($success) {
			$class = $out[1];
		}

		if ($mark_active) {
			if ($has_list_tags) {
				return "<li class='active {$class}'>{$linktext}</li>";
			}
			else {
				return $linktext;
			}
		}

		else {
			if ($class) {
				$class = " class=\""  . $out[1] . "\"";
			}

			$opener = ($has_list_tags) ? "<li>" : "";
			$closer = ($has_list_tags) ? "</li>" : "";

			return "{$opener}<a " . "href=\"{$url}\"{$class}>{$linktext}</a>{$closer}";
		}
	}
}

/** Voeg cache-control toe aan pagina's en markeer actieve pagina's in menu's
 *
 * Class wpUtilities
 *
 * @see wordpressSanitizer::html_sanitize_and_echo()
 * @see wordpressSanitizerConfiguration::get_options()
 *
 * @package WordPressSanitizer
 */
class wordpressSanitizer implements iWordpressSanitizer {

	//de instellingen hieronder worden grotendeels via de class wordpressSanitizerConfiguration ingesteld (dependency injection pattern)


	/** @see wordpressSanitizerConfiguration::get_options()
	 * @var bool */
	private static $_servercache_disable = false;

	/** @see wordpressSanitizerConfiguration::get_options()
	 * @var array */
	private static $_users = Array();

	/** @see wordpressSanitizerConfiguration::get_options()
	 * @var array */
	private static $_rewritemap = Array();

	/** @see wordpressSanitizerConfiguration::get_options()
	 * @var bool */
	private static $_disable_wordpress_sanitizer = false;

	/** @see wordpressSanitizerConfiguration::get_options()
	 * @var int */
	private static $_days = 3;

	/** @see wordpressSanitizerConfiguration::get_options()
	 * @var string */
	private static $_theme_name = "";

	/** @see wordpressSanitizerConfiguration::get_options()
	 * @var bool */
	private static $_development_mode = false;

	/** @see wordpressSanitizerConfiguration::get_options()
	 * @var bool */
	private static $_format_html = true;

	/** @see wordpressSanitizerConfiguration::get_options()
	 * @var bool */
	private static $_remove_javascripts = true;

	/** @see wordpressSanitizerConfiguration::get_options()
	 * @var string */
	private static $_protected_resources = "";

	/** @see wordpressSanitizerConfiguration::get_options()
	 * @var array */
	private static $_javascripts_required_per_page = Array ();

	/** Het ipnummer van de bezoeker (wordt gebruikt om te kijken of de cache-wipe-button moet worden weergegeven
	 * @var string */
	private static $_ip = "";

	private static $_is_registered_user = false;

	private static $_form_posted = false;

	private static $_initiated = false;

	private static $_post_time = 0;

	/** Combineer alle scripts op de pagina tot één script en sla die op in een extern bestand. Echo tevens de pagina en cache die.
	 *
	 * @see wp_cache
	 * @see wordpressSanitizer::servercache_start()
	 */
	public static function html_sanitize_and_echo () {

		if (self::$_disable_wordpress_sanitizer) {
			return;
		}

		//hier halen we de html van de pagina op uit die outputbuffer. De outputbuffer werd met ob_start() gestart in wordpressSanitizer::servercache_start():
		//get de HTML of the page from the output buffer. This buffer was started with ob_start() in wordpressSanitizer::servercache_start():
		$html = ob_get_clean();

		//plaats een icoon voor het wissen van de servercache indien het script in development mode staat en de bezoeker een admin is:
		//inject an icon/button for deleting the server cache (only if the WPS has been put in development mode and the visitor is an admin):
		self::_servercache_delete_button_add($html);

		self::_required_javascripts_compute();

		self::_javascripts_combine($html);
		self::_stylesheets_combine($html);

		self::_urls_shorten($html);

		self::_html_corrections($html);

		//corrigeer/formatteer/beautify de HTML:
		self::_html_format($html);

		self::_post_time_get();

		self::_cache_control_headers_send($html);

		echo $html;

		self::_servercache_save($html);
	}

	private static function _urls_shorten (&$subject) {
		//absolute links inkorten:
		//shorten absolute links:
		$subject = preg_replace('#http://[^/><\'") ]+#', "", $subject);

		//obfusceer de standaard WordPress-paden (.htaccess moet hierop zijn afgestemd):
		//obfuscate the default WordPress paths (.htaccess has to have been adapted for this):
		$subject = str_replace(Array("wp-content/uploads", "/uploads", "wp-content/themes", "wp-content/plugins", "wp-admin", "wp-content", "wp-includes"), Array("resources", "/resources", "t", "p", "a", "c", "i"), $subject);
	}

	private static function _javascripts_combine(&$html) {

		//verwijder alle javascripts op de openbare pagina:
		//remove all javascripts from the public page:
		if (self::$_remove_javascripts) {

			$javascript_construct = "";

			$javascript_already_combined = false;

			$success = preg_match_all('#<script[^>]*?src\s*=\s*[\'"]([^\'"]+)#i', $html, $out);

			$target = $html_target = "";

			$external_sources = ($success) ? $out[1] : Array();

			if (!$success || (count($out[1]) == 1 && stristr($external_sources[0], "html5"))) {
				self::_resources_delete_from_page($html, "javascript", "embedded");
				self::_resources_delete_from_page($html, "javascript", "external");
				return;
			}

			if (!$javascript_already_combined) {
				$all_targets = join("", $external_sources);
				$html_target = "/scripts/" . md5($all_targets) . ".js";
				$target = $_SERVER['DOCUMENT_ROOT'] . $html_target;

				//het cachefile niet opnieuw opslaan als functions.php en de post niet nieuwer zijn dan dat cachefile:
				//no need to store the server cache file again if functions.php and the post aren't newer than this server cache file:
				if (file_exists($target)) {

					$filemtime = filemtime(__FILE__);
					self::_post_time_get();

					$resource_time = filemtime($target);

					if ($resource_time > $filemtime && $resource_time > self::$_post_time) {
						$javascript_already_combined = true;
					}
				}
			}

			if (!$javascript_already_combined && $external_sources) {

				foreach ($external_sources as $src) {
					if (!preg_match('#(?:' . self::$_protected_resources . ')#', $src) || stristr($src, "html5.js")) {
						continue;
					}

					//verwijder eventuele versienummers:
					//remove version numbers (in querystrings) if present:
					$file_src = preg_replace('#[?].+$#', "", $_SERVER['DOCUMENT_ROOT'] . $src, 1);

					if (file_exists($file_src)) {
						$javascript_construct .= file_get_contents($file_src) . "\r\n";
					}
				}

				//verwijder alle links naar externe scripts:
				//remove all links to external javascripts:
				self::_resources_delete_from_page($html, "javascript", "external");


				//lees alle embedded scripts in:
				//collect all embedded javascripts:
				$success = preg_match_all('#<script(?:[^>](?!src))*>(.*?)</script>#si', $html, $out);

				if ($success) {
					foreach ($out[1] as $script) {

						if (!$script || !preg_match('#(?:' . self::$_protected_resources . ')#', $script)) {
							continue;
						}

						$javascript_construct .= $script . "\r\n";
					}

					//verwijder alle embedded scripts uit de pagina:
					//remove all embedded scripts from the page:
					self::_resources_delete_from_page($html, "javascript", "embedded");
				}

				//cache het verzamelde javascript (mits dat er is):
				//cache the collected javascript (if existant):
				if ($javascript_construct) {
					file_put_contents($target, $javascript_construct);
					if (!locaal) {
						chmod($target, 0666);
					}

					//sla ook een gzipped versie op:
					$gzipped = gzencode($javascript_construct);
					file_put_contents($target . ".gzip", $gzipped);
					if (!locaal) {
						chmod($target . ".gzip", 0666);
					}
				}
			}

			if (($javascript_already_combined || $javascript_construct) && $html_target) {
				self::_resources_delete_from_page($html, "javascript", "embedded");
				self::_resources_delete_from_page($html, "javascript", "external");

				//link naar het verzamelde javascript:
				$html = str_replace("</body>", "<script src='" . "{$html_target}'></script>\r\n</body>", $html);
			}

		}

	}

	private static function _stylesheets_combine(&$html) {

		//verwijder alle stylesheets op de openbare pagina:
		//remove all stylesheets from the public page:
		if (self::$_remove_javascripts && !self::$_servercache_disable) {

			$css_construct = "";

			$sanitizer_css = WPTHEMEFOLDER . "/wordpress-sanitizer.css";
			if (file_exists($sanitizer_css)) {
				$css_construct .= file_get_contents($sanitizer_css) . "\r\n";
			}

			$css_already_combined = false;

			$success = preg_match_all('#<link[^>]*?href\s*=\s*[\'"]([^\'"]+?\.css)#i', $html, $out);

			$target = $html_target = "";
			$external_sources = Array();

			if ($success) {
				$external_sources = $out[1];

				$all_targets = join("", $external_sources);
				$code = md5($all_targets);
				$html_target = "/css/{$code}.css";
				$target = $_SERVER['DOCUMENT_ROOT'] . "/css/{$code}.css";

				//het cachefile niet opnieuw opslaan als functions.php en de post niet nieuwer zijn dan dat cachefile:
				//don't store the server cache file again if functions.php and the post are older than this same cache file:
				if (file_exists($target)) {

					$filemtime = filemtime(__FILE__);
					self::_post_time_get();

					$resource_time = filemtime($target);

					if ($resource_time > $filemtime && $resource_time > self::$_post_time) {
						$css_already_combined = true;
					}
				}
			}

			if (!$css_already_combined && $external_sources) {

				if ($external_sources) {
					foreach ($external_sources as $src) {
						if (preg_match('#(?:ie\d+\.css$)#', $src)) {
							continue;
						}

						//verwijder eventuele versienummers:
						//remove version numbers (in querystrings) if present:
						$file_src = preg_replace('#[?].+$#', "", $_SERVER['DOCUMENT_ROOT'] . $src, 1);

						if (file_exists($file_src)) {
							$css_construct .= file_get_contents($file_src) . "\r\n";
						}
					}
				}

				//verwijder alle links naar externe stylesheets:
				//remove all links to external stylesheets:
				self::_resources_delete_from_page($html, "stylesheet", "external");


				//lees alle embedded CSS in:
				//collect all embedded CSS:
				$success = preg_match_all('#<style[^>]*>(.*?)</script>#si', $html, $out);

				if ($success) {
					foreach ($out[1] as $css) {

						if (!$css || !preg_match('#(?:' . self::$_protected_resources . ')#', $css)) {
							continue;
						}

						$css_construct .= $css . "\r\n";
					}

					//verwijder alle embedded stylesheets uit de pagina:
					//remove all embeddes stylesheets from the page:
					self::_resources_delete_from_page($html, "stylesheet", "embedded");
				}

				//cache het verzamelde javascript (mits dat er is):
				if ($css_construct) {

					//correcties van de CSS:
					//correct the CSS:
					$css_construct = preg_replace('#([\'"])webfonts#', "\\1/t/" . self::$_theme_name . "/webfonts", $css_construct);

					self::_urls_shorten($css_construct);

					file_put_contents($target, $css_construct);
					if (!locaal) {
						chmod($target, 0666);
					}

					//sla ook een gzipped versie op:
					//also store a gzipped version of the server cache file:
					$gzipped = gzencode($css_construct);
					file_put_contents($target . ".gzip", $gzipped);
					if (!locaal) {
						chmod($target . ".gzip", 0666);
					}
				}
			}

			if (($css_already_combined || $css_construct) && $html_target) {
				self::_resources_delete_from_page($html, "stylesheet", "embedded");
				self::_resources_delete_from_page($html, "stylesheet", "external");

				//link naar het verzamelde javascript:
				//inject a link to the collected javascript:
				$html = str_replace("</head>", "<link rel='stylesheet' href='" . "{$html_target}'>\r\n</head>", $html);
			}

		}

	}

	private static function _resources_delete_from_page (&$html, $stream = "javascript", $target = "embedded") {

		if ($stream == "javascript") {
			//verwijder alle embedded scripts uit de pagina:
			//remove all embedded javascripts from the page:
			if ($target == "embedded") {
				$html = preg_replace('#<script(?:[^>](?!\bsrc))*>.*?</script>#si', "", $html);
			}

			//verwijder alle links naar externe scripts
			//remove all links to external javascripts
			else {
				$html = preg_replace('#<script[^>]*?\bsrc\b(?:[^>](?!html5))*?>.*?</script>#si', "", $html);
			}
		}

		//stylesheets:
		else {
			//verwijder alle embedded stylesheets uit de pagina:
			//remove all embedded stylesheets from the page:
			if ($target == "embedded") {
				$html = preg_replace('#<style[^>]*>.*?</style>#si', "", $html);
			}

			//verwijder alle links naar externe stylesheets:
			//remove all links to external stylesheets:
			else {
				$html = preg_replace('#<link(?:[^>](?!ie\d+))*?stylesheet(?:[^>](?!ie\d+))*?>#i', "", $html);
			}
		}
	}

	/**
	 *
	 * @param iConfigurator $configuration Het configuratie-object dat de instellingen aanlevert voor deze class
	 */
	public static function init(iConfigurator $configuration) {

		if (self::$_initiated) {
			return;
		}

		self::$_initiated = true;

		self::$_form_posted = (isset($_POST) && $_POST);

		//stel die via het configuratie-object opgehaalde opties in:
		$options = $configuration->get_options();
		foreach ($options as $key => $value) {
			$key = "_" . $key;
			if (property_exists("WordpressSanitizer", $key)) {
				self::$$key = $value;
			}
		}

	}

	/**
	 * Start het caching proces; indien er een geldig cachebestand bestaat, wordt dat geëchood, het script afgebroken en WordPress dus verder niet meer geladen.
	 */
	public static function servercache_start () {
		if (self::$_disable_wordpress_sanitizer) {
			return;
		}

		self::assert_is_admin();

		$expire = 3600 * 24 * self::$_days;
		header("Cache-Control: max-age={$expire},must-revalidate,proxy-revalidate");

		$success = preg_match('#/(.*?)/?$#', $_SERVER['REQUEST_URI'], $out);

		$url ="unknown";

		if ($success) {
			$url = (!$out[1]) ? "index" : $out[1];

			//verwijder querystrings uit de naam van het cachebestand:
			//remove querystrings from the name of the server cache file:
			$url = preg_replace('#[?].+$#', "", $url, 1);

			//pagina's in "submappen" converteren naar een bestandsnaam zonder slashes:
			//convert pages in "sub folders" to a filename without slashes:
			$url = str_replace("/", "-", $url);
		}

		//HTML-cache voor registered users op een andere locatie opslaan:
		//store HTML server cache in another location for admins:
		if (self::$_is_registered_user) {
			define("WPPOSTCACHEFILE", $_SERVER['DOCUMENT_ROOT'] . "/cache-for-admins/{$url}.html");
		}

		else {
			define("WPPOSTCACHEFILE", $_SERVER['DOCUMENT_ROOT'] . "/cache/{$url}.html");
		}

		//geen caching-technieken toepassen voor een gepost formulier:
		//don't use server cache in case of posted forms:
		if (!self::$_servercache_disable && file_exists(WPPOSTCACHEFILE) && !self::$_form_posted) {

			$cachetime = filemtime(WPPOSTCACHEFILE);

			$cache_is_valid = (time() - $cachetime <= $expire);

			//cachefile en eventuele not-modified-headers enkel gebruiken wanneer het cachebestand niet ouder is dan het in $expire opgegeven aantal dagen:
			//use server cache file and not-modified headers only if the server cache file not is older than the given number of days in $expire:
			if ($cache_is_valid) {

				//kijken of we alles kunnen afhandelen via een not-modified header:
				//check if we only have to send a not-modified header (browser cache not expired):
				$if_modsince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) : false;

				//http_if_none_match contains the etag as it was sent to the browser during a previous request of the same file:
				$if_etag = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? str_replace('"', '', stripslashes($_SERVER['HTTP_IF_NONE_MATCH'])) : false;

				$etag = md5_file(WPPOSTCACHEFILE);

				//if the etag is still the same as the etag sent during a previous request or if the file modification time of the online file is less then or equal to the file modifcation time of the file the browser has in its cache, send a not-modified header (in this case you don't have to send the html of the page again):
				if (($if_modsince && $cachetime <= $if_modsince) || $etag == $if_etag) {
					header('HTTP/1.0 304 Not Modified');

					if (ob_get_length()) {
						ob_end_clean();
					}

					//als we hier zijn het script afbreken, we hoeven dan geen content te sturen:
					//if we've come to this position, we don't have to send any content anymore:
					die;
				}


				//indien ondersteund door de browser van de bezoeker sturen we bij voorkeur het gzipped cachebestand:
				//if supported by the visitor's browser we preferably send the gzipped version of the server cache file:
				$gzOK = (isset($_SERVER, $_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip'));

				if ($gzOK && file_exists(WPPOSTCACHEFILE . ".gzip")) {

					header("Content-Encoding: gzip");
					header("Vary: Accept-Encoding");

					readfile(WPPOSTCACHEFILE . ".gzip");
					die;
				}

				//als we hier zijn, ondersteunt de browser van de bezoeker klaarblijkelijk geen gzip-compressie:
				//if weh have come to here, the visitor's browser clearly doesn't support gzip compression:
				readfile(WPPOSTCACHEFILE);
				die;
			}
		}

		//vang de output af, om de onzinnige rommel (absolute links) van WordPress te corrigeren en om bestandspaden te obfusceren:
		//start catching the output buffer, to get the chance to correct the WordPress HTML and to obfuscate paths therein, before sending the HTML to the browser:
		ob_start();
	}

	/**
	 * @param string $menu
	 * @param bool $has_no_listitem Indien true, dan enkel matchen op links. Bijvoorbeeld handig voor link naar homepagina rondom image.
	 */
	public static function active_page_mark ($menu, $has_no_listitem = false) {

		$regex = ($has_no_listitem) ? '#<a.*?</a>#si' : '#<li.*?</li>#si';

		$menu = preg_replace_callback($regex, "wordpressSanitizerCallbacks::active_page_mark", $menu);

		echo $menu;
	}

	/** return array with count and list of servercache files
	 *
	 * @return array ($total_count, $cachefiles)
	 */
	private static function _servercache_files_get () {
		$cachefiles = Array();

		$cachefolders = Array("/cache", "/cache-for-admins", "/css", "/scripts");

		$total_count = 0;

		foreach ($cachefolders as $folder) {
			$files = scandir($_SERVER['DOCUMENT_ROOT'] . $folder);
			$count = count($files);

			//lege map met enkel .. en . :
			//empty folder with only .. and . :
			if ($count == 2) {
				continue;
			}

			$temp = Array();

			foreach ($files as $file) {
				//scandir() laat pad van files weg:
				//scandir() doesn't return the path to files:
				$path = $_SERVER['DOCUMENT_ROOT'] . $folder . "/" . $file;

				if (in_array($file, Array(".", "..")) || is_dir($path) || substr($file, 0, 1) == ".") {
					$count--;
					continue;
				}

				$temp[] = $path;
			}

			$files = $temp;

			$total_count += $count;
			$cachefiles = array_merge($cachefiles, $files);
		}

		return Array($total_count, $cachefiles);
	}

	/** Check whether a vistor is an admin (based on ip address). If this is true, the array with registered users is returned. The names of these users are NOT obtained from the WordPress database, but from /wp-content/themes/[themename]/rewritemap/isadmin.map
	 *
	 * @param bool $only_return_user_list = false
	 *
	 * @return array
	 */
	public static function assert_is_admin ($only_return_user_list = false) {

		if (!$only_return_user_list) {

			//ipdefinitie
			//ip address definition
			self::ip_determine();

			self::$_is_registered_user = (locaal || in_array(self::$_ip, self::$_users));

			if (self::$_is_registered_user) {
				return self::$_users;
			}
			else {
				return Array();
			}
		}

		//when this method has been called from wordpressSanitizerUtilities::_setip() we want to receive some additional data:
		else {
			return Array(self::$_rewritemap, self::$_users);
		}
	}

	public static function ip_determine () {
		if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			self::$_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} elseif(isset($_SERVER['HTTP_CLIENT_IP'])) {
			self::$_ip = $_SERVER['HTTP_CLIENT_IP'];
		} else {
			self::$_ip = $_SERVER['REMOTE_ADDR'];
		}

		return self::$_ip;
	}

	/** Returns de HTML of the WordPress Sanitizer template (if existing).
	 *
	 * @param string $title
	 * @param string $message
	 *
	 * @return string
	 */
	public static function message_display ($title = "", $message = "") {

		$template = WPTHEMEFOLDER . "/wordpress-sanitizer-template.html";

		if (file_exists($template)) {
			$template = file_get_contents($template);

			$message = str_replace(Array("((title))", "((message))"), Array($title, $message), $template);
		}

		//indien er geen template bestaat, worden de berichten in een volkomen kale HTML-pagina weergegeven:
		//if there is no template available, messages will be displayed in an empty HTML page:

		echo $message;
	}

	/** Delete the server cache
	 *
	 * @param bool $nomessage
	 */
	public static function servercache_delete($nomessage = false) {

		list($total_count, $files) = self::_servercache_files_get();

		foreach ($files as $path) {

			unlink ($path);
		}

		if ($nomessage) {
			return;
		}

		$link = "<br /><a " . "href=\"/\">naar de homepagina</a>";

		$title_OK = "Cache gewist";
		$message_OK = "{$total_count} bestanden gewist!<br /><strong>Vergeet niet om ook nog je browsercache te wissen...</strong>" . $link;

		$title_error = "Geen cachebestanden gevonden";
		$message_error = "Er waren geen cachebestanden om te wissen..." . $link;

		$success = $total_count;

		self::_error_status_display($success, $message_OK, $message_error, $title_OK, $title_error);
	}

	/** Geef een melding weer, die afhankelijk van $success ofwel een succesmelding is, ofwel een foutmelding
	 *
	 * @param mixed $success
	 * @param string $message_OK
	 * @param string $message_error
	 * @param string $title_OK
	 * @param string $title_error
	 */
	private static function _error_status_display($success, $message_OK = "", $message_error = "", $title_OK = "", $title_error = "") {

		//berichten met gebruik van een template:
		//message displayed WITH a template:
		if ($success) {
			$title = $title_OK;

			$message = $message_OK;
		}

		else {
			$title = $title_error;

			$message = $message_error;
		}

		self::message_display($title, $message);
	}

	/**
	 * Bepaal het tijdstip van publicatie van de post op de huidige pagina
	 */
	private static function _post_time_get() {
		if (!self::$_post_time) {
			ob_start();
			the_date();
			echo " ";
			the_time();
			self::$_post_time = strtotime(ob_end_clean());
		}
	}

	private static function _cache_control_headers_send($html) {
		if (!self::$_form_posted) {
			header('Last-Modified: ' . gmdate("D, d M Y H:i:s", self::$_post_time - 2) . " GMT");

			//ETag ten behoeve van cache-control zenden:
			//send ETag for cache-control:
			header("ETag: " . md5($html));
		}

		//bij gepost formulier zorgen dat de pagina altijd verlopen is:
		//if a form was posted, make sure the page is marked as expired:
		else {
			header("Expires: " . gmdate("D, d M Y H:i:s") . " GMT"); // Always expired
			header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
			header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate"); // HTTP/1.1
			header("Pragma: nocache"); // HTTP/1.0
		}
	}

	/** Adds a button for wiping the servercache to the page
	 *
	 * @param string $html
	 */
	private static function _servercache_delete_button_add(&$html) {

		if (self::$_servercache_disable) {

			//make sure that no servercache remains (and display no messages about this deletion):
			self::servercache_delete(true);

			return;
		}

		//button only available in development mode and for registered users:
		if (!self::$_development_mode || !self::$_is_registered_user) {
			return;
		}

		$use_button = true;

		//if servercache disabled in the settings, the wipe button only will be displayed if there are still some servercache files present:
		if (self::$_servercache_disable) {

			/** @noinspection PhpUnusedLocalVariableInspection */
			list ($file_count, $files) = self::_servercache_files_get();

			//if no cachefiles were found, $use_button will be false:
			$use_button = $file_count;
		}

		if ($use_button) {
			$url = (locaal) ? "/wipecache" : "https://" . $_SERVER['SERVER_NAME'] . "/wipecache";

			$html = str_replace("</body>", "<a " . "href='{$url}' id='wipecache'><img src='/images/wipe.png' height='16' width='16' alt='Verwijder servercache' title='Klik hier om de servercache te wissen'></a>\r\n</body>", $html);
		}
	}

	/**
	 * @param $html
	 */
	private static function _servercache_save($html) {
		if (!self::$_servercache_disable && !self::$_form_posted && defined("WPPOSTCACHEFILE")) {

			file_put_contents(WPPOSTCACHEFILE, $html);
			if (!locaal) {
				chmod(WPPOSTCACHEFILE, 0666);
			}

			$gzipped = gzencode($html);

			file_put_contents(WPPOSTCACHEFILE . ".gzip", $gzipped);
			if (!locaal) {
				chmod(WPPOSTCACHEFILE . ".gzip", 0666);
			}
		}
	}

	/**
	 * @param $html
	 */
	private static function _html_format(&$html) {
		if (self::$_format_html && !self::$_form_posted) {
			//laad de HTML-formatter:
			require_once(WPTHEMEFOLDER . "/wordpress-sanitizer-htmllawed.php");
			$html = htmlLawed::purify($html);
		}
	}

	private static function _required_javascripts_compute() {
		//pas de regex voor te verwijderen javascripts afhankelijk van de pagina aan (alleen op pagina's die een bepaald javascript vereisen wordt dat javascript gehandhaafd):
		foreach (self::$_javascripts_required_per_page as $script => $pages) {
			if (preg_match('#(?:' . $pages . ')[^/]*/?$#', $_SERVER['REQUEST_URI'])) {
				self::$_protected_resources .= "|" . $script;
			}
		}
	}

	/**
	 * @param $html
	 */
	private static function _html_corrections(&$html) {
		//pleister:
		$html = preg_replace('#/[a-z]/resources#', "/resources", $html);
	}
}

