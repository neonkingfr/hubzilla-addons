<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;
use Zotlabs\Access\AccessList;

require_once(dirname(__FILE__).'/chess.php');

class Chess extends Controller {

	function init() {
		$channel = App::get_channel();
		if(argc() === 1 && $channel['channel_address']) {
			goaway(z_root() . '/chess/' . $channel['channel_address'] );
		}
		if(argc() === 3 && argv(2) === 'new' && $channel['channel_address'] !== argv(1)) {
			goaway(z_root() . '/chess/');
		}
	}
	function post() {

		/**
		 * @brief This function provides the API endpoints, primarily called by the
		 * JavaScript functions via $.post() calls.
		 *
		 * @return json JSON-formatted structures with a "status" indicator for success
		 * as well as other requested data
		 */

		if (argc() > 1) {
			switch (argv(1)) {
				// API: /chess/settings
				// Updates game settings for the observer
				case 'settings':
					$observer = App::get_observer();
					$settings = (x($_POST, 'settings') ? $_POST['settings'] : null );
					$settings = json_decode($settings, true);
					if (!isset($settings['notify_enabled'])) {
						json_return_and_die(array('errormsg' => 'Invalid settings', 'status' => false));
					}
					$notify_enable = intval($settings['notify_enabled']);
					set_xconfig($observer['xchan_hash'], 'chess', 'notifications', $notify_enable);
					json_return_and_die(array('status' => true));
				// API: /chess/resume
				// Resumes a game specified by "game_id" allowing further moves
				case 'resume':
					$observer = App::get_observer();
					$game_id = (x($_POST, 'game_id') ? $_POST['game_id'] : '' );
					$g = chess_get_game($game_id);
					if (!$g['status']) {
						json_return_and_die(array('errormsg' => 'Invalid game', 'status' => false));
					}
					// Verify that observer is a valid player
					$game = json_decode($g['game']['obj'], true);
					if (!in_array($observer['xchan_hash'], $game['players'])) {
						json_return_and_die(array('errormsg' => 'You are not a valid player', 'status' => false));
					}
					$success = chess_resume_game($g['game']);
					if (!$success) {
						json_return_and_die(array('errormsg' => 'Error resuming game', 'status' => false));
					} else {
						json_return_and_die(array('status' => true));
					}
				// API: /chess/end
				// Ends a game specified by "game_id" preventing further moves
				case 'end':
					$observer = App::get_observer();
					$game_id = (x($_POST, 'game_id') ? $_POST['game_id'] : '' );
					$g = chess_get_game($game_id);
					if (!$g['status']) {
						json_return_and_die(array('errormsg' => 'Invalid game', 'status' => false));
					}
					// Verify that observer is a valid player
					$game = json_decode($g['game']['obj'], true);
					if (!in_array($observer['xchan_hash'], $game['players'])) {
						json_return_and_die(array('errormsg' => 'You are not a valid player', 'status' => false));
					}
					$success = chess_end_game($g['game']);
					if (!$success) {
						json_return_and_die(array('errormsg' => 'Error ending game', 'status' => false));
					} else {
						json_return_and_die(array('status' => true));
					}
				// API: /chess/delete
				// Deletes a game specified by "game_id"
				case 'delete':
					if (!local_channel()) {
						json_return_and_die(array('errormsg' => 'Must be local channel.', 'status' => false));
					}
					$channel = App::get_channel();
					$game_id = (x($_POST, 'game_id') ? $_POST['game_id'] : '' );
					$d = chess_delete_game($game_id, $channel);
					if (!$d['status']) {
						json_return_and_die(array('errormsg' => 'Error deleting game', 'status' => false));
					} else {
						json_return_and_die(array('status' => true));
					}
				// API: /chess/revert
				// Reverts a game specified by "game_id" to a previous board position
				// specified by the "mid" of the child post of the original game post
				// in the item table
				// TODO: Determine why the board position in the game item is not actually
				// being reverted
				case 'revert':
					$observer = App::get_observer();
					$game_id = $_POST['game_id'];
					$g = chess_get_game($game_id);
					if (!$g['status']) {
						json_return_and_die(array('errormsg' => 'Invalid game', 'status' => false));
					}
					// Verify that observer is a valid player
					$game = json_decode($g['game']['obj'], true);
					if (!in_array($observer['xchan_hash'], $game['players'])) {
						json_return_and_die(array('errormsg' => 'You are not a valid player', 'status' => false));
					}
					$active = ($game['active'] === $observer['xchan_hash'] ? true : false);
					if (!$active) {
						json_return_and_die(array('errormsg' => 'It is not your turn', 'status' => false));
					}
					$r = chess_revert_position($g['game'], $observer, $_POST['mid']);
					if (!$r['status']) {
						json_return_and_die(array('errormsg' => 'Error reverting game', 'status' => false));
					}
					json_return_and_die(array('status' => true));
				// API: /chess/history
				// Retrieves all the board positions for a game in order to populate the
				// history viewer in the control panel
				case 'history':
					$observer = App::get_observer();
					$game_id = $_POST['game_id'];
					$g = chess_get_game($game_id);
					if (!$g['status']) {
						json_return_and_die(array('errormsg' => 'Invalid game', 'status' => false));
					}
					// Verify that observer is a valid player
					$game = json_decode($g['game']['obj'], true);
					if (!in_array($observer['xchan_hash'], $game['players']) && !$game['public_visible']) {
						json_return_and_die(array('errormsg' => 'You are not a valid player', 'status' => false));
					}
					//$player = array_search($observer['xchan_hash'], $game['players']);
					$h = chess_get_history($g['game']);
					if (!$h['status']) {
						json_return_and_die(array('errormsg' => 'Error retrieving game history', 'status' => false));
					}
					json_return_and_die(array('history' => $h['history'], 'status' => true));
				// API: /chess/update
				// Updates a game specified by "game_id" with a new board position specified
				// by "newPosFEN" in FEN-format
				case 'update':
					$observer = App::get_observer();
					$game_id = $_POST['game_id'];
					$g = chess_get_game($game_id);
					if (!$g['status']) {
						json_return_and_die(array('errormsg' => 'Invalid game', 'status' => false));
					}
					// Verify that observer is a valid player
					$game = json_decode($g['game']['obj'], true);
					if (!in_array($observer['xchan_hash'], $game['players'])) {
						if($game['public_visible']) {
							json_return_and_die(array('position' => $game['position'], 'ended' => $game['ended'], 'active' => $game['active'], 'status' => true));
						} else {
							json_return_and_die(array('errormsg' => 'You are not a valid player', 'status' => false));
						}
					}
					$player = array_search($observer['xchan_hash'], $game['players']);
					$active = ($game['active'] === $game['players'][$player] ? true : false);
					json_return_and_die(array('position' => $game['position'], 'myturn' => $active, 'ended' => $game['ended'], 'enforce_legal_moves' => $game['enforce_legal_moves'], 'status' => true));
				// API: /chess/move
				// Adds a new board position by creating a child post for the original
				// game item.
				case 'move':
					$observer = App::get_observer();
					$game_id = $_POST['game_id'];
					$newPosFEN = $_POST['newPosFEN'];
					$g = chess_get_game($game_id);
					if (!$g['status']) {
						notice(t('Invalid game.') . EOL);
						json_return_and_die(array('errormsg' => 'Invalid game ID', 'status' => false));
					}
					// Verify that observer is a valid player
					$game = json_decode($g['game']['obj'], true);
					if (!in_array($observer['xchan_hash'], $game['players'])) {
						notice(t('You are not a player in this game.') . EOL);
						goaway('/chess');
					}
					$player = array_search($observer['xchan_hash'], $game['players']);
					$color = $game['colors'][$player];
					$active = ($game['active'] === $observer['xchan_hash'] ? true : false);
					if (!$active) {
						json_return_and_die(array('errormsg' => 'It is not your turn', 'status' => false));
					}
					if (x($game, 'ended') && intval($game['ended']) === 1) {
						json_return_and_die(array('errormsg' => 'The game is over', 'status' => false));
					}
					$move = chess_make_move($observer, $newPosFEN, $g['game']);
					if ($move['status']) {
						$active_xchan = ($game['players'][0] === $observer['xchan_hash'] ? $game['players'][1] : $game['players'][0]);
						if (chess_set_position(chess_get_game($game_id)['game'], $newPosFEN)) {
							chess_set_active(chess_get_game($game_id)['game'], $active_xchan);
						}
						json_return_and_die(array('status' => true));
					} else {
						json_return_and_die(array('errormsg' => 'Move failed', 'status' => false));
					}
					// API: chess/toggle_legal_moves
					// Toggles the enforcement of legal moves for a game
				case 'toggle_legal_moves':
					if (!local_channel()) {
						json_return_and_die(array('errormsg' => 'Must be local channel.', 'status' => false));
					}
					$channel = App::get_channel();
					$game_id = (x($_POST, 'game_id') ? $_POST['game_id'] : '' );
					$g = chess_get_game($game_id);
					if (!$g['status']) {
						json_return_and_die(array('errormsg' => 'Invalid game', 'status' => false));
					}
					// Verify that observer is a valid player
					$game = json_decode($g['game']['obj'], true);
					if ($channel['channel_hash'] !== $g['game']['owner_xchan']) {
						json_return_and_die(array('errormsg' => 'You must be the game owner', 'status' => false));
					}
					$d = chess_toggle_legal_moves($g);
					if (!$d['status']) {
						json_return_and_die(array('errormsg' => 'Error toggling legal move enforcement', 'status' => false));
					} else {
						json_return_and_die(array('enforce_legal_moves' => $d['enforce_legal_moves'], 'status' => true));
					}
				default:
					break;
			}
		}
		if (argc() > 2) {
			switch (argv(2)) {
				// API: /chess/[channelname]/new/
				// This endpoint handles the new game form submission and creates a new
				// game between two channels specified by the standard ACL
				case 'new':
					if (!local_channel()) {
						notice(t('You must be a local channel to create a game.') . EOL);
						return;
					}
					// Ensure ACL specifies exactly one other channel
					$channel = App::get_channel();
					$acl = new AccessList($channel);
					$acl->set_from_array($_REQUEST);
					$perms = $acl->get();
					$allow_cid = expand_acl($perms['allow_cid']);
					$valid = 0;
					if (count($allow_cid) >= 1) {
						foreach ($allow_cid as $allow) {
							if ($allow == $channel['channel_hash'])
								continue;
							$valid ++;
						}
					}
					if ($valid != 1) {
						notice(t('You must select one opponent that is not yourself.') . EOL);
						return;
					} else {
						//info(t('Creating new game...') . EOL);
						// Get the game owner's color choice
						$color = '';
						if ($_POST['color'] === 'white' || $_POST['color'] === 'black') {
							$color = $_POST['color'];
						} else {
							$randomValue = mt_rand();
							$color = (($randomValue % 2 == 0) ? 'white' : 'black');
							info(t('Random color chosen.') . EOL);
						}
						$enforce_legal_moves = isset($_POST['playmode']) ? 1 : 0;
						$public_visible = isset($_POST['public_visible']) ? 1 : 0;
						$game = chess_create_game($channel, $color, $acl, $enforce_legal_moves, $public_visible);
						if ($game['status']) {
							goaway('/chess/' . $channel['channel_address'] . '/' . $game['item']['resource_id']);
						} else {
							notice(t('Error creating new game.') . EOL);
						}
						return;
					}
				default:
					break;
			}
		}
	}

	function get() {
		// Include the custom CSS and JavaScript necessary for the chess board

		head_add_css('/addon/chess/view/css/chessboard.css');
		head_add_js('/addon/chess/view/js/chess.js');
		head_add_js('/addon/chess/view/js/chessboard.js');

		// If the user is not a local channel, then they must use a URL like /chess/localchannel
		// to specify which local channel "chess host" they are visiting
		$which = null;
		if (argc() > 1) {
			$which = argv(1);
			$user = q("select channel_id from channel where channel_address = '%s' AND channel_removed = 0 limit 1",
				dbesc($which)
			);

			if (!$user) {
				notice(t('Requested channel is not available.') . EOL);
				App::$error = 404;
				return;
			} else {
				if(! Apps::addon_app_installed(intval($user['channel_id']), 'chess')) {
					notice(t('Chess not installed.') . EOL);
					App::$error = 404;
					return;
				}
			}
		}

		if (!$which) {
			if (local_channel()) {
				$channel = App::get_channel();
				if ($channel && $channel['channel_address'])
					$which = $channel['channel_address'];
			}
		}
		if (!$which) {
			notice(t('You must select a local channel /chess/channelname') . EOL);
			return;
		} else {
			$user = q("select channel_id from channel where channel_address = '%s' AND channel_removed = 0 limit 1",
				dbesc($which)
			);
			if(! Apps::addon_app_installed(intval($user['channel_id']), 'chess')) {
				notice(t('Chess not installed.') . EOL);
				App::$error = 404;
				return;
			}
		}

		if (argc() > 2) {
			switch (argv(2)) {
				case 'new':
					if (!local_channel()) {
						notice(t('You must be logged in to see this page.') . EOL);
						return;
					}
					$acl = new AccessList(App::get_channel());
					$channel_acl = $acl->get();

					require_once('include/acl_selectors.php');

					$channel = App::get_channel();
					$o = replace_macros(get_markup_template('chess_new.tpl', 'addon/chess'), array(
						'$acl' => populate_acl($channel_acl, false, '',
						  'Select "Custom selection" and choose a <i>single</i> channel '
						  . 'to select your opponent by pressing the "Show" button for '
						  . 'the desired channel.'),
						'$allow_cid' => '',
						'$allow_gid' => '',
						'$deny_cid' => acl2json($channel['channel_hash']),
						'$deny_gid' => '',
						'$channel' => $channel['channel_address']
					));
					return $o;
				default:
					// argv(2) is the resource_id for an existing game
					// argv(1) should be the owner channel of the game
					$owner = argv(1);
					$hash = q("select channel_hash from channel where channel_address = '%s' "
						. "and channel_removed = 0  limit 1",
						dbesc($owner)
					);
					$owner_hash = $hash[0]['channel_hash'];
					$game_id = argv(2);
					$observer = App::get_observer();
					$g = chess_get_game($game_id);
					if (!$g['status'] || $g['game']['owner_xchan'] !== $owner_hash) {
						notice(t('Invalid game.') . EOL);
						return;
					}
					// Verify that observer is a valid player
					$game = json_decode($g['game']['obj'], true);
					$game_ended = ((!x($game, 'ended') || $game['ended'] === 0) ? 0 : 1);
					$white_xchan_hash = $game['players'][array_search('white', $game['colors'])];
					$r = q("SELECT xchan_name FROM xchan WHERE xchan_hash = '%s' LIMIT 1",
						dbesc($white_xchan_hash)
					);
					if($r) {
						$whiteplayer = $r[0]['xchan_name'];
					} else {
						$whiteplayer = 'White player';
					}

					$black_xchan_hash = $game['players'][array_search('black', $game['colors'])];
					$r = q("SELECT xchan_name FROM xchan WHERE xchan_hash = '%s' LIMIT 1",
						dbesc($black_xchan_hash)
					);
					if($r) {
						$blackplayer = $r[0]['xchan_name'];
					} else {
						$blackplayer = 'Black player';
					}
					if (!in_array($observer['xchan_hash'], $game['players'])) {
						if($game['public_visible']) {
							$o = replace_macros(get_markup_template('chess_game_spectator.tpl', 'addon/chess'), array(
								'$game_id' => $game_id,
								'$position' => $game['position'],
								'$ended' => $game_ended,
								// TODO: populate player information
								'$whiteplayer' => $whiteplayer,
								'$white_xchan_hash' => $white_xchan_hash,
								'$blackplayer' => $blackplayer,
								'$black_xchan_hash' => $black_xchan_hash,
								'$active' => $game['active']
							));
							return $o;
						} else {
							notice(t('You are not a player in this game.') . EOL);
							goaway('/chess');
						}
					}
					$player = array_search($observer['xchan_hash'], $game['players']);
					$color = $game['colors'][$player];
					$active = ($game['active'] === $game['players'][$player] ? true : false);
					$enforce_legal_moves = ((!x($game, 'enforce_legal_moves') || $game['enforce_legal_moves'] === 0) ? 0 : 1);
					$notify = intval(get_xconfig($observer['xchan_hash'], 'chess', 'notifications'));
					$o = replace_macros(get_markup_template('chess_game.tpl', 'addon/chess'), array(
						'$myturn' => ($active ? 'true' : 'false'),
						'$active' => $active,
						'$color' => $color,
						'$game_id' => $game_id,
						'$position' => $game['position'],
						'$ended' => $game_ended,
						'$notifications' => $notify,
						'$enforce_legal_moves' => $enforce_legal_moves
					));
					// TODO: Create settings panel to set the board size and eventually the board theme
					// and other customizations
					return $o;
			}
		}
		// If the URL was simply /chess, then if the script reaches this point the
		// user is a local channel, so load any games they may have as well as a board
		// they can move pieces around on without storing the moves anywhere

		if(! Apps::addon_app_installed(local_channel(), 'chess')) {
			App::$error = 404;
			return "<h2>".t('Page not found.')."</h2>";
		}
		
		$o .= replace_macros(get_markup_template('chess.tpl', 'addon/chess'), array(
			'$color' => 'white'
		));
		return $o;
	}

}
