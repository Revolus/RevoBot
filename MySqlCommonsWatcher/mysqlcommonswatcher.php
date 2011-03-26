<?php

$r1 = getenv('REVO_BOT_START');
if($r1 == '') die('Kein Startzeitpunkt');
$r2 = getenv('REVO_BOT_END');
if($r2 == '') die('Kein End');

include('/home/revolus/.mysql.link.php'); // sets $link

$sql = sprintf('
	INSERT IGNORE INTO u_revolus.bestandscheck (page_id, rev_id, user)
	SELECT p.page_id, p.page_latest, "%s"
	FROM image i
	JOIN commonswiki_p.logging l ON (i.img_name = l.log_title)
	JOIN page p ON (i.img_name = p.page_title)
	JOIN commonswiki_p.user u ON (l.log_user = u.user_id)
	WHERE u.user_name = "%s"
	AND p.page_namespace = 6
	AND l.log_timestamp BETWEEN "%s" AND "%s";', "File Upload Bot (Magnus Manske)", "File Upload Bot (Magnus Manske)", $r1, $r2);

mysql_query($sql);
