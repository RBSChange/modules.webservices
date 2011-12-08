<?php
class webservices_WsdlTypes
{
	private $typeArray = array();
	
	private $className = null;
	
	private $typeService = false;
	
	/**
	 * @param string $className
	 */
	public function __construct($className)
	{
		$this->className = $className;
	}
	
	/**
	 * @return webservices_WsdlTypesService
	 */
	private function getTypeService()
	{
		if ($this->typeService === false)
		{
			$this->applyTypeService();
		}
		return $this->typeService;
	}
	
	/**
	 * @param string $phpType
	 * @return webservices_XsdElement
	 */
	public function createXsdElement($phpType)
	{
		if ($phpType === null)
		{
			return null;
		}
		list($phpClass, $isArray) = self::parsePhpType($phpType);
		$xsdElement = self::getSimpleType($phpClass);
		if ($xsdElement !== null)
		{
			if ($isArray)
			{
				return webservices_XsdComplexArray::SIMPLETYPEARRAY($xsdElement);
			}
			return $xsdElement;
		}
		
		if ($this->getTypeService() !== null)
		{
			$xsdElement = $this->getTypeService()->getWsdlType($phpClass);
		}
		
		if ($xsdElement === null)
		{
			$xsdElement = self::getComplexType($phpClass);
		}
		
		if ($xsdElement !== null && $isArray)
		{
			return webservices_XsdComplexArray::OBJECTARRAY($xsdElement);
		}
		return $xsdElement;
	}
	
	/**
	 * @param webservices_XsdComplex $complexType
	 */
	public function addComplexType($complexType)
	{
		if ($complexType instanceof webservices_XsdComplex)
		{
			$this->addType($complexType);
		}
	}
	
	/**
	 * @param f_util_DOMDocument $wsdl
	 */
	public function addInSchema($wsdl)
	{
		$schemaElem = $wsdl->findUnique("wsdl:types/xsd:schema");
		foreach ($this->typeArray as $typeName => $complexType)
		{
			$complexType->addInSchema($wsdl, $schemaElem);
		}
	}
	
	/**
	 * @return webservices_XsdComplex[]
	 */
	public function getTypes()
	{
		return $this->typeArray;
	}
	
	/**
	 * @param string $name
	 * @return webservices_XsdComplex
	 */
	public function getType($name)
	{
		return isset($this->typeArray[$name]) ? $this->typeArray[$name] : null;
	}
	
	private function applyTypeService()
	{
		$this->typeService = null;
		if ($this->className === null)
		{
			return;
		}
		
		$class = new ReflectionClass($this->className);
		if ($class->hasMethod('getWsdlTypesService'))
		{
			$method = $class->getMethod('getWsdlTypesService');
			$typeService = call_user_func(array($class->getName(), 'getWsdlTypesService'));
			if ($typeService instanceof webservices_WsdlTypesService)
			{
				$this->typeService = $typeService;
			}
		}
	}
	
	/**
	 * @param webservices_XsdComplex $xsdComplex
	 */
	private function addType($xsdComplex)
	{
		$typeName = $xsdComplex->getType();
		$this->typeArray[$typeName] = $xsdComplex;
		foreach ($xsdComplex->getXsdElementArray() as $element)
		{
			if ($element instanceof webservices_XsdComplex)
			{
				$this->addType($element);
			}
		}
	}
	
	/**
	 * 
	 * @param string $phpType
	 * @return list(string phpClass, boolean isArray)
	 */
	public static function parsePhpType($phpType)
	{
		if (substr($phpType, -2) == '[]')
		{
			return array(substr($phpType, 0, strlen($phpType) - 2), true);
		}
		return array($phpType, false);
	}
	
	/**
	 * 
	 * @param string $phpScalar
	 */
	public static function getSimpleType($phpScalar)
	{
		switch (strtolower($phpScalar))
		{
			case 'int' :
			case 'integer' :
				return webservices_XsdElement::INTEGER();
			case 'boolean' :
				return webservices_XsdElement::BOOLEAN();
			case 'float' :
			case 'double' :
				return webservices_XsdElement::DOUBLE();
			case 'date' :
			case 'datetime' :
				return webservices_XsdElement::DATETIME();
			case 'string' :
				return webservices_XsdElement::STRING();
		}
		return null;
	}
	
	/**
	 * @param string $phpClass
	 * @return webservices_XsdComplex
	 */
	public static function getComplexType($phpClass)
	{
		if (!f_util_ClassUtils::classExists($phpClass))
		{
			return null;
		}
		$reflectionClass = new ReflectionClass($phpClass);
		if ($reflectionClass->isSubclassOf('f_persistentdocument_PersistentDocument'))
		{
			list($moduleName, $persDoc, $documentName) = explode('_', $phpClass);
			if ($persDoc === 'persistentdocument')
			{
				$model = f_persistentdocument_PersistentDocumentModel::getInstance($moduleName, $documentName);
				$propertyNames = self::getDefaultModelPopertyNames($model);
				return webservices_XsdComplex::DOCUMENTMODEL($model, $phpClass, $propertyNames);
			}
			return null;
		}
		else
		{
			$objType = webservices_XsdComplex::OBJECT($phpClass);
			$objModel = BeanUtils::getBeanModel($reflectionClass);
			foreach ($objModel->getBeanPropertiesInfos() as $propName => $beanPropertyInfo)
			{
				$wsType = webservices_XsdComplex::FROM_BEAN_PROPERTYINFO($beanPropertyInfo);
				if ($wsType !== null)
				{
					$objType->addXsdElement($propName, $wsType);
				}
			}
			return $objType;
		}
	}
	
	/**
	 * @param f_persistentdocument_PersistentDocumentModel $model
	 */
	public static function getDefaultModelPopertyNames($model)
	{
		$result = array();
		$internalProperties = array("author", "model", "authorid", "creationdate", "modificationdate", "metas", "modelversion", "documentversion", "metastring", "s18s");
		if (!$model->publishOnDayChange())
		{
			$internalProperties[] = "startpublicationdate";
			$internalProperties[] = "endpublicationdate";
		}
		
		foreach ($model->getEditablePropertiesInfos() as $propertyInfo)
		{
			if (!in_array($propertyInfo->getName(), $internalProperties))
			{
				$result[] = $propertyInfo->getName();
			}
		}
		return $result;
	}
}

class webservices_WsdlTypesService extends BaseService
{
	
	/**
	 * @param string $name
	 * @return webservices_XsdComplex
	 */
	public function getWsdlType($name)
	{
		return null;
	}
	
	/**
	 * @param string $moduleName
	 * @param string $documentName
	 * @return f_persistentdocument_PersistentDocumentModel
	 */
	protected function getPersistentModel($moduleName, $documentName)
	{
		return f_persistentdocument_PersistentDocumentModel::getInstance($moduleName, $documentName);
	}
	
	/**
	 * @param f_persistentdocument_PersistentDocumentModel $model
	 * @return string[]
	 */
	protected function getDefaultModelPopertyNames($model)
	{
		return webservices_WsdlTypes::getDefaultModelPopertyNames($model);
	}
	
	/**
	 * @param f_persistentdocument_PersistentDocumentModel $model
	 * @param array $exludedProperties
	 * @return string[]
	 */
	protected function getLocalizedtModelPopertyNames($model, $exludedProperties = array())
	{
		$result = array('id');
		foreach ($model->getEditablePropertiesInfos() as $propertyInfo)
		{
			if (in_array($propertyInfo->getName(), $exludedProperties))
			{
				continue;
			}
			if ($propertyInfo->isLocalized())
			{
				$result[] = $propertyInfo->getName();
			}
		}
		return $result;
	}
	
	/**
	 * @param f_persistentdocument_PersistentDocumentModel $model
	 * @param array $exludedProperties
	 * @return string[]
	 */
	protected function getScalarPopertyNames($model, $exludedProperties = array())
	{
		$result = array();
		foreach ($model->getEditablePropertiesInfos() as $propertyInfo)
		{
			if (in_array($propertyInfo->getName(), $exludedProperties))
			{
				continue;
			}
			if (!$propertyInfo->isDocument())
			{
				$result[] = $propertyInfo->getName();
			}
		}
		return $result;
	}
}