<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;

require_once('addon/diaspora/util.php'); // needed for diaspora_build_relay_tags()
require_once('addon/diaspora/diaspora.php'); // needed for diaspora_init_relay()

class Diaspora extends Controller {

	function post() {

		if(! local_channel())
			return;

		if(! Apps::addon_app_installed(local_channel(), 'diaspora'))
			return;

		check_form_security_token_redirectOnErr('diaspora', 'diaspora');

		set_pconfig(local_channel(),'system','diaspora_public_comments',intval($_POST['dspr_pubcomment']));
		set_pconfig(local_channel(),'system','prevent_tag_hijacking',intval($_POST['dspr_hijack']));
		set_pconfig(local_channel(),'diaspora','sign_unsigned',intval($_POST['dspr_sign']));

		$followed = $_POST['dspr_followed'];
		$ntags = array();
		if($followed) {
			$tags = explode(',', $followed);
			foreach($tags as $t) {
				$t = trim($t);
				if($t)
					$ntags[] = $t;
			}
		}
		set_pconfig(local_channel(),'diaspora','followed_tags',$ntags);

		if(plugin_is_installed('statistics'))
			diaspora_build_relay_tags();
			
		info( t('Diaspora Protocol Settings updated.') . EOL);

	}

	function get() {

		if(! local_channel())
			return;

		$desc = t('The diaspora protocol does not support location independence. Connections you make within that network may be unreachable from alternate channel locations.');

		if(! Apps::addon_app_installed(local_channel(), 'diaspora')) {
			//Do not display any associated widgets at this point
			App::$pdl = '';

			$o = '<b>' . t('Diaspora Protocol App') . ' (' . t('Not Installed') . '):</b><br>';
			$o .= $desc;
			return $o;
		}

		diaspora_init_relay();

		$pubcomments  = get_pconfig(local_channel(),'system','diaspora_public_comments',1);
		$hijacking    = get_pconfig(local_channel(),'system','prevent_tag_hijacking');
		$signing      = get_pconfig(local_channel(),'diaspora','sign_unsigned');
		$followed     = get_pconfig(local_channel(),'diaspora','followed_tags');
		if(is_array($followed))
			$hashtags = implode(',',$followed);
		else
			$hashtags = '';

		$sc = '<div class="section-content-info-wrapper">' . $desc . '</div>';

		$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
			'$field'	=> array('dspr_pubcomment', t('Allow any Diaspora member to comment on your public posts'), $pubcomments, '', $yes_no),
		));

		$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
			'$field'	=> array('dspr_hijack', t('Prevent your hashtags from being redirected to other sites'), $hijacking, '', $yes_no),
		));

		$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
			'$field'	=> array('dspr_sign', t('Sign and forward posts and comments with no existing Diaspora signature'), $signing, '', $yes_no),
		));

		if(plugin_is_installed('statistics')) {
			$sc .= replace_macros(get_markup_template('field_input.tpl'), array(
				'$field'	=> array('dspr_followed', t('Followed hashtags (comma separated, do not include the #)'), $hashtags, '')
			));
		}

		$tpl = get_markup_template("settings_addon.tpl");

		$o .= replace_macros($tpl, array(
			'$action_url' => 'diaspora',
			'$form_security_token' => get_form_security_token("diaspora"),
			'$title' => t('Diaspora Protocol'),
			'$content'  => $sc,
			'$baseurl'   => z_root(),
			'$submit'    => t('Submit'),
		));

		return $o;


	}

}
