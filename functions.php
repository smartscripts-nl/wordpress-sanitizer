<?php

define("WPTHEMEFOLDER", dirname(__FILE__));
require_once(WPTHEMEFOLDER . "/wordpress-sanitizer.php");

// Schakelt widgets voor alle pagina's behalve de homepage uit

/*
 add_filter( 'sidebars_widgets', 'disable_all_widgets' );

function disable_all_widgets( $sidebars_widgets ) {

 if ( is_page ( array ( 'Nieuws' , 'Scholen' ,'Teken mee!' , 'Expositie' , 'Doneren' , 'Contact' , 'Purchase History' , 'Transaction Failed' , 'Paypal > Bedankt' ) ) )
	  $sidebars_widgets = array( false );

 elseif ( is_category() )
	  $sidebars_widgets = array( false );

 elseif ( is_single() )
	  $sidebars_widgets = array( false );

 return $sidebars_widgets;
}
*/

//Registreer menus
/*
function register_my_menus() {
  register_nav_menus(
    array(
      'hoofdmenu' => __( 'Hoofdmenu' )
    )
  );
}
add_action( 'init', 'register_my_menus' );
*/





// ESSENTIEEL: Voeg metaboxes toe ten behoeve van de eerste en laatste kolom:
include_once 'metaboxes/setup.php';
include_once 'metaboxes/simple-spec.php';



/** Voeg voor het admin-paneel extra javascript toe, zodat de selector-afbeeldingen ten behoeve van de eerste en laatste kolom klikbaar gemaakt kunnen worden.
 *
 * @param $hook
 */
function my_enqueue($hook) {
	if ('post.php' != $hook) {
		return;
	}
	wp_enqueue_script('my_custom_script', get_theme_root_uri() . '/coloursole/extra-editors.js', Array(), "1.0", true);
}
add_action('admin_enqueue_scripts', 'my_enqueue');

// Verwijder de volgende actions (eigenlijk allemaal behalve de description die de SEO Title Tag plugin plaatst) uit wp_head:

remove_action( 'wp_head', 'feed_links_extra', 3 ); // Display the links to the extra feeds such as category feeds
remove_action( 'wp_head', 'feed_links', 2 ); // Display the links to the general feeds: Post and Comment Feed
remove_action( 'wp_head', 'rsd_link' ); // Display the link to the Really Simple Discovery service endpoint, EditURI link
remove_action( 'wp_head', 'wlwmanifest_link' ); // Display the link to the Windows Live Writer manifest file.
remove_action( 'wp_head', 'index_rel_link' ); // index link
remove_action( 'wp_head', 'parent_post_rel_link', 10, 0 ); // prev link
remove_action( 'wp_head', 'start_post_rel_link', 10, 0 ); // start link
remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0 ); // Display relational links for the posts adjacent to the current post.
remove_action( 'wp_head', 'wp_generator' ); // Display the XHTML generator that is generated on the wp_head hook, WP version
remove_action('wp_head', 'rel_canonical' );
remove_action( 'wp_head', 'wp_shortlink_wp_head', 10, 0 );


