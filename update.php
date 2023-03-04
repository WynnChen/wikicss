<?php
/**
 * Use the content of output.Common.css to update MediaWiki:Common.css
 */

use mWiki\Wiki;
use mWiki\WikiConfig;
use mWiki\WikiFactory;

require __DIR__.'/bootstrap.php';

$filename = OUTPUT_DIR.'/Common.css';

if(!file_exists($filename)){
	msg('The output Common.css file does not exist. Please run "build" command first.');
	exit;
}

$content = file_get_contents($filename);

array_shift($argv);
$summary = ($argv[0]??null) ? implode(' ', $argv) : null;

//upload to MediaWiki:Common.css
$wiki = WikiFactory::get('terraria');
array_shift($argv);
$result = $wiki->edit(['title'=>'MediaWiki:Common.css', 'text'=>$content, 'summary'=> ($summary?:'update.')]);
if(Wiki::parseResult($result, 'edit', 'result') == 'Success'){
	msg('common.css updated.');
}
else{
	msg('Failed to parse the operation result, please check the history of MediaWiki:Common.css page.');
}

// theme css pages
$page = $wiki->getPageByTitle('MediaWiki:Theme-definitions');
$content = $page->getContent();
preg_match_all('|^\*(.+)(\[.+])?$|iUum', $content, $matches);
foreach ($matches[1] as $index => $theme){
	if(strpos($matches[2][$index], 'bundled')){
		continue;
	}
	$theme = trim($theme);
	$filename = 'Theme-'.$theme.'.css';
	$file = OUTPUT_DIR.'/'.$filename;
	if(file_exists($file)){
		$content = file_get_contents($file);
	}
	else{
		$content = '/* Intentionally left blank */';
	}
	$result = $wiki->edit(['title'=>'MediaWiki:'.$filename, 'text'=>$content, 'summary'=> ($summary?:'update.')]);
	if(Wiki::parseResult($result, 'edit', 'result') == 'Success'){
		msg($filename.' updated.');
	}
	else{
		msg('Failed to parse the operation result, please check the history of MediaWiki:'.$filename.' page.');
	}
}

msg('done');

