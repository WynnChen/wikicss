<?php
namespace mWiki;
use mHttp\Request;

/**
 * Wiki Request
 */
class WikiRequest extends Request
{
	public readonly Wiki $wiki;
	/**
	 * @var callable|null
	 */
	private $onerror = null;

	public function __construct(Wiki $wiki, string $url = null, ?array $data = null, \mHttp\Method $method = \mHttp\Method::GET)
	{
		parent::__construct($url, $data, $method);
		$this->wiki = $wiki;
		$this->onerror = $this->defaultErrorHandle(...);
	}

	/**
	 * callable: function($result, $request)
	 * @param callable|null $callback
	 * @param ...$path
	 * @return $this
	 */
	public function setOnCompleteHandle(?callable $callback, ...$path): self
	{
		$wiki = $this->wiki;
		return parent::setOnCompleteHandle(function($request, $response)use($wiki, $callback, $path){
			set_error_handler($this->onerror);
			$result = unserialize($response);
			restore_error_handler();
			$result = $wiki->parseResult($result, ...$path);
			call_user_func($callback, $result, $request);
		});
	}

	public function setOnErrorHandle(?callable $callback): self
	{
		$this->onerror = $callback;
		return $this;
	}

	private function defaultErrorHandle()
	{
		throw new Exception('Failed to parse API result.');
	}

}