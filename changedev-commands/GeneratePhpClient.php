<?php
class commands_GeneratePhpClient extends commands_AbstractChangedevCommand
{
	/**
	 * @return String
	 */
	function getUsage()
	{
		return "<className>";
	}

	/**
	 * @return String
	 */
	function getDescription()
	{
		return "generate PHP client for a given webservice";
	}

	/**
	 * @param String[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 */
	protected function validateArgs($params, $options)
	{
		return count($params) == 1;
	}

	/**
	 * @param Integer $completeParamCount the parameters that are already complete in the command line
	 * @param String[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 * @return String[] or null
	 */
	function getParameters($completeParamCount, $params, $options, $current)
	{
		if ($completeParamCount == 0)
		{
			$this->loadFramework();
			return ClassResolver::getClassNames($current);
		}
	}

	/**
	 * @param String[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 * @see c_ChangescriptCommand::parseArgs($args)
	 */
	function _execute($params, $options)
	{
		$className = $params[0];
		$this->loadFramework();
		$class = new ReflectionClass($className);
		if (!$class->implementsInterface("webservices_WebService"))
		{
			return $this->quitError("$className does not implement webservices_WebService interface");
		}
		


		ob_start();
		echo "<?php\n";
echo '
class wscli_array implements Iterator, ArrayAccess
{
    private $position = 0;  
    public $items = array();  
    
    
    public function rewind() {$this->position = 0;}
    public function current() {return $this->items[$this->position];}
    public function key() {return $this->position;}
    public function next() {++$this->position;}
    public function valid() {return isset($this->items[$this->position]);}
    
 	public function offsetSet($offset, $value) 
 	{
        if (is_null($offset))
        {
            $this->items[] = $value;
        } 
        else 
        {
            $this->items[$offset] = $value;
        }
    }
    public function offsetExists($offset) {return isset($this->items[$offset]);}
    public function offsetUnset($offset) {unset($this->items[$offset]);}
    public function offsetGet($offset) {return isset($this->items[$offset]) ? $this->items[$offset] : null;}
}
';
		
		$classmap = array();
		$wsdlTypes = webservices_ModuleService::getInstance()->getServiceTypeDefinitions($className);
		foreach ($wsdlTypes->getTypes() as $xsdComplex) 
		{
			//$xsdComplex = new webservices_XsdComplex();
			if ($xsdComplex->isArray()) 
			{
				$classmap[$xsdComplex->getType()] = 'wscli_array';
			}
			else
			{
				$classmap[$xsdComplex->getType()] = 'cl_' . $xsdComplex->getType();
			}
		}
		
		foreach ($classmap as $wsdlTypeName => $cn) 
		{
			if ($cn === 'wscli_array') {continue;}
			
			$xsdComplex = $wsdlTypes->getType($wsdlTypeName);
			echo "class $cn {\n";
			foreach ($xsdComplex->getXsdElementArray() as $propName => $xsdPropType) 
			{
				if ($xsdPropType->isArray())
				{
					$propType = $xsdPropType->getItem()->getType();
					if (isset($classmap[$propType])) {$propType = $classmap[$propType];}
					if ($propType === 'int') {$propType = 'integer';}
					$propType .= '[]';
				}
				else
				{
					$propType = $xsdPropType->getType();
					if (isset($classmap[$propType])) {$propType = $classmap[$propType];}
					if ($propType === 'int') {$propType = 'integer';}
				}
				echo "	/**\n";
				echo "	 * @var $propType\n";
				echo "	 */\n";
				echo "	 public \$$propName;\n\n";
			}
			echo "}\n";
		}
		
	
		// TODO: generate classes for object method returns and set classmap option for soap client
		foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
		{
			echo "class ".strtolower($className)."_".ucfirst($method->getName())."Param {\n";
			$params = array();
			foreach ($method->getParameters() as $param)
			{
				$params[$param->getName()] = '$'.$param->getName();
			}
			if (count($params) > 0)
			{
				echo "	public ";
				echo join(", ", $params);
				echo ";\n";

				echo "	function __construct(".join(", ", $params).") {\n";
				foreach ($params as $paramName => $paramVar)
				{
					echo '		$this->'.$paramName.' = '.$paramVar.";\n";
				}
				echo "	}\n";
			}
			echo "}\n";
		}
		
		echo "class ".$class->getName()."Client {
	private \$client;
	private \$endPoint;
	private \$clientOptions;

	/**
	 * @param String \$endPoint the change webservice location (http[s]://<targetFQDN>/webservices/<moduleName>/<serviceName>)
	 */
	function __construct(\$endPoint) {
		\$this->endPoint = \$endPoint;
		\$this->clientOptions = array('encoding' => 'utf-8', 'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP, 'trace' => true, 'features' => SOAP_SINGLE_ELEMENT_ARRAYS);
		\$this->clientOptions['classmap'] = " . var_export($classmap, true) .";
	}
	
	/**
	 * @param String \$login
	 */
	function setLogin(\$login) {
		\$this->clientOptions['login'] = \$login;
	}
	
	/**
	 * @param String \$password
	 */
	function setPassword(\$password) {
		\$this->clientOptions['password'] = \$password;
	}
	
	/**
	 * See http://php.net/manual/en/soapclient.soapclient.php for possible options
	 * @param String \$name
	 * @param mixed \$value
	 */
	function setSoapOption(\$name, \$value) {
		\$this->clientOptions[\$name] = \$value;
	}
	
	private function getClient() {
		if (\$this->client === null) {
			\$this->client = new SoapClient(\$this->endPoint.'?wsdl', \$this->clientOptions);
		}
		return \$this->client;
	}
	
	private function getArray(\$res) {
		if (!isset(\$res->items)) {
			return array();	
		}
		if (is_array(\$res->items)) {
			return \$res->items;
		}
		/* This one is not necessary when SOAP_SINGLE_ELEMENT_ARRAYS feature is enabled
		if (is_object(\$res->items)) {
			return array(\$res->items);
		}*/
		throw new Exception('Unknown array type result '.var_export(\$res, true));
	}\n\n";
		foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
		{
			$params = array();
			foreach ($method->getParameters() as $param)
			{
				$params[$param->getName()] = '$'.$param->getName();
			}
			$comment = str_replace(array_keys($classmap) , array_values($classmap), $method->getDocComment());
			echo "	".$comment."\n";
			echo "	function ".$method->getName()."(".join(", ", $params).") {\n";
			echo "		return \$this->getClient()->".$method->getName()."(new ".strtolower($className)."_".ucfirst($method->getName())."Param(".join(", ", $params)."))->".$method->getName()."Result;\n";
			echo "	}\n\n";
		}
		echo "}\n";
		echo ob_get_clean();
	}
}