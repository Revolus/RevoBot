<?php

include('../load_config.php'); // sets $curl, fail, xmlFail, doLog, <config>...
include('/home/revolus/.mysql.link.php'); // sets $link

function mysql_array_escape($d)
{
  global $link;
  $r = mysql_real_escape_string($d, $link) or fail(mysql_error());
  return "'$r'";
}

foreach($templates as $k => $v) {
  $templates[$k] = array_map('mysql_array_escape', $templates[$k]);
}

mysql_query('DELETE FROM `u_revolus`.`bestandscheck`')  or fail(mysql_error());

$start = 0;
for(; ;) {
  $query = '
SELECT /* SLOW_OK */
  `page_id`,
  `page_latest`,
  (IF(I1.`img_name` IS NULL
    , IF(NOT I2.`img_name` IS NULL
      , 0 + EXISTS(SELECT * FROM `dewiki_p`.`templatelinks` WHERE (`tl_from` = `page_id`) AND (`tl_namespace` = 10) AND (`tl_title` IN (' . implode(', ', $templates[NO_COMMONS]) . ')))
      , 2 + EXISTS(SELECT * FROM `dewiki_p`.`templatelinks` WHERE (`tl_from` = `page_id`) AND (`tl_namespace` = 10) AND (`tl_title` IN (' . implode(', ', $templates[NO_MISSING]) . ')))
    )
    , IF(NOT I2.`img_name` IS NULL
        , IF(I2.`img_name` <=> I3.`img_name`
          , 4 + EXISTS(SELECT * FROM `dewiki_p`.`templatelinks` WHERE (`tl_from` = `page_id`) AND (`tl_namespace` = 10) AND (`tl_title` IN (' . implode(', ', $templates[NO_DUPLICATE]) . ')))
          , 6 + EXISTS(SELECT * FROM `dewiki_p`.`templatelinks` WHERE (`tl_from` = `page_id`) AND (`tl_namespace` = 10) AND (`tl_title` IN (' . implode(', ', $templates[NO_SHADOWS]) . ')))
        )
        , IF(NOT I3.`img_name` IS NULL
          , 8 + EXISTS(SELECT * FROM `dewiki_p`.`templatelinks` WHERE (`tl_from` = `page_id`) AND (`tl_namespace` = 10) AND (`tl_title` IN (' . implode(', ', $templates[NO_NAMED_DUP]) . ')))
          , 10 + EXISTS(SELECT * FROM `dewiki_p`.`templatelinks` WHERE (`tl_from` = `page_id`) AND (`tl_namespace` = 10) AND (`tl_title` IN (' . implode(', ', $templates[ALLOW]) . ')))
        )
      )
    )
  ) AS `res`
FROM `dewiki_p`.`page`
LEFT OUTER JOIN `dewiki_p`.`image`      I1 ON I1.`img_name` = `page_title`
LEFT OUTER JOIN `commonswiki_p`.`image` I2 ON I2.`img_name` = `page_title`
LEFT OUTER JOIN `commonswiki_p`.`image` I3 ON I3.`img_sha1` = I1.`img_sha1`
WHERE `page`.`page_namespace` = 6
AND `page`.`page_is_redirect` = 0
LIMIT '.$start.',1000
';
  $res = mysql_query($query, $link) or fail(mysql_error());
  $num = mysql_num_rows($res);
  if($num == 0) {
    break;
  }
  $start += $num;
  while($row = mysql_fetch_assoc($res)) {
    print(($row['res']%2) . ',' . implode(',', $row) . "\n");
    if(($row['res']%2) == 0) {
      $values[] = sprintf('(%d,%d,%d)', $row['page_id'], $row['page_latest'], $row['res']);
    }
  }
  mysql_free_result($res);
  
  if(count($values)) {
    mysql_query('
INSERT IGNORE INTO
  `u_revolus`.`bestandscheck` (`page_id`, `rev_id`, `wrong`)
VALUES ' . implode(', ', $values) . '
', $link) or fail(mysql_error());
    unset($values);
  }
}
mysql_close($link);
