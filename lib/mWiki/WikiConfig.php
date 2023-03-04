<?php
namespace mWiki;
/**
 * Class WikiConfig
 *
 */
class WikiConfig
{

	/**
	 * @param string $endpoint wiki api endpoint
	 * @param string $username username of bot
	 * @param string $password password for bot user
	 * @param bool $is_bot true for bot account(with 'bot' right), false for user account(with 'user' right)
	 * @param int $max_con number of concurrent requests on this wiki
	 * @param int $con_timeout connection timeout, in seconds
	 * @param int $trans_timeout transporting timeout, in seconds
	 * @param array $curl_options Other curl options.
	 */
	public function __construct(
		public readonly string $endpoint,
		public readonly string $username,
		public readonly string $password,
		public readonly bool $is_bot,
		public readonly int $max_con = 6,
		public readonly int $con_timeout = 10,
		public readonly int $trans_timeout = 180,
		public readonly array $curl_options = [],
	)
	{}

}