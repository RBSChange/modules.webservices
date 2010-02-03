<?php
/**
 * Class used by webservices_ServerAction that wraps your
 * webservices_WebService class to generate proper responses
 */
class webservices_WebServiceProxy
{
	// OK, should not normally be static but PHP < 5.2.0 does not provide SoapServer::setObject() so ...
	public static $serviceClassName;
	
	/**
	 * @var webservices_WebService
	 */
	private $service;
	
	function __construct()
	{
		$this->service = new self::$serviceClassName();
	}
	
	function __call($name, $arguments)
	{
		$paramsObj = $arguments[0];
		$class = new ReflectionClass($this->service);
		$args = array();
		foreach ($class->getMethod($name)->getParameters() as $parameter)
		{
			$args[] = $paramsObj->{$parameter->getName()};
		}
		$res = f_util_ClassUtils::callMethodArgsOn($this->service, $name, $args);
		/*if ($res === null)
		{
			return null;
		}*/
		return array($name."Result" => $res);
	}
}