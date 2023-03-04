<?php
declare(strict_types=1);

namespace mFramework;

use ArrayObject;

/**
 * mFramework - Map
 *
 * Map是通用的key-value数据容器。旨在提供一种灵活方便的数据封装与访问方式。
 * 对于容器内key为 'key' ，value为$value的数据，允许用3种不同的方式访问：
 *
 * 方法1：
 * $map->set('key', $value);
 * $map->batchSet($array);
 * $var = $map->get('key'); //return $value
 * $map->has('key'); //return true
 * $map->del('key');
 * 在这个方式下，试图get不存在的值也是允许的：
 * $var = $map->get('nonexistent_key'); //return null
 *
 * 方法2：
 * $map['key'] = $value;
 * $var = $map['key'];
 * isset($map['key']);
 * unset($map['key']);
 *
 * 方法3：
 * $map->key = $value;
 * $var = $map->key;
 * isset($map->key);
 * unset($map->key);
 *
 * 方法 2 和 3 下试图读取不存在的索引一样会引发报错。
 *
 * Map的所有存取方式最终均实际通过 offset*() 系列方法执行具体存取，
 * 因此如果需要扩展时只需要处理这系列即可。
 *
 * 用ArrayObject做基础的原因：
 * 1. 有exchangeArray()方法。
 * 2. Map不需要成为一个 Iterator 。
 *
 */
class Map extends ArrayObject
{

	/**
	 * 接受array或者object做参数。
	 *
	 * @param array|object|null $data 输入的数据。
	 */
	public function __construct(array|object|null $data = null)
	{
		parent::__construct($data ?? [], self::ARRAY_AS_PROPS);
	}

	/**
	 * 允许以$map->get($key)的方式来获取数据
	 * 不存在的 key 返回 null
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function get(string $key): mixed
	{
		return $this->offsetExists($key) ? $this->offsetGet($key) : null;
	}

	/**
	 * 允许以 $map->set($key, $value) 的方式来存入数据
	 *
	 * @param string $key
	 * @param mixed $value
	 */
	public function set(string $key, mixed $value = null): void
	{
		$this->offsetSet($key, $value);
	}

	/**
	 * @param iterable $data
	 */
	public function batchSet(iterable $data)
	{
		foreach($data as $key => $value){
			$this->offsetSet($key, $value);
		}
	}

	/**
	 * 某个数据是否存在？
	 *
	 * @param mixed $key
	 * @return bool
	 */
	public function has(string $key): bool
	{
		return $this->offsetExists($key);
	}

	/**
	 * 删除某个值。
	 *
	 * @param mixed $key
	 * @return void
	 */
	public function delete(string $key): void
	{
		$this->offsetUnset($key);
	}
}
