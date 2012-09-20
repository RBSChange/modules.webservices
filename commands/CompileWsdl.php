<?php
class commands_CompileWsdl extends c_ChangescriptCommand
{
	/**
	 * @return string
	 */
	public function getUsage()
	{
		return "";
	}

	/**
	 * @return string
	 */
	public function getDescription()
	{
		return "compile wsdl for declared webservices";
	}
	
	/**
	 * @return array
	 */
	public function getEvents()
	{
		return array(
			array('target' => 'compile-all'),
		);
	}
	
	/**
	 * @param string[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 */
	protected function validateArgs($params, $options)
	{
		return true;
	}

	/**
	 * @param string[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 * @see c_ChangescriptCommand::parseArgs($args)
	 */
	public function _execute($params, $options)
	{
		$this->message("== Compile wsdls ==");
		$this->loadFramework();
		webservices_ModuleService::getInstance()->compileWsdls();
		return $this->quitOk("webservices compiled successfully.");
	}
}