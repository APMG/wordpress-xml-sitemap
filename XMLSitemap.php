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

		if(get_option('timezone_string')) {
			date_default_timezone_set(get_option('timezone_string'));			
		}

		add_action( 'init', array( &$this, 'init_xml_sitemap' ) );

		$this->sitemap_url = get_bloginfo('url').'/sitemap.xml';
		$this->cache_key = $this::slug . ':' . $this->sitemap_url;
		$this->sitemap_index_url = get_bloginfo('url').'/sitemapindex.xml';
		$this->sitemap_index_cache_key = $this::slug . ':' . $this->sitemap_index_url;
		$this->posts_per_page = 100;

	}
  
	/**
	 * Runs when the plugin is initialized
	 */
	function init_xml_sitemap() {

		if(get_option('blog_public')) {
			add_action( 'template_redirect', array( &$this, 'render_sitemap' ) );
			add_action( 'template_redirect', array( &$this, 'render_sitemap_index' ) );
			add_action( 'send_headers', array( &$this, 'add_http_headers' ) );
			add_action( 'wp_head', array( &$this, 'append_sitemap_link_tag' ) );
			add_filter( 'plugin_action_links_'.plugin_basename(__FILE__), array( &$this, 'plugin_settings_link' ) );
			// add_filter( 'query_vars', array( &$this, 'add_query_vars' ));
		}

		add_filter( 'robots_txt', array( &$this, 'robots_modify' ) );
		add_action( 'save_post', array( &$this, 'clear_sitemap_cache' ) );

	}

	/**
	 * Add settings link on plugin page
	 */
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
		if ( ! preg_match( '/sitemap\.xml/', $_SERVER['REQUEST_URI'] ) ) {
			return;
		}

		$xml = wp_cache_get( $this->cache_key );
		if ( false === $xml ) {
			$xml = $this->get_sitemap_xml();
			wp_cache_set( $this->cache_key, $xml );
		} 

		status_header(200);
		print $xml;
		exit();

	}

	/**
	 * Render Sitemap Index
	 * @link http://www.sitemaps.org/protocol.html#index
	 * @link https://support.google.com/webmasters/answer/75712?hl=en
	 */
	function render_sitemap_index() {
		if ( ! preg_match( '/sitemapindex\.xml/', $_SERVER['REQUEST_URI'] ) ) {
			return;
		}

		$xml = wp_cache_get( $this->sitemap_index_cache_key );
		if ( false === $xml ) {
			$xml = $this->get_sitemap_index_xml();
			wp_cache_set( $this->sitemap_index_cache_key, $xml );
		} 

		status_header(200);
		print $xml;
		exit();

	}

	/**
	 * Generate XML Sitemap with SimpleXMLElement
	 * @return string
	 * @todo Make number of posts configurable via querystring
	 * @link http://support.google.com/webmasters/answer/183668 ()
	 */
	function get_sitemap_xml() {

		$xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8" ?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd http://www.google.com/schemas/sitemap-news/0.9 http://www.google.com/schemas/sitemap-news/0.9/sitemap-news.xsd"></urlset>');

		// Setup Pagination	
		$page = get_query_var( 'page', 0 );

		// Add parameter to show all entries
		if( (get_query_var( 'show', 0 )) == 'all') { 
			$show_all = true;
		} else {
			$show_all = false;
		}

		// Get available public custom post types
		$custom_post_types = get_post_types(array(
		   'public'   => true,
		   'exclude_from_search' => false,
		   '_builtin' => false
		)); 

		$date_query = array(
			array(
				'after' => '1 week ago' // filter posts from last week
			)
		);

		// Generate base arguments for WP_Query
		$args = array(
			'post_type' => array_merge( array( 'post', 'page', ), $custom_post_types),
			'orderby' => 'modified',
			'order' => 'DESC'
		);

		// Use Pagination if "?page=1" is passed
		if($show_all) {
			$args['posts_per_page'] = -1;
		}
		elseif($page) {
			$args['posts_per_page'] = $this->posts_per_page;
			$args['paged'] = $page;
		} else {
			$args['nopaging'] = true;
			$args['date_query'] = $date_query; // Don't use date-based query if pagination is enabled
		}

		$query = new \WP_Query ( $args );		

		// Add attributes for debugging purposes
		$xml->addAttribute('generated', date(\DateTime::RSS));
		if($page) {
			$xml->addAttribute('page', $page);
			$xml->addAttribute('maxpages', $query->max_num_pages);
		} 

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

					// UTF8 Debug Test
					// $utf8_sample = 'I am text with Ünicödé & HTML €ntities ©';
					// $image->addChild('image:title', htmlspecialchars($utf8_sample), $this::ns_sitemap_image);			
					// $image->addChild('image:caption', htmlspecialchars($utf8_sample), $this::ns_sitemap_image);

				}
			}

			$news = $item->addChild('news:news', NULL, $this::ns_sitemap_news);
			$news->addChild('news:publication_date', get_the_date(DATE_W3C) );
			$news->addChild('news:title', get_the_title_rss());

			// TODO: Not sure if news:genres should be included or not
			// $news->addChild('news:genres', 'PressRelease, Blog'); // https://support.google.com/news/publisher/answer/93992

			$publication = $news->addChild('news:publication');
			$publication->addChild('news:name', get_bloginfo_rss('name'));
			$publication->addChild('news:language', get_bloginfo_rss('language'));

		endwhile;

		wp_reset_query();


		// Advertise taxonomy terms on regular non-paginated sitemap
		if($args['nopaging'] = true) {

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

		}

		if(WP_DEBUG) {
			$xml->addAttribute('debug', get_num_queries() . ' queries in ' . timer_stop( 0 ) . ' seconds' );
		}

		return $xml->asXML();

	}


	/**
	 * Generate XML Sitemap Index with SimpleXMLElement
	 * @return string
	 */
	function get_sitemap_index_xml() {

		// Get the most recently modified post/page/custom date
		$last_modified_date = null;

		// Get available public custom post types
		$custom_post_types = get_post_types(array(
		   'public'   => true,
		   'exclude_from_search' => false,
		   '_builtin' => false
		)); 

		// Generate arguments for WP_Query
		$args = array(
			'post_type' => array_merge( array( 'post', 'page', ), $custom_post_types),
			'orderby' => 'modified',
			'order' => 'DESC',
			'posts_per_page' => 1
		);

		$query = new \WP_Query ( $args );

		// Set the last modified date to that of the latest post
		while ( $query->have_posts() ) : $query->the_post();
			$last_modified_date = get_the_modified_date(DATE_W3C);
		endwhile;


		// Initialize the XML for SitemapIndex
		$xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8" ?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></sitemapindex>');

		// Add attributes for debugging purposes
		$xml->addAttribute('generated', date(\DateTime::RSS));

		$item = $xml->addChild('sitemap');
		$item->addChild('loc', $this->sitemap_url);
		$item->addChild('lastmod', $last_modified_date ); 

		$total_entries = wp_count_posts()->publish + wp_count_posts('page')->publish; // TODO: Count all posts, pages and custom post types

		$number_of_sitemaps_in_index = ceil( $total_entries / $this->posts_per_page );

		for ($i=1; $i <= $number_of_sitemaps_in_index; $i++) { 
			$item = $xml->addChild('sitemap');
			$item->addChild('loc', $this->sitemap_url . "?page={$i}");
			$item->addChild('lastmod', $last_modified_date ); 
		}

		if(WP_DEBUG) {
			$xml->addAttribute('debug', get_num_queries() . ' queries in ' . timer_stop( 0 ) . ' seconds' );
		}

		return $xml->asXML();
	}

	/**
	 * Register Query Argument
	 */
	function add_query_vars($public_query_vars) {
	    $public_query_vars[] = 'show';
	    return $public_query_vars;
	}

	/**
	 * Append necessary HTTP headers for serving XML 
	 * @link http://codex.wordpress.org/Plugin_API/Action_Reference/send_headers
	 * @link http://stackoverflow.com/questions/4832357/whats-the-difference-between-text-xml-vs-application-xml-for-webservice-respons
	 */
	function add_http_headers() {
		if ( ! preg_match( '/(sitemap|sitemapindex)\.xml/', $_SERVER['REQUEST_URI'] ) ) {
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
			wp_cache_delete( $this->sitemap_index_cache_key );

		}
	}

	/**
	 * Advertise in robots.txt 
	 * @link https://trepmal.com/2011/04/03/change-the-virtual-robots-txt-file/
	 * @link http://wordpress.stackexchange.com/questions/38859/create-unique-robots-txt-for-every-site-on-multisite-installation
	 * @todo return sitemap index files instead of sitemaps
	 * @return string
	 */
	function robots_modify( $output ) {

		// Multisite 
		if ( is_multisite() ) { 

			$args = array(
				'public'     => true,
				'archived'   => false,
				'spam'       => false,
				'deleted'    => false,
			); 

			/**
			 * @todo Cache wp_get_sites call for 60 minutes (useful for large networks)
			 * @todo Clear cache on and 'wpmu_create_blog' and 'update_option_blog_public' and related hooks
			 * @link https://core.trac.wordpress.org/browser/tags/3.9.1/src/wp-includes/ms-functions.php#L2061
			 */

			$sites = wp_get_sites( $args );

			foreach($sites as $site) {
				$url = 'http://' . $site['domain'] . $site['path'] . 'sitemap.xml';
				$output .= 'Sitemap: ' . $url . PHP_EOL;
			}

		// Not Multisite
		} else {
			if(get_option('blog_public')) {
				$url = get_bloginfo('url') . '/sitemapindex.xml';
				$output .= 'Sitemap: ' . $url . PHP_EOL;
			}
		}

		return $output;

	}
  
} 

new \APMG\XMLSitemap();

?>