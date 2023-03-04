<?php
namespace mHttp;

use Countable;
use CurlHandle;
use CurlMultiHandle;
use CurlShareHandle;
use JetBrains\PhpStorm\Pure;
use SplObjectStorage;
use SplPriorityQueue;

/**
 * 基于CURL的http客户端工具类
 * 针对单个站点的访问请求
 * 支持并发连接，默认使用 share handle，这样同一个client下的所有handle共享相同的cookie等信息
 *
 */
Class Client implements Countable
{
	private const CURLE_MSG = [
		CURLE_OK => 'OK',
		CURLE_UNSUPPORTED_PROTOCOL => 'UNSUPPORTED_PROTOCOL',
		CURLE_FAILED_INIT => 'FAILED_INIT',
		CURLE_URL_MALFORMAT => 'URL_MALFORMAT',
		CURLE_URL_MALFORMAT_USER => 'URL_MALFORMAT_USER',
		CURLE_COULDNT_RESOLVE_PROXY => 'COULDNT_RESOLVE_PROXY',
		CURLE_COULDNT_RESOLVE_HOST => 'COULDNT_RESOLVE_HOST',
		CURLE_COULDNT_CONNECT => 'COULDNT_CONNECT',
		CURLE_FTP_WEIRD_SERVER_REPLY => 'FTP_WEIRD_SERVER_REPLY',
		CURLE_FTP_ACCESS_DENIED => 'FTP_ACCESS_DENIED',
		CURLE_FTP_USER_PASSWORD_INCORRECT => 'FTP_USER_PASSWORD_INCORRECT',
		CURLE_FTP_WEIRD_PASS_REPLY => 'FTP_WEIRD_PASS_REPLY',
		CURLE_FTP_WEIRD_USER_REPLY => 'FTP_WEIRD_USER_REPLY',
		CURLE_FTP_WEIRD_PASV_REPLY => 'FTP_WEIRD_PASV_REPLY',
		CURLE_FTP_WEIRD_227_FORMAT => 'FTP_WEIRD_227_FORMAT',
		CURLE_FTP_CANT_GET_HOST => 'FTP_CANT_GET_HOST',
		CURLE_FTP_CANT_RECONNECT => 'FTP_CANT_RECONNECT',
		CURLE_FTP_COULDNT_SET_BINARY => 'FTP_COULDNT_SET_BINARY',
		CURLE_PARTIAL_FILE => 'PARTIAL_FILE',
		CURLE_FTP_COULDNT_RETR_FILE => 'FTP_COULDNT_RETR_FILE',
		CURLE_FTP_WRITE_ERROR => 'FTP_WRITE_ERROR',
		CURLE_FTP_QUOTE_ERROR => 'FTP_QUOTE_ERROR',
		CURLE_HTTP_NOT_FOUND => 'HTTP_NOT_FOUND',
		CURLE_WRITE_ERROR => 'WRITE_ERROR',
		CURLE_MALFORMAT_USER => 'MALFORMAT_USER',
		CURLE_FTP_COULDNT_STOR_FILE => 'FTP_COULDNT_STOR_FILE',
		CURLE_READ_ERROR => 'READ_ERROR',
		CURLE_OUT_OF_MEMORY => 'OUT_OF_MEMORY',
		CURLE_OPERATION_TIMEOUTED => 'OPERATION_TIMEOUTED',
		CURLE_FTP_COULDNT_SET_ASCII => 'FTP_COULDNT_SET_ASCII',
		CURLE_FTP_PORT_FAILED => 'FTP_PORT_FAILED',
		CURLE_FTP_COULDNT_USE_REST => 'FTP_COULDNT_USE_REST',
		CURLE_FTP_COULDNT_GET_SIZE => 'FTP_COULDNT_GET_SIZE',
		CURLE_HTTP_RANGE_ERROR => 'HTTP_RANGE_ERROR',
		CURLE_HTTP_POST_ERROR => 'HTTP_POST_ERROR',
		CURLE_SSL_CONNECT_ERROR => 'SSL_CONNECT_ERROR',
		CURLE_FTP_BAD_DOWNLOAD_RESUME => 'FTP_BAD_DOWNLOAD_RESUME',
		CURLE_FILE_COULDNT_READ_FILE => 'FILE_COULDNT_READ_FILE',
		CURLE_LDAP_CANNOT_BIND => 'LDAP_CANNOT_BIND',
		CURLE_LDAP_SEARCH_FAILED => 'LDAP_SEARCH_FAILED',
		CURLE_LIBRARY_NOT_FOUND => 'LIBRARY_NOT_FOUND',
		CURLE_FUNCTION_NOT_FOUND => 'FUNCTION_NOT_FOUND',
		CURLE_ABORTED_BY_CALLBACK => 'ABORTED_BY_CALLBACK',
		CURLE_BAD_FUNCTION_ARGUMENT => 'BAD_FUNCTION_ARGUMENT',
		CURLE_BAD_CALLING_ORDER => 'BAD_CALLING_ORDER',
		CURLE_HTTP_PORT_FAILED => 'HTTP_PORT_FAILED',
		CURLE_BAD_PASSWORD_ENTERED => 'BAD_PASSWORD_ENTERED',
		CURLE_TOO_MANY_REDIRECTS => 'TOO_MANY_REDIRECTS',
		CURLE_UNKNOWN_TELNET_OPTION => 'UNKNOWN_TELNET_OPTION',
		CURLE_TELNET_OPTION_SYNTAX => 'TELNET_OPTION_SYNTAX',
		CURLE_OBSOLETE => 'OBSOLETE',
		CURLE_GOT_NOTHING => 'GOT_NOTHING',
		CURLE_SSL_ENGINE_NOTFOUND => 'SSL_ENGINE_NOTFOUND',
		CURLE_SSL_ENGINE_SETFAILED => 'SSL_ENGINE_SETFAILED',
		CURLE_SEND_ERROR => 'SEND_ERROR',
		CURLE_RECV_ERROR => 'RECV_ERROR',
		CURLE_SHARE_IN_USE => 'SHARE_IN_USE',
		CURLE_SSL_CERTPROBLEM => 'SSL_CERTPROBLEM',
		CURLE_SSL_CIPHER => 'SSL_CIPHER',
		CURLE_SSL_CACERT => 'SSL_CACERT',
		CURLE_BAD_CONTENT_ENCODING => 'BAD_CONTENT_ENCODING',
		CURLE_LDAP_INVALID_URL => 'LDAP_INVALID_URL',
		CURLE_FILESIZE_EXCEEDED => 'FILESIZE_EXCEEDED',
		CURLE_FTP_SSL_FAILED => 'FTP_SSL_FAILED',
		CURLE_SSH => 'SSH',
		92 => 'HTTP2_STREAM', //php中尚未定义，但libcurl中有了，所以有时候会返回这个
	];

	/**
	 * 默认公用选项
	 */
	private array $options = [
		CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:106.0) Gecko/20100101 Firefox/106.0',
		CURLOPT_AUTOREFERER => true,
		CURLOPT_FOLLOWLOCATION =>true,
		CURLOPT_MAXREDIRS => 30,
		CURLOPT_SSL_VERIFYPEER =>  false,
		CURLOPT_ENCODING => '', //接受gzip等。
		CURLOPT_COOKIEFILE => "", //启动内存内cookie管理，而且必须这么指定一下才能搞定跨请求传递cookie（同一个curl客户端内）
		CURLOPT_HTTPGET => 1, //默认使用GET
		//CURLOPT_HEADER => true,
	];

	private array $config = [
		'max_con' => 6,
		'con_timeout' => 20,
		'trans_timeout' => 900
	];

	/**
	 * @var array 默认的http header
	 */
	private array $headers = [];

	/**
	 * 存放curl multi handle
	 * @var CurlMultiHandle
	 */
	private CurlMultiHandle $handle;
	/**
	 * 存放curl share handle
	 * @var CurlShareHandle
	 */
	private CurlShareHandle $share_handle;

	/**
	 * 优先队列。保存所有待跑的请求
	 * @var SplPriorityQueue
	 */
	private SplPriorityQueue $queue;

	/**
	 * 记录在跑请求。
	 * PHP 8 开始curl handle变成了Object
	 * @var SplObjectStorage
	 */
	private SplObjectStorage $running;

	/**
	 * 初始化
	 * @param array|null $config 对client本身的配置。 和默认的config 进行 merge
	 * @param array|null $options 默认options，会传递给名下所有的$request 和默认的 options 进行 merge
	 */
	public function __construct(?array $config = null, ?array $options = null)
	{
		$this->handle = curl_multi_init();
		$this->share_handle = curl_share_init();
		curl_share_setopt($this->share_handle, CURLSHOPT_SHARE, CURL_LOCK_DATA_COOKIE);
		$this->queue = new SplPriorityQueue();
		$this->running = new SplObjectStorage();
		$config and $this->setConfig($config);
		$options and $this->setOptions($options);
	}

	/**
	 * 清理
	 */
	public function __destruct()
	{
		curl_multi_close($this->handle);
		curl_share_close($this->share_handle);
	}

	/**
	 * 添加一个请求进入
	 *
	 * @param Request $request
	 * @param int $priority 优先级
	 * @return self
	 */
	public function addRequest(Request $request, int $priority = 10):self
	{
		$this->queue->insert($request, $priority);
		return $this;
	}

	/**
	 * 跑起来！
	 * @throws Exception
	 */
	public function execute()
	{
		$curl_multi_handle = $this->handle;
		do{
			$this->loadRequestsFromQueue(); //填充
			//先执行。 从curl 7.20开始不需要用do循环了
			$multi_handle_status = curl_multi_exec($curl_multi_handle, $active);
			if($multi_handle_status != CURLM_OK){
				throw new Exception('CURL ERROR, CURLM CODE:'.$multi_handle_status);
			}
			//一轮执行之后检查一下有没有完成的，有则处理。一次处理一个。
			$completed = curl_multi_info_read($curl_multi_handle);
			if($completed){
				$curl_handle = $completed['handle'];
				// '完成 ';
				$this->processCompleted($curl_handle, $completed['result']);
				$this->remove($curl_handle);
				usleep(10);
			}
			else{
				usleep(200);
			}
		}while($active or count($this->running) or !$this->queue->isEmpty());
	}


	/**
	 * 把队列中的请求填充到在跑队列去。
	 */
	private function loadRequestsFromQueue()
	{
		$share_handle = $this->share_handle;
		while(count($this->running) < $this->getConfig()['max_con']){
			//echo '添加', count($this->running), '/',$this->getConfig()['max_con'], '队列',count($this->queue);
			if($this->queue->isEmpty()){
				//echo '队列空了 ';
				break;
			}
			/** @var Request $request */
			$request = $this->queue->extract();
			$curl_handle = curl_init();
			$options = $this->buildOptions($request);
			curl_setopt_array($curl_handle, $options);
			$request['curl_options'] = $options;
			curl_setopt($curl_handle, CURLOPT_SHARE, $share_handle);
			curl_multi_add_handle($this->handle, $curl_handle);
			$request['curl_handle'] = $curl_handle;
			$request->client = $this;
			$this->running[$curl_handle] = $request;
		}
	}

	/**
	 * 同步方式直接发送一个request，直接返回结果，解决异步的各种问题
	 * 不负责处理各种request中的请求异常问题，需要调用方自己处理。
	 * @param Request $request
	 * @return bool|string
	 */
	public function sendSyncRequest(Request $request): bool|string
	{
		$share_handle = $this->share_handle;
		$curl_handle = curl_init();
		$options = $this->buildOptions($request);
		curl_setopt_array($curl_handle, $options);
		$request['curl_options'] = $options;
		curl_setopt($curl_handle, CURLOPT_SHARE, $share_handle);
		$result = curl_exec($curl_handle);
		curl_close($curl_handle);
		return $result;
	}

	/**
	 * 某个请求运行结束了之后拿掉。
	 * @param CurlHandle $curl_handle
	 */
	private function remove(CurlHandle $curl_handle)
	{
		unset($this->running[$curl_handle]);
		curl_multi_remove_handle($this->handle, $curl_handle);
		curl_close($curl_handle);
	}


	/**
	 * Build individual cURL options for a request
	 * @param Request $request
	 * @return array
	 */
	#[Pure] private function buildOptions(Request $request): array
	{
		$options = ($request->options ?? []) + $this->getOptions();
		$url = $request->url;
		$data = $request->data;
		$method = $request->method;
		if($data){
			// enable POST method and set POST parameters
			if($method == Method::POST) {
				$options[CURLOPT_POST] = 1;
				$options[CURLOPT_POSTFIELDS] = http_build_query($data);
			}
			else{//GET
				$url .= strpos($url, '?')?'&':'?';
				$url .= http_build_query($data,  '', '&',  PHP_QUERY_RFC3986);
			}
		}
		//url
		$options[CURLOPT_URL] = $url;

		$headers = ($request->header ?? []) + $this->getHeaders();
		if($headers) {
			$options[CURLOPT_HTTPHEADER] = $headers;
		}

		//the below will overide the corresponding default or individual options
		$options[CURLOPT_RETURNTRANSFER] = true;
		$options[CURLOPT_NOSIGNAL] = 1;
		//用配置覆盖
		$config = $this->getConfig();
		$options[CURLOPT_CONNECTTIMEOUT] = $config['con_timeout']; //minimum of 1 second
		$options[CURLOPT_TIMEOUT] = $config['trans_timeout'];
		return $options;
	}

	/**
	 * @param CurlHandle $ch curl handle
	 * @param int $result
	 */
	private function processCompleted(CurlHandle $ch, int $result)
	{
		$request_info = curl_getinfo($ch);
		$request_info['curle'] = $result;
		$request_info['curle_msg'] = self::CURLE_MSG[$result]??("unknown(".$result.")");

		$request = $this->running[$ch];

		if(curl_errno($ch) !== 0 || intval($request_info['http_code']) !== 200){ // if server responded with http error
			$response = false;
		}else{ // sucessful response
			$response = curl_multi_getcontent($ch);
		}

		//get request info
		$options = $request['curl_options'];

		if($response && isset($options[CURLOPT_HEADER])) {
			$k = intval($request_info['header_size']);
			$request_info['response_header'] = substr($response, 0, $k);
			$response = substr($response, $k);
		}

		$request->onComplete($response, $request_info);
	}

	/**
	 * @return array
	 */
	public function getOptions(): array
	{
		return $this->options;
	}

	/**
	 * @param array $options
	 * @return Client
	 */
	public function setOptions(array $options): Client
	{
		$this->options = $options + $this->options;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getConfig(): array
	{
		return $this->config;
	}

	/**
	 * @param array $config
	 * @return Client
	 */
	public function setConfig(array $config): Client
	{
		$this->config = $config + $this->config;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getHeaders(): array
	{
		return $this->headers;
	}

	/**
	 * @param array $headers
	 * @return Client
	 */
	public function setHeaders(array $headers): Client
	{
		$this->headers = $headers;
		return $this;
	}

	/**
	 * @return int
	 */
	#[Pure] public function count():int
	{
		return count($this->queue);
	}
}
