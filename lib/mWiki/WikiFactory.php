<?php
namespace mWiki;
/**
 * Class WikiFactory
 *
 */
class WikiFactory
{

	private static array $wikis;

	/**
	 * @param string $name
	 * @param WikiConfig $config
	 * @return void
	 * @throws Exception
	 */
	public static function register(string $name, WikiConfig $config):void
	{
		if(self::$wikis[$name]??null){
			throw new Exception('A wiki config with the name "'.$name.'" has been registered.');
		}
		self::$wikis[$name] = $config;
	}

	public static function get($name):Wiki
	{
		if(!(self::$wikis[$name]??null)){
			throw new Exception('Wiki config with the name "'.$name.'" has not been registered.');
		}
		if(self::$wikis[$name] instanceof WikiConfig){
			self::$wikis[$name] = new Wiki($name, self::$wikis[$name]);
		}
		return self::$wikis[$name];
	}

}