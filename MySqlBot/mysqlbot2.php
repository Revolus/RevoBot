<?php

include('../load_config.php'); // sets $curl, fail, xmlFail, doLog, <config>...
include('/home/revolus/.curl.cookie.php'); // sets $result, $data and $cookieData
include('/home/revolus/.mysql.link.php'); // sets $link

$r1 = getenv('REVO_BOT_START');
if($r1 == '') fail('Kein Startzeitpunkt');
$r2 = getenv('REVO_BOT_END');
if($r2 == '') fail('Kein End');
$logLine = sprintf("INFO Start: %s, Ende: %s\n\n", $r1, $r2);
$log .= "\n" . $logLine;
print($logLine);

// Sanitizer eigentlich vollkommen unnötig
$r1 = mysql_real_escape_string($r1, $link) or fail(mysql_error());
$r2 = mysql_real_escape_string($r2, $link) or fail(mysql_error());

function mysql_array_escape($d)
{
  global $link;
  $r = mysql_real_escape_string($d, $link) or fail(mysql_error());
  return "'$r'";
}

$sqlTemplates = '';
foreach($templateMap as $k => $v) {
  if(count($templates[$k])) {
    $sqlTemplates .= sprintf(",\n  (`tl_title` IN (%s)) AS `%s`", implode(', ', array_map('mysql_array_escape', $templates[$k])), $v);
  }
}
if($sqlTemplates == '') {
  fail('Keine Vorlagen vorhanden?');
}

$pages   = array();
$pageIds = array();
$byUser  = array();

$homeWiki = 'dewiki_p';
$sharedWiki = 'commonswiki_p';

$result = mysql_query("
SELECT /* SLOW_OK */
  `page_id`
FROM
  `page`
  LEFT JOIN `revision` ON `rev_id` = `page_latest`
WHERE `page`.`page_namespace`   = 6 -- nur Bilder
  AND `page`.`page_is_redirect` = 0 -- keine Weiterleitungen
  AND `page`.`page_touched`    >= '$r1' -- Bearbeitung => Touch; r1 & r2 sind bereits sanitized
  AND `page`.`page_latest`      = `revision`.`rev_id` -- Tautologie?
  AND `revision`.`rev_timestamp` BETWEEN '$r1' AND '$r2' -- Revisionszeitraum
LIMIT 500 -- sollte pro 3h nicht > 250 sein
", $link) or fail(mysql_error());
while($row = mysql_fetch_row($result)) {
  $stockIds[$row[0]] = $row[0];
}
mysql_free_result($result);

$result = mysql_query("
SELECT
  `page_id`, `user`
FROM
  `u_revolus`.`bestandscheck`
LIMIT 250
", $link) or fail(mysql_error());
while($row = mysql_fetch_row($result)) {
  $ignoredIds[$row[0]] = $row[0];
  $stockIds[$row[0]] = $row[0];
  if($row[1]) {
    $byUser[$row[0]] = $row[1];
  }
}
mysql_free_result($result);

if(!count($stockIds)) {
  print("\nNichts geändert\n");
  $log .= "\nNichts geändert\n";
  doLog();
  curl_close($curl);
  exit;
}

$result = mysql_query("
SELECT /* SLOW_OK */
  `page_id`, `page_title`, `page_latest`, `rev_timestamp`, `rev_user_text`, `rev_user`,
  `$homeWiki`.`image`.`img_sha1` AS `local`,
  `$sharedWiki`.`image`.`img_sha1` AS `shared`,
  (SELECT `$sharedWiki`.`image`.`img_name`
    FROM `$sharedWiki`.`image`
    WHERE `$sharedWiki`.`image`.`img_sha1` = `local`
      AND `$sharedWiki`.`image`.`img_size` = `$homeWiki`.`image`.`img_size`
    LIMIT 1
  ) AS `shared_name`
FROM -- ``revision`, `page`
  `page`
  LEFT JOIN `revision`            ON `rev_id`     = `page_latest`
  LEFT JOIN `$homeWiki`.`image`   ON `page_title` = `$homeWiki`.`image`.`img_name`
  LEFT JOIN `$sharedWiki`.`image` ON `page_title` = `$sharedWiki`.`image`.`img_name`
WHERE `page`.`page_id`         IN (" . implode(', ', $stockIds) . ")
  AND `page`.`page_is_redirect` = 0 -- keine Weiterleitungen
  AND `page`.`page_latest`      = `revision`.`rev_id`
LIMIT 1000 -- sollte pro 3h nicht > 250 sein
", $link) or fail(mysql_error());
printf("CHANGED IMAGES: %d   [local/shared/identic] <file> (page id) (old id) <shared name>\n", mysql_num_rows($result));
while($row = mysql_fetch_assoc($result)) {
  unset($ignoredIds[ $row['page_id'] ]);
  if(!$row['local'] || $row['shared']) {
    $row['shared_name'] = ''; // vereinfacht spätere Abfragen
  }
  printf("# IMAGE %s%s%s <%s> (%d) (%d) <%s>\n",
    $row['local'] ? 'L' : '_', $row['shared'] ? 'S' : '_', $row['local'] == $row['shared'] ? 'D' : '_',
    $row['page_title'], $row['page_id'], $row['page_latest'], $row['shared_name']
  );
  $pages[$row['page_id']] = $row;
  $pageIds[$row['page_title']] = $row['page_id'];
}
mysql_free_result($result);
print("\n");

if(count($ignoredIds)) {
  foreach($ignoredIds as $iid) {
    printf("IGNORED: <%s> (%d)\n", $pages[$iid]['page_title'], $iid);
  }
  print("\n");
  mysql_query('          
  DELETE FROM
    `u_revolus`.`bestandscheck`
  WHERE
    `page_id` IN (' . implode(', ', $ignoredIds) . ')
  ', $link) or fail(mysql_error());
}

$result = mysql_query("
SELECT /* SLOW_OK */
 `tl_from`, `tl_title` $sqlTemplates
FROM `templatelinks`
WHERE `tl_namespace` = 10 -- Nur Vorlage:
  AND `tl_from` IN (" . implode(', ', $pageIds) /* page_ids sind Zahlen -> nicht sanitizen */ . ")
  AND `tl_title` NOT IN ( -- häufige unnötige Vorlagen rausfiltern
    '!', '!!', '(!', '!)', -- Struktur
    'Achtung', 'Lizenzdesign1', 'Lizenzdesign2', 'Lizenzdesign3', 'Lizenzdesign4', 'Lizenzdesign5', 'Bausteindesign3', 'Bausteindesign4', -- Optik
    'JetztSVG', 'JetztAuchSVG', 'In_SVG_konvertieren', 'Bilderwerkstatt', -- unnötige Markierungen
    'IsCommons', 'ParmPart', 'Information', -- Vorlagenprogrammierung
    'OTRS', 'OTRS/Text', 'Recht_am_eigenen_Bild', 'Wappenrecht', 'Logo', 'Logo-Anmerkungen', 'Panoramafreiheit', -- Rechtliches
    'DÜP', 'Düp', 'NC', 'SVG', 'Nowcommons' -- Weiterleitungen
  )
-- LIMIT 50000 -- sollte pro Bild nicht > 5 sein
") or fail(mysql_error());
printf("USED TEMPLATES: %d   [%s] <file> (old id) <template>\n", mysql_num_rows($result), implode('/', $templateMap));
while($row = mysql_fetch_assoc($result)) {
  $has = false;
  print('# HAS ');
  foreach($templateMap as $k => $v) {
    if($row[$v]) {
      $pages[$row['tl_from']][$k] = true;
    }
    print($row[$v] ? $templateKey[$k] : '_');
  }
  printf(" <%s> (%d): <%s>\n",
    $pages[$row['tl_from']]['page_title'],
    $row['tl_from'],
    $row['tl_title']
  );
}
mysql_free_result($result);
print("\n");

foreach($templateMapFlip as $k) {
  $badOnes[$k] = array();
}
printf("IMAGE RESULTS:   [BAD|___] [%s] <file> (page id) <last editor> (user id)\n", implode('/', $typeMap));
foreach($pageIds as $pageId) {
  if($pages[$pageId]['local']) {
    if($pages[$pageId]['shared']) {
      if($pages[$pageId]['local'] == $pages[$pageId]['shared']) {
        $type = NO_DUPLICATE; // identisch
      } else {
        $type = NO_SHADOWS; // unidentisch
      }
    } elseif($pages[$pageId]['shared_name']) {
      $type = NO_NAMED_DUP; // anderer Name auf Commons
    } else {
      $type = ALLOW; // nur lokal
    }
  } elseif($pages[$pageId]['shared']) {
    $type = NO_COMMONS; // nur shared
  } else {
    $type = NO_MISSING; // nicht vorhanden
  }
  
  if(!$pages[$pageId][$type]) {
    array_push($badOnes[$type], $pageId);
    $pages[$pageId]['bad'] = $type;
  }
  printf("# IS %s %s <%s> (%d) <%s> (%d)\n",
    $pages[$pageId][$type] ? '___' : 'BAD', $typeMap[$type], $pages[$pageId]['page_title'], $pageId, $pages[$pageId]['rev_user_text'], $pages[$pageId]['rev_user']
  );
  $log .= sprintf("# IS %s %s [{{fullurl:File:%s|curid=%d&oldid=%d}} {{subst:FULLPAGENAME:File:%s}}] by [[{{subst:FULLPAGENAME:User:%s}}|%s]]%s\n",
    $pages[$pageId][$type] ? '___' : "'''BAD'''",
    $typeMap[$type],
    $pages[$pageId]['page_title'],
    $pageId,
    $pages[$pageId]['page_latest'],
    $pages[$pageId]['page_title'],
    $pages[$pageId]['rev_user_text'],
    $pages[$pageId]['rev_user_text'],
    isset($byUser[$pageId]) ? sprintf(', through [[{{subst:FULLPAGENAME:User:%s}}|%s]]', $byUser[$pageId], $byUser[$pageId]) : ''
  );
}
print("\n");
$log .= "\nStats:";

$sum = 0;
print('STATS:');
foreach($typeMap as $k => $v) {
  $sum += count($badOnes[$k]);
  $logLine = sprintf(' %d×%s', count($badOnes[$k]), $v);
  print($logLine);
  $log .= $logLine;
}

$isIgnoredCommons = array();
$isIgnoredCommonsNames = array();

foreach(array(NO_DUPLICATE, NO_SHADOWS, NO_NAMED_DUP) as $category) {
  foreach($badOnes[$category] as $v2) {
    $isIgnoredCommons[$v2] = false;
    $name = $pages[$v2]['shared_name'];
    if(!$name) {
      $name = $pages[$v2]['page_title'];
    }
    array_push($isIgnoredCommonsNames, mysql_array_escape($name, $link));
  }
}

if($isIgnoredCommons && $commonsIgnoreCategory) {
  $sql = sprintf('
      SELECT /* SLOW_OK */
        loc_page.page_id
      FROM page                        loc_page
      JOIN commonswiki_p.page          img_page ON loc_page.page_title = img_page.page_title
      JOIN commonswiki_p.templatelinks tpl_link ON img_page.page_id    = tpl_link.tl_from
      JOIN commonswiki_p.page          tpl_page ON tpl_link.tl_title   = tpl_page.page_title
      JOIN commonswiki_p.categorylinks cat_link ON tpl_page.page_id    = cat_link.cl_from
      WHERE loc_page.page_namespace = 6
      AND   img_page.page_namespace = 6
      AND   tpl_page.page_namespace = 10
      AND   cat_link.cl_to      IN (%s)
      AND   img_page.page_title IN (%s)
    ', implode(', ', array_map('mysql_array_escape', $commonsIgnoreCategory)), implode(', ', $isIgnoredCommonsNames)
  );
  $result = mysql_query($sql);
  while($row = mysql_fetch_row($result)) {
    printf('Ignore commons: ' . $row['title'] . "\n");
    $isIgnoredCommons[$row[0]] = true;
  }
}

$log .= "\n\nEDIT RESULTS:\n";
printf("\n\nEDIT RESULTS:\n");
if($sum == 0) {
  print("ALLES KORREKT :-)\n");
  $log .= "ALLES KORREKT :-)\n";
} else do {
  foreach($badOnes as $v1) {
    foreach($v1 as $v2) {
      if(!$text[-($pages[$v2]['bad']*3 + 4)]) { // ignore if summary is empty
        printf("# EDIT %s <skipped> <%s> (%d): no summary\n", $typeMap[$pages[$v2]['bad']], $pages[$v2]['page_title'], $v2);
        $log .= sprintf("# EDIT (Fall ignoriert) %s [{{fullurl:Datei:%s|curid=%d&oldid=%d}} Datei:%s]\n",
          $typeMap[$pages[$v2]['bad']], $pages[$v2]['page_title'], $pageId, $pages[$v2]['page_latest'], $pages[$v2]['page_title']
        );
      } elseif($ignored[$pages[$v2]['page_title']]) { // ignore if file is to ignore ...
        printf("# EDIT %s <skipped> <%s> (%d): image ignored\n", $typeMap[$pages[$v2]['bad']], $pages[$v2]['page_title'], $v2);
        $log .= sprintf("# EDIT (Datei ignoriert) %s [{{fullurl:Datei:%s|curid=%d&oldid=%d}} Datei:%s]\n",
          $typeMap[$pages[$v2]['bad']], $pages[$v2]['page_title'], $pageId, $pages[$v2]['page_latest'], $pages[$v2]['page_title']
        );
      } else { // mark file
        $str .= ($str ? '|' : '') . 'File:' . $pages[$v2]['page_title']; // no L10n!
      }
    }
  }
  if(!$str) {
    break; // nothing to do
  }
  curl_setopt($curl, CURLOPT_POSTFIELDS, array(
    'assert'  => 'bot',
    'action'  => 'query',
    'prop'    => 'info',
    'inprop'  => 'protection',
    'intoken' => 'edit',
    'titles'  => $str
  )) or fail('CURLOPT_POSTFIELDS');
  $result = curl_exec($curl) or fail('curl_exec()');
  $data = unserialize($result) or fail('unserialize()', $result);
  if(!isset($data['query'])) {
    fail('Query fehlgeschlagen');
  }

  function sndTry() {
    global $result, $data2, $typeMap, $pages, $pageId, $query, $log, $logLine, $curl;
    $logLine = sprintf('# EDIT %s <failed> <%s> (%d): second try', $typeMap[$pages[$pageId]['bad']], $query['title'], $pageId);
    $log .= $logLine . "\n";
    print($logLine. '  Sleeping ');
  
    for($i = 19; $i>0; --$i) {
      print((3*$i) . ' ');
      sleep(3);
    }
    print("0\n");
    $result = curl_exec($curl) or fail('curl_exec()');
    $data2 = unserialize($result) or thrdTry();
  }

  function thrdTry() {
    global $result, $data2, $typeMap, $pages, $pageId, $query, $log, $logLine, $curl;
    $logLine = sprintf('# EDIT %s <failed> <%s> (%d): THIRD try', $typeMap[$pages[$pageId]['bad']], $query['title'], $pageId);
    $log .= $logLine . "\n";
    print($logLine. '  Sleeping ');
  
    for($i = 19; $i>0; --$i) {
      print((15*$i) . ' ');
      sleep(15);
    }
    print("0\n");
    $result = curl_exec($curl) or fail('curl_exec()');
    $data2 = unserialize($result) or fail('unserialize()', $result);
  }
  
  foreach($data['query']['pages'] as $pageId => $query) {
    if(isset($pages[$pageId]['protection'])) {
      foreach($pages[$pageId]['protection'] as $protection) {
        if(($protection['type'] == 'edit') && ($protection['level'] == 'sysop')){
          printf("# EDIT %s <skipped> <%s> (%d): Edit protected\n", $typeMap[$pages[$pageId]['bad']],
            $query['title'], $pageId
          );
          $log .= sprintf("# EDIT (Seite gesperrt) %s [{{fullurl:%s|curid=%d&oldid=%d}} %s]\n", $typeMap[$pages[$pageId]['bad']],
            $query['title'], $pageId, $query['lastrevid'], $query['title']
          );
        }
      }
    }

    if($query['lastrevid'] != $pages[$pageId]['page_latest']) {
      printf("# EDIT %s <skipped> <%s> (%d): revision outdated (toolserver %d) (server %d)\n", $typeMap[$pages[$pageId]['bad']],
        $query['title'], $pageId, $pages[$pageId]['page_latest'], $query['lastrevid']
      );
      $log .= sprintf("# EDIT %s (Toolserver-Daten [{{fullurl:%s|curod=%d&oldid=%d}} veraltet]) [{{fullurl:%s|oldid=%d}} %s]\n",
        $typeMap[$pages[$pageId]['bad']], $query['title'], $query['lastrevid'],
        $query['title'], $pageId, $pages[$pageId]['page_latest'], $query['title']
      );
      continue;
    }

    if($isIgnoredCommons[$pageId]) {
      printf("# EDIT %s <ignore category> <%s> (%d)\n", $typeMap[$pages[$pageId]['bad']], $query['title'], $pageId);
      $log .= sprintf("# EDIT (Commons-Kategorie ignoriert) %s [{{fullurl:%s|oldid=%d}} %s]\n",
        $typeMap[$pages[$pageId]['bad']], $query['title'], $query['lastrevid'], $query['title']
      );
      continue;
    } 
  
    if($query['edittoken'] == '+\\') {
      fail('Anonym?', array('cookie' => $cookieData, 'query' => $data));
    }
    $post =  array(
      'assert'         => 'bot',
      'action'         => 'edit',
      'nocreate'       => 1,
      'title'          => $query['title'], // normalisiert
      'token'          => $query['edittoken'],
      'summary'        => getBotText(&$pages[$pageId], 'summary'),
      'prependtext'    => getBotText(&$pages[$pageId], 'prepend'),
      'appendtext'     => getBotText(&$pages[$pageId], 'append'),
      'basetimestamp'  => gmdate('Y-m-d\TH:i:s\Z', strtotime($pages[$pageId]['rev_timestamp'])),
      'starttimestamp' => $query['starttimestamp'],
      'bot'            => 1
    );
    if(!$post['summary']) { // should be imposible
      printf("# EDIT %s <skipped> <%s> (%d): no summary\n", $typeMap[$pages[$pageId]['bad']], $query['title'], $pageId);
      $log .= sprintf("# EDIT (Fall ignoriert) %s [{{fullurl:%s|oldid=%d}} %s]\n",
        $typeMap[$pages[$pageId]['bad']], $query['title'], $query['lastrevid'],
        $query['title']
      );
      continue;
    }
    if(!$post['prependtext']) unset($post['prependtext']);
    if(!$post[ 'appendtext']) unset($post[ 'appendtext']);
  
    curl_setopt($curl, CURLOPT_POSTFIELDS, $post) or fail('CURLOPT_POSTFIELDS');
    $result = curl_exec($curl) or fail('curl_exec()');
    $data2 = unserialize($result) or sndTry();
    if(!isset($data2) || !isset($data2['edit'])) {
      if(isset($data2['error'])) {
        printf("# EDIT %s <fail> <%s> (%d): <%s> (<%s>)\n",
          $typeMap[$pages[$pageId]['bad']],
          $query['title'], $pageId, $data2['error']['info'], $data2['error']['code']
        );
        $log .= sprintf("# EDIT (Fehlgeschlagen [%s]) %s [{{fullurl:%s|curid=%d&oldid=%d}} %s]\n",
          htmlentities($data2['error']['info'], ENT_QUOTES, 'UTF-8'), $typeMap[$pages[$pageId]['bad']],
          $query['title'], $pageId, $query['lastrevid'], $query['title']
        );
        unset($stockIds[$pageId]);
      } else {
        fail('Edit fehlgeschlagen', $result);
      }
    } else {
      printf("# EDIT %s <%s> <%s> (%d) (old %d) (new %d)  Sleeping ",
        $typeMap[$pages[$pageId]['bad']],
        $data2['edit']['result'], $data2['edit']['title'], $data2['edit']['pageid'],
        $data2['edit']['oldrevid'], $data2['edit']['newrevid']
      );
//    var_dump($data2);
//    var_dump($pages[$pageId]);
      $log .= sprintf("# EDIT (%s) %s [{{fullurl:%s|curid=%d&diff=%d&oldid=%d}} %s] by [[{{subst:FULLPAGENAME:User:%s}}|]]\n",
        $data2['edit']['result'], $typeMap[$pages[$pageId]['bad']], $data2['edit']['title'], 
        $pageId, $data2['edit']['newrevid'], $data2['edit']['oldrevid'], $data2['edit']['title'],
        $pages[$pageId]['rev_user_text'], $pages[$pageId]['rev_user_text']
      );
      for($i = 19; $i>0; --$i) {
        print($i . ' ');
        usleep(900000);
      }
      print("0\n");
    }
  }
} while(false);

if($stockIds) {
	mysql_query('
	DELETE FROM
	  `u_revolus`.`bestandscheck`
	WHERE
	  `page_id` IN (' . implode(', ', $stockIds) . ')
	', $link) or fail(mysql_error());
}

doLog();
curl_close($curl);
mysql_close($link);
