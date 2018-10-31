<?php

namespace Zotlabs\Module;

use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;
use Zotlabs\Storage;

class Flashcards extends \Zotlabs\Web\Controller {

    function init() {
        // Determine which channel's flashcards to display to the observer
        $nick = null;
        if (argc() > 1) {
            $nick = argv(1); // if the channel name is in the URL, use that
        }
        if (!$nick && local_channel()) { // if no channel name was provided, assume the current logged in channel
            $channel = \App::get_channel();
            if ($channel && $channel['channel_address']) {
                $nick = $channel['channel_address'];
                goaway(z_root() . '/flashcards/' . $nick);
            }
        }
        if (!$nick) {
            notice(t('Profile Unavailable.') . EOL);
            goaway(z_root());
        }

        profile_load($nick);
        
//        $this->flashcards_merge_test();
    }

    function get() {
        
        head_add_css('/addon/flashcards/view/css/flashcards.css');

        if (observer_prohibited(true)) {
            return login();
        }

        $desc = 'This addon app provides a learning software for your channel.';

        $text = '<div class="section-content-info-wrapper">' . $desc . '</div>';
        
        $ownerProfile = \App::$profile_uid; // TODO Delete

        if (!$ownerProfile) {
            return $text;
        }

        if(! Apps::addon_app_installed($ownerProfile,'flashcards')) { 
            return $text;
        }

        if (!perm_is_allowed($ownerProfile, get_observer_hash(), 'view_storage')) {
            notice(t('Permission denied.') . EOL);
            return;
        }
        
	$observer = \App::get_observer();
        $editor = $observer['xchan_addr'];

        $ownerChannel = channelx_by_n($ownerProfile);
		
        $is_owner = $this->isOwner();

        $o = replace_macros(get_markup_template('flashcards.tpl','addon/flashcards'),array(
                '$post_url' => 'flashcards/' . $ownerChannel['channel_address'],
                '$is_owner' => $is_owner,
                '$flashcards_editor' => $editor
        ));
        return $o;
    }

    function post() {

	if(! local_channel()) {
            return;
        }
	
        $owner_uid = \App::$profile_uid;

        $is_owner = $this->isOwner();
        
        $owner = channelx_by_n($owner_uid);        
        $observer = \App::get_observer();

        $ob_hash = (($observer) ? $observer['xchan_hash'] : '');
        
        // If an observer is allowed to view flashcards of the owner then
        // he can automatically use these flashcards. The addon will create a
        // copy for the observer where he can
        // - edit the cards
        // - reshare the edits
        // - store his learning progress.
        // The observer will never own his copy (and learning progress).
        // At every time the owner can
        // - deny the permissions for the observer
        // - delete the flashcards of the observer
        if (!perm_is_allowed($owner_uid, $ob_hash, 'view_storage')) {
            notice(t('Permission denied.') . EOL);
            json_return_and_die(array('status' => false, 'errormsg' => t('Permission denied.') . EOL));
        }
        
        $boxesDir = $this->getAddonDir($owner);
        
        if (argc() > 2) {
            switch (argv(2)) {
                case 'upload':
                    // API: /flashcards/nick/upload
                    // Creates or merges a box
                    $this->writeBox($boxesDir, $is_owner, $owner, $observer);
                case 'download':
                    // API: /flashcards/nick/download
                    // Downloads a box specified by param "box_id"
                    $this->sendBox($boxesDir, $is_owner, $owner, $observer);
                case 'list':
                    // API: /flashcards/nick/list
                    // List all boxes owned by the channel
                    $this->listBoxes($boxesDir, $owner);
                case 'delete':
                    // API: /flashcards/nick/delete
                    // Deletes a box specified by param "box_id"
                    $this->deleteBox($boxesDir, $is_owner, $owner, $observer);
                default:
                    break;
            }
        } 
        
    }
    
    private function isOwner() {

        $owner_uid = \App::$profile_uid;
        $observer_uid = local_channel();

        $is_owner = ($observer_uid && $observer_uid == $owner_uid);
        
        return $is_owner;
    }

    private function listBoxes($boxesDir, $owner) {
        
        $this->recoverBoxes($boxesDir, $owner);
        
        $boxes = [];
        
        try {
            $children = $boxesDir->getChildren();
        } catch (\Exception $e) {
            notice(t('Permission denied.') . EOL);
            json_return_and_die(array('status' => false, 'errormsg' => $e->getMessage() . EOL));
        }
        foreach($children as $child) {
            if ($child instanceof \Zotlabs\Storage\File) {
                if($child->getContentType() === strtolower('application/json')) {
                    $box = $this->readBox($boxesDir, $child->getName());
                    unset($box['cards']);
                    array_push($boxes, $box);
                }
            }
        }
        if (empty($boxes)) {
            notice('No boxes found');
        }
        
        json_return_and_die(array('status' => true, 'boxes' => $boxes));
    }

    private function recoverBoxes($boxesDir, $channel) {
        
        if(! $this->isOwner()) {
            return;
        }
        
        $recoverDir = $this->getRecoverDir($boxesDir, $channel);
        
        $children = $recoverDir->getChildren();
        foreach($children as $child) {
            if ($child instanceof \Zotlabs\Storage\File) {
                if($child->getContentType() === strtolower('application/json')) {
                    $fname = $child->getName();
                    if($boxesDir->childExists($fname)) {
                        notice('Recovery failed. File "' . $fname . '" exist.');
                    } else {
                        $box = $this->readBox($recoverDir, $fname);
                        $hash = random_string(15);
                        $box["boxID"] = $hash;
                        $box["boxPublicID"] = $hash;
                        $boxesDir->createFile($hash . '.json', json_encode($box));
                        info('Box was recovered.');
                    }
                    $child->delete();
                }
            }
        }
       
       
    }

    private function sendBox($boxesDir, $is_owner, $owner, $observer) {    
        $box_id = isset($_POST['boxID']) ? $_POST['boxID'] : ''; 
        if(strlen($box_id) > 0) {
            $box = $this->readBox($boxesDir, $box_id . '.json');
            if(! $box) {
                json_return_and_die(array('status' => false, 'errormsg' => 'No box found or no permissions for ' . $box_id));
            }
            if($is_owner) {
                $box = $this->importSharedBoxes($boxesDir, $owner, $box);
                json_return_and_die(
                        array(
                            'status' => true,
                            'box' => $box,
                            'resource_public_id' => $box["boxPublicID"],
                            'resource_id' => $box["boxID"]));
            }
            $this->sendBoxObserver($boxesDir, $box, $owner, $observer);
        }
    }
    
    private function sendBoxObserver($boxesDir, $box, $owner, $observer) {
        
        $box_id = $box['boxID'];
        
        $boxDir = $this->createDirBoxObserver($boxesDir, $box_id, $owner);
        
        $box_name = $this->getBoxNameObserver($observer);
        
        $filename = $box_name . '.json';
        
        if(! $boxDir->childExists($filename)) {
            $cards = $box['cards'];
            if($cards) {            
                foreach ($cards as &$card) {
                    for($x = 6; $x < 11; $x++) {
                        $card['content'][$x] = '';                        
                    }
                }
                $box['cards'] = $cards;
            }
            $hash = random_string(15);
            $box["boxID"] = $hash;
            $boxDir->createFile($filename, json_encode($box));
            info('Box was copied box for you');
        }
        
        $boxObserver = $this->readBox($boxDir, $filename);
        
        $boxObserver = $this->mergeOwnerBoxIntoObserverBox($boxesDir, $boxObserver);
        
        json_return_and_die(
                array(
                    'status' => true,
                    'box' => $boxObserver,
                    'resource_public_id' => $box["boxPublicID"],
                    'resource_id' => $box["boxID"]));
        
    }
    
    private function createDirBoxObserver($boxesDir, $box_id, $owner) {
        
        if(! $boxesDir->childExists($box_id)) {
            $boxesDir->createDirectory($box_id);
        }

        $boxDir = new \Zotlabs\Storage\Directory('/'. $owner['channel_address'] . '/flashcards/' . $box_id, $this->createAuth($owner));
        if(! $boxDir) {
            json_return_and_die(array('message' => 'Directory for box can not be created.', 'success' => false));
        }
        
        return $boxDir;
        
    }
    
    private function writeBox($boxesDir, $is_owner, $owner, $observer) {
        
        $boxRemote = $_POST['box'];
        if(!$boxRemote) {
            json_return_and_die(array('status' => false, 'errormsg' => 'No box was sent'));
        }
        $box_id = $boxRemote["boxID"];

        $cards = $boxRemote['cards'];
        $cardIDsReceived = array();
        if(isset($cards)) {                    
            foreach ($cards as &$card) {
                array_push($cardIDsReceived, $card['content'][0]);
            }
        }
        
        if($is_owner) {

            if(strlen($box_id) > 0) {
                
                $filename = $box_id . '.json';
                if(! $boxesDir->childExists($filename)) {
                    $boxesDir->createFile($filename, $boxRemote);
                } else {
                    $this->mergeBox($boxesDir, $box_id, $boxRemote, $cardIDsReceived, $owner);
                }
                
                json_return_and_die(array('status' => true, 'resource_id' => $box_id, 'cardIDsReceived' => $cardIDsReceived));
                
            } else {
                
                $hash = random_string(15);
                $boxRemote["boxID"] = $hash;
                $boxRemote["boxPublicID"] = $hash;
                $boxesDir->createFile($hash . '.json', json_encode($boxRemote));
                
                json_return_and_die(array('status' => true, 'resource_id' => $hash, 'resource_public_id' => $hash, 'cardIDsReceived' => $cardIDsReceived));     
                
            }
            
        } else {
            
            $this->writeBoxObserver($boxesDir, $boxRemote, $cardIDsReceived, $owner, $observer);
            
        }
        
    }
    
    private function writeBoxObserver($boxesDir, $boxRemote, $cardIDsReceived, $owner, $observer) {
        
        $box_id = $boxRemote["boxID"];
        $box_public_id = $boxRemote["boxPublicID"];
        
        if(! $boxesDir->childExists($box_public_id)) {
            notice('No box dir found. Might be deleted by owner.');
            json_return_and_die(array('status' => false, 'errormsg' => 'No box dir found. Might be deleted by owner.'));
        }

        $boxDir = new \Zotlabs\Storage\Directory('/'. $owner['channel_address'] . '/flashcards/' . $box_public_id, $this->createAuth($owner));

        $filename = $this->getBoxNameObserver($observer) . '.json';
        
        if(! $boxDir->childExists($filename)) {
            notice('No box found. Might be delete by owner.');
            json_return_and_die(array('status' => false, 'errormsg' => 'No box found. Might be delete by owner.'));
        }        
        
        $boxLocal = $this->readBox($boxDir, $filename);
        
        $boxLocalMergedOwner = $this->mergeOwnerBoxIntoObserverBox($boxesDir, $boxLocal);
        
        $boxes = $this->flashcards_merge($boxLocalMergedOwner, $boxRemote);
        $boxToWrite = $boxes['boxLocal'];
        $boxToSend = $boxes['boxRemote'];

        $boxToWrite['lastShared'] = $boxToSend['lastShared'] = round(microtime(true) * 1000);
        
        // store box of observer
        $boxDir->getChild($filename)->put(json_encode($boxToWrite));
        
        // write box of observer to dir "share" where the owner can merge it
        $this->shareObserverBoxLocally($boxesDir, $boxToWrite, $owner);
        
        json_return_and_die(array('status' => true, 'box' => $boxToSend, 'resource_id' => $box_id, 'cardIDsReceived' => $cardIDsReceived));
        
    }
    
    private function mergeOwnerBoxIntoObserverBox($boxesDir, $boxObserver) {
        
        $box_public_id = $boxObserver["boxPublicID"];
        $filename = $box_public_id . '.json';
        
        $boxOwner = $this->readBox($boxesDir, $filename);
        if(! $boxOwner) {
            notice('Box of owner not found on server'); // This should never happen. Anyway.
            return $boxObserver;
        }
        
        $boxes = $this->flashcards_merge($boxOwner, $boxObserver, false);
        
        return $boxes['boxLocal'];
    }
    
    private function mergeOwnerBoxIntoObserverBoxChangedOnly($boxesDir, $boxObserver) {
        
        $box_public_id = $boxObserver["boxPublicID"];
        $filename = $box_public_id . '.json';
        
        $boxOwner = $this->readBox($boxesDir, $filename);
        if(! $boxOwner) {
            notice('Box of owner not found on server'); // This should never happen. Anyway.
            return $boxObserver;
        }
        
        $boxes = $this->flashcards_merge($boxOwner, $boxObserver, false);
        
        return $boxes['boxRemote'];
    }
    
    private function shareObserverBoxLocally($boxesDir, $boxObserver, $owner) {
        
        $cards = $boxObserver['cards'];
        $cardPublic = array();
        if(isset($cards)) {                    
            foreach ($cards as &$card) {
                for ($i = 6; $i < 10; $i++) {
                    $card['content'][$i] = 0;
                }
                $card['content'][10] = false;
                array_push($cardPublic, $card);
            }
        }
        $boxObserver['cards'] = $cardPublic;
        
        $shareDir = $this->getShareDir($boxesDir, $owner);
        
        $filename = $this->getShareFileName($boxObserver);
        
        if($shareDir->childExists($filename)) {
            $shareDir->getChild($filename)->put(json_encode($boxObserver));
        } else {
            $shareDir->createFile($filename, json_encode($boxObserver));
        }
    }
    
    private function importSharedBoxes($boxesDir, $owner, $box) {
        
        $boxId = $box['boxID'];
        
        $shareDir = $this->getShareDir($boxesDir, $owner);
        
        $boxes = [];
        
        $children = $shareDir->getChildren();
        foreach($children as $child) {
            if ($child instanceof \Zotlabs\Storage\File) {
                if($child->getContentType() === strtolower('application/json')) {
                    $sharedFileName = $child->getName();
                    if (strpos($sharedFileName, $boxId) === 0) {
                        $sharedBox = $this->readBox($shareDir, $sharedFileName);
                        $boxes = $this->flashcards_merge($box, $sharedBox, false);
                        $box = $boxes['boxLocal'];
                        $shareDir->getChild($sharedFileName)->delete();
                    }
                }
            }
        }
        
        return $box;
        
    }
    
    private function getShareFileName($boxObserver) {
        $filename = $boxObserver['boxPublicID'] . '-' . $boxObserver['boxID'] . '.json';
        return $filename;
    }
    
    private function getBoxNameObserver($observer) {
        $ob_hash = $observer['xchan_hash'];
        $box_name = substr($ob_hash, 0, 15);
        return $box_name;
    }
    
    private function readBox($boxesDir, $filename) {
        $boxFileExists = $boxesDir->childExists($filename);
        if(! $boxFileExists) {
            return false;
        }
        
        $JSONstream = $boxesDir->getChild($filename)->get();
        $contents = stream_get_contents($JSONstream);
        $box = json_decode($contents, true);
        fclose($JSONstream);
        
        return $box;
        
    }

    private function mergeBox($boxesDir, $box_id, $boxRemote, $cardIDsReceived, $owner) {
        
        $boxLocal = $this->readBox($boxesDir, $box_id . '.json');
        if(! $boxLocal) {
            json_return_and_die(array('status' => false, 'resource_id' => $box_id, 'errormsg' => 'Box not found on server'));
        }
        
        // Another user might have changed the box
        $boxLocalWithImports = $this->importSharedBoxes($boxesDir, $owner, $boxLocal);
        
        // The same user might have changed the box meanwhile from a different device
        $boxes = $this->flashcards_merge($boxLocalWithImports, $boxLocal);
        $boxLocalMergedWithImportsAndLocal = $boxes['boxLocal'];

        // Merge the changes from the client (browser)
        $boxes = $this->flashcards_merge($boxLocalMergedWithImportsAndLocal, $boxRemote);
        $boxToWrite = $boxes['boxLocal'];
        $boxToSend = $boxes['boxRemote'];

        $boxToWrite['lastShared'] = $boxToSend['lastShared'] = round(microtime(true) * 1000);
        
        $boxesDir->getChild($box_id . '.json')->put(json_encode($boxToWrite));
        json_return_and_die(array('status' => true, 'box' => $boxToSend, 'resource_id' => $box_id, 'cardIDsReceived' => $cardIDsReceived));
        
    }
    
    private function deleteBox($boxesDir, $is_owner, $owner, $observer) {
        
        $boxID = $_POST['boxID'];
        if(! $boxID) {
            return;
        }
        
        if(!$is_owner) {
            $this->deleteBoxObserver($boxesDir, $boxID, $owner, $observer);
        }
        
        $filename = $boxID . '.json';
        
        if($boxesDir->childExists($filename)) {      
            
            // delete box itself
            $boxesDir->getChild($filename)->delete();
            // delete directory of box for the observers and their boxes too
            if($boxesDir->childExists($boxID)) {
                $boxesDir->getChild($boxID)->delete();
            }

            // delete boxes in "share" directory
            $shareDir = $this->getShareDir($boxesDir, $owner);
            $children = $shareDir->getChildren();
            foreach($children as $child) {
                if ($child instanceof \Zotlabs\Storage\File) {
                    if($child->getContentType() === strtolower('application/json')) {
                        $sharedFileName = $child->getName();
                        if (strpos($sharedFileName, $boxID) === 0) {
                            $shareDir->getChild($sharedFileName)->delete();
                        }
                    }
                }
            }
            
            json_return_and_die(array('status' => true));
            
        } else {
            
            json_return_and_die(array('status' => false, 'errormsg' => 'Box not found on server'));
            
        }
        
    }
    
    private function deleteBoxObserver($boxesDir, $box_id, $owner, $observer) {
        
        if(! $boxesDir->childExists($box_id)) {
            notice('No box dir found. Might be delete by owner.');
            json_return_and_die(array('status' => true));
        }

        $boxDir = new \Zotlabs\Storage\Directory('/'. $owner['channel_address'] . '/flashcards/' . $box_id, $this->createAuth($owner));

        $filename = $this->getBoxNameObserver($observer) . '.json';
        
        if($boxDir->childExists($filename)) {      
            $boxDir->getChild($filename)->delete();
            json_return_and_die(array('status' => true));
        }
        
    }
    
    private function createAuth($channel) { 
        
        // copied/adapted from Cloud.php
		$auth = new \Zotlabs\Storage\BasicAuth();

        $auth->setCurrentUser($channel['channel_address']);
        $auth->channel_id = $channel['channel_id'];
        $auth->channel_hash = $channel['channel_hash'];
        $auth->channel_account_id = $channel['channel_account_id'];
        if($channel['channel_timezone']) {
            $auth->setTimezone($channel['channel_timezone']);
        }
        // this is not true but reflects that no files are owned by the observer
        $auth->observer = $channel['channel_hash'];
        
        return $auth;
    }
    
    private function getShareDir($boxesDir, $channel) {
        
        $auth = $this->createAuth($channel);
        
        if(! $boxesDir->childExists('share')) {
            $boxesDir->createDirectory('share');
        }
        
        $channelAddress = $channel['channel_address'];
        
        $shareDir = new \Zotlabs\Storage\Directory('/'. $channelAddress . '/flashcards/share', $auth);
        
        if(! $shareDir) {
            json_return_and_die(array('message' => 'Directory share is missing.', 'success' => false));
        }
        
        return $shareDir;
    }
    
    private function getRecoverDir($boxesDir, $channel) {
        
        $auth = $this->createAuth($channel);
        
        if(! $boxesDir->childExists('recover')) {
            $boxesDir->createDirectory('recover');
        }
        
        $channelAddress = $channel['channel_address'];
        
        $recoverDir = new \Zotlabs\Storage\Directory('/'. $channelAddress . '/flashcards/recover', $auth);
        
        if(! $recoverDir) {
            json_return_and_die(array('message' => 'Directory recover is missing.', 'success' => false));
        }
        
        return $recoverDir;
    }
    
    private function getAddonDir($channel) {
        
        $auth = $this->createAuth($channel);
        
        $rootDirectory = new \Zotlabs\Storage\Directory('/', $auth);
        
        $channelAddress = $channel['channel_address'];
        
        if(! $rootDirectory->childExists($channelAddress)) {
            json_return_and_die(array('message' => 'No cloud directory.', 'success' => false));
        }
        $channelDir = new \Zotlabs\Storage\Directory('/' . $channelAddress, $auth);
        
        if(! $channelDir->childExists('flashcards')) {
            $channelDir->createDirectory('flashcards');
        }
        
        $boxesDir = new \Zotlabs\Storage\Directory('/'. $channelAddress . '/flashcards', $auth);
        if(! $boxesDir) {
            json_return_and_die(array('message' => 'Directory flashcards is missing.', 'success' => false));
        }
        
        return $boxesDir;
    }

    /*
     * Merge to boxes of flashcards
     * 
     *  compare
     *  - boxPublicID > if not equal then do nothing
     *  do not touch
     *  - boxPublicID
     *  - boxID
     *  - creator
     *  - lastShared
     *  - maxLengthCardField
     *  - cardsColumnName
     *  - private_hasChanged
     *  lastChangedPublicMetaData
     *  - title
     *  - description
     *  - lastEditor
     *  lastChangedPrivateMetaData
     *  - cardsDecks
     *  - cardsDeckWaitExponent
     *  - cardsRepetitionsPerDeck
     *  - private_sortColumn
     *  - private_sortReverse
     *  - private_filter
     *  - private_visibleColumns
     *  - private_switch_learn_direction
     *  - private_switch_learn_all
     *  calculate
     *  - size
     *  cards
     *  0 - id = creation timestamp, milliseconds, Integer > do not touch
     *  1 - Language A, String > last modified content
     *  2 - Language B, String > last modified content
     *  3 - Description, String > last modified content
     *  4 - Tags, "Lesson 010.03" or anything else, String > last modified content
     *  5 - last modified content, milliseconds, Integer
     *  6 - Deck, Integer from 0 to 6 but configurable > last modified progress
     *  7 - progress in deck default 0, Integer > last modified progress
     *  8 - How often learned (information for the user only), Integer > last modified progress
     *  9 - last modified progress, milliseconds, Integer
     *  10 - has local changes, Boolean > use to create new box to send
     *  
     * @param $boxLocal array from local DB
     * @param $boxRemote array received to merge with box in DB
     */
    function flashcards_merge($boxLocal, $boxRemote, $is_private = true) {
        if($is_private) {
            if($boxLocal['boxID'] != $boxRemote['boxID']) {
                unset($boxRemote['cards']);
                return array('boxLocal' => $boxLocal, 'boxRemote' => $boxRemote);
            }
        }
        else {
            if($boxLocal['boxPublicID'] != $boxRemote['boxPublicID']) {
                unset($boxRemote['cards']);
                return array('boxLocal' => $boxLocal, 'boxRemote' => $boxRemote);
            }
        }
        $keysPublic = array('title', 'description', 'lastEditor', 'lastChangedPublicMetaData', 'lastShared');
        $keysPrivate = array('cardsDecks', 'cardsDeckWaitExponent', 'cardsRepetitionsPerDeck', 'private_sortColumn', 'private_sortReverse', 'private_filter', 'private_visibleColumns', 'private_switch_learn_direction', 'private_switch_learn_all', 'lastChangedPrivateMetaData');
        if($boxLocal['lastChangedPublicMetaData'] != $boxRemote['lastChangedPublicMetaData']) {
            if($boxLocal['lastChangedPublicMetaData'] > $boxRemote['lastChangedPublicMetaData']) {
                foreach ($keysPublic as &$key) {
                    $boxRemote[$key] = $boxLocal[$key];
                }
            } else {
                foreach ($keysPublic as &$key) {
                    $boxLocal[$key] = $boxRemote[$key];
                }
            }
        }
        if($is_private) {
            if($boxLocal['lastChangedPrivateMetaData'] != $boxRemote['lastChangedPrivateMetaData']) {
                if($boxLocal['lastChangedPrivateMetaData'] > $boxRemote['lastChangedPrivateMetaData']) {
                    foreach ($keysPrivate as &$key) {
                        $boxRemote[$key] = $boxLocal[$key];
                    }
                } else {
                    foreach ($keysPrivate as &$key) {
                        $boxLocal[$key] = $boxRemote[$key];
                    }
                }
            }
        }
        $cardsDB = $boxLocal['cards'];
        if(! $cardsDB) {
            $cardsDB = [];
        }
        $cardsRemote = $boxRemote['cards'];
        if(! $cardsRemote) {
            $cardsRemote = [];
        }
        $cardsDBadded = array();
        $cardsRemoteToUpload = array();
        foreach ($cardsRemote as &$cardRemote) {
            $isInDB = false;
            foreach ($cardsDB as &$cardDB) {
                if($cardRemote['content'][0] == $cardDB['content'][0]) {
                    $isInDB = true;
                    $isRemoteChanged = false;
                    if($cardDB['content'][5] != $cardRemote['content'][5]) {
                        if($cardDB['content'][5] > $cardRemote['content'][5]) {
                            for ($i = 1; $i < 6; $i++) {
                                $cardRemote['content'][$i] = $cardDB['content'][$i];
                                $isRemoteChanged = true;
                            }
                        } else {
                            for ($i = 1; $i < 6; $i++) {
                                $cardDB['content'][$i] = $cardRemote['content'][$i];
                            }
                        }
                    }
                    if($is_private) {
                        if($cardDB['content'][9] != $cardRemote['content'][9]) {
                            if($cardDB['content'][9] > $cardRemote['content'][9]) {
                                for ($i = 6; $i < 10; $i++) {
                                    $cardRemote['content'][$i] = $cardDB['content'][$i];
                                    $isRemoteChanged = true;
                                }
                            } else {
                                for ($i = 6; $i < 10; $i++) {
                                    $cardDB['content'][$i] = $cardRemote['content'][$i];
                                }
                            }
                        }
                    }
                    if($isRemoteChanged === true) {
                        array_push($cardsRemoteToUpload, $cardDB);
                    }
                    break;
                }
            }
            if(!$isInDB) {
                if(!$is_private) {
                    for ($i = 6; $i < 10; $i++) {
                        $cardRemote['content'][$i] = 0;
                    }
                    $cardRemote['content'][10] = false;
                }
                array_push($cardsDBadded, $cardRemote);
            }
        }
        // Add cards from local DB that are not in the remote cards
        $lastShared = $boxRemote['lastShared'];
        foreach ($cardsDB as &$cardDB) {
            $isInRemote = false;
            foreach ($cardsRemote as &$cardRemote) {
                if($cardRemote[0] == $cardDB[0]) {
                    $isInRemote = true;
                    break;
                }
            }
            if(!$isInRemote) {
                if($lastShared < $cardDB['content'][5]) {
                    array_push($cardsRemoteToUpload, $cardDB);
                } else if($lastShared < $cardDB['content'][9]) {
                    array_push($cardsRemoteToUpload, $cardDB);
                }
            }
        }
        // Check if the same user change a card on a different client (browser)
        $cardsDB = array_merge($cardsDB, $cardsDBadded);
        $boxLocal['size'] = count($cardsDB);
        $boxRemote['size'] = count($cardsDB);
        $boxLocal['cards'] = $cardsDB;
        $boxRemote['cards'] = $cardsRemoteToUpload; // send changed or new cards only
        return array('boxLocal' => $boxLocal, 'boxRemote' => $boxRemote);
    }
    
    function flashcards_merge_test() {
        $boxIn1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219599,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219599,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[]}';
        $boxIn2 = '{"boxID":"b2b2b2","title":"a2aaaaaaaaaaa","description":"A2aaaaaaaaaaaa","creator":"bMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"dMan","lastChangedPublicMetaData":1531058219599,"maxLengthCardField":1000,"cardsDecks":"6","cardsDeckWaitExponent":"2","cardsRepetitionsPerDeck":"1","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219599,"private_sortColumn":1,"private_sortReverse":false,"private_filter":["b","","","","","","","","","",""],"private_visibleColumns":[true,false,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":true,"private_switch_learn_all":true,"private_hasChanged":false,"cards":[]}';
        $box1 = json_decode($boxIn1, true);
        $box2 = json_decode($boxIn2, true);
        $boxCompare2 = '{"boxID":"b2b2b2","title":"a2aaaaaaaaaaa","description":"A2aaaaaaaaaaaa","creator":"bMan","lastShared":0,"boxPublicID":"b","size":2,"lastEditor":"dMan","lastChangedPublicMetaData":1531058219599,"maxLengthCardField":1000,"cardsDecks":"6","cardsDeckWaitExponent":"2","cardsRepetitionsPerDeck":"1","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219599,"private_sortColumn":1,"private_sortReverse":false,"private_filter":["b","","","","","","","","","",""],"private_visibleColumns":[true,false,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":true,"private_switch_learn_all":true,"private_hasChanged":false}';
        $box2['boxPublicID'] = 'b';
        $boxes = $this->flashcards_merge($box1, $box2, false);
        $boxOut1 = json_encode($boxes['boxLocal']);
        $boxOut2 = json_encode($boxes['boxRemote']);
        if($boxOut1 !== $boxIn1) {
            return false;
        }
        if($boxOut2 !== $boxCompare2) {
            return false;
        }
        // Nothing changed
        $box2['boxPublicID'] = $box1['boxPublicID'];
        $boxCompare2 = '{"boxID":"b2b2b2","title":"a2aaaaaaaaaaa","description":"A2aaaaaaaaaaaa","creator":"bMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"dMan","lastChangedPublicMetaData":1531058219599,"maxLengthCardField":1000,"cardsDecks":"6","cardsDeckWaitExponent":"2","cardsRepetitionsPerDeck":"1","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219599,"private_sortColumn":1,"private_sortReverse":false,"private_filter":["b","","","","","","","","","",""],"private_visibleColumns":[true,false,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":true,"private_switch_learn_all":true,"private_hasChanged":false}';
        $boxes = $this->flashcards_merge($box1, $box2);
        $boxOut1 = json_encode($boxes['boxLocal']);
        $boxOut2 = json_encode($boxes['boxRemote']);
        if($boxOut1 !== $boxIn1) {
            return false;
        }
        if($boxOut2 !== $boxCompare2) {
            return false;
        }
        // Public and private meta data
        $boxIn1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219599,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[]}';
        $boxIn2 = '{"boxID":"a1a1a1","title":"a2aaaaaaaaaaa","description":"A2aaaaaaaaaaaa","creator":"bMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"dMan","lastChangedPublicMetaData":1531058219599,"maxLengthCardField":1000,"cardsDecks":"6","cardsDeckWaitExponent":"2","cardsRepetitionsPerDeck":"1","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":1,"private_sortReverse":false,"private_filter":["b","","","","","","","","","",""],"private_visibleColumns":[true,false,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":true,"private_switch_learn_all":true,"private_hasChanged":false,"cards":[]}';
        $box1 = json_decode($boxIn1, true);
        $box2 = json_decode($boxIn2, true);
        $boxCompare1 = '{"boxID":"a1a1a1","title":"a2aaaaaaaaaaa","description":"A2aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":0,"lastEditor":"dMan","lastChangedPublicMetaData":1531058219599,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219599,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[]}';
        $boxCompare2 = '{"boxID":"a1a1a1","title":"a2aaaaaaaaaaa","description":"A2aaaaaaaaaaaa","creator":"bMan","lastShared":0,"boxPublicID":1531058219599,"size":0,"lastEditor":"dMan","lastChangedPublicMetaData":1531058219599,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219599,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":false,"cards":[]}';
        $boxes = $this->flashcards_merge($box1, $box2);
        $boxOut1 = json_encode($boxes['boxLocal']);
        $boxOut2 = json_encode($boxes['boxRemote']);
        if($boxOut1 !== $boxCompare1) {
            return false;
        }
        if($boxOut2 !== $boxCompare2) {
            return false;
        }
        // Public and private meta data the other way around
        $boxIn1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219599,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[]}';
        $boxIn2 = '{"boxID":"a1a1a1","title":"a2aaaaaaaaaaa","description":"A2aaaaaaaaaaaa","creator":"bMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"dMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"6","cardsDeckWaitExponent":"2","cardsRepetitionsPerDeck":"1","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219599,"private_sortColumn":1,"private_sortReverse":true,"private_filter":["b","","","","","","","","","",""],"private_visibleColumns":[true,false,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":true,"private_switch_learn_all":true,"private_hasChanged":false,"cards":[]}';
        $box1 = json_decode($boxIn1, true);
        $box2 = json_decode($boxIn2, true);
        $boxCompare1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":0,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219599,"maxLengthCardField":1000,"cardsDecks":"6","cardsDeckWaitExponent":"2","cardsRepetitionsPerDeck":"1","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219599,"private_sortColumn":1,"private_sortReverse":true,"private_filter":["b","","","","","","","","","",""],"private_visibleColumns":[true,false,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":true,"private_switch_learn_all":true,"private_hasChanged":true,"cards":[]}';
        $boxCompare2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"bMan","lastShared":0,"boxPublicID":1531058219599,"size":0,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219599,"maxLengthCardField":1000,"cardsDecks":"6","cardsDeckWaitExponent":"2","cardsRepetitionsPerDeck":"1","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219599,"private_sortColumn":1,"private_sortReverse":true,"private_filter":["b","","","","","","","","","",""],"private_visibleColumns":[true,false,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":true,"private_switch_learn_all":true,"private_hasChanged":false,"cards":[]}';
        $boxes = $this->flashcards_merge($box1, $box2);
        $boxOut1 = json_encode($boxes['boxLocal']);
        $boxOut2 = json_encode($boxes['boxRemote']);
        if($boxOut1 !== $boxCompare1) {
            return false;
        }
        if($boxOut2 !== $boxCompare2) {
            return false;
        }
        // Add remote card to empty local cards
        $boxIn1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":0,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[]}';
        $boxIn2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":0,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230161,0,0,0,0,true]},{"content":[1531058231082,"b1","b1b","b1bb","b1bbb",1531058239161,2,4,6,8,true]}]}';
        $box1 = json_decode($boxIn1, true);
        $box2 = json_decode($boxIn2, true);
        $boxCompare1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230161,0,0,0,0,true]},{"content":[1531058231082,"b1","b1b","b1bb","b1bbb",1531058239161,2,4,6,8,true]}]}';
        $boxCompare2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[]}';
        $boxes = $this->flashcards_merge($box1, $box2);
        $boxOut1 = json_encode($boxes['boxLocal']);
        $boxOut2 = json_encode($boxes['boxRemote']);
        if($boxOut1 !== $boxCompare1) {
            return false;
        }
        if($boxOut2 !== $boxCompare2) {
            return false;
        }
        // Add local cards to empty remote cards
        $boxIn1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":999,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230161,0,0,0,0,true]},{"content":[1531058231082,"b1","b1b","b1bb","b1bbb",1531058239161,2,4,6,8,true]}]}';
        $boxIn2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":999,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[]}';
        $box1 = json_decode($boxIn1, true);
        $box2 = json_decode($boxIn2, true);
        $boxCompare1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230161,0,0,0,0,true]},{"content":[1531058231082,"b1","b1b","b1bb","b1bbb",1531058239161,2,4,6,8,true]}]}';
        $boxCompare2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230161,0,0,0,0,true]},{"content":[1531058231082,"b1","b1b","b1bb","b1bbb",1531058239161,2,4,6,8,true]}]}';
        $boxes = $this->flashcards_merge($box1, $box2);
        $boxOut1 = json_encode($boxes['boxLocal']);
        $boxOut2 = json_encode($boxes['boxRemote']);
        if($boxOut1 !== $boxCompare1) {
            return false;
        }
        if($boxOut2 !== $boxCompare2) {
            return false;
        }
        // change card values
        $boxIn1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"ax","aax","aaax","aaaax",1531058230161,0,0,0,1531058230162,true]},{"content":[1531058231082,"b1","b1b","b1bb","b1bbb",1531058239161,2,4,6,8,true]}]}';
        $boxIn2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230162,1,2,3,1531058230161,true]},{"content":[1531058231082,"b1","b1b","b1bb","b1bbb",1531058239161,2,4,6,8,true]}]}';
        $box1 = json_decode($boxIn1, true);
        $box2 = json_decode($boxIn2, true);
        $boxCompare1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230162,0,0,0,1531058230162,true]},{"content":[1531058231082,"b1","b1b","b1bb","b1bbb",1531058239161,2,4,6,8,true]}]}';
        $boxCompare2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230162,0,0,0,1531058230162,true]}]}';
        $boxes = $this->flashcards_merge($box1, $box2);
        $boxOut1 = json_encode($boxes['boxLocal']);
        $boxOut2 = json_encode($boxes['boxRemote']);
        if($boxOut1 !== $boxCompare1) {
            return false;
        }
        if($boxOut2 !== $boxCompare2) {
            return false;
        }
        // change card values the other way around
        $boxIn1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230162,1,2,3,1531058230161,true]},{"content":[1531058231082,"b1","b1b","b1bb","b1bbb",1531058239161,2,4,6,8,true]}]}';
        $boxIn2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"ax","aax","aaax","aaaax",1531058230161,0,0,0,1531058230162,true]},{"content":[1531058231082,"b1","b1b","b1bb","b1bbb",1531058239161,2,4,6,8,true]}]}';
        $box1 = json_decode($boxIn1, true);
        $box2 = json_decode($boxIn2, true);
        $boxCompare1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230162,0,0,0,1531058230162,true]},{"content":[1531058231082,"b1","b1b","b1bb","b1bbb",1531058239161,2,4,6,8,true]}]}';
        $boxCompare2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230162,0,0,0,1531058230162,true]}]}';
        $boxes = $this->flashcards_merge($box1, $box2);
        $boxOut1 = json_encode($boxes['boxLocal']);
        $boxOut2 = json_encode($boxes['boxRemote']);
        if($boxOut1 !== $boxCompare1) {
            return false;
        }
        if($boxOut2 !== $boxCompare2) {
            return false;
        }
        // add public card to box 1 (local)
        $boxIn1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true}';
        $boxIn2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"ax","aax","aaax","aaaax",1531058230161,0,0,0,1531058230162,true]},{"content":[1531058231082,"b1","b1b","b1bb","b1bbb",1531058239161,2,4,6,8,true]}]}';
        $box1 = json_decode($boxIn1, true);
        $box2 = json_decode($boxIn2, true);
        $boxCompare1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"ax","aax","aaax","aaaax",1531058230161,0,0,0,0,false]},{"content":[1531058231082,"b1","b1b","b1bb","b1bbb",1531058239161,0,0,0,0,false]}]}';
        $boxCompare2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":0,"boxPublicID":1531058219599,"size":2,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[]}';
        $boxes = $this->flashcards_merge($box1, $box2, false);
        $boxOut1 = json_encode($boxes['boxLocal']);
        $boxOut2 = json_encode($boxes['boxRemote']);
        if($boxOut1 !== $boxCompare1) {
            return false;
        }
        if($boxOut2 !== $boxCompare2) {
            return false;
        }        
        
        // last shared younger than last learnt
        $boxIn1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058239161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230160,1,2,3,1531058230162,true]}]}';
        $boxIn2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058239161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"ax","aax","aaax","aaaax",1531058230162,0,0,0,1531058230160,true]}]}';
        $box1 = json_decode($boxIn1, true);
        $box2 = json_decode($boxIn2, true);
        $boxCompare1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058239161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"ax","aax","aaax","aaaax",1531058230162,1,2,3,1531058230162,true]}]}';
        $boxCompare2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058239161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"ax","aax","aaax","aaaax",1531058230162,1,2,3,1531058230162,true]}]}';
        $boxes = $this->flashcards_merge($box1, $box2);
        $boxOut1 = json_encode($boxes['boxLocal']);
        $boxOut2 = json_encode($boxes['boxRemote']);
        if($boxOut1 !== $boxCompare1) {
            return false;
        }
        if($boxOut2 !== $boxCompare2) {
            return false;
        }    
        
        // last shared younger than last edit
        $boxIn1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058239161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230162,1,2,3,1531058230160,true]}]}';
        $boxIn2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058239161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"ax","aax","aaax","aaaax",1531058230160,0,0,0,1531058230162,true]}]}';
        $box1 = json_decode($boxIn1, true);
        $box2 = json_decode($boxIn2, true);
        $boxCompare1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058239161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230162,0,0,0,1531058230162,true]}]}';
        $boxCompare2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058239161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230162,0,0,0,1531058230162,true]}]}';
        $boxes = $this->flashcards_merge($box1, $box2);
        $boxOut1 = json_encode($boxes['boxLocal']);
        $boxOut2 = json_encode($boxes['boxRemote']);
        if($boxOut1 !== $boxCompare1) {
            return false;
        }
        if($boxOut2 !== $boxCompare2) {
            return false;
        } 
        
        // last shared older than last edit and last learnt
        $boxIn1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058239161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230160,1,2,3,1531058230160,true]}]}';
        $boxIn2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058239161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true}';
        $box1 = json_decode($boxIn1, true);
        $box2 = json_decode($boxIn2, true);
        $boxCompare1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058239161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230160,1,2,3,1531058230160,true]}]}';
        $boxCompare2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058239161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[]}';
        $boxes = $this->flashcards_merge($box1, $box2);
        $boxOut1 = json_encode($boxes['boxLocal']);
        $boxOut2 = json_encode($boxes['boxRemote']);
        if($boxOut1 !== $boxCompare1) {
            return false;
        }
        if($boxOut2 !== $boxCompare2) {
            return false;
        }
        
        // last shared older than last edit
        $boxIn1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058230161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230162,1,2,3,1531058230160,true]}]}';
        $boxIn2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058230161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true}';
        $box1 = json_decode($boxIn1, true);
        $box2 = json_decode($boxIn2, true);
        $boxCompare1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058230161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230162,1,2,3,1531058230160,true]}]}';
        $boxCompare2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058230161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230162,1,2,3,1531058230160,true]}]}';
        $boxes = $this->flashcards_merge($box1, $box2);
        $boxOut1 = json_encode($boxes['boxLocal']);
        $boxOut2 = json_encode($boxes['boxRemote']);
        if($boxOut1 !== $boxCompare1) {
            return false;
        }
        if($boxOut2 !== $boxCompare2) {
            return false;
        }
        
        // last shared older than last learnt
        $boxIn1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058230161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230160,1,2,3,1531058230162,true]}]}';
        $boxIn2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058230161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true}';
        $box1 = json_decode($boxIn1, true);
        $box2 = json_decode($boxIn2, true);
        $boxCompare1 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058230161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230160,1,2,3,1531058230162,true]}]}';
        $boxCompare2 = '{"boxID":"a1a1a1","title":"a1aaaaaaaaaaa","description":"A1aaaaaaaaaaaa","creator":"aMan","lastShared":1531058230161,"boxPublicID":1531058219599,"size":1,"lastEditor":"cMan","lastChangedPublicMetaData":1531058219598,"maxLengthCardField":1000,"cardsDecks":"7","cardsDeckWaitExponent":"3","cardsRepetitionsPerDeck":"3","cardsColumnName":["Created","Side 1","Side 2","Description","Tags","modified","Deck","Progress","Counter","Learnt","Upload"],"lastChangedPrivateMetaData":1531058219598,"private_sortColumn":0,"private_sortReverse":false,"private_filter":["a","","","","","","","","","",""],"private_visibleColumns":[false,true,true,true,true,false,false,false,false,false,false],"private_switch_learn_direction":false,"private_switch_learn_all":false,"private_hasChanged":true,"cards":[{"content":[1531058221298,"a","aa","aaa","aaaa",1531058230160,1,2,3,1531058230162,true]}]}';
        $boxes = $this->flashcards_merge($box1, $box2);
        $boxOut1 = json_encode($boxes['boxLocal']);
        $boxOut2 = json_encode($boxes['boxRemote']);
        if($boxOut1 !== $boxCompare1) {
            return false;
        }
        if($boxOut2 !== $boxCompare2) {
            return false;
        }
        
        logger('tests all passed');
        return true;
    }

}
