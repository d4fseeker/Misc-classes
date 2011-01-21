<?php
/* CONFIG START - - - - - */

//IP of Whois-Server to listen on
define("WHOISSERVER_IP","127.0.0.1");
//IP is IPv6  (Otherwise IPv4)
define("WHOISSERVER_IP6",false);
//Port to listen on. Default: 43
define("WHOISSERVER_PORT",43);
//Child process time limit or 0 to disable
define("WHOISSERVER_TIMELIMIT",10);
//Domain Blacklist file
define("WHOISSERVER_BLACKLIST",dirname(__FILE__)."/blacklist.ini");

/* CONFIG END - - - - - - */

if(WHOISSERVER_BLACKLIST) $BLACKLIST = parse_ini_file(WHOISSERVER_BLACKLIST,true);
else $BLACKLIST = array();

require_once dirname(__FILE__).'/domaincheck.class.php';
$domaincheck =  new domaincheck;

$SOCKET = socket_create((WHOISSERVER_IP6?AF_INET6:AF_INET), SOCK_STREAM, SOL_TCP); 
while(!socket_bind($SOCKET, WHOISSERVER_IP, WHOISSERVER_PORT)) {
	trigger_error("Could not bind to ".(WHOISSERVER_IP6?"IPv6":"IPv4")."->".WHOISSERVER_IP.":".WHOISSERVER_PORT,E_USER_WARNING);
	sleep(3);
}
trigger_error("Successfully opened Socket ".(WHOISSERVER_IP6?"IPv6":"IPv4")."->".WHOISSERVER_IP.":".WHOISSERVER_PORT,E_USER_NOTICE);
socket_listen($SOCKET);

//Master process
$PID = 1; $CLIENT = null;
while($PID) {
	@socket_close($CLIENT);
	$CLIENT = socket_accept($SOCKET);
	$PID = pcntl_fork();
}

//Child process
@socket_close($SOCKET);
set_time_limit(WHOISSERVER_TIMELIMIT);
$domain_name = strtolower(trim(socket_read($CLIENT,1024)));
if(substr($domain_name,-1) == '.') $domain_name = substr($domain_name,0,-1);
list($domain_sld,$domain_tld) = explode(".",$domain_name);
try {
	$domain_status = $domaincheck->check($domain_name);
	if($domain_status) $status = "TAKEN";
	elseif($BLACKLIST && isset($BLACKLIST[$domain_tld][$domain_name]) && $BLACKLIST[$domain_tld][$domain_name] == 1) $status = "BLACKLISTED";
	else $status = "AVAILABLE";
	socket_write($CLIENT,$domain_name." ".$status."\n\n");
}
catch(Exception $e) {
	socket_write($CLIENT,$domain_name." ERROR\n\n");
}
socket_close($CLIENT);
echo "\nGot domain: ".$domain_name;
?>