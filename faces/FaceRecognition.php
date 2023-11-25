<?php

namespace Zotlabs\Module;

class FaceRecognition {

    function start($storeDirectory, $channel_id, $recognize, $rm_params, $probe_param) {
        $status_suffix = "";
        if ($recognize) {
            $status_suffix = $channel_id;
        }
        if (!$rm_params) {
            $isRunning = $this->isScriptRunning($status_suffix);
            if ($isRunning) {
                return;
            }
        } else {
            logger("Start the python script despite another one is still running. Set a new procid. This will stopp a running python script ", LOGGER_DEBUG);
        }
        $procid = random_string(10);
        set_config("faces", "status" . $status_suffix, "started " . datetime_convert() . " pid " . $procid);

        $logfile = get_config('logrot', 'logrotpath') . '/faces.log';
        $logfileparam = " --logfile " . $logfile;
        if (!$logfile) {
            $logfileparam = "";
        } else {
            if (!is_writable($logfile)) {
                logger("PLEASE CHECK PATH OR PERMISSIONS! Can not write log file " . $logfile, LOGGER_DEBUG);
                $logfileparam = "";
            }
        }
        $logEnabled = get_config('system', 'debugging');
        if (!$logEnabled) {
            $logfile = '';
        }
        $loglevel = (get_config('system', 'loglevel') ? get_config('system', 'loglevel') : LOGGER_NORMAL);

        $param_recognize = "";
        if ($recognize) {
            $param_recognize = " --recognize on";
        }

        @include('.htconfig.php');
        $cmd = escapeshellcmd("python3 " . getcwd() . "/addon/faces/py/faces.py"
                . " --host " . $db_host . " --user " . $db_user . " --pass " . $db_pass . " --db " . $db_data
                . " --imagespath " . $storeDirectory . " --channelid " . $channel_id
                . " --procid " . $procid
                . $param_recognize . " "
                . $rm_params . " "
                . $probe_param . " "
                . " --loglevel " . $loglevel . $logfileparam);

        logger('The pyhton script will be executed using the following command ...', LOGGER_NORMAL);
        // overwrite password
        $a = explode(" ", $cmd);
        $key = array_search("--pass", $a);
        $a[$key + 1] = "xxx";
        logger(implode(" ", $a), LOGGER_DEBUG);

        // python3 /var/www/mywebsite/addon/faces/py/faces.py --host 127.0.0.1 --user mywebsite --pass xxx --db mywebsite --imagespath /var/www/mywebsite/store/oj --channelid 0 --procid aeeed6d862 --first_result on --percent 2 --pixel 50 --training 224 --result 50 --statistics on --history on --loglevel 2 --logfile /var/www/log/faces.log --detectors retinaface,mtcnn,ssd,opencv,mediapipe --models Facenet512,ArcFace,VGG-Face,Facenet,OpenFace,DeepFace,SFace --distance_metrics euclidean,cosine,euclidean_l2 --demography Emotion,Age,Gender,Race
        exec($cmd . ' > /dev/null 2>/dev/null &');
    }

    function isScriptRunning($channel_id = "") {
        $txt = get_config("faces", "status" . $channel_id);
        if (!$txt) {
            logger('The python script was never started befor', LOGGER_DEBUG);
            return false;
        }
        logger('status face detection ' . $channel_id . ': ' . $txt, LOGGER_DEBUG);
        $a = explode(' ', $txt);
        $status = $a[0];

        if (sizeof($a) < 5) {
            logger("Status face detection " . $channel_id . ": not the expected format. Content='" . trim($txt) . "' . Size of array not 4 if splitted by a space. Assuming that the python script is not running.", LOGGER_DEBUG);
            return false;
        }

        if (strtolower($status) == "finished") {
            return false;
        }

        $updated = $a[1] . " " . $a[2];
        $elapsed = strtotime(datetime_convert()) - strtotime($updated); // both UTC
        if ($elapsed > 60 * 10) {
            // Th Python script writes a timestamp "updated" every 10 seconds to indicate it is still running.
            // Now we are 10 minutes away from the last "updated" written by the script.
            // It might be that the python script hangs or was stopped.
            $msg = 'The script (detection) did not finish yet. Please watch this condition. Why? It might be that the python script hangs, run into errors or was stopped externally. The last update by the script was at ' . $updated . '. This is more then 10 minutes ago. This is unusual because the script writes a time stamp every 10 seconds to indicate that it is still running.';
            logger($msg, LOGGER_DEBUG);
            $this->finished($channel_id);
            return false;
        }
        logger('The python script is still running. Last update ' . $channel_id . ': ' . $updated, LOGGER_DEBUG);
        return true;
    }

    function stop($channel_id = "") {
        $procid = random_string(10);
        set_config("faces", "status" . $channel_id, "stopped " . datetime_convert() . " pid " . $procid);
    }

    function finished($channel_id = "") {
        $procid = random_string(10);
        set_config("faces", "status" . $channel_id, "finished " . datetime_convert() . " pid " . $procid);
    }

}
