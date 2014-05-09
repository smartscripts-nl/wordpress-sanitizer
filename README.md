#WordPress-Sanitizer

*Optimizes WordPress HTML and makes WordPress pages load faster*

##Disclaimer

The scripts in this repository are in bèta-phase. Use them at your own risk! SmartScripts is not liable for damage following from usage of these scripts.

## Credits ##

1. WordPress (of course!)
2. [htmlLawed](http://www.bioinformatics.org/phplabware/internal_utilities/htmLawed/beta/), to beautify and correct the WordPress HTML (adapted version by Alex Pot)

## About this Respository ##

The most up-to-date branch in this repository will always be *develop*.

##Features of WordPress-Sanitizer

 * **Removes unnecessary javascripts and stylesheets** from pages.
 * **Combines all remaining resources** after removal (javascripts and stylesheets, both external and embedded) into a single resource for both javascript and CSS. Thanks to this pages will load faster, because of the fewer number of server requests that are necessary this way.
 * **Make the long, absolute urls of WordPress relative** and rewrite them to aliases. This way a site will become less recognizable as a WordPress-site and therefor hopefully less susceptible to attacks by hackers.
 * With Wordpress Sanitizer you can **gain much faster page loading**. The reason for this is that all HTML, CSS and javascript will be stored in (gzip-compressed) server cache. During ensueing server requests this server cache:

	1. will either be sent directy to the visitor's browser (sometimes even without  intervention of PHP, if and when you have the right to implement rewritemaps on your server),
	2. or not be sent at all, because of the cachecontrol by WordPress Sanitizer (not modified headers), in which case the pages content will be loaded from the browser cache.

* Places a button in the HTML for **deleting all browser cache in one fell swoop**. This button will only be visible for admins, whose ip addresses have been defined in a rewrite map.
 * **Formats/beautifies the HTML** of the pages  and tries to **repair** (if possible) HTML-errors.
 * Can mark **mark active pages** in the site menu.

## Special URL ##

Admins can adjust their ip address (stored in the rewritemap /wp-content/themes/[themename]/rewritemap/isadmin.map) with the from at https://www.[domain]/setip. Thanks to this address on the public pages there will be available only for admins a button/icon with which they can wipe the server cache of the site.

##Howto's

The optimalisations by WordPress Sanitizer can be activated in the following way:

1. WordPress Sanitizer has to be loaded at the top of functions.php:

		define("WPTHEMEFOLDER", dirname(__FILE__));
		require_once(WPTHEMEFOLDER . "/wordpress-sanitizer.php");


2. At the top of index.php (or a comparable page, for example page.php) in the theme folder servercaching can be activated this way:

        wordpressSanitizer::init(new wordpressSanitizerConfiguration());
        wordpressSanitizer::servercache_start();


3. In the same pages in the theme folder the HTML is being caught and optimised with:

		wordpressSanitizer::html_correct_and_echo();

4. Settings for WordPressSanitizer have to be done in wordpress-sanitizer-config.php, in this class method:

		wordpressSanitizerConfiguration::get_options();

5. **Marking pages active** in the site menu or in links to the home page (this code can for example be applied in header.php in the thema folder):

	* 1) marking pages as active in the **menu** (WPS can handle both "pretty url's" written by WordPress or numerical id's of pages and posts in the database):

    		<?php ob_start(); ?>

    		<li><a href="order">order</a></li>

			or

			<li><a href="326">order</a></li>

    		<?php wordpressSanitizer::active_page_mark(ob_get_clean());
			?>

	* 2) marking as active (read: removing) the **link to the home page** when the visitor is on that home page:

			<?php
			require_once("wordpress-sanitizer.php");
			ob_start();
			?>

			<a href="45"><img src="/wp-content/themes/themefolder/images/banner-theme.gif" alt=""></a>

			<?php
			//argument true means: this "menu" has no list items:
			wordpressSanitizer::active_page_mark(ob_get_clean(), true);
			?>
6. **Defining admins**: in the file wp-content/themes/[themename]/rewritemap/isadmin.map you can define admins and their ip-addresses. These addresses wil we used to determine if someone is an admin. Only for admins a **cache wipe button** will be added to the HTML, wherewith they can delete the server cache. The server cache for admin and non-admins is stored in two different locations, /cache-for-admins and /cache, respectively. These folders have been protected from access in the browser bij an .htaccess file in these folders. The same goes for wp-content/themes/[themename]/rewritemap/ . An example of the contents of isadmin.map (on each new line you start with an ip address, followed by the name of the admin):

		127.0.0.1 lokaal
		77.44.172.12 Helen
		54.11.234.6 Walter


##Requirements

1. In the root folder of the site these **writable** folders have to be present: "cache", "cache-for-admins" "css", "images" and "scripts".
2. Also upload the folder /images to your server.
3. The rewritemap /wp-content/themes/[themename]/rewritemap/isadmin.map has to be writable! Otherwise admins won't be able to adjust their ip address stored in this file.
3. Your server has to support mod_rewrite.

##Optional extension with rewritemaps

*If your server supports rewritemaps AND you have admin rights*, you can implement rewritemaps. With rewrite maps WordPress Sanitizer can echo pages directly to the browser from Apache. This makes possible much, much faster page reception in the browser than when this server cache has to be echoed with PHP/WordPress (although WordPress Sanitizer makes this possible too, as a fallback mechanism).

To implement the rewrite map for WordPressSanitizer follow these steps (if you don't have admin rights on your server, you will not be able to use the rewritemap):

1. Place /rewritemap/noslashes.pl in this repository in a folder on your server (preferably outside the rootmap of your site) and make it executable.
2. Place this code in vhost.conf (see example in /rewritemap/vhost.conf of this repository):

		#This code **has** to be used here, otherwise
		#a server error will ensue and the rewritemap
		#won't function:

		RewriteEngine On

		#Alex: rewrite-maps have to be placed here in vhost.conf
		#on my Linux-server, not in httpd-vhosts.conf;
		#locally in Xampp they are allowed in httpd-vhosts.conf,
		#but outside the virtualhost containers:

		#Define the rewrite map:
		#This is a rewrite map for removing a ending slash from an url:
		RewriteMap noslashes "prg:/usr/bin/perl /path/to/noslashes.pl"

		#Of course you will have to check if the path to perl
		#is valid for your server (also on the first line of noslashes.pl).

		#This is the rewrite map which determines whether a visitor is an admin:
		RewriteMap isadmin "txt:/path/to/wp-content/themes/[themename]/rewritemap/isadmin.map"

3. Check that you do have the *writable* folders "cache", "cache-for-admins", "css" and "javascript" in the root of your domain.

4. Restart Apache, for example with "**service httpd restart**".
5. The rewritemap is being used in .htaccess. If you can't implement rewritemaps you can delete the code block in .htaccess that  uses the rewritemap "noslashes" (zie explanations in .htaccess).

Even without rewritemaps WordPressSanitizer will still give you more loading speed, albeit a significantly lesser speed than *with* rewritemaps.
