<?php
/**
 * pull sass source files from https://terraria.wiki.gg/MediaWiki:Common.css/src/*
 *
 */


use mWiki\Wiki;
use mWiki\WikiFactory;

do{
	echo 'Pull scss files from the wiki to local. Continue? (Y/N)';
	$str = strtolower(trim(fgets(STDIN)));
	if($str==='n'){
		exit;
	}
}while($str !== 'y');

require __DIR__.'/bootstrap.php';

$wiki = WikiFactory::get('terraria');

$page_list_remote = getRemotePageList($wiki);
$page_list_local = getLocalPageList();
if(!$page_list_remote){
	msg('No source file is retrieved. Please check your connection and wiki status, then retry.');
	exit;
}
$removed_pages = array_diff($page_list_local, $page_list_remote);

update($page_list_remote, $wiki);

delete($removed_pages);

msg('done');

/************************************************************************************/

function delete(array $pages):void
{
	foreach($pages as $page){
		$filename = titleToFilename($page);
		msg('Delete: ', $filename);
		unlink($filename);
	}
}

function update(array $list, Wiki $wiki):void
{
	$handle = function ($result) use (&$error) {
		$info = Wiki::parseResult($result, 'query', 'pages');
		if (!$info) {
			msg('Error: there is no `query > pages` fields in result. Please retry later.');
			exit;
		}
		foreach ($info as $page) {
			$title = $page['title'];
			$content = Wiki::parseResult($page, 'revisions', 0, 'slots', 'main', '*'); //ok，保存内容
			// It seems there is a total result length limit. If there is a big page in a batch,
			// Those pages after it may get empty content. We have to try to retrieve such pages again.
			if(!$content){
				$error[] = $title;
				continue;
			}
			$filename = titleToFilename($title);
			if(normalizeContent(file_get_contents($filename)) != normalizeContent($content)){
				msg('Update: ', $filename);
				@mkdir(dirname($filename), 0777, true);
				file_put_contents($filename, $content);
			}
			else{
				msg('No change: ', $filename);
			}
		}
	};

	$c = 30; //how many pages in a batch
	do{
		$error = [];
		foreach (array_chunk($list, $c) as $chunk) {
			$request = $wiki->newGetApiCall('query', ['titles' => implode('|', $chunk), 'prop' => 'revisions', 'rvprop' => 'content', 'rvslots' => 'main', 'redirects' => 'yes']);
			$request->setOnCompleteHandle($handle);
		}
		$wiki->sendApiCalls();
		if(count($list) == count($error)){
			msg('Nothing is retrieved, please check wiki status.');
			exit;
		}
		$c = floor($c/2);
		if($c < 1){
			$c = 1;
		}
		$list = $error;
	}while($error);
}


