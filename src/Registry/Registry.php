<?php

namespace Zixsihub\JsonRpc\Registry;

use ReflectionMethod;
use ReflectionParameter;
use RuntimeException;
use Zixsihub\JsonRpc\Data\RequestData;
use Zixsihub\JsonRpc\Exception\JsonRpcException;

class Registry implements RegistryInterface
{

	/** @var array */
	private $classMap = [];

	/** @var array */
	private $instancesMap = [];

	/** @var array */
	private $reflectionMap = [];

	/**
	 * @param string $name
	 * @param string|object $instance
	 * @return RegistryInterface
	 */
	public function add(string $name, $instance): RegistryInterface
	{
		if (!is_string($instance) && !is_object($instance)) {
			throw new RuntimeException("Invalid class definition");
		}

		$this->classMap[$name] = $instance;

		if (is_object($instance)) {
			$this->instancesMap[$name] = $instance;
		}

		return $this;
	}
	
	/**
	 * @param RequestData $request
	 * @return mixed
	 */
	public function invoke(RequestData $request)
	{
		$instance = $this->getInstance($request->getMethodObjectName());
		$method = $this->getReflectionMethod($instance, $request);
		
		return  $method->invokeArgs($instance, $request->getParams());
	}
	
	/**
	 * @param string $namespace
	 * @return bool
	 */
	protected function has(string $namespace): bool
	{
		return array_key_exists($namespace, $this->classMap);
	}

	/**
	 * @param string $namespace
	 * @return object
	 */
	protected function getInstance(string $namespace): object
	{
		if (array_key_exists($namespace, $this->instancesMap)) {
			return $this->instancesMap[$namespace];
		}

		if (!$this->has($namespace)) {
			throw new JsonRpcException('Method not found', -32601);
		}

		$this->instancesMap[$namespace] = 
			is_string($this->classMap[$namespace]) 
			? new $this->classMap[$namespace] 
			: $this->classMap[$namespace];

		return $this->instancesMap[$namespace];
	}
	
	/**
	 * @param object $instance
	 * @param RequestData $data
	 * @return ReflectionMethod
	 * @throws JsonRpcException
	 */
	protected function getReflectionMethod(object $instance, RequestData $data): ReflectionMethod
	{
		if (!method_exists($instance, $data->getMethodActionName())) {
			throw new JsonRpcException('Method not found', -32601);
		}
		
		if (!array_key_exists($data->getMethodActionName(), $this->reflectionMap)) {
			$this->reflectionMap[$data->getMethod()] = new ReflectionMethod($instance, $data->getMethodActionName());
		}
		
		$reflectionMethod = $this->reflectionMap[$data->getMethod()];
		
		/** @var ReflectionParameter $param */
		foreach ($reflectionMethod->getParameters() as $param) {
			if (
				$param->isDefaultValueAvailable()
				|| array_key_exists($param->getName(), $data->getParams())
			) {
				continue;
			}

			throw new JsonRpcException('Invalid params', -32602,  $param->getName() . ' not found');
		}
		
		return $reflectionMethod;
	}

}