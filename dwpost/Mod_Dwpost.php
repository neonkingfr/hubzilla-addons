<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;

class Dwpost extends Controller {

	function post() {
		if(! local_channel())
			return;

		if(! Apps::addon_app_installed(local_channel(), 'dwpost'))
			return;

		check_form_security_token_redirectOnErr('dwpost', 'dwpost');

		set_pconfig(local_channel(),'dwpost','post_by_default',intval($_POST['dw_bydefault']));
		set_pconfig(local_channel(),'dwpost','dw_username',trim($_POST['dw_username']));
		set_pconfig(local_channel(),'dwpost','dw_password',z_obscure(trim($_POST['dw_password'])));

	        info( t('Dreamwidth Crosspost Connector Settings saved.') . EOL);
	}

	function get() {

		if(! local_channel())
			return;

		if(! Apps::addon_app_installed(local_channel(), 'dwpost')) {
			//Do not display any associated widgets at this point
			App::$pdl = '';

			$o = '<b>Dreamwidth Crosspost Connector App (Not Installed):</b><br>';
			$o .= t('Relay public postings to Dreamwidth');
			return $o;
		}

		$def_enabled = get_pconfig(local_channel(),'dwpost','post_by_default');

		$def_checked = (($def_enabled) ? 1 : false);

		$dw_username = get_pconfig(local_channel(), 'dwpost', 'dw_username');
		$dw_password = z_unobscure(get_pconfig(local_channel(), 'dwpost', 'dw_password'));


		/* Add some HTML to the existing form */

		$sc .= replace_macros(get_markup_template('field_input.tpl'), array(
			'$field'	=> array('dw_username', t('Dreamwidth username'), $dw_username, '')
		));

		$sc .= replace_macros(get_markup_template('field_password.tpl'), array(
			'$field'	=> array('dw_password', t('Dreamwidth password'), $dw_password, '')
		));

		$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
			'$field'	=> array('dw_bydefault', t('Post to Dreamwidth by default'), $def_checked, '', array(t('No'),t('Yes'))),
		));

		$tpl = get_markup_template("settings_addon.tpl");

		$o = replace_macros($tpl, array(
			'$action_url' => 'dwpost',
			'$form_security_token' => get_form_security_token('dwpost'),
			'$title' => t('Dreamwidth Crosspost Connector'),
			'$content'  => $sc,
			'$baseurl'   => z_root(),
			'$submit'    => t('Submit'),
		));

		return $o;

	}
}
