<?php
class debugmod{
	private $debug = true;
	private $errReporting = false;
	private $log_path = "./debug.log";
	/*
	ReportOnScreen: sets ini display_errors 1
	logPath default: ./debug.log
	*/
	function __construct($reportOnScreen = false,$logPath = null){
		if($logPath !== null){
			$this->log_path = $logPath;
		}
		
		if($reportOnScreen){
			$this->errReporting = true;
		}else{
			$this->errReporting = false;
		}
		
		$this->debugmode();
	}
	
	private function debugmode(){
		if ($this->debug === true) {
			error_reporting( E_ALL );
	
			if ( $this->errReporting ) {
				ini_set('display_errors', 1);
			} elseif ( null !== $this->errReporting ) {
				ini_set('display_errors', 0);
			}
			
			if($this->log_path){
				ini_set('log_errors', 1);
				ini_set('error_log', $this->log_path);
			}
			
		}else{
			error_reporting( E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_ERROR | E_WARNING | E_PARSE | E_USER_ERROR | E_USER_WARNING | E_RECOVERABLE_ERROR );
		}
		
	}
}