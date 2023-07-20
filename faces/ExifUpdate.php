<?php

namespace Zotlabs\Module;

/**
 * Write  the names of the face recognition into the images
 */
class ExifUpdate {

	/**
	 * Write  the name into the image.
	 * 
	 * Why Exiftool?
	 * 
	 * Exiftool is the only tool found that writes the keywords reliably and in a standard way.
	 * See  https://exiftool.org/TagNames/MWG.html
	 * 
	 * GD does only read exif.
	 * 
	 * Imagick can write exif but it is not sure how to write something that is compliant
	 * to differenct ways (exif, iptc,... ) and tools.
	 *   
	 * TODO: there might be other tools for PHP?
	 */
	function updateExif($path, $name) {

		// exiftool -ver
		// exiftool -Subject $path
		// exiftool -mwg:keywords+="SCHLÜSSELWORT"

		$cmd = escapeshellcmd("exiftool -Subject " . $path);
		exec($cmd, $o);
		$r = $o[0];
		if (!$r) {
			logger("Exiftool found no -Subject in file=" . $path, LOGGER_DEBUG);
		} else if (strpos(strtolower($r), strtolower(strtolower($name)))) {
			logger("OK name=" . $name . " does exist already as subject in image, file=" . $path, LOGGER_DEBUG);
			return false;
		}
		$cmd = escapeshellcmd("exiftool -mwg:keywords+=\"" . $name . "\" " . $path);
		$o = [];
		exec($cmd, $o);
		if (!$o[0]) {
			logger("Failed to write name=" . $name . " into image. Reason: Exiftool failed to write -Subject into file = " . $path, LOGGER_DEBUG);
			return false;
		} else {
			logger("Exiftool returned: " . $o[0], LOGGER_DEBUG);
		}
		logger("Exiftool wrote name=" . $name . " into image, file=" . $path, LOGGER_DEBUG);

		return true;
	}

}
