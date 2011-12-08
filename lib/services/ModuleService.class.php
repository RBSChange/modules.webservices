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
	 * 
	 * @var string
	 */
	private $logFilePath;

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
	
	protected function __construct()
	{
		$this->logFilePath = f_util_FileUtils::buildWebeditPath('log', 'webservices', 'webservices.log');
		if (!file_exists($this->logFilePath))
		{
			f_util_FileUtils::writeAndCreateContainer($this->logFilePath, gmdate('Y-m-d H:i:s')."\t Created");
		}
	}
	
	public function log($stringLine)
	{
		error_log("\n". gmdate('Y-m-d H:i:s')."\t".$stringLine, 3, $this->logFilePath);
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
		$path = f_util_FileUtils::buildChangeBuildPath("wsdl", str_replace("_", DIRECTORY_SEPARATOR, $className) . ".wsdl");
		if (Framework::inDevelopmentMode())
		{
			$wsdl = $this->generateWsdl($className);
			f_util_FileUtils::writeAndCreateContainer($path, $wsdl, f_util_FileUtils::OVERRIDE);
		}
		return $path;
	}
	
	/**
	 * Compile all WSLD defined in the configuration
	 */
	function compileWsdls()
	{
		$wss = webservices_WsService::getInstance()->createQuery()->find();
		foreach ($wss as $ws) 
		{
			$this->compileWsdl($ws->getPhpclass());
		}
	}
	
	/**
	 * @param string $className
	 */
	function compileWsdl($className)
	{
		Framework::info(__METHOD__ . " " . $className);
		
		$path = f_util_FileUtils::buildChangeBuildPath("wsdl", str_replace("_", DIRECTORY_SEPARATOR, $className) . '.types');
		$types = $this->generateServiceTypeDefinitions($className);
		f_util_FileUtils::writeAndCreateContainer($path, serialize($types), f_util_FileUtils::OVERRIDE);
		
		$path = f_util_FileUtils::buildChangeBuildPath("wsdl", str_replace("_", DIRECTORY_SEPARATOR, $className) . ".wsdl");
		$wsdl = $this->generateWsdl($className);
		f_util_FileUtils::writeAndCreateContainer($path, $wsdl, f_util_FileUtils::OVERRIDE);
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
		$serviceName = f_util_ArrayUtils::lastElement($classInfo);
		if (f_util_StringUtils::endsWith($serviceName, "WebService"))
		{
			$serviceName = substr($serviceName, 0, -10);
		}

		$targetNameSpace = Framework::getUIBaseUrl() . "/ws/".$moduleName."/".$serviceName;
		$baseWsdl = str_replace("TARGET_NAME_SPACE", $targetNameSpace, $baseWsdl);
		$baseWsdl = str_replace("MY_SERVICE", $serviceName, $baseWsdl);
		$baseWsdl = str_replace("MY_MODULE", $moduleName, $baseWsdl);
		$baseWsdl = str_replace("MY_FQDN", Framework::getUIBaseUrl(), $baseWsdl);

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
			$methodName = $method->getName();
			if ($methodName === 'getWsdlTypesService' || $methodName === 'getWsdlTypes')
			{
				continue;
			}
			
			// Binding
			$operationElem = $wsdl->createElement('wsdl:operation');
			$operationElem->setAttribute("name", $methodName);
			$soapOperation =  $wsdl->createElement('soap:operation');
			$soapOperation->setAttribute("soapAction", $targetNameSpace."/".$methodName);
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
			$operationElem->setAttribute("name", $methodName);

			$input = $wsdl->createElement('wsdl:input');
			$input->setAttribute("message", "tns:".$methodName."Request");
			$operationElem->appendChild($input);

			$output = $wsdl->createElement('wsdl:output');
			$output->setAttribute("message", "tns:".$methodName."Response");
			$operationElem->appendChild($output);

			$portTypeElem->appendChild($operationElem);

			// Request message
			$requestMessageElem = $wsdl->createElement('wsdl:message');
			$requestMessageElem->setAttribute("name", $methodName."Request");
			$requestMessagePartElem = $wsdl->createElement('wsdl:part');
			$requestMessagePartElem->setAttribute("name", "parameters");
			$requestMessagePartElem->setAttribute("element", "tns:".$methodName."Params");
			$requestMessageElem->appendChild($requestMessagePartElem);
			$wsdl->documentElement->appendChild($requestMessageElem);
								
			// Response message
			$responseMessageElem = $wsdl->createElement('wsdl:message');
			$responseMessageElem->setAttribute("name", $methodName."Response");
			$responseMessagePartElem = $wsdl->createElement('wsdl:part');
			$responseMessagePartElem->setAttribute("name", "parameters");
			$responseMessagePartElem->setAttribute("element", "tns:".$methodName."Response");
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
		$path = f_util_FileUtils::buildChangeBuildPath("wsdl", str_replace("_", DIRECTORY_SEPARATOR, $className) . '.types');
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
			$methodName = $method->getName();
			if ($methodName === 'getWsdlTypesService' || $methodName === 'getWsdlTypes')
			{
				continue;
			}
			$type = webservices_XsdComplexFunction::FUNCTIONINFO($methodName."Params", 'in');
			foreach ($method->getParameters() as $parameter)
			{
				$paramName = $parameter->getName();			
				$phpType = f_util_ClassUtils::getParamType($method, $paramName);
				if (empty($phpType))
				{
					throw new Exception("Invalid parameter type in $className::" . $methodName . " param -> $paramName");
				}
				$para = $typeList->createXsdElement($phpType);
				$para->setMinOccurs(1);	
				$type->addXsdElement($paramName, $para);
			}
			$typeList->addComplexType($type);

			$type = webservices_XsdComplexFunction::FUNCTIONINFO($methodName."Response", 'out');
			$phpType = f_util_ClassUtils::getReturnType($method);
			$para = $typeList->createXsdElement($phpType);
			if ($para === null)
			{
				$para = webservices_XsdElement::STRING();
				$para->setMinOccurs(1);
			}
			$type->addXsdElement($methodName."Result", $para);
			$typeList->addComplexType($type);
		}
		return $typeList;
	}
}