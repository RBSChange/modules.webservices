<?php
class webservices_ServiceJSONProxy
{
	/**
	 * @var webservices_WebService
	 */
	private $service;
	
	function __construct($serviceClassName)
	{
		$this->service = new $serviceClassName();
	}
	
	function handle($method, $arguments)
	{
		$class = new ReflectionClass($this->service);
		$args = array();
		foreach ($class->getMethod($method)->getParameters() as $parameter)
		{
			if (isset($arguments[$parameter->getName()]))
			{
				$args[] = $arguments[$parameter->getName()];
			}
			else
			{
				$args[] = null;
			}
		}
		$res = f_util_ClassUtils::callMethodArgsOn($this->service, $method, $args);
		if ($res != null)
		{
			if (f_util_ClassUtils::methodExists($this->service, 'getWsdlTypes'))
			{
				$typeList = $this->service->getWsdlTypes();
			}
			else
			{
				$typeList = webservices_ModuleService::getInstance()->getServiceTypeDefinitions(get_class($this->service));
			}
			$responseType = $typeList->getType($method."Response");
			$resultTypeName = $responseType->getXsdElement($method."Result")->getType();
			$resultType = $typeList->getType($resultTypeName);
			if ($resultType === null)
			{
				$resultType = webservices_WsdlTypes::getSimpleType($resultTypeName);
			}
			$res = $resultType->formatValue($res);
		}
		echo JsonService::getInstance()->encode(array('result' => $res));
	}
}