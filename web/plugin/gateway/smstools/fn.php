<?php
if(!(defined('_SECURE_'))){die('Intruder alert');};

function smstools_hook_getsmsstatus($gpid=0,$uid="",$smslog_id="",$p_datetime="",$p_update="") {
	global $smstools_param;
	// p_status :
	// 0 = pending
	// 1 = sent/delivered
	// 2 = failed
	if ($gpid) {
		$fn = $smstools_param['path']."/sent/out.$gpid.$uid.$smslog_id";
		$efn = $smstools_param['path']."/failed/out.$gpid.$uid.$smslog_id";
	} else {
		$fn = $smstools_param['path']."/sent/out.0.$uid.$smslog_id";
		$efn = $smstools_param['path']."/failed/out.0.$uid.$smslog_id";
	}
	$p_datetime_stamp = strtotime($p_datetime);
	$p_update_stamp = strtotime($p_update);
	$p_delay = floor(($p_update_stamp - $p_datetime_stamp)/86400);
	// set pending first
	$p_status = 0;
	setsmsdeliverystatus($smslog_id,$uid,$p_status);
	// set failed if its at least 2 days old
	if ($p_delay >= 2) {
		$p_status = 2;
		setsmsdeliverystatus($smslog_id,$uid,$p_status);
	}
	// set if its sent/delivered
	if (file_exists($fn)) {
		$p_status = 1;
		setsmsdeliverystatus($smslog_id,$uid,$p_status);
	}
	// set if its failed
	if (file_exists($efn)) {
		$p_status = 2;
		setsmsdeliverystatus($smslog_id,$uid,$p_status);
	}
	@unlink ($fn);
	@unlink ($efn);
	return;
}

function smstools_hook_playsmsd() {
	// nothing
}

function smstools_hook_getsmsinbox() {
	global $smstools_param;
	$handle = @opendir($smstools_param['path']."/incoming");
	while ($sms_in_file = @readdir($handle)) {
		$fn = $smstools_param['path']."/incoming/$sms_in_file";
		logger_print("infile:".$fn, 3, "smstools incoming");
		$tobe_deleted = $fn;
		$lines = @file ($fn);
		$start = 0;
		for ($c=0;$c<count($lines);$c++) {
			$c_line = $lines[$c];
			if (ereg('^From: ',$c_line)) {
				$sms_sender = '+'.trim(str_replace('From: ','',trim($c_line)));
			} else if (ereg('^Received: ',$c_line)) {
				$sms_datetime = '20'.trim(str_replace('Received: ','',trim($c_line)));
			} else if ($c_line == "\n") {
				$start = $c + 1;
				break;
			}
		}
		@unlink($tobe_deleted);
		// continue process only when incoming sms file can be deleted
		if (! file_exists($tobe_deleted)) {
			if ($sms_sender && $sms_datetime && $start) {
				$message = "";
				for ($lc=$start;$lc<count($lines);$lc++) {
					$message .= trim($lines[$lc]);
				}
				// collected:
				// $sms_datetime, $sms_sender, $message, $sms_receiver
				setsmsincomingaction($sms_datetime,$sms_sender,$message,$sms_receiver);
			}
			logger_print("sender:".$sms_sender." receiver:".$sms_receiver." dt:".$sms_datetime." msg:".$message, 3, "smstools incoming");
		}
	}
}

function smstools_hook_sendsms($sms_sender,$sms_footer,$sms_to,$sms_msg,$uid='',$gpid=0,$smslog_id=0,$sms_type='text',$unicode=0) {
	global $smstools_param;
	$sms_id = "$gpid.$uid.$smslog_id";
	if (empty($sms_id)) {
		$sms_id = mktime();
	}
	if ($sms_footer) {
		$sms_msg = $sms_msg.$sms_footer;
	}
	$the_msg = "From: $sms_sender\n";
	$the_msg .= "To: $sms_to\n";
	$the_msg .= "Report: yes\n";
	if ($msg_type=="flash") {
		$the_msg .= "Flash: yes\n";
	}
	if ($unicode) {
		if (function_exists('mb_convert_encoding')) {
			$the_msg .= "Alphabet: UCS\n";
			$sms_msg = mb_convert_encoding($sms_msg, "UCS-2BE", "auto");
		}
		// $sms_msg = str2hex($sms_msg);
	}
	$the_msg .= "\n$sms_msg";
	$fn = $smstools_param['path']."/outgoing/out.$sms_id";
	logger_print("outfile:".$fn, 3, "smstools outgoing");
	umask(0);
	$fd = @fopen($fn, "w+");
	@fputs($fd, $the_msg);
	@fclose($fd);
	$ok = false;
	if (file_exists($fn)) {
		$ok = true;
	}
	return $ok;
}

?>