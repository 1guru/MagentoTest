<?php

$file_content = file_get_contents(dirname(__FILE__) . '/../sitemap/sitemap.xml');
$matches=null;
preg_match_all('/(http:[^<]*)/', $file_content, $matches);

if(!empty($matches[1])) {
	foreach ($matches[1] as $link) {
		file_get_contents($link);
		echo "$link\n";
	}
}
