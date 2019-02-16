<?php

/**
 *
 * Name: Chess
 * Description: Hubzilla plugin for decentralized, identity-aware chess games powered by chessboard.js
 * Version: 0.9.1
 * Author: Andrew Manning <https://grid.reticu.li/channel/andrewmanning/>
 * MinVersion: 3.8.0
 *
 */
 use Zotlabs\Lib\Apps;
 use Zotlabs\Extend\Hook;
 use Zotlabs\Extend\Route;


define('ACTIVITY_OBJ_CHESSGAME', NAMESPACE_ZOT . '/activity/chessgame');

/**
 * @brief Return the current plugin version
 *
 * @return string Current plugin version
 */
function chess_get_version() {
	return '0.9.1';
}

function chess_load() {
	// Control the page composition by loading a custom layout
	Hook::register('load_pdl', 'addon/chess/chess.php', 'chess_load_pdl');
	Route::register('addon/chess/Mod_Chess.php','chess');
}

function chess_unload() {
	Hook::unregister('load_pdl', 'addon/chess/chess.php', 'chess_load_pdl');
	Route::unregister('addon/chess/Mod_Chess.php','chess');
}

function chess_install() {
	info('Chess plugin installed successfully');
	logger('Chess plugin installed successfully');
}

function chess_uninstall() {
	info('Chess plugin uninstalled successfully');
	logger('Chess plugin uninstalled successfully');
}

/**
 * @brief Returns game ID from second URL argument
 * @return string
 */
function chess_game_id_from_url() {
	if (argc() > 2 && argv(2) !== 'new') {
		return(argv(2));
	} else {
		return null;
	}
}

/**
 * @brief Returns game ID from second URL argument
 * array|boolean
 *   - array with channel entry
 *   - false if no channel with $nick was found
 */
function chess_owner_from_url() {
	if (argc() > 1) {
		return channelx_by_nick(argv(1));
	} else {
		return false;
	}
}

/**
 * @brief Returns information about the observer's role
 * @param array $owner Game owner channel info
 * @param string $game_id game ID
 * @return array
 *         - role => null, 'spectator', 'opponent', 'owner'
 */
function chess_observer_role() {

	$game_id = chess_game_id_from_url();
	$owner = chess_owner_from_url();
	$observer = App::get_observer();
	$role = null;
	// /chess/channel_name/*
	if ($observer !== null && $observer['xchan_hash'] === $owner['xchan_hash']) {
		$role = 'owner';
	}
	// /chess/channel_name/game_id
	// If there is a game ID and the channel owner is not the observer
	if ($game_id && !$role) {
		$g = chess_get_game($game_id);
		if ($g['status']) {
			$game = json_decode($g['game']['obj'], true);
			if (!in_array($observer['xchan_hash'], $game['players'])) {
				// Observer is not a game player
				if ($game['public_visible']) {
					$role = 'spectator';
				}
			} else {
				// Observer is the opponent
				$role = 'opponent';
			}
		}
	}
	return(array('role' => $role, 'observer' => $observer));
}

/**
 * @brief Defines the widget for the page layout, providing the game controls
 *
 * @return string HTML content of the aside region
 */
function widget_chess_controls() {

	$owner = chess_owner_from_url();

	if(! Apps::addon_app_installed(intval($owner['channel_id']), 'chess'))
		return;

	$game_id = chess_game_id_from_url();
	$obs_role = chess_observer_role();
	$observer = $obs_role['observer'];
	$role = $obs_role['role'];
	$o = '';
	switch ($role) {
		case 'owner':
			$obs_nick = explode('@', $observer['xchan_addr'])[0];
			$g = chess_get_games($observer, $obs_nick);
			$games = $g['games'];
			$gameinfo = chess_get_info($observer, $game_id);
			$o .= replace_macros(get_markup_template('chess_controls.tpl', 'addon/chess'), array(
				'$owner' => 1,
				'$channel' => $owner['channel_address'],
				'$games' => $games,
				'$gameinfo' => $gameinfo,
				'$historyviewer' => 1,
				'$notify_toggle' => chess_game_settings(),
				'$version' => 'v' . chess_get_version()
			));
			break;
		case 'opponent':
			$gameinfo = chess_get_info($observer, $game_id);
			$o .= replace_macros(get_markup_template('chess_controls.tpl', 'addon/chess'), array(
				'$owner' => 0,
				'$channel' => $owner['channel_address'],
				'$games' => 0,
				'$gameinfo' => $gameinfo,
				'$historyviewer' => 1,
				'$notify_toggle' => chess_game_settings(),
				'$version' => 'v' . chess_get_version()
			));
			break;
		case 'spectator':
			// TODO: Allow viewing the history viewer
			break;
		default:
			break;
	}


	return $o;
}

/**
 * @brief Set the layout for page composition, defining the aside region as an
 * instance of the controls widget
 *
 * @return null
 */
function chess_load_pdl(&$b) {
	if ($b['module'] === 'chess') {
		$b['layout'] = '
			[region=aside]
			[widget=chess_controls][/widget]
			[/region]
			';
	}
}



/**
 * @brief Create a new game by generating a new item table record as a standard
 * post. This will propagate to the other player and provide a link to begin playing
 *
 * @return array Status and parameters of the new game post
 */
function chess_create_game($channel, $color, $acl, $enforce_legal_moves, $public_visible) {

	$resource_type = 'chess';
	// Generate unique resource_id using the same method as item_message_id()
	do {
		$dups = false;
		$resource_id = random_string(5);
		$r = q("SELECT mid FROM item WHERE resource_id = '%s' AND resource_type = '%s' AND uid = %d LIMIT 1",
			dbesc($resource_id),
			dbesc($resource_type),
			intval($channel['channel_id'])
		);
		if (count($r))
			$dups = true;
	} while ($dups == true);
	$ac = $acl->get();
	$uuid = item_message_id();
	$mid = z_root() . '/item/' . $uuid;

	$arr = array();  // Initialize the array of parameters for the post
	$objtype = ACTIVITY_OBJ_CHESSGAME;
	$perms = $acl->get();
	$allow_cid = expand_acl($perms['allow_cid']);
	$player2 = null;
	if (count($allow_cid)) {
		foreach ($allow_cid as $allow) {
			if ($allow === $channel['channel_hash'])
				continue;
			$player2 = $allow;
		}
	}


	$players = array($channel['channel_hash'], $player2);
	$object = json_encode(array(
		'id' => z_root() . '/chess/game/' . $resource_id,
		'players' => $players,
		'colors' => array($color, ($color === 'white' ? 'black' : 'white')),
		'active' => ($color === 'white' ? $players[0] : $players[1]),
		'position' => 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1',
		'ended' => 0,
		'enforce_legal_moves' => (($enforce_legal_moves === 0 || $enforce_legal_moves === 1) ? $enforce_legal_moves : 0),
		'public_visible' => (($public_visible === 0 || $public_visible === 1) ? $public_visible : 0),
		'version' => chess_get_version()	// Potential compatability issues
	));
	$item_hidden = 0; // TODO: Allow form creator to send post to ACL about new game automatically
	$game_url = z_root() . '/chess/' . $channel['channel_address'] . '/' . $resource_id;
	$arr['aid'] = $channel['channel_account_id'];
	$arr['uid'] = $channel['channel_id'];
	$arr['uuid'] = $uuid;
	$arr['mid'] = $mid;
	$arr['parent_mid'] = $mid;
	$arr['item_hidden'] = $item_hidden;
	$arr['resource_type'] = $resource_type;
	$arr['resource_id'] = $resource_id;
	$arr['owner_xchan'] = $channel['channel_hash'];
	$arr['author_xchan'] = $channel['channel_hash'];
	// Store info about the type of chess item using the "title" field
	// Other types include 'move' for children items but may in the future include
	// additional types that will determine how the "object" field is interpreted
	$arr['title'] = 'game';
	$arr['allow_cid'] = $ac['allow_cid'];
	$arr['item_wall'] = 1;
	$arr['item_origin'] = 1;
	$arr['item_thread_top'] = 1;
	$arr['item_private'] = intval($acl->is_private());
	$arr['verb'] = ACTIVITY_POST;
	$arr['obj_type'] = $objtype;
	$arr['obj'] = $object;
	$arr['body'] = '[table][tr][td][h1]New Chess Game[/h1][/td][/tr][tr][td][zrl=' . $game_url . ']Click here to play[/zrl][/td][/tr][/table]';

	$post = item_store($arr);
	$item_id = $post['item_id'];

	if ($item_id) {
		Zotlabs\Daemon\Master::Summon(['Notifier', 'activity', $item_id]);
		return array('item' => $arr, 'status' => true);
	} else {
		return array('item' => null, 'status' => false);
	}
}

/**
 * @brief Create a new move in the game by generating a child item for the game post
 *
 * @param $observer Authenticated observer (remote or local channel) viewing the page
 * @param $newPosFEN New board position in FEN-format
 * @param $g Game post item table record with all the game information
 * @return array Success status and array of new post data
 */
function chess_make_move($observer, $newPosFEN, $g) {
	$resource_type = 'chess';
	$resource_id = $g['resource_id'];
	$uuid = item_message_id();
	$mid = z_root() . '/item/' . $uuid;
	$arr = array();  // Initialize the array of parameters for the post
	$objtype = ACTIVITY_OBJ_CHESSGAME;
	$object = json_encode(array(
		'id' => z_root() . '/chess/game/' . $resource_id,
		'position' => $newPosFEN, // Store the new board position in FEN notation
		'version' => chess_get_version()	// Potential compatability issues
	));
	$item_hidden = 0; // TODO: Allow form creator to send post to ACL about new game automatically
	$r = q("select channel_address from channel where channel_id = %d limit 1",
		intval($g['uid'])
	);
	$channel_address = '';
	if ($r) {
		$channel_address = $r[0]['channel_address'];
	}
	$game_url = z_root() . '/chess/' . $channel_address . '/' . $resource_id;
	$arr['aid'] = $g['aid'];
	$arr['uid'] = $g['uid'];
	$arr['uuid'] = $uuid;
	$arr['mid'] = $mid;
	$arr['parent_mid'] = $g['mid'];
	$arr['item_hidden'] = $item_hidden;
	$arr['resource_type'] = $resource_type;
	$arr['resource_id'] = $resource_id;		   // Game ID
	$arr['owner_xchan'] = $g['owner_xchan'];	// Tracks the owner of the game
	$arr['author_xchan'] = $observer['xchan_hash'];  // Denotes which player made this move
	// Store info about the type of chess item using the "title" field
	// Other types include 'move' for children items but may in the future include
	// additional types that will determine how the "object" field is interpreted
	$arr['title'] = 'move';
	$arr['item_wall'] = 1;
	$arr['item_origin'] = 1;
	$arr['item_thread_top'] = 0;
	$arr['item_private'] = 1;
	$arr['verb'] = ACTIVITY_POST;
	$arr['obj_type'] = $objtype;
	$arr['obj'] = $object;
	$arr['body'] = 'New position (FEN format): [zrl=' . $game_url. ']' . $newPosFEN. '[/zrl]';

	$post = item_store($arr);
	$item_id = $post['item_id'];

	if ($item_id) {
		Zotlabs\Daemon\Master::Summon(['Notifier', 'activity', $item_id]);
		return array('item' => $arr, 'status' => true);
	} else {
		return array('item' => null, 'status' => false);
	}
}

/**
 * @brief Change the game item to specify which player should take the next turn
 *
 * @param $xchan Unique hash associated with which channel should take the next turn
 * @param $g Game post item table record with all the game information
 * @return boolean Success of game item update
 */
function chess_set_active($g, $xchan) {
	$game = json_decode($g['obj'], true);
	$game['active'] = $xchan;

	if (!$game['id'])
		$game['id'] = $game['resource_id'];

	$gameobj = json_encode($game);
	$r = q("UPDATE item set obj = '%s' WHERE mid = '%s' AND resource_type = '%s'",
		dbesc($gameobj),
		dbesc($g['mid']),
		dbesc('chess')
	);
	return $r;
}

/**
 * @brief Updates the game item with the latest board position
 *
 * @param $position New board position in FEN-format
 * @param $g Game post item table record with all the game information
 * @return array Success of game item update
 */
function chess_set_position($g, $position) {
	$game = json_decode($g['obj'], true);
	$game['position'] = $position;

	if (!$game['id'])
		$game['id'] = $game['resource_id'];

	$gameobj = json_encode($game);
	$r = q("UPDATE item set obj = '%s' WHERE mid = '%s' AND resource_type = '%s'",
		dbesc($gameobj),
		dbesc($g['mid']),
		dbesc('chess')
	);
	return $r;
}

/**
 * @brief Retrieve the game item data structure
 *
 * @param $game_id Unique game ID string
 * @return array Success of retrieval and game item
 */
function chess_get_game($game_id) {
	$g = q("SELECT * FROM item WHERE resource_id = '%s' AND resource_type = '%s' and "
	  . "mid = parent_mid AND item_deleted = 0 LIMIT 1",
		dbesc($game_id),
		dbesc('chess')
	);
	if (!$g) {
		return array('game' => null, 'status' => false);
	} else {
		return array('game' => $g[0], 'status' => true);
	}
}

/**
 * @brief Retrieve all board positions of a game
 *
 * @param $g Game post item table record with all the game information
 * @return array Success of retrieval and game history
 */
function chess_get_history($g) {
	$parentmid = $g['mid'];
	$moves = q("SELECT mid,obj,author_xchan FROM item WHERE resource_type = '%s' "
		. "AND resource_id = '%s' AND parent_mid = '%s' AND mid != parent_mid order by id",
		dbesc('chess'),
		dbesc($g['resource_id']),
dbesc($parentmid)
	);
	if (!$moves) {
		return array('history' => null, 'status' => false);
	} else {
		return array('history' => $moves, 'status' => true);
	}
}

/**
 * @brief Revert the game to a previous board position
 *
 * @param $g Game post item table record with all the game information
 * @param $observer Authenticated observer (remote or local channel) viewing the page
 * @return array Success of board position reversion
 */
function chess_revert_position($g, $observer, $mid) {
	$m = q("SELECT obj FROM item WHERE resource_type = '%s' AND resource_id = '%s' "
		. "AND mid = '%s' LIMIT 1",
		dbesc('chess'),
		dbesc($g['resource_id']),
		dbesc($mid)
	);
	if (!$m) {
		return array('status' => false);
	} else {
		$gameobj = json_decode($g['obj'], true);
		$moveobj = json_decode($m[0]['obj'], true);
		$move = chess_make_move($observer, $moveobj['position'], $g);
		if ($move['status']) {
			if (chess_set_position($g, $moveobj['position'])) {
				$active_xchan = ($gameobj['players'][0] === $observer['xchan_hash'] ? $gameobj['players'][1] : $gameobj['players'][0]);
				chess_set_active($g, $active_xchan);
				return array('status' => true);
			} else {
				return array('status' => false);
			}
		} else {
			return array('status' => false);
		}
	}
}

/**
 * @brief Retrieve a list of games in which the observer is a participant, separating
 * lists of those owned and those not owned
 *
 * @param $owner_address The channel name taken from the URL /chess/[channelname]
 * @param $observer Authenticated observer (remote or local channel) viewing the page
 * @return array Success of games retrieval and the games data
 */
function chess_get_games($observer, $owner_address) {
	$g = [];
	$g['owner_active'] = $g['player_active'] = $g['owner_ended'] = $g['player_ended'] = [];
	$hash = q("select channel_hash from channel where channel_address = '%s' AND "
		. "channel_removed = 0  limit 1",
		  dbesc($owner_address)
	);
	if (!$hash) {
		$g['owner_active'] = $g['player_active'] = $g['owner_ended'] = $g['player_ended'] = null;
		return array('games' => $g, 'status' => false);
	}
	$owner_hash = $hash[0]['channel_hash'];
	// This is a potentially expensive query if there are many chess games
	$games = q("SELECT * FROM item WHERE resource_type = '%s' AND title = '%s' AND "
		. "owner_xchan = '%s' AND obj LIKE '%s' AND item_deleted = 0 order by id desc",
		dbesc('chess'),
		dbesc('game'),
		dbesc($owner_hash),
		dbesc('%' . $observer['xchan_hash'] . '%')
	);
	if (!$games) {
		$g['owner_active'] = $g['player_active'] = $g['owner_ended'] = $g['player_ended'] = null;
		return array('games' => $g, 'status' => false);
	}
	foreach ($games as $game) {
		$gameobj = json_decode($game['obj'], true);
		// Get the names of the players
		$info = chess_get_info($observer, $game['resource_id']);
		// Determine opponent's name
		$opponent_name = (($observer['xchan_hash'] === $gameobj['players'][0]) ? $info['players'][1] : $info['players'][0]);
		$active = (($observer['xchan_hash'] === $gameobj['active']) ? true : false);
		$date = array_shift(explode(' ', $game['created']));
		if ($game['owner_xchan'] === $observer['xchan_hash']) {
			if (!x($gameobj, 'ended') || $gameobj['ended'] === 0) {
				$g['owner_active'][] = array('plink' => $game['plink'], 'game_id' => $game['resource_id'], 'date' => $date, 'opponent' => $opponent_name, 'active' => $active, 'obj' => $gameobj);
			} else {
				$g['owner_ended'][] = array('plink' => $game['plink'], 'game_id' => $game['resource_id'], 'date' => $date, 'opponent' => $opponent_name, 'active' => $active, 'obj' => $gameobj);
			}
		} elseif (in_array($observer['xchan_hash'], $gameobj['players'])) {
			if (!x($gameobj, 'ended') || $gameobj['ended'] === 0) {
				$g['player_active'][] = array('plink' => $game['plink'], 'game_id' => $game['resource_id'], 'date' => $date, 'opponent' => $opponent_name, 'active' => $active, 'obj' => $gameobj);
			} else {
				$g['player_ended'][] = array('plink' => $game['plink'], 'game_id' => $game['resource_id'], 'date' => $date, 'opponent' => $opponent_name, 'active' => $active, 'obj' => $gameobj);
			}
		}
	}
	$g['owner_active'] = ((empty($g['owner_active'])) ? null : $g['owner_active']);
	$g['owner_ended'] = ((empty($g['owner_ended'])) ? null : $g['owner_ended']);
	$g['player_active'] = ((empty($g['player_active'])) ? null : $g['player_active']);
	$g['player_ended'] = ((empty($g['player_ended'])) ? null : $g['player_ended']);

	return array('games' => $g, 'status' => true);
}

/**
 * @brief Delete a chess game using the standard drop_item method for posts in the
 * item table
 *
 * @param $game_id unique ID of the game to be deleted
 * @param $channel The authenticated local channel requesting the deletion
 * @return array Success of deletion and item that was deleted
 */
function chess_delete_game($game_id, $channel) {
	$items = q("SELECT id FROM item WHERE resource_type = '%s' AND resource_id = '%s' "
		. "AND uid = %d AND item_deleted = 0 limit 1",
		dbesc('chess'),
		dbesc($game_id),
		intval($channel['channel_id'])
	);
	if (!$items) {
		return array('items' => null, 'status' => false);
	} else {
		$drop = drop_item($items[0]['id'], false, DROPITEM_NORMAL, true);
		return array('items' => $items, 'status' => (($drop === 1) ? true : false));
	}
}

/**
 * @brief Toggle the enforcement of legal chess moves for a game
 *
 * @param $game_id unique ID of the game
 * @param $channel The authenticated local channel requesting the toggle
 * @return array Success of toggle
 */
function chess_toggle_legal_moves($g) {
	$game = json_decode($g['game']['obj'], true);
	$game['enforce_legal_moves'] = ((!x($game, 'enforce_legal_moves') || $game['enforce_legal_moves'] === 0) ? 1 : 0);
	$gameobj = json_encode($game);
	$r = q("UPDATE item set obj = '%s' WHERE resource_id = '%s' AND resource_type = '%s'",
		dbesc($gameobj),
		dbesc($g['game']['resource_id']),
		dbesc('chess')
	);
	$g = chess_get_game($g['game']['resource_id']);
	$game = json_decode($g['game']['obj'], true);
	if (!$r) {
		return array('enforce_legal_moves' => null, 'status' => false);
	} else {
		return array('enforce_legal_moves' => $game['enforce_legal_moves'], 'status' => true);
	}
}

/**
 * @brief Ends a chess game by setting a game item object property. Assumes the
 * permissions to perform this action are already verified
 *
 * @param $g Game post item table record with all the game information
 * @return array Success of ending game
 */
function chess_end_game($g) {
	$game = json_decode($g['obj'], true);
	$game['ended'] = 1; // An active game will have ended = 0 or will not have "ended" at all
	$gameobj = json_encode($game);
	$r = q("UPDATE item set obj = '%s' WHERE mid = '%s' AND resource_type = '%s'",
		dbesc($gameobj),
		dbesc($g['mid']),
		dbesc('chess')
	);
	return $r;
}

/**
 * @brief Resumes a chess game by setting a game item object property. Assumes the
 * permissions to perform this action are already verified
 *
 * @param $g Game post item table record with all the game information
 * @return array Success of resuming game
 * @todo Combine this with chess_end_game() with a 0/1 input parameter
 */
function chess_resume_game($g) {
	$game = json_decode($g['obj'], true);
	$game['ended'] = 0; // An active game will have ended = 0 or will not have "ended" at all
	$gameobj = json_encode($game);
	$r = q("UPDATE item set obj = '%s' WHERE mid = '%s' AND resource_type = '%s'",
		dbesc($gameobj),
		dbesc($g['mid']),
		dbesc('chess')
	);
	return $r;
}

/**
 * @brief Retrieve various info about a game, including the players' names and the
 * permanent link to the game conversation
 *
 * @param $game_id unique ID of the game to be deleted
 * @param $observer Authenticated observer (remote or local channel) viewing the page
 * @return array Success of retrieval and the game info
 */
function chess_get_info($observer, $game_id) {
	$g = chess_get_game($game_id);
	if (!$g) {
		return array('players' => null, 'status' => false);
	}
	$game = json_decode($g['game']['obj'], true);
	// If the observer is a player in the game, get the names of the players
	if (in_array($observer['xchan_hash'], $game['players'])) {
		$player_names = [];
		foreach ($game['players'] as $xchan_hash) {
			$p = q("select xchan_name from xchan where xchan_hash = '%s' limit 1",
				dbesc($xchan_hash)
			);
			if (!$p) {
				return array('players' => null, 'status' => false);
			}
			$player_names[] = $p[0]['xchan_name'];
		}
		return array('players' => $player_names, 'plink' => $g['game']['plink'], 'status' => true);
	} else {
		return array('players' => null, 'plink' => null, 'status' => false);
	}
}

function chess_game_settings() {
	$observer = App::get_observer();
	$notifications = get_xconfig($observer['xchan_hash'], 'chess', 'notifications');

	return array('chess_notify_enabled', t('Enable notifications'), $notifications, '', $yes_no);
}
