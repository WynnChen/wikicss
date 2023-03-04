<?php
namespace mWiki;
use mHttp\Client;
use mHttp\Method;

/**
 * wiki bot
 */
Class Wiki
{
	public readonly array $namespaces;

	private readonly Client $client;

	private readonly string $endpoint;
	public readonly string $username;
	private readonly string $password;
	public readonly bool $is_bot;

	/**
	 * 初始化。
	 * @param string $name
	 * @param WikiConfig $config
	 * @throws Exception
	 */
	public function __construct(string $name, WikiConfig $config)
	{
		$this->endpoint = $config->endpoint;
		$this->username = $config->username;
		$this->password = $config->password;
		$this->is_bot = $config->is_bot;

		//初始化客户端
		$this->client = new Client([
			'max_con' => $config->max_con,
			'con_timeout' => $config->con_timeout,
			'trans_timeout' => $config->trans_timeout
		], $config->curl_options);
		$this->client->setOptions([CURLOPT_USERAGENT => 'westgrass_Bot/2.0 (By Westgrass; For Terraria Wiki) (user: '.$this->username.')']);

		//登录先：
		echo $name, ' init:', "\n", 'login...', "\n";

		try{
			$this->login();
		}catch (Exception $exception){
			die('Login failed.');
		}
		echo 'parse namespaces info...', "\n";
		$info = $this->query(['meta'=>'siteinfo', 'siprop'=>'namespaces|namespacealiases'], 'query');
		$namespaces = self::parseResult($info, 'namespaces');
		$n = [];
		foreach($namespaces as $namespace){
			if($namespace['canonical']??null){
				$n[ $namespace['canonical'] ] = $namespace['id'];
			}
			if($namespace['*']??null) {
				$n[$namespace['*']] = $namespace['id'];
			}
		}
		$aliases = self::parseResult($info, 'namespacealiases');
		foreach($aliases as $alias){
			$n[ $alias['*'] ] = $alias['id'];
		}
		//main ns
		$n[''] = 0;
		$n['Main'] = 0;
		$n['main'] = 0;
		$this->namespaces = $n;
		echo 'ok.', "\n";
	}

	/**
	 * @throws Exception
	 */
	protected function login():void
	{
		//login
		$token = $this->query(['meta'=>'tokens', 'type'=>'login'], 'query','tokens','logintoken');
		if(!$token){
			throw new Exception('Can not retrieve login token.');
		}
		$result = $this->post('login', ['lgname'=>$this->username, 'lgpassword'=>$this->password, 'lgtoken'=>$token]);
		if(self::parseResult($result, 'login', 'result') != 'Success'){
			var_dump($result);
			throw new Exception('login failed');
		}
	}

	/**
	 * 标准化wiki页面标题。 urldecode，_替换为空白，以及ucfirst处理
	 * @param string $title
	 * @return string
	 */
	public static function normalize(string $title): string
	{
		$pieces = explode(':', rawurldecode($title));
		$pieces = array_map(function($str){
			return str_replace('_', ' ', ucfirst($str));
		}, $pieces);
		return trim(implode(':', $pieces));
	}

	/**
	 * 同步方式请求API
	 * @param Method $method
	 * @param string $action
	 * @param array $parameters
	 * @param mixed ...$path
	 * @return mixed|null
	 * @throws Exception
	 */
	protected function api(Method $method, string $action, array $parameters, ...$path): mixed
	{
		$parameters = ['action' => $action, 'format' => 'php'] + $parameters;

		$client = $this->client;
		$request = $this->createRequest($parameters, $method);
		$result = $client->sendSyncRequest($request);
		set_error_handler(function()use($method, $result){
			throw new Exception('Error parsing API result. '.$method->value.' '.$result);
		});

		//file_put_contents('debug.txt', $response);
		$result = unserialize($result);
		restore_error_handler();
		return $this->parseResult($result, ...$path);
	}

	/**
	 * 试图从api的返回结果里捞出特定key路径的内容。 任何一步不成功结果都是null
	 * @param $result
	 * @param mixed ...$path
	 * @return mixed
	 */
	public static function parseResult($result, ...$path): mixed
	{
		if(!$result){
			return null;
		}
		foreach($path as $key){
			if(!array_key_exists($key, $result)) {
				return null;
			}
			$result = $result[$key];
		}
		return $result;
	}



	/**
	 * 同步方式
	 * @param string $action
	 * @param array $parameters
	 * @param mixed ...$path
	 * @return mixed
	 * @throws Exception
	 */
	public function get(string $action, array $parameters, ...$path): mixed
	{
		return $this->api(Method::GET, $action, $parameters, ...$path);
	}

	/**
	 * 同步方式
	 * @param string $action
	 * @param array $parameters
	 * @param mixed ...$path
	 * @return mixed
	 * @throws Exception
	 */
	public function post(string $action, array $parameters, ...$path): mixed
	{
		return $this->api(Method::POST, $action, $parameters, ...$path);
	}

	public function postWithToken($action, array $parameters): mixed
	{
		$this->csrfToken($parameters);
		return $this->post($action, $parameters);
	}

	/**
	 * 同步
	 * @param array $parameters
	 * @param mixed ...$path
	 * @return mixed
	 * @throws Exception
	 */
	public function query(array $parameters, ...$path): mixed
	{
		return $this->get('query', $parameters, ...$path);
	}

	/**
	 * 给parameter附加上csrftoken。
	 *
	 * 注意涉及到token的操作都要用同步方式，异步并行处理会因为竞争导致token可能失效。
	 * @param array $parameters
	 * @throws Exception
	 */
	protected function csrfToken(array &$parameters)
	{
		//反复尝试三次
		$token = $this->query(['meta'=>'tokens'], 'query', 'tokens', 'csrftoken');
		if(!$token){
			sleep(5);
			$token = $this->query(['meta'=>'tokens'], 'query', 'tokens', 'csrftoken');
		}
		if(!$token){
			sleep(5);
			$token = $this->query(['meta'=>'tokens'], 'query', 'tokens', 'csrftoken');
		}
		if(!$token){
			throw new Exception('can not retireve csrf token.');
		}
		$parameters['token'] = $token;
		sleep(1);
	}

	/**
	 * API edit
	 * 调用之前无需处理token问题，本调用会自行处理。本调用会自动加入 assert = user。
	 * @param array $parameters
	 * @return mixed
	 * @throws Exception
	 */
	public function edit(array $parameters): mixed
	{
		sleep(1); //辅助维持不触发频次错误。
		$this->csrfToken($parameters);
		$parameters['assert'] = $this->is_bot?'bot':'user';
		$parameters['bot'] = $this->is_bot?"yes":null;
		return $this->post('edit', $parameters);
	}

	/**
	 * @param array $parameters
	 * @return mixed
	 * @throws Exception
	 */
	public function delete(array $parameters): mixed
	{
		$this->csrfToken($parameters);
		return $this->post('delete', $parameters);
	}

	/**
	 * API move
	 *
	 * @param array $parameters
	 * @return mixed
	 * @throws Exception
	 */
	public function move(array $parameters): mixed
	{
		$this->csrfToken($parameters);
		return $this->post('move', $parameters);
	}

	/**
	 * 根据wikitext内容来判断是否是重定向
	 * @param $wikitext
	 * @return string|null
	 */
	public static function isRedirect($wikitext):?string
	{
		if(preg_match('/#(重定向|redirect)\s*\[\[(.+)]]/i', $wikitext, $matches)) {
			return $matches[2];
		}
		return null;
	}

	public function getNamespace($name)
	{
		return $this->namespaces[$name]??null;
	}

	public function getNamespaces(...$names)
	{
		$ns = [];
		foreach($names as $name){
			if(($this->namespaces[$name]??null) !== null){
				$ns[] = $this->namespaces[$name];
			}
		}
		return $ns;
	}

	public function getPagesUsingTemplate(string $template, int $limit = 0, array $namespaces = [0])
	{
		echo '获取使用了模板'.$template.'的页面 | namespace=', implode(',', $namespaces), ' | limit=', $limit;
		$list = [];
		foreach($namespaces as $namespace){
			$eicontinue = null;
			echo "\nnamespace ".$namespace.': ';
			while(true){
				$params = ['list'=>'embeddedin', 'eititle'=>'Template:'.$template, 'einamespace' => $namespaces, 'eifilterredir' => 'all'];
				if($eicontinue){
					$params['eicontinue'] = $eicontinue;
				}
				if($limit and $limit < 200){
					$params['eilimit'] = $limit;
				}
				else{
					$params['eilimit'] = 200;
				}
				$result = $this->get('query', $params);

				foreach($result['query']['embeddedin'] as $page){
					$list[] =  new WikiPage($this, $page);
				}

				if(empty($result['continue']['eicontinue'])){
					break;
				}
				else{
					$eicontinue = $result['continue']['eicontinue'];
				}
				$count = count($list);
				echo $count, ' ';
				if($limit and $count >= $limit){
					break 2;
				}
			}
		}
		echo "\n", '获取完毕', "\n";
		return $list;
	}

	/**
	 * @param int|null $page_id
	 * @param string|null $title
	 * @param bool $follow_redirect
	 * @return WikiPage|null
	 * @throws Exception
	 */
	protected function getPage(?int $page_id, ?string $title, bool $follow_redirect): ?WikiPage
	{
		$result = $this->query(['pageids'=> $page_id, 'titles' => $title, 'redirects'=>$follow_redirect?'yes':null], 'query', 'pages');
		$page_info = current($result);
		if (!$page_info) {
			//echo '查询无结果', $title;
			return null;
		}
		if (isset($page_info['invalid'])) {
			//echo '标题无效 ', $title;
			return null;
		}
		if (isset($page_info['missing'])) {
			//echo '页面不存在 ', $title;
			return null;
		}
		if (!$page_info['pageid'] or $page_info['pageid'] < 0) {
			//echo '页面ID异常 ', $page_id;
			return null;
		}
		return $this->page($page_info);
	}

	protected function page($page_info):?WikiPage{
		return new WikiPage($this, $page_info);
	}

	/**
	 * 获取指定namespace下的所有页面，不同namespace之间用并行
	 *
	 * @param array $namespaces
	 * @param array $params
	 * @return array
	 * @throws \mHttp\Exception
	 */
	protected function getAllPages(array $namespaces, array $params): array
	{
		$limit = $params['aplimit'];
		if(empty($params['aplimit']) or $params['aplimit'] > 500){
			$params['aplimit'] = 500;
		}
		$from = $params['apfrom'] ?? null;
		$params['list'] = 'allpages';
		$list = [];
		$handle = function($result, $request)use(&$list, $limit, &$handle){
			if($this->parseResult($result, 'error')){
				var_dump($result);
				throw new Exception();
			}
			//var_dump($result);
			foreach((array)$this->parseResult($result, 'query', 'allpages') as $page_info){
				$list[] = $this->page($page_info);
			}
			$count = count($list);
			echo $count, ' ';
			if($limit and $count >= $limit){
				return;
			}
			$apfrom = $this->parseResult($result, 'continue','apcontinue');
			if($apfrom){
				$params = $request['params'];
				$params['apfrom'] = $apfrom;
				$request = $this->newApiCall(Method::GET, 'query', $params);
				$request['params'] = $params;
				$request->setOnCompleteHandle($handle);
			}
		};
		foreach($namespaces as $namespace){
			$params['apnamespace'] =$namespace;
			$params['apfrom'] = $from;
			$request = $this->newApiCall(Method::GET, 'query', $params);
			$request['params'] = $params;
			$request->setOnCompleteHandle($handle);
		}
		$this->sendApiCalls();
		return $list;
	}


	/**
	 * @param null $limit
	 * @param int[] $namespaces
	 * @param null $from
	 * @return array
	 * @throws \mHttp\Exception
	 */
	public function getAllContentPages($limit = null, array $namespaces = [0], $from = null): array
	{
		msg('获取全部内容页面：namespace='.implode(',', $namespaces), ' limit='.$limit, ' from='.$from);

		$params = array(
			'apfilterredir'=>'nonredirects',
			'apfrom' => $from,
			'aplimit' => $limit,
		);
		$list = $this->getAllPages($namespaces, $params);
		msg('完毕');
		return $list;
	}

	/**
	 * @param null $limit
	 * @param int[] $namespaces
	 * @param null $from
	 * @return array
	 * @throws \mHttp\Exception
	 */
	public function getAllRedirectPages($limit = null, array $namespaces = [0], $from = null): array
	{
		msg('获取全部重定向页面：namespace=' . implode(',', $namespaces), ' limit=' . $limit, ' from=' . $from);

		$params = array(
			'apfilterredir' => 'redirects',
			'apfrom' => $from,
			'aplimit' => $limit,
			'redirect'
		);
		$list = $this->getAllPages($namespaces, $params);
		msg('完毕');
		return $list;
	}

	/**
	 * 异步方式请求API，用回调触发后继处理
	 * 注意本方法不会直接触发execute
	 * @param Method $method
	 * @param string $action
	 * @param array $parameters
	 * @return WikiRequest
	 */
	public function newApiCall(Method $method, string $action, array $parameters):WikiRequest
	{
		$parameters = ['action' => $action, 'format' => 'php'] + $parameters;
		$client = $this->client;

		$request = $this->createRequest($parameters, $method);
		$client->addRequest($request);
		return $request;
	}

	public function newGetApiCall(string $action, array $parameters):WikiRequest
	{
		return $this->newApiCall(Method::GET, $action, $parameters);
	}
	public function newPostApiCall(string $action, array $parameters):WikiRequest
	{
		return $this->newApiCall(Method::POST, $action, $parameters);
	}

	public function createRequest(?array $paramters = null, Method $method = Method::GET):WikiRequest
	{
		return new WikiRequest($this, $this->endpoint, $paramters, $method);
	}

	/**
	 * 并行执行所有api call
	 * @throws \mHttp\Exception
	 */
	public function sendApiCalls()
	{
		$this->client->execute();
	}


	/**
	 * 从输入的标题名/主文件名进行查询，完成标准化处理、重定向解析等，输出最终页面的信息
	 * 传入文件名为  Template_xxxx 会被特殊对待（不分大小写），视为模板转换为Template:xxxx 进行后继处理
	 *
	 * @param string $query_title
	 * @param bool $follow_redirect
	 * @return null|WikiPage
	 * @throws Exception
	 */
	public function getPageByTitle(string $query_title, bool $follow_redirect = true) : ?WikiPage
	{
		$title = self::normalize($query_title);
		$params = ['titles'=> $title];
		if($follow_redirect){
			$params['redirects'] = 'yes';
		}
		$result = $this->query($params, 'query');
		//解析取ID
		$page_info = current($result['pages']);
		if(!$page_info){
			//echo '查询无结果', $title;
			return null;
		}
		if(isset($page_info['invalid'])){
			//echo '标题无效 ', $title;
			return null;
		}
		if(isset($page_info['missing'])){
			//echo '页面不存在 ', $title;
			return null;
		}
		$page_id = $page_info['pageid'];
		if(!$page_id or $page_id < 0){
			//echo '页面ID异常 ', $page_id;
			return null;
		}
		return $this->page($page_info);
	}

	/**
	 * 从输入的id获取wiki page,这个默认不跟随redirect
	 *
	 * @param int $pageid
	 * @param bool $follow_redirect
	 * @return WikiPage|null
	 * @throws Exception
	 */
	public function getPageByPageId(int $pageid, bool $follow_redirect = false) : ?WikiPage
	{
		$params = ['pageids'=> $pageid];
		if($follow_redirect){
			$params['redirects'] = 'yes';
		}

		$result = $this->query($params, 'query');

		$page_info = current($result['pages']);
		if(!$page_info){
			//echo '查询无结果', $title;
			return null;
		}
		if(isset($page_info['invalid'])){
			//echo '标题无效 ', $title;
			return null;
		}
		if(isset($page_info['missing'])){
			//echo '页面不存在 ', $title;
			return null;
		}
		$page_id = $page_info['pageid'];
		if(!$page_id or $page_id < 0){
			//echo '页面ID异常 ', $page_id;
			return null;
		}
		return $this->page($page_info);
	}

}

