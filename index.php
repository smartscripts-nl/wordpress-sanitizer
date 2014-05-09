<?php

require_once(dirname(__FILE__) . "/wordpress-sanitizer.php");
wordpressSanitizer::init(new wordpressSanitizerConfiguration());
wordpressSanitizer::servercache_start();

// één extra ob_start nodig (in servercache_start() ook al ob_start() aangeroepen), om later problemen met de outputbuffer te voorkomen. Oorzaak wellicht de metaboxes die de outputbuffer afvangen?
ob_start();

get_header(); ?>
<section role="main">
<?php if (have_posts()) : ?>
<?php while (have_posts()) : the_post(); ?>
<h1><?php the_title(); ?></h1>
<?php the_content(); ?>
<?php endwhile; ?>
<?php endif; ?>
</section>
</div>
<?php
//de ouderwetse procedurele opzet van WP verplicht ons helaas deze variabelen (verwijzend naar metaboxes) als globaal te definiëren (anders kunnen de metaboxes niet worden ingevoegd):
global $custom_metabox_first, $custom_metabox_last;

//laad de HTML-bewerkingen ten behoeve van coloursole:
$filler = metaboxFillerColoursole::get_instance();
metaboxGenerator::metabox_filler_set($filler);

//deze is essentieel om met editable_regions::container_end() de HTML af te kunnen afvangen en nabewerken:
ob_start();

metaboxGenerator::container_start("div#columns");

	//het tweede argument geeft aan dat de metabox in een container moet komen. Wil je geen container er rondomheen hebben, geef dan voor het tweede argument "" op, of laat het weg:
	metaboxGenerator::metabox_inject($custom_metabox_first, "div.col first");

	metaboxGenerator::metabox_inject($custom_metabox_last, "div.col last");

metaboxGenerator::container_end("div#columns");

get_footer();

//kort onder andere de absolute paden van WordPress in en maak de site minder herkenbaar als een WordPress-site:
wordpressSanitizer::html_sanitize_and_echo();

