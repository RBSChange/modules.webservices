<?php
class commands_GenerateWsdl extends commands_AbstractChangedevCommand
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
		return "generate wsdl for a given webservice class";
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

		$wsdl = webservices_ModuleService::getInstance()->generateWsdl($className)."\n";
		echo $wsdl;
	}
}