<?php

/*
 * Adds Meta Box to NavMenu Page
 * - Implements a custom menu item to add navigation to menus
 */
function add_custompostarchives_navmenu()
{
	add_meta_box( "add-custompostarchives", __("Custom Post Archives"), 'wp_nav_menu_item_custompostarchives_meta_box', 'nav-menus', 'side', 'default' );
}
add_action('admin_init','add_custompostarchives_navmenu');

/*
 * Code for Meta Box
 */
function wp_nav_menu_item_custompostarchives_meta_box($object)
{
	global $_nav_menu_placeholder, $nav_menu_selected_id;
	$_nav_menu_placeholder = 0 > $_nav_menu_placeholder ? $_nav_menu_placeholder - 1 : -1;
	
	$rewrites = CustomPostArchives::get_manager()->get_rewrites();
	
	?>
	<div class="custompostarchivesdiv" id="custompostarchivesdiv">

			<input type="hidden" value="custom" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-type]" />
            <?php foreach($rewrites as $post_type => $rewrite){
				if($rewrite['base'] == "") continue;
				$post_type_object = get_post_type_object( $post_type );
				?>
                <p id="menu-item-url-wrap">
                    <label class="menu-item-title" for="custom-post-archive[<?php echo $post_type; ?>]">
                        <img class="waiting" src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" alt="" />
						<input
                        	id="custom-post-archive[<?php echo $post_type; ?>]"
                            name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-type][<?php echo $post_type; ?>]"
                            type="checkbox"
                            class="menu-item-checkbox"
                            value="<?php echo CustomPostArchives::get_archive_url($post_type); ?>"
                        	/>
                        <span><?php echo($post_type_object->labels->name); ?></span>
                    </label>
                </p>
            <?php } ?>

		<p class="button-controls">
			<span class="add-to-menu">
				<input
                    id="submit-custompostarchivesdiv"
                    name="add-custom-menu-item"
                	type="submit"<?php disabled( $nav_menu_selected_id, 0 ); ?>
                    class="button-secondary submit-add-to-menu"
                    value="<?php esc_attr_e('Add to Menu'); ?>"
                    />
			</span>
		</p>

	</div><!-- /.customlinkdiv -->
    <script type="text/javascript">
	jQuery(function($){
		$('#submit-custompostarchivesdiv').click(function(){
			var api = wpNavMenu;
			
			$('#custompostarchivesdiv input:checked').each(function(){
				url = $(this).val();
				label = $(this).next('span').text();
				processMethod = api.addMenuItemToBottom;
				
				$(this).prev('.waiting').show();
				
				callback = function(){
					// Remove the ajax spinner
					$('#custompostarchivesdiv .waiting').hide();
					// Set form back to defaults
					$('#custompostarchivesdiv input:checked').attr('checked',false);
				};
		
				api.addItemToMenu({
					'-1': {
						'menu-item-type': 'custom',
						'menu-item-url': url,
						'menu-item-title': label
					}
				}, processMethod, callback);
			});
		});
	});

	</script>
	<?php
}
?>