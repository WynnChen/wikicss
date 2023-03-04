<?php
declare(strict_types=1);

namespace mFramework;

/**
 * mFramework - ClassLoader
 *
  * 需要设定 类=>文件 的直接映射，或设定 namespace前缀 => 处理函数的关系。
 * 直接映射优先级大于namespace前缀。
 *
 * 对于 namespace前缀，提供了基于PSR4的前缀处理函数生成器。
 *
 * 整合用法，一般初始化如下：
 * ClassLoader::getInstance()->addClassFiles()->addPrefixHandles()->register();
 * 可以在后继追加设置：
 * ClassLoader::getInstance()->addPrefixHandles();
 *
 * ClassLoader不负责处理格式验证等问题，调用方自行保证
 *
 */
class ClassLoader
{
	private static ?self $instance = null;

	final static public function getInstance(): static
	{
		if (!self::$instance) {
			self::$instance = new static();
		}
		return self::$instance;
	}

	protected array $map = array();
	protected array $prefixes = array();

	/**
	 * @codeCoverageIgnore
	 *
	 * 注册到 SPL autoloader 序列中。
	 * SPL autoloader 的注册过程会自行处理重复注册问题，无需额外操作。
	 *
	 * @param bool $prepend 等同于spl_autoload_register()的$prepend参数。
	 * @return bool 注册结果
	 */
	public function register(bool $prepend = true): bool
	{
		return spl_autoload_register([$this,'loadClass'], true, $prepend);
	}

	/**
	 * 从 SPL autoloader 序列中撤销。
	 *
	 * @return bool 操作结果
	 */
	public function unregister(): bool
	{
		return spl_autoload_unregister([$this,'loadClass']);
	}

	/**
	 * 检测本 loader 是否已经注册。
	 *
	 * 注意本方法需要遍历所有已注册loader，复杂度为O(n)。
	 *
	 * @return bool
	 */
	public function isRegistered(): bool
	{
		return in_array([$this,'loadClass'], spl_autoload_functions());
	}

	/**
	 * 一次性指定多个 类 => 文件的映射。
	 *
	 * 类名应当是完全限定类名。
	 * 不检查指定文件名是否有效。
	 * 已存在的条目会跳过。
	 *
	 * 不对内容进行检查，调用方自行处理
	 *
	 * 直接指定的优先级高于namespace前缀
	 *
	 * @param array $map
	 * @param bool $override
	 * @return self $this
	 */
	public function addClassMap(array $map, bool $override = false): self
	{
		if($override){
			$this->map = array_merge($this->map, $map);
		}
		else{
			$this->map += $map;
		}
		return $this;
	}

	/**
	 * 同时为多个 namespace 前缀指定处理方式。
	 *
	 * 重复指定同一个前缀会被忽略。
	 * 前缀识别区分大小写。
	 *
	 *
	 * 处理函数handle的格式：
	 * function(string $relative_class, string $prefix):string
	 * 返回值为对应需要加载的文件名，或 null 。
	 *
	 * @param string $prefix
	 * @param callable $handle
	 * @param bool $prepend 将其加到对应前缀的队列前面还是后面？
	 * @return self $this
	 */
	public function addNamespace(string $prefix, callable $handle,  bool $prepend = false): self
	{
		$prefix = trim($prefix, '\\'); //不带最后的 /， 全局空间的 '\' 映射到 ''；

		if(!isset($this->prefixes[$prefix])){
			$this->prefixes[$prefix] = array();
		}

		if ($prepend) {
			array_unshift($this->prefixes[$prefix], $handle);
		} else {
			$this->prefixes[$prefix][] = $handle;
		}

		return $this;
	}

	/**
	 * 按照给定类名加载对应的类文件。
	 * 类名为解析好的限定名称，前面再加上一个 \ 就是对应的完全限定名称。
	 * 由于这是autoload，不判定是否类已经加载。手工调用本方法时自行注意不要重复加载。
	 *
	 * 除了负责加载类文件之外，还会做某些特定初始化操作，例如 database model 的初始化.
	 *
	 * @param string $class 需要加载的类名。
	 * @throws \ReflectionException
	 */
	public function loadClass(string $class):void
	{
		// 直接显式指定映射的有吗？
		if (isset($this->map[$class])) {
			$this->includeFile($this->map[$class]);
			return;
		}
		// 从后往前逐段测试namespace前缀，检查是否有指定了相应的处理函数
		$prefix = $class;
		while (($pos = strrpos($prefix, '\\')) !== false) {
			$prefix = substr($class, 0, $pos); // 不带有最后的\。
			$relative_class = substr($class, $pos + 1);
			if (isset($this->prefixes[$prefix])) {
				foreach($this->prefixes[$prefix] as $handle){
					if($file = $handle($relative_class, $prefix)){
						$this->includeFile($file);
						return;
					}
				}
			}
		}
		// 落到全局空间
		if (isset($this->prefixes[''])) {
			foreach($this->prefixes[''] as $handle){
				if($file = $handle($class, '')){
					$this->includeFile($file);
					return;
				}
			}
		}
	}

	/**
	 * @codeCoverageIgnore
	 *
	 * 如果文件存在则加载之。
	 * 返回值表示文件是否存在，文件存在不保证加载成功。
	 *
	 * @param string $file
	 *			需要加载的文件名
	 */
	protected function includeFile(string $file):void
	{
		// 基于效率考虑，不额外判定 is_readable()，调用方应该保证这一点。
		// 测试发现 is_readable() 会让速度下降一个数量级。
		// is_file()比file_exists()更快且能过滤掉目录。
		if (is_file($file)) {
			require $file;
		}
	}

	/**
	 * Class Loader 提供的预设 handle 。 在PSR4的基础上加上了对snake name的支持（大致相当于PSR0）。
	 * 指定基础目录，将 $relative_class 中的（部分）\与_替换成目录层级，最后加上.php
	 * 返回值为符合handle要求的 callable 函数。
	 *
	 * 替换的 \ 和 _ 要求之前一个字符不是 \或者_。
	 * 例：a\_b\d__e 处理得到 a/_b/d/_e.php
	 *
	 * @param string $base_dir
	 * @param string $ext
	 * @return callable
	 */
	static public function baseDirHandle(string $base_dir, string $ext = '.php'): callable
	{
		$base_dir = rtrim($base_dir, '/\\');
		return function ($relative_class) use ($base_dir, $ext) {
			// regex中反斜杠双重转义，要4个反斜杠
			$file = preg_replace('/([^\\\\_])[\\\\_]/', '\1' . DIRECTORY_SEPARATOR, $relative_class) . $ext;
			return  $base_dir . DIRECTORY_SEPARATOR . $file;
		};
	}
}

