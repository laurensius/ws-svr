<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Socket_server extends CI_Controller {

	function __construct(){
		parent::__construct();
		require_once APPPATH.'/third_party/class.datahandler.php';
	}
	
	function index(){
		$this->load->view('welcome_message');
	}

	function run_server(){
		$null = NULL;
		$dataHandler = new DataHandler();
		$socketResource = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		socket_set_option($socketResource, SOL_SOCKET, SO_REUSEADDR, 1);
		socket_bind($socketResource, 0, PORT);
		socket_listen($socketResource);
		global $clientSocketArray;
		$clientSocketArray = array($socketResource);
		while (true) {
			$newSocketArray = $clientSocketArray;
			socket_select($newSocketArray, $null, $null, 0, 10);
			
			if (in_array($socketResource, $newSocketArray)) {
				$newSocket = socket_accept($socketResource);
				$clientSocketArray[] = $newSocket;
				$header = socket_read($newSocket, 1024);
				$dataHandler->doHandshake($header, $newSocket, HOST_NAME, PORT);
				socket_getpeername($newSocket, $client_ip_address);
				$connectionACK = $dataHandler->newConnectionACK($client_ip_address);
				$dataHandler->send($connectionACK);
				echo $client_ip_address . " terhubung pada " . date("Ymd H:i:s") . "\r\n";
				$newSocketIndex = array_search($socketResource, $newSocketArray);
				unset($newSocketArray[$newSocketIndex]);
			}
			
			foreach ($newSocketArray as $newSocketArrayResource) {	
				while(socket_recv($newSocketArrayResource, $socketData, 1024, 0) >= 1){
					$socketMessage = $dataHandler->unseal($socketData);
					$messageObj = json_decode($socketMessage);
					$chat_box_message = $dataHandler->messagingProcess($messageObj->client_ip_address,$messageObj->hardware_id, $messageObj->data_packet);
					$dataHandler->send($chat_box_message);
					break 2;
				}
				
				$socketData = @socket_read($newSocketArrayResource, 1024, PHP_NORMAL_READ);
				if ($socketData === false) { 
					socket_getpeername($newSocketArrayResource, $client_ip_address);
					$connectionACK = $dataHandler->connectionDisconnectACK($client_ip_address);
					$dataHandler->send($connectionACK);
					$newSocketIndex = array_search($newSocketArrayResource, $clientSocketArray);
					unset($clientSocketArray[$newSocketIndex]);			
				}
			}
		}
		socket_close($socketResource);
	}

}




