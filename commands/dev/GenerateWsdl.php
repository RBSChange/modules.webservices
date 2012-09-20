<?php
class commands_GenerateWsdl extends c_ChangescriptCommand
{
	/**
	 * @return string
	 */
	public function getUsage()
	{
		return "<className>";
	}

	/**
	 * @return string
	 */
	public function getDescription()
	{
		return "generate wsdl for a given webservice class";
	}

	/**
	 * @param string[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 */
	protected function validateArgs($params, $options)
	{
		return count($params) == 1;
	}
	
	/**
	 * @param string[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 * @see c_ChangescriptCommand::parseArgs($args)
	 */
	public function _execute($params, $options)
	{
		$className = $params[0];
		$this->loadFramework();
		$class = new ReflectionClass($className);
		if (!$class->implementsInterface("webservices_WebService"))
		{
			return $this->quitError("$className does not implement webservices_WebService interface");
		}

		$wsdl = webservices_ModuleService::getInstance()->generateWsdl($className)."\n";
		echo $wsdl;
	}
}