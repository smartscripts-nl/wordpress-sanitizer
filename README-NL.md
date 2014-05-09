#WordPress-Sanitizer

*Optimaliseert de HTML van WordPress en zorgt dat WordPress-pagina's sneller laden*

##Disclaimer

De scripts op deze pagina bevinden zich nog in bèta-stadium. Gebruik ervan is geheel voor eigen risico. SmartScripts is niet verantwoordelijk voor eventuele schade ontstaan door gebruik van deze scripts.

## Credits ##

1. WordPress (natuurlijk!)
2. [htmlLawed](http://www.bioinformatics.org/phplabware/internal_utilities/htmLawed/beta/), om de HTML van WordPress te formatteren en eventueel te corrigeren (aangepaste versie door Alex Pot)

## Over dit respository ##

De meest up-to-date tak in dit repository zal altijd *develop* zijn.

##Features van WordPress-Sanitizer

 * **Verwijdert overbodige javascripts en stylesheets** uit de pagina's.
 * **Combineert alle overgebleven resources** (javascripts en stylesheets), zowel extern als embedded geladen) tot één resource voor resp. javascript en CSS. Hierdoor laden pagina's sneller, omdat ze minder requests naar de server hoeven te sturen.
 * **Kort de lange, absolute url's van WordPress in** en herschrijft ze naar aliassen, zodat een site minder herkenbaar wordt als een WordPress-site (verdediging tegen hackers).
 * Met Wordpress Sanitizer valt **veel snelheidswinst** bij het laden van pagina's te behalen. Alle HTML, CSS en javascript wordt namelijk in (met gzip gecomprimeerde) servercachebestanden opgeslagen. Bij volgende verzoeken worden deze cachebestanden:

	1. ofwel rechtsstreeks naar nieuwe bezoekers gestuurd (soms zelfs onder tussenkomst van PHP, indien je rewritemaps kunt instellen op je server),
	2. ofwel dankzij de cachecontrol van WordPress Sanitizer helemaal niet meer verstuurd en direct uit de browsercache geladen (not modified headers).

* Plaatst een button in de HTML waarmee je **in één keer alle servercache kunt wissen**. Deze button zal enkel zichtbaar zijn voor admins, waarvan het ip nummer is gedefinieerd in een rewrite map.
 * **Formatteert de HTML** van de pagina's en **repareert** voor zover mogelijk eventuele fouten daarin.
 * Kan de **actieve pagina markeren** in het sitemenu.


## Speciale Url ##

Admins kunnen via https://www.[domein]/setip hun ipnummer aanpassen zoals dat is opgeslagen in de rewritemap /wp-content/themes/[themanaam]/rewritemap/isadmin.map. Dankzij dit ipnummer zal op de openbare site enkel voor admins een button/icoon zichtbaar zijn waarmee zij de servercache van hun site kunnen wissen.

##Howto's

De optimalisaties door WordPress Sanitizer worden geactiveerd op de volgende manier:

1. WordPress Sanitizer wordt geladen bovenaan in functions.php:

		define("WPTHEMEFOLDER", dirname(__FILE__));
		require_once(WPTHEMEFOLDER . "/wordpress-sanitizer.php");


2. Bovenaan in index.php (of een vergelijkbare pagina, bijvoorbeeld page.php) in de themamap wordt servercaching aldus gestart:

        wordpressSanitizer::init(new wordpressSanitizerConfiguration());
        wordpressSanitizer::servercache_start();


3. In datzelfde bestand in de themamap wordt de HTML afgevangen en geoptimaliseerd met:

		wordpressSanitizer::html_correct_and_echo();

4. De instellingen voor WordPressSanitizer dien je te doen in wordpress-sanitizer-config.php, in deze methode:

		wordpressSanitizerConfiguration::get_options();

5. **Actiefmarkering** pagina's in menu of van link naar homepagina (deze code kan bijvoorbeeld in header.php in de themamap staan):


	 * 1) actiefmarkering in **menu** (met - door WordPressSanitizer - naar paginanamen vertaalde id's als link, maar kan ook de id van de page/post in de database zijn):


    		<?php ob_start(); ?>

    		<li><a href="bestellen">bestellen</a></li>

    		of

    		<li><a href="326">bestellen</a></li>

    		<?php wordpressSanitizer::active_page_mark(ob_get_clean());
			?>

	* 2) actiefmarkering (lees: verwijdering) van de **link naar de homepagina** op die homepagina zelf:

			<?php
			require_once("wordpress-sanitizer.php");
			ob_start();
			?>

			<a href="45"><img src="/wp-content/themes/themefolder/images/banner-theme.gif" alt=""></a>

			<?php
			//argument true betekent: dit "menu" heeft geen list-items:
			wordpressSanitizer::active_page_mark(ob_get_clean(), true);
			?>
6. **Adminds definiëren**: in het bestand wp-content/themes/[themename]/rewritemap/isadmin.map kun je de ip adressen van admins en hun namen opgeven. De hierin gedefinieerde ip-adressen worden gebruikt om te bepalen of iemand een admin is. Alleen voor admins zal een **button ten behoeve van het wissen van de servercache** aan de HTML worden toegevoegd. De servercache voor admins en non-admins wordt op twee verschillende locaties opgeslagen, resp. /cache-for-admins en /cache. Deze mappen zijn beschermd tegen toegang vanuit de browser met .htaccess-bestanden in deze mappen. Dit is ook zo geregeld voor de map wp-content/themes/[themename]/rewritemap/ . Een voorbeeld van de inhoud van isadmin.map (met op elke regel één ip-adres gevolgd door de naam van de bijbehorende admin):

		127.0.0.1 lokaal
		77.44.172.12 Heleen
		54.11.234.6 Walter

##Vereisten

1. In de rootmap van de site dienen de SCHRIJFBARE mappen "cache", "cache-for-admins", "css", "images" en "scripts" aanwezig te zijn
2. Kopieer ook de map /images naar je server.
3. De rewritemap /wp-content/themes/[themename]/rewritemap/isadmin.map **moet** schrijfbaar zijn! Anders zullen admins hun ipnummer zoals dat is opgeslagen in dit bestand niet kunnen aanpassen.
3. Je server dient mod_rewrite te ondersteunen

##Optionele uitbreiding met rewritemaps

*Als je server rewritemaps ondersteunt en je admin-rechten hebt* op die server, kun je gebruik maken van rewritemaps. Daarmee kan de door WordPressSanitizer opgeslagen servercache rechtstreeks vanuit Apache geëchood worden, wat vele malen sneller gaat dan echoën van die cache met behulp van PHP.

Je dient de voor WordPressSanitizer geconfigureerde rewritemap als volgt in te stellen (heb je geen admin-rechten, dan kun je ook geen rewritemap instellen):

1. Plaats /rewritemap/noslashes.pl in dit repository ergens op je server (bij voorkeur buiten de rootmap van je site) en maak het uitvoerbaar.
2. Plaats in vhost.conf deze code (zie voorbeeld in /rewritemap/vhost.conf van dit repository):

		#Deze móet hier staan, anders treedt er een serverfout op
		#en werkt de rewritemap niet:

		RewriteEngine On

		#Alex: rewrite-maps móeten voor mijn Linux-server hier in vhost.conf staan,
		#dus buiten httpd-vhosts.conf; lokaal in Xampp mogen ze wel in httpd-vhosts.conf
		#staan, maar dan wel buiten virtualhost-containers:

		#Hier wordt de rewritemap gedefinieerd:

		RewriteMap noslashes "prg:/usr/bin/perl /pad/naar/noslashes.pl"

		#Uiteraard zul je nog even moeten controleren of het hier opgegeven
		#pad naar perl (ook in de eerste regel van noslashes.pl) klopt voor jouw server.

		#Dit is de rewritemap waarmee bepaald wordt of een bezoeker een admin is of niet:
		RewriteMap isadmin "txt:/path/to/wp-content/themes/[themename]/rewritemap/isadmin.map"

3. Controleer dat je de *schrijfbare* mappen "cache", "cache-for-admins", "css" en "javascript" in de root van je domein hebt geplaatst

4. Herstart Apache, bijvoorbeeld met "**service httpd restart**" of met "**sudo /etc/init.d/apache2 restart**" onder Ubuntu (ik heb niet gecontroleerd of rewritemaps te gebruiken zijn in de Ubuntu-image die InHolland-studenten ter beschikking is gesteld).
5. De rewritemap wordt aangeroepen in .htaccess. Kun je geen rewritemaps instellen, dan kun je het blok met de rewriterule die de rewritemap "noslashes" aanroept met een gerust hart verwijderen (zie toelichting in .htaccess).

Ook zonder de rewritemap zal WordPressSanitizer nog steeds snelheidswinst opleveren, alleen duidelijk minder dan mét die rewritemap.
