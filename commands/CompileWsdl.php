<?php
class commands_CompileWsdl extends c_ChangescriptCommand
{
	/**
	 * @return String
	 */
	function getUsage()
	{
		return "";
	}

	/**
	 * @return String
	 */
	function getDescription()
	{
		return "compile wsdl for declared webservices";
	}
	
	/**
	 * @see c_ChangescriptCommand::getEvents()
	 */
	public function getEvents()
	{
		return array(
			array('target' => 'compile-all'),
		);
	}
	
	/**
	 * @param String[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 */
	protected function validateArgs($params, $options)
	{
		return true;
	}

	/**
	 * @param String[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 * @see c_ChangescriptCommand::parseArgs($args)
	 */
	function _execute($params, $options)
	{
		$this->message("== Compile wsdls ==");
		$this->loadFramework();
		webservices_ModuleService::getInstance()->compileWsdls();
		return $this->quitOk("webservices compiled successfully.");
	}
}