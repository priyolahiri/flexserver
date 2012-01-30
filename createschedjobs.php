<?php
include (__DIR__.'/config.php');
//script starts
$m = new Mongo($mongoserver);
$mdb = $m->flexmailer;
$jobs = $mdb->jobs;
$campaigns = $mdb->campaign;
$lists = $mdb->maillist;
$current = date('d-m-Y H:i');
$findjob = $jobs->findOne(array("status" => "processing", "schedule" => "later", 'scheduletime' => array('$lt' => $current)));
if (!$findjob) {
	echo (date('d-m-YY H:i'). " - Cron Ran - No Jobs To Process \n");
	exit;	
}
$updatejob = $jobs->update(array('_id' => $findjob['_id']), array('$set' =>array("status" => "adding to queue")));
$campaignname = $findjob['campaignname'];
$listname = $findjob['listname'];
$thiscamp = $campaigns->findOne(array('_id' => $campaignname));
$thislist = $lists->findOne(array('_id' => $listname));
if (!$thiscamp and !$thislist) {
	echo (date('d-m-YY H:i') . " - Cron Ran - Campaign or List Not Found \n");
	exit;
}
$client = new GearmanClient();
$client->addServers($gearserver);
$html = $thiscamp['html'];
$text = $thiscamp['text'];
$sender = $thiscamp['sender'];
$subject = $thiscamp['subject'];
$c=0;
foreach ($thislist['candidates'] as $index => $candidate) {
	$c++;
	$fieldsearch = array();
	$fieldreplace = array();
	foreach($thislist['fields'] as  $index2 => $thisfield) {
		array_push($fieldsearch, "##".$thisfield."##");
		array_push($fieldreplace, $candidate[$thisfield]);
		if ($thisfield == 'email' or $thisfield == 'e_mail' or $thisfield == 'e-mail') {
			$dest = $candidate[$thisfield];
		}
	}
	$msghtml = str_replace($fieldsearch, $fieldreplace, $html);
	$msgtext = str_replace($fieldsearch, $fieldreplace, $text);
	$unsubhtml = "<a href='".$server."/unsub/".$listname.'/'.$index."'>Unsubscribe</a>";
	$unsubtext = "\n Unsubscribe at: ".$server."/unsub/".$listname.'/'.$index;
	$msghtml = str_replace('##unsub##', $unsubhtml, $msghtml);
	$msgtext = str_replace('##unsub##', $unsubtext, $msgtext);
	$job = json_encode(array(
		"dest" => $dest,
		"sender" => $sender,
		"campaign" => $campaignname,
		"html" => $msghtml,
		"text" => $msgtext,
		"subject" => $subject
	));
	$client->doBackground('mailsend', $job);
}
$updatejob2 = $jobs->update(array('_id' => $findjob['_id']), array('$set' =>array("status" => "mailing")));
echo (date('d-m-YY H:i') . " - Cron Ran - "."$c mails processed  \n");
