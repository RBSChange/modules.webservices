<?php
/**
 * @package modules.webservices.lib.services
 */
class webservices_ModuleService extends ModuleBaseService
{
	/**
	 * @var webservices_ModuleService
	 */
	private static $instance = null;

	/**
	 * @return webservices_ModuleService
	 */
	public static function getInstance()
	{
		if (is_null(self::$instance))
		{
			self::$instance = self::getServiceClassInstance(get_class());
		}
		return self::$instance;
	}

	/**
	 * Return the content of WSDL
	 * @param string $className
	 * @return string
	 */
	function getWsdl($className)
	{
		return f_util_FileUtils::read($this->getWsdlPath($className));
	}

	/**
	 * Return the file path of cached WSDL
	 * @param string $className
	 * @return string
	 */
	function getWsdlPath($className)
	{
		if (Framework::inDevelopmentMode())
		{
			$path = f_util_FileUtils::buildCachePath("wsdl", str_replace("_", DIRECTORY_SEPARATOR, $className));
			$wsdl = $this->generateWsdl($className);
			f_util_FileUtils::writeAndCreateContainer($path, $wsdl, f_util_FileUtils::OVERRIDE);
			return $path;
		}
		return f_util_FileUtils::buildChangeBuildPath("wsdl", str_replace("_", DIRECTORY_SEPARATOR, $className));
	}
	
	/**
	 * Compile all WSLD defined in the configuration
	 */
	function compileWsdls()
	{
		$modules = Framework::getConfiguration("modules");
		foreach ($modules as $moduleEntry)
		{
			if (isset($moduleEntry["webservices"]))
			{
				foreach ($moduleEntry["webservices"] as $webserviceName => $webserviceEntry)
				{
					if (!isset($webserviceEntry["class"]) || !f_util_ClassUtils::classExists($webserviceEntry["class"]))
					{
						throw new Exception("Invalid webservice class : ".$webserviceEntry["class"]);
						
					}
					$className = $webserviceEntry["class"];
					$path = f_util_FileUtils::buildChangeBuildPath("wsdl", str_replace("_", DIRECTORY_SEPARATOR, $className));
					$wsdl = $this->generateWsdl($className);
					f_util_FileUtils::writeAndCreateContainer($path, $wsdl, f_util_FileUtils::OVERRIDE);
				}
			}
		}
	}

	/**
	 * Return the content on generated WSDL
	 * @param string $className
	 * @return string
	 */
	public function generateWsdl($className)
	{
		$class = new ReflectionClass($className);
		if (!$class->implementsInterface("webservices_WebService"))
		{
			throw new Exception("$className does not implement webservices_WebService interface");
		}
		$baseWsdlPath = f_util_FileUtils::buildWebeditPath("modules", "webservices", "wsdefs", "WebServiceBase.wsdl");
		$baseWsdl = f_util_FileUtils::read($baseWsdlPath);

		$classInfo = explode("_", $className);
		$moduleName = $classInfo[0];
		$serviceName = strtolower(f_util_ArrayUtils::lastElement($classInfo));
		if (f_util_StringUtils::endsWith($serviceName, "webservice"))
		{
			$serviceName = substr($serviceName, 0, -10);
		}

		$targetNameSpace = Framework::getBaseUrl()."/ws/".$moduleName."/".$serviceName;
		$baseWsdl = str_replace("TARGET_NAME_SPACE", $targetNameSpace, $baseWsdl);
		$baseWsdl = str_replace("MY_SERVICE", $serviceName, $baseWsdl);
		$baseWsdl = str_replace("MY_MODULE", $moduleName, $baseWsdl);
		$baseWsdl = str_replace("MY_FQDN", Framework::getBaseUrl(), $baseWsdl);

		$wsdl = f_util_DOMUtils::fromString($baseWsdl);
		$wsdl->registerNamespace("tns", $targetNameSpace);
		$wsdl->registerNamespace("soap", "http://schemas.xmlsoap.org/wsdl/soap/");
		//$wsdl->registerNamespace("soapenc", "http://schemas.xmlsoap.org/soap/encoding/");
		$wsdl->registerNamespace("wsdl", "http://schemas.xmlsoap.org/wsdl/");
		$wsdl->registerNamespace("xsd", "http://www.w3.org/2001/XMLSchema");

		$bindingElem = $wsdl->findUnique("wsdl:binding");
		$portTypeElem = $wsdl->findUnique("wsdl:portType");
		
		foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
		{
			if ($method->getName() === 'getWsdlTypesService')
			{
				continue;
			}
			
			// Binding
			$operationElem = $wsdl->createElement('wsdl:operation');
			$operationElem->setAttribute("name", $method->getName());
			$soapOperation =  $wsdl->createElement('soap:operation');
			$soapOperation->setAttribute("soapAction", $targetNameSpace."/".$method->getName());
			$soapOperation->setAttribute("style", "document");
			$operationElem->appendChild($soapOperation);

			$wsdlInput = $wsdl->createElement('wsdl:input');
			$soapBody = $wsdl->createElement('soap:body');
			$soapBody->setAttribute("use", "literal");
			$wsdlInput->appendChild($soapBody);
			$operationElem->appendChild($wsdlInput);

			$wsdlOutput = $wsdl->createElement('wsdl:output');
			$soapBody = $wsdl->createElement('soap:body');
			$soapBody->setAttribute("use", "literal");
			$wsdlOutput->appendChild($soapBody);
			$operationElem->appendChild($wsdlOutput);

			$bindingElem->appendChild($operationElem);

			// PortType
			$operationElem = $wsdl->createElement('wsdl:operation');
			$operationElem->setAttribute("name", $method->getName());

			$input = $wsdl->createElement('wsdl:input');
			$input->setAttribute("message", "tns:".$method->getName()."Request");
			$operationElem->appendChild($input);

			$output = $wsdl->createElement('wsdl:output');
			$output->setAttribute("message", "tns:".$method->getName()."Response");
			$operationElem->appendChild($output);

			$portTypeElem->appendChild($operationElem);

			// Request message
			$requestMessageElem = $wsdl->createElement('wsdl:message');
			$requestMessageElem->setAttribute("name", $method->getName()."Request");
			$requestMessagePartElem = $wsdl->createElement('wsdl:part');
			$requestMessagePartElem->setAttribute("name", "parameters");
			$requestMessagePartElem->setAttribute("element", "tns:".$method->getName()."Params");
			$requestMessageElem->appendChild($requestMessagePartElem);
			$wsdl->documentElement->appendChild($requestMessageElem);
								
			// Response message
			$responseMessageElem = $wsdl->createElement('wsdl:message');
			$responseMessageElem->setAttribute("name", $method->getName()."Response");
			$responseMessagePartElem = $wsdl->createElement('wsdl:part');
			$responseMessagePartElem->setAttribute("name", "parameters");
			$responseMessagePartElem->setAttribute("element", "tns:".$method->getName()."Response");
			$responseMessageElem->appendChild($responseMessagePartElem);
			$wsdl->documentElement->appendChild($responseMessageElem);
		}
		
		$typeList = $this->getServiceTypeDefinitions($className);
		$typeList->addInSchema($wsdl);
		return $wsdl->saveXML();
	}
	
	/**
	 * Return the definition of all type used by the service
	 * @param string $className
	 * @return webservices_WsdlTypes
	 */
	public function getServiceTypeDefinitions($className)
	{
		$path = f_util_FileUtils::buildCachePath("wsdl", str_replace("_", DIRECTORY_SEPARATOR, $className) . '.types');
		if (!file_exists($path) || Framework::inDevelopmentMode())
		{
			$types = $this->generateServiceTypeDefinitions($className);
			f_util_FileUtils::writeAndCreateContainer($path, serialize($types), f_util_FileUtils::OVERRIDE);
		}
		else
		{
			$types = unserialize(f_util_FileUtils::read($path));
		}
		
		return $types;
	}
	
	/**
	 * Generate the definition of all type used by the service
	 * @param string $className
	 * @return webservices_WsdlTypes
	 */
	public function generateServiceTypeDefinitions($className)
	{
		$class = new ReflectionClass($className);
		if (!$class->implementsInterface("webservices_WebService"))
		{
			throw new Exception("$className does not implement webservices_WebService interface");
		}
		
		$typeList = new webservices_WsdlTypes($className);	
		foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
		{
			if ($method->getName() === 'getWsdlTypesService')
			{
				continue;
			}
			
			$type = webservices_XsdComplexFunction::FUNCTIONINFO($method->getName()."Params", 'in');
			foreach ($method->getParameters() as $parameter)
			{
				$paramName = $parameter->getName();			
				$phpType = f_util_ClassUtils::getParamType($method, $paramName);
				$para = $typeList->createXsdElement($phpType);
				$para->setMinOccurs(1);	
				$type->addXsdElement($paramName, $para);
			}
			$typeList->addComplexType($type);

			$type = webservices_XsdComplexFunction::FUNCTIONINFO($method->getName()."Response", 'out');
			$phpType = f_util_ClassUtils::getReturnType($method);
			$para = $typeList->createXsdElement($phpType);
			if ($para === null)
			{
				$para = webservices_XsdElement::STRING();
				$para->setMinOccurs(1);
			}
			$type->addXsdElement($method->getName()."Result", $para);
			$typeList->addComplexType($type);
		}
		return $typeList;
	}
}