=== Plugin Name ===
Contributors: spacemanspud
Donate link: http://bozell.com/
Tags: custom post types, mod_rewrite, archives, templates
Requires at least: 3.0
Tested up to: 3.1.2
Stable tag: 1.0.3

Custom Post Archives creates a fully featured set of archives for each post type using a robust back-end and native templating functionality.

== Description ==

Custom Post Archives bridges the gap between creating Custom Post Types in WordPress 3, and actually displaying those posts. With this plugin, you have
the option of displaying a completely seperate blog-like section for each post type, complete with all the features you expect to see with WordPress.

For each custom post type, if you click "active" and enter a slug-name, this plugin will let you display post-type specific:
	
* Archives
* Date archives
* Author archives
* Category archives
* RSS Feeds

This plug-in also provides many additional features to fully integrate your custom post types, including:

* Adds a menu option to the new menu section created in WordPress 3 (for themes that support it)
* Combined archives (http://www.mysite.com/type1+type2/)
* Option to add post types to default blog
* Option to add post types to default RSS feed
* Adds post type support to wp_get_archives function
* Adds multiple global functions and filters for working with custom post types (see Functions and Filters or Plugin Help for details)
* Automatically displays the associated post type on custom taxonomy archives
* Extends upon the WordPress templating, allowing for flexibility by theme authors (see FAQ or Plugin Help for details)
* Automatically flushes the rewrite cache after modifications are detected
* Automatically adds a "blog-{post_type}" type class to the body of the created archives (for themes that implement body_class())

For help and support, help has been built into the plug-in page's contextual help section; be sure to check [the FAQ](http://wordpress.org/extend/plugins/custom-post-archives/faq/) and the [plug-in forums](http://wordpress.org/tags/custom-post-archives?forum_id=10) if that doesn't do it.

If there are any other features you'd like to see, I'm all ears. Feel free to [send a message](mailto:requests.custompostarchives@gmail.com), or [hit the forums](http://wordpress.org/tags/custom-post-archives?forum_id=10)! 

== Installation ==

1. Upload the `custom-post-archives/` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Configure the post types you would like archives for on the settings page

== Frequently Asked Questions ==

= How do I format the newly created Archives? =

This plugin implements an extension of the [WordPress templating framework](http://codex.wordpress.org/Template_Hierarchy).
In descending order, each custom archive will look for these files in your template directory:

* tag-{post-type}.php (Only if is_tag())
* date-{post-type}.php (Only if is_date())
* author-{post-type}.php (Only if is_author())
* category-{post-type}.php (Only if is_category())
* archive-{post-type}.php (Only if is_custom_archive({post-type}))
* archive-custom.php (Only if is_custom_archive())
* archive.php
* index.php

In each of the above examples, {post-type} is replaced by the custom post type name, 
or an alphabetically sorted list of post types separated by a `_`, for archives where multiple post types are shown.

Custom Post Archives does not actually create any new files in your template directory, but it will use the archive.php file by default.

= How can I format the page title on the custom archive page? =

Custom Post Archives implements the `wp_title` filter to format the page title. If you'd like to change that formatting you can place
a variation of the following code into your `functions.php` file in your template directory.

If there are any other issues, feel free to [send a message](mailto:requests.custompostarchives@gmail.com), or [hit the forums](http://wordpress.org/tags/custom-post-archives?forum_id=10). 

`<?php
// Format Page title for custom archives
function custom_archive_wp_title($title, $sep, $seplocation)
{
	if(!is_custom_archive()) return $title;
	
	$label = get_custom_archive_label();
	
	if($label)
		$title = ($seplocation == 'right')
			? "$label $sep "
			: " $sep $label";
	
	return $title;
}
add_filter('wp_title','custom_archive_wp_title',10,3);
?>`

== Screenshots ==

1. The Settings Screen in the Administration.
2. You have the ability to add Custom Post Archives via the new Menu option!

== Changelog ==

= 1.0.3 - 28 Apr 2011 =
* Fixed bug where config page did not appear in certain cases if perl was installed
* Additional sanity checks in set_queried_object
* Eliminated redundant code in pre_get_home
* Fixed multi-post archive template call - was using "+" instead of "_"
* Added archive-post_type and tag-post_type body classes
* Addition of new 'get_post_type_archive_link' function.
* Addition of new 'get_post_type_archive_feed_link' function.
* Addition of new 'post_type_archive_title' function.


= 1.0.2 - 12 Nov 2010 =
* Added ability to use "/" or "+" in rewrite slug.
* Addition of new 'get_custom_archive_year_link' function.
* Addition of new 'get_custom_archive_month_link' function.
* Addition of new 'get_custom_archive_day_link' function.
* Addition of new 'get_custom_archive_feed_link' function.
* Addition of new 'get_custom_archive_feed_url' function.

= 1.0.1 - 11 Nov 2010 =
* Addition of new body class (category-{post-type}), a new template (category-{post-type}.php) and updates to help section.

= 1.0 - 10 Nov 2010 =
* Initial public release onto WordPress.org.

== Upgrade Notice ==

= 1.0.3 =
Bug fixes, addition of tag classes/templates.

= 1.0.2 =
Addition of ability to use new symbols in slug, and some new global functions.

= 1.0.1 =
Addition of new body class (category-{post-type}), a new template (category-{post-type}.php) and updates to help section.

= 1.0 =
Initial public download.

== Functions and Filters ==

The following function and filters are created by this plug-in. For more detailed descriptions, check the Help section built into the settings page.

**Functions**

`is_custom_archive({$post_type = false});`

`get_custom_archive_url($post_type);`
`get_custom_archive_label({$post_type = false});`
`get_custom_archive_link($post_type);`

The following functions are for advanced users. Each overrides the settings for the corresponding post_type in the plugin settings.

`add_custom_archive($post_type,$slug,{$in_default = false,$in_rss = false});`
`remove_custom_archive($post_type);`
`add_to_default_archive($post_type);`
`remove_from_default_archive($post_type);`
`reset_custom_archive_to_default($post_type);`

**Filters**

`cpa_rewrite_label`
Allows you to format the label returned by get_custom_archive_label().

`cpa_templates`
Allows you to provide or alter the list of templates Custom Post Archives searches for.
