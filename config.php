<?php

/*
 *	Configuration Page
 *
 *
 */

define('CPA_CONFIG', __FILE__);

class CPAConfig{

	public static $cpa_nonce = "cpa_update";

	public static function get_instance()
	{
		if(!isset(self::$_singleton)){
			self::$_singleton = new CPAConfig();
		}
		return self::$_singleton;
	}
	private static $_singleton;

	private $settings;

	private function __construct()
	{
		$this->settings = get_option('cpa_config_settings');

		add_action('admin_init',array(&$this,'admin_init'));
		add_action('admin_menu',array(&$this,'admin_menu'));

		//Apply Settings
		$this->apply_settings();
	}

	private function save_settings()
	{
		// Verify Nonce
		if ( !isset($_POST['cpa_submit']) || !wp_verify_nonce( $_POST[self::$cpa_nonce], plugin_basename(CPA_CONFIG) )) return;


		//check permissions
		if ( !current_user_can('manage_options') )
			die(__('Cheatin&#8217; uh?'));

		//incase we didn't have it
		add_option('cpa_config_settings','','','no');



		$rewrites = CustomPostArchives::get_manager()->get_rewrites();
		$parameters = array('overloaded','base','in_default','in_rss');
		$config_settings = array();

		foreach($rewrites as $post_type => $rewrite){

			$config_settings[$post_type] = array();

			foreach($parameters as $parameter){
				// See if it's set
				$value = @$_POST["cpa::$post_type::$parameter"];
				if(!isset($value)) continue;

				//Scrub
				switch($parameter){
					case 'base':
						if($value == "--disabled--") $value = "";
						$value = preg_replace('/[^\da-z-_]+/','-',strtolower($value));
						break;
					case 'in_default':
						$value = ($value == 'on') ? true : false;
						break;
					case 'in_rss':
						$value = ($value == 'on') ? true : false;
						break;
					case 'overloaded': //Ignore all settings
						break 2;
				}

				$config_settings[$post_type][$parameter] = $value;
			}

			if(count($config_settings[$post_type]) == 0)
				CustomPostArchives::reset_to_default($post_type);
		}

		//save options
		update_option('cpa_config_settings',$config_settings);
		CustomPostArchives::save();

		$return_vars = "?page=cpa_options&updated=true"; // success

		//redirect
		header('Location: options-general.php'.$return_vars);
		exit();
	}

	// Apply Loaded Settings
	private function apply_settings()
	{
		if(!is_array($this->settings)) return;

		foreach($this->settings as $post_type=>$setting){
			CustomPostArchives::add_base(
				$post_type,
				$setting['base'],
				(isset($setting['in_default']) && $setting['in_default']),
				(isset($setting['in_rss']) && $setting['in_rss']));
		}
	}

	// Check that settings have not been over-ridden
	private function check_settings($rewrites)
	{
		if(!is_array($this->settings)) return $rewrites;

		foreach($rewrites as $post_type => &$rewrite){
			if(!isset($this->settings[$post_type])){
				if($rewrite['externally_set'])
					$rewrite['overloaded'] = true;
			}else{
				unset($rewrite['externally_set']);
				foreach($rewrite as $key => $value){
					if(isset($this->settings[$post_type][$key])){
						if($this->settings[$post_type][$key] != $value)
						$rewrite['overloaded'] = true;
					}else if($value != ""){
						$rewrite['overloaded'] = true;
					}
				}
			}
		}

		return $rewrites;
	}

	/*
	 *	Callbacks
	 */

	// All postback actions here
	public function admin_init()
	{
		add_action('admin_head', array($this,'admin_head'));

		//Add to plugins menu
		if ( current_user_can('manage_options') ){
			add_filter('plugin_action_links',array(&$this,'plugin_action_links'),10,2);
		}

		//Save
		$this->save_settings();
	}

	// Add JS, CSS
	public function admin_head() {
		echo '<script type="text/javascript" src="'.plugins_url('scripts/cpa_scripts.js',__FILE__).'"></script>';
		if(isset($_GET['page']) && $_GET['page'] == 'cpa_options'){
			echo '<link type="text/css" media="screen" rel="stylesheet" href="'.plugins_url('styles/cpa_styles.css',__FILE__).'" />';
			add_contextual_help('settings_page_cpa_options',$this->config_help());
		}
	}

	// Add to Menu
	public function admin_menu()
	{
		add_options_page( __('Custom Post Archives'), __('Custom Post Archives'), 'manage_options', 'cpa_options', array(&$this,'config_page'));
	}

	// Tag deactivate link to allow for customization, add settings link
	public function plugin_action_links($links, $file) {
		if( $file == CPA_BASENAME ){
			$links['deactivate'] = preg_replace("/(title=)/","id=\"cpa_deactivate\" $1",$links['deactivate']);
			$settings_link = '<a href="options-general.php?page=cpa_options">Settings</a>';
			array_unshift( $links, $settings_link );
		}
		return $links;
	}

	/*
	 * Config Page
	 */
	 public function config_page()
	 {

		//check permissions
		if ( !current_user_can('manage_options') )
			die(__('Cheatin&#8217; uh?'));

		//Show confirmation of update
		if ( isset($_GET['updated']) )
			printf('<div id="message" class="updated fade"><p><strong>%1$s</strong></p></div>',__('Options Saved.'));

		?>

        <script type="text/javascript">
			jQuery(document).ready(function($){
				// On Active Change
				$('input.active').change(function(){
					var base = $(this).parents('tr').find('.base');
					if($(this).is(':checked')){
						if(base[0].prevValue != null)
							base.val(base[0].prevValue);
						if(base.val() == '')
							base.val($(this).parents('tr').find('h5').text());

						base.attr('disabled',false)
							.removeClass('disabled');
					}else{
						base[0].prevValue = (base.val() == "--disabled--")
							? ''
							: base.val();
						base.val('--disabled--')
							.attr('disabled',true)
							.addClass('disabled')
							.after('<div class="cover"/>');

						var offset = base.position();

						base.next('.cover').css({
								position:'absolute'
								,left:offset.left+'px'
								,top:offset.top+'px'
								,width:base.outerWidth()+'px'
								,height:base.outerHeight()+'px'
								,zindex:3
								,cursor:'pointer'
							})
							.click(function(){
								$(this).parents('tr').find('.active').attr('checked',true).change();
								base.val(base[0].prevValue)
									.focus();
								$(this).remove();
							});
					}
				}).change();

				// On Base Change
				$('input.base')
					.change(function(){
						$(this).val($(this).val().toLowerCase().replace(/[^\da-z-_]+/g,'-'));
					})
					.blur(function(){
						if($(this).val() == ""){
							$(this).parents('tr').find('.active').attr('checked',false).change();
						}
					});

				// Ignore Action
				$('a.ignore').click(function(){
					$(this).parents('tr').removeClass('overloaded').find('.overloaded-notice').remove();
					return false;
				});

				// Layout Additions
				$('#screen-meta-links').append('<div id="getting-started-link">Getting Started?</div>');
			});
		</script>

        <div class="wrap">
        	<div class="icon32" id="icon-options-general"><br></div>
            <h2><?php _e('Custom Post Archives'); ?></h2>

            <form action="options.php" method="post" id="cpa-config" class="cpa-form">
                <input type="hidden" name="cpa_submit" value="true" />
                <input type="hidden" name="<?php echo CPAConfig::$cpa_nonce; ?>" id="<?php echo CPAConfig::$cpa_nonce; ?>" value="<?php echo wp_create_nonce( plugin_basename(CPA_CONFIG) ); ?>" />

                <table class="post-archives" cellpadding="0" cellspacing="0">
                	<thead>
                    	<th><?php _e('Post Type'); ?></th>
                    	<th><?php _e('Active'); ?></th>
                    	<th><?php _e('Include in Blog'); ?></th>
                    	<th><?php _e('Include in RSS'); ?></th>
                    	<th><?php _e('Archive Slug'); ?></th>
                    </thead>
                    <tbody>
					<?php
                    $rewrites = CustomPostArchives::get_manager()->get_rewrites();

                    // Check for changes from the settings applied by config
                    $rewrites = $this->check_settings($rewrites);

                    foreach($rewrites as $post_type => $rewrite){
                        $post_type_object = get_post_type_object( $post_type );

                        ?>
                        <tr<?php if($rewrite['overloaded']) echo ' class="overloaded"';?>>
                            <th>
                                <h5><?php echo $post_type; ?></h5>
                            </th>
                            <th>
								<?php printf('<input type="checkbox" class="active" name="cpa::%1$s::active" id="cpa::%1$s::active" %2$s />',
                                    $post_type,($rewrite['base'] != "") ? 'checked="checked"' : ''); ?>
                            </th>
                            <th>
								<?php printf('<input type="checkbox" class="in-default" name="cpa::%1$s::in_default" id="cpa::%1$s::in_default" %2$s />',
                                    $post_type,($rewrite['in_default'] === true) ? 'checked="checked"' : ''); ?>
                            </th>
                            <th>
								<?php printf('<input type="checkbox" class="in-rss" name="cpa::%1$s::in_rss" id="cpa::%1$s::in_rss" %2$s />',
                                    $post_type,($rewrite['in_rss'] === true) ? 'checked="checked"' : ''); ?>
                            </th>
                            <th>
								<?php printf('<input type="text" class="base%4$s" name="cpa::%1$s::base" id="cpa::%1$s::base" value="%2$s" %3$s />',
                                    $post_type,
									($rewrite['base'] == "") ? '--disabled--' : $rewrite['base'],
									($rewrite['base'] == "") ? 'disabled="disabled"' : '',
									($rewrite['base'] == "") ? ' disabled' : ''); ?>
                                <?php if($rewrite['overloaded']){?>
                                	<div class="overloaded-notice">
                                    	<div>
											<?php printf('<input type="hidden" name="cpa::%1$s::overloaded" id="cpa::%1$s::overloaded" value="true" />',$post_type);?>
                                        	<?php _e('This archive configuration has been overloaded by a function on your site.'); ?>
                                        	<a class="ignore" href="#ignore"><?php _e('Ignore &raquo;'); ?></a>
                                        </div>
                                    </div>
                                <?php } ?>
                            </th>
                    	</tr>
                        <?php
                    }
                    ?>
	                </tbody>
                </table>
                <div id="cpa-actions">
                	<input type="submit" value="Save Changes" accesskey="s" class="button-primary">
                </div>
            </form>
        </div>

        <?php
	 }

	public function config_help()
	{

	$blog_page = get_option('home');
	$rss_feed = get_bloginfo('rss2_url');
	$menu_page = get_option('siteurl')."/wp-admin/nav-menus.php";

	$message = <<<EOD
	<div id="cpa-help">
		<h3>Custom Post Archive Help <small>For all examples on this page, the custom post type of "custom" with a slug of "custom-posts" is assumed.</small></h3>

		<h4 class="cpa-tab-link"><a href="#cpa-basics">Basics</a></h4>

		<div id="cpa-basics" class="cpa-tab">

			<p>Custom Post Archives bridges the gap between creating Custom Post Types in WordPress 3, and actually displaying those posts. With this plugin, you have
			the option of displaying a completely seperate blog-like section for each post type, complete with all the features you expect to see with WordPress.</p>

			<h5>For each custom post type, if you click "active" and enter a slug-name, this plugin will let you display post-type specific:</h5>

			<ul>
				<li>
					Archives
					<span class="caption">- A paginated list of all posts in that custom post type.</span><br />
					<pre>http://www.example.com/custom-posts</pre>
				</li>
				<li>
					Date archives
					<span class="caption">- Day, Month, and Year archives with feeds and pagination.</span><br />
					<pre>http://www.example.com/custom-posts/2010/10</pre>
				</li>
				<li>
					Author archives
					<span class="caption">- Custom Post Type specific author pages.</span><br />
					<pre>http://www.example.com/custom-posts/author/some-author</pre>
				</li>
				<li>
					Category archives
					<span class="caption">- Custom Post Type specific category pages.</span><br />
					<pre>http://www.example.com/custom-posts/category-base/some-category</pre>
				</li>
				<li>
					Feeds
					<span class="caption">- Custom Post Type specific feeds.</span><br />
					<pre>http://www.example.com/custom-posts/feed</pre>
				</li>
			</ul>

			<h5>This plug-in also provides additional features to fully integrate your custom post types:</h5>

			<ul>
				<li>Check "Include in Blog" to add that Post Type into the <a href="$blog_page" target="_blank">default blog page</a> results.<br />
					This will also add those posts into any query that doesn't explicitly specify a post_type.<br />
					<pre>query_posts('posts_per_page=5');</pre>
					</li>
				<li>Check "Include in RSS" to add that Post Type into the <a href="$rss_feed" target="_blank">default RSS feed</a>. This is useful where you want to have content
					aggregators grab all content, even those not shown on the default blog screen.</li>
				<li>Use the <a href="$menu_page">Appearance->Menus settings page</a> to add menu items for your Custom Post Archives (If current theme supports it).</li>
				<li>You can use a "+" seperated list of slugs to access multiple post-types<br />
					<pre>http://www.example.com/custom-posts+other-posts</pre></li>
				<li>Added post type support to wp_get_archives function.<br />
					<pre>wp_get_archives('type=monthly&limit=12&post_type=custom');</pre></li>
				<li>WordPress will now automatically display the associated post type on custom taxonomy archives.<br />
					<pre>http://www.example.com/custom-taxonomy</pre></li>
				<li>This plug-in automatically flushes the rewrite cache after modifications are detected - no need to go to the permalinks section.</li>
			</ul>
		</div>

		<h4 class="cpa-tab-link"><a href="#cpa-functions">Functions &amp; Filters</a></h4>

		<div id="cpa-functions" class="cpa-tab">
			<p>This plug-in adds the following global functions and filters for working with custom post types:</p>
			<h5>Functions</h5>

			<p>These global functions can be used to conditionally format content, and provide a way to link to the archives manually.</p>

			<ul>
				<li><pre>is_custom_archive({\$post_type = false});</pre></li>
				<li><pre>get_custom_archive_url(\$post_type);</pre></li>
				<li><pre>get_custom_archive_feed_url(\$post_type,\$feed = '');</pre></li>
				<li><pre>get_custom_archive_label({\$post_type = false});</pre></li>
				<li><pre>get_custom_archive_link(\$post_type);</pre></li>
				<li><pre>get_custom_archive_year_link(\$post_type = '',\$year = '');</pre></li>
				<li><pre>get_custom_archive_month_link(\$post_type = '',\$year = '',\$month = '');</pre></li>
				<li><pre>get_custom_archive_day_link(\$post_type = '',\$year = '',\$month = '',\$day = '');</pre></li>
				<li><pre>get_custom_archive_feed_link(\$post_type,\$anchor,\$feed = '');</pre></li>
			</ul>

			<p>The following functions are for advanced users. Each overrides the settings for the corresponding post_type in the plugin settings.</p>

			<ul>
				<li><pre>add_custom_archive(\$post_type,\$slug,{\$in_default = false,\$in_rss = false});</pre></li>
				<li><pre>remove_custom_archive(\$post_type);</pre></li>
				<li><pre>add_to_default_archive(\$post_type);</pre></li>
				<li><pre>remove_from_default_archive(\$post_type);</pre></li>
				<li><pre>reset_custom_archive_to_default(\$post_type);</pre></li>
			</ul>


			<h5>Filters</h5>

			<ul>
				<li><pre>cpa_rewrite_label</pre><br />
					Allows you to format the label returned by <pre>get_custom_archive_label()</pre>.</li>
				<li><pre>cpa_templates</pre><br />
					Allows you to provide or alter the array of templates Custom Post Archives searches for.</li>
			</ul>
		</div>

		<h4 class="cpa-tab-link"><a href="#cpa-templates">Templates &amp; Classes</a></h4>

		<div id="cpa-templates" class="cpa-tab">

			<p>The templates and classes implemented by this plug-in can be used to either modify the templating of your custom archives, or to style the existing output
			(if your theme supports it, of course).

			<h5>Templates</h5>

			<p>This plugin implements an extension of the <a href="http://codex.wordpress.org/Template_Hierarchy">WordPress templating framework</a>.
			In descending order, each custom archive will look for these files in your template directory:</p>

			<ul>
				<li><strong>tag-{post-type}.php</strong><br />
				Only if <pre>is_tag()</pre> and <pre>is_custom_archive({post-type})</pre></li>
				<li><strong>date-{post-type}.php</strong><br />
				Only if <pre>is_date()</pre> and <pre>is_custom_archive({post-type})</pre></li>
				<li><strong>author-{post-type}.php</strong><br />
				Only if <pre>is_author()</pre> and <pre>is_custom_archive({post-type})</pre></li>
				<li><strong>category-{post-type}.php</strong><br />
				Only if <pre>is_category()</pre> and <pre>is_custom_archive({post-type})</pre></li>
				<li><strong>archive-{post-type}.php</strong><br />
				Only if <pre>is_custom_archive({post-type}))</li>
				<li><strong>archive-custom.php</strong><br />
				Only if <pre>is_custom_archive())</li>
				<li><strong>archive.php</strong><br />
				Only if <pre>is_archive())</li>
				<li><strong>index.php</strong><br />
				The default catch-all</li>
			</ul>

			<p>In each of the above examples, <span class="pre">{post-type}</span> is replaced by the custom post type name,
			or an alphabetically sorted list of post types separated by a `_` for pages where multiple post types are shown.</p>

			<p>Custom Post Archives does not actually create any new files in your template directory, but it will use the archive.php file by default.</p>

			<h5>Classes</h5>
			<p>The following classes will be automatically added to the body of themes using the <span class="pre">body_class()</span> function:</p>

			<ul>
				<li><pre>blog-{post-type}</pre><br />
				Custom Archive</li>
				<li><pre>date-{post-type}</pre><br />
				Custom Date Archive</li>
				<li><pre>author-{post-type}</pre><br />
				Custom Author Archive</li>
				<li><pre>category-{post-type}</pre><br />
				Custom Category Archive</li>
			</ul>
		</div>

		<div class="foot">
			<p>If there are any errors you've encountered, or other features you'd like to see, I'm all ears. Feel free to <a href="mailto:requests.custompostarchives@gmail.com">send an email</a>, or <a href="http://wordpress.org/tags/custom-post-archives" target="_blank">hit the forums</a>!</p>
		</div>
	</div>
	<script type="text/javascript">
		jQuery(document).ready(function($){
			var tabs = $('#cpa-help .cpa-tab');
			var tabLinks = $('#cpa-help .cpa-tab-link').remove();

			tabs.first().before(tabLinks);

			tabs.wrapAll('<div class="cpa-tabs"/>');
			tabLinks.wrapAll('<div class="cpa-tab-links"/>');

			tabLinks.find('a').click(function(){
				$('#cpa-help .cpa-tab').hide();
				$($(this).attr('href')).show();

				$('#cpa-help .at').removeClass('at');
				$(this).parent().addClass('at');
				return false;
			}).first().click();
		});
	</script>
EOD;

	return $message;
	}
}
CPAConfig::get_instance();
?>