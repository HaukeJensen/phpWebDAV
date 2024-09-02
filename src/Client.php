<?php
namespace WebDAV;

class Client {
	protected $_remoteUrl;

	protected $_httpResponseCode;
	protected $_httpResponseHeader = [];
	protected $_httpResponseText;

	protected $_httpRequestCookie;
	protected $_httpRequestAuthorization;

	/**
	 * @param string $location
	 * @param string $username
	 * @param string $password
	 *
	 * @return $this
	 * @throws Exception
	 */
	public function connect($location, $username, $password)
	{
		$header = [];
		$auth = !empty($username) ? $username . ':' . $password : $password;
		$this->_httpRequestAuthorization = 'Basic ' . base64_encode($auth);

		$this->sendHttpRequest('HEAD', $location, null, $header);

		if($this->_httpResponseCode != 200){
			throw new \Exception(__FUNCTION__.':Unexpected response code. ' . $this->_httpResponseCode);
		}

		$this->_remoteUrl = $location;

		return $this;
	}

	/**
	 * @param string $folderPath
	 *
	 * @return array
	 * @throws Exception
	 */
	public function getFolderItemCollection($folderPath)
	{
		$itemCollection = [];
		$remotePath = parse_url($this->_remoteUrl, PHP_URL_PATH);
		$remotePath .= $folderPath;

		$location = $this->_remoteUrl . $folderPath;
		$this->sendHttpRequest('PROPFIND', $location);

		$allowed = [200, 207];
		if(in_array($this->_httpResponseCode, $allowed ) == false){
			throw new \Exception(__FUNCTION__.':Unexpected response code. ' . $this->_httpResponseCode);
		}

		$doc = new \DOMDocument;
		@$doc->loadXML($this->_httpResponseText);
		$nodeList = $doc->getElementsByTagName('href');

		for($i = 1; $i < $nodeList->length; $i++){
			$nodePath = $nodeList->item($i)->textContent;
			if(substr($nodePath, 0, strlen($remotePath)) == $remotePath){
				$nodePath = substr($nodePath, strlen($remotePath));
			}
			if($nodePath == ''){
				continue;
			}
			$itemCollection[] = $nodePath;
		}

		return $itemCollection;
	}

	/**
	 * @param mixed $content
	 * @param string $remotePath
	 *
	 * @return $this
	 * @throws Exception
	 */
	public function upload($content, $remotePath)
	{
		$location = $this->_remoteUrl . $remotePath;
		$this->sendHttpRequest('PUT', $location, $content);

		$allowed = [200, 201, 204];
		if(in_array($this->_httpResponseCode, $allowed ) == false){
			throw new \Exception(__FUNCTION__.':Unexpected response code. ' . $this->_httpResponseCode);
		}
		return $this;
	}

	/**
	 * Upload file to remote host
	 *
	 * @param string $localFile
	 * @param string $remoteFile
	 *
	 * @return $this
	 * @throws Exception
	 */
	public function uploadFile($localFile, $remoteFile)
	{
		if(!is_file($localFile)){
			throw new \Exception(__FUNCTION__.":File not found. '$localFile'");
		}

		$content = file_get_contents($localFile);

		$this->upload($content, $remoteFile);

		return $this;
	}

	/**
	 * Delete file on remote host
	 *
	 * @param string $remoteFile
	 *
	 * @return $this
	 * @throws Exception
	 */
	public function deleteFile($remoteFile)
	{
		$httpRequestUrl = $this->_remoteUrl . '/' . $remoteFile;
		$this->sendHttpRequest('DELETE', $httpRequestUrl);

		$allowed = [
			200,
			204 // Deleted
		];
		if(in_array($this->_httpResponseCode, $allowed ) == false){
			throw new \Exception(__FUNCTION__.':Unexpected response code. ' . $this->_httpResponseCode);
		}

		return $this;
	}

	/**
	 * Create a directory on the remote host
	 *
	 * @param string $remoteDirectory
	 *
	 * @return void
	 * @throws Exception
	 */
	public function createDirectory($remoteDirectory)
	{
		$httpRequestUrl = $this->_remoteUrl . '/' . $remoteDirectory;
		$this->sendHttpRequest('MKCOL', $httpRequestUrl);

		$allowed = [
			200,
			201, // Created
			202, // Accepted
		];
		if(in_array($this->_httpResponseCode, $allowed ) == false){
			throw new \Exception(__FUNCTION__.':Unexpected response code. ' . $this->_httpResponseCode);
		}
	}

	/**
	 * @param $file
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function download($file) {
		$httpRequestUrl = $this->_remoteUrl.'/'.$file;
		$this->sendHttpRequest('GET', $httpRequestUrl);
		if ($this->_httpResponseCode != 200 ) {
			throw new \Exception(__FUNCTION__.':Unexpected response code. '.$this->_httpResponseCode);
		}
		return $this->_httpResponseText;
	}

	/**
	 * @param $file
	 * @param $localFile
	 *
	 * @return false|int
	 * @throws Exception
	 */
	public function downloadFile($file, $localFile) {
		return file_put_contents($localFile, $this->download($file));
	}

	protected function sendHttpRequest($method, $url, $data = null, $addHeader = null)
	{
		$this->_httpResponseCode = null;
		$this->_httpResponseHeader = [];
		$this->_httpResponseText = null;

		$option = ['http' => ['method' => $method,]];

		$header = [];
		$header[] = ['Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'];
		$header[] = ['Accept-Languange' => 'en-US,en;q=0.5'];
		$header[] = ['Authorization' => $this->_httpRequestAuthorization];

		// additional header
		if(is_null($addHeader) == false){
			foreach($addHeader as $pHeaderItem){
				$header[] = $pHeaderItem;
			}
		}

		// cookie
		if(!empty($this->_httpRequestCookie)){
			$cookieItem = [];
			foreach($this->_httpRequestCookie as $cookieName => $cookieValue){
				$cookieItem[] = $cookieName . '=' . $cookieValue;
			}
			$headerCookieValue = implode('; ', $cookieItem);
			$header[] = ['Cookie' => $headerCookieValue];
		}

		$header[] = ['Cache-Control' => 'no-cache'];
		$header[] = ['Upgrade-Insecure-Requests' => '1'];
		$header[] = ['Pragma' => 'no-cache'];

		// post data
		if(!is_null($data)){
			// assume binary data
			$header[] = ['Content-Type' => 'application/octet-stream'];
			$header[] = ['Content-Length' => strlen($data)];
			$option['http']['content'] = $data;
		}

		// http request header
		$headerLine = [];
		foreach($header as $headerItem){
			foreach($headerItem as $headerName => $headerValue){
				$headerLine[] = $headerName . ': ' . $headerValue;
			}
		}
		$headerText = implode("\r\n", $headerLine) . "\r\n";

		$option['http']['header'] = $headerText;

		$context = stream_context_create($option);
		$responseText = file_get_contents($url, false, $context);
		$this->_httpResponseText = $responseText;

		// response header
		foreach($http_response_header as $responseHeader){

			if(preg_match("/^([^:]+): (.+)$/", $responseHeader, $responseMatch) == false){

				// HTTP/1.0 200 OK
				if(preg_match("/^HTTP\/[0-9]\.[0-9] ([0-9]+)/", $responseHeader, $codeMatch)){
					$this->_httpResponseCode = (int)$codeMatch[1];
				}
				continue;
			}

			$headerName = $responseMatch[1];
			$headerValue = $responseMatch[2];

			$this->_httpResponseHeader[] = [$headerName => $headerValue];

			// Handle cookie
			if($headerName == 'Set-Cookie'){
				if(preg_match("/^([^=]+)=([^;]+)/", $headerValue, $cookieMatch)){
					$this->_httpRequestCookie[$cookieMatch[1]] = $cookieMatch[2];
				}
			}
		}
		return true;
	}
}