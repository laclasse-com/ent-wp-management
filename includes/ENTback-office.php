<?php
// create custom plugin settings menu
add_action('admin_menu', 'ENT_WP_Mngnt_create_menu');

function ENT_WP_Mngnt_create_menu() {

	//create new top-level menu
	add_menu_page('Options du plugin ENT-WP-Management', NOM_ENT, 'administrator', __FILE__, 'ENT_WP_Mngnt_settings_page',plugins_url('/images/icon.png', __FILE__));

	//call register settings function
	add_action( 'admin_init', 'ENT_WP_Mgmnt_register_mysettings' );
}


function ENT_WP_Mgmnt_register_mysettings() {
	//register our settings
	register_setting( 'cas-sso-settings-group', 'cas_server_name' );
	register_setting( 'baw-settings-group', 'some_other_option' );
	register_setting( 'baw-settings-group', 'option_etc' );
}

function ENT_WP_Mngnt_settings_page() {
?>
<div class="wrap">
<h2>ENT - WP - Management :: <?php echo NOM_ENT; ?></h2>

<form method="post" action="options.php">
    <?php settings_fields( 'cas-sso-settings-group' ); ?>
    <table class="form-table">
        <tr valign="top">
        <th scope="row">Nom du serveur CAS</th>
        <td><input type="text" name="cas_server_name" value="<?php echo get_option('cas_server_name'); ?>" /></td>
        </tr>
         
        <tr valign="top">
        <th scope="row">Nom du service de synchronisation des blogs dans l'ENT</th>
        <td><input type="text" name="some_other_option" value="<?php echo get_option('some_other_option'); ?>" /></td>
        </tr>
        
        <tr valign="top">
        <th scope="row">Options, Etc.</th>
        <td><input type="text" name="option_etc" value="<?php echo get_option('option_etc'); ?>" /></td>
        </tr>
    </table>
    
    <p class="submit">
    <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
    </p>

</form>
</div>
<?php } ?>
