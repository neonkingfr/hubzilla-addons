<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;

class Flattrwidget extends Controller {

	function post() {

		if(! local_channel())
			return;

		if(! Apps::addon_app_installed(local_channel(), 'flattrwidget'))
			return;

		check_form_security_token_redirectOnErr('flattrwidget', 'flattrwidget');

		$c = App::get_channel();

		set_pconfig( local_channel(), 'flattrwidget', 'align', $_POST['flattrwidget-align'] );
		set_pconfig( local_channel(), 'flattrwidget', 'sd', $_POST['flattrwidget-static'] );

		$thing = $_POST['flattrwidget-thing'];
		if ($thing == '') {
			$thing = z_root().'/channel/'.$c['channel_address'];
		}

		set_pconfig( local_channel(), 'flattrwidget', 'thing', $thing);
		set_pconfig( local_channel(), 'flattrwidget', 'user', $_POST['flattrwidget-user']);

		$ftitle = $_POST['flattrwidget-thingtitle'];
		if ($ftitle == '') {
			$ftitle = $c['channel_name'].' on The Hubzilla';
		}

		set_pconfig( local_channel(), 'flattrwidget', 'title', $ftitle);

		info(t('Flattr widget settings updated.').EOL);
	}

	function get() {
		$id = local_channel();
		if (! $id)
			return;

		if(! Apps::addon_app_installed(local_channel(), 'flattrwidget')) {
			//Do not display any associated widgets at this point
			App::$pdl = '';

			$o = '<b>' . t('Flattr Widget App') . ' (' . t('Not Installed') . '):</b><br>';
			$o .= t('Add a Flattr button to your channel page');
			return $o;
		}

		$lr = get_pconfig( $id, 'flattrwidget', 'align');
		$sd = get_pconfig( $id, 'flattrwidget', 'sd');
		$thing = get_pconfig( $id, 'flattrwidget', 'thing');
		$user = get_pconfig( $id, 'flattrwidget', 'user');
		$ftitle = get_pconfig( $id, 'flattrwidget', 'title');

		$sc .= replace_macros(get_markup_template('field_input.tpl'), array(
			'$field'	=> array('flattrwidget-user', t('Flattr user'), $user, '')
		));

		$sc .= replace_macros(get_markup_template('field_input.tpl'), array(
			'$field'	=> array('flattrwidget-thing', t('URL of the Thing to flattr'), $thing, t('If empty channel URL is used'))
		));

		$sc .= replace_macros(get_markup_template('field_input.tpl'), array(
			'$field'	=> array('flattrwidget-thingtitle', t('Title of the Thing to flattr'), $ftitle, t('If empty "channel name on The Hubzilla" will be used'))
		));

		$sc .= replace_macros(get_markup_template('field_select.tpl'), array(
			'$field'	=> array('flattrwidget-static', t('Static or dynamic flattr button'), $sd, '', array('static'=>t('static'), 'dynamic'=>t('dynamic')))
		));

		$sc .= replace_macros(get_markup_template('field_select.tpl'), array(
			'$field'	=> array('flattrwidget-align', t('Alignment of the widget'), $lr, '', array('aside'=>t('left'), 'right_aside'=>t('right')))
		));

		$tpl = get_markup_template("settings_addon.tpl");

		$o .= replace_macros($tpl, array(
			'$action_url' => 'flattrwidget',
			'$form_security_token' => get_form_security_token("flattrwidget"),
			'$title' => t('Flattr Widget'),
			'$content'  => $sc,
			'$baseurl'   => z_root(),
			'$submit'    => t('Submit'),
		));

		return $o;

	}

}
