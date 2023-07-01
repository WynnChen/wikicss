<?php
use mFramework\ClassLoader;
use mWiki\Wiki;
use mWiki\WikiFactory;

if (!defined('ROOT_DIR')){
	define('ROOT_DIR', __DIR__);
	require ROOT_DIR . '/lib/mFramework/ClassLoader.php';
	require ROOT_DIR . '/lib/mFramework/Map.php';

	ClassLoader::getInstance()
		->addNamespace('' , ClassLoader::baseDirHandle(ROOT_DIR))
		->addNamespace('mHttp', ClassLoader::baseDirHandle(ROOT_DIR.'/lib/mHttp'))
		->addNamespace('mWiki', ClassLoader::baseDirHandle(ROOT_DIR.'/lib/mWiki'))
		->register();

	define('SRC_PATH', 'MediaWiki:Common.css/src/');

	define('SCSS_DIR', ROOT_DIR.'/scss');
	define('CSS_DIR', ROOT_DIR.'/css');
	define('OUTPUT_DIR', ROOT_DIR.'/output');

	$config = require(__DIR__.'/config.php');
	WikiFactory::register('terraria', $config);

	set_error_handler(function($no, $str, $file, $line){
		if (!(error_reporting() & $no)) {
			// This error code is not included in error_reporting
			return;
		}
		echo "\n\n ======== ERROR =========\n\n";
		echo 'Code ', $no, ': ',$str, "\n";
		echo 'File ', $file,', line ',$line, "\n";
		//echo 'error_context: ', "\n"; var_export($context);
		exit;
	});

	set_exception_handler(function ($e){
		echo "\n\n ======== EXCEPTION =========\n\n";
		msg($e->getMessage());
		msg($e->getTraceAsString());
	});

	function msg(...$t){
		echo "\n";
		foreach($t as $msg){
			echo $msg;
		}
		echo "\n";
	}


	/*********************************************************************/
	function getRemotePageList(Wiki $wiki):?array
	{
		msg('Retrieveing all source scss pages from wiki...');
		$params = array(
			'list' => 'prefixsearch',
			'pssearch' => SRC_PATH,
			'pslimit' => 500,
		);
		$list = $wiki->query($params, 'query', 'prefixsearch');
		$list = array_map(fn($info) => normalizeFilename($info['title']), $list);
		$list = array_filter($list, fn($title) => $title != 'MediaWiki:Common.css/src/nav');
		return $list;
	}

	function getLocalPageList():array
	{
		$directory = new RecursiveDirectoryIterator(SCSS_DIR);
		$iterator = new RecursiveIteratorIterator($directory);
		$regex = new RegexIterator($iterator, '/^.+\.(s)?css$/i');
		$files = array_keys(iterator_to_array($regex));
		$list = array_map(fn($name) => str_replace('\\', '/', substr($name, strlen(SCSS_DIR)+1)), $files);
		return array_map(fn($name) => SRC_PATH.$name, $list);
	}

	function titleToFilename($title):string
	{
		$title = normalizeFilename($title);
		return SCSS_DIR.'/'.substr($title, strlen(SRC_PATH));
	}

	function normalizeContent(string $content):string
	{
		return str_replace(["\r\n", "\n\r", "\r"], "\n",rtrim($content));
	}

	function normalizeFilename(string $filename):string
	{
		return str_replace(' ', '_', $filename);
	}

}
