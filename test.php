<?php
require 'HttpClient.class.php';
$client = new HttpClient();
//Init
$client->get('http://froxlor.MYDOMAIN.com/');
//Send POST
$client->post('/index.php',array('loginname'=>'admin','password'=>'NOPASSWORD','language'=>'English','send'=>'send'));
echo "Redirected ".$client->response['redirect_count']." times, with final link being: ".$client->response['final_path']." and status: ".$client->response['status']." (".$client->response['status_msg'].")";
echo $client->response['content'];
