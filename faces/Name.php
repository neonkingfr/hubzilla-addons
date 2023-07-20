<?php

namespace Zotlabs\Module;

class Name {

    public function write(\Zotlabs\Storage\File $file, string $image, string $name, string $id, $position) {
        logger("Try to write name '" . $name . "' for face id ='" . $id . "' in image=" . $image, LOGGER_DEBUG);

        $stream = $file->get();
        $contents = stream_get_contents($stream);
        $faces = json_decode($contents, true);

        $faces_replaced = $this->replace($faces, $image, $name, $id, $position);
        if (!$faces_replaced) {
            return false;
        }

        logger("about to write name='" . $name . "' for face id ='" . $id . "'", LOGGER_DEBUG);
        $json = json_encode($faces_replaced);
        $file->put($json);
        logger("wrote name='" . $name . "' for face id ='" . $id . "' in image=" . $image, LOGGER_DEBUG);

        return true;
    }

    private function replace($faces, string $image, string $name, string $id, $position) {
        $i = 0;
        while ($faces["id"][$i]) {
            $face_id = $faces["id"][$i];
            if ($face_id === $id) {
                logger("Existing face id ='" . $id . "'", LOGGER_DEBUG);
                break;
            }
            $i++;
        }
        $faces["id"][$i] = $id;
        $faces["name"][$i] = $name;
        $faces["file"][$i] = $image;
        $faces["position"][$i] = $position;
        // $objDateTime = new DateTime('NOW'); 
        // $dateTimeString = $objDateTime->format(DateTime::W3C);
        date_default_timezone_set("UTC");
        $dateTimeString = date("Y-m-d\TH:i:sP");  // same as W3C format but always ...T+00:00
        $faces["time_named"][$i] = $dateTimeString;
        logger("replaced name=" . $name . "' for image=" . $image . " with face id ='" . $id, LOGGER_DEBUG);
        return $faces;
    }

}
