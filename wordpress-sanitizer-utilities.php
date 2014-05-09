<?php

if (!defined("locaal")) {
	if (isset($_SERVER, $_SERVER['SERVER_NAME']) && (stristr($_SERVER['SERVER_NAME'],"localhost") || stristr($_SERVER['SERVER_NAME'],"local-"))) {
		define("locaal",true);
	}
	else {
		define("locaal",false);
	}
}

interface iWordpressSanitizerUtilties {

	static function init();
}



/** Utilities for wordpressSanitizer. Implemented utilities for this moment:
 * wipecache
 *
 * Class wordpressSanitizerUtilities
 *
 * @package WordPress Sanitizer
 */
class wordpressSanitizerUtilities implements iWordpressSanitizerUtilties {

	/**
	 * @var wpdb */
	private static $_wpdb = null;

	/**
	 * Check if a valid utility has been called and if the visitor is an admin. Only then the utility will be activated.
	 */
	public static function init() {

		if (!isset($_GET['utility'])) {
			die;
		}

		//online a https-verbinding is mandatory:
		$https_required = !locaal;
		if ($https_required && $_SERVER['SERVER_PORT'] != '443') {
			self::_header404();
		}

		$utility = "_" . $_GET['utility'];

		if (method_exists("wordpressSanitizerUtilities", $utility)) {

			require(dirname(__FILE__) . "/wordpress-sanitizer.php");
			wordpressSanitizer::init(new wordpressSanitizerConfiguration());

			//only admins can use the utilities:

			if ($_GET['utility'] == "setip" || wordpressSanitizer::assert_is_admin()) {
				self::$utility();
			}

			else {
				self::_header404();
			}
		}

		else {
			self::_header404();
		}
	}

	private function _setip () {

		//if no data posted, we need to display a form:
		if (!isset($_POST['password'], $_POST['user'])) {

			wordpressSanitizer::message_display("Wijzig ipnummer", '<p ' . '>Deze pagina detecteert automatisch je gewijzigde ipnummer, dus dat hoef je niet op te geven.</p><form method="post" action="/setip"><label for="user">User</label><input type="text" name="user" id="user" value=""><label for="password">Wachtwoord</label><input type="password" name="password" id="password" value=""><div class="frm_submit">
								<input type="submit" value="Verzend" /> <img class="frm_ajax_loading" src="/p/formidable/images/ajax_loader.gif" alt="Sending" style="visibility:hidden;" />
							</div></form>');

		}

		else {

			self::_wp_database_init();

			if (!self::$_wpdb) {
				return;
			}

			list($rewritemap, $valid_users) = wordpressSanitizer::assert_is_admin(true);

			$user = $_POST['user'];

			if (!isset($valid_users[$user])) {
				self::_header404();
			}

			$password = $_POST['password'];

			$sql = "SELECT " . "user_pass FROM wp_users WHERE user_login = '{$user}'";

			$hash_in_db = self::$_wpdb->get_var($sql);

			/** @noinspection PhpIncludeInspection */
			require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-includes/class-phpass.php' );

			$wp_hasher = new PasswordHash(8, true);
			//$hash_check = $wp_hasher->HashPassword($password);

			$is_valid_user = $wp_hasher->CheckPassword($password, $hash_in_db);

			if ($is_valid_user) {
				$new_ip = wordpressSanitizer::ip_determine();

				//only if the ip address is actually different from the stored ip address in the rewritemap isadmin.map do we need to store this changed address:
				if ($valid_users[$user] != $new_ip) {
					$valid_users[$user] = $new_ip;

					$construct = "";
					foreach ($valid_users as $user => $ip) {
						$construct .= $ip . " " . $user . "\r\n";
					}

					file_put_contents($rewritemap, $construct);

					wordpressSanitizer::message_display("IPnummer gewijzigd", "Het gewijzigde IPnummer &ndash; {$new_ip} &ndash; is opgeslagen!");
				}

				else {
					wordpressSanitizer::message_display("IPnummer NIET gewijzigd", "Het IPnummer hoefde niet gewijzigd te worden (was al bekend)...");
				}

			}
		}
	}

	/**
	 * Wipe the server cache. URL: /wipecache (see .htaccess)
	 */
	private static function _wipecache () {

		wordpressSanitizer::servercache_delete();
	}

	/**
	 * Init the WordPress database library for external utilities
	 */
	private static function _wp_database_init () {
		define("WP_DEBUG_DISPLAY", false);

		define("WP_USE_EXT_MYSQL", false);

		/** @noinspection PhpIncludeInspection */
		require_once($_SERVER['DOCUMENT_ROOT'] . "/wp-config.php");
		/** @noinspection PhpIncludeInspection */
		require_once($_SERVER['DOCUMENT_ROOT'] . "/wp-includes/wp-db.php");

		self::$_wpdb = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
	}


	private function _header404 () {
		header("HTTP/1.0 404 Not Found");
		exit;
	}
}

wordpressSanitizerUtilities::init();