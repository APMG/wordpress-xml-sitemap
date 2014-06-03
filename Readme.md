# Google News and Images XML Sitemap

* Contributors: pwenzel
* Tags: xml, sitemap, multisite, network, custom post types
* Requires at least: 3.7
* Tested up to: 3.9.1
* License: GPLv2 or later

Provides an XML Sitemap with Google News and Image Sitemap attributes. Supports custom post types and multisite installations. 

## Description 

This plugin automatically generates an XML sitemap and advertises itself to search engines in robots.txt and your page header.

It is designed to be run with no human intervention. Therefore, this plugin contains no options, buttons, switches, or levers.

Requires at least PHP 5.3.0.

### Features 

* Generates an XML sitemap at the root of your site (e.g. http://example.com/sitemap.xml)
* Includes custom post types
* Advertises itself in <code>robots.txt</code> (e.g. Sitemap: http://example.com/sitemap.xml)
* Advertises itself in HTML HEAD with <code>link rel-sitemap</code> tag (See http://microformats.org/wiki/rel-sitemap)
* Follows the <a href="http://www.google.com/schemas/sitemap-news/0.9/">Google News</a> and <a href="http://www.google.com/schemas/sitemap-image/1.1/">Image Sitemap</a> schemas
* <a href="http://en.support.wordpress.com/featured-images/">Featured Images</a> are included automatically 
* Includes last modified dates
* Includes category and tag archives
* <code>news:language</code> follows your blog's language setting
* The sitemap will display or advertise itself if <a href="http://en.support.wordpress.com/search-engines/">Discourage search engines</a> is checked

## Installation 

1. Install the plugin your plugins directory.
2. Enable it from the Plugins menu. Multisite installations can use the Network Activate option. 
3. Go to Settings... Reading and and uncheck "Discourage search engines from indexing this site" from the Search Engine Visibility section. 

### Testing 

1. Test it by going to <code>/sitemap.xml</code> at your site's root
2. Validate your sitemap at <a href="http://www.validome.org/google/">validome.org/google/</a>
3. Submit your sitemap to <a href="https://www.google.com/webmasters/tools">Google Webmaster Tools</a>

## Frequently Asked Questions 

#### What custom post types are included?

In addition to posts and pages, all custom post types you register will be included in the sitemap. 

Custom post types will be excluded if their <code>public</code> option is false, or if <code>exclude_from_search</code> is true.

Attachments are excluded from the sitemap, unless they are added to a post as a <a href="http://en.support.wordpress.com/featured-images/">Featured Image</a>.

#### What sitemaps are included robots.txt if using Wordpress Multisite? 

All sites marked "public" will have their sitemaps included in robots.txt.

Sites marked "archive", "spam", or "deleted" are excluded.

#### What image sizes are included in the image sitemap? 

It defaults to the <code>large</code> size, as available.

#### Do you use the post creation date, or the last modified date?  
The <code>lastmod</code> attribute in your sitemap uses a post's last modified date, not the post creation date. 

This ensures that search engines are aware of content changes. 

#### I don't see a link rel-sitemap tag in my header. Why not? 

Make sure your theme's template includes <code><?php wp_head(); ?></code> in the header. 