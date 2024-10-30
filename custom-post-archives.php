<?php
/*
 * Plugin Name: Custom Post Archives
 * Plugin URI: http://www.bozell.com
 * Description: Adds rewrite rules for archive pages for all public custom post types. (ie: you can view all posts for custom post type 'project' at 'http://www.mywebsite.com/project/')
 * Author: Jacob Dunn
 * Version: 1.0.3
 * Author URI: http://www.bozell.com/
 *
 * -------------------------------------
 *
 * @package Custom Post Archives
 * @category Plugin
 * @author Jacob Dunn
 * @link http://www.bozell.com/ Bozell
 * @version 1.0.3
 *
 * -------------------------------------
 *
 * Custom Post Archives is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/*
	This plugin allows for the following types of urls:

		custom post type: project
		slug: project

		http://www.mysite.com/project/
		http://www.mysite.com/project/feed/
		http://www.mysite.com/project/page/2/
		http://www.mysite.com/project/date/2010/
		http://www.mysite.com/project/date/2010/feed/
		http://www.mysite.com/project/date/2010/page/2/
		http://www.mysite.com/project/date/2010/2/
		http://www.mysite.com/project/date/2010/2/feed/
		http://www.mysite.com/project/date/2010/2/page/2/

	To register a different base than the default post slug, use the following:

		if(class_exists(CustomPostArchives))
			CustomPostArchives::add_base("project","projects");

		This will change the above urls to:

		http://www.mysite.com/projects/
		http://www.mysite.com/projects/feed/
		http://www.mysite.com/projects/page/2/
		http://www.mysite.com/projects/date/2010/
		http://www.mysite.com/projects/date/2010/feed/
		http://www.mysite.com/projects/date/2010/page/2/
		http://www.mysite.com/projects/date/2010/2/
		http://www.mysite.com/projects/date/2010/2/feed/
		http://www.mysite.com/projects/date/2010/2/page/2/

	Once redirected, this plugin also allows for you to create post-type specific archive pages in your
	templates directory. The convention for naming is as follows:

		TEMPLATES/date-{post-type}.php
		TEMPLATES/archive-{post-type}.php

	Also adds ability to add post types to archive lists. ie, the following would return posts plus projects:

		wp_get_archives( "type=monthly&post_type=post,project" );

	You also have the option of adding the custom post type to the default archive and post queries. There are two ways to do this:

	Specify while adding base:

		if(class_exists(CustomPostArchives))
			CustomPostArchives::add_base("project","projects",true);

	Or Specify while keeping default base:

		if(class_exists(CustomPostArchives))
			CustomPostArchives::add_to_default("project");
*/

class CustomPostArchiveOptions{

	/*
		Sets pages with the same path as an archive base as a category 'home page'
	*/
	public static $pages_as_home = true;

	/*
		Promote sub pages of that base to be read before the custom post type
		--promotes the rewrite rules for page to above Custom Post Rewrite's rules
		--depends on $pages_as_home == true
	*/
	public static $promote_sub_pages = true;

	/*
		Highlight archive nav items if the current page is a sub-item of that page
	*/
	public static $highlight_archive_nav = true;

}

/* LESS EDITING BELOW */

define('CPA_VERSION', '1.0.3');
define('CPA_BASENAME', plugin_basename(__FILE__));

// Activation/Deactivation
register_activation_hook( __FILE__, 'CustomPostArchives::activate' );
register_deactivation_hook( __FILE__, 'CustomPostArchives::deactivate' );

// Configuration Page
require_once(dirname(__FILE__).'/config.php');
// Navigation Menu
require_once(dirname(__FILE__).'/nav-menu.php');

// Initialize
CustomPostArchives::get_manager();

class CustomPostArchives{

	private static $_singleton;
	private static $_debug_output = array();
	private static $_debug = false;
	
	public static function get_manager()
	{
		if(!isset(self::$_singleton)){
			self::$_singleton = new CustomPostArchives();
		}
		return self::$_singleton;
	}

	public static function add_base($post_type,$base,$in_default = false,$in_rss = false)
	{
		self::get_manager()->add_rewrite_base($post_type,$base,$in_default,$in_rss);
	}

	public static function remove_base($post_type)
	{
		self::get_manager()->remove_rewrite_base($post_type);
	}

	public static function add_to_default($post_type)
	{
		self::get_manager()->add_to_post_default($post_type);
	}

	public static function remove_from_default($post_type)
	{
		self::get_manager()->remove_from_post_default($post_type);
	}

	public static function reset_to_default($post_type)
	{
		self::get_manager()->add_rewrite_base($post_type,'',($post_type == "post"),($post_type == "post"));
		self::get_manager()->remove_rewrite_base($post_type);
	}

	public static function add($post_type)
	{
		self::get_manager()->remove_rewrite_base($post_type);
	}

	public static function remove($post_type)
	{
		self::get_manager()->add_rewrite_base($post_type,"");
	}

	public static function activate()
	{
		add_option('custom_post_rewrites',array(),'','yes');
	}

	public static function deactivate()
	{
		delete_option('custom_post_rewrites');
		if(isset($_GET['delete_data']))
			delete_option('cpa_config_settings');
	}

	public static function is_archive($post_type = false)
	{
		if(!isset(self::get_manager()->_current)) return false;
		if($post_type && !in_array($post_type,self::get_manager()->_current)) return false;
		return true;
	}

	public static function get_archive_link($post_type)
	{
		return self::get_manager()->get_rewrite_link($post_type);
	}

	public static function get_archive_year_link($post_type,$year)
	{
		return self::get_manager()->get_rewrite_year_link($post_type,$year);
	}

	public static function get_archive_month_link($post_type,$year,$month)
	{
		return self::get_manager()->get_rewrite_month_link($post_type,$year,$month);
	}

	public static function get_archive_day_link($post_type,$year,$month,$day)
	{
		return self::get_manager()->get_rewrite_day_link($post_type,$year,$month,$day);
	}

	public static function get_archive_feed_link($post_type,$anchor,$feed)
	{
		return self::get_manager()->get_rewrite_feed_link($post_type,$anchor,$feed);
	}

	public static function get_archive_url($post_type)
	{
		return self::get_manager()->get_rewrite_url($post_type);
	}

	public static function get_archive_feed_url($post_type,$feed)
	{
		return self::get_manager()->get_rewrite_feed_url($post_type,$feed);
	}

	public static function get_archive_label($post_type = false)
	{
		return self::get_manager()->get_rewrite_label($post_type);
	}

	public static function save()
	{
		return self::get_manager()->save_rules();
	}

	/* End Static Methods */

	protected $_rewrites;
	protected $_modified;

	protected $_current;
	protected $_current_base;
	protected $_queried_object;

	protected $_archive_post_types;
	protected $_archive_post_bases;
	protected $_archive_post_is_defaults;

	public function is_base()
	{
		return isset($this->_current);
	}

	public function get_rewrites()
	{
		return $this->_rewrites;
	}

	private function __construct() {
		$this->_rewrites = get_option('custom_post_rewrites');
		$this->_modified = false;

		add_action('init',array(&$this,'init'),100);
		add_action('publish_post',array(&$this,'publish_post'));
		add_action('generate_rewrite_rules', array(&$this,'generate_rewrite_rules'));
	}

	private function add_rewrite_base($post_type,$base,$in_default = false,$in_rss = false)
	{
		$rewrite = array('base'=>$base,'externally_set'=>true,'in_default'=>$in_default,'in_rss'=>$in_rss);
		$this->merge_rewrite($post_type,$rewrite);
	}

	private function remove_rewrite_base($post_type)
	{
		$rewrite = array('externally_set'=>false);
		$this->merge_rewrite($post_type,$rewrite);
	}

	private function add_to_post_default($post_type)
	{
		$rewrite = array('in_default'=>true);
		$this->merge_rewrite($post_type,$rewrite);
	}

	private function remove_from_post_default($post_type)
	{
		$rewrite = array('in_default'=>false);
		$this->merge_rewrite($post_type,$rewrite);
	}

	private function merge_rewrite($post_type,$rewrite)
	{
		$this->debug();

		if(isset($this->_rewrites[$post_type]))
			$rewrite = array_merge($this->_rewrites[$post_type],$rewrite);

		if($this->_rewrites[$post_type] != $rewrite){
			$this->_rewrites[$post_type] = $rewrite;
			$this->_modified = true;
		}
	}

	private function get_rewrite_link($post_types)
	{
		$url = $this->get_rewrite_url($post_types);
		if($url === false) return $url;

		$label = $this->get_rewrite_label($post_types);
		if($label === false) return $label;

		return apply_filters("cp_rewrite_link",sprintf('<a href="%1$s" title="%2$s">%2$s</a>',$url,$label));
	}

	private function get_rewrite_year_link($post_types = array(),$year)
	{
		$base = $this->get_rewrite_url($post_types);
		$url = get_year_link($year);
		if($base !== false)
			$url = str_replace(array(home_url(),'?'),array($base,'&'),$url);
		return apply_filters('cp_rewrite_year_link', $url, $year);
	}

	private function get_rewrite_month_link($post_types = array(),$year,$month)
	{
		$base = $this->get_rewrite_url($post_types);
		$url = get_month_link($year,$month);
		if($base !== false)
			$url = str_replace(array(home_url(),'?'),array($base,'&'),$url);
		return apply_filters('cp_rewrite_month_link', $url, $year, $month);
	}

	private function get_rewrite_day_link($post_types = array(),$year,$month,$day)
	{
		$base = $this->get_rewrite_url($post_types);
		$url = get_day_link($year,$month,$day);
		if($base !== false)
			$url = str_replace(array(home_url(),'?'),array($base,'&'),$url);
		return apply_filters('cp_rewrite_day_link', $url, $year, $month, $day);
	}

	private function get_rewrite_feed_link($post_types = array(),$anchor,$feed = '')
	{
		$url = $this->get_rewrite_feed_url($post_types, $feed);
		if($url === false) return $url;

		return apply_filters("cp_rewrite_feed_link",sprintf('<a href="%1$s" title="%2$s">%2$s</a>',$url,$anchor));
	}

	private function get_rewrite_url($post_types = array())
	{
		// Parse Args, grab current if set
		if($post_types == false) $post_types = array();
		if(!is_array($post_types)) $post_types = explode(",",$post_types);
		$current = (isset($this->_current)) ? $this->_current : array();
		$post_types = wp_parse_args( $post_types, $current );

		if(count($post_types) == 0) return false;

		// Get Bases
		$bases = array();
		$home = get_option( 'home' );
		foreach($post_types as $post_type){
			$base = $this->get_base($post_type);
			if($base != NULL) $bases[] = $base;
		}

		if(count($base) == 0) return false;

		global $wp_rewrite;
		if ( $wp_rewrite->using_permalinks() )
			return $home.'/'.implode("+",$bases);
		else
			return $home.'/index.php?archive='.implode("+",$bases);

		return false;
	}

	private function get_rewrite_feed_url($post_types = array(),$feed = "")
	{
		$output = $this->get_rewrite_url($post_types);
		if($feed == '') $feed = get_default_feed();

		global $wp_rewrite;
		$output .= ( $wp_rewrite->using_permalinks() )
			? '/feed'
			: '&feed='.$feed;

		return apply_filters('feed_link', $output, $feed);

		return false;
	}

	private function get_rewrite_label($post_types = false)
	{
		if(!$post_types) $post_types = $this->_current;
		$post_types = (is_array($post_types)) ? $post_types : explode(",",$post_types);
		$return = array();
		foreach($post_types as $post_type){
			$post_type_object = get_post_type_object($post_type);
			if(!empty($post_type_object->labels->name))
				$return[] = $post_type_object->labels->name;
		}
		if(count($return) == 0) return false;
		if(count($return) > 1){
			$last = array_pop($return);
			$return = implode(", ",$return).__(" and")." $last";
		}else $return = $return[0];

		return apply_filters("cpa_rewrite_label",$return);
	}

	private function get_post_types(){
		return get_post_types(array('public'=>true,'publicly_queryable'=>true),'objects');
	}

	private function populate_rewrites()
	{
		// See what's changed
		$post_types = $this->get_post_types();
		foreach($post_types as $post_type => $post_data){
			$default = array(
				"externally_set" => false
				,"in_default" => ($post_type == "post")
				,"in_rss" => ($post_type == "post")
			);

			if(isset($this->_rewrites[$post_type]))
				$default = array_merge($default,$this->_rewrites[$post_type]);
			if(!$default["externally_set"])
				$default["base"] = $post_data->rewrite["slug"];

			if($this->_rewrites[$post_type] != $default){
				$this->_rewrites[$post_type] = $default;
				$this->_modified = true;
			}
		}

		// Delete what's gone
		foreach($this->_rewrites as $post_type => $rewrite){
			if(!array_key_exists($post_type,$post_types)){
				unset($this->_rewrites[$post_type]);
				$this->_modified = true;
			}
		}
	}

	private function flush_rules(){
		// Processor Intensive, only run on change or addition
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	}

	private function save_rules()
	{
		$this->flush_rules();
		update_option('custom_post_rewrites',$this->_rewrites);
	}

	private function debug()
	{
		if(self::$_debug)
			self::$_debug_output[] = debug_backtrace();
	}

	/* Actions */

	public function init(){
		$this->populate_rewrites();

		if($this->_modified){
			$this->save_rules();
		}

		add_action('query_vars', array(&$this,'query_vars'));
		add_action('pre_get_posts', array(&$this,'pre_get_posts'));
		add_action('template_redirect', array(&$this,'template_redirect'));
		add_action('wp_head', array(&$this,'feed_links'), 2);

		add_filter('request', array(&$this,'request'));
		add_filter('getarchives_where', array(&$this, 'getarchives_where'),10,2);
		add_filter('body_class', array(&$this,'body_class'));
		add_filter('year_link', array(&$this,'date_link'));
		add_filter('month_link', array(&$this,'date_link'));
		add_filter('day_link', array(&$this,'date_link'));
		add_filter('author_link', array(&$this,'author_link'));
		add_filter('wp_title',array(&$this,'wp_title'),9,3);

		// Add Parent Class to home page, remove from base page if categories are highlighted
		if(CustomPostArchiveOptions::$pages_as_home){
			add_filter('wp_list_pages', array(&$this,'wp_list_pages'),10,2);
			add_filter('wp_nav_menu', array(&$this,'wp_nav_menu'),10,2);
			add_filter('wp_nav_menu_items',array(&$this,'wp_nav_menu_items'),10,2);
		}

		// Add Parent Class to a categories of current post
		if(CustomPostArchiveOptions::$highlight_archive_nav)
			add_filter('wp_list_categories', array(&$this,'wp_list_categories'));

		// FOR DEBUGGING
		if(self::$_debug){
			add_action('parse_request', array(&$this,'parse_request'));
			$this->flush_rules();
		}
	}

	//Debug
	public function parse_request($wp)
	{
		global $wp_rewrite;
		print("<div style=\"display:none;\">");
			print_r($wp);
			print_r($wp_rewrite);
			print_r($this->_rewrites);
			print_r(self::$_debug_output);
		print("</div>");
	}

	// See if this is a sub page of an existing base
	public function publish_post($post_id)
	{
		global $wp_rewrite;

		if(!CustomPostArchiveOptions::$pages_as_home // We aren't handling this
			|| $wp_rewrite->use_verbose_page_rules) // Wordpress will flush...don't worry about it.
			return $post_id;

		// Page Path
		$path = $wp_rewrite->get_page_permastruct();
		$path = str_replace('%pagename%', get_page_uri($post_id), $path);
		$path = user_trailingslashit($path, 'page');

		$bases = $this->get_bases();

		// Search Bases for Path
		foreach($bases as $base){
			$base = preg_quote($base);
			if(preg_match("#^$base#",$path)){
				$this->_modified = true;
				break;
			}
		}
		return $post_id;
	}

	// Add our rewrite rules
	public function generate_rewrite_rules( $wp_rewrite )
	{
		global $wp_rewrite;
		$this->populate_rewrites();
		$bases = $this->get_bases();

		$wp_rewrite->add_rewrite_tag("%archive%", "((?:(?:".implode("|",$bases).")\+?)+)", 'archive=');

		$rewrites  = $wp_rewrite->generate_rewrite_rules("/%archive%/");
		$rewrites += $wp_rewrite->generate_rewrite_rules("/%archive%".$wp_rewrite->get_category_permastruct());
		$rewrites += $wp_rewrite->generate_rewrite_rules("/%archive%".$wp_rewrite->get_author_permastruct());
		$rewrites += $wp_rewrite->generate_rewrite_rules("/%archive%".$wp_rewrite->get_tag_permastruct());
		$rewrites += $wp_rewrite->generate_rewrite_rules("/%archive%".$wp_rewrite->get_date_permastruct());
		$rewrites += $wp_rewrite->generate_rewrite_rules("/%archive%".$wp_rewrite->get_month_permastruct());
		$rewrites += $wp_rewrite->generate_rewrite_rules("/%archive%".$wp_rewrite->get_year_permastruct());

		// Promote sub-pages of pages that share same path as our bases
		$rewrites = $this->promote_sub_pages($rewrites,$bases);

		$wp_rewrite->rules = $rewrites + $wp_rewrite->rules;

		return $wp_rewrite;
	}

	// Promotes rewrites for all sub pages
	private function promote_sub_pages($rewrites,$bases)
	{
		if(!CustomPostArchiveOptions::$pages_as_home || !CustomPostArchiveOptions::$promote_sub_pages)
			return $rewrites; // We aren't handling this

		global $wp_rewrite;
		$page_permastruct = $wp_rewrite->get_page_permastruct();
		$promote = array();

		// Find paths to promote
		foreach($bases as $base){
			if($base == "") continue;
			$page = & get_page_by_path($base);
			if(!empty($page)){
				$children = get_posts(array('post_parent'=>$page->ID,'numberposts'=>-1,'post_type'=>'page','post_status'=>'all'));
				foreach($children as $child){
					$path = str_replace('%pagename%', get_page_uri($child->ID), $page_permastruct);
					$path = user_trailingslashit($path, 'page');
					$promote[] = $path;
				}
			}
		}

		// Generate verbose rules if not already generated
		if($wp_rewrite->use_verbose_page_rules)
			$source = & $wp_rewrite->rules;
		else
			$source = & $wp_rewrite->page_rewrite_rules();

		// Find matching rules, and promote
		$promoted = array();
		foreach($source as $rule => $action){
			foreach($promote as $path){
				$path = preg_quote($path);
				if(preg_match("#^\(?$path#",$rule)){
					$promoted[$rule] = $action;
					unset($source[$rule_index]);
					break;
				}
			}
		}
		$rewrites = $promoted + $rewrites;

		return $rewrites;
	}

	// Add our custom var
	public function query_vars($query_vars)
	{
		$query_vars[] = "archive";
		return $query_vars;
	}

	// Handle custom var
	public function pre_get_posts($query)
	{
		// $query->query can be passed as string
		$query->query = wp_parse_args($query->query);

		// Check to see if this is a query this plugin modifies
		if(isset($query->query["archive"]))
			$query = $this->pre_get_custom_archive($query);
		else if(isset($query->query["taxonomy"]))
			$query = $this->pre_get_taxonomy($query);
		else if(is_archive() || is_date() || is_author() || is_tag() || is_home())
			$query = $this->pre_get_archive($query);

		// Select matching page, if default query
		if($query === $GLOBALS['wp_the_query'])
			$this->set_queried_object($query);

		return $query;
	}

	private function pre_get_custom_archive($query)
	{
		// Find the correct post type
		$this->_current_base = explode("+",$query->query["archive"]);
		$this->_current = array();
		foreach($this->_current_base as $base){
			$this->_current[] = $this->get_post_type($base);
		}
		sort($this->_current);

		// Merge our vars back in w/ the correct post type
		$query_vars = array('post_type' => $this->_current);
		$query_vars = array_merge($query->query,$query_vars);

		// Get rid of the archive var
		unset($query_vars["archive"]);

		// Re-Parse
		$query->parse_query($query_vars);

		return $query;
	}

	// Add associated post types to query
	private function pre_get_taxonomy($query)
	{
		global $wp_taxonomies;

		// If a specific post type is requested, ignore
		if(isset($query->query["post_type"]))
			return $query;

		// Find selected taxonomy
		$taxonomy = $wp_taxonomies[$query->query["taxonomy"]];

		// Make sure we have a valid taxonomy
		if(!isset($taxonomy))
			return $query;

		// Merge our vars back in w/ the associated object types
		$query_vars = array('post_type' => $taxonomy->object_type);
		$query_vars = array_merge($query->query,$query_vars);

		// Re-Parse
		$query->parse_query($query_vars);

		return $query;
	}

	// Add publicly queryable post types to query
	private function pre_get_archive($query)
	{
		// If a specific post type is requested, ignore
		if(isset($query->query['post_type']))
			return $query;

		// Find selected post types
		$post_types = array();
		foreach($this->_rewrites as $post_type => $rewrite){
			if($rewrite["in_default"])
				$post_types[] = $post_type;
		}

		// Merge our vars back in w/ the selected post types
		$query_vars = array('post_type' => $post_types);
		$query_vars = array_merge($query->query,$query_vars);

		// Re-Parse
		$query->parse_query($query_vars);

		return $query;
	}

	// See if there's a page w/slug == _current_base || post_type, if so, set that to be the queried_object
	private function set_queried_object($query)
	{
		// If Enabled
		if(!CustomPostArchiveOptions::$pages_as_home) return;

		// Figure out what the base is.
		if(isset($this->_current_base)) $path = implode("+",$this->_current_base);
		else if(isset($query->query["post_type"])){
			$post_type = $query->query["post_type"];
			$path = str_replace(get_option( 'home' ).'/','',$this->get_rewrite_url($post_type));
		}else return;

		if(!$path) return;

		// Find out if a page shares the same path
		$page = & get_page_by_path($path);

		if(!empty($page)){
			// Set this as an INTERNAL query object - originally set the wp_query queried_object, but that had unintended side-effects
			$this->_queried_object = & $page;
		}else if($post_type){
			if(!is_array($post_type)) $post_type = array($post_type);
			foreach($post_type as $rewrite){
				if(!$this->_rewrites[$rewrite]["in_default"]){
					// No object found - but it's not in the default, so 'at' needs to be removed
					$this->_queried_object = $path;
					break;
				}
			}
		}
	}

	public function get_bases()
	{
		$bases = array();
		foreach($this->_rewrites as $key => $rewrite){
			if($rewrite["base"] == "") continue;
			$bases[] = $rewrite["base"];
		}
		return $bases;
	}

	// Get base by post_type
	public function get_base($post_type)
	{
		if(!is_string($post_type)) return NULL;
		$rewrite = $this->_rewrites[$post_type];
		if(!isset($rewrite)) return NULL;
		return $rewrite["base"];
	}

	// Get post_type by base
	public function get_post_type($base)
	{
		foreach($this->_rewrites as $post_type => $rewrite){
			if($rewrite["base"] == $base){
				return $post_type;
			}
		}
		return NULL;
	}

	//Add our custom templates
	public function template_redirect(){
		if(!isset($this->_current) || is_feed()) return;

		$templates = array();
		if(is_tag()) $templates[] = "tag-".implode("_",$this->_current).".php";
		if(is_date()) $templates[] = "date-".implode("_",$this->_current).".php";
		if(is_author()) $templates[] = "author-".implode("_",$this->_current).".php";
		if(is_category()) $templates[] = "category-".implode("_",$this->_current).".php";
		$templates[] = "archive-".implode("_",$this->_current).".php";
		$templates[] = "archive-custom.php";
		$templates[] = "archive.php";
		$templates[] = 'index.php';
		$templates =  apply_filters("cpa_templates",$templates);

		$template = locate_template( $templates );

		require_once($template);

		die();
	}


	/* Adds select post types to default RSS feed */
	public function request($query) {
		// Modify request if is default feed
		if (!isset($query['feed']) || isset($query['post_type']) ||  isset($query['archive']))
			return $query;

		$post_types = array();
		foreach($this->_rewrites as $post_type => $rewrite){
			if($rewrite["in_rss"] == true)
				$post_types[] = $post_type;
		}

		$query['post_type'] = $post_types;

		return $query;
	}

	// Add selected to archive list query
	public function getarchives_where($where,$r){

		unset($this->_archive_post_types);
		unset($this->_archive_post_bases);
		$this->_archive_post_is_defaults = false;

		if(isset($r["post_type"])){
			$this->_archive_post_types = (!is_array($r["post_type"]))
				? explode(",",$r["post_type"])
				: $r["post_type"];

			// Escape...
			array_walk($this->_archive_post_types, create_function(
					'&$value,$key',
					'global $wpdb; $value = $wpdb->escape($value);'));
		}else if(isset($this->_current)){
			$this->_archive_post_types = $this->_current;
		}else{
			// Add all additional default archive post types
			$this->_archive_post_is_defaults = true;
			$this->_archive_post_types = array();
			foreach($this->_rewrites as $post_type => $rewrite){
				if($rewrite["in_default"])
					$this->_archive_post_types[] = $post_type;
			}
		}

		if(isset($this->_archive_post_types))
			$where = preg_replace("/post_type = 'post'/","(post_type = '".implode("' OR post_type = '",$this->_archive_post_types)."')",$where);

		return $where;
	}

	// Body class for archives, taxonomies
	public function body_class($classes){
		global $wp_query;

		if(isset($this->_current))
			foreach($this->_current as $post_type){
				$classes[] = "blog-".$post_type;
				$classes[] = "archive-".$post_type;
				if(is_tag()) $classes[] = "tag-".$post_type;
				if(is_date()) $classes[] = "date-".$post_type;
				if(is_author()) $classes[] = "author-".$post_type;
				if(is_category()) $classes[] = "category-".$post_type;
			}
		if(is_tax())
			$classes[] = $wp_query->query_vars["taxonomy"]."-".$wp_query->query_vars[term];
		return $classes;
	}

	// Format Date Archive Links
	public function date_link($link)
	{
		if(!isset($this->_archive_post_types)) return $link;

		if(!isset($this->_archive_post_bases)){
			$this->_archive_post_bases = array();
			foreach($this->_archive_post_types as $post_type){
				$base = $this->get_base($post_type);
				if($base != NULL) $this->_archive_post_bases[] = $base;
			}
		}

		if(count($this->_archive_post_bases) == 0 || $this->_archive_post_is_defaults) return $link;

		global $wp_rewrite;
		if ( $wp_rewrite->using_permalinks() ) {
			$home = get_option( 'home' );
			$link = str_replace($home,"$home/".implode("+",$this->_archive_post_bases),$link);
		}else{
			$link .= "&archive=".implode("+",$this->_archive_post_bases);
		}

		return $link;
	}

	// Format Author Links
	public function author_link($link)
	{
		global $post;

		$base = NULL;
		if(isset($post))
			$base = $this->get_base($post->post_type);
		else if(is_array($this->_current_base))
			$base = implode('+',$this->_current_base);

		if($base == NULL) return $link;


		global $wp_rewrite;
		if ( $wp_rewrite->using_permalinks() ) {
			$home = get_option( 'home' );
			$link = str_replace($home,"$home/".$base,$link);
		}else{
			$link .= "&archive=".$base;
		}

		return $link;
	}

	// Format Home Page Links
	public function wp_list_pages($output, $r)
	{
		if(!isset($this->_queried_object)) return $output;

		// Remove current 'at' reference - it's wrong
		$output = preg_replace("#\s*current_page_parent#","",$output);

		// Add 'at' to current
		if(is_object($this->_queried_object))
			$output = preg_replace("#(page-item-".$this->_queried_object->ID.")#","$1 current_page_parent",$output);

		return $output;
	}

	// Format Home Page Links
	public function wp_nav_menu_items($items, $args)
	{
		if(!isset($this->_queried_object)) return $items;
		return $items;
	}

	// Format Home Page Links
	public function wp_nav_menu($nav_menu, $args)
	{
		if(!isset($this->_queried_object)) return $nav_menu;

		// Remove current 'at' reference - it's wrong
		$nav_menu = preg_replace("#\s*current_page_parent#","",$nav_menu);

		// Add 'at' to parent of current object
		if(is_string($this->_queried_object))
			$nav_menu = preg_replace(
				sprintf('#\<li(.*?)class="([^"]*)"(.*?)href="(%1$s/%2$s)"#',get_option( 'home' ),$this->_queried_object),
				'<li$1class="$2 current_page_parent"$3href="$4"',
				$nav_menu);

		// Add 'at' to current
		if(is_object($this->_queried_object))
			$nav_menu = preg_replace("#(current-".$this->get_post_type($this->_queried_object->post_name)."-parent)#","$1 current_page_parent",$nav_menu);

		return $nav_menu;
	}

	// Format Taxonomy Links
	public function wp_list_categories($output)
	{
		if(!is_single()) return $output;

		global $post;
		$taxonomies = get_taxonomies(array('public' => true),"object");
		$parents = array();
		foreach($taxonomies as $taxonomy){
			$terms = get_the_terms($post->ID,$taxonomy->name);
			if($terms)
			foreach($terms as $term)
				array_push($parents,'/(cat-item-'.$term->term_id.')/');
		}

		if(count($parents) == 0) return $output;

		$output = preg_replace($parents,"$1 current-item-cat",$output);

		return $output;
	}

	// Format Page title for archives
	public function wp_title($title, $sep, $seplocation)
	{
		if(!isset($this->_current)) return $title;

		$label = $this->get_rewrite_label($this->_current);

		if($label)
			$title = ($seplocation == 'right')
				? "$label $sep "
				: " $sep $label";

		return $title;
	}

	// Add Feed Links
	public function feed_links($args = array())
	{
		if ( !current_theme_supports('automatic-feed-links') || !isset($this->_current)) return;
		$label = $this->get_rewrite_label($this->_current);

		if(!$label) return;

		$defaults = array(
			/* translators: Separator between blog name and feed type in feed links */
			'separator'	=> _x('&raquo;', 'feed link'),
			/* translators: 1: blog title, 2: separator (raquo), 3: Post Type title */
			'feedtitle'	=> __('%1$s %2$s %3$s Feed')
		);

		$args = wp_parse_args( $args, $defaults );

		echo '<link rel="alternate" type="' . feed_content_type() . '" title="' . esc_attr(sprintf( $args['feedtitle'], get_bloginfo('name'), $args['separator'], $label)) . '" href="' . $this->get_rewrite_feed_url() . "\" />\n";

	}
}



/*
 *
 *	Public Global Functions
 *
 */

function is_custom_archive($post_type = false)
{
	return CustomPostArchives::is_archive($post_type);
}

function get_custom_archive_year_link($post_type = '',$year = '')
{
	return CustomPostArchives::get_archive_year_link($post_type,$year);
}

function get_custom_archive_month_link($post_type = '',$year = '',$month = '')
{
	return CustomPostArchives::get_archive_month_link($post_type,$year,$month);
}

function get_custom_archive_day_link($post_type = '',$year = '',$month = '',$day = '')
{
	return CustomPostArchives::get_archive_day_link($post_type,$year,$month,$day);
}

function get_custom_archive_feed_link($post_type, $anchor, $feed = '')
{
	return CustomPostArchives::get_archive_feed_link($post_type, $anchor, $feed);
}

function get_custom_archive_link($post_type)
{
	return CustomPostArchives::get_archive_link($post_type);
}

function get_custom_archive_url($post_type)
{
	return CustomPostArchives::get_archive_url($post_type);
}

function get_custom_archive_feed_url($post_type,$feed = '')
{
	return CustomPostArchives::get_archive_feed_url($post_type,$feed);
}

function get_custom_archive_label($post_type = false)
{
	return CustomPostArchives::get_archive_label($post_type);
}

function add_custom_archive($post_type,$slug,$in_default = false,$in_rss = false)
{
	CustomPostArchives::add_base($post_type,$slug,$in_default,$in_rss);
}

function remove_custom_archive($post_type)
{
	CustomPostArchives::remove_base($post_type);
}

function add_to_default_archive($post_type)
{
	CustomPostArchives::add_to_default($post_type);
}

function remove_from_default_archive($post_type)
{
	CustomPostArchives::remove_from_default($post_type);
}

function reset_custom_archive_to_default($post_type)
{
	CustomPostArchives::reset_to_default($post_type);
}

/*
 * Provide functionality for the new functions to be provided in the 3.1 update
 *
 * These are the functions proposed in the patch, not solid until release.
 */

if(!function_exists('get_post_type_archive_link')) :
function get_post_type_archive_link($post_type)
{
	return CustomPostArchives::get_archive_link($post_type);
}
endif;

if(!function_exists('get_post_type_archive_feed_link')) :
function get_post_type_archive_feed_link($post_type, $anchor, $feed = '')
{
	return CustomPostArchives::get_archive_feed_link($post_type, $anchor, $feed);
}
endif;

if(!function_exists('post_type_archive_title')) :
function post_type_archive_title($post_type = false)
{
	return CustomPostArchives::get_archive_label($post_type);
}
endif;