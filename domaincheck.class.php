<?php
define("DNS_DIG_PATH","/usr/bin/dig");

class domaincheck {
	
	function check($domain) {
		if(substr($domain,-1) == '.') $domain = substr($domain,0,-1); //Remove trailing dot
		$parts = explode(".",$domain);
		if(count($parts) != 2) {
			throw new Exception("Domain must be of scheme example.tld or example.tld. -> Subdomains not allowed");
			return false;
		}
		//Check domain extension
		if(!$dns_ext = $this->domainExtension($parts[1])) return false;
		//Domain status
		$status = $this->queryStatus($dns_ext,$domain.".");
		switch($status) {
			case "NXDOMAIN":
				return false;
			default:
				return true;
		}
	}
	
	function domainExtension($extension) {
		$records = @dns_get_record($extension.".",DNS_NS);
		$record_count = count($records);
		if(!$records) {
			throw new Exception("Domain extension ".$extension." does not exist");
			return false;
		}
		return $records[rand(0,$record_count-1)]['target'];
	}
	
	function queryStatus($ns,$domain) {
		$response = shell_exec(DNS_DIG_PATH." @".$ns." NS ".$domain);
		if(strpos($response,"opcode: QUERY, status:") === false) {
			throw new Exception("Dig failed: ".$response);
			return false;
		}
		$info = substr($response,strpos($response,"opcode: QUERY, status:")+strlen("opcode: QUERY, status:"));
		$info = trim(substr($info,0,strpos($info,",")));
		return $info;
	}
}

/* TEST CODE
$class = new domaincheck;
$domains = array("example.org","doesnotexist12345.net","kaffi.lu","alutashop.com","design4you12.lu","example.ovh");
foreach($domains as $domain) {
	try {
		$domain_status = $class->check($domain);
		echo "\nDomain ".$domain." does ".($domain_status?'':'NOT')." exist";
	}
	catch(Exception $e) {
		echo "\n".$e->getMessage()." for domain ".$domain;
	}
	
}
*/
?>