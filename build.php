<?php
/**
 * custom postprocessor.
 * build Common.css and theme css files output from css/*
 */

require __DIR__.'/bootstrap.php';

/*** Common.css ***/
$filename = CSS_DIR.'/Common.css';
if(!file_exists($filename)){
	msg('The css/Common.css file does not exist.');
	exit;
}
$content = file_get_contents($filename);
// directive: /* @import <file> */
$pattern = '|^([ \t]*)/\*\s*@import (.+)\s*\*/|iUum';
do{
	$content = preg_replace_callback($pattern, function($matches){
		$filename = $matches[2];
		if(!str_ends_with($filename, '.css')){
			$filename .= '.css';
		}
		$content = file_get_contents(CSS_DIR.'/'.$filename);
		$content = $matches[1].str_replace("\n", "\n".$matches[1], $content);//inherit the indent of /* @import */
		return $content;
	}, $content, -1, $count);
}while($count);
// directive: /*!! <internal comment> */
$content = preg_replace('/\/\*!!.+\*\//iUus', '', $content);
// directive: /*<< <comment> */
$content = preg_replace('/\s+\/\*<<(.+)\*\//iUus', ' /*\1*/', $content);

// directives: @theme <name> { ... }
$themes = array();
$content = preg_replace_callback(
	pattern: '|^(\s*)@theme\s+(.+)\s*{$\n(.+)^\1}$\n|iUusm',
	callback: function($matches)use(&$themes){
		$name = $matches[2];
		$content = preg_replace('/^  /m', '', $matches[3]);
		$themes[$name] = ($themes[$name]??'').$content;
		return '';
	},
	subject: $content,
);

file_put_contents(OUTPUT_DIR.'/Common.css', $content);
foreach($themes as $name => $info){
	file_put_contents(OUTPUT_DIR.'/Theme-'.$name.'.css', "/* theme: $name */\n".$info);
}
