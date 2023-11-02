<?php

/**
 * @license https://www.gnu.org/licenses/gpl-3.0.en.html GPLv3
 * @author Maxence Cauderlier <mx.koder@gmail.com>
 * 
 * @link https://github.com/MaxenceCauderlier/Pingback
 */

/**
 * Pingback is a simple class to send and receive pingback, compatible with lot of blogs like WordPress
 */
class Pingback {

    const ERR_GENERIC =             [0  => ''];
    const ERR_URI_DONT_EXIST =      [16 => 'The source URI does not exist.'];
    const ERR_NO_LINK_URI =         [17 => 'The source URI dont contain link to the target.'];
    const ERR_TARGET_DONT_EXIST =   [32 => 'The specified target URI does not exist.'];
    const ERR_TARGET_NOT_USABLE =   [33 => 'The specified target URI cannot be used as a target.'];
    const ERR_ALREADY_REGISTERRED = [48 => 'The pingback has already been registered.'];
    const ERR_DENIED =              [49 => 'Access denied.'];
    const ERR_CAN_COMPLETE =        [50 => 'Server was unable to complete the request'];

    /**
     * Log is content of Pingback log
     * @var string
     */
    protected string $log = "";

    /**
     * response is set after listen method, to respond to any request
     * @var string
     */
    protected string $response = "";

    /**
     * Initialize libxml_use_internal_errors(true) to hide untagged elements errors
     */
    public function __construct() {
        libxml_use_internal_errors(true);
    }

    /**
     * Load $urlSource page content, parse links tags and send pingback to other servers
     * @param string $urlSource
     * @return void
     */
    public function inspect(string $urlSource):void {
        $req = $this->curl($urlSource);
        if (empty($req['body'])) {
            return;
        }
        $doc = new DOMDocument('1.0', 'utf-8');
        $doc->stricterrorchecking = false;
        $doc->recover = true;
        if ($doc->loadHTML($req['body'])) {
            $links=$doc->getelementsbytagname('a');
            foreach($links as $link) {
                $href = $link->getattribute('href');
                $pingUrl = $this->isEnabled($href);
                if ($pingUrl) {
                    //Pingback URL found
                    $xml = $this->getXml($urlSource, $href);
                    $request = $this->curl($pingUrl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $xml]);
                    $this->addLog("Send Pingback from '$urlSource' to '$href' by '$pingUrl'");
                }
            }
        }
        libxml_clear_errors();
        unset($doc);
    }

    /**
     * Receive pingback, check if request is valid,
     * check our page accept pingback, verify link in source
     * and call the callback if all right
     * @param mixed $callback A valid Callback
     * @return void
     */
    public function listen($callback) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            // Accept only POST request
            $this->generateErrorResponse(self::ERR_DENIED);
        }
        if (!isset( $HTTP_RAW_POST_DATA ) ) {
			$HTTP_RAW_POST_DATA = file_get_contents( 'php://input' );
		}
        $dom = new DOMDocument;
        $dom->loadXML($HTTP_RAW_POST_DATA);
        if (!$dom) {
            // DOM is malformed, unable to respond
            $this->generateErrorResponse(self::ERR_GENERIC);
        }
        $method = simplexml_import_dom($dom);
        if ($method->getName() === 'methodCall') {
            // Try to pingback our site
            if ((string)$method->methodName !== 'pingback.ping') {
                $this->generateErrorResponse(self::ERR_DENIED);
            }
            $urls = [];
            foreach ($method->params->children() as $child) {
                $urls[] = (string)$child->value->string;
            }
            if (count($urls) !== 2) {
                // No source & permalink provided
                $this->generateErrorResponse(self::ERR_DENIED);
            }
            // Source is the unknown blog post, permalink is URL of your blog post
            list($source, $permalink)=$urls;
            $permalink = $this->getAbsoluteUrl($permalink);
            $source = $this->getAbsoluteUrl($source);
            if ($source === $permalink) {
                // We don't continue if source & permalink are the same blog post
                $$this->generateErrorResponse(self::ERR_GENERIC);
            }
            if (!$this->isEnabled($permalink)) {
                // Our page don't accept Pingback
                $this->generateErrorResponse(self::ERR_TARGET_NOT_USABLE);
            }
            // We will check if a link to our blog post exist in the source
            $req = $this->curl($source);
            if (empty($req['body'])) {
                $this->generateErrorResponse(self::ERR_TARGET_DONT_EXIST);
            }
            $doc = new DOMDocument('1.0', 'utf-8');
            $doc->stricterrorchecking = false;
            $doc->recover = true;
            if ($doc->loadHTML($req['body'])) {
                $links=$doc->getelementsbytagname('a');
                foreach($links as $link) {
                    $href = $link->getattribute('href');
                    if ($this->getAbsoluteUrl($href) === $permalink) {
                        // Link found
                        $this->addLog('Pingback success from ' . $source . ' to our article ' . $permalink);
                        call_user_func_array($callback,[$source, $permalink, $req['body']]);
                        $this->response = $this->getSuccessXml($source,$permalink);
                    }
                }
            }
            // No link found
            libxml_clear_errors();
            unset($doc);
            $this->generateErrorResponse(self::ERR_NO_LINK_URI);
        } else {
            // Bad method called
            $this->generateErrorResponse(self::ERR_GENERIC);
        }
    }

    /**
     * Send the response to the request.
     * Call it only after listen method
     * @return void
     */
    public function sendResponse():void {
        die($this->response);
    }

    /**
     * Set the response by give up an error.
     * @see Constant class
     * @param array $error
     * @return void
     */
    public function generateErrorResponse(array $error):void {
        $key = key($error);
        $this->response = $this->getErrorXml($key, $error[$key]);
    }

    /**
     * Return pingback (like http://domain.com/xmlrpc.php for WordPress) URL if pingback is enabled in $url
     * Else, return false.
     * @param string $url
     * @return mixed
     */
    public function isEnabled(string $url) {
        $url = $this->getAbsoluteUrl($url);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $request = $this->curl($url);
        if (!isset($request['headers'])) {
            return false;
        }
        foreach($request['headers'] as $head) {
            if (preg_match('/^X-Pingback:\h*(.+)/',$head,$href)) {
                return $href[1];
            }
        }
        if (preg_match('/<link\s+rel\s?=\s?\"pingback\"\s+href\s?=\s?\"(.+?)\"\s*\/?>/is',$request['body'],$href)) {
            return $href[1];
        }
        return false;
    }

    /**
     * Return content of the Pingback log
     * @return string
     */
    public function log():string {
        return $this->log;
    }

    /**
     * If $url is relative (local, like '../blog/article'), modify it to get absolute URL
     * @param string $url
     * @return string Absolute URL
     */
    protected function getAbsoluteUrl(string $url):string {
        if (stripos($url, '..') === 0) {
            // Relative URL from this site
            $url = str_replace('..', '', $url);
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                $tUrl = "https";
            } else {
                $tUrl = "http";
            }
            $tUrl .= "://" . $_SERVER['HTTP_HOST'];
            return $tUrl . $url;
        }
        return $url;
    }

    /**
     * Fire a request to $url with curl
     * @param string $url
     * @param array $options
     * @return array Request as an array
     */
    protected function curl(string $url,array $options = []):array {
        $opts = [
            CURLOPT_HEADER => 'Content-Type: application/xml',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/xml'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_MAXREDIRS => 10
        ];
        foreach ($options as $k => $v) {
            $opts[$k] = $v;
        }
        $ch = curl_init();
        curl_setopt_array($ch, $opts);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_HEADERFUNCTION,
			// Callback for response headers
			function($ch,$line) use(&$headers) {
                $headers[]=trim($line);
				return strlen($line);
			}
		);
        ob_start();
		$body = curl_exec($ch);
		ob_get_clean();
		$err=curl_error($ch);
		curl_close($ch);
        return [
            'body' => $body,
            'headers' => $headers,
            'error' => $err
        ];
    }

    /**
     * Add a string to the Pingback log
     * @param string $log
     * @return void
     */
    protected function addLog(string $log):void {
        $this->log .= date('r') . ' : ' . $log . "\n";
    }

    /**
     * Get an XML formed to send a pingback
     * @param string $source
     * @param string $target
     * @return string XML
     */
    protected function getXml(string $source,string $target) {
        return '<?xml version="1.0" encoding="UTF-8"?>
                <methodCall>
                    <methodName>pingback.ping</methodName>
                    <params>
                        <param>
                            <value>
                                <string>' . $source .'</string>
                            </value>
                        </param>
                        <param>
                            <value>
                                <string>' . $target .'</string>
                            </value>
                        </param>
                    </params>
                </methodCall>';
    }

    /**
     * Get an XML formed to send an error response
     * @param int $errorCode
     * @param string $errorMessage
     * @return string
     */
    protected function getErrorXml(int $errorCode,string $errorMessage) {
        return '<?xml version="1.0" encoding="UTF-8"?>
                <methodResponse>
                    <fault>
                        <value>
                            <struct>
                                <member>
                                    <name>faultCode</name>
                                    <value><int>' . $errorCode . '</int></value>
                                </member>
                                <member>
                                    <name>faultString</name>
                                    <value><string>' . $errorMessage .'</string></value>
                                </member>
                            </struct>
                        </value>
                    </fault>
                </methodResponse>';
    }

    /**
     * Get an XML formed to send a success response
     * @param string $source
     * @param string $permalink
     * @return string
     */
    protected function getSuccessXml(string $source,string $permalink) {
        return '<?xml version="1.0" encoding="UTF-8"?>
                <methodResponse>
                    <params>
                        <param>
                            <value>
                            <string>Got a ping from ' .$source. ' for '.$permalink.'. Let\'s continue the conversation ! :-)</string>
                            </value>
                        </param>
                    </params>
                </methodResponse>';
    }
}
