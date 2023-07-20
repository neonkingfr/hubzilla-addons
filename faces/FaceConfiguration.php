<?php

namespace Zotlabs\Module;

use Zotlabs\Storage\File;

class FaceConfiguration {

    private $available_detectors = "retinaface,mtcnn,ssd,opencv,mediapipe";
    private $available_models = "Facenet512,ArcFace,VGG-Face,SFace,Facenet,OpenFace,DeepFace";
    private $available_metrics = "euclidean_l2,cosine,euclidean";
    private $available_attributes = "Emotion,Age,Gender,Race";

    function read(\Zotlabs\Storage\File $file, $can_write) {

        $JSONstream = $file->get();
        $contents = stream_get_contents($JSONstream);
        $config = json_decode($contents, true);
        fclose($JSONstream);

        if (!$config) {
            $config = $this->getDefaultConfig();
            $this->write($file, $config);
        } else {
            $config = $this->checkConfig($config);
            $toCompare = json_encode($config);
            if ($contents != $toCompare && $can_write) {
                // the admin might have changed the settings meanwhile
                $this->write($file, $config);
            }
        }


        return $config;
    }

    function write(\Zotlabs\Storage\File $file, $config) {
        //$config = $this->checkConfig($config);

        $json = json_encode($config);

        $file->put($json);

        logger('wrote configuration file=' . $file->getName() . ", content=" . $json, LOGGER_DATA);
    }

    function checkConfig($config) {

        $experimental_allowed = get_config('faces', 'experimental_allowed') ? get_config('faces', 'experimental_allowed') : false;

        $config = $this->checkAllowed($config, 'detectors', $experimental_allowed);
        $config = $this->checkIsOneSwitchedON($config, 'detectors');

        $config = $this->checkAllowed($config, 'models', $experimental_allowed);
        $config = $this->checkIsOneSwitchedON($config, 'models');

        $config = $this->checkAllowed($config, 'distance_metrics', $experimental_allowed);
        $config = $this->checkIsOneSwitchedON($config, 'distance_metrics');

        $config = $this->checkAllowed($config, 'demography', $experimental_allowed);

        $config = $this->validate($config);

        return $config;
    }

    private function checkAllowed($config, $group, $experimental_allowed) {
        $allowed = explode(",", get_config('faces', $group));
        $found = false;
        for ($i = 0; $i < sizeof($config[$group]); $i++) {
            $element = $config[$group][$i];
            $name = $element[0];
            $value = $element[1];
            if (in_array($name, $allowed)) {
                if ($value && !$found) {
                    $found = true;
                } elseif ($value && $found && !$experimental_allowed && $group !== "demography") {
                    $config[$group][$i][1] = false;
                }
                $config[$group][$i][2] = false;
            } else {
                $config[$group][$i][1] = false;
                $config[$group][$i][2] = true;  // disabled
            }
        }

        if (!$found && $group !== "demography") {
            // switch first allowed on
            for ($i = 0; $i < sizeof($config[$group]); $i++) {
                $element = $config[$group][$i];
                $name = $element[0];
                $value = $element[1];
                $disabled = $element[2];
                if (!$disabled) {
                    $config[$group][$i][1] = true;
                    break;
                }
            }
        }

        return $config;
    }

    private function validate($config) {

        $experimental_allowed = get_config('faces', 'experimental_allowed') ? get_config('faces', 'experimental_allowed') : false;

        if (!$experimental_allowed) {
            $config["statistics"][0][1] = false;  // checkbox is checked
            $config["statistics"][0][2] = true;   // checkbox is disabled

            $config["history"][0][1] = false;
            $config["history"][0][2] = true;

            $config["enforce"][0][1] = false;
            $config["enforce"][0][2] = true;

            $config["faces_experimental"][0][1] = false;
            $config["faces_experimental"][0][2] = true;

            $config["faces_defaults"][0][1] = false;  // checkobox is not checked
            $config["faces_defaults"][0][2] = false;  // checkbox is enabled
        } else {
            $config["statistics"][0][2] = false;

            $config["history"][0][2] = false;

            $config["enforce"][0][2] = false;

            $config["faces_experimental"][0][1] = false;
            $config["faces_experimental"][0][2] = false;

            $config["faces_defaults"][0][1] = false;
            $config["faces_defaults"][0][2] = false;
        }

        $immediate_search_allowed = get_config('faces', 'immediatly') ? get_config('faces', 'immediatly') : false;
        if (!$immediate_search_allowed) {
            $config["immediatly"][0][1] = false;
            $config["immediatly"][0][2] = true;
        } else {
            $config["immediatly"][0][2] = false;
        }



        for ($i = 0; $i < sizeof($config["min_face_width_detection"]); $i++) {
            $element = $config["min_face_width_detection"][$i];
            $name = $element[0];
            $number = $element[1];

            $min = 30;
            $max = 10000;
            $default = 50;

            if ($name === "percent") {
                $min = 1;
                $max = 99;
                $default = 2;
            }

            if (!is_numeric($number)) {
                $number = $default;
            } else {
                $number = round($number);
                if ($number > $max) {
                    $number = $max;
                } elseif ($number < $min) {
                    $number = $min;
                }
            }

            $config["min_face_width_detection"][$i][1] = $number;
        }


        for ($i = 0; $i < sizeof($config["min_face_width_recognition"]); $i++) {
            $element = $config["min_face_width_recognition"][$i];
            $name = $element[0];
            $number = $element[1];

            // training data
            $min = 30;
            $max = 10000;
            $default = 224;

            // faces to find
            if ($name === "result") {
                $min = 30;
                $max = 10000;
                $default = 50;
            }

            if (!is_numeric($number)) {
                $number = $default;
            } else {
                $number = round($number);
                if ($number > $max) {
                    $number = $max;
                } elseif ($number < $min) {
                    $number = $min;
                }
            }

            $config["min_face_width_recognition"][$i][1] = $number;
        }
           
        $config["exif"][0][2] = false;
        $config["ascending"][0][2] = false;
        
        $min = 1;
        $max = 6;
        $default = 2;
        $number = $config["zoom"][0][1];
        if (!is_numeric($number)) {
            $number = $default;
        } else {
            $number = round($number);
            if ($number > $max) {
                $number = $max;
            } elseif ($number < $min) {
                $number = $min;
            }
        }
        
        $min = 0;
        $max = 99;
        $default = 50;
        $number = $config["closeness"][0][1];
        if (!is_numeric($number)) {
            $number = $default;
        } else {
            $number = round($number);
            if ($number > $max) {
                $number = $max;
            } elseif ($number < $min) {
                $number = $min;
            }
        }
        
        $min = 1;
        $max = 100;
        $default = 70;
        $number = $config["most_similar_percent"][0][1];
        if (!is_numeric($number)) {
            $number = $default;
        } else {
            $number = round($number);
            if ($number > $max) {
                $number = $max;
            } elseif ($number < $min) {
                $number = $min;
            }
        }
        $config["most_similar_percent"][0][1] = $number;
        
        $min = 1;
        $max = 100;
        $default = 10;
        $number = $config["most_similar_number"][0][1];
        if (!is_numeric($number)) {
            $number = $default;
        } else {
            $number = round($number);
            if ($number > $max) {
                $number = $max;
            } elseif ($number < $min) {
                $number = $min;
            }
        }
        $config["most_similar_number"][0][1] = $number;
        
        $min = 1;
        $max = 100;
        $default = 1;
        $number = $config["max_shared"][0][1];
        if (!is_numeric($number)) {
            $number = $default;
        } else {
            $number = round($number);
            if ($number > $max) {
                $number = $max;
            } elseif ($number < $min) {
                $number = $min;
            }
        }
        $config["max_shared"][0][1] = $number;
        
        $config["worker"]["ram"] = get_config('faces', 'max_ram') ? get_config('faces', 'max_ram') : 80;

        return $config;
    }

    private function checkIsOneSwitchedON($config, $group) {

        for ($i = 0; $i < sizeof($config[$group]); $i++) {
            $element = $config[$group][$i];
            $value = $element[1];
            if ($value) {
                return $config;
            }
        }

        $config[$group][0][1] = true;
        $config[$group][0][2] = false;

        return $config;
    }

    function getDefaultConfig() {
        $config = [];
        
        //----------------------------------------------------------------------
        // set in frontend by user (some limited by admin)
        $config = $this->addConfigElement("detectors", $this->available_detectors, $config, true);
        $config = $this->addConfigElement("models", $this->available_models, $config, true);
        $config = $this->addConfigElement("distance_metrics", $this->available_metrics, $config, true);
        $config = $this->addConfigElement("demography", $this->available_attributes, $config, false);

        $config = $this->addConfigElement("statistics", "statistics", $config, false);
        $config = $this->addConfigElement("history", "history", $config, false);
        $config = $this->addConfigElement("enforce", "enforce", $config, false);
        $config = $this->addConfigElement("faces_defaults", "reset", $config, false);
        $config = $this->addConfigElement("faces_experimental", "experimental", $config, false);
        $config = $this->addConfigElement("immediatly", "immediatly", $config, false);
        $config = $this->addConfigElement("exif", "exif", $config, false);
        $config = $this->addConfigElement("ascending", "ascending", $config, false);
        $config["min_face_width_detection"] = [["percent", 5], ["pixel", 50]];
        $config["min_face_width_recognition"] = [["training", 224], ["result", 50]];
        $config["zoom"] = [["zoom", 2]];
        $config["closeness"] = [["closeness", 50]];
        $config["most_similar_percent"] = [["most_similar_percent", 70]];
        $config["most_similar_number"] = [["most_similar_number", 10]];
        $config["max_shared"] = [["max_shared", 1]];  // there is no setting in the GUI for this value
        
        //----------------------------------------------------------------------
        // set by admin in admin page of addon
        $config["worker"]["ram"] = get_config('faces', 'max_ram') ? get_config('faces', 'max_ram') : 80;
        
        //----------------------------------------------------------------------
        // not set in frontend
        $config["worker"]["interval_alive_signal"] = 10;
        $config["worker"]["interval_backup_detection"] = 60*2;
        $config["worker"]["sort_column"] = "mtime";
        $config["worker"]["sort_ascending"] = false;
        $config["finder"]["valid_detectors"] = ["opencv", "ssd", "mtcnn", "retinaface", "mediapipe"];
        $config["finder"]["valid_models"] = ['VGG-Face', 'Facenet', 'Facenet512', 'ArcFace', 'OpenFace', 'DeepFace', 'SFace'];
        $config["finder"]["valid_attributes"] = ["Gender", "Age", "Race", "Emotion"];
        $config["finder"]["use_css_position"] = true;
        $config["recognizer"]["valid_distance_metrics"] = ["cosine", "euclidean", "euclidean_l2"];
        //----------------------------------------------------------------------

        $config = $this->checkConfig($config);

        return $config;
    }

    private function addConfigElement($group, $list, $config, $isON) {
        $group_names = explode(",", $list);
        $group_config = [];
        foreach ($group_names as $name) {
            array_push($group_config, array($name, $isON, false));
            //$group_config[$name] = $isON;
        }
        $config[$group] = $group_config;
        return $config;
    }

}
