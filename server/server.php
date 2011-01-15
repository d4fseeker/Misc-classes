<?php
/*
CODE BY Daniel Ruppert  < daniel@kaffi.lu >
This code is a re-licensed copy of the original code made available under MIT Opensource license.
Original license: proprietary. Noone other than the author may use, copy, modify or visualize the code or any resulting forms. Relicensing allowed solely by author
*/

//Where to store PID for IP-Port combination
if(!defined("SOK_PID_FOLDER")) define("SOK_PID_FOLDER",dirname(__FILE__)."/");
//How long to wait between 2 consecutive attempts to listen on socket or port
if(!defined("SOK_RETRY_WAIT")) define("SOK_RETRY_WAIT",1);
//How many times to attempt to listen on socket or port. (0=infinite)
if(!defined("SOK_RETRY_ATTEMPTS")) define("SOK_RETRY_ATTEMPTS",25);
//Enable PHP error reporting
if(!defined("SOK_TRIGGER")) define("SOK_TRIGGER",true);

/**
2-stage signal handler for SIGTERM. (soft/hard)
@param signo Signal Number.
@return [bool] true
*/
declare(ticks = 1);
function sig_handler($signo) {
	if($signo == SIGTERM) {
		if(!defined("SOK_TERMINATE")) {
			define("SOK_TERMINATE",true);
			if(SOK_TRIGGER) trigger_error("SIGTERM received. Gracefully shutting down.",E_USER_WARNING);
		}
		else {
			if(SOK_TRIGGER) trigger_error("Second SIGTERM received. Exiting forcefully!",E_USER_ERROR);
			die(); //If error level is below E_ERROR
		}
	}
	else {
		if(SOK_TRIGGER) trigger_error("Cannot handle received signal. Signoring",E_USER_NOTICE);
	}
	return true;
}
pcntl_signal(SIGTERM, "sig_handler");

//Misc elements
class SOKexception extends Exception {} 
if(!function_exists('array_last_key')) {
	/**
	Returns the last key of a provided array
	@param array [Array] Array to use
	@return [String/Integer] Last key
	*/
	function array_last_key($array) {
		end($arraay);
		return key($array);
	}
}

class socketserver {
	//Master sockets
	var $masters = array();
	//Client sockets
	var $clients = array();
	
	/**
	Prepare a new socket for creation.
	@param type 'TCP' or 'UNIX'
	@param path [String] If type=TCP: 'IP:PORT', type=UNIX: '/path/to/my.socket'
	@return [bool] Success status
	*/
	public function socketRegister($type,$path) {
		if(socketExists($type,$path)) {
			throw new SOKexception('Socket '.$type.'::'.$path.' is already in usage');
			if(SOK_TRIGGER) trigger_error('Socket '.$type.'::'.$path.' is already in usage. Skipping!',E_USER_WARNING);
			return false;
		}
		//Prepare
		$this->masters[] = array('type'=>$type,'path'=>$path,'socket'=>null);
		$master_id = array_last_key($this->masters);
		//Create
		$check = $this->socketCreate($master_id);
		if(!$check) return false;
		return true;
	}
	
	/**
	Checks if a socket is already used by another process
	*/
	public function socketExists($type,$path) {
		$FILE = SOK_PID_FOLDER.md5($type.",".$path).".pid";
		if(!file_exists($FILE)) return false;
		if(!is_readable($FILE)) {
			throw new SOKexception("Permission to PID-file denied: ".$FILE);
			if(SOK_TRIGGER) trigger_error("Permission to PID-file denied: ".$FILE,E_USER_WARNING);
			return false;
		}
		$pid = (int)file_get_contents($FILE);
		if(posix_kill($pid,0) || posix_get_last_error()==1) return true;  //Check if process is dead and not owned by other user (E_PERM)
		return false;
	}
	
	/**
	Create a registered socket if not already online
	@param master_id ID of the master socket to create
	@return [bool] success/failure
	*/
	protected function socketCreate($master_id) {
		if(!isset($this->masters[$master_id])) {
			if(SOK_TRIGGER) trigger_error("Socket Master-ID not registered. Aborting!",E_USER_WARNING);
			return false;
		}
		if(!is_null($this->masters[$master_id]['socket'])) {
			if(SOK_TRIGGER) trigger_error("Socket is already created. socketDestroy() first!",E_USER_NOTICE);
			return true; //technically it's online...
		}
		if($this->masters[$master_id]['type'] == 'TCP') return socketCreateTCP($master_id);
		elseif($this->masters[$master_id]['type'] == 'UNIX') return socketCreateUNIX($master_id);
		else {
			if(SOK_TRIGGER) trigger_error("Undefined socket type. Skipping.",E_USER_WARNING);
			return false;
		}
	}
	
	/**
	Attempt to create a registered TCP-socket. No validation
	@param master_id ID of the master socket to create
	@return [bool] success/failure
	*/
	protected function socketCreateTCP($master_id) {
		$sock = socket_create(AF_INET, SOCK_STREAM, 0); 
	}
	
	
}
?>