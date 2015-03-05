<?php
/*
Plugin Name: News and Image XML Sitemap
Plugin URI: https://github.com/APMG/wordpress-xml-sitemap
Description: <a href="http://www.sitemaps.org/">XML Sitemap</a> with <a href="http://www.google.com/schemas/sitemap-news/0.9/">Google News</a> and <a href="http://www.google.com/schemas/sitemap-image/1.1/">Image Sitemap</a> attributes.
Uses PHP SimpleXML to ensure proper escaping.
Version: 0.5.4
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
	const UTC_DATE_FORMAT = 'D, d M Y H:i:s T';

	function __construct() {

		if(get_option('timezone_string')) {
			date_default_timezone_set(get_option('timezone_string'));			
		}

		add_action( 'init', array( &$this, 'init_xml_sitemap' ) );

		// number of items included in paginated sitemap
		// could probably increase this number, but keeping queries lightweight
		$this->posts_per_page = 100; 

		// last modified date appended as HTTP Header
		$this->last_modified_header = null;
	}
  
	/**
	 * Runs when the plugin is initialized
	 */
	function init_xml_sitemap() {

		if(get_option('blog_public')) {
			add_action( 'template_redirect', array( &$this, 'render_sitemap' ) );
			add_action( 'wp_head', array( &$this, 'append_sitemap_link_tag' ) );
			add_filter( 'plugin_action_links_'.plugin_basename(__FILE__), array( &$this, 'plugin_settings_link' ) );
		}

		add_filter( 'robots_txt', array( &$this, 'robots_modify' ) );

	}

	/**
	 * Add settings links on plugin page
	 */
	function plugin_settings_link($links) { 

		$sitemaps = array(
			'Full Sitemap' => '/sitemap-all.xml',
			'Sitemap Index' => '/sitemapindex.xml',
			'Sitemap' => '/sitemap.xml',
		);

		foreach ($sitemaps as $label => $sitemap) {
			$url = get_bloginfo('url') . $sitemap;
			$settings_link = "<a href='${sitemap}'>${label}</a>"; 
			array_unshift($links, $settings_link);
		}

		return $links; 
		
	}

	/**
	 * Check which type of sitemap to generate
	 * Be it sitemap.xml, sitemap.xml?page=N, sitemap-all.xml, or sitemapindex.xml
	 */
	function render_sitemap() {

		// match /sitemap.xml with pagination
		if ( preg_match( '/sitemap\.xml/', $_SERVER['REQUEST_URI'] ) ) {
			$xml = $this->get_sitemap_xml();
		}

		// match without querystring parameters
		elseif ( preg_match( '/sitemap-all\.xml$/', $_SERVER['REQUEST_URI'] ) ) {
			$xml = $this->get_sitemap_xml( $show_all = true );
		}

		// match without querystring parameters
		elseif ( preg_match( '/sitemapindex\.xml$/', $_SERVER['REQUEST_URI'] ) ) {
			$xml = $this->get_sitemap_index_xml();
		}

		// this request has nothing to sitemaps
		else {
			return;
		}

		// print XML and exit
		$this->add_http_headers();
		print $xml;
		exit();

	}


	/**
	 * Generate XML Sitemap with SimpleXMLElement
	 * @return string
	 * @todo Make number of posts configurable via querystring
	 * @link http://support.google.com/webmasters/answer/183668 ()
	 */
	function get_sitemap_xml($show_all = false) {

		$xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8" ?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd http://www.google.com/schemas/sitemap-news/0.9 http://www.google.com/schemas/sitemap-news/0.9/sitemap-news.xsd"></urlset>');

		// Setup Pagination	
		$page = get_query_var( 'page', 0 );

		// Get available public custom post types
		$custom_post_types = get_post_types(array(
		   'public'   => true,
		   'exclude_from_search' => false,
		   '_builtin' => false
		)); 

		// Limit/filter posts from last week
		$date_query = array(
			array(
				'after' => '1 week ago' 
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

		// Run WP_Query
		$query = new \WP_Query ( $args );		

		// Add attributes for debugging purposes
		$xml->addAttribute('generated', date(\DateTime::RSS));
		if($page) {
			$xml->addAttribute('page', $page);
			$xml->addAttribute('maxpages', $query->max_num_pages);
		} 

		// Just exit with 404 response if no posts returned
		if(!$query->have_posts()) {
			$this->return_404();
		}

		// Add site url to top of sitemap
		if($page <= 1) {
			$home = $xml->addChild('url');
			$home->addChild('loc', get_site_url());
			$home->addChild('changefreq', 'always');
			$home->addChild('priority', '1.0');
		}

		// Iterate over all posts
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
		// Attach query details to attributes if debugging is enabled
		if(WP_DEBUG) {
			$xml->addAttribute('debug', get_num_queries() . ' queries in ' . timer_stop( 0 ) . ' seconds' );
		}

		return $xml->asXML();

	}


	/**
	 * Generate XML Sitemap Index with SimpleXMLElement
	 * @return string
	 * @todo send Last Modified Header
	 */
	function get_sitemap_index_xml() {

		$sitemap_url = get_bloginfo('url') . '/sitemap.xml';

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

		// Run WP_Query
		$query = new \WP_Query ( $args );

		// Set the last modified date to that of the latest post
		while ( $query->have_posts() ) : $query->the_post();
			$last_modified_date = get_the_modified_date(DATE_W3C);
			$this->last_modified_header = get_the_modified_date($this::UTC_DATE_FORMAT); 
		endwhile;

		// Initialize the XML for SitemapIndex
		$xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8" ?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></sitemapindex>');

		// Add attributes for debugging purposes
		$xml->addAttribute('generated', date(\DateTime::RSS));

		$item = $xml->addChild('sitemap');
		$item->addChild('loc', $sitemap_url);
		$item->addChild('lastmod', $last_modified_date ); 

		$total_entries = wp_count_posts()->publish + wp_count_posts('page')->publish; // TODO: Count all posts, pages and custom post types

		$number_of_sitemaps_in_index = ceil( $total_entries / $this->posts_per_page );

		for ($i=1; $i <= $number_of_sitemaps_in_index; $i++) { 
			$item = $xml->addChild('sitemap');
			$item->addChild('loc', $sitemap_url . "?page={$i}");
			$item->addChild('lastmod', $last_modified_date ); 
		}

		// Attach query details to attributes if debugging is enabled
		if(WP_DEBUG) {
			$xml->addAttribute('debug', get_num_queries() . ' queries in ' . timer_stop( 0 ) . ' seconds' );
		}

		return $xml->asXML();
	}

	/**
	 * Append necessary HTTP headers for serving XML 
	 * @link http://codex.wordpress.org/Plugin_API/Action_Reference/send_headers
	 * @link http://stackoverflow.com/questions/4832357/whats-the-difference-between-text-xml-vs-application-xml-for-webservice-respons
	 * @todo Add last-modified headers
	 */
	function add_http_headers() {

		// print HTTP OK status; template_redirect might not do that for you
		status_header(200);

		// Send XML headers
		header('Content-Type: application/xml; charset=utf-8');

		// Send Last Modified Headers
		if($this->last_modified_header) {
			header('Last-Modified: ' . $this->last_modified_header );
		}
		
	}

	/**
	 * Add link tag to head
	 * @link http://microformats.org/wiki/rel-sitemap
	 * @example <link rel="sitemap" href="path/to/sitemap.xml" />
	 * @return string
	 */
	function append_sitemap_link_tag() {
		if (!is_admin()) {
			$sitemap = get_bloginfo('url') . '/sitemap.xml';
			echo '<link rel="sitemap" href="' . $sitemap . '" />' . PHP_EOL;
		}
	}

	/**
	 * Force 404 Response
	 */
	function return_404() {
		status_header(404);
		nocache_headers();
		include( get_404_template() );
		exit;
	}

	/**
	 * Advertise Sitemaps in Robots.txt 
	 * @link https://trepmal.com/2011/04/03/change-the-virtual-robots-txt-file/
	 * @link http://wordpress.stackexchange.com/questions/38859/create-unique-robots-txt-for-every-site-on-multisite-installation
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

			$sites = wp_get_sites( $args );

			foreach($sites as $site) {
				$url = 'http://' . $site['domain'] . $site['path'] . 'sitemap.xml';
				$output .= 'Sitemap: ' . $url . PHP_EOL;
				$url = 'http://' . $site['domain'] . $site['path'] . 'sitemapindex.xml';
				$output .= 'Sitemap: ' . $url . PHP_EOL;
			}

		// Not Multisite
		} else {
			if(get_option('blog_public')) {
				$url = get_bloginfo('url') . '/sitemap.xml';
				$output .= 'Sitemap: ' . $url . PHP_EOL;
				$url = get_bloginfo('url') . '/sitemapindex.xml';
				$output .= 'Sitemap: ' . $url . PHP_EOL;
			}
		}

		return $output;

	}
  
} 

new \APMG\XMLSitemap();

?>