<?php

$configName = 'Benutzer:Forrester/lizenzvorlagen.js';

function fail($s, $a = '')
{
  global $link, $xml, $log, $logLine;
  $raw = debug_backtrace();
  foreach($raw as $entry) {
    if($entry['function'] == 'fail') {
      $output .= "\nFile: " . $entry['file'] . " (Line: " . $entry['line'] . ")\n";
      continue;
    }
    elseif($entry['function'] == 'xmlFail') {
      continue;
    }
    $output .= "\nFile: " . $entry['file'] . " (Line: " . $entry['line'] . ")\n"; 
    $output .= "Function: " . $entry['function'] . "\n"; 
    $output .= "Args: " . var_export($entry['args'], true) . "\n"; 
  }
  $logLine = "\n\n\n\n\n\n\n\n\nOH NOES:\n$s\n";
  $log .= $logLine;
  print($logLine);
  
  @xml_parser_free($xml);
  @mysql_close($link);
  $s .= "\n\n------------\n\n". $output ."\n\n------------\n\n". var_export($a, true) ."\n\n------------\n";
  mail('revolus@toolserver.org', 'REVOBOT ACHTUNG: fail', $s);
  die();
}

function xmlFail()
{
  global $at, $xml;
  fail(xml_get_current_line_number($xml) . ':' . xml_get_current_column_number($xml) . ' Config malformed @' . $at);
}

function doLog()
{
  global $log, $curl, $cookieData, $r1, $r2;
  if($log == '') return;
  if(!isset($curl) || is_null($curl)) {
    print("\n\n\n\n\n\nWARNUNG: Log nicht geschrieben (kein \$curl)\n\n\n");
    return false;
  }
  if(!curl_setopt($curl, CURLOPT_POSTFIELDS, array(
    'assert'  => 'bot',
    'action'  => 'query',
    'prop'    => 'info|revisions',
    'inprop'  => '',
    'rvprop'  => 'timestamp',
    'intoken' => 'edit',
    'titles'  => 'Benutzer:RevoBot/log'
  ))) return printf("\n\n\n\n\n\nWARNUNG: Log nicht geschrieben (CURLOPT_POSTFIELDS beim Edittoken)\n\n\n");
  if(!($result = curl_exec($curl))) {
    printf("\n\n\n\n\n\nWARNUNG: Log nicht geschrieben (curl_exec() beim Edittoken)\n\n\n");
    return false;
  }
  if(!($data = unserialize($result))) {
    var_dump($result);
    printf("\n\n\n\n\n\nWARNUNG: Log nicht geschrieben (unserialize() beim Edittoken)\n\n\n");
    return false;
  }
  if(!isset($data['query'])) {
    var_dump($data);
    print("\n\n\n\n\n\nWARNUNG: Log nicht geschrieben (query beim Edittoken)\n\n\n");
    return false;
  }
  if(!(list(, $query) = each($data['query']['pages']))) {
    var_dump($data);
    printf("\n\n\n\n\n\nWARNUNG: Log nicht geschrieben (each bei den Pages)\n\n\n");
    return false;
  }
  if($query['edittoken'] == '+\\') {
    var_dump($data);
    printf("\n\n\n\n\n\nWARNUNG: Log nicht geschrieben (anonym?)\n\n\n");
    return false;
  }
  if(!(list(, $ts) = each($query['revisions']))) {
    var_dump($data);
    printf("\n\n\n\n\n\nWARNUNG: Log nicht geschrieben (each bei den Revisionen)\n\n\n");
    return false;
  }
  if(!($text = htmlentities(sprintf('Automatischer Log --~~~~

Letzter Log vom [{{fullurl:%s|curid=%d&oldid=%d}} {{subst:#timel:d.m.Y, H:i:s|%s}}]

== {{subst:#timel:d.m.Y, H:i:s|%s}} — {{subst:#timel:d.m.Y, H:i:s|%s}} ==

%s
== Log ende …  ==

Bei Fragen, Anregungen, Kritik und Wutanfällen: [[Benutzer Diskussion:Revolus]] --~~~~
', $query['title'], $query['pageid'], $query['lastrevid'], $ts['timestamp'], $r1, $r2, $log), ENT_NOQUOTES, 'UTF-8', false))) {
    printf("\n\n\n\n\n\nWARNUNG: Log nicht geschrieben (sprintf vom Text)\n\n\n");
    return false;
  }
  if(!curl_setopt($curl, CURLOPT_POSTFIELDS, array(
    'assert'         => 'bot',
    'action'         => 'edit',
//  'nocreate'       => 1,
    'recreate'       => 1,
    'title'          => $query['title'], // normalisiert
    'token'          => $query['edittoken'],
    'summary'        => sprintf('Bot:Schreibe Log für Zeitraum: %s — %s', $r1, $r2),
    'text'           => $text,
//  'basetimestamp'  => gmdate('Y-m-d\TH:i:s\Z', strtotime($pages[$pageId]['rev_timestamp'])),
    'starttimestamp' => $query['starttimestamp'],
    'bot'            => 1
  ))) {
    printf("\n\n\n\n\n\nWARNUNG: Log nicht geschrieben (CURLOPT_POSTFIELDS beim Speichern)\n\n\n");
    return false;
  }
  if(!($result = curl_exec($curl))) {
    printf("\n\n\n\n\n\nWARNUNG: Log nicht geschrieben (curl_exec() beim Speichern)\n\n\n");
    return false;
  }
  if(!($data = unserialize($result))) {
    var_dump($result);
    printf("\n\n\n\n\n\nWARNUNG: Log nicht geschrieben (unserialize() beim Speichern)\n\n\n");
    return false;
  }
//  var_dump($data);
  printf("\nINFO WROTE LOG <%s> <%s> (%d) (old %d) (new %d)\n",
    $data['edit']['result'], $data['edit']['title'], $data['edit']['pageid'],
    $data['edit']['oldrevid'], $data['edit']['newrevid']
  );
  $log = '';
}

ini_set('memory_limit', '512M') or fail('Memory limit');

define('IGNORE_CATEGORY',     7);
define('IGNORE',              6);

define('NO_NAMED_DUP',        5);
define('NO_DUPLICATE',        4);
define('NO_SHADOWS',          3);
define('NO_MISSING',          2);
define('NO_COMMONS',          1);
define('ALLOW',               0);

define(          'NOWHERE',  -1);

define(    'FALSE_PREPEND',  -2); define(    'FALSE_APPEND',  -3); define(    'FALSE_SUMMARY',  -4);
define(  'COMMONS_PREPEND',  -5); define(  'COMMONS_APPEND',  -6); define(  'COMMONS_SUMMARY',  -7);
define(  'MISSING_PREPEND',  -8); define(  'MISSING_APPEND',  -9); define(  'MISSING_SUMMARY', -10);
define(  'SHADOWS_PREPEND', -11); define(  'SHADOWS_APPEND', -12); define(  'SHADOWS_SUMMARY', -13);
define('DUPLICATE_PREPEND', -14); define('DUPLICATE_APPEND', -15); define('DUPLICATE_SUMMARY', -16);
define('NAMED_DUP_PREPEND', -17); define('NAMED_DUP_APPEND', -18); define('NAMED_DUP_SUMMARY', -19);

function getBotText($page, $what) {
  global $text, $typeMap, $textMap;
  switch($what) {
    case('prepend'): $offs = -2; break;
    case( 'append'): $offs = -3; break;
    case('summary'): $offs = -4; break;
    default: fail('Falsches $what');
  }
  $offs -= 3 * $page['bad'];
  if(!$text[$offs]) {
    return '';
  }
  $original = array('%id%',           '%title%',           '%revision%',         '%timestamp%',
                    '%local sha1%',   '%commons sha1%',    '%commons name%',     '%fault%', '%editor%');
  $replace =  array($page['page_id'], $page['page_title'], $page['page_latest'], $page['rev_timestamp'],
                    $page['local'],   $page['shared'],     $page['shared_name'], $typeMap[$page['bad']], $page['rev_user_text']);
  $s = str_replace($original, $replace, $text[$offs]) or fail('Die Texte scheinen verkackt zu sein (' . $textMap[$offs] . ')');
  return $s;
}

$templateMap = array(
  ALLOW        => 'allow',
  NO_MISSING   => 'no missing',
  NO_COMMONS   => 'no commonsseite',
  NO_SHADOWS   => 'no shadows commons',
  NO_DUPLICATE => 'no duplicate',
  NO_NAMED_DUP => 'no renamed duplicate'
);
ksort($templateMap);
$templateMapFlip = array_flip($templateMap);

$templateKey = array(
  ALLOW        => 'A',
  NO_MISSING   => 'M',
  NO_COMMONS   => 'C',
  NO_SHADOWS   => 'S',
  NO_DUPLICATE => 'D',
  NO_NAMED_DUP => 'R'
);
ksort($templateKey); 

$typeMap = array(
    ALLOW        => 'LOCAL',
    NO_MISSING   => 'MISSING',
    NO_COMMONS   => 'COMMONS',
    NO_SHADOWS   => 'SHADOW',
    NO_DUPLICATE => 'DUPLICATE' ,
    NO_NAMED_DUP => 'RENAMED DUP',
);
ksort($typeMap);

$textMap = array(
  FALSE_PREPEND     => 'false prepend',
  FALSE_APPEND      => 'false append',
  FALSE_SUMMARY     => 'false summary',
  
  COMMONS_PREPEND   => 'commonsseite prepend',
  COMMONS_APPEND    => 'commonsseite append',
  COMMONS_SUMMARY   => 'commonsseite summary',
  
  MISSING_PREPEND   => 'missing prepend',
  MISSING_APPEND    => 'missing append',
  MISSING_SUMMARY   => 'missing summary',
  
  SHADOWS_PREPEND   => 'shadows commons prepend',
  SHADOWS_APPEND    => 'shadows commons append',
  SHADOWS_SUMMARY   => 'shadows commons summary',
  
  DUPLICATE_PREPEND => 'duplicate prepend',
  DUPLICATE_APPEND  => 'duplicate append', 
  DUPLICATE_SUMMARY => 'duplicate summary',
  
  NAMED_DUP_PREPEND => 'renamed duplicate prepend',
  NAMED_DUP_APPEND  => 'renamed duplicate append',
  NAMED_DUP_SUMMARY => 'renamed duplicate summary'
);
ksort($textMap);
$textMapFlip = array_flip($textMap);

foreach($templateMapFlip as $i) {
  $templates[$i] = array();
}

$text = array();
$ignored = array();
$commonsIgnoreCategory = array();
$at = NOWHERE;

function xmlCfgStartElementHandler($parser, $name, $attribs) {
  global $templates, $texts, $at, $textMapFlip, $templateMapFlip, $templateMap, $ignored, $commonsIgnoreCategory;
  switch($name) {
    case('TEXT'):
      if($at != NOWHERE) {
        xmlFail();
      }
      if(isset($textMapFlip[ $attribs['TYPE'] ])) {
        $at = $textMapFlip[ $attribs['TYPE'] ];
      } else {
        xmlFail();
      }
    break;
    
    case('RULESET'):
      if($at != NOWHERE) {
        xmlFail();
      }
      if(isset($templateMapFlip[ $attribs['TYPE'] ])) {
        $at = $templateMapFlip[ $attribs['TYPE'] ];
      } elseif($attribs['TYPE'] == 'ignore') {
        $at = IGNORE;
      } elseif($attribs['TYPE'] == 'commons ignore category') {
        $at = IGNORE_CATEGORY;
      } else {
        xmlFail();
      }
    break;
    
    case('PAGE'):
      if($at < 0 || !isset($attribs['TL_TITLE'])) {
        xmlFail();
      }
      array_push($templates[$at], $attribs['TL_TITLE']);
      printf("* TEMPLATE <%s>: <%s>\n", $templateMap[$at], $attribs['TL_TITLE']);
    break;
    
    case('IMAGE'):
      if($at != IGNORE || !isset($attribs['PAGE_TITLE'])) {
        xmlFail();
      }
      $ignored[ $attribs['PAGE_TITLE'] ] = true;
      printf("* IMAGE TO IGNORE: <%s>\n", $attribs['PAGE_TITLE']);
    break;

    case('CATEGORY'):
      if($at != IGNORE_CATEGORY || !isset($attribs['CL_TO'])) {
        xmlFail();
      }
      array_push($commonsIgnoreCategory, $attribs['CL_TO']);
      printf("* CATEGORY TO IGNORE: <%s>\n", $attribs['CL_TO']);
    break;
    
    case('SOURCE'):
    case('SETTINGS'):
    break;
    
    default:
      xmlFail();
  }
}

function xmlCfgEndElementHandler($parser, $name)
{
  global $at, $text, $textMap;
  switch($name) {
    case('SOURCE'):
    case('SETTINGS'):
    case('RULESET'):
      print("\n");
      $at = NOWHERE;
    break;
    
    case('TEXT'):
      if(isset($textMap[$at])) {
        printf("* TEXT <%s>: <%s>\n", $textMap[$at], $text[$at]);
        $at = NOWHERE;
      } else {
        xmlFail();
      }
    break;
  }
}

function xmlCfgCharacterDataHandler($parser, $data)
{
  global $at, $text;
  if($at <= NOWHERE) {
    $text[$at] .= $data;
  } elseif(trim($data) != '') { // keine Texte an falscher Stelle
    xmlFail();
  }
}

$curl = curl_init('http://de.wikipedia.org/w/api.php?format=php') or fail('curl_init');
curl_setopt($curl, CURLOPT_HTTP_VERSION,   CURL_HTTP_VERSION_1_0) or fail('CURLOPT_HTTP_VERSION');
curl_setopt($curl, CURLOPT_AUTOREFERER,    true)  or fail('CURLOPT_AUTOREFERER');
curl_setopt($curl, CURLOPT_FAILONERROR,    false) or fail('CURLOPT_FAILONERROR');
curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true)  or fail('CURLOPT_FOLLOWLOCATION');
curl_setopt($curl, CURLOPT_POST,           true)  or fail('CURLOPT_POST');
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true)  or fail('CURLOPT_RETURNTRANSFER');
curl_setopt($curl, CURLOPT_TIMEOUT,        60)    or fail('CURLOPT_TIMEOUT');
curl_setopt($curl, CURLOPT_ENCODING,       'gzip,deflate')    or fail('CURLOPT_ENCODING');
curl_setopt($curl, CURLOPT_USERAGENT,      'Mozilla/5.0 (U) RevoBot/6.0 revolus@toolserver.org') or fail('CURLOPT_USERAGENT');
curl_setopt($curl, CURLOPT_HTTPHEADER, array(
  'Cache-Control: max-age=0',
  'Accept-Charset: UTF-8,*',
  'Connection: Keep-Alive'
)) or fail('CURLOPT_HTTPHEADER');

curl_setopt($curl, CURLOPT_POSTFIELDS, array(
  'action' => 'query',
  'prop' => 'revisions',
  'titles' => $configName,
  'rvprop' => 'content|ids',
)) or fail('CURLOPT_POSTFIELDS');

$result = curl_exec($curl) or fail('curl_exec()');
$data = unserialize($result) or fail('unserialize()', $result);
if(!isset($data['query']) || !isset($data['query']['pages'])) {
  fail('Config fehlerhaft? ' . $result);
}
list( , $data) = each($data['query']['pages']) or fail('Query', $result);
if(!isset($data['revisions']) || !isset($data['revisions'][0]) || ($data['title'] != $configName)) {
  fail('Falsche Config-Revision', $result);
}
printf("INFO Config revision <%d>\n", $data['revisions'][0]['revid']);
$log .= sprintf("INFO Config revision [{{fullurl:%s|curid=%d&oldid=%d}} %d]\n",
  $data['title'], $data['pageid'], $data['revisions'][0]['revid'], $data['revisions'][0]['revid']
);

$xml = xml_parser_create('UTF-8') or fail('Kein Parser');
xml_parser_set_option($xml, XML_OPTION_CASE_FOLDING, 1) or fail('XML_OPTION_CASE_FOLDING');
xml_parser_set_option($xml, XML_OPTION_SKIP_WHITE, 0) or fail('XML_OPTION_SKIP_WHITE');
xml_set_element_handler($xml, 'xmlCfgStartElementHandler', 'xmlCfgEndElementHandler') or fail('Kein Elementhandler');
xml_set_character_data_handler($xml, 'xmlCfgCharacterDataHandler') or fail('Kein Characterhandler');
xml_parse($xml, $data['revisions'][0]['*'], true) or fail('Konnte Config nicht parsen.');
xml_parser_free($xml);
