<?php
class webservices_ServiceJSONProxy
{
	/**
	 * @var webservices_WebService
	 */
	private $service;
	
	/**
	 * @var webservices_WsdlTypes
	 */
	private $typeList = null;
	
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
			$typeList = $this->getTypeList();
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
	
	/**
	 * @return webservices_WsdlTypes
	 */
	private function getTypeList()
	{
		if ($this->typeList === null)
		{
			if (f_util_ClassUtils::methodExists($this->service, 'getWsdlTypes'))
			{
				$this->typeList = $this->service->getWsdlTypes();
			}
			else
			{
				$this->typeList = webservices_ModuleService::getInstance()->getServiceTypeDefinitions(get_class($this->service));
			}	
		}
		return $this->typeList;
	}
	
	
	public function getMethods()
	{
		$result = array();
		foreach ($this->getTypeList()->getTypes() as $wsdlType) 
		{
			if ($wsdlType->getDirection() === 'in')
			{
				$name = substr($wsdlType->getType(), 0, strlen($wsdlType->getType()) -6);
				$request = array('method' => $name);
				$arguments = array();
				foreach ($wsdlType->getXsdElementArray() as $propName => $propType) 
				{
					$arguments[$propName] = $this->getDefaultValue($propType, $propName);
				}
				if (count($arguments))
				{
					$request['arguments'] = $arguments;
				}
				$result[$name] = JsonService::getInstance()->encode($request);
			}
		}
		return $result;
	}
	
	private function getDefaultValue($propType, $name = null)
	{
		if ($propType instanceof webservices_XsdComplexArray)
		{
			return array($this->getDefaultValue($propType->getItem()));
		}
		else if ($propType instanceof webservices_XsdComplex) 
		{
			$result = array();
			foreach ($propType->getXsdElementArray() as $objectName => $subType)
			{
				$result[$objectName] = $this->getDefaultValue($subType, $objectName);
			}
			return $result;
		}
		else
		{
			switch ($propType->getType())
			{
				case 'int':
					if ($name === 'id')
					{
						return -1;
					}
					return 0;
				case 'boolean':
					return false;
				case 'float':
					return 0.0;
				case 'dateTime':
					if ($name === 'startpublicationdate')
					{
						return $propType->formatValue(date_Calendar::getInstance()->add(date_Calendar::MONTH, -1)->__toString());
					}
					else if ($name === 'endpublicationdate')
					{
						return $propType->formatValue(date_Calendar::getInstance()->add(date_Calendar::MONTH, 1)->__toString());
					}
					return $propType->formatValue(date_Calendar::getInstance()->__toString());
				case 'string':
					if ($name === 'lang')
					{
						return RequestContext::getInstance()->getDefaultLang();
					}
					else if ($name === 'publicationstatus')
					{
						return 'ACTIVE';
					}
					return "TEXT";
			}
		}
		return null;		
	}
}