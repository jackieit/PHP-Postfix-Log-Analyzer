<?php
/**
 * PHP Postfix log analyzer main file
 *
 * @package default
 * @author  jackie
 **/
define('ROOT',dirname(__FILE__).DIRECTORY_SEPARATOR);
//日志文件路径
$logFile  = ROOT.'maillog';
//记忆上次读取日志文件的位置
$remFile  = ROOT.'remember.txt';
//锁文件，如果有进行在处理日志了则退出
$lockFile = ROOT.'write.lock';
if(is_file($lockFile)){
	exit("Another process is Working...,Exit.");
}
if(!touch($lockFile)){
	exit("Permission denied to create lock file!");
}
$fileSize = filesize($logFile);
//获取上次记忆日志文件大小与读取位置
$lastSize = 0;
$lastPos  = 0;
if(is_file($remFile) && filesize($remFile) >0){
	$hd = fopen($remFile,'r+');
	$tmp = fgets($hd,128);
	//$tmp = file_get_contents($remFile);
	$tmp_arr  = explode(',',$tmp);
	$lastSize = (int)$tmp_arr[0];
	$lastPos  = (int)$tmp_arr[1];
	fseek($hd,0);
}else{
	touch($remFile);
	$hd = fopen($remFile,'r+');
}
if($lastSize <$fileSize){
   $lastPos = 0;
}

$hdLog = new SplFileObject($logFile);
if($lastPos >0)
	$hdLog->seek($lastPos);
//结果数组 格式为 array('msgID'=>array('email'=>'status'));
$ret = array();

while(!$hdLog->eof()){
	$content = $hdLog->current();
	//echo strpos($content,'connect from localhost[::1]');
	if(($pos = strpos($content,': message-id=<'))!==false){
		//邮件发送标识符，邮件发送队列中唯一
		$ID  = substr($content,$pos-10,10);
		$pos    += 14; 
		$pos_end = strpos($content,'>',$pos);
		$msgId     = substr($content,$pos,($pos_end-$pos));
		//只找批量发送队列中的邮件
		if(strpos($msgId,'vip.vlongbiz.com')!==false){
			$hdLog->next();
			//$readLines++;
			continue;			
		}
 
		$curLine = $hdLog->key();
 
		$nextPos = $curLine+1;
		//$readLines++;
		$msgMail = array();
		do{
		    $hdLog->seek($nextPos);
			$content = $hdLog->current();
			//echo $content;
			$nextPos++;
			//根据MessageID找出第一个收件人
			if(strpos($content,$ID.': to=<')!==false){
				preg_match('/to=<([^>]+)>,\s+.+,\s+status=(sent|deferred|bounced|deferral|reject)/isU',$content,$matches);
				$status = $matches[2]==='sent'?1:0;
				$msgMail[$matches[1]] = $status;
			}
			if(strpos($content,$ID.': removed')!==false)
			{
				$ret[$msgId] = $msgMail;
				//返回至Message ID的位置，继续找下一个MessageID
				$hdLog->seek(($curLine+1));
				continue 2;				
			}
			 
		}while(!$hdLog->eof());

	}else{
		 
		$hdLog->next();
 
		continue;
	}
}
$lastPos = $hdLog->key();
$str = $fileSize.','.$lastPos;
//print result
print_r($ret);
ftruncate($hd);
fwrite($hd,$str,strlen($str));
fclose($hd);
@unlink($lockFile);
?>