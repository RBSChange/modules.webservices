<?php
class webservices_XsdElement
{
	protected $namespace = 'xsd';
	protected $type = null;
	
	protected $nillable = true;
	protected $minOccurs = 0;
	
		
	/**
	 * @return string
	 */
	public function getType()
	{
		return $this->type;
	}
	
	/**
	 * @param integer $minOccurs
	 * @return webservices_XsdElement $this
	 */
	public function setMinOccurs($minOccurs = 0)
	{
		$this->minOccurs = $minOccurs;
		return $this;
	}

	/**
	 * @param boolean $nillable
	 * @return webservices_XsdElement $this
	 */
	public function setNillable($nillable = true)
	{
		$this->nillable = ($nillable == true);
		return $this;
	}

	/**
	 * @return string
	 */
	public function getTypeNS()
	{
		return $this->namespace . ':' . $this->type;
	}
	
	/**
	 * @return boolean
	 */
	public function isArray()
	{
		return false;
	}
	
	/**
	 * @return boolean
	 */
	public function isNillable()
	{
		return $this->nillable;
	}
	
	/**
	 * @return string in | out | NULL
	 */
	public function getDirection()
	{
		return NULL;
	}
	
	/**
	 * @param string $type
	 * @param boolean $nillable
	 * @param string $namespace
	 */
	protected function __construct($type, $nillable, $namespace)
	{
		$this->type = $type;
		$this->nillable = ($nillable == true);
		$this->namespace = $namespace;
	}
	
	
	/**
	 * @param boolean $required
	 * @return webservices_XsdElement
	 */
	public static function STRING($required = false)
	{
		return new self('string', !$required, 'xsd');
	}
	
	/**
	 * @param boolean $required
	 * @return webservices_XsdElement
	 */
	public static function BOOLEAN($required = false)
	{
		return new self('boolean', !$required, 'xsd');
	}

	/**
	 * @param boolean $required
	 * @return webservices_XsdElement
	 */
	public static function INTEGER($required = false)
	{
		return new self('int', !$required, 'xsd');
	}
	
	/**
	 * @param boolean $required
	 * @return webservices_XsdElement
	 */
	public static function DOUBLE($required = false)
	{
		return new self('float', !$required, 'xsd');
	}
	
	/**
	 * @param boolean $required
	 * @return webservices_XsdElement
	 */
	public static function DATETIME($required = false)
	{
		return new self('dateTime', !$required, 'xsd');
	}
	
	/**
	 * @param string $type
	 * @param boolean $required
	 * @return webservices_XsdElement
	 */
	public static function ELEMENT($type, $required = false)
	{
		return new self($type, !$required, 'tns');
	}
	
	/**
	 * @param DOMDocument $wsdl
	 * @param string $name
	 * @param boolean $setMinOccurs
	 * @param boolean $setNillable
	 * @return DOMElement
	 */
	public function getElementNode($wsdl, $name = null, $setMinOccurs = true)
	{
		$el = $wsdl->createElement('xsd:element');
		if ($name !== null)
		{
			$el->setAttribute("name", $name);
		}
		
		$el->setAttribute("type", $this->getTypeNS());
		if ($setMinOccurs)
		{
			$el->setAttribute("minOccurs", $this->minOccurs);
		}
		
		if ($this->nillable)
		{
			$el->setAttribute("nillable", "true");
		}
		return $el;
	}
	
	/**
	 * @param mixed $data
	 * @return mixed
	 */
	public function formatValue($data)
	{
		if ($this->type === 'dateTime' && !empty($data))
		{
			return str_replace(' ', 'T', $data) . 'Z';
		}
		else if ($this->type === 'boolean')
		{
			return ($data == true);
		}
		return $data;
	}
	
	public function formatPhpValue($data, $outObject = null)
	{
		if ($this->type === 'dateTime' && !empty($data))
		{
			return str_replace(array('T', 'Z'), array(' ', ''), $data);
		}
		else if ($this->type === 'boolean')
		{
			return ($data == true);
		}
		return $data;		
	}
	
	/**
	 * @param string $phpClass
	 * @param string $propName
	 * @param f_mvc_BeanModel $model
	 * @return webservices_XsdElement|null
	 */
	public static function OBJECT_PROPERTY($phpClass, $propName, $model = null)
	{
		if ($model === null)
		{
			$model = new f_mvc_DynBeanModel($phpClass);
		}
		if (!$model->hasBeanProperty($propName))
		{
			return null;
		}
		return self::FROM_BEAN_PROPERTYINFO($model->getBeanPropertyInfo($propName));
	}
	
	/**
	 * @param BeanPropertyInfo $beanPropertyInfo
	 * @return webservices_XsdElement|null
	 */
	public static function FROM_BEAN_PROPERTYINFO($beanPropertyInfo)
	{
		$multiple = $beanPropertyInfo->getCardinality() > 1 || $beanPropertyInfo->getCardinality() == -1;
		$elem = null;
		switch ($beanPropertyInfo->getType())
		{
			case BeanPropertyType::BOOLEAN :
				if ($multiple)
				{
					$elem = webservices_XsdComplexArray::SIMPLETYPEARRAY(webservices_XsdElement::BOOLEAN(true));
				}
				else 
				{
					$elem = webservices_XsdElement::BOOLEAN(true);
				}
				break;
			case BeanPropertyType::INTEGER :
				if ($multiple)
				{
					$elem = webservices_XsdComplexArray::SIMPLETYPEARRAY(webservices_XsdElement::INTEGER($beanPropertyInfo->isRequired()));
				}
				else
				{
					$elem = webservices_XsdElement::INTEGER($beanPropertyInfo->isRequired());
				}
				break;
			case BeanPropertyType::DOUBLE :
				if ($multiple)
				{
					$elem = webservices_XsdComplexArray::SIMPLETYPEARRAY(webservices_XsdElement::DOUBLE($beanPropertyInfo->isRequired()));
				}
				else 
				{
					$elem = webservices_XsdElement::DOUBLE($beanPropertyInfo->isRequired());
				}
				break;
			case BeanPropertyType::DATETIME :
			case BeanPropertyType::DATE :
				if ($multiple)
				{
					$elem = webservices_XsdComplexArray::SIMPLETYPEARRAY(webservices_XsdElement::DATETIME($beanPropertyInfo->isRequired()));
				}
				else 
				{
					$elem = webservices_XsdElement::DATETIME($beanPropertyInfo->isRequired());
				}
				break;
			case BeanPropertyType::STRING :
				if ($multiple)
				{
					$elem = webservices_XsdComplexArray::SIMPLETYPEARRAY(webservices_XsdElement::STRING($beanPropertyInfo->isRequired()));
				}
				else 
				{
					$elem = webservices_XsdElement::STRING($beanPropertyInfo->isRequired());
				}
				break;
			case BeanPropertyType::DOCUMENT:
				$docModel = f_persistentdocument_PersistentDocumentModel::getInstanceFromDocumentModelName($beanPropertyInfo->getDocumentType());
				$propertyNames = webservices_WsdlTypes::getDefaultModelPopertyNames($docModel);
				$elem = webservices_XsdComplex::DOCUMENTMODEL($docModel, null, $propertyNames);
				break;
			case BeanPropertyType::CLASS_TYPE:
				if ($multiple)
				{
					$elem = webservices_XsdComplexArray::OBJECTARRAY(webservices_WsdlTypes::getComplexType($beanPropertyInfo->getClassName()));
				}
				else
				{
					$elem = webservices_WsdlTypes::getComplexType($beanPropertyInfo->getClassName());
				}
				break;
		}
		return $elem;
	}
}

class webservices_XsdComplex extends webservices_XsdElement
{
	/**
	 * @var webservices_XsdElement
	 */
	protected $xsdElementArray = array();
			
	/**
	 * 
	 * @var string
	 */
	protected $phpClass = null;
		
	/**
	 * @param string $name
	 * @param webservices_XsdElement $xsdElement
	 * @return webservices_XsdComplex $this
	 */
	public function addXsdElement($name, $xsdElement)
	{
		if (!$this->isArray()) {$xsdElement->setMinOccurs(1);}
		$this->xsdElementArray[$name] = $xsdElement;
		return $this;
	}
	
	/**
	 * @return array<$name => webservices_XsdElement>
	 */
	public function getXsdElementArray()
	{
		return $this->xsdElementArray;
	}
	
	/**
	 * @param string $name
	 * @return webservices_XsdElement
	 */
	public function getXsdElement($name)
	{
		return isset($this->xsdElementArray[$name]) ? $this->xsdElementArray[$name] : null;
	}	
	
	/**
	 * @param string $name
	 * @return webservices_XsdComplex $this
	 */
	public function removeXsdElement($name)
	{
		if (isset($this->xsdElementArray[$name]))
		{
			unset($this->xsdElementArray[$name]);
		}
		return $this;
	}

	/**
	 * @return webservices_XsdComplex
	 */
	public static function DOCUMENT()
	{
		$result = new self('webservices_Document', true, 'tns');
		$result->xsdElementArray['id'] = webservices_XsdElement::INTEGER(true);
		$result->phpClass = "f_persistentdocument_PersistentDocumentImpl";
		return $result;
	}
	
	/**
	 * @param string $name
	 * @param string $phpClass
	 * @param string[] $propNames
	 * @return webservices_XsdComplex
	 */
	public static function OBJECT($name, $phpClass = null, $propNames = null)
	{
		$result = new self($name, false, 'tns');
		$result->phpClass = ($phpClass !== null) ? $phpClass : $name;
		if ($propNames !== null && f_util_ClassUtils::classExists($result->phpClass))
		{
			$class = new ReflectionClass($result->phpClass);
			$model = BeanUtils::getBeanModel($class);
			foreach ($propNames as $propName)
			{
				$wsType = self::OBJECT_PROPERTY($result->phpClass, $propName, $model);
				if ($wsType !== null)
				{
					$result->addXsdElement($propName, $wsType);
				}
			}
		}
		return $result;
	}
	
	/**
	 * @param f_persistentdocument_PersistentDocumentModel $model
	 * @param string $name
	 * @param string[] $propertyNames
	 * @return webservices_XsdComplex
	 */
	public static function DOCUMENTMODEL($model, $name = null, $propertyNames = array())
	{
		if ($name === null) 
		{ 
			$name = $model->getDocumentClassName(); 
		}	
		$result = new self($name, false, 'tns');
		$result->phpClass = $model->getDocumentClassName();
		
		foreach (array_unique($propertyNames) as $propertyName) 
		{
			$propertyInfo = $model->getEditableProperty($propertyName);
			$result->addPropertyInfo($propertyInfo);	
		}
		return $result;
	}
	
	/**
	 * @param PropertyInfo $propertyInfo
	 * @return webservices_XsdComplex $this
	 */
	public function addPropertyInfo($propertyInfo)
	{
		if ($propertyInfo instanceof PropertyInfo)
		{
			$propName = $propertyInfo->getName();
			if ($propertyInfo->isDocument())
			{
				if ($propertyInfo->isArray())
				{
					$this->addXsdElement($propName, webservices_XsdComplexArray::DOCUMENTARRAY());
				}
				else
				{
					$el = self::DOCUMENT();
					if ($propertyInfo->isRequired())
					{
						$el->setNillable(false);
					}
					$this->addXsdElement($propName, $el);
				}
			}
			else
			{
				switch ($propertyInfo->getType()) 
				{
					case f_persistentdocument_PersistentDocument::PROPERTYTYPE_BOOLEAN:
						$this->addXsdElement($propName, self::BOOLEAN(true));
						break;
					case f_persistentdocument_PersistentDocument::PROPERTYTYPE_INTEGER:
						$this->addXsdElement($propName, self::INTEGER($propertyInfo->isRequired()));
						break;						
					case f_persistentdocument_PersistentDocument::PROPERTYTYPE_DOUBLE:
						$this->addXsdElement($propName, self::DOUBLE($propertyInfo->isRequired()));
						break;	
					case f_persistentdocument_PersistentDocument::PROPERTYTYPE_DATETIME:
						$this->addXsdElement($propName, self::DATETIME($propertyInfo->isRequired()));
						break;
					default:
						$this->addXsdElement($propName, self::STRING($propertyInfo->isRequired()));	
						break;
				}
			}
		}
		
		return $this;
	}

	/**
	 * @param DOMDocument $wsdl
	 * @param DOMElement $schemaElem
	 * @return DOMElement
	 */
	public function addInSchema($wsdl, $schemaElem)
	{
		$ct = $wsdl->createElement('xsd:complexType');
		$ct->setAttribute("name", $this->getType());
		$seq = $wsdl->createElement('xsd:sequence');
		$ct->appendChild($seq);
		$schemaElem->appendChild($ct);
	
		foreach ($this->xsdElementArray as $name => $xsdElement)
		{
			$seq->appendChild($xsdElement->getElementNode($wsdl, $name));
		}
	}
	
	/**
	 * @param mixed $data
	 * @return mixed
	 */
	public function formatValue($data)
	{
		if ($data !== null && is_object($data))
		{
			$result = new stdClass();
			foreach ($this->getXsdElementArray() as $propName => $element) 
			{
				$getter = 'get' . ucfirst($propName) . ($element->isArray() ? 'Array' : '');
				if (is_callable(array($data, $getter), false))
				{
					$value = $data->{$getter}();
				}
				else if ($propName === 'model' && $data instanceof f_persistentdocument_PersistentDocumentImpl)
				{
					$value = $data->getDocumentModelName();
				}
				else
				{
					$value = $data->{$propName};
				}
				$result->$propName = $element->formatValue($value);
			}
			return $result;
		}
		return null;
	}
	
	/**
	 * 
	 * @param mixed $data
	 * @param string $name
	 * @return array<$value, $defined>
	 */
	private function getRawPhpPropertyValue($data, $name)
	{
		if (is_array($data))
		{
			if (isset($data[$name]))
			{
				return array($data[$name], true);
			}
		}
		else if (is_object($data))
		{
			if (isset($data->{$name})) 
			{
				return array($data->{$name}, true);
			}
		}
		return array(null, false);
	}
	
	public function formatPhpValue($data, $outObject = null)
	{
		if ($data === null) {return null;}
		if ($this->phpClass === "f_persistentdocument_PersistentDocumentImpl")
		{
			list($id, $defined) = $this->getRawPhpPropertyValue($data, 'id');
			$id = $defined ? intval($id) : 0;
			if ($id > 0)
			{
				return DocumentHelper::getDocumentInstance($id);
			}
			return $outObject;
		}
		
		$isPersitentDoc = false;
		if ($this->phpClass !== null)
		{
			$reflectionClass = new ReflectionClass($this->phpClass);
			if ($reflectionClass->implementsInterface('f_persistentdocument_PersistentDocument'))
			{
				$isPersitentDoc = true;
				list($id, $defined) = $this->getRawPhpPropertyValue($data, 'id');
				$id = $defined ? intval($id) : 0;
				
				if ($id > 0 && $outObject === null)
				{
					$outObject = DocumentHelper::getDocumentInstance($id);
				}
				if ($outObject === null || !($outObject instanceof $this->phpClass))
				{
					return null;
				}
			}
		}
		if ($outObject === null)
		{
			$outObject = new $this->phpClass;
		}
		
		if (!$isPersitentDoc)
		{
			foreach ($this->getXsdElementArray() as $propName => $element)
			{
				list($value, $defined) = $this->getRawPhpPropertyValue($data, $propName);
				if ($defined)
				{
					$outObject->{$propName} = $element->formatPhpValue($value);
				}
			}
			return $outObject;	
		}
		
		foreach ($this->getXsdElementArray() as $propName => $element)
		{
			if ($propName === 'id') {continue;}
			list($rawValue, $defined) = $this->getRawPhpPropertyValue($data, $propName);
			if (!$defined) {continue;}
						
			$propValue = $element->formatPhpValue($rawValue);
			$setter = 'set' . ucfirst($propName) . ($element->isArray() ? 'Array' : '');
			if (is_callable(array($outObject, $setter), false))
			{
				$outObject->{$setter}($propValue);
			}
			else
			{
				$outObject->{$propName} = $propValue;
			}
		}
		return $outObject;	
	}
}

class webservices_XsdComplexFunction extends webservices_XsdComplex
{
	/**
	 * @var string in | out
	 */
	protected $direction;
	
	/**
	 * @return string in | out
	 */
	public function getDirection()
	{
		return $this->direction;
	}
	
	/**
	 * @param DOMDocument $wsdl
	 * @param DOMElement $schemaElem
	 * @return DOMElement
	 */
	public function addInSchema($wsdl, $schemaElem)
	{
		$elem = $this->getElementNode($wsdl, $this->type, false);
		$elem->removeAttribute('type');
		$ct = $wsdl->createElement('xsd:complexType');
		$seq = $wsdl->createElement('xsd:sequence');
		$minOccurs = ($this->direction === 'out' || count($this->xsdElementArray)) ? "1" : "0";
		$seq->setAttribute("minOccurs", $minOccurs);
		$ct->appendChild($seq);
		$elem->appendChild($ct);
		$schemaElem->appendChild($elem);

		foreach ($this->xsdElementArray as $name => $xsdElement)
		{
			$seq->appendChild($xsdElement->getElementNode($wsdl, $name));
		}
	}	
	/**
	 * @param $name
	 * @return webservices_XsdComplex
	 */
	public static function FUNCTIONINFO($name, $direction = 'in')
	{
		$result = new self($name, false, 'tns');
		$result->direction = $direction;
		$result->setNillable(false);
		return $result;
	}
}

class webservices_XsdComplexArray extends webservices_XsdComplex
{
	/**
	 * @return boolean
	 */
	public function isArray()
	{
		return true;
	}
	
	/**
	 * @return webservices_XsdElement
	 */
	public function getItem()
	{
		return $this->getXsdElement('items');
	}
	
	/**
	 * @param webservices_XsdElement $element
	 * @return webservices_XsdComplexArray
	 */
	public static function SIMPLETYPEARRAY($element)
	{
		$result = new self('ArrayOf' . $element->getType(), false, 'tns');
		$result->xsdElementArray['items'] = $element;
		$result->setNillable(false);
		return $result;
	}
	
	/**
	 * @return webservices_XsdComplexArray
	 */
	public static function DOCUMENTARRAY()
	{
		$element = webservices_XsdComplex::DOCUMENT();
		$result = new self('ArrayOf' . $element->getType(), false, 'tns');
		$result->xsdElementArray['items'] = $element;
		$result->setNillable(false);
		return $result;
	}
	
	/**
	 * @param webservices_XsdComplex $complexElement
	 * @return webservices_XsdComplexArray
	 */
	public static function OBJECTARRAY($complexElement)
	{
		$result = new self('ArrayOf' . $complexElement->type , false, 'tns');
		$result->xsdElementArray['items'] = $complexElement;
		$result->setNillable(false);
		return $result;
	}
	
	/**
	 * @param DOMDocument $wsdl
	 * @param DOMElement $schemaElem
	 * @return DOMElement
	 */
	public function addInSchema($wsdl, $schemaElem)
	{
		$elem = $this->getElementNode($wsdl, $this->getType(), false);
		$schemaElem->appendChild($elem);
		
		$ct = $wsdl->createElement('xsd:complexType');
		$ct->setAttribute("name", $this->getType());
		$seq = $wsdl->createElement('xsd:sequence');
		$seq->setAttribute("minOccurs", "0");
		$ct->appendChild($seq);
		$schemaElem->appendChild($ct);
		$name = 'items';
		$xsdElement = $this->getItem();
		
		$xsdElement->setNillable(false);
		$el = $xsdElement->getElementNode($wsdl, $name);
		$el->setAttribute("maxOccurs", "unbounded");	
		$seq->appendChild($el);
	}
	
	/**
	 * @param mixed $data
	 * @return mixed
	 */
	public function formatValue($data)
	{
		$result = array();
		if (is_array($data) || $data instanceof ArrayObject)
		{
			$element = $this->getItem();
			foreach ($data as $item) 
			{
				$result[] = $element->formatValue($item);
			}
		}
		return $result;
	}
	
	public function formatPhpValue($data, $outObject = null)
	{
		$result = array();
		if ($data !== null)
		{
			$elements = array();
			if (is_array($data))
			{
				$elements = $data;
			}
			elseif (isset($data->items)&& is_array($data->items))
			{
				$elements = $data->items;
			}
			elseif (isset($data->items))
			{
				$elements = array($data->items);
			}
			$element = $this->getItem();
			foreach ($elements as $item) 
			{
				$result[] = $element->formatPhpValue($item);
			}
		}
		return $result;	
	}
}