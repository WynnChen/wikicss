<?php
namespace mWiki;
/**
 * Class WikiPage
 *
 * 代表wiki页面。
 * 除了基础的 page_info 信息外，其他全部都无缓存，通过api临时获取，需要缓存要自行处理。
 */
class WikiPage
{

	private Wiki $wiki;
	private array $page_info;

	/**
	 * WikiPage constructor.
	 * @param Wiki $wiki
	 * @param array $page_info
	 */
	public function __construct(Wiki $wiki, array $page_info)
	{
		$this->wiki = $wiki;
		$this->page_info = $page_info;
	}

	public function getPageId()
	{
		return $this->page_info['pageid'];
	}

	/**
	 * @param bool $full
	 * @return string
	 */
	public function getTitle(bool $full = false):string
	{
		$title = $this->page_info['title'];
		if($full){
			$title = sprintf('%s:%s', $this->wiki->getNamespace($this->page_info['ns'], 'canonical'), $title);
		}
		return $title;
	}

	/**
	 * @return string|null
	 * @throws ApiException
	 */
	public function getContent(): ?string
	{
		return $this->wiki->query(
			['prop'=>'revisions', 'rvprop'=>'content', 'pageids'=>$this->page_info['pageid']],
			'query','pages', $this->page_info['pageid'],'revisions',0, '*'
		);
	}

	/**
	 * @see https://www.mediawiki.org/wiki/API:Parsing_wikitext
	 *
	 * @param $parameters
	 * @param string:int ...$path
	 * @return mixed
	 * @throws ApiException
	 */
	public function parse($parameters, ...$path)
	{
		$parameters['pageid'] = $this->page_info['pageid'];
		return $this->wiki->get('parse', $parameters, ...$path);
	}

	/**
	 * 分析interwiki links
	 * 完整信息类似于
	 *      array(5) {
		["lang"]=>
		string(2) "fr"
		["url"]=>
		string(48) "https://terraria-fr.gamepedia.com/Jeune_Plantera"
		["langname"]=>
		string(6) "French"
		["autonym"]=>
		string(9) "français"
		["*"]=>
		string(14) "Jeune Plantera"
		}
	 * @param string|null $lang 目标lang,不写返回全部。
	 * @param bool $full 完整信息还是单纯目标（就是 [*] 字段的内容）
	 * @return array|string|null
	 * @throws ApiException
	 */
	public function parseLanglinks(?string $lang = null, bool $full = false)
	{
		$result = $this->parse(['prop'=>'langlinks'], 'parse', 'langlinks');
		if(!$result){
			return null;
		}
		$langlinks = [];
		foreach($result as $row){
			if (!empty($langlinks[$row['lang']])){
				throw new ApiException('langlink重复： '.$this->wiki->type.' | '.$this->getTitle(true).' | ',$row['lang']);
			}
			$langlinks[$row['lang']] = $full ? $row : $row['*'];
		}

		return $lang ? @$langlinks[$lang] : $langlinks;
	}

	/**
	 * @return array|null
	 * @throws ApiException
	 */
	public function getCategories():?array
	{
		$result = $this->parse(['prop'=>'categories'], 'parse', 'categories');
		if(!$result){
			return null;
		}
		$categories = [];
		foreach($result as $row){
			$categories[] = $row['*'];
		}
		return $categories;
	}

	/**
	 * @param string $category
	 * @param bool $case_insenstive
	 * @return bool
	 * @throws ApiException
	 */
	public function hasCategory(string $category, bool $case_insenstive = false):bool
	{
		$categories = $this->getCategories();
		if(!$categories){
			return false;
		}
		$category = ucfirst(str_replace(' ', '_', $category));
		foreach($categories as $cate){
			if($case_insenstive){
				if(strtolower($cate) == strtolower($category)){
					return true;
				}
			}
			else{
				if($cate == $category){
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @param string $content
	 * @param string $summary
	 * @param bool $minor
	 * @param bool $bot
	 * @return mixed|null
	 * @throws ApiException
	 */
	public function edit(string $content, string $summary, bool $minor = false, bool $bot = true)
	{
		return $this->wiki->edit([
			'pageid'=>$this->page_info['pageid'],
			'text'=>$content,
			'summary'=>$summary,
			'minor'=> $minor?'yes':null,
			'bot'=>$bot?'yes':null
		]);
	}

	public function isLanguagePage()
	{
		$title = $this->getTitle();
		$langs = 'ar, bg, cs, da, el, es, fi, hi, id, it, ja, lt, lv, nl, no, ro, sk, sv, th, tr, vi, yue, de, fr, hu, ko, ru, pl, pt, uk, zh';
		foreach(explode(', ', $langs) as $lang){
			if(str_ends_with($title, '/'.$lang)){
				return true;
			}
			if(str_contains($title, '/'.$lang.'/')){
				return true;
			}
		}
		return  false;
	}

}