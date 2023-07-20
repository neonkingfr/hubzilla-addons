<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Channel;
use Zotlabs\Lib\Head;
use Zotlabs\Render\Theme;
use Zotlabs\Storage\BasicAuth;
use Zotlabs\Storage\Directory;
use Zotlabs\Storage\File;
use Zotlabs\Lib\Libsync;

class Faces extends Controller {

    private $is_owner;
    private $can_write;
    private $owner;
    private $observer;
    private $addonDirName = "faces";
    private $probeDirName = "probe";
    private $fileNameEmbeddings = "faces.gzip";
    private $fileNameFaces = "faces.json";
    private $fileNameNames = "names.json";
    private $fileNameFacesStatistic = "face_statistics.csv";
    private $fileNameModelsStatistic = "model_statistics.csv";
    private $fileNameConfig = "config.json";
    private $fileNameThresholds = "thresholds.json";
    private $fileNameProbe = "probe.csv";
    private $fileNameMostSimilar = "faces_most_similar.gzip";
    private $fileNameShare = "share.json";
    private $filesData = [];
    private $files_faces = [];
    private $files_names = [];
    private $observerAuth;

    function init() {

        $this->observer = \App::get_observer();
        $this->checkOwner();
    }

    function get() {
        logger('reveived get request', LOGGER_DEBUG);

        //----------------------------------------------------------------------
        // permisson checks
        //----------------------------------------------------------------------
        if (!$this->owner) {
            if (local_channel()) { // if no channel name was provided, assume the current logged in channel
                $channel = \App::get_channel();
                logger('No nick but local channel - channel = ' . $channel, LOGGER_DEBUG);
                if ($channel && $channel['channel_address']) {
                    $nick = $channel['channel_address'];
                    goaway(z_root() . '/faces/' . $nick);
                }
            }
        }

        if (!$this->owner) {
            logger('No nick and no local channel', LOGGER_DEBUG);
            notice(t('Profile Unavailable.') . EOL);
            goaway(z_root());
        }

        if (is_null($this->observer)) {
            logger('observer unkown', LOGGER_DEBUG);
            goaway(z_root());
        }

        $status = $this->permChecks();
        if (!$status['status']) {
            logger('observer prohibited', LOGGER_DEBUG);
            notice($status['message'] . EOL);
            goaway(z_root());
        }

        $ret['status'] = true;
        $ret['message'] = "";

        $channel = \App::get_channel();

        // tell the browser about the log level
        $loglevel = -1;
        $logEnabled = get_config('system', 'debugging');
        if ($logEnabled) {
            $loglevel = (get_config('system', 'loglevel') ? get_config('system', 'loglevel') : LOGGER_NORMAL);
        }

        $me = false;

        if (argc() > 2) {
            switch (argv(2)) {
                case 'settings':
                    // API: /faces/nick/settings
                    $o = $this->showSettingsPage($loglevel);
                    return $o;
                case 'sharing':
                    // API: /faces/nick/sharing
                    $o = $this->showSharingPage($loglevel);
                    return $o;
                case 'thresholds':
                    // API: /faces/nick/thresholds
                    $o = $this->showThresholdsPage($loglevel);
                    return $o;
                case 'probe':
                    // API: /faces/nick/probe
                    $o = $this->showProbePage($loglevel);
                    return $o;
                case 'remove':
                    // API: /faces/nick/remove
                    $o = $this->showRemovePage($loglevel);
                    return $o;
                case 'help':
                    // API: /faces/nick/help
                    $o = $this->showHelpPage();
                    return $o;
                default:
                    break;
            }
        }

        //----------------------------------------------------------------------
        // fill some elements in the
        //----------------------------------------------------------------------


        $version = $this->getAppVersion();
        logger("App version is " . $version);

        head_add_css('/addon/faces/view/css/faces.css');
        $o .= replace_macros(get_markup_template('faces.tpl', 'addon/faces'), array(
            '$version' => $version,
            '$status' => $ret['status'],
            '$message' => $ret['message'],
            '$can_write' => $this->can_write ? 'true' : 'false',
            '$is_owner' => $this->is_owner ? 'true' : 'false',
            '$log_level' => $loglevel,
            '$submit' => t('Submit'),
        ));

        logger('returning page for faces.tpl', LOGGER_DEBUG);
        return $o;
    }

    function post() {
        $api = argv(2);
        logger('received post request api = ' . $api, LOGGER_DEBUG);
        $status = $this->permChecks();

        if (!$status['status']) {
            // Commented out notice because contacts try to download shared faces too.
            // notice($status['message'] . EOL); 
            logger('sending status=false' . $status['message'], LOGGER_NORMAL);
            json_return_and_die(array('status' => false, 'message' => $status['message']));
        }
        if (!$this->observer) {
            logger('sending status=false, Unknown observer. Please login.', LOGGER_NORMAL);
            json_return_and_die(array('status' => false, 'message' => 'Unknown observer. Please login.'));
        }

        if (argc() > 2) {
            if ($api === 'start') {
                // API: /faces/nick/start
                $this->startDetection('start', false);
            } elseif ($api === 'recognize') {
                // API: /faces/nick/recognize
                $this->startRecognition();
            } elseif ($api === 'results') {
                // API: /faces/nick/results
                $this->startDetection('results', false);
            } elseif ($api === 'status') {
                // API: /faces/nick/results
                $this->startDetection('results', false);
            } elseif ($api === 'share') {
                // API: /faces/nick/share
                $this->share();
            } elseif ($api === 'shared') {
                // API: /faces/nick/shared
                $this->storeShared();
            } elseif ($api === 'cleanupshared') {
                // API: /faces/nick/cleanupshared
                $this->cleanupShared();
            } elseif ($api === 'contacts') {
                // API: /faces/nick/contacts
                $this->sendContacts();
            } elseif ($api === 'name') {
                // API: /faces/nick/name
                $this->setName();
            } elseif ($api === 'config') {
                // API: /faces/nick/config
                $this->sendConfig();
            } elseif ($api === 'settings') {
                // API: /faces/nick/settings
                $this->setConfig();
            } elseif ($api === 'zoom') {
                // API: /faces/nick/zoom
                $this->setConfigZoom();
            } elseif ($api === 'remove') {
                // API: /faces/nick/remove
                $this->remove();
            } elseif ($api === 'rthresholds') {
                // API: /faces/nick/rthresholds
                $this->sendThresholds();
            } elseif ($api === 'thresholds') {
                // API: /faces/nick/thresholds
                $this->setThresholds();
            } elseif ($api === 'probe') {
                // API: /faces/nick/probe
                $this->startProbe();
            }
        }
    }

    private function checkOwner() {
        // Determine which channel's faces to display to the observer
        $nick = null;
        if (argc() > 1) {
            $nick = argv(1); // if the channel name is in the URL, use that
        }
        logger('nick = ' . $nick, LOGGER_DEBUG);
        $this->owner = channelx_by_nick($nick);
    }

    private function permChecks() {

        $owner_uid = $this->owner['channel_id'];

        if (!$owner_uid) {
            logger('Stop: No owner profil', LOGGER_DEBUG);
            return array('status' => false, 'message' => 'No owner profil');
        }

        if ($this->observer) {
            logger('observer = ' . $this->observer['xchan_addr'], LOGGER_NORMAL);
        } else {
            logger('observer not known (null)', LOGGER_NORMAL);
        }

        $this->is_owner = ($this->observer['xchan_hash'] && $this->observer['xchan_hash'] == $this->owner['xchan_hash']);
        if ($this->is_owner) {
            logger('observer = owner', LOGGER_DEBUG);
        } else {
            logger('observer != owner', LOGGER_DEBUG);
        }

        if (!Apps::addon_app_installed($owner_uid, 'faces')) {
            $msg = "Owner profile " . $this->owner['xchan_addr'] . " has addon not installed";
            logger('Stop: ' . $msg, LOGGER_DEBUG);
            return array('status' => false, 'message' => $msg);
        }

        // Leave this check because the observer needs permissions to view photos too
        if (!perm_is_allowed($owner_uid, get_observer_hash(), 'view_storage')) {
            logger('Stop: Permission view storage denied', LOGGER_DEBUG);
            return array('status' => false, 'message' => 'Permission view storage denied');
        }

        $this->can_write = perm_is_allowed($owner_uid, get_observer_hash(), 'write_storage');
        logger('observer can write: ' . $this->can_write, LOGGER_DEBUG);

        return array('status' => true);
    }

    function getAppVersion() {
        $r = q("SELECT app.app_version FROM app WHERE app.app_name = 'Faces' and app.app_channel = %d", $this->owner['channel_id']);
        if (!$r) {
            return "";
        }
        return $r[0]["app_version"];
    }

    function isMe() {
        if (argc() > 3) {
            $api = argv(3);
            if ($api === 'me') {
                return true;
            }
        }
        return false;
    }

    private function startDetection($action, $rm_params) {

        require_once('FaceRecognition.php');
        $fr = new FaceRecognition();

        if (!$rm_params) {
            if ($fr->isScriptRunning() && $action !== 'start') {
                //notice('Face detection is still busy' . EOL);
                logger('sending status=true, face detection is still busy', LOGGER_NORMAL);
                json_return_and_die(array('status' => true, 'message' => 'Face detection is still busy'));
            }
        }

        $contacts = $this->getContacts();

        $is_touch = false;
        if ($action === 'results') {
            $is_touch = true;
        }

        $block = (get_config('faces', 'block_python') ? get_config('faces', 'block_python') : false);

        $this->prepareFiles($is_touch);
        $config = $this->getConfig();
        $immediatly = $config["immediatly"][0][1] ? $config["immediatly"][0][1] : false;
        $sort_exif = $config["exif"][0][1] ? $config["exif"][0][1] : false;
        $sort_ascending = $config["ascending"][0][1] ? $config["ascending"][0][1] : false;
        $zoom = $config["zoom"][0][1] ? $config["zoom"][0][1] : 2;

        if (!$this->can_write) {
            //notice('no permission to start the detection' . EOL);
            // Show the images if the page is reloaded
            logger("sending status=true, no permission to start the detection, faces: " . json_encode($this->files_faces), LOGGER_NORMAL);
            json_return_and_die(array(
                'status' => true,
                'names' => $this->files_faces,
                'names_waiting' => $this->files_names,
                'immediatly' => $immediatly,
                'sort_exif' => $sort_exif,
                'sort_ascending' => $sort_ascending,
                'zoom' => $zoom,
                'python_blocked' => $block,
                'contacts' => $contacts,
                'me' => $this->observer["xchan_hash"],
                'message' => "ok"));
        }

        if ($fr->isScriptRunning() && $action === 'start') {
            // Show the images if the page is reloaded
            logger("sending status=true, faces: " . json_encode($this->files_faces), LOGGER_NORMAL);
            json_return_and_die(array(
                'status' => true,
                'names' => $this->files_faces,
                'names_waiting' => $this->files_names,
                'immediatly' => $immediatly,
                'sort_exif' => $sort_exif,
                'sort_ascending' => $sort_ascending,
                'zoom' => $zoom,
                'python_blocked' => $block,
                'contacts' => $contacts,
                'message' => "ok"));
        }

        if (!$block) {
            if ($action === 'start') {
                $storeDirectory = $this->getStoreDir();
                $recognize = false;
                $channel_id = $this->owner['channel_id'];
                $fr->start($storeDirectory, $channel_id, $recognize, $rm_params, "");
            }
            if ($rm_params) {
                return;
            }
        }

        logger("sending status=true, faces: " . json_encode($this->files_faces), LOGGER_NORMAL);

        json_return_and_die(array(
            'status' => true,
            'names' => $this->files_faces,
            'names_waiting' => $this->files_names,
            'immediatly' => $immediatly,
            'sort_exif' => $sort_exif,
            'sort_ascending' => $sort_ascending,
            'zoom' => $zoom,
            'python_blocked' => $block,
            'contacts' => $contacts,
            'me' => $this->observer["xchan_hash"],
            'message' => "ok"));
    }

    private function startRecognition() {
        if (!$this->can_write && !$this->isMe()) {
            //notice('no permission to start the recognition' . EOL);
            logger("sending status=ok, no permission to start the recognition", LOGGER_NORMAL);
            json_return_and_die(array('status' => true, 'message' => "no permission to start the recognition"));
        }

        $block = (get_config('faces', 'block_python') ? get_config('faces', 'block_python') : false);
        if ($block) {
            logger("sending status=ok, python is blocked, face recogntion not started", LOGGER_NORMAL);
            json_return_and_die(array('status' => true, 'message' => "ok, python is blocked, face recogntion not started"));
        }

        $immediatly = (get_config('faces', 'immediatly') ? get_config('faces', 'immediatly') : false);
        if (!$immediatly) {
            logger("sending status=ok, immediate search not activated, face recogntion not started", LOGGER_NORMAL);
            json_return_and_die(array('status' => true, 'message' => "ok, immediate search not activated, face recogntion not started"));
        }

        require_once('FaceRecognition.php');
        $fr = new FaceRecognition();

        $channel_id = $this->owner['channel_id'];
        if ($fr->isScriptRunning($channel_id)) {
            logger("sending status=ok, recognition is still running for this user", LOGGER_NORMAL);
            json_return_and_die(array('status' => true, 'message' => "ok, recognition is still running for this user"));
        }

        $storeDirectory = $this->getStoreDir();
        $recognize = true;
        $rm_params = "";
        $fr->start($storeDirectory, $channel_id, $recognize, $rm_params, "");

        logger("sending status=ok, face recognition started", LOGGER_NORMAL);
        json_return_and_die(array('status' => true, 'message' => "face recognition started"));
    }

    private function startProbe() {
        if (!$this->is_owner) {
            //notice('no permission to run probe' . EOL);
            logger("sending status=ok, no permission to run probe", LOGGER_NORMAL);
            return;
        }
        $block = (get_config('faces', 'block_python') ? get_config('faces', 'block_python') : false);
        if ($block) {
            notice('face recognition (python) is blocked on this server' . EOL);
            return;
        }
        $this->getAddonDir();  // create probe.json (results) if it does not exist

        require_once('FaceRecognition.php');
        $fr = new FaceRecognition();

        $channel_id = $this->owner['channel_id'];
        if ($fr->isScriptRunning($channel_id)) {
            logger("sending status=ok, face recognition is still busy", LOGGER_NORMAL);
            json_return_and_die(array(
                'status' => true,
                'message' => "another face recognition is still busy"));
        }

        $storeDirectory = $this->getStoreDir();
        $recognize = true;
        $rm_params = "";
        $fr->start($storeDirectory, $channel_id, $recognize, $rm_params, "--probe on ");

        logger("probe distance metrics was started", LOGGER_DEBUG);

        notice('probe started' . EOL);
    }

    private function getStoreDir() {
        $storeDirectory = getcwd() . "/store/" . $this->owner['channel_address'];
        return $storeDirectory;
    }

    private function prepareFiles($is_touch = false) {
        $userDir = $this->getUserDir();
        $this->getAddonDir($is_touch);

        $this->filesData = [
            $this->fileNameEmbeddings,
            $this->fileNameFaces,
            $this->fileNameNames
        ];
        $this->checkDataFiles($userDir, $userDir->getName(), $is_touch);
    }

    private function checkDataFiles(Directory $dir, String $path, $is_touch = false) {
        // The pyhton script is allowed to write to existing files only. It will
        // ignore images in directories where the data files are missing.
        $children = $dir->getChildren();
        $check = true;
        foreach ($children as $child) {
            if ($child instanceof File && $check) {
                if ($child->getContentType() === strtolower('image/jpeg') || $child->getContentType() === strtolower('image/png')) {

                    foreach ($this->filesData as $f) {
                        if (!$dir->childExists($f)) {
                            if ($this->can_write) {
                                $dir->createFile($f);
                            }
                        } else {
                            if ($is_touch) {
                                $this->touch($dir->getChild($f), $path);
                            }
                            if ($f === $this->fileNameNames) {
                                $this->files_names[] = $path . "/" . $this->fileNameNames;
                            } elseif ($f === $this->fileNameFaces) {
                                $this->files_faces[] = $path . "/" . $this->fileNameFaces;
                            }
                        }
                    }

                    $check = false;
                }
            } else if ($child instanceof Directory) {
                $p = $path . "/" . $child->getName();
                $this->checkDataFiles($child, $p, $is_touch);
            }
        }
    }

    private function touch(File $file, $path) {
        if (!$this->can_write) {
            return;
        }
        $fName = $file->getName();
        $pos = strpos($path, "/");
        if (str_starts_with($path, "/")) {
            $path = substr($path, 1);
        } elseif ($pos) {
            $path = substr($path, $pos + 1);
        }
        $displaypath = $path . "/" . $fName;
        $r = q("SELECT id, hash, os_path FROM attach WHERE display_path = '%s' AND uid = %d LIMIT 1",
                dbesc($displaypath),
                intval($this->owner['channel_id'])
        );
        $f = $r[0];
        if (!$f) {
            return;
        }
        $storeDir = $this->getStoreDir();
        $path_fs = $storeDir . DIRECTORY_SEPARATOR . $f["os_path"];
        date_default_timezone_set("UTC");
        $modified_fs = filemtime($path_fs);
        $modified_db = $file->getLastModified();
        // inside Code\Storage\File put(...) the edited time in the db is set
        // after the file is written to the file system. So it should be save
        // to compare the times here.
        if ($modified_fs <= $modified_db) {
            return;
        }
        // assume the file was written by the python scripts. Otherwise
        // the last modified time in the file system should not be greater
        // than the time in the database
        logger($displaypath . " was written by python. Set file size and last modified in database and synchronize to clones...", LOGGER_DEBUG);

        $edited = date("Y-m-d H:i:s", $modified_fs);
        $size = filesize($path_fs);

        $d = q("UPDATE attach SET filesize = '%s', edited = '%s' WHERE hash = '%s' AND uid = %d",
                dbesc($size),
                dbesc($edited),
                dbesc($f['hash']),
                intval($this->owner['channel_id'])
        );

        $channel = \App::get_channel();

        $sync = attach_export_data($channel, $f['hash']);

        if ($sync) {
            Libsync::build_sync_packet($channel['channel_id'], array('file' => array($sync)));
        }
    }

    private function getUserDir() {
        $rootDir = $this->getRootDir();
        $channelAddress = $this->owner['channel_address'];
        $userDir = $rootDir->getChild($channelAddress);
        if (!$userDir) {
            json_return_and_die(array('message' => 'No cloud directory for channel ' . $channelAddress, 'success' => false));
        }

        return $userDir;
    }

    private function getAddonDir($is_touch = false) {

        $userDir = $this->getUserDir();
        if (!$userDir->childExists($this->addonDirName)) {
            if ($this->can_write) {
                $userDir->createDirectory($this->addonDirName);
            }
        }

        $addonDir = $userDir->getChild($this->addonDirName);
        //$channelAddress = $this->owner['channel_address'];
        //$addonDir = new Directory('/' . $channelAddress . '/' . $this->addonDirName, [], $this->getAuth());

        $path = $channelAddress . DIRECTORY_SEPARATOR . $this->addonDirName;

        $filesAddonDir = [
            $this->fileNameFacesStatistic,
            $this->fileNameModelsStatistic,
            $this->fileNameThresholds,
            $this->fileNameProbe,
            $this->fileNameConfig,
            $this->fileNameMostSimilar,
            $this->fileNameShare
        ];

        foreach ($filesAddonDir as $f) {

            if (!$addonDir->childExists($f)) {
                if ($this->can_write) {
                    $addonDir->createFile($f);
                }
            } else {
                if ($is_touch) {
                    $this->touch($addonDir->getChild($f), $path);
                }
            }
        }

        return $addonDir;
    }

    private function prepareProbeDirs() {

        $channelAddress = $this->owner['channel_address'];
        $addonDir = new Directory('/' . $channelAddress . '/' . $this->addonDirName, [], $this->getAuth());

        $path = $channelAddress . DIRECTORY_SEPARATOR . $this->addonDirName . DIRECTORY_SEPARATOR . $this->probeDirName;

        if (!$addonDir->childExists($this->probeDirName)) {
            if ($this->can_write) {
                $addonDir->createDirectory($this->probeDirName);
            }
        }

        $dirs = ["unknown", "Jane", "Bob"];
        foreach ($dirs as $d) {
            if (!$addonDir->getChild($this->probeDirName)->childExists($d)) {
                if ($this->can_write) {
                    !$addonDir->getChild($this->probeDirName)->createDirectory($d);
                }
            }
        }
    }

    private function getConfigFile() {
        $addonDir = $this->getAddonDir();
        $confFile = null;
        if ($addonDir) {
            $confFile = $addonDir->getChild($this->fileNameConfig);
        }
        return $confFile;
    }

    private function getRootDir() {
        $rootDirectory = new Directory('/', [], $this->getAuth());
        $channelAddress = $this->owner['channel_address'];
        if (!$rootDirectory->childExists($channelAddress)) {
            logger("sending status=false, no cloud directory", LOGGER_NORMAL);
            json_return_and_die(array('message' => 'No cloud directory.', 'success' => false));
        }
        return $rootDirectory;
    }

    private function getAuth() {

        $b = $this->isMe();
        if ($this->observerAuth && !$b) {
            return $this->observerAuth;
        }
        $auth = new BasicAuth();
        if ($b) {
            $ob_hash = $this->owner['xchan_hash'];
            $auth->setCurrentUser($this->owner['channel_address']);
            $auth->channel_id = $this->owner['channel_id'];
            $auth->channel_hash = $this->owner['channel_hash'];
            $auth->channel_account_id = $this->owner['channel_account_id'];
            if ($this->owner['channel_timezone']) {
                $auth->setTimezone($this->owner['channel_timezone']);
            }
            $auth->observer = $ob_hash;
        } else {
            $ob_hash = get_observer_hash();
            if ($ob_hash) {
                if (local_channel()) {
                    $channel = \App::get_channel();
                    $auth->setCurrentUser($channel['channel_address']);
                    $auth->channel_id = $channel['channel_id'];
                    $auth->channel_hash = $channel['channel_hash'];
                    $auth->channel_account_id = $channel['channel_account_id'];
                    if ($channel['channel_timezone']) {
                        $auth->setTimezone($channel['channel_timezone']);
                    }
                }
                $auth->observer = $ob_hash;
                $this->observerAuth = $auth;
            }
        }

        return $auth;
    }

    private function getStatus() {
        $txt = get_config("faces", "status");
        if (!$txt) {
            logger('sending running=false , the python script was never started befor', LOGGER_NORMAL);
            json_return_and_die(array('running' => false));
        }
        logger('status face recognition: ' . $txt, LOGGER_DEBUG);
        $a = explode(' ', $txt);
        $status = $a[0];

        if (sizeof($a) < 5) {
            logger("sending running=false, status face recognition: not the expected format. Content='" . trim($txt) . "' . Size of array not 4 if splitted by a space. Assuming that the python script is not running.", LOGGER_NORMAL);
            json_return_and_die(array('message' => 'wrong format in database', 'running' => false));
        }

        $values["status"] = $status;
        $running = (strtolower($status) == "finished") ? false : true;

        $updated = $a[1] . " " . $a[2];
        $elapsed = strtotime(datetime_convert()) - strtotime($updated); // both UTC
        // UTC in ISO data"2015-03-25T12:00:00Z", T... seperator, Z... UTC
        $values["utc"] = $a[1] . "T" . $a[2] . "Z";
        $values["elapsed"] = $elapsed;

        $values["procid"] = $a[4];

        logger('sending running=.' . $running . ', values: ' . json_encode($values), LOGGER_NORMAL);
        json_return_and_die(array('running' => $running, 'status' => $values));
    }

    private function setName() {
        if (!$this->can_write && !$this->isMe()) {
            notice("No permission to write a name" . EOL);
            logger('sending status=false, message=no write permission', LOGGER_NORMAL);
            json_return_and_die(array('status' => false, 'message' => "no write permission"));
        }
        $face = $_POST["face"];
        if ($face) {
            logger('Received face ' . json_encode($face), LOGGER_DEBUG);
        } else {
            logger('sending status=false, parameter face was not received', LOGGER_NORMAL);
            json_return_and_die(array('status' => false, 'message' => "Parameter face was not received"));
        }

        $file = $face['file'];
        if (!$face['position']) {
            $msg = "sending status=false, received no face position to write a name. Received: " . json_encode($face);
            logger($msg, LOGGER_NORMAL);
            json_return_and_die(array('status' => false, 'message' => $msg));
        } elseif (!$file) {
            $msg = "received no file to write a name. Received: " . json_encode($face);
            logger('sending status=false, ' . $msg, LOGGER_NORMAL);
            json_return_and_die(array('status' => false, 'message' => $msg));
        } else {
            $i = strpos($file, "/", strlen("cloud"));
            $file = substr($file, $i);
            $dirname = pathinfo($file, PATHINFO_DIRNAME);
            $imgDir = new Directory($dirname, [], $this->getAuth());
            if (!$imgDir->childExists($this->fileNameNames)) {
                if ($this->can_write) {
                    $imgDir->createFile($this->fileNameNames);
                }
            }
            $names_file = $imgDir->getChild($this->fileNameNames);
            $chan_addr = $this->owner['channel_address'];
            $i = strpos($file, "/", strlen($chan_addr));
            $image = substr($file, $i + 1);
            ////////////
            require_once('Name.php');
            $writer = new Name();
            $success = $writer->write($names_file, $image, $face['name'], $face['id'], $face['position']);
            ////////////
            if (!$success) {
                $msg = "Failed to write name='" . $face['name'] . "' with id='" . $face['id'] . " for image='" . $image;
                logger('sending status=false, ' . $msg, LOGGER_NORMAL);
                json_return_and_die(array('status' => false, 'message' => $msg));
            }
            logger('sending status=true, name was written for face=' . json_encode($face), LOGGER_NORMAL);
            json_return_and_die(array('status' => true, 'message' => "name was written", 'face' => $face));
        }
    }

    private function showSettingsPage($loglevel) {
        if (!$this->is_owner) {
            notice('only the owner is allowed to change settings' . EOL);
        }

        $o .= replace_macros(get_markup_template('settings.tpl', 'addon/faces'), array(
            '$version' => $this->getAppVersion(),
            '$loglevel' => $loglevel,
        ));

        logger('returning page for settings.tpl', LOGGER_DEBUG);
        return $o;
    }

    private function showSharingPage($loglevel) {
        if (!$this->is_owner) {
            notice('only the owner is allowed to set sharing settings' . EOL);
        }

        $o .= replace_macros(get_markup_template('sharing.tpl', 'addon/faces'), array(
            '$version' => $this->getAppVersion(),
            '$loglevel' => $loglevel,
        ));

        logger('returning page for sharing.tpl', LOGGER_DEBUG);
        return $o;
    }

    private function showThresholdsPage($loglevel) {
        if (!$this->is_owner) {
            notice('only the owner can set thresholds' . EOL);
        }

        $o .= replace_macros(get_markup_template('thresholds.tpl', 'addon/faces'), array(
            '$version' => $this->getAppVersion(),
            '$loglevel' => $loglevel,
        ));
        logger('returning page for thresholds.tpl', LOGGER_DEBUG);
        return $o;
    }

    private function showProbePage($loglevel) {

        $this->getAddonDir(); // create probe.json (results) if it does not exist
        if (!$this->can_write || !$this->is_owner) {
            notice('only the owner can run probe' . EOL);
        } else {
            $this->prepareProbeDirs();
        }

        $o .= replace_macros(get_markup_template('probe.tpl', 'addon/faces'), array(
            '$version' => $this->getAppVersion(),
            '$loglevel' => $loglevel,
        ));

        logger('returning page for probe.tpl', LOGGER_DEBUG);
        return $o;
    }

    private function sendConfig() {
        $this->prepareFiles();
        $config = $this->getConfig();
        logger("Sending configuration... " . json_encode($config), LOGGER_DATA);
        json_return_and_die(array('config' => $config));
    }

    private function setConfig() {
        $config = $this->setConfigPrepare();
        if (!$config) {
            return;
        }

        $exclude = ["reset", "experimental"];
        $isText = ["percent", "pixel", "training", "result", "zoom", "closeness", "most_similar_number", "most_similar_percent"];
        foreach ($config as $name => $values) {
            for ($i = 0;
                    $i < sizeof($values);
                    $i++) {
                $elName = $values[$i][0];
                if (!$elName) {
                    continue;  // not every config value is configured in the frontend
                }
                if (in_array($elName, $exclude)) {
                    continue;
                }
                $received = $_POST[$elName];
                if ($received && !in_array($elName, $isText)) {
                    $config[$name][$i][1] = true;
                } elseif (in_array($elName, $isText)) {
                    if ($elName === "closeness") {
                        if (($tmp = strstr($received, ',')) !== false) {
                            $received = substr($tmp, 1);   // jRange makes 0,66 from 66
                        } 
                    }
                    $config[$name][$i][1] = $received;
                } else {
                    $config[$name][$i][1] = false;
                }
            }
        }

        $this->setConfigWrite($config);
    }

    private function setConfigZoom() {
        $received = $_POST["zoom"];
        if (!$received) {
            json_return_and_die(array('status' => false, 'message' => "missing parameter zoom"));
            return;
        }
        $config = $this->setConfigPrepare();
        if (!$config) {
            json_return_and_die(array('status' => false, 'message' => "failed to read config file"));
            return;
        }
        $config["zoom"][0][1] = $received;
        $this->setConfigWrite($config);

        logger("sending status=true, zoom", LOGGER_NORMAL);
        json_return_and_die(array(
            'status' => true,
            'message' => "zoom was set"));
    }

    private function setConfigShare() {
        $received = $_POST["closeness"];
        if (!$received) {
            return;
        }
        $config = $this->setConfigPrepare();
        if (!$config) {
            return;
        }
        $config["closeness"][0][1] = $received;
        $this->setConfigWrite($config);
    }

    private function setConfigPrepare() {
        if (!$this->is_owner) {
            //notice('only the owner is allowed to change settings' . EOL);
            false;
        }
        $this->prepareFiles();
        $config = $this->getConfig();
        return $config;
    }

    private function setConfigWrite($config) {
        require_once('FaceRecognition.php');
        $fr = new FaceRecognition();
        $fr->stop();

        $configFile = $this->getConfigFile();
        require_once('FaceConfiguration.php');
        $fc = new FaceConfiguration();
        $fc->write($configFile, $config);

        $fr->finished();
    }

    private function getThresholds() {
        require_once('Thresholds.php');
        $th = new FaceThresholds();

        $thresholds = [];

        $file = $this->getThresholdsFile();
        if (!$file) {
            $thresholds = $th->getDefaults();
            logger("using default thresholds because failed to read file", LOGGER_DEBUG);
        } else {
            $thresholds = $th->read($file);
        }
        logger("did read thresholds " . json_encode($thresholds), LOGGER_DEBUG);
        return $thresholds;
    }

    private function sendThresholds() {
        $this->prepareFiles();
        $thresholds = $this->getThresholds();
        require_once('Thresholds.php');
        $th = new FaceThresholds();
        $defaults = $th->getDefaults();
        logger("Sending tresholds... " . json_encode($thresholds), LOGGER_NORMAL);
        json_return_and_die(array(
            'thresholds' => $thresholds,
            'defaults' => $defaults));
    }

    private function getThresholdsFile() {
        $addonDir = $this->getAddonDir();
        $thresholdsFile = null;
        if ($addonDir) {
            $thresholdsFile = $addonDir->getChild($this->fileNameThresholds);
        }
        return $thresholdsFile;
    }

    private function setThresholds() {
        if (!$this->is_owner) {
            return;
        }
        $this->prepareFiles();
        //$thresholds = $this->getThresholds();

        require_once('Thresholds.php');
        $th = new FaceThresholds();
        $defaults = $th->getDefaults();
        foreach ($defaults as $model => $model_metrics) {
            foreach ($model_metrics as $metric => $value) {
                $received = $_POST[$model . "_" . $metric];
                if (is_numeric($received)) {
                    $defaults[$model][$metric] = $received;
                }
            }
        }
        $file = $this->getThresholdsFile();
        $th->write($file, $defaults, true);
    }

    private function getConfig() {
        require_once('FaceConfiguration.php');
        $fc = new FaceConfiguration();

        $config = [];

        $configFile = $this->getConfigFile();
        if (!$configFile) {
            $config = $fc->getDefaultConfig();
            logger("using default configuration because failed to read config file", LOGGER_DEBUG);
        } else {
            $config = $fc->read($configFile, $this->can_write);
        }
        logger("did read configuration " . json_encode($config), LOGGER_DATA);
        return $config;
    }

    private function showRemovePage($loglevel) {
        if (!$this->is_owner) {
            notice('only the owner can remove results' . EOL);
        }

        $block = (get_config('faces', 'block_python') ? get_config('faces', 'block_python') : false);
        if ($block) {
            $o = "Not available. Why? The Python scripts for face detection and recognition are blocked on this server.";
            return $o;
        }

        $o .= replace_macros(get_markup_template('remove.tpl', 'addon/faces'), array(
            '$version' => $this->getAppVersion(),
            '$loglevel' => $loglevel,
        ));

        logger('returning page for remove.tpl', LOGGER_DEBUG);
        return $o;
    }

    private function remove() {
        if (!$this->is_owner) {
            logger("sending status=false, no permission to remove results", LOGGER_NORMAL);
            return;
        }

        $config = $this->getConfig();
        $params = "";
        $types = ["detectors", "models"];
        foreach ($types as $type) {
            $elements = $config[$type];
            $param = "";
            foreach ($elements as $element) {
                $name = $element[0];
                $received = $_POST[$name];
                if ($received) {
                    if ($param !== "") {
                        $param .= ",";
                    }
                    $param .= $name;
                }
            }
            if ($param !== "") {
                $params .= " --rm_" . $type . " " . $param;
            }
        }
        $rm_names = $_POST["names"];
        if ($rm_names) {
            $params .= " --rm_names on";
        }
        if ($params !== "") {
            $this->startDetection("start", $params);
        }
    }

    private function showHelpPage() {
        head_add_css('/addon/faces/view/css/faces.css');

        $o .= replace_macros(get_markup_template('help.tpl', 'addon/faces'), array(
            '$version' => $this->getAppVersion(),
        ));
        logger('returning page for help.tpl', LOGGER_DEBUG);
        return $o;
    }

    private function sendContacts() {
        $this->setConfigShare();

        $contacts = $this->getContacts();

        logger("sending status=true, contacts", LOGGER_NORMAL);
        logger("contacts: " . json_encode($contacts), LOGGER_DATA);
        json_return_and_die(array(
            'status' => true,
            'contacts' => $contacts));
    }

    private function getContacts() {
        $uid = $this->owner["channel_id"];
        load_contact_links($uid);
        $cs = App::$contacts;
        // xchan_url = "http://localhost/channel/a"
        // xchan_name = "a"
        $conf = $this->getConfig();
        foreach ($cs as $c) {
            if ($this->isCloseEnough($c['xchan_hash'], $conf)) {
                $url = $c['xchan_url'];
                $url_shared_faces = str_replace("/channel/", "/faces/", $url) . "/share";
                
                // Mimic the xchan_addr "nick@domain" of Streams to keep
                // the JavaScript code consistent between Hubzilla and Streams
                $arr = explode("/",$c['xchan_url']);
                $c['xchan_addr'] = $c['xchan_name'] . "@" . $arr[2];  // this mimics the 
                
                $ret[$c['xchan_hash']] = [
                    $c['xchan_hash'],
                    $c['xchan_name'],
                    $c['xchan_addr'],
                    $url_shared_faces,
                    ($c['xchan_hash'] === $this->owner['xchan_hash'])
                ];
            }
        }
        return $ret;
    }

    private function share() {
        // decide what faces to share depending on observer
        // - observer must be in contact list
        // - observer must have a close relationship (proximity slider)
        $conf = $this->getConfig();
        $ob_xchan_hash = $this->observer['xchan_hash'];
        if ($this->isContact() && $this->isCloseEnough($ob_xchan_hash, $conf)) {
            $this->sendSharedFaces();
        }
        $msg = "not connected or not close enough (affinity slider).";
        logger("sending status=false, " . $msg, LOGGER_NORMAL);
        json_return_and_die(array('status' => false, 'message' => $msg));
        return false;
    }

    private function isContact() {
        $observer_hash = $this->observer['xchan_hash'];
        $uid = $this->owner["channel_id"];
        load_contact_links($uid);
        $contacts = App::$contacts;
        foreach ($contacts as $contact) {
            if ($observer_hash === $contact['xchan_hash']) {
                return true;
            }
        }
        logger("not in contact list", LOGGER_DEBUG);
        return false;
    }

    private function isCloseEnough($observer_xchan_hash, $conf) {
        $closeness_min = $conf["closeness"][0][1];
        if (!$closeness_min) {
            // this might happen if addon is installed but never used, or
            // the config file was deleted
            logger("closeness not set for this observer" . $msg, LOGGER_DEBUG);
            return false;
        }

        $r = q("SELECT abook_closeness from abook WHERE abook_channel = %d AND abook_xchan = '%s'",
                intval($this->owner["channel_id"]),
                dbesc($observer_xchan_hash)
        );
        if ($r) {
            $closeness_found = $r[0]["abook_closeness"];
            if ($closeness_found <= $closeness_min) {
                // default is 80 if affinity addon is not installed or affinity is not set for contact
                return true;
            }
        }
        logger("contact " . $observer_xchan_hash . " not close enough, found " . $closeness_found . " but need " . $closeness_min, LOGGER_DEBUG);
        return false;
    }

    private function sendSharedFaces() {
        $filename = "share.json";
        $addonDir = $this->getAddonDir();
        $is_file = $addonDir->childExists($filename);
        if (!$is_file) {
            $msg = "no file " . $this->fileNameShare;
            logger("sending status=false, message: " . $msg, LOGGER_NORMAL);
            json_return_and_die(array('status' => true, 'message' => $msg));
        }
        $file = $addonDir->getChild($filename);
        $stream = $file->get();
        $contents = stream_get_contents($stream);
        $faces = json_decode($contents, true);
        logger("sending status=true, sending shared faces", LOGGER_NORMAL);
        logger($contents, LOGGER_DATA);
        json_return_and_die(array('status' => true, 'message' => "ok", 'faces' => $faces));
    }

    /**
     * Store faces from contacts.
     * - parse post param that sends the faces
     * - iterate through every faces and remove faces of contacts that are not
     *   close enough (set by affinity slider).
     *   Why?
     *     Be fair to your contacts.
     *     If you do not share your own faces with a contact then in turn
     *     you should not be allowed to tag this contact in your images.
     */
    private function storeShared() {
        if (!$this->can_write) {
            logger("sending status=false, no write permission", LOGGER_NORMAL);
            json_return_and_die(array('status' => false, 'message' => "no write permission"));
        }

        if (!$_POST["faces"]) {
            logger('sending status=false, no face received', LOGGER_NORMAL);
            json_return_and_die(array('status' => false, 'message' => "no face received"));
        }
        $s = $_POST["faces"];
        $faces = json_decode($_POST["faces"], true);

        $sender = $_POST["sender"];  // shortened xchan_hash
        if (!$sender) {
            $sender = "unknown-sender";
        }
        $source_url = $_POST["url"];
        if (!$source_url) {
            $source_url = "";
        }
        logger("storing faces that where shared by a contact and downloaded at " . $source_url, LOGGER_DEBUG);

        $conf = $this->getConfig();

        $indicesToRemove = [];
        $size = count($faces["name"]);
        for ($x = 0; $x < $size; $x++) {
            $faces["source"][$x] = $source_url;
            // If you use one of your contacts (connected channels) to tag faces
            // the xchan_hash of the contat is used as "name" to make the "name" unique.
            $xchan_hash = $faces["name"][$x];
            // TODO: The closeness all contacts could be requested from the database once
            //       to avoid sql requests for each contact. Furthermore a contact is checked
            //       several times if it has several entries iin the shared file what makes
            //       it even more time consuming.
            if (!$this->isCloseEnough($xchan_hash, $conf)) {
                $indicesToRemove[] = $x;
            }
        }
        if (sizeof($indicesToRemove) > 0) {
            foreach ($faces as $key => $value) {
                foreach ($indicesToRemove as $i) {
                    $tmp = $key;
                    $temp = $value;
                    $a = $faces[$key];
                    $b = $faces[$key][$i];
                    unset($faces[$key][$i]);
                }
            }
        }

        logger("Received shared faces from sender name (shortened xchan_hash) = " . $sender . " from url=" . $source_url, LOGGER_DEBUG);
        logger('faces: ' . json_encode($faces), LOGGER_DATA);

        $filename = "shared_" . $sender . ".json";

        $addonDir = $this->getAddonDir();
        $is_file = $addonDir->childExists($filename);
        if (!$is_file) {
            $addonDir->createFile($filename);
        }
        $file = $addonDir->getChild($filename);

        $json = json_encode($faces);
        $file->put($json);
        logger("wrote shared faces into file=" . $filename, LOGGER_NORMAL);

        json_return_and_die(array('status' => true, 'message' => "shared faces received and stored for name "));
    }

    /**
     * Delete files of contacts that are not close enough.
     * 
     * Main purpose of this function:
     * Be fair to your contacts.
     * If you do not share your own faces with a contact then in turn
     * you should not be allowed to tag this contact in your images.
     * 
     * Iterate over the files that contain faces shared by contacts. These files
     * where downloaded before from contacts. In the meantime you might have changed the
     * closness (affinity slider) for this contact. As result the contact is not
     * close enough anymore. For your contact this would have the effect that he
     * is not allowed to downloade your faces anymore.
     */
    function cleanupShared() {
        logger("check if files containing shared faces have to be cleaned up", LOGGER_DEBUG);
        if (!$this->can_write) {
            logger("sending status=false, no write permission", LOGGER_NORMAL);
            json_return_and_die(array('status' => false, 'message' => "no write permission"));
        }
        $contacts = $this->getContacts();
        $cs = [];
        foreach ($contacts as $key => $value) {
            $cs[] = substr($key, 0, 8);
        }
        $addonDir = $this->getAddonDir();
        $children = $addonDir->getChildren();
        foreach ($children as $child) {
            if ($child instanceof File) {
                if ($child->getContentType() === strtolower('application/json')) {
                    $fname = $child->getName();
                    if (!str_starts_with($fname, "shared_")) {
                        continue;
                    }
                    $s = str_replace("shared_", "", $fname);
                    if (!str_ends_with($s, ".json")) {
                        continue;
                    }
                    $shortened_xchan_hash = str_replace(".json", "", $s);
                    if (!in_array($shortened_xchan_hash, $cs)) {
                        logger("deleting file " . $fname, LOGGER_DEBUG);
                        $child->delete();
                    }
                }
            }
        }
        logger("sending status=true, ok", LOGGER_NORMAL);
        json_return_and_die(array('status' => true, 'message' => "ok"));
    }
}
