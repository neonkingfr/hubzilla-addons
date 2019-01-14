<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;

class Ljpost extends Controller {

	function post() {

		if(! local_channel())
			return;

		if(! Apps::addon_app_installed(local_channel(),'ljpost'))
			return;

		check_form_security_token_redirectOnErr('ljpost', 'ljpost');

		set_pconfig(local_channel(),'ljpost','post_by_default',intval($_POST['lj_bydefault']));
		set_pconfig(local_channel(),'ljpost','lj_username',trim($_POST['lj_username']));
		set_pconfig(local_channel(),'ljpost','lj_password',z_obscure(trim($_POST['lj_password'])));
	}


	function get() {

		if(! local_channel())
			return;

		if(! Apps::addon_app_installed(local_channel(), 'ljpost')) {
			//Do not display any associated widgets at this point
			App::$pdl = '';

			$o = '<b>Livejournal Crosspost Connector App (Not Installed):</b><br>';
			$o .= t('Relay public posts to Livejournal');
			return $o;
		}

		/* Get the current state of our config variables */

		$def_enabled = get_pconfig(local_channel(),'ljpost','post_by_default');

		$def_checked = (($def_enabled) ? 1 : false);

		$lj_username = get_pconfig(local_channel(), 'ljpost', 'lj_username');
		$lj_password = z_unobscure(get_pconfig(local_channel(), 'ljpost', 'lj_password'));


		/* Add some HTML to the existing form */

		$sc .= replace_macros(get_markup_template('field_input.tpl'), array(
			'$field'	=> array('lj_username', t('Livejournal username'), $lj_username, '')
		));

		$sc .= replace_macros(get_markup_template('field_password.tpl'), array(
			'$field'	=> array('lj_password', t('Livejournal password'), $lj_password, '')
		));

		$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
			'$field'	=> array('lj_bydefault', t('Post to Livejournal by default'), $def_checked, '', array(t('No'),t('Yes'))),
		));

		$tpl = get_markup_template("settings_addon.tpl");

		$o = replace_macros($tpl, array(
			'$action_url' => 'ljpost',
			'$form_security_token' => get_form_security_token("ljpost"),
			'$title' => t('Livejournal Crosspost Connector'),
			'$content'  => $sc,
			'$baseurl'   => z_root(),
			'$submit'    => t('Submit'),
		));

		return $o;
	}
}
