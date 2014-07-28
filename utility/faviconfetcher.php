<?php
/**
 * ownCloud - News
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Alessandro Cosentino <cosenal@gmail.com>
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @copyright Alessandro Cosentino 2012
 * @copyright Bernhard Posselt 2012, 2014
 */

namespace OCA\Passman\Utility;


class FaviconFetcher {

	private $apiFactory;
	private $config;

	/**
	 * Inject a factory to build a simplepie file object. This is needed because
	 * the file object contains logic in its constructor which makes it
	 * impossible to inject and test
	 */
	public function __construct(SimplePieAPIFactory $apiFactory) {
		$this->apiFactory = $apiFactory;
	}


	/**
	 * Fetches a favicon from a given URL
	 * @param string|null $url the url where to fetch it from
	 */
	public function fetch($url) {
		try {
			$url = $this->buildURL($url);
		} catch (NoValidUrlException $e) {
			return null;
		}

		$faviconUrl = $this->extractFromPage($url);

		// check the url for a valid image
		if($faviconUrl && $this->isImage($faviconUrl)) {
			return $faviconUrl;
		} elseif($url) {
			// try /favicon.ico as fallback
			$parts = parse_url($url);
			$faviconUrl = $parts['scheme'] . "://" . $parts['host'] . (array_key_exists("port", $parts) ? ':'.$parts['port'] : '') .  (array_key_exists("path", $parts) ? $parts['path'] : '') ."/favicon.ico";

			if($this->isImage($faviconUrl)) {
				return $faviconUrl;
			}
			else
			{
				return null;
			}
		}

		return null;
	}


	/**
	 * Tries to get a favicon from a page
	 * @param string $url the url to the page
	 * @return string the full url to the page
	 */
	protected function extractFromPage($url) {
		if(!$url) {
			return null;
		}

		$file = $this->getFile($url);

		if($file->body !== '') {
			$document = new \DOMDocument();
			@$document->loadHTML($file->body);

			if($document) {
				$xpath = new \DOMXpath($document);
				$elements = $xpath->query("//link[contains(@rel, 'icon')]");

				if ($elements->length > 0) {
					$iconPath = $elements->item(0)->getAttribute('href');
					$absPath = \SimplePie_Misc::absolutize_url($iconPath, $url);
					return $absPath;
				}
			}
		}
	}

	
	private function getFile($url) {
		//if(trim($this->config->getProxyHost()) === '') {
			return $this->apiFactory->getFile($url, 10, 5, null, null, false,
				null, null, null);
		/*} else {
			return $this->apiFactory->getFile($url, 10, 5, null, null, false,
				$this->config->getProxyHost(),
				$this->config->getProxyPort(),
				$this->config->getProxyAuth());
		}*/
	}


	/**
	 * Test if the file is an image
	 * @param string $url the url to the file
	 * @return bool true if image
	 */
	protected function isImage($url) {
		// check for empty urls
		if(!$url) {
			return false;
		}

		$file = $this->getFile($url);
		$status_range = (string) substr($file->status_code,0,1);
		//echo $status_range;
		if($status_range == 2 || $status_range == 3 || !empty($status_range)){ // 200's are a good response and 300's are redirects
			$sniffer = new \SimplePie_Content_Type_Sniffer($file);
			return $sniffer->image() !== false;
		}
	}


	/**
	 * Get HTTP or HTTPS addresses from an incomplete URL
	 * @param string $url the url that should be built
	 * @return string a string containing the http or https address
	 * @throws NoValidUrlException when no valid url can be returned
	 */
	protected function buildURL($url) {
		// trim the right / from the url
		$url = trim($url);
		$url = rtrim($url, '/');

		// check for http:// or https:// and validate URL
		if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
			if (filter_var($url, FILTER_VALIDATE_URL)) {
				return $url;
			}
		} elseif (filter_var("http://" . $url, FILTER_VALIDATE_URL)) {
			// maybe $url was something like www.example.com
			return 'http://' . $url;
		}

		// no valid URL was passed in or could be built from $url
		throw new NoValidUrlException();
	}

}

/**
 * Thrown when no valid url was found by faviconfetcher
 */
class NoValidUrlException extends \Exception {
}