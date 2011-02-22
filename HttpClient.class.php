<?php

/* Version 0.1, January 2011 - Daniel Ruppert ( daniel@kaffi.lu )
   License: Opensource MIT
   Changelog: 
   	[0.1]
   		[2010/01/19] First usuable version (able to login to Froxlor with GET, POST and redirect)
   TODO
	 see ./TODO
*/ 

//http://www.php.net/manual/en/function.realpath.php#85388
function resolve_href ($base, $href) { 
	if($href) $final_slash = (substr($href,-1) == '/')?true:false; //d4f
	// href="" ==> current url. 
    if (!$href) { 
        return $base; 
    } 

    // href="http://..." ==> href isn't relative 
    $rel_parsed = parse_url($href); 
    if (array_key_exists('scheme', $rel_parsed)) { 
        return $href; 
    } 

    // add an extra character so that, if it ends in a /, we don't lose the last piece. 
    $base_parsed = parse_url("$base "); 
    // if it's just server.com and no path, then put a / there. 
    if (!array_key_exists('path', $base_parsed)) { 
        $base_parsed = parse_url("$base/ "); 
    } 

    // href="/ ==> throw away current path. 
    if ($href{0} === "/") { 
        $path = $href; 
    } else { 
        $path = dirname($base_parsed['path']) . "/$href"; 
    } 

    // bla/./bloo ==> bla/bloo 
    $path = preg_replace('~/\./~', '/', $path); 

    // resolve /../ 
    // loop through all the parts, popping whenever there's a .., pushing otherwise. 
        $parts = array(); 
        foreach ( 
            explode('/', preg_replace('~/+~', '/', $path)) as $part 
        ) if ($part === "..") { 
            array_pop($parts); 
        } elseif ($part!="") { 
            $parts[] = $part; 
        } 

		//d4f final-slash patched
    $url = ( 
        (array_key_exists('scheme', $base_parsed)) ? 
            $base_parsed['scheme'] . '://' . $base_parsed['host'] : "" 
    ) . "/" . implode("/", $parts); 
	
	if($final_slash && (substr($url,-1) != '/')) $url .= '/';
	
	return $url;
	

}

function array_last_key($array) {
	end($array);
	return key($array);
}

class HttpClient {
	//Connection parameter [Only edit if reqest->auto_connect is false]
	var $connection = array(
				'timeout'		=>	5,			//Try to establish TCP connection for X seconds
				'encoding_accept'	=>	true,			//Allow response data compression
				'encoding_supported'	=>	array('gzip'=>true),	//Which compression method to allow
				'http_version'		=>	'1.0',			//Recommended HTTP/1.0. 1.1 supports chunked, but we don't (yet)
				//Automatically set
				'domain'		=>	'',			//Domain of the final path
				'port'			=>	80,			//Port of the final path
				'ssl'			=>	false,			//Weither connection is ssl
				'ssl_tls'		=>	array(),		//Array containing info weither SSL is a TLS
			);
	//Request parameter
	var $request = array(
				'user_agent'		=>	'Seeker/IncubaBrowser 1.0 (like Mozilla/5.0 on Windows NT 5.1)',
				'type'			=>	'GET',			//GET,HEAD,POST		
				'headers_custom'	=>	array(),		//User-defined headers (key=>(count=>value)). Kept until removed by user
				//All headers below can be set to false to disable completely
				'referer'		=>	null,			//Automatically uses response->final_path if null (except if empty).
				'accept_format'		=>	'text/xml,application/xml,application/xhtml+xml,text/html,text/plain,image/png,image/jpeg,image/gif,*/*',
				'accept_language'	=>	'en-us',		//Preferred language. 
				
			);
	//Cookie parameters
	var $cookie = array(
				'enable'		=>	true,			//Enable cookie support
				'store'			=>	array(),		//Cookie storage location
				
			);
	//Response parameter [DO NOT EDIT]
	var $response = array(
				'final_path'		=>	'',			//Final path after all redirects have been applied. Includes GET
				'final_folder'		=>	'',			//Absolute folder we are in
				'redirected'		=>	false,			//Have we been redirected?
				'redirect_count'	=>	0,			//How many times have we been redirect
				'redirects'		=>	array(),		//List of all redirects in chronological order with redirection-code. Body is discarded
				'raw'			=>	'',			//Raw response including headers
				'header_request'	=>	'',			//Raw request headers
				'header_response'	=>	'',			//Raw Headers of response
				'headers'		=>	array(),		//Response headers in key=>(count=>value) format
				'content'		=>	'',			//Response content
				'status'		=>	0,			//Response Status code
				'status_msg'		=>	'',			//Response Status Message
			);
	//Performance settings
	var $performance = array(
				'header_only'		=>	false,			//Stop retreiving response after header has been received. (Sets response->content to null)
				'redirect_max'		=>	10,			//Max redirects per query
				'allow_redirect'	=>	true,			//Automatically redirect
			);
	//HTTP-Auth settings
	var $http_auth = array(
				'method'		=>	'basic',		//Currently only 'basic' is supported, no digest..
				'username'		=>	'',			//Username
				'password'		=>	'',			//Password
			);
			
	
	public function __construct() {
		//Nothing to do yet ;)
	}
	
	public function get($uri) {
		$this->request('get',$uri,false);
	}
	
	public function post($uri,$data) {
		$this->request('post',$uri,$data);
	}
	
	public function request($mode,$uri,$data=false,$first=true) {
		$header = $this->buildRequest($mode,$uri,$data);
		$info = $this->urlToParts($uri);
		$this->response['final_path'] = $info['path'].($info['query']?('?'.$info['query']):'');
		$this->response['final_folder'] = substr($info['path'],0,strpos($info['path'],'/')).'/';
		$this->response['header_request'] = $header;
		if($first) {;
			$this->response['redirected'] = false;
			$this->response['redirect_count'] = 0;
			$this->response['redirects'] = array();
		}
		//Connect to SSL or TCP depending on requirement
		if($info['ssl']) {
			if(isset($this->connection->ssl_tls[$info['domain']])) {
				if($this->connection->ssl_tls[$info['domain']] && !$fp = fsockopen('tls://'.$info['domain'],$info['port'],$errno,$errstr,$this->connection['timeout'])) {
					throw new Exception("Could not connect to TLS ".$info['domain'].":".$info['port']." :: ".$errno." (".$errstr.")");
					return false;
				}
				elseif(!$fp = fsockopen('ssl://'.$info['domain'],$info['port'],$errno,$errstr,$this->connection['timeout'])) {
					throw new Exception("Could not connect to SSL ".$info['domain'].":".$info['port']." :: ".$errno." (".$errstr.")");
					return false;
				}
			}
			//Attempt to use the newer TLS (=SSL3) before falling back to SSL2
			elseif(!$fp = fsockopen('tls://'.$info['domain'],$info['port'],$errno,$errstr,$this->connection['timeout'])) {
				if(!$fp = fsockopen('ssl://'.$info['domain'],$info['port'],$errno,$errstr,$this->connection['timeout'])) {
					throw new Exception("Could not connect to SSL ".$info['domain'].":".$info['port']." :: ".$errno." (".$errstr.")");
					return false;
				}
				else $this->connection->ssl_tls[$info['domain']] = false;
			}
			else $this->connection->ssl_tls[$info['domain']] = true;
		}
		else {
			if(!$fp = fsockopen($info['domain'],$info['port'],$errno,$errstr,$this->connection['timeout'])) {
				throw new Exception("Could not connect to TCP ".$info['domain'].":".$info['port']." :: ".$errno." (".$errstr.")");
				return false;
			}
		}
		socket_set_timeout($fp, $this->connection['timeout']);
		//Write header
		fwrite($fp,$header);
		//Read response
		$header = array();
		$header_raw = '';
		$content = '';
		$first_line = true;
		$in_header = true;
		while (!feof($fp)) {
			if($in_header) $line = fgets($fp, 4096);
			else $line = fread($fp,4096); //Binary content protection
    	    		if ($first_line) {
    	    			$first_line = false;
    	        		if (!preg_match('/HTTP\/(\\d\\.\\d)\\s*(\\d+)\\s*(.*)/', $line, $m)) {
    	            			throw new Exception("Status code line invalid: ".htmlentities($line));
    	            			return false;
    	            		}
    	            		$this->response['status'] = $m[2];
    	            		$this->response['status_msg'] = $m[3];
    	            		continue;
    	            	}
    	            	if($in_header) {
    	            		//End of header
    	            		if(trim($line) == '') {
    	            			$in_header = false;
    	            			if($this->performance['header_only']) break;
    	            			continue;
    	            		}
    	            		//Skip lines without usuable informations
    	            		if (!preg_match('/([^:]+):\\s*(.*)/', $line, $m)) {
    	            			// Skip to the next header
    	            			continue;
    	        		}
    	        		//Parse header line
    	        		$key = strtolower(trim($m[1]));
    	        		$value = trim($m[2]);
    	        		if(!isset($header[$key])) $header[$key] = array();
    	        		$header[$key][] = $value;
    	        		$header_raw .= $line;
    	            	}
    	            	else $content .= $line;
		}
		fclose($fp);
		//Write details
		$this->response['raw'] = $header_raw."\r\n".$content;
		$this->response['header_response'] = $header_raw;
		$this->response['headers'] = $header;
		$this->connection['domain'] = $info['domain'];
		$this->connection['port'] = $info['port'];
		$this->connection['ssl'] = $info['ssl'];
		
		//Add cookies to the jar
		if(isset($header['set-cookie'])) {
			foreach($header['set-cookie'] as $cookielist) {
				$cookielist = explode(",",$cookielist);
				foreach($cookielist as $cookie) {
					$cookie = explode(" ",$cookie); $cookie = $cookie[0]; //Ignore path, domain and time
					//Some cookies are broken (valueless)
					@list($key,$value) = explode("=",$cookie);
					$value = substr($value,0,-1);
					$this->cookie['store'][$info['domain']][$key] = $value;
				}
			}
		}
		//Content decoding
        	if (isset($header['content-encoding']) && $header['content-encoding'][0] == 'gzip') {
           		$content = substr($content, 10); // See http://www.php.net/manual/en/function.gzencode.php
           	  	$content = gzinflate($content);
		}
         	$this->response['content'] = $content;
         	//Redirection handling
         	if(isset($header['location'])) {
         		$this->response['redirected'] = true;
         		//Check if redirect allowed
         		if($this->response['redirect_count'] >= $this->performance['redirect_max']) {
         			throw new Exception("Reached limit of ".$this->performance['redirect_max']." redirections. Aborting");
         			return false;
         		}
         		$this->response['redirect_count']++;
         		//Get new URL
         		$parts = explode(" ",$header['location'][0]);
         		$url = $parts[0];
         		$status = isset($parts[1])?$parts[1]:302;
         		//Update info
         		$this->response['redirects'][] = array('code'=>$status,'url'=>resolve_href($this->response['final_path'],$url));
         		return $this->request('get',$url,false,false);
         	}
         	return true;
	}
	
	function urlToParts($url) {
		//Rudimentary caching support
		static $cached_url = array('url'=>null,'info'=>null);
		if($url == $cached_url['url']) return $cached_url['info'];
		//New request
		$is_full = false;
		$info = array('ssl'=>false,'domain'=>'','port'=>80,'path'=>'','query'=>false);
		//Check if ssl
    		if(substr($url,0,7) == 'http://') {
    			$is_full = true;
    			$domainpath = substr($url,7);  //strlen('http://')
    		}
    		elseif(substr($url,0,8) == 'https://') {
    			$is_full = true;
    			$domainpath = substr($url,8);  //strlen('https://')
    			$info['ssl'] = true;
    			$info['port'] = 443;
    		}
    		else {
    			$info['ssl'] = $this->connection['ssl'];
    			$info['domain'] = $this->connection['domain'];
    			$info['port'] = $this->connection['port'];
    		}
    		//Get domain name [and port] if full url
    		if($is_full) {
    			if(strpos($domainpath,'/') !== false) $domain = substr($domainpath,0,strpos($domainpath,'/'));
    			else $domain = $domainpath;
    			$domain = explode(":",$domain);
    			if(isset($domain[1])) $info['port'] = $domain[1];
    			$info['domain'] = $domain[0];
    		}
    		else {
    			$info['domain'] = $this->connection['domain'];
    			$info['port'] = $this->connection['port'];
    		}
    		//Get full path
    		if($is_full) {
    			if(strpos($domainpath,"/") !== false) $path = substr($domainpath,strpos($domainpath,"/"));
    			else $path = '/';
    		}
    		else $path = $url;
			//Correct relative path's
			$path = resolve_href($this->response['final_path'],$path);
    		//Extract GET parameter
    		if(strpos($path,'?') !== false) {
    			$info['query'] = substr($path,strpos($path,'?')+1);
    			if(empty($info['query'])) $info['query'] = false; //Sometimes we only have a '?' but no GET content
    			$path = substr($path,0,strpos($path,'?'));
    		}
    		else $info['query'] = false;
			//Add path to list
			$info['path'] = $path;
    		//And done
    		$cached_url['url'] = $url;
    		$cached_url['info'] = $info;
    		return $info;
	}
	
	protected function buildRequest($method,$path,$post=false) {
    		$method = strtoupper($method);
    		//Get info
    		$pathinfo = $this->urlToParts($path);
    		$headers = array();
    		//First line: Method, Path and HTTP Version
    		$headers[] = $method." ".$pathinfo['path'].($pathinfo['query']?'?'.$pathinfo['query']:'')." HTTP/".$this->connection['http_version'];
    		//Host
    		$headers[] = "Host: ".$pathinfo['domain'];
    		//User-Agent [optional]
    		if($this->request['user_agent']) $headers[] = "User-Agent: ".$this->request['user_agent'];
    		//Accept contents [optional]
    		if($this->request['accept_format']) $headers[] = "Accept: ".$this->request['accept_format'];
    		//Accept content encoding [optional]
    		if($this->connection['encoding_accept'] && $this->connection['encoding_supported']['gzip']) $headers[] = "Accept-encoding: gzip";
    		//Accept language [optional]
    		if($this->request['accept_language']) $headers[] = "Accept-language: ".$this->request['accept_language'];
    		//Referer
    		if($this->request['referer'] !== false) {
    			if($this->request['referer'] == null) {
    				if(!empty($this->response['final_path'])) $referer = $this->response['final_path'];
    				else $referer = false; //No referer yet. It's first visit
    			}
    			else $referer = $this->request['referer'];
		}
		else $referer = false;
		if($referer) $headers[] = "Referer: ".$referer;
		//Cookies
		if($this->cookie['enable'] && isset($this->cookie['store'][$pathinfo['domain']]) && count($this->cookie['store'][$pathinfo['domain']])) {
			$cookie = "Cookie: ";
			foreach($this->cookie['store'][$pathinfo['domain']] as $name=>$value) {
				$cookie .= $name."=".$value."; ";
			}
			$headers[] = $cookie;
		}
		//HTTP Auth
		if(!empty($this->http_auth['username']) && !empty($this->http_auth['password'])) {
			$headers[] = "Authorization: BASIC ".base64_encode($this->http_auth['username'].":".$this->http_auth['password']);
		}
		//POST
		if($method == "POST") {
			$poststring = '';
			foreach($post as $key=>$value) {
				$poststring .=  rawurlencode($key)."=".rawurlencode($value)."&";
			}
			$poststring = substr($poststring,0,-1); //remove ending '&'
			$headers[] = "Content-Type: application/x-www-form-urlencoded";
			$headers[] = "Content-Length: ".strlen($poststring);
		}
		else $poststring = '';
		//Final touch
		return implode("\r\n", $headers)."\r\n\r\n".$poststring;
    }
}
?>
