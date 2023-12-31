<?php
namespace Zotlabs\Module;

use Zotlabs\Web\HTTPSig;
use Zotlabs\Lib\ActivityStreams;

require_once('library/jsonld/jsonld.php');

class Ap_probe extends \Zotlabs\Web\Controller {

	function get() {

		if (!is_site_admin()) {
			return;
		}

		$o .= '<h3>ActivityPub Probe Diagnostic</h3>';

		$o .= '<form action="ap_probe" method="post">';
		$o .= 'Lookup URI: <input type="text" style="width: 250px;" name="addr" value="' . $_REQUEST['addr'] .'" /><br>';
		$o .= 'or paste text: <textarea style="width: 250px;" name="text">' . htmlspecialchars($_REQUEST['text']) . '</textarea><br>';
		$o .= '<input type="checkbox" name="sign" /> Sign request <br>';
		$o .= '<input type="submit" name="submit" value="Submit" /></form>';

		$o .= '<br /><br />';

		if(x($_REQUEST,'addr')) {
			$addr = $_REQUEST['addr'];

			if ($_REQUEST['sign']) {
				$channel = get_sys_channel();
				$m = parse_url($addr);

				$headers = [
					'Accept'           => ActivityStreams::get_accept_header_string($channel),
					'Host'             => $m['host'],
					'Date'             => datetime_convert('UTC', 'UTC', 'now', 'D, d M Y H:i:s \\G\\M\\T'),
					'(request-target)' => 'get ' . get_request_string($addr)
				];
				$headers = HTTPSig::create_sig($headers, $channel['channel_prvkey'], channel_url($channel), false);
			}
			else {
				$headers = 'Accept: application/activity+json, application/ld+json; profile="https://www.w3.org/ns/activitystreams", application/ld+json;profile="https://www.w3.org/ns/activitystreams", application/activity+json, application/ld+json';
			}

			$redirects = 0;
		    $x = z_fetch_url($addr, true, $redirects, [ 'headers' => $headers ]);

	    	if($x['success'])

				$o .= '<pre>' . htmlspecialchars($x['header']) . '</pre>' . EOL;


				$o .= '<pre>' . htmlspecialchars($x['body']) . '</pre>' . EOL;

				$o .= 'verify returns: ' . str_replace("\n",EOL,print_r(HTTPSig::verify($x),true)) . EOL;
				$text = $x['body'];
			}
			else {
				$text = $_REQUEST['text'];
			}

			if($text) {

//				if($text && json_decode($text)) {
//					$normalized1 = jsonld_normalize(json_decode($text),[ 'algorithm' => 'URDNA2015', 'format' => 'application/nquads' ]);
//					$o .= str_replace("\n",EOL,htmlentities(var_export($normalized1,true)));

	//				$o .= '<pre>' . json_encode($normalized1, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</pre>';
//				}

				$o .= '<pre>' . str_replace(['\\n','\\'],["\n",''],htmlspecialchars(jindent($text))) . '</pre>';

				$AP = new \Zotlabs\Lib\ActivityStreams($text);
				$o .= '<pre>' . htmlspecialchars($AP->debug()) . '</pre>';
		}

		return $o;
	}

}
