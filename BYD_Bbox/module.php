<?php
include_once("CRC.php");

abstract class LogLevel
{
    const ALL = 9;
	const TEST = 8;
	const TRACE = 7;
	const COMMUNICATION = 6;
	const DEBUG = 5;
	const INFO = 4;
	const WARN = 3;
	const ERROR = 2;
	const FATAL = 1;
}

abstract class VARIABLE
{
    const TYPE_BOOLEAN = 0;
    const TYPE_INTEGER = 1;
    const TYPE_FLOAT = 2;
    const TYPE_STRING = 3;
}


	class BYD_Bbox extends IPSModule {

		use CRC;

		const CRC_INIT = 0x0284;
		const CRC_XOROUT = 0x0000;
		const CRC_POLY = 0x8005;

		private $logLevel = 3;
		private $enableIPSLogOutput = false;
		private $parentRootId;
		private $archivInstanzID;

		public function __construct($InstanceID) {
		
			parent::__construct($InstanceID);		// Diese Zeile nicht löschen

			if(IPS_InstanceExists($InstanceID)) {

				$this->parentRootId = IPS_GetParent($this->InstanceID);
				$this->archivInstanzID = IPS_GetInstanceListByModuleID("{43192F0B-135B-4CE7-A0A7-1475603F3060}")[0];

				$currentStatus = $this->GetStatus();
				if($currentStatus == 102) {				//Instanz ist aktiv
					$this->logLevel = $this->ReadPropertyInteger("LogLevel");
					if($this->logLevel >= LogLevel::TRACE) { $this->AddLog(__FUNCTION__, sprintf("Log-Level is %d", $this->logLevel), 0); }
				} else {
					if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf("Current Status is '%s'", $currentStatus), 0); }	
				}

			} else {
				IPS_LogMessage("[" . __CLASS__ . "] - " . __FUNCTION__, sprintf("INFO: Instance '%s' not exists", $InstanceID));
			}
		}

		public function Create() {
			//Never delete this line!
			parent::Create();

			$this->ConnectParent("{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}");

			$this->RegisterPropertyBoolean('AutoUpdate', false);
			$this->RegisterPropertyInteger("TimerInterval", 30);		
			$this->RegisterPropertyInteger("LogLevel", 4);

			$this->RegisterPropertyBoolean("cb_AppData1", false);
			$this->RegisterPropertyBoolean("cb_AppData2", false);

			$this->RegisterPropertyInteger("ns_UpdateMultiplier", 2);	
			$this->RegisterPropertyBoolean("cb_PlusSystemInfo", false);
			$this->RegisterPropertyBoolean("cb_PlusDiagnosis", false);
			$this->RegisterPropertyBoolean("cb_PlusHistory", false);


			$this->RegisterTimer('TimerMidnightBYD', 0, 'BYD_TimerMidnightBYD($_IPS["TARGET"]);');
			$this->RegisterTimer('TimerAutoUpdateBYD', 0, 'BYD_TimerAutoUpdateBYD($_IPS["TARGET"]);');
			//$this->RegisterTimer('TimerAutoUpdateBYD', 0, 'BYD_Timer_AutoUpdate($_IPS[\'TARGET\']);');

		}

		public function Destroy() {
			$this->SetUpdateInterval(0);		//Stop Auto-Update Timer
			parent::Destroy();					//Never delete this line!
		}

		public function ApplyChanges() {
			//Never delete this line!
			parent::ApplyChanges();

			$this->logLevel = $this->ReadPropertyInteger("LogLevel");
			if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, sprintf("Set Log-Level to %d", $this->logLevel), 0); }
			
			if (IPS_GetKernelRunlevel() != KR_READY) {
				if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, sprintf("GetKernelRunlevel is '%s'", IPS_GetKernelRunlevel()), 0); }
				//return;
			}

			if ((float) IPS_GetKernelVersion() < 4.2) {
				$this->RegisterMessage(0, IPS_KERNELMESSAGE);
			} else {
				$this->RegisterMessage(0, IPS_KERNELSTARTED);
				$this->RegisterMessage(0, IPS_KERNELSHUTDOWN);
			}

			$this->RegisterMessage($this->InstanceID, IM_CONNECT);
			$this->RegisterMessage($this->InstanceID, IM_DISCONNECT);
			$this->RegisterMessage($this->InstanceID, IM_CHANGESTATUS);
			$this->RegisterMessage($this->InstanceID, IM_CHANGESETTINGS);
			$this->RegisterMessage($this->InstanceID, FM_CONNECT);
			$this->RegisterMessage($this->InstanceID, FM_DISCONNECT);

			$conID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
			if($conID > 0) {

				if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, sprintf("Instance ConnectionID is '%s' >> RegisterMessages for this Connection ...", $conID), 0); }
				
				$this->RegisterMessage($conID , IM_CONNECT);
				$this->RegisterMessage($conID , IM_DISCONNECT);		
				if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, sprintf("RegisterMessage 'IM_CONNECT' and 'IM_DISCONNECT' for Instanz '%s'", $conID), 0); }	

				$this->RegisterMessage($conID , IM_CHANGESTATUS);
				$this->RegisterMessage($conID , IM_CHANGESETTINGS);		
				if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, sprintf("RegisterMessage 'IM_CHANGESTATUS' and 'IM_CHANGESETTINGS' for Instanz '%s'", $conID), 0); }	

				$this->RegisterMessage($conID , IS_ACTIVE);
				$this->RegisterMessage($conID , IS_INACTIVE);		
				if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, sprintf("RegisterMessage 'IS_ACTIVE' and 'IS_INACTIVE' for Instanz '%s'", $conID), 0); }	

				$this->RegisterMessage($conID , FM_CONNECT);
				$this->RegisterMessage($conID , FM_DISCONNECT);		
				if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, sprintf("RegisterMessage 'FM_CONNECT' and 'FM_DISCONNECT' for Instanz '%s'", $conID), 0); }	

			} else {
				if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, sprintf("Instance ConnectionID is '%s'", $conID), 0); }	
			}
	

			$this->RegisterProfiles();
			$this->RegisterVariables();  

			$this->CreateBattVoltageCurrentChart($this->InstanceID, 10) ;
			$this->CreateCellVoltageChart($this->InstanceID, 11) ;
			$this->CreateCellTempChart($this->InstanceID, 12) ;
			
			$autoUpdate = $this->ReadPropertyBoolean("AutoUpdate");		
			if($autoUpdate) {
				$timerInterval = $this->ReadPropertyInteger("TimerInterval");
			} else {
				$timerInterval = 0;
			}

			$this->SetTimerMidnight();
			$this->SetUpdateInterval($timerInterval);


		}

		public function MessageSink($TimeStamp, $SenderID, $Message, $Data) {
 			$logMsg = sprintf("Message from SenderID '%s' with Message '%s'\r\n Data: %s", $SenderID, $Message, print_r($Data, true));
			//IPS_LogMessage("MessageSink", $logMsg);
			if($this->logLevel >= LogLevel::TRACE) { $this->AddLog(__FUNCTION__, $logMsg, 0); }
		}


		public function RequestBeConnectAppData1(string $Text) {
			if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, "Request BeConnect App Data -  SerialNr and Fireware Version(s)...", 0); }
			$reqData = array(0x01, 0x03, 0x00, 0x00, 0x00, 0x13, 0x04, 0x07);
			$reqData = implode(array_map("chr", $reqData));
			$this->SendData($reqData);
		}

		public function RequestBeConnectAppData2(string $Text) {
			if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, "Request BeConnect App Data - Measurement Data (Voltages, Current, Temp, SOC, SOH) ...", 0); }
			$reqData = array(0x01, 0x03, 0x05, 0x00, 0x00, 0x19, 0x84, 0xcc);
			$reqData = implode(array_map("chr", $reqData));
			$this->SendData($reqData);
		}


		public function RequestBeConnectPlusSystemInfo(string $Text) {
			if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, "Request BeConnect Plus - System Info ...", 0); }
			//$reqData = array(0x01, 0x03, 0x05, 0x00, 0x00, 0x19, 0x84, 0xcc);
			//$reqData = implode(array_map("chr", $reqData));
			//$this->SendData($reqData);
		}


		public function RequestBeConnectPlusDiagnosis(string $Text) {
			if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, "Request BeConnect Plus - Diagnosis Data ...", 0); }


			$reqData = array(0x02, 0x04, 0x05, 0xB4, 0x01, 0x03, 0x03, 0x08, 0x01, 0x01, 0x04, 0x02);		//connect
			//$this->SendData(implode(array_map("chr", $reqData)));
			IPS_Sleep(350);

			$reqData = array(0x01, 0x03, 0x00, 0x00, 0x00, 0x66, 0xC5, 0xE0);		//INFO
			//$this->SendData(implode(array_map("chr", $reqData)));
			IPS_Sleep(150);
			$reqData = array(0x01, 0x03, 0x05, 0x00, 0x00, 0x19, 0x84, 0xCC);		//INFO
			//$this->SendData(implode(array_map("chr", $reqData)));
			IPS_Sleep(150);
			$reqData = array(0x01, 0x03, 0x00, 0x10, 0x00, 0x03, 0x04, 0x0E);		//INFO
			//$this->SendData(implode(array_map("chr", $reqData)));
			IPS_Sleep(350);			

			$reqData = array(0x01, 0x10, 0x05, 0x50, 0x00, 0x02, 0x04, 0x00, 0x01, 0x81, 0x00, 0xF8, 0x53);
			$this->SendData(implode(array_map("chr", $reqData)));
			IPS_Sleep(1000);
			$reqData = array(0x01, 0x03, 0x05, 0x51, 0x00, 0x01, 0xD5, 0x17);
			$this->SendData(implode(array_map("chr", $reqData)));
			IPS_Sleep(500);
			$reqData = array(0x01, 0x03, 0x05, 0x58, 0x00, 0x41, 0x04, 0xE5);
			$this->SendData(implode(array_map("chr", $reqData)));
			IPS_Sleep(250);
			$reqData = array(0x01, 0x03, 0x05, 0x58, 0x00, 0x41, 0x04, 0xE5);
			$this->SendData(implode(array_map("chr", $reqData)));
			IPS_Sleep(250);
			$reqData = array(0x01, 0x03, 0x05, 0x58, 0x00, 0x41, 0x04, 0xE5);
			$this->SendData(implode(array_map("chr", $reqData)));
			IPS_Sleep(250);
			$reqData = array(0x01, 0x03, 0x05, 0x58, 0x00, 0x41, 0x04, 0xE5);
			$this->SendData(implode(array_map("chr", $reqData)));
		}

		public function RequestBeConnectPlusHistory(string $Text) {
			if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, "Request BeConnect Plus - History Data ...", 0); }
			//$reqData = array(0x01, 0x03, 0x05, 0x00, 0x00, 0x19, 0x84, 0xcc);
			//$reqData = implode(array_map("chr", $reqData));
			//$this->SendData($reqData);
		}		

		public function SendData(string $data) {

			$masterOnOff = GetValue($this->GetIDForIdent("masterOnOff"));
			if($masterOnOff) {
				$currentStatus = $this->GetStatus();
				if($currentStatus == 102) {		
					$connectionState = $this->OpenConnection();
					if($connectionState == 102) {
						if($this->logLevel >= LogLevel::COMMUNICATION) { $this->AddLog(__FUNCTION__, $data, 1); }
						$this->SendDataToParent(json_encode(Array("DataID" => "{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}", "Buffer" => utf8_encode($data))));
						SetValue($this->GetIDForIdent("requestCnt"), GetValue($this->GetIDForIdent("requestCnt")) + 1);  	
					} else {
						if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, sprintf("Connection NOT activ [Status=%s]", $connectionState), 0); }
					}
				} else {
					SetValue($this->GetIDForIdent("instanzInactivCnt"), GetValue($this->GetIDForIdent("instanzInactivCnt")) + 1);
					if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, sprintf("Instanz '%s - [%s]' not activ [Status=%s]", $this->InstanceID, IPS_GetName($this->InstanceID), $currentStatus), 0); }
					$this->CloseConnection();
				}
			} else {
				if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, "Master-Switch is OFF > SendData() canceled > Check Connection Closed ...", 0); }
				$this->CloseConnection();
			}

		}

		public function ReceiveData($JSONString) {
			SetValue($this->GetIDForIdent("receiveCnt"), GetValue($this->GetIDForIdent("receiveCnt")) + 1);  											
			SetValue($this->GetIDForIdent("LastDataReceived"), time()); 
			$data = json_decode($JSONString);
			$rawData = utf8_decode($data->Buffer);
			if($this->logLevel >= LogLevel::COMMUNICATION) { $this->AddLog(__FUNCTION__, $rawData, 1); }
			$this->ProcessData($rawData);
		}


		public function SetTimerMidnight() {
			$midnight = strtotime('tomorrow midnight') + 5;
			$diff = $midnight - time();
			$interval = $diff * 1000;
			$logMsg = sprintf("Set Midnight Timer for '%s' to %s [Interval: %s ms]", $this->InstanceID, date('d.m.Y H:i:s', $midnight), $interval);
			if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, $logMsg, 0); }
			IPS_LogMessage(__CLASS__ . " - " . __FUNCTION__, $logMsg);
			$this->SetTimerInterval("TimerMidnightBYD", $interval);
		}

		public function TimerMidnightBYD() {
			$logMsg = "TimerMidnightBYD occurred > Reset 'cellVoltageDiffMaxToday' ...";
			if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, $logMsg, 0); }
			IPS_LogMessage(__CLASS__ . " - " . __FUNCTION__, $logMsg);	
					
			SetValue($this->GetIDForIdent("cellVoltageDiffMaxToday"), 0); 

			$parentId = $this->GetCategoryID("Diagnosis", "Diagnosis", $this->parentRootId);
			if($parentId !== false) {
				$varId = @IPS_GetObjectIDByIdent("cellDriftMaxToday", $parentId);
						if($varId !== false) {
							SetValue($varId, 0); 
						}
			}

			$this->SetTimerMidnight();

			$this->RequestBeConnectAppData1("via Midnight Timer");
		}


		public function SetUpdateInterval(int $timerInterval) {
			if ($timerInterval == 0) {  
				if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, "Auto-Update stopped [TimerIntervall = 0]", 0); }	
			}else if ($timerInterval < 5) { 
				$timerInterval = 5; 
				if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, sprintf("Set Auto-Update Timer Intervall to %s sec", $timerInterval), 0); }	
			} else {
				if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, sprintf("Set Auto-Update Timer Intervall to %s sec", $timerInterval), 0); }
			}
			$this->SetTimerInterval("TimerAutoUpdateBYD", $timerInterval*1000);	
		}

		public function TimerAutoUpdateBYD() {
			
			$connectionState = $this->GetConnectionState();

			$masterOnOff = GetValue($this->GetIDForIdent("masterOnOff"));
			if($masterOnOff) {
				if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, "Initiate update ...", 0); }

				if($this->ReadPropertyBoolean("cb_AppData1")) 			{ $this->RequestBeConnectAppData1(""); IPS_Sleep(250); }
				if($this->ReadPropertyBoolean("cb_AppData2")) 			{ $this->RequestBeConnectAppData2(""); IPS_Sleep(250); }
				SetValue($this->GetIDForIdent("beConnectAppUpdateCnt"), GetValue($this->GetIDForIdent("beConnectAppUpdateCnt")) + 1);  


				$updateMultiplier = 1;
				if(GetValue($this->GetIDForIdent("cellVoltageDiff")) < 0.05) {
					$updateMultiplier = $this->ReadPropertyInteger("ns_UpdateMultiplier");
				}

				$beConnectPlusUpdateHelper = GetValue($this->GetIDForIdent("beConnectPlusUpdateHelper"));
				$beConnectPlusUpdateHelper--;
				if($beConnectPlusUpdateHelper <= 0) {
					SetValue($this->GetIDForIdent("beConnectPlusUpdateHelper"), $updateMultiplier); 
					if($this->ReadPropertyBoolean("cb_PlusSystemInfo")) 	{ $this->RequestBeConnectPlusSystemInfo(""); IPS_Sleep(250); }
					if($this->ReadPropertyBoolean("cb_PlusDiagnosis")) 	{ $this->RequestBeConnectPlusDiagnosis(""); IPS_Sleep(250); }
					if($this->ReadPropertyBoolean("cb_PlusHistory")) 		{ $this->RequestBeConnectPlusHistory(""); IPS_Sleep(250); }		
					
					SetValue($this->GetIDForIdent("beConnectPlusUpdateCnt"), GetValue($this->GetIDForIdent("beConnectPlusUpdateCnt")) + 1);  
					
				} else {
					if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, sprintf("Skip BeConnect Plus Update [%d]", $beConnectPlusUpdateHelper), 0); }
					SetValue($this->GetIDForIdent("beConnectPlusUpdateHelper"), $beConnectPlusUpdateHelper); 
				}

						
			} else {
				if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, sprintf("AutoUpate CANCELED > Master Swich is OFF > Connection State '%s' ...", $this->GetConnectionState()), 0); }
				$this->CloseConnection();
			}
						
		}

		public function GetConnectionState() {
			$connectionState = -1;
			$conID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
			if($conID > 0) {
				$connectionState = IPS_GetInstance($conID)['InstanceStatus'];
			} else {
				$connectionState = 0;
				if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, sprintf("Instanz '%s [%s]' has NO Gateway/Connection [ConnectionID=%s]", $this->InstanceID, IPS_GetName($this->InstanceID), $conID), 0); }
			}
			SetValue($this->GetIDForIdent("connectionState"), $connectionState);
			return $connectionState;
		}

		public function OpenConnection() {
			$connectionState = $this->GetConnectionState();
			if($connectionState != 102) {
				$conID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
				IPS_SetProperty($conID, "Open", true);
				IPS_ApplyChanges($conID);
				IPS_Sleep(150);
				$connectionState = IPS_GetInstance($conID)['InstanceStatus'];
				SetValue($this->GetIDForIdent("connectionState"), $connectionState);
				if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, sprintf("'%s [%s]' InstanceStatus is now '%s'", $conID, IPS_GetName($conID), $connectionState), 0); }
			}
			return $connectionState;
		}

		public function CloseConnection() {
			$connectionState = $this->GetConnectionState();
			if($connectionState == 102) {
				$conID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
				IPS_SetProperty($conID, "Open", false);
				IPS_ApplyChanges($conID);
				IPS_Sleep(150);
				$connectionState = IPS_GetInstance($conID)['InstanceStatus'];
				SetValue($this->GetIDForIdent("connectionState"), $connectionState);
				if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, sprintf("'%s [%s]' InstanceStatus is now '%s'", $conID, IPS_GetName($conID), $connectionState), 0); }
			}
			return $connectionState;
		}

		protected function ProcessData($rawData) {
	
			if($this->StartsWith($rawData,"\x01\x03\x82\x00")) {

				$byteArray = unpack('C*', $rawData);

				$firstByte1 = array_shift($byteArray);
				$firstByte2 = array_shift($byteArray);
				$dataLen = array_shift($byteArray);
				
				$byte_x1 = array_shift($byteArray);
				$byte_x2 = array_shift($byteArray);
		
				$crcByte1 = array_pop($byteArray);
				$crcByte2 = array_pop($byteArray);	
				$crcSOLL = (($crcByte1<<8) + $crcByte2);

				$dataCrcCheck = substr($rawData, 2, -2);
				$crcIST = $this->crc16($dataCrcCheck, self::CRC_POLY, self::CRC_INIT, self::CRC_XOROUT, true, true);
				if($crcIST != $crcSOLL) {
					SetValue($this->GetIDForIdent("crcErrorCnt"), GetValue($this->GetIDForIdent("crcErrorCnt")) + 1);  
					if($this->logLevel >= LogLevel::ERROR) { $this->AddLog(__FUNCTION__, sprintf("CRC Error [IST: %02X | SOLL: %02X]", $crcIST, $crcSOLL), 0); }
					if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf("CRC Details [init: %02X | xorout: %02X | poly: %02X]", self::CRC_INIT, self::CRC_XOROUT, self::CRC_POLY), 0); }
					if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf("rawData     : %s", $this->String2Hex($rawData)), 0); }
					if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf("dataCrcCheck: %s", $this->String2Hex($dataCrcCheck)), 0); }
				} else {
					if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf("DataLen: 0x%02X , CRC ok [IST: 0x%02X | SOLL: 0x%02X]", $dataLen, $crcIST, $crcSOLL), 0); }

					if(($byteArray[38] == 0) AND ($byteArray[39] == 0)) {       
						$this->ExtractDiagnosis_Part4($byteArray); 
					 } else if(($byteArray[94] == 0) AND ($byteArray[95] == 0)) {       
						$this->ExtractDiagnosis_Part1($byteArray);
					 } else if(($byteArray[96] == 0) AND ($byteArray[97] == 0)) {
						$this->ExtractDiagnosis_Part3($byteArray);            
					 } else {
						$this->ExtractDiagnosis_Part2($byteArray);
					 }

				}

			} else if($this->startsWith($rawData,"\x01\x03")) {

				$byteArray = unpack('C*', $rawData);
				//$this->AddLog(__FUNCTION__, sprintf("byteArray: %s", $this->ByteArr2HexStr($byteArray)), 0);
		
				$firstByte1 = array_shift($byteArray);
				$firstByte2 = array_shift($byteArray);
				$dataLen = array_shift($byteArray);
				$crcByte1 = array_pop($byteArray);
				$crcByte2 = array_pop($byteArray);		
				$crcSOLL = (($crcByte1<<8) + $crcByte2);

				//$this->AddLog(__FUNCTION__."_rawData", sprintf("%s", $this->String2Hex($rawData)), 0);
				//$this->AddLog(__FUNCTION__."_byteArray", sprintf("%s", $this->ByteArr2HexStr($byteArray)), 0);
				//$this->AddLog(__FUNCTION__."_firstByte1", sprintf("%02X", $firstByte1), 0);
				//$this->AddLog(__FUNCTION__."_firstByte2", sprintf("%02X", $firstByte2), 0);
				//$this->AddLog(__FUNCTION__."_dataLen", sprintf("%02X", $dataLen), 0);
				//$this->AddLog(__FUNCTION__."_crcByte1", sprintf("%02X", $crcByte1), 0);
				//$this->AddLog(__FUNCTION__."_crcByte2", sprintf("%02X", $crcByte2), 0);

				if($dataLen == 38) {

					$dataCrcCheck = substr($rawData, 2, -2);
					$crcIST = $this->crc16($dataCrcCheck, self::CRC_POLY, self::CRC_INIT, self::CRC_XOROUT, true, true);
					if($crcIST != $crcSOLL) {
						SetValue($this->GetIDForIdent("crcErrorCnt"), GetValue($this->GetIDForIdent("crcErrorCnt")) + 1);  
						if($this->logLevel >= LogLevel::ERROR) { $this->AddLog(__FUNCTION__, sprintf("CRC Error [IST: %02X | SOLL: %02X]", $crcIST, $crcSOLL), 0); }
						if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf("CRC Details [init: %02X | xorout: %02X | poly: %02X]", self::CRC_INIT, self::CRC_XOROUT, self::CRC_POLY), 0); }
						if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf("rawData     : %s", $this->String2Hex($rawData)), 0); }
						if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf("dataCrcCheck: %s", $this->String2Hex($dataCrcCheck)), 0); }
					} else {

						if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf("DataLen: 0x%02X , CRC ok [IST: 0x%02X | SOLL: 0x%02X]", $dataLen, $crcIST, $crcSOLL), 0); }

						$serialNumber = array_slice($byteArray, 0, 19);
						$unkown1 = array_slice($byteArray, 19, 5);
						$bmu_a = array_slice($byteArray, 24, 2);
						$bmu_b = array_slice($byteArray, 26, 2);
						$bms = array_slice($byteArray, 28, 2);
						$unkown2 = array_slice($byteArray, 30);

						$serialNumber = implode(array_map("chr", $serialNumber));
						if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf("extracted 'serialNumber' : %s", $serialNumber), 0); }
						SetValue($this->GetIDForIdent("serial_no"), $serialNumber);  

						$bmu_aTXT = sprintf("%s.%s", $bmu_a[0], $bmu_a[1]);
						if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf("extracted 'BMU-A' : %s [0x%02X 0x%02X @Position 24 ]", $bmu_aTXT, $bmu_a[0], $bmu_a[1]), 0); }
						SetValue($this->GetIDForIdent("bmu_a"), $bmu_aTXT);  

						$bmu_bTXT = sprintf("%s.%s", $bmu_b[0], $bmu_b[1]);
						if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf("extracted 'BMU-B' : %s [0x%02X 0x%02X @Position 26 ]", $bmu_bTXT, $bmu_b[0], $bmu_b[1]), 0); }
						SetValue($this->GetIDForIdent("bmu_b"), $bmu_bTXT);  

						$bmsTxt = sprintf("%s.%s", $bms[0], $bms[1]);
						if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf("extracted 'BMS' : %s [0x%02X 0x%02X @Position 28]", $bmsTxt, $bms[0], $bms[1]), 0); }
						SetValue($this->GetIDForIdent("bms"), $bmsTxt);  

						if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, "SerialNr and Fireware Version(s) received/extracted/saved", 0); }
					}	

				} else if($dataLen == 50) {
	
					$dataCrcCheck = substr($rawData, 2, -2);
					$crcIST = $this->crc16($dataCrcCheck, self::CRC_POLY, self::CRC_INIT, self::CRC_XOROUT, true, true);
					if($crcIST != $crcSOLL) {
						SetValue($this->GetIDForIdent("crcErrorCnt"), GetValue($this->GetIDForIdent("crcErrorCnt")) + 1);  
						if($this->logLevel >= LogLevel::ERROR) { $this->AddLog(__FUNCTION__, sprintf("CRC Error [IST: %02X | SOLL: %02X]", $crcIST, $crcSOLL), 0); }
						if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf("CRC Details [init: %02X | xorout: %02X | poly: %02X]", self::CRC_INIT, self::CRC_XOROUT, self::CRC_POLY), 0); }
						if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf("rawData     : %s", $this->String2Hex($rawData)), 0); }
						if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf("dataCrcCheck: %s", $this->String2Hex($dataCrcCheck)), 0); }
					} else {

						if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf("DataLen: 0x%02X , CRC ok [IST: 0x%02X | SOLL: 0x%02X]", $dataLen, $crcIST, $crcSOLL), 0); }

						$this->ExtractUnsignedValue($byteArray, 0, "SOC", 1);
						$cellVoltageHigh = $this->ExtractUnsignedValue($byteArray, 2, "cellV_High", 100);
						$cellVoltageLow = $this->ExtractUnsignedValue($byteArray, 4, "cellV_Low", 100);
						$this->ExtractUnsignedValue($byteArray, 6, "SOH", 1);
						$current = $this->ExtractSignedValue($byteArray, 8, "current", 10);
						$voltageBatt = $this->ExtractUnsignedValue($byteArray, 10, "voltageBatt", 100);
						$this->ExtractUnsignedValue($byteArray, 12, "cellT_High", 1);
						$this->ExtractUnsignedValue($byteArray, 14, "cellT_Low", 1);
						$voltageOut = $this->ExtractUnsignedValue($byteArray, 32, "voltageOut", 100);

						$voltageDiff = $voltageBatt - $voltageOut;
						$powerLoss = abs($voltageDiff * $current);
						SetValue( $this->GetIDForIdent("voltageDiffCalculated"), round($voltageDiff,3) ); 
						SetValue( $this->GetIDForIdent("powerLossCalculated"), round($powerLoss,3) ); 

						$cellVoltageDiff = abs($cellVoltageHigh-$cellVoltageLow);
						SetValue( $this->GetIDForIdent("cellVoltageDiff"), round($cellVoltageDiff,3) ); 


						$cellVoltageDiffMaxToday = GetValue($this->GetIDForIdent("cellVoltageDiffMaxToday"));
						if($cellVoltageDiff > $cellVoltageDiffMaxToday) {
							SetValue( $this->GetIDForIdent("cellVoltageDiffMaxToday"), round($cellVoltageDiff,3) ); 
						}

						$cellVoltageDiffMaxOverall = GetValue($this->GetIDForIdent("cellVoltageDiffMaxOverall"));
						if($cellVoltageDiff > $cellVoltageDiffMaxOverall) {
							SetValue( $this->GetIDForIdent("cellVoltageDiffMaxOverall"), round($cellVoltageDiff,3) ); 
						}

						if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, "Data received/extracted/saved", 0); }
					}

				} else if($this->startsWith($rawData,"\x01\x03\x82\x00")) {
					if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, sprintf("_receive Part_1: %s", $this->String2Hex($rawData)), 0); }

					//128 Cell Voltage
					// 64 Cell Temp

					$byteArray = unpack('C*', $rawData);

					$firstByte1 = array_shift($byteArray);
					$firstByte2 = array_shift($byteArray);
					$dataLen = array_shift($byteArray);
					$crcByte1 = array_pop($byteArray);
					$crcByte2 = array_pop($byteArray);	

					if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, sprintf("_receive : [%d][%d][%d]", $firstByte1,$firstByte2,$dataLen), 0); }


					$arrLen = count($byteArray);
					for($i=100; $i<$arrLen; $i = $i+2) {
						$cellVoltage = $this->ExtractUnsignedValue($byteArray, $i, "cellV_".$i, 1);
						//if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, sprintf("_received [%s/%s] : %s", $i, $arrLen, $cellVoltage ), 0); }
					}

				} else {
					//$this->AddLog(__FUNCTION__, sprintf("dataLen: %s", $dataLen), 0);
					//$this->AddLog(__FUNCTION__, sprintf("data: %s", $this->ByteArr2HexStr($byteArray)), 0);
					//$this->AddLog(__FUNCTION__, sprintf("crc: %s", $this->ByteArr2HexStr($crc)), 0);
					//if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, sprintf("38 bzw. 50 bytes expected but %d bytes received", $dataLen), 0); }					
					//if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, sprintf("_receive : %s", $this->String2Hex($rawData)), 0); }
				}

			} else {
				//if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, "the received data does not start with '0x01 0x03'", 0); }
				//if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, sprintf("_receive : %s", $this->String2Hex($rawData)), 0); }
			}

		}


		protected function ExtractDiagnosis_Part1($byteArray) {
			if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf("Byte Array Count: %d", count($byteArray)), 0); }
			
			$categoryId = $this->GetCategoryID("Diagnosis", "Diagnosis", $this->parentRootId);


			$cellV_Hight = $this->ExtractSaveValue($byteArray, 0, 2, 1, $categoryId, "cellV_High", "Cell Voltage Max", VARIABLE::TYPE_INTEGER, 0, "BYD_CellVoltage"); 
			$cellV_Low = $this->ExtractSaveValue($byteArray, 2, 2, 1, $categoryId, "cellV_Low", "Cell Voltage Min", VARIABLE::TYPE_INTEGER, 2, "BYD_CellVoltage"); 
			$this->ExtractSaveValue($byteArray, 4, 1, 1, $categoryId, "cellV_HighNo", "Cell Voltage Max No", VARIABLE::TYPE_INTEGER, 4, ""); 
			$this->ExtractSaveValue($byteArray, 5, 1, 1, $categoryId, "cellV_LowNo", "Cell Voltage Min No", VARIABLE::TYPE_INTEGER, 5, ""); 
	
			$cellDrift = $cellV_Hight - $cellV_Low;
			$this->SaveVariableValue($cellDrift, $categoryId, "cellDrift", "Cell Drift", VARIABLE::TYPE_INTEGER, 6, "BYD_CellVoltage");
			$this->SaveVariableValue($cellDrift, $categoryId, "cellDriftMaxToday", "Cell Drift Max (Today)", VARIABLE::TYPE_INTEGER, 6, "BYD_CellVoltage", true);

			$this->ExtractSaveValue($byteArray, 7, 1, 1, $categoryId, "cellT_High", "Cell Temp Max", VARIABLE::TYPE_FLOAT, 7, "BYD_Temp.1"); 
			$this->ExtractSaveValue($byteArray, 9, 1, 1, $categoryId, "cellT_Low", "Cell Temp Min", VARIABLE::TYPE_FLOAT, 9, "BYD_Temp.1"); 
			$this->ExtractSaveValue($byteArray, 10, 1, 1, $categoryId, "cellT_HighNo", "Cell Temp Max No", VARIABLE::TYPE_INTEGER, 10, ""); 
			$this->ExtractSaveValue($byteArray, 11, 1, 1, $categoryId, "cellT_LowNo", "Cell Temp Min No", VARIABLE::TYPE_INTEGER, 11, ""); 
	
			//for($i=28; $i<40; $i++) {
			//	IPS_LogMessage("unknown", sprintf("%d >> %02x (%d) {%s}", $i, $byteArray[$i], $byteArray[$i], chr($byteArray[$i])));
			//}

			$this->ExtractSaveValue($byteArray, 40, 2, 10, $categoryId, "voltageBatt", "Voltage BATT", VARIABLE::TYPE_FLOAT, 40, "BYD_BattVoltage.1");
			$this->ExtractSaveValue($byteArray, 46, 2, 10, $categoryId, "voltageOut", "Voltage OUT", VARIABLE::TYPE_FLOAT, 46, "BYD_BattVoltage.1");

			$this->ExtractSaveValue($byteArray, 48, 2, 10, $categoryId, "soc", "SOC", VARIABLE::TYPE_FLOAT, 48, "BYD_Percent.1"); 
			$this->ExtractSaveValue($byteArray, 50, 2, 1, $categoryId, "soh", "SOH", VARIABLE::TYPE_FLOAT, 50, "BYD_Percent.1"); 
			$this->ExtractSaveValue($byteArray, 52, -2, 10, $categoryId, "current", "Current", VARIABLE::TYPE_FLOAT, 52, "BYD_Temp.1"); 


			//$versionX1 = sprintf("%d.%d", $byteArray[61], $byteArray[60]);
			//$versionX2 = sprintf("%d.%d", $byteArray[63], $byteArray[62]);
			//IPS_LogMessage("Version_X1", sprintf("%s",$versionX1));
			//IPS_LogMessage("Version_X2", sprintf("%s",$versionX2));
	
			//$serNr = implode(array_map("chr", array_slice($byteArray,66,19)));
			//IPS_LogMessage("SerialNo", sprintf("%s",  $serNr));

			$categoryIdCellVoltages = $this->GetCategoryID("cellVoltages", "Cell Voltages", $categoryId);
			$arrLen = count($byteArray);
			$cellCnt = 0;
			for($i=96; $i<$arrLen; $i = $i+2) {
				$cellCnt++;
				$this->ExtractSaveValue($byteArray, $i, 2, 1, $categoryIdCellVoltages, "cellV_".$cellCnt, "Cell Voltage No. " . $cellCnt, VARIABLE::TYPE_INTEGER, $cellCnt, "BYD_CellVoltage"); 
			}
	
			if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, "Data received/extracted/saved", 0); }
		}

		protected function ExtractDiagnosis_Part2($byteArray) {
			if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf("Byte Array Count: %d", count($byteArray)), 0); }

			$categoryId = $this->GetCategoryID("Diagnosis", "Diagnosis", $this->parentRootId);
			$categoryIdCellVoltages = $this->GetCategoryID("cellVoltages", "Cell Voltages", $categoryId);
			$arrLen = count($byteArray);
			$cellCnt = 16;
			for($i=0; $i<$arrLen; $i=$i+2) {
				$cellCnt++;
				$this->ExtractSaveValue($byteArray, $i, 2, 1, $categoryIdCellVoltages, "cellV_".$cellCnt, "Cell Voltage No. " . $cellCnt, VARIABLE::TYPE_INTEGER, $cellCnt, "BYD_CellVoltage"); 
			}
			if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, "Data received/extracted/saved", 0); }
		}

		protected function ExtractDiagnosis_Part3($byteArray) {
			if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf("Byte Array Count: %d", count($byteArray)), 0); }

			$categoryId = $this->GetCategoryID("Diagnosis", "Diagnosis", $this->parentRootId);
			$categoryIdCellVoltages = $this->GetCategoryID("cellVoltages", "Cell Voltages", $categoryId);
			$arrLen = count($byteArray);
			$cellCnt = 80;
			for($i=0; $i<96; $i=$i+2) {
				$cellCnt++;
				$this->ExtractSaveValue($byteArray, $i, 2, 1, $categoryIdCellVoltages, "cellV_".$cellCnt, "Cell Voltage No. " . $cellCnt, VARIABLE::TYPE_INTEGER, $cellCnt, "BYD_CellVoltage"); 
			}
			
			$categoryIdCellTemperatures = $this->GetCategoryID("cellTemperatures", "Cell Temperatures", $categoryId);
			$arrLen = count($byteArray);
			$cellCnt = 0;
			for($i=98; $i<$arrLen; $i++) {
				$cellCnt++;
				$this->ExtractSaveValue($byteArray, $i, 1, 1, $categoryIdCellTemperatures, "cellT_".$cellCnt, "Cell Temp No. " . $cellCnt, VARIABLE::TYPE_INTEGER, $cellCnt, "BYD_Temp"); 
			}
			if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, "Data received/extracted/saved", 0); }
		}

		protected function ExtractDiagnosis_Part4($byteArray) {
			if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf("Byte Array Count: %d", count($byteArray)), 0); }

			$categoryId = $this->GetCategoryID("Diagnosis", "Diagnosis", $this->parentRootId);
			$categoryIdCellTemperatures = $this->GetCategoryID("cellTemperatures", "Cell Temperatures", $categoryId);
			$arrLen = count($byteArray);
			$cellCnt = 30;
			for($i=0; $i<34; $i++) {
				$cellCnt++;
				$this->ExtractSaveValue($byteArray, $i, 1, 1, $categoryIdCellTemperatures, "cellT_".$cellCnt, "Cell Temp No. " . $cellCnt, VARIABLE::TYPE_INTEGER, $cellCnt, "BYD_Temp"); 
			}
			if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, "Data received/extracted/saved", 0); }
		}

		protected function GetCategoryID($identName, $categoryName, $parentId) {

			$categoryId = @IPS_GetObjectIDByIdent($identName, $parentId);
			if ($categoryId == false) {
				$categoryId = IPS_CreateCategory();
				IPS_SetParent($categoryId, $parentId);
				IPS_SetIdent($categoryId, $identName);
				IPS_SetName($categoryId, $categoryName);
			}  
			return $categoryId;

		}


		protected function ExtractSaveValue($byteArray, $firstByte, $byteCount, $divider=1, $parentId, $varIdent, $varName, $varType=3, $position=0, $varProfile="") {
			
			if(abs($byteCount) == 2) {
				$byte1 = ($byteArray[$firstByte]) & 255;
				$byte2 = ($byteArray[$firstByte + 1]) & 255;
				$rawValue = (($byte1<<8) + $byte2 );

				if($byteCount < 0) {
					//handle negative values
					$rawValue = $rawValue & 0xFFFF;
					if (0x8000 & $rawValue) { $rawValue = - (0xFFFF - $rawValue + 1); }
				}

				$value = $rawValue / $divider;
				if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf("extract Bytes 0x%02X 0x%02X @Position %d [RawValue = %s | %b] > Set Value '%s' to Ident '%s'", $byte1, $byte2, $firstByte, $rawValue, $rawValue, $value, $varIdent), 0); }
			} else {
				$rawValue = $byteArray[$firstByte];
				$value = $rawValue / $divider;
				if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf("extract Byte 0x%02X @Position %d [RawValue = %s] > Set Value '%s' to Ident '%s'", $rawValue, $firstByte, $rawValue, $rawValue, $value, $varIdent), 0); }
			}
			
			$this->SaveVariableValue($value, $parentId, $varIdent, $varName, $varType, $position, $varProfile);
			return $value;
		}


		protected function SaveVariableValue($value, $parentId, $varIdent, $varName, $varType=3, $position=0, $varProfile="", $asMaxValue=false) {
			
			$varId = @IPS_GetObjectIDByIdent($varIdent, $parentId);
            if($varId === false) {

                if($this->logLevel >= LogLevel::TRACE) { $this->AddLog(__FUNCTION__, 
                    sprintf("Create IPS-Variable :: Type: %d | Ident: %s | Profile: %s | Name: %s", $varType, $varIdent, $varProfile, $varName), 0); }	

                $varId = IPS_CreateVariable($varType);
                IPS_SetParent($varId, $parentId);
                IPS_SetIdent($varId, $varIdent);
                IPS_SetName($varId, $varName);
				IPS_SetPosition($varId, $position);
                IPS_SetVariableCustomProfile($varId, $varProfile);
				AC_SetLoggingStatus ($this->archivInstanzID, $varId, true);
				IPS_ApplyChanges($this->archivInstanzID);

            }			
			
			if($asMaxValue) {
				$valueTemp = GetValue($varId); 
				if($value > $valueTemp) {
					SetValue($varId, $value); 	
				}

			} else {
				SetValue($varId, $value);  
			}
			return $value;
		}


		protected function ExtractUnsignedValue($byteArray, $first_byte, $varIdent, $divider=1) {
			$byte1 = ($byteArray[$first_byte]) & 255;
			$byte2 = ($byteArray[$first_byte + 1]) & 255;
			$rawValue = (($byte1<<8) + $byte2 );

			$value = $rawValue / $divider;
			if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf("extract Bytes 0x%02X 0x%02X @Position %d [RawValue = %s | %b] > Set Value '%s' to Ident '%s'", $byte1, $byte2, $first_byte, $rawValue, $rawValue, $value, $varIdent), 0); }
			SetValue($this->GetIDForIdent($varIdent), $value);  
			return $value;
		}

		protected function ExtractSignedValue($byteArray, $first_byte, $varIdent, $divider=1) {
			$byte1 = ($byteArray[$first_byte]) & 255;
			$byte2 = ($byteArray[$first_byte + 1]) & 255;
			$rawValue = (($byte1<<8) + $byte2 );

			//handle negative values
			$rawValue = $rawValue & 0xFFFF;
			if (0x8000 & $rawValue) { $rawValue = - (0xFFFF - $rawValue + 1); }

			$value = $rawValue / $divider;
			if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf("extract Bytes 0x%02X 0x%02X @Position %d [RawValue = %s | %b] > Set Value '%s' to Ident '%s'", $byte1, $byte2, $first_byte, $rawValue, $rawValue, $value, $varIdent), 0); }
			SetValue($this->GetIDForIdent($varIdent), $value);  
			return $value;
		}


		public function ResetCalculationsAndCounter(string $source) {

			if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, "RESET Calculation and Counter Values", 0); }

			SetValue($this->GetIDForIdent("connectionState"), 0); 

			SetValue($this->GetIDForIdent("voltageDiffCalculated"), 0); 
			SetValue($this->GetIDForIdent("powerLossCalculated"), 0); 
			SetValue($this->GetIDForIdent("cellVoltageDiff"), 0); 
			SetValue($this->GetIDForIdent("cellVoltageDiffMaxToday"), 0); 
			SetValue($this->GetIDForIdent("cellVoltageDiffMaxOverall"), 0); 

			SetValue($this->GetIDForIdent("requestCnt"), 0); 
			SetValue($this->GetIDForIdent("receiveCnt"), 0); 
			SetValue($this->GetIDForIdent("crcErrorCnt"), 0);
			SetValue($this->GetIDForIdent("instanzInactivCnt"), 0);
			
			SetValue($this->GetIDForIdent("beConnectAppUpdateCnt"), 0);
			SetValue($this->GetIDForIdent("beConnectPlusUpdateCnt"), 0);
			SetValue($this->GetIDForIdent("beConnectPlusUpdateHelper"), 0);
			
			SetValue($this->GetIDForIdent("LastDataReceived"), 0); 
		}

		public function DeleteLoggedData(string $source) {

			$enable_DeleteLoggedData = false;
			if($enable_DeleteLoggedData) {
				if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, '  ..:: DELETE LOGGED DATA :: ..', 0); }
				$timerIntervalTemp = $this->GetTimerInterval("TimerAutoUpdateBYD");
				$this->SetTimerInterval("TimerAutoUpdateBYD", 0);
				if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, 'STOP "TimerAutoUpdateBYD" !', 0); }

				if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf("InstanceID: %s", $this->InstanceID), 0); }

				$archiveControlID = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}')[0]; 
				if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf("Archiv Conrol ID: %s", $archiveControlID), 0); }

				$childrenIDs = IPS_GetChildrenIDs($this->InstanceID);
					foreach($childrenIDs as $childID) {
						if (IPS_GetObject($childID)["ObjectType"] == 2) {
						$loggingStatus = AC_GetLoggingStatus($archiveControlID, $childID);
						if($loggingStatus) {
							if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf('Logging Status for Variable "[%s] %s" is TRUE', $childID, IPS_GetName($childID)), 0); }
							$result = AC_DeleteVariableData($archiveControlID, $childID, 0, time());
							if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, sprintf('%d Logged Values deleted for Variable "[%s] %s"', $result, $childID, IPS_GetName($childID)), 0); }
							$result = AC_ReAggregateVariable($archiveControlID, $childID);
							if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, sprintf('Start Reaggregation for Variable "[%s] %s" [result: %b]', $childID, IPS_GetName($childID), $result), 0); }
							IPS_Sleep(150);
						} else {
							if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf('Logging Status for Variable "[%s] %s" is FALSE', $childID, IPS_GetName($childID)), 0); }
						}
					} else {
						if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf('Object "[%s] %s" is no Variable', $childID, IPS_GetName($childID)), 0); }	
					}
				}
				if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, sprintf('Restore Timer Interval for "TimerAutoUpdateBYD" to %d ms', $timerIntervalTemp), 0); }
				$this->SetTimerInterval("TimerAutoUpdateBYD", $timerIntervalTemp);
				if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, '  - - - :: LOGGED DATA DELETED :: - - - ', 0); }
			} else {
				if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, '  - - - :: LOGGED DATA NOT DELETED > function disabled ! ', 0); }
			}
		}	


		protected function startsWith($haystack, $needle) {
			return strpos($haystack, $needle) === 0;
		}

		protected function String2Hex($string) {
			$hex='';
			for ($i=0; $i < strlen($string); $i++){
				//$hex .= dechex(ord($string[$i]));
				$hex .= sprintf("%02X", ord($string[$i])) . " ";
				//$hex .= "0x" . sprintf("%02X", ord($string[$i])) . " ";
			}
			return trim($hex);
		}

		protected function ByteArr2HexStr($arr) {
			$hex_str = "";
			foreach ($arr as $byte) {
				$hex_str .= sprintf("%02X ", $byte);
			}
			return $hex_str;
		}

		protected function RegisterProfiles() {


            if ( !IPS_VariableProfileExists('BYD_ConnectionState') ) {
                IPS_CreateVariableProfile('BYD_ConnectionState', 1 );
				IPS_SetVariableProfileText('BYD_ConnectionState', "", "" );
				IPS_SetVariableProfileAssociation ('BYD_ConnectionState', 100, "[%d] Unknown", "", -1);
				IPS_SetVariableProfileAssociation ('BYD_ConnectionState', 101, "[%d] wird erstellt", "", -1);
				IPS_SetVariableProfileAssociation ('BYD_ConnectionState', 102, "[%d] aktiv", "", -1);
				IPS_SetVariableProfileAssociation ('BYD_ConnectionState', 103, "[%d] wird gelöscht", "", -1);
				IPS_SetVariableProfileAssociation ('BYD_ConnectionState', 104, "[%d] inaktiv", "", -1);
				IPS_SetVariableProfileAssociation ('BYD_ConnectionState', 105, "[%d] wurde nicht erstellt", "", -1);
				IPS_SetVariableProfileAssociation ('BYD_ConnectionState', 106, "[%d] fehlerhaft", "", -1);
				IPS_SetVariableProfileAssociation ('BYD_ConnectionState', 200, "[%d] Unknown", "", -1);
            } 


            if ( !IPS_VariableProfileExists('BYD_CellVoltage') ) {
                IPS_CreateVariableProfile('BYD_CellVoltage', 1 );
				IPS_SetVariableProfileText('BYD_CellVoltage', "", " mV" );
				IPS_SetVariableProfileValues("BYD_CellVoltage", 0, 4000, 0);
			} 

            if ( !IPS_VariableProfileExists('BYD_CellVoltage.2') ) {
                IPS_CreateVariableProfile('BYD_CellVoltage.2', 2 );
                IPS_SetVariableProfileDigits('BYD_CellVoltage.2', 2 );
				IPS_SetVariableProfileText('BYD_CellVoltage.2', "", " V" );
				IPS_SetVariableProfileValues("BYD_CellVoltage.2", 0, 4, 0);
			} 

            if ( !IPS_VariableProfileExists('BYD_BattVoltage.1') ) {
                IPS_CreateVariableProfile('BYD_BattVoltage.1', 2 );
                IPS_SetVariableProfileDigits('BYD_BattVoltage.1', 1 );
				IPS_SetVariableProfileText('BYD_BattVoltage.1', "", " V" );
				IPS_SetVariableProfileValues("BYD_BattVoltage.1", 0, 500, 0);
			} 		
			

            if ( !IPS_VariableProfileExists('BYD_Current.1') ) {
                IPS_CreateVariableProfile('BYD_Current.1', 2 );
                IPS_SetVariableProfileDigits('BYD_Current.1', 1 );
				IPS_SetVariableProfileText('BYD_Current.1', "", " A" );
				IPS_SetVariableProfileValues("BYD_Current.1", 0, 25, 0);
			} 		

            if ( !IPS_VariableProfileExists('BYD_Watt.1') ) {
                IPS_CreateVariableProfile('BYD_Watt.1', 2 );
                IPS_SetVariableProfileDigits('BYD_Watt.1', 1 );
				IPS_SetVariableProfileText('BYD_Watt.1', "", " W" );
				IPS_SetVariableProfileValues("BYD_Watt.1", 0, 100, 0);
			} 	

			
            if ( !IPS_VariableProfileExists('BYD_Temp') ) {
                IPS_CreateVariableProfile('BYD_Temp', 1 );
                //IPS_SetVariableProfileDigits('BYD_Temp', 1 );
				IPS_SetVariableProfileText('BYD_Temp', "", " °C" );
				IPS_SetVariableProfileValues("BYD_Temp", 0, 40, 0);
			} 	

            if ( !IPS_VariableProfileExists('BYD_Temp.1') ) {
                IPS_CreateVariableProfile('BYD_Temp.1', 2 );
                IPS_SetVariableProfileDigits('BYD_Temp.1', 1 );
				IPS_SetVariableProfileText('BYD_Temp.1', "", " °C" );
				IPS_SetVariableProfileValues("BYD_Temp.1", 0, 40, 0);
			} 			

						
            if ( !IPS_VariableProfileExists('BYD_Percent') ) {
                IPS_CreateVariableProfile('BYD_Percent', 1 );
				IPS_SetVariableProfileText('BYD_Percent', "", " %" );
				IPS_SetVariableProfileValues("BYD_Percent", 0, 100, 0);
			} 	

            if ( !IPS_VariableProfileExists('BYD_Percent.1') ) {
                IPS_CreateVariableProfile('BYD_Percent.1', 2 );
             	IPS_SetVariableProfileDigits('BYD_Percent.1', 1 );				
				IPS_SetVariableProfileText('BYD_Percent.1', "", " %" );
				IPS_SetVariableProfileValues("BYD_Percent.1", 0, 100, 0);
			} 			

			if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, "Variable Profiles registered", 0); }
		}

		protected function RegisterVariables() {

			$scriptContent = '<? $varId=$_IPS["VARIABLE"]; SetValue($varId, $_IPS["VALUE"]); BYD_CloseConnection(IPS_GetParent($varId)); ?>';

			$scriptId = $this->RegisterScript("aktionsskriptOnOff", "Aktionsskript On/Off", $scriptContent, 999);
			IPS_SetParent($scriptId, $this->InstanceID);
			IPS_SetHidden($scriptId, true);
			IPS_SetDisabled($scriptId, true);


			$varId = $this->RegisterVariableBoolean("masterOnOff", "MASTER ON / OFF", "~Switch", 100);
			IPS_SetVariableCustomAction($varId, $scriptId);

			$varId = $this->RegisterVariableInteger("connectionState", "Connection STATE", "BYD_ConnectionState", 110);
			AC_SetLoggingStatus ($this->archivInstanzID, $varId, true);


			$this->RegisterVariableString("serial_no", "SerialNo", "", 200);
			
			$varId = $this->RegisterVariableString("bms", "BMS", "", 201);
			AC_SetLoggingStatus ($this->archivInstanzID, $varId, true);
			
			$varId = $this->RegisterVariableString("bmu_a", "BMU-A", "", 202);
			AC_SetLoggingStatus ($this->archivInstanzID, $varId, true);
			
			$varId = $this->RegisterVariableString("bmu_b", "BMU-B", "", 203);
			AC_SetLoggingStatus ($this->archivInstanzID, $varId, true);
			
			$varId = $this->RegisterVariableInteger("SOC", "SOC", "BYD_Percent", 210);
			AC_SetLoggingStatus ($this->archivInstanzID, $varId, true);

			$varId = $this->RegisterVariableFloat("cellV_Low", "Cell Voltage Min", "BYD_CellVoltage.2", 211);
			AC_SetLoggingStatus ($this->archivInstanzID, $varId, true);

			$varId = $this->RegisterVariableFloat("cellV_High", "Cell Voltage Max", "BYD_CellVoltage.2", 212);
			AC_SetLoggingStatus ($this->archivInstanzID, $varId, true);

			$varId = $this->RegisterVariableFloat("cellT_Low", "Cell Temp Min", "BYD_Temp.1", 213);
			AC_SetLoggingStatus ($this->archivInstanzID, $varId, true);

			$varId = $this->RegisterVariableFloat("cellT_High", "Cell Temp Max", "BYD_Temp.1", 214);
			AC_SetLoggingStatus ($this->archivInstanzID, $varId, true);			

			$varId = $this->RegisterVariableInteger("SOH", "SOH", "BYD_Percent", 215);
			AC_SetLoggingStatus ($this->archivInstanzID, $varId, true);

			$varId = $this->RegisterVariableFloat("current", "Current", "BYD_Current.1", 216);
			AC_SetLoggingStatus ($this->archivInstanzID, $varId, true);

			$varId = $this->RegisterVariableFloat("voltageBatt", "Voltage BATT", "BYD_BattVoltage.1", 217);
			AC_SetLoggingStatus ($this->archivInstanzID, $varId, true);			

			$varId = $this->RegisterVariableFloat("voltageOut", "Voltage OUT", "BYD_BattVoltage.1", 218);
			AC_SetLoggingStatus ($this->archivInstanzID, $varId, true);	

			$varId = $this->RegisterVariableFloat("voltageDiffCalculated", "CALC :.: Voltage Difference [Vbatt - Vout]", "BYD_BattVoltage.1", 250);
			AC_SetLoggingStatus ($this->archivInstanzID, $varId, true);	
		
			$varId = $this->RegisterVariableFloat("powerLossCalculated", "CALC :.: Power loss [(Vbatt-Vout)*Current]", "BYD_Watt.1", 251);
			AC_SetLoggingStatus ($this->archivInstanzID, $varId, true);	

			$varId = $this->RegisterVariableFloat("cellVoltageDiff", " :: Cell Voltage Diff", "BYD_CellVoltage.2", 260);
			AC_SetLoggingStatus ($this->archivInstanzID, $varId, true);	

			$varId = $this->RegisterVariableFloat("cellVoltageDiffMaxToday", " :: Cell Voltage Diff MAX Today", "BYD_CellVoltage.2", 261);
			AC_SetLoggingStatus ($this->archivInstanzID, $varId, true);	

			$varId = $this->RegisterVariableFloat("cellVoltageDiffMaxOverall", " :: Cell Voltage Diff MAX Overall", "BYD_CellVoltage.2", 262);
			AC_SetLoggingStatus ($this->archivInstanzID, $varId, true);	

			$this->RegisterVariableInteger("requestCnt", "Request Cnt", "", 900);
			$this->RegisterVariableInteger("receiveCnt", "Receive Cnt", "", 910);
			$this->RegisterVariableInteger("crcErrorCnt", "CRC Error Cnt", "", 920);
			$this->RegisterVariableInteger("instanzInactivCnt", "Instanz Inactiv Cnt", "", 930);
			$this->RegisterVariableInteger("beConnectAppUpdateCnt", "BeConnect App Update Cnt", "", 941);
			$this->RegisterVariableInteger("beConnectPlusUpdateCnt", "BeConnect Plus Update Cnt", "", 941);
			$this->RegisterVariableInteger("beConnectPlusUpdateHelper", "BeConnect Plus Update Helper", "", 942);
	  		$this->RegisterVariableInteger("LastDataReceived", "Last Data Received", "~UnixTimestamp", 950);

			IPS_ApplyChanges($this->archivInstanzID);

			if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, "Variables registered", 0); }

		}


		protected function CreateBattVoltageCurrentChart($parentID, $position) {

			$chart = [
				"datasets" => [
					 [
						 "variableID"=> $this->GetIDForIdent("voltageBatt"),
						 "fillColor"=> "clear",
						 "strokeColor"=> "#3023e1",
						 "timeOffset"=> 0,
						 "axis"=> 0
					 ], [
						 "variableID"=> $this->GetIDForIdent("voltageOut"),
						 "fillColor"=> "clear",
						 "strokeColor"=> "#e9e932",
						 "timeOffset"=> 0,
						 "axis"=> 0
					 ], [
						"variableID"=> $this->GetIDForIdent("current"),
						"fillColor"=> "clear",
						"strokeColor"=> "#e30d0d",
						"timeOffset"=> 0,
						"axis"=> 1
					]
				],
			   "type"=>"line",
			   "axes"  => [
					 [
						 "profile" => "BYD_BattVoltage.1",
						 "side" => "left"
					 ], [
						"profile" => "BYD_Current.1",
						"side" => "right"
					]
				 ]
			];
			$chartJSON = json_encode($chart);
			$mediaID = $this->CreateMediaChart("BATT Voltage/Current Chart", $chartJSON, $position, $parentID);
			if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, "MediaChart 'BATT Voltage/Current Chart' created", 0); }
		}


		protected function CreateCellVoltageChart($parentID, $position) {

			$chart = [
				"datasets" => [
					 [
						 "variableID"=> $this->GetIDForIdent("cellV_Low"),
						 "fillColor"=> "clear",
						 "strokeColor"=> "#3023e1",
						 "timeOffset"=> 0,
						 "axis"=> 0
					 ], [
						 "variableID"=> $this->GetIDForIdent("cellV_High"),
						 "fillColor"=> "clear",
						 "strokeColor"=> "#e30d0d",
						 "timeOffset"=> 0,
						 "axis"=> 0
					 ]
				],
			   "type"=>"line",
			   "axes"  => [
					 [
						 "profile" => "BYD_CellVoltage.2",
						 "side" => "left"
					 ]
				 ]
			];
			$chartJSON = json_encode($chart);
			$mediaID = $this->CreateMediaChart("Cell Voltage Chart", $chartJSON, $position, $parentID);
			if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, "MediaChart 'Cell Voltage Chart' created", 0); }
		}


		protected function CreateCellTempChart($parentID, $position) {

			$chart = [
				"datasets" => [
					 [
						 "variableID"=> $this->GetIDForIdent("cellT_Low"),
						 "fillColor"=> "clear",
						 "strokeColor"=> "#3023e1",
						 "timeOffset"=> 0,
						 "axis"=> 0
					 ], [
						 "variableID"=> $this->GetIDForIdent("cellT_High"),
						 "fillColor"=> "clear",
						 "strokeColor"=> "#e30d0d",
						 "timeOffset"=> 0,
						 "axis"=> 0
					 ]
				],
			   "type"=>"line",
			   "axes"  => [
					 [
						 "profile" => "BYD_Temp.1",
						 "side" => "left"
					 ]
				 ]
			];
			$chartJSON = json_encode($chart);
			$mediaID = $this->CreateMediaChart("Cell Temperature Chart", $chartJSON, $position, $parentID);
			if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, "MediaChart 'Cell Temperature Chart' created", 0); }
		}


		protected function CreateMediaChart($chartName, $chartJSON, $position, $parentID) {
			$mediaID = @IPS_GetObjectIDByName($chartName, $parentID); 
			if ($mediaID === false){ 
				$mediaID = IPS_CreateMedia(4); 
				IPS_SetParent($mediaID, $parentID);
				$media = IPS_GetKernelDir().join(DIRECTORY_SEPARATOR, array("media", "".$mediaID.".chart")); 
				IPS_SetPosition($mediaID, $position); 
				IPS_SetMediaCached($mediaID, false); 
				IPS_SetName($mediaID, $chartName); 
				IPS_SetIcon($mediaID,"Graph");

				IPS_SetMediaFile($mediaID, $media, false); 
				IPS_SetMediaContent($mediaID, base64_encode($chartJSON)); 
				IPS_SendMediaEvent($mediaID); 				
			}
			return $mediaID;
		}

		protected function AddLog($name, $daten, $format) {
			$this->SendDebug("[" . __CLASS__ . "] - " . $name, $daten, $format); 	
	
			if($this->enableIPSLogOutput) {
				if($format == 0) {
					IPS_LogMessage("[" . __CLASS__ . "] - " . $name, $daten);	
				} else {
					IPS_LogMessage("[" . __CLASS__ . "] - " . $name, $this->String2Hex($daten));			
				}
			}
		}


	}