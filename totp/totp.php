<?php
/**
 * Name: TOTP
 * Description: TOTP two-factor authentication
 * Version: 0.1
 * Depends: Core
 * Recommends: None
 * Category: authentication
 * Author: Pete Yadlowsky <pm@yadlowsky.us>
 * Maintainer: Pete Yadlowsky <pm@yadlowsky.us>
 */


function totp_load(){
	register_hook('construct_page', 'addon/totp/totp.php', 'totp_construct_page');
	register_hook('feature_settings', 'addon/totp/totp.php', 'totp_settings');
	register_hook('feature_settings_post', 'addon/totp/totp.php', 'totp_settings_post');

}


function totp_unload(){
	unregister_hook('construct_page', 'addon/totp/totp.php', 'totp_construct_page');
	unregister_hook('feature_settings', 'addon/totp/totp.php', 'totp_settings');
	unregister_hook('feature_settings_post', 'addon/totp/totp.php', 'totp_settings_post');
}



function totp_construct_page(&$a, &$b){
	if(! local_channel())
		return;

	$some_setting = get_pconfig(local_channel(), 'totp','some_setting');

	// Whatever you put in settings, will show up on the left nav of your pages.
	$b['layout']['region_aside'] .= '<div>' . htmlentities($some_setting) .  '</div>';

}



function totp_settings_post($a,$s) {
	if(! local_channel())
		return;

	set_pconfig( local_channel(), 'totp', 'some_setting', $_POST['some_setting'] );

}

function totp_settings(&$a,&$s) {
	$id = local_channel();
	if (! $id)
		return;

	$some_setting = get_pconfig( $id, 'totp', 'some_setting');

	$sc = replace_macros(get_markup_template('field_input.tpl'), array(
				     '$field'	=> array('some_setting', t('Some setting'), 
							 $some_setting, 
							 t('A setting'))));
	$s .= replace_macros(get_markup_template('generic_addon_settings.tpl'), array(
				     '$addon' 	=> array('totp',
							 t('TOTP Settings'), '', 
							 t('Submit')),
				     '$content'	=> $sc));

}
