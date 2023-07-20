<?php

namespace Zotlabs\Module;

use Zotlabs\Storage\File;

class FaceThresholds {

    function read(\Zotlabs\Storage\File $file) {

        $JSONstream = $file->get();
        $contents = stream_get_contents($JSONstream);
        $thresholds = json_decode($contents, true);
        fclose($JSONstream);

        if (!$thresholds) {
            $thresholds = $this->getDefaults();
            $this->write($file, $thresholds);
        } else {
            $thresholds = $this->validate($thresholds);
            $toCompare = json_encode($thresholds);
            if ($contents != $toCompare) {
                $this->write($file, $thresholds);
            }
        }


        return $thresholds;
    }

    function write(\Zotlabs\Storage\File $file, $thresholds, $validate = false) {
        if ($validate) {
            $thresholds = $this->validate($thresholds);
        }

        $json = json_encode($thresholds);

        $file->put($json);

        logger('wrote thresholds file=' . $file->getName() . ", content=" . $json, LOGGER_DEBUG);
    }

    function validate($thresholds) {
        $defaults = $this->getDefaults();
        foreach ($defaults as $model => $model_metrics) {
            foreach ($model_metrics as $metric => $value) {
                $value_to_validate = $thresholds[$model][$metric];
                if (!is_numeric($value_to_validate)) {
                    $value_to_validate = $value;
                } elseif ($value_to_validate < ($value / 2)) {
                    $value_to_validate = $value / 2;
                } elseif ($value_to_validate > ($value * 1.5)) {
                    $value_to_validate = $value * 1.5;
                }
                $thresholds[$model][$metric] = $value_to_validate;
            }
        }

        return $thresholds;
    }

    function getDefaults() {
        $thresholds = [];
        $thresholds['VGG-Face']["cosine"] = 0.40;
        $thresholds['VGG-Face']["euclidean"] = 0.60;
        $thresholds['VGG-Face']["euclidean_l2"] = 0.86;

        $thresholds['Facenet']["cosine"] = 0.40;
        $thresholds['Facenet']["euclidean"] = 10;
        $thresholds['Facenet']["euclidean_l2"] = 0.80;

        $thresholds['Facenet512']["cosine"] = 0.30;
        $thresholds['Facenet512']["euclidean"] = 23.56;
        $thresholds['Facenet512']["euclidean_l2"] = 1.04;

        $thresholds['ArcFace']["cosine"] = 0.68;
        $thresholds['ArcFace']["euclidean"] = 4.15;
        $thresholds['ArcFace']["euclidean_l2"] = 1.13;

        $thresholds['SFace']["cosine"] = 0.5932763306134152;
        $thresholds['SFace']["euclidean"] = 10.734038121282206;
        $thresholds['SFace']["euclidean_l2"] = 1.055836701022614;

        $thresholds['OpenFace']["cosine"] = 0.10;
        $thresholds['OpenFace']["euclidean"] = 0.55;
        $thresholds['OpenFace']["euclidean_l2"] = 0.55;

        $thresholds['DeepFace']["cosine"] = 0.23;
        $thresholds['DeepFace']["euclidean"] = 64;
        $thresholds['DeepFace']["euclidean_l2"] = 0.64;

//        $thresholds['DeepID']["cosine"] = 0.015;        
//        $thresholds['DeepID']["euclidean"] = 45;
//        $thresholds['DeepID']["euclidean_l2"] = 0.17;
//        
//        $thresholds['Dlib']["cosine"] = 0.07;        
//        $thresholds['Dlib']["euclidean"] = 0.6;
//        $thresholds['Dlib']["euclidean_l2"] = 0.4;

        return $thresholds;
    }

}
