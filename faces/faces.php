<?php

use Zotlabs\Extend\Route;

/**
 * Name: Faces
 * Description: face recognition
 * Version: 1.0
 * Author: Tom Wiedenhöft
 * Maintainer: Tom Wiedenhöft
 *
 */
function faces_load() {
    Route::register('addon/faces/Mod_Faces.php', 'faces');
}

function faces_unload() {
    Route::unregister('addon/faces/Mod_Faces.php', 'faces');
}

function faces_plugin_admin(&$o) {

    $block = (get_config('faces', 'block_python') ? get_config('faces', 'block_python') : false);

    if (!$block) {
        stopRunningFaceRecognition();

        $pythoncheckmsg = "";
        $ret = testPythonVersion();
        if (!$ret['status']) {
            $pythoncheckmsg = "<p style=\"color:red;\">" . $ret['message'] . "</p>";
            set_config('faces', 'block_python', true);
            $block = true;
        } else {
            $pythoncheckmsg = "<p style=\"color:green;\">" . $ret['message'] . "</p>";
        }

        $deepfacecheckmsg = "";
        $ret = testDeepfaceVersion();
        if (!$ret['status']) {
            $deepfacecheckmsg = "<p style=\"color:red;\">" . $ret['message'] . "</p>";
            set_config('faces', 'block_python', true);
            $block = true;
        } else {
            $deepfacecheckmsg = "<p style=\"color:green;\">" . $ret['message'] . "</p>";
        }

        $mysqlconnectorcheckmsg = "";
        $ret = testMySQLConnectorVersion();
        if (!$ret['status']) {
            $mysqlconnectorcheckmsg = "<p style=\"color:red;\">" . $ret['message'] . "</p>";
            set_config('faces', 'block_python', true);
            $block = true;
        } else {
            $mysqlconnectorcheckmsg = "<p style=\"color:green;\">" . $ret['message'] . "</p>";
        }
        unblockFaceRecognition();
    }

    // set default values
    $detectors = get_config('faces', 'detectors');
    if (!$detectors) {
        $detectors = "retinaface";
        set_config('faces', 'detectors', $detectors);
    }
    $models = get_config('faces', 'models');
    if (!$models) {
        $models = 'Facenet512';
        set_config('faces', 'models', $models);
    }
    $demography = get_config('faces', 'demography');
    if (!$demography) {
        $demography = 'off';
        set_config('faces', 'demography', $demography);
    }
    $distance_metrics = get_config('faces', 'distance_metrics');
    if (!$distance_metrics) {
        $distance_metrics = 'cosine,euclidean_l2';
        set_config('faces', 'distance_metrics', $distance_metrics);
    }

    $zoom = get_config('faces', 'zoom');
    if (!$zoom) {
        $zoom = 3;
    }

    $experimental_allowed = get_config('faces', 'experimental_allowed') ? get_config('faces', 'experimental_allowed') : false;
    $immediatly = get_config('faces', 'immediatly') ? get_config('faces', 'immediatly') : false;

    $max_ram = get_config('faces', 'max_ram') ? get_config('faces', 'max_ram') : 80;

    $t = get_markup_template("admin.tpl", "addon/faces/");

    $o = replace_macros($t, array(
        '$submit' => "Submit and Test",
        '$pythoncheckmsg' => $pythoncheckmsg,
        '$deepfacecheckmsg' => $deepfacecheckmsg,
        '$mysqlconnectorcheckmsg' => $mysqlconnectorcheckmsg,
        '$ramcheckmsg' => get_config('faces', 'ramcheck'),
        '$block' => array('block', "block", $block, "Do not allow the python scripts to run on this server"),
        '$retinaface' => array('retinaface', "retinaface", str_contains($detectors, "retinaface"), "slow, most accurate"),
        '$mtcnn' => array('mtcnn', "mtcnn", str_contains($detectors, "mtcnn"), "accurate"),
        '$ssd' => array('ssd', "ssd", str_contains($detectors, "ssd"), "fast, less results"),
        '$mediapipe' => array('mediapipe', "mediapipe", str_contains($detectors, "mediapipe"), "Google"),
        '$opencv' => array('opencv', "opencv", str_contains($detectors, "opencv"), "fast, inaccurate"),
        '$Facenet512' => array('Facenet512', "Facenet512", str_contains($models, "Facenet512"), "accurate - by Google"),
        '$ArcFace' => array('ArcFace', "ArcFace", str_contains($models, "ArcFace"), "accurate"),
        '$VGGFace' => array('VGGFace', "VGG-Face", str_contains($models, "VGG-Face"), "fast"),
        '$Facenet' => array('Facenet', "Facenet", preg_match("/\bFacenet\b/", $models), "by Google"),
        '$OpenFace' => array('OpenFace', "OpenFace", str_contains($models, "OpenFace"), "fast"),
        '$DeepFace' => array('DeepFace', "DeepFace", str_contains($models, "DeepFace"), "by Facebook"),
        '$SFace' => array('SFace', "SFace", str_contains($models, "SFace"), "new"),
        '$cosine' => array('cosine', "cosine", str_contains($distance_metrics, "cosine"), "fast"),
        '$euclidean_l2' => array('euclidean_l2', "euclidean_l2", str_contains($distance_metrics, "euclidean_l2"), "sometimes more reliable"),
        '$euclidean' => array('euclidean', "euclidean", preg_match("/\beuclidean\b/", $distance_metrics), ""),
        '$Emotion' => array('Emotion', "Emotion", str_contains($demography, "Emotion"), ""),
        '$Age' => array('Age', "Age", str_contains($demography, "Age"), ""),
        '$Gender' => array('Gender', "Gender", str_contains($demography, "Gender"), ""),
        '$Race' => array('Race', "Race", str_contains($demography, "Race"), ""),
        '$zoom' => array('zoom', 'Zoom - Start Value', $zoom, 'Number of Images displayed in a Row (allowed values 1 - 6)'),
        '$experimental_allowed' => array('experimental_allowed', 'allow experimental mode', $experimental_allowed, 'Allow users to use more than one detector, model, distance metric and to analyse facial attributes (gender, race, emotion, age)'),
        '$immediatly' => array('immediatly', 'allow immediate search', $immediatly, 'Start the face recognition always immediatly after a user changed a name'),
        '$max_ram' => array('max_ram', 'maximum allowed ram', $max_ram, 'The python scripts will stop if the server ram exceeds this value in percent'),
    ));
}

function faces_plugin_admin_post() {

    $block = ((x($_POST, 'block')) ? x($_POST, 'block') : false);
    set_config('faces', 'block_python', $block);

    // detectors
    $detectors = [];

    $retinaface = ((x($_POST, 'retinaface')) ? true : false);
    if ($retinaface) {
        $detectors[] = 'retinaface';
    }
    $mtcnn = ((x($_POST, 'mtcnn')) ? true : false);
    if ($mtcnn) {
        $detectors[] = 'mtcnn';
    }
    $ssd = ((x($_POST, 'ssd')) ? true : false);
    if ($ssd) {
        $detectors[] = 'ssd';
    }
    $mediapipe = ((x($_POST, 'mediapipe')) ? true : false);
    if ($mediapipe) {
        $detectors[] = 'mediapipe';
    }
    $opencv = ((x($_POST, 'opencv')) ? true : false);
    if ($opencv) {
        $detectors[] = 'opencv';
    }

    if (!$retinaface && !$mtcnn && !$ssd && !$mediapipe && !$opencv) {
        // default
        $detectors[] = 'retinaface';
    }

    $detectorsconfig = implode(",", $detectors);
    set_config('faces', 'detectors', preg_replace('/\s+/', '', $detectorsconfig));

    // models
    $models = [];

    $Facenet512 = ((x($_POST, 'Facenet512')) ? true : false);
    if ($Facenet512) {
        $models[] = 'Facenet512';
    }

    $ArcFace = ((x($_POST, 'ArcFace')) ? true : false);
    if ($ArcFace) {
        $models[] = 'ArcFace';
    }

    $VGGFace = ((x($_POST, 'VGGFace')) ? true : false);
    if ($VGGFace) {
        $models[] = 'VGG-Face';
    }

    $Facenet = ((x($_POST, 'Facenet')) ? true : false);
    if ($Facenet) {
        $models[] = 'Facenet';
    }

    $OpenFace = ((x($_POST, 'OpenFace')) ? true : false);
    if ($OpenFace) {
        $models[] = 'OpenFace';
    }

    $DeepFace = ((x($_POST, 'DeepFace')) ? true : false);
    if ($DeepFace) {
        $models[] = 'DeepFace';
    }

    $SFace = ((x($_POST, 'SFace')) ? true : false);
    if ($SFace) {
        $models[] = 'SFace';
    }

    if (!$Facenet512 && !$ArcFace && !$VGGFace && !$Facenet && !$OpenFace && !$DeepFace && !$SFace) {
        // default
        $models[] = 'Facenet512';
    }

    $modelsconfig = implode(",", $models);
    set_config('faces', 'models', preg_replace('/\s+/', '', $modelsconfig));

    // distance_metrics
    $distance_metrics = [];

    $cosine = ((x($_POST, 'cosine')) ? true : false);
    if ($cosine) {
        $distance_metrics[] = 'cosine';
    }

    $euclidean_l2 = ((x($_POST, 'euclidean_l2')) ? true : false);
    if ($euclidean_l2) {
        $distance_metrics[] = 'euclidean_l2';
    }

    $euclidean = ((x($_POST, 'euclidean')) ? true : false);
    if ($euclidean) {
        $distance_metrics[] = 'euclidean';
    }

    if (!$cosine && !$euclidean_l2 && !$euclidean) {
        $distance_metrics[] = 'cosine,euclidean_l2';
    }

    $metricsconfig = implode(",", $distance_metrics);
    set_config('faces', 'distance_metrics', preg_replace('/\s+/', '', $metricsconfig));

    // demography
    $demography = [];

    $Emotion = ((x($_POST, 'Emotion')) ? true : false);
    if ($Emotion) {
        $demography[] = 'Emotion';
    }

    $Age = ((x($_POST, 'Age')) ? true : false);
    if ($Age) {
        $demography[] = 'Age';
    }

    $Gender = ((x($_POST, 'Gender')) ? true : false);
    if ($Gender) {
        $demography[] = 'Gender';
    }

    $Race = ((x($_POST, 'Race')) ? true : false);
    if ($Race) {
        $demography[] = 'Race';
    }

    if (!$Emotion && !$Age && !$Gender && !$Race) {
        $demography[] = 'off';
    }

    $demographyconfig = implode(",", $demography);
    set_config('faces', 'demography', preg_replace('/\s+/', '', $demographyconfig));

    $zoom = ((x($_POST, 'zoom')) ? intval(trim($_POST['zoom'])) : 3);
    if ($zoom > 6) {
        $zoom = 6;
    } else if ($zoom < 1) {
        $zoom = 1;
    }
    set_config('faces', 'zoom', $zoom);
    logger("set zoom to " . $zoom, LOGGER_NORMAL);

    $max_ram = ((x($_POST, 'max_ram')) ? intval(trim($_POST['max_ram'])) : 80);
    if ($max_ram > 90) {
        $max_ram = 90;
    } else if ($max_ram < 10) {
        $max_ram = 10;
    }
    set_config('faces', 'max_ram', $max_ram);
    logger("set max_ram " . $max_ram, LOGGER_NORMAL);

    $experimental_allowed = ((x($_POST, 'experimental_allowed')) ? true : false);
    set_config('faces', 'experimental_allowed', $experimental_allowed);
    logger("set experimental_allowed to " . $experimental_allowed, LOGGER_NORMAL);

    $immediatly = ((x($_POST, 'immediatly')) ? true : false);
    set_config('faces', 'immediatly', $immediatly);
    logger("set immediatly to " . $immediatly, LOGGER_NORMAL);

    info(t('Settings updated.') . EOL);

    if ($block) {
        set_config('faces', 'ramcheck', "");
        return;
    }

    $detectors = get_config('faces', 'detectors');
    $models = get_config('faces', 'models');
    $demography = get_config('faces', 'demography');

    stopRunningFaceRecognition();

    // Check the configuration
    $ret = testDeepfaceModules($detectors, $models, $demography);

    // unblock execution of python script
    unblockFaceRecognition();

    // correct the configuration if nesseccary
    $d = $ret['d'];
    if (sizeof($d) > 0) {
        $detectors = implode(",", $d);
    } else {
        $detectors = "retinaface";
    }
    set_config('faces', 'detectors', $detectors);
    logger("set detectors to " . $detectors, LOGGER_NORMAL);
    $m = $ret['m'];
    if (sizeof($m) > 0) {
        $models = implode(",", $m);
    } else {
        $models = 'Facenet512';
    }
    set_config('faces', 'models', $models);
    logger("set models to " . $models, LOGGER_NORMAL);
    $dm = $ret['dm'];
    if (sizeof($dm) > 0) {
        $demography = implode(",", $dm);
    } else {
        $demography = 'off';
    }
    set_config('faces', 'demography', $demography);
    logger("set demography to " . $demography, LOGGER_NORMAL);

    $ramcheckmsg = ($ret["r"] == 0) ? "" : $ret["r"];
    set_config('faces', 'ramcheck', $ramcheckmsg);
}

function stopRunningFaceRecognition() {
    // stop python script (if running) by changing the procid
    $procid = random_string(10);
    set_config("faces", "status", "started " . datetime_convert() . " pid " . $procid);
}

function unblockFaceRecognition() {
    // stop python script (if running) by changing the procid
    $procid = random_string(10);
    set_config("faces", "status", "finished " . datetime_convert() . " pid " . $procid);
}

function testPythonVersion() {
    // For the min version check https://github.com/serengil/deepface/blob/master/setup.py
    // This above file states: python_requires=">=3.5.5".
    $min_version = "3.5.5";
    $cmd = 'python3 -c "import platform;print(platform.python_version())"';
    exec($cmd, $output, $r);
    if ($output[0]) {
        $ret_string = trim($output[0]);
        logger("python version: " . $ret_string, LOGGER_NORMAL);
        $version_arr_found = explode(".", $ret_string);
        $version_arr_min = explode(".", $min_version);
        if ($version_arr_found[0] < $version_arr_min[0]) {
            return array('status' => false, 'message' => 'Failed: python version ' . $ret_string . ' < ' . $min_version);
        }
        if ($version_arr_found[1] < $version_arr_min[1]) {
            return array('status' => false, 'message' => 'Failed: python version ' . $ret_string . ' < ' . $min_version);
        }
    } else {
        return array('status' => false, 'message' => 'Failed: python3 not found');
    }
    return array('status' => true, 'message' => 'Found  python version ' . $output[0] . ' >= ' . $min_version);
}

function testDeepfaceVersion() {
    $cmd = 'pip show deepface';
    exec($cmd, $output);
    $ret_string = "";
    if ($output[0]) {
        foreach ($output as $line) {
            logger($line, LOGGER_DEBUG);
            if (str_starts_with(strtolower($line), "version:")) {
                $ret_string = $line;
                break;
            }
        }
        if ($ret_string == "") {
            $ret_string = $output[0];
            return array('status' => false, 'message' => 'Failed: deepface not found (found ' . $ret_string . ')', LOGGER_DEBUG);
        }
    } else {
        return array('status' => false, 'message' => 'Failed: deepfaces not found (exec wihout return value)', LOGGER_DEBUG);
    }
    return array('status' => true, 'message' => 'Found  deepface ' . $ret_string, LOGGER_NORMAL);
}

function testMySQLConnectorVersion() {
    $cmd = 'pip show mysql-connector-python';
    exec($cmd, $output);
    $ret_string = "";
    if ($output[0]) {
        foreach ($output as $line) {
            logger($line, LOGGER_DEBUG);
            if (str_starts_with(strtolower($line), "version:")) {
                $ret_string = $line;
                break;
            }
        }
        if ($ret_string == "") {
            $ret_string = $output[0];
            return array('status' => false, 'message' => 'Failed: mysql-connector-python not found (found ' . $ret_string . ')', LOGGER_DEBUG);
        }
    } else {
        return array('status' => false, 'message' => 'Failed: mysql-connector-python not found (exec wihout return value)', LOGGER_DEBUG);
    }
    return array('status' => true, 'message' => 'Found  mysql-connector-python ' . $ret_string, LOGGER_NORMAL);
}

function testDeepfaceModules($pdetectors, $pmodels, $pdemography) {
    if ($pdetectors === "") {
        $pdetectors = "retinaface";
    }
    if ($pmodels === "") {
        $pmodels = "Facenet512";
    }
    if ($pdemography === "") {
        $pdemography = "off";
    }
    $detectors = [];
    $models = [];
    $demography = [];
    $ram = 0;
    $cmd = "python3 " . getcwd() . "/addon/faces/py/availability.py --detectors " . $pdetectors . " --models " . $pmodels . " --demography " . $pdemography;
    exec($cmd, $output);
    foreach ($output as $line) {
        logger($line, LOGGER_DEBUG);
        if (str_starts_with(strtolower($line), "found model")) {
            $splittees = explode(" ", $line);
            $models[] = $splittees[2];
        }
        if (str_starts_with(strtolower($line), "found detector")) {
            $splittees = explode(" ", $line);
            $detectors[] = $splittees[2];
        }
        if (str_starts_with(strtolower($line), "found demography")) {
            $splittees = explode(" ", $line);
            $demography[] = $splittees[2];
        }
        if (str_starts_with(strtolower($line), "ram")) {
            $ram = $line;
        }
    }
    return array('d' => $detectors, 'm' => $models, 'dm' => $demography, 'r' => $ram);
}
