<?php
/**
 * @package modules.webservices.lib.services
 */
class webservices_ModuleService extends ModuleBaseService
{
	/**
	 * Singleton
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

	function getWsdl($className)
	{
		return f_util_FileUtils::read($this->getWsdlPath($className));
	}

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
	 * Class level metas supported:
	 * - wsdlProperties(<className>:<propName1>,<propName2>;<className2>:_minimal_)
	 * - extraWsdlProperties(<className>:<newPropName1>|<newPropType1>,<newPropName2>|<newPropType2>;<className2>...)
	 * @param $className
	 * @return unknown_type
	 */
	function generateWsdl($className)
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

		// wsdlProperties management
		$exposedProperties = array();
		if (f_util_ClassUtils::hasMeta("wsdlProperties", $class))
		{
			foreach (explode(";", f_util_ClassUtils::getMetaValue("wsdlProperties", $class)) as $wsdlPropertiesInfo)
			{
				list ($exposedClassName, $propNames) = explode(":", $wsdlPropertiesInfo);
				if ($propNames == "_minimal_")
				{
					$propNames = "id,label,lang,type";
				}
				$exposedProperties[$exposedClassName] = array_flip(explode(",", $propNames));
			}
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

		$types = array();
		$bindingElem = $wsdl->findUnique("wsdl:binding");
		$portTypeElem = $wsdl->findUnique("wsdl:portType");
		$schemaElem = $wsdl->findUnique("wsdl:types/xsd:schema");

		foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
		{
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
				
			// Request type
			$requestTypeElem = $wsdl->createElement('xsd:element');
			$requestTypeElem->setAttribute("name", $method->getName()."Params");
			$complexTypeElem = $wsdl->createElement('xsd:complexType');
				
			$paramSequenceElem = $wsdl->createElement('xsd:sequence');
			foreach ($method->getParameters() as $parameter)
			{
				$requestParam = $wsdl->createElement('xsd:element');
				$requestParam->setAttribute("name", $parameter->getName());
				$requestParam->setAttribute("type", $this->getType($parameter->getName(), $method, $types, $exposedProperties));
				$paramSequenceElem->appendChild($requestParam);
			}
				
			$complexTypeElem->appendChild($paramSequenceElem);
			$requestTypeElem->appendChild($complexTypeElem);
			$schemaElem->appendChild($requestTypeElem);

			// Response message
			$responseMessageElem = $wsdl->createElement('wsdl:message');
			$responseMessageElem->setAttribute("name", $method->getName()."Response");
			$responseMessagePartElem = $wsdl->createElement('wsdl:part');
			$responseMessagePartElem->setAttribute("name", "parameters");
			$responseMessagePartElem->setAttribute("element", "tns:".$method->getName()."Response");
			$responseMessageElem->appendChild($responseMessagePartElem);
			$wsdl->documentElement->appendChild($responseMessageElem);
				
			// Response type
			$requestTypeElem = $wsdl->createElement('xsd:element');
			$requestTypeElem->setAttribute("name", $method->getName()."Response");
			$requestTypeElem->setAttribute("nillable", "true");
			$complexTypeElem = $wsdl->createElement('xsd:complexType');
			$paramSequenceElem = $wsdl->createElement('xsd:sequence');
			$paramSequenceElem->setAttribute("minOccurs", "0");
				
			//
			$responsePart = $wsdl->createElement('xsd:element');
			$responsePart->setAttribute("name", $method->getName()."Result");
			$responsePart->setAttribute("type", $this->getReturnType($method, $types, $exposedProperties));
			$responsePart->setAttribute("nillable", "true");
			$responsePart->setAttribute("minOccurs", "0");
			$responseMessageElem->appendChild($responsePart);
				
			$paramSequenceElem->appendChild($responsePart);
			$complexTypeElem->appendChild($paramSequenceElem);
			$requestTypeElem->appendChild($complexTypeElem);
			$schemaElem->appendChild($requestTypeElem);

			if (f_util_ClassUtils::hasMeta("extraWsdlProperties", $class))
			{
				$extraWsdlProps = f_util_ClassUtils::getMetaValue("extraWsdlProperties", $class);
				foreach (explode(";", $extraWsdlProps) as $extraWsdl)
				{
					$extraWsdlInfo = explode(":", $extraWsdl);
					$type = $extraWsdlInfo[0];
					if (isset($types[$type]))
					{
						if (!isset($types[$type]["extra-properties"]))
						{
							$types[$type]["extra-properties"] = array();
						}
						foreach (explode(",", $extraWsdlInfo[1]) as $propMeta)
						{
							$propInfo = explode("|", $propMeta);
							if (count($propInfo) == 1)
							{
								$propInfo[] = "String";
							}
								
							$propInfoObj = new BeanPropertyInfoImpl($propInfo[0], $propInfo[1]);
							$types[$type]["extra-properties"][$propInfo[0]] = $propInfoObj;
						}
					}
				}
			}
		}

		foreach ($types as $typeName => $typeInfo)
		{
			if (is_array($typeInfo) && isset($typeInfo["isArray"]) && $typeInfo["isArray"])
			{
				$typeInfo["baseType"];
				$typeElem = $wsdl->createElement("xsd:complexType");
				$typeElem->setAttribute("name", $typeName);

				$sequenceElem = $wsdl->createElement("xsd:sequence");
				$sequenceElem->setAttribute("minOccurs", "0");
				$elementElem = $wsdl->createElement("xsd:element");
				$elementElem->setAttribute("name", "items");
				$elementElem->setAttribute("minOccurs", "0");
				$elementElem->setAttribute("type", $typeInfo["baseType"]);
				$elementElem->setAttribute("maxOccurs", "unbounded");
				$sequenceElem->appendChild($elementElem);
				$typeElem->appendChild($sequenceElem);
				$schemaElem->appendChild($typeElem);

				$elementElem = $wsdl->createElement("xsd:element");
				$elementElem->setAttribute("name", $typeName);
				$elementElem->setAttribute("type", "tns:".$typeName);
				$schemaElem->appendChild($elementElem);
			}
			else
			{
				switch ($typeName)
				{
					case "PersistentDocument":
						$typeElem = $wsdl->createElement("xsd:complexType");
						$typeElem->setAttribute("name", $typeName);
						$sequenceElem = $wsdl->createElement("xsd:sequence");
							
						// id
						$elementElem = $wsdl->createElement("xsd:element");
						$elementElem->setAttribute("name", "id");
						$elementElem->setAttribute("type", "xsd:int");
						$elementElem->setAttribute("minOccurs", "1");
						$sequenceElem->appendChild($elementElem);
							
						$elementElem = $wsdl->createElement("xsd:element");
						$elementElem->setAttribute("name", "label");
						$elementElem->setAttribute("type", "xsd:string");
						$elementElem->setAttribute("minOccurs", "0");
						$sequenceElem->appendChild($elementElem);
							
						$elementElem = $wsdl->createElement("xsd:element");
						$elementElem->setAttribute("name", "type");
						$elementElem->setAttribute("type", "xsd:string");
						$elementElem->setAttribute("minOccurs", "0");
						$sequenceElem->appendChild($elementElem);
							
						$typeElem->appendChild($sequenceElem);
						$schemaElem->appendChild($typeElem);
						break;

					case "PersistentDocumentArray":
						$typeElem = $wsdl->createElement("xsd:complexType");
						$typeElem->setAttribute("name", $typeName);
						$sequenceElem = $wsdl->createElement("xsd:sequence");
							
						// id
						$elementElem = $wsdl->createElement("xsd:element");
						$elementElem->setAttribute("name", "count");
						$elementElem->setAttribute("type", "xsd:int");
						$elementElem->setAttribute("minOccurs", "1");
						$sequenceElem->appendChild($elementElem);
							
						$elementElem = $wsdl->createElement("xsd:element");
						$elementElem->setAttribute("name", "docs");
						$elementElem->setAttribute("type", "tns:ArrayOfPersistentDocument");
						$elementElem->setAttribute("minOccurs", "1");
						$sequenceElem->appendChild($elementElem);
							
						$typeElem->appendChild($sequenceElem);
						$schemaElem->appendChild($typeElem);
						break;
					default:
						$typeElem = $wsdl->createElement("xsd:complexType");
						$typeElem->setAttribute("name", $typeName);
						$sequenceElem = $wsdl->createElement("xsd:sequence");
							
						// id
						$class = new ReflectionClass($typeName);
						$bean = BeanUtils::getNewBeanInstance($class);

						$propInfos = $bean->getBeanModel()->getBeanPropertiesInfos();
						if (isset($typeInfo["extra-properties"]))
						{
							$propInfos = array_merge($propInfos, $typeInfo["extra-properties"]);
						}

						if ($class->implementsInterface("f_persistentdocument_PersistentDocument"))
						{
							$elementElem = $wsdl->createElement("xsd:element");
							$elementElem->setAttribute("name", "type");
							$elementElem->setAttribute("type", "xsd:string");
							$elementElem->setAttribute("minOccurs", "0");
							$elementElem->setAttribute("nillable", "true");
							$sequenceElem->appendChild($elementElem);
						}

						foreach ($propInfos as $propName => $propInfo)
						{
							if (isset($exposedProperties[$typeName]) && !isset($exposedProperties[$typeName][$propName]) && !isset($typeInfo["extra-properties"][$propName]))
							{
								continue;
							}
							$elementElem = $wsdl->createElement("xsd:element");
							$elementElem->setAttribute("name", $propName);
								
							$isArray = ($propInfo->getCardinality() == -1 || $propInfo->getCardinality() > 1);
							switch ($propInfo->getType())
							{
								case BeanPropertyType::BOOLEAN:
									$type = "xsd:boolean";
									break;
								case BeanPropertyType::INTEGER:
									$type = "xsd:int";
									break;
								case BeanPropertyType::DOUBLE:
									$type = "xsd:float";
									break;
								case BeanPropertyType::DATE:
								case BeanPropertyType::DATETIME:
									$type = "xsd:dateTime";
									break;
								case BeanPropertyType::LONGSTRING:
								case BeanPropertyType::LOB:
								case BeanPropertyType::STRING:
								case BeanPropertyType::XHTMLFRAGMENT:
									$type = "xsd:string";
									break;
								case BeanPropertyType::DOCUMENT:
								case BeanPropertyType::BEAN:
								case BeanPropertyType::CLASS_TYPE:
									if ($isArray)
									{
										$type = "tns:ArrayOf".$propInfo->getClassName();
									}
									else
									{
										$type = "tns:".$propInfo->getClassName();
									}
									break;
							}
								
							$elementElem->setAttribute("type", $type);
							if ($propName == "id")
							{
								$elementElem->setAttribute("minOccurs", "1");
							}
							else
							{
								$elementElem->setAttribute("minOccurs", "0");
								$elementElem->setAttribute("nillable", "true");
							}
							$sequenceElem->appendChild($elementElem);
						}
							
						$typeElem->appendChild($sequenceElem);
						$schemaElem->appendChild($typeElem);

						break;
				}
			}
		}

		return $wsdl->saveXML();
	}

	private function addType(&$types, $name, $value = array())
	{
		if (!isset($types[$name]))
		{
			$types[$name] = $value;
			return true;
		}
		return false;
	}

	private function phpTypeToWsdlType($phpType, &$types, $exposedProperties)
	{
		if ($phpType === null)
		{
			return null;
		}
		$isArray = f_util_StringUtils::endsWith($phpType, "[]");
		if ($isArray)
		{
			$phpType = substr($phpType, 0, -2);
		}
		switch (strtolower($phpType))
		{
			case "string":
				if ($isArray)
				{
					$this->addType($types, "ArrayOfString", array("isArray" => true, "baseType" => "xsd:string"));
					return "tns:ArrayOfString";
				}
				return "xsd:string";
			case "int":
			case "integer":
				if ($isArray)
				{
					$this->addType($types, "ArrayOfInt", array("isArray" => true, "baseType" => "xsd:int"));
					return "tns:ArrayOfInt";
				}
				return "xsd:int";
			case "boolean":
				if ($isArray)
				{
					$this->addType($types, "ArrayOfBoolean", array("isArray" => true, "baseType" => "xsd:boolean"));
					return "tns:ArrayOfBoolean";
				}
				return "xsd:boolean";
			case "float":
			case "double":
				if ($isArray)
				{
					$this->addType($types, "ArrayOfFloat", array("isArray" => true, "baseType" => "xsd:float"));
					return "tns:ArrayOfFloat";
				}
				return "xsd:float";
			case "webservices_persistentdocument":
				$this->addType($types, "PersistentDocument");
				if ($isArray)
				{
					$this->addType($types, "ArrayOfPersistentDocument", array("isArray" => true, "baseType" => "tns:PersistentDocument"));
					return "tns:ArrayOfPersistentDocument";
				}
				return "tns:PersistentDocument";
			case "webservices_persistentdocumentarray":
				$this->addType($types, "PersistentDocument");
				$this->addType($types, "ArrayOfPersistentDocument", array("isArray" => true, "baseType" => "tns:PersistentDocument"));
				$this->addType($types, "PersistentDocumentArray");
				if ($isArray)
				{
					throw new Exception("$phpType wsdl type not supported");
				}
				return "tns:PersistentDocumentArray";
			default:
				$class = new ReflectionClass($phpType);
				$bean = BeanUtils::getNewBeanInstance($class);
				if ($this->addType($types, $phpType))
				{
					foreach ($bean->getBeanModel()->getBeanPropertiesInfos() as $propName => $propInfo)
					{
						if (isset($exposedProperties[$phpType]) && !isset($exposedProperties[$phpType][$propName]))
						{
							continue;
						}
						if ($propInfo->getType() == BeanPropertyType::CLASS_TYPE
						|| $propInfo->getType() == BeanPropertyType::DOCUMENT
						|| $propInfo->getType() == BeanPropertyType::BEAN)
						{
							$subPhpType = $propInfo->getClassName();
							if ($propInfo->getCardinality() == -1)
							{
								$subPhpType .= "[]";
							}
							$this->phpTypeToWsdlType($subPhpType, $types, $exposedProperties);
						}
					}
				}

				if ($isArray)
				{
					$this->addType($types, "ArrayOf".$phpType, array("isArray" => true, "baseType" => "tns:".$phpType));
					return "tns:ArrayOf".$phpType;
				}
				return "tns:".$phpType;
		}
		throw new Exception("$phpType wsdl type not supported");
	}

	private function getType($paramName, $method, &$types, $exposedProperties)
	{
		$phpType = f_util_ClassUtils::getParamType($method, $paramName);
		return $this->phpTypeToWsdlType($phpType, $types, $exposedProperties);
	}

	private function getReturnType($method, &$types, $exposedProperties)
	{
		$phpType = f_util_ClassUtils::getReturnType($method);
		return $this->phpTypeToWsdlType($phpType, $types, $exposedProperties);
	}

	/**
	 * @param Integer $documentId
	 * @return f_persistentdocument_PersistentTreeNode
	 */
	//	public function getParentNodeForPermissions($documentId)
	//	{
	//		// Define this method to handle permissions on a virtual tree node. Example available in list module.
	//	}
}