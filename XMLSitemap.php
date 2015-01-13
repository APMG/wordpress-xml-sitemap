<?php
/*
Plugin Name: News and Image XML Sitemap
Plugin URI: http://
Description: <a href="http://www.sitemaps.org/">XML Sitemap</a> with <a href="http://www.google.com/schemas/sitemap-news/0.9/">Google News</a> and <a href="http://www.google.com/schemas/sitemap-image/1.1/">Image Sitemap</a> attributes.
Uses PHP SimpleXML to ensure proper escaping.
Version: 0.4.2
Author: Paul Wenzel
Author Email: pwenzel@mpr.org
License:

  Copyright 2015 Paul Wenzel (pwenzel@mpr.org)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as 
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
  
*/

namespace APMG;

class XMLSitemap {

	const name = 'XML Sitemap';
	const slug = 'xml_sitemap';
	const ns_sitemap_image = 'http://www.google.com/schemas/sitemap-image/1.1';	
	const ns_sitemap_news = 'http://www.google.com/schemas/sitemap-news/0.9';

	function __construct() {

		add_action( 'init', array( &$this, 'init_xml_sitemap' ) );

		$this->sitemap_url = get_bloginfo('url').'/sitemap.xml';
		$this->cache_key = $this::slug . ':' . $this->sitemap_url;

	}
  

	/**
	 * Runs when the plugin is initialized
	 */
	function init_xml_sitemap() {

		if(get_option('blog_public')) {
			add_action( 'template_redirect', array( &$this, 'render_sitemap' ) );
			add_action( 'send_headers', array( &$this, 'add_http_headers' ) );
			add_action( 'wp_head', array( &$this, 'append_sitemap_link_tag' ) );
			add_filter( 'plugin_action_links_'.plugin_basename(__FILE__), array( &$this, 'plugin_settings_link' ) );
		}

		add_filter( 'robots_txt', array( &$this, 'robots_modify' ) );
		add_action( 'save_post', array( &$this, 'clear_sitemap_cache' ) );

	}

	// Add settings link on plugin page
	function plugin_settings_link($links) { 
	  $settings_link = '<a href="'.$this->sitemap_url.'">View Sitemap</a>'; 
	  array_unshift($links, $settings_link); 
	  return $links; 
	}



	/**
	 * Query for posts, pages, categories, and tags
	 * Render Sitemap schema with PHP SimpleXMLElement
	 * http://www.sitemaps.org/
	 */
	function render_sitemap() {
		if ( ! preg_match( '/sitemap\.xml$/', $_SERVER['REQUEST_URI'] ) ) {
			return;
		}

		$xml = wp_cache_get( $this->cache_key );
		if ( false === $xml ) {
			$xml = $this->get_sitemap_xml();
			wp_cache_set( $this->cache_key, $xml );
		} 

		// $xml = $this->get_sitemap_xml();

		status_header(200);
		print $xml;
		exit();

	}

	/**
	 * Generate XML with SimpleXMLElement
	 * @return string
	 */
	function get_sitemap_xml() {

		if(get_option('timezone_string')) {
			date_default_timezone_set(get_option('timezone_string'));			
		}

		$xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8" ?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd http://www.google.com/schemas/sitemap-news/0.9 http://www.google.com/schemas/sitemap-news/0.9/sitemap-news.xsd" generated="'.date(\DateTime::RSS).'"></urlset>');

		$custom_post_types = get_post_types(array(
		   'public'   => true,
		   'exclude_from_search' => false,
		   '_builtin' => false
		)); 

		// var_dump($custom_post_types); exit(); // debug

		$args = array(
			'post_type' => array_merge( array( 'post', 'page', ), $custom_post_types),
			'orderby' => 'date',
			'showposts' => 50000, // Sitemaps can contain no more than 50,000 URLs (http://support.google.com/webmasters/answer/183668)
		);

		$query = new \WP_Query ( $args );		

		// Add site url to top of sitemap
        $home = $xml->addChild('url');
        $home->addChild('loc', get_site_url());
        $home->addChild('changefreq', 'always');
        $home->addChild('priority', '1.0');

		while ( $query->have_posts() ) : $query->the_post();
            
            $item = $xml->addChild('url');
            $item->addChild('loc', get_the_permalink());
            $item->addChild('lastmod', get_the_modified_date(DATE_W3C) );

			if ( has_post_thumbnail() ) {

				$featured_image = get_post(get_post_thumbnail_id());
				if(isset($featured_image->ID)) {
					$thumb = wp_get_attachment_image_src( $featured_image->ID, 'large' );
					$thumb_url = $thumb['0'];

					$image = $item->addChild('image:image', NULL, $this::ns_sitemap_image);
					$image->addChild('image:loc', $thumb_url, $this::ns_sitemap_image);

					$image->addChild('image:title', htmlspecialchars($featured_image->post_title), $this::ns_sitemap_image);			
	                $image->addChild('image:caption', htmlspecialchars($featured_image->post_excerpt), $this::ns_sitemap_image);
				}
			}

            $news = $item->addChild('news:news', NULL, $this::ns_sitemap_news);
            $news->addChild('news:publication_date', get_the_date(DATE_W3C) );
            $news->addChild('news:title', get_the_title_rss());

            // Not sure if news:genres should be included or not
            // $news->addChild('news:genres', 'PressRelease, Blog'); // https://support.google.com/news/publisher/answer/93992

            $publication = $news->addChild('news:publication');
            $publication->addChild('news:name', get_bloginfo_rss('name'));
            $publication->addChild('news:language', get_bloginfo_rss('language'));

 		endwhile;

		wp_reset_query();

		// Get all categories
		foreach (get_categories() as $category) {
			$item = $xml->addChild('url');
            $item->addChild('loc', get_category_link( $category->term_id ));
		}

		// Get all tags
		foreach ( get_tags() as $tag ) {
			$item = $xml->addChild('url');
            $item->addChild('loc', get_tag_link( $tag->term_id ));
		}

		return $xml->asXML();

	}


	/**
	 * Append necessary HTTP headers for serving XML 
	 * @link http://codex.wordpress.org/Plugin_API/Action_Reference/send_headers
	 * @link http://stackoverflow.com/questions/4832357/whats-the-difference-between-text-xml-vs-application-xml-for-webservice-respons
	 */
	function add_http_headers() {
		if ( ! preg_match( '/sitemap\.xml$/', $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		header('Content-Type: application/xml; charset=utf-8' );
	}

	/**
	 * Add link tag to head
	 * @link http://microformats.org/wiki/rel-sitemap
	 * @example <link rel="sitemap" href="path/to/sitemap.xml" />
	 * @return string
	 */
	function append_sitemap_link_tag() {
		if (!is_admin()) {
			echo '<link rel="sitemap" href="'.$this->sitemap_url.'" />' . PHP_EOL;
		}
	}

	/**
	 * Clear XML Sitemap cache for this site
	 */
	function clear_sitemap_cache( $post_id ){
		if ( ! wp_is_post_revision( $post_id ) ){
		
			// wp_cache_delete doesn't seem to work perfectly with W3TC
			// It only seems to work on post update, not saving of a new post
			// However, wp_cache_delete works well on WPEngine 
			wp_cache_delete( $this->cache_key );
		
		}
	}

	/**
	 * Advetise in robots.txt 
	 * @link https://trepmal.com/2011/04/03/change-the-virtual-robots-txt-file/
	 * @link http://wordpress.stackexchange.com/questions/38859/create-unique-robots-txt-for-every-site-on-multisite-installation
	 * @return string
	 */
	function robots_modify( $output ) {

		// multisite 
		if ( is_multisite() ) { 

			$args = array(
			    'public'     => true,
			    'archived'   => false,
			    'spam'       => false,
			    'deleted'    => false,
			); 

			/**
			 * TODO: Cache wp_get_sites call for 60 minutes (useful for large networks)
			 * TODO: Clear cache on and 'wpmu_create_blog' and 'update_option_blog_public' and related hooks
			 * @link https://core.trac.wordpress.org/browser/tags/3.9.1/src/wp-includes/ms-functions.php#L2061
			 */

			$sites = wp_get_sites( $args );

			foreach($sites as $site) {
				$url = 'http://' . $site['domain'] . $site['path'] . 'sitemap.xml';
				$output .= 'Sitemap: ' . $url . PHP_EOL;
			}

		// not multisite
		} else {
			if(get_option('blog_public')) {
				$url = get_bloginfo('url') . '/sitemap.xml';
				$output .= 'Sitemap: ' . $url . PHP_EOL;
			}
		}

		return $output;

	}
  
} 

new \APMG\XMLSitemap();

?>