<?php

namespace Zotlabs\Module\Settings;

use App;
use Zotlabs\Lib\Apps;

class Nsfw {

	function post() {
		if(! local_channel())
			return;

		if(! Apps::addon_app_installed(local_channel(),'nsfw'))
			return;

		check_form_security_token_redirectOnErr('/settings/nsfw', 'settings_nsfw');

		set_pconfig(local_channel(),'nsfw','words',trim($_POST['nsfw-words']));
		$enable = ((x($_POST,'nsfw-enable')) ? intval($_POST['nsfw-enable']) : 0);
		$disable = 1-$enable;
		set_pconfig(local_channel(),'nsfw','disable', $disable);
		info( t('NSFW Settings saved.') . EOL);
	}

	function get() {
		if(! local_channel())
			return;

		if(! Apps::addon_app_installed(local_channel(), 'nsfw')) {
			//Do not display any associated widgets at this point
			App::$pdl = '';

			$o = '<b>NSFW App (Not Installed):</b><br>';
			$o .= t('Collapse content that contains predefined words');
			return $o;
		}

		$enable_checked = (intval(get_pconfig(local_channel(),'nsfw','disable')) ? false : 1);
		$words = get_pconfig(local_channel(),'nsfw','words');

		if(! $words)
			$words = 'nsfw,contentwarning,';

		$content .= '<div class="section-content-info-wrapper">';
		$content .= t('This app looks in posts for the words/text you specify below, and collapses any content containing those keywords so it is not displayed at inappropriate times, such as sexual innuendo that may be improper in a work setting. It is polite and recommended to tag any content containing nudity with #NSFW.  This filter can also match any other word/text you specify, and can thereby be used as a general purpose content filter.');
		$content .= '</div>';

		$content .= replace_macros(get_markup_template('field_input.tpl'), 
			[
				'$field' => ['nsfw-words', t('Comma separated list of keywords to hide'), $words, t('Word, /regular-expression/, lang=xx, lang!=xx')]
			]
		);

		$tpl = get_markup_template("settings_addon.tpl");

		$o .= replace_macros($tpl, array(
			'$action_url' => 'settings/nsfw',
			'$form_security_token' => get_form_security_token("settings_nsfw"),
			'$title' => t('NSFW Settings'),
			'$content'  => $content,
			'$baseurl'   => z_root(),
			'$submit'    => t('Submit'),
		));

		return $o;
	}
	
}
