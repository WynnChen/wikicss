<?php
/**
 * Upload scss files under /scss folder to MediaWiki:Common.css/src/
 */

use mWiki\Wiki;
use mWiki\WikiFactory;

do{
	echo 'Push local scss files to the wiki. Continue? (Y/N)';
	$str = strtolower(trim(fgets(STDIN)));
	if($str==='n'){
		exit;
	}
}while($str !== 'y');

require __DIR__.'/bootstrap.php';

array_shift($argv);
$summary = ($argv[0]??null) ? implode(' ', $argv) : null;

$wiki = WikiFactory::get('terraria');

$page_list_local = getLocalPageList();
$page_list_remote = getRemotePageList($wiki);
$removed_pages = array_diff($page_list_remote, $page_list_local);

update($page_list_local, $wiki, $summary);

delete($removed_pages, $wiki, $summary);

msg('done.');

/******************************************************************************/



/**
 * @throws \mWiki\Exception
 */
function delete(array $pages, Wiki $wiki, ?string $summary):void
{
	//delete unused pages
	foreach ($pages as $page) {
		msg('Delete: ', $page);
		$wiki->delete(['title' => $page, 'reason' => ($summary?:'no longer used.')]);
	}
}


/**
 * @throws \mHttp\Exception
 */
function update(array $pages, Wiki $wiki, ?string $summary):void
{
	$handle = function ($result) use ($wiki, &$error, $summary) {
		$info = Wiki::parseResult($result, 'query', 'pages');
		if (!$info) {
			msg('Error: there is no `query > pages` fields in result. Please retry later.');
			exit;
		}
		foreach ($info as $page) {
			$title = $page['title'];
			$filename = titleToFilename($title);
			$file_content = file_get_contents($filename);
			if(Wiki::parseResult($page, 'missing') !== null){ //new page
				msg('New: ', $title);
				$wiki->edit(['title'=>$title, 'text'=>$file_content, 'summary'=>($summary?:'init.')]);
				sleep(1);
				$wiki->postWithToken('changecontentmodel', ['title'=>$title, 'model'=>'css', 'summary'=>'change content model to css']);
			}
			else{
				$content = Wiki::parseResult($page, 'revisions', 0, 'slots', 'main', '*'); //content
				// It seems there is a total result length limit. If there is a big page in a batch,
				// Those pages after it may get empty content. We have to try again to retrieve such pages.
				if(!$content){
					$error[] = $title;
					continue;
				}
				if(normalizeContent($file_content) != normalizeContent($content)){
					msg('Update: ', $title);
					$wiki->edit(['title'=>$title, 'text'=>$file_content, 'summary'=>($summary?:'update.')]);
				}
				else{
					msg('No change: ', $title);
				}
				if($page['contentmodel'] != 'css'){
					$wiki->postWithToken('changecontentmodel', ['title'=>$title, 'model'=>'css', 'summary'=>'change content model to css']);
				}
			}
		}
	};

	$c = 30; //how many pages in a batch
	do{
		$error = [];
		foreach (array_chunk($pages, $c) as $chunk) {
			$request = $wiki->newGetApiCall('query', ['titles' => implode('|', $chunk), 'prop' => 'revisions|info', 'rvprop' => 'content', 'rvslots' => 'main', 'redirects' => 'yes']);
			$request->setOnCompleteHandle($handle);
		}
		$wiki->sendApiCalls();
		if(count($pages) == count($error)){
			msg('Nothing is retrieved, please check wiki status.');
			exit;
		}
		$c = floor($c/2);
		if($c < 1){
			$c = 1;
		}
		$pages = $error;
	}while($error);
}
