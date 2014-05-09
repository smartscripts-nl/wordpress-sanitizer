<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<title><?php wp_title( '|', true, 'right' ); ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="shortcut icon" href="/favicon.ico" type="image/vnd.microsoft.icon">
<link rel="stylesheet" href="/wp-content/themes/coloursole/style.css">
<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>">
<!--[if lt IE 9]>
<script src="/wp-content/themes/js/html5.js"></script>
<![endif]-->
<!--[if lt IE 9 & !IEMobile]>
<link rel="stylesheet" href="/wp-content/themes/coloursole/ie8.css">
<![endif]-->
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<div id="wrapper">
<div id="container-main">
<header role="banner">

	<?php
	//start het caching proces:
	require_once("wordpress-sanitizer.php");
	ob_start();
	?>

	<a href="45"><img src="/wp-content/themes/coloursole/images/banner-coloursoles.gif" alt=""></a>

	<?php
	//argument true betekent: dit "menu" heeft geen list-items:
	wordpressSanitizer::active_page_mark(ob_get_clean(), true);
	?>

	<img src="/wp-content/themes/coloursole/images/logo-coloursoles.png" width="241" height="200" alt="" class="logo">
  <nav role="navigation" class="nav"> <a href="#menu" id="hamlink">menu...</a>
    <ul id="menu">

	    <?php ob_start(); ?>

	    <li><a href="/bestellen" class="hemelsblauw">bestellen</a></li>
	    <li><a href="/etalage" class="verkeersoranje">etalage</a></li>
	    <li><a href="/overons" class="signaalgroen">over ons</a></li>
	    <li><a href="/contact" class="heidepaars">contact</a></li>

	   <?php wordpressSanitizer::active_page_mark(ob_get_clean()); ?>

    </ul>
  </nav>
</header>

