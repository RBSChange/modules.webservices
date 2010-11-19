<?php
/**
 * webservices_CompileAction
 * @package modules.webservices.actions
 */
class webservices_CompileAction extends f_action_BaseJSONAction
{

	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$document = $this->getDocumentInstanceFromRequest($request);
		if ($document instanceof webservices_persistentdocument_ws)
		{
			webservices_ModuleService::getInstance()->compileWsdl($document->getPhpclass());
			return $this->sendJSON(array('message' => 
				LocaleService::getInstance()->transBO('m.webservices.bo.general.compiled-succefully', array(), array('label' => $document->getLabel()))));
		}
		return $this->sendJSONError(LocaleService::getInstance()->transBO('m.webservices.bo.general.compiled-error'), true);
	}
}