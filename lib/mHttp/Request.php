<?php
namespace mHttp;
use ArrayObject;

class Request extends ArrayObject
{
	public string $url;
	public ?array $data = null;
	public ?array $options = null;
	public ?array $headers = null;
	public ?Client $client = null;
	public Method $method = Method::GET;

	private bool $auto_retry = true;

	/**
	 * @var callable|null;
	 */
	private $on_complete = null;

	public function __construct(string $url = null, ?array $data = null, Method $method = Method::GET)
	{
		parent::__construct();
		$url and ($this->url = $url);
		$data and ($this->data = $data);
		$this->method = $method;
	}

	public function retry():bool
	{
		if($this->client){
			$this->client->addRequest($this);
			return true;
		}
		return false;
	}

	public function onComplete($response, $request_info)
	{
		//默认处理，保存就完事了。
		$this['response'] = $response;
		$this['request_info'] = $request_info;
		//检查是否需要retry
		if(!$response and $this->auto_retry){
			$this->retry();
			return;
		}
		if($this->on_complete) {
			//有额外的处理
			call_user_func($this->on_complete, $this, $response, $request_info);
		}
	}

	/**
	 * @return callable|null
	 */
	public function getOnCompleteHandle(): ?callable
	{
		return $this->on_complete;
	}

	/**
	 * callable的格式： function($request, $response, $request_info)
	 *
	 * @param callable|null $on_complete
	 * @return Request
	 */
	public function setOnCompleteHandle(?callable $on_complete): self
	{
		$this->on_complete = $on_complete;
		return $this;
	}

}