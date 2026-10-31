<?php

namespace APP\plugins\generic\smtpManager\controllers;

use APP\handler\Handler;
use APP\notification\NotificationManager;
use APP\plugins\generic\smtpManager\classes\SmtpSettingsForm;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\DB;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\notification\PKPNotification;
use PKP\security\Role;
use PKP\security\Validation;

class SmtpManagerHandler extends Handler
{
	public $_isBackendPage = true;

	public function __construct(public $plugin)
	{
		parent::__construct();
		$this->addRoleAssignment(
			[Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER],
			['index', 'save', 'testMail', 'getLogs', 'clearLogs']
		);
	}

	public function authorize($request, &$args, $roleAssignments)
	{
		if (!Validation::isLoggedIn()) {
			Validation::redirectLogin();
			return false;
		}

		$isSiteAdmin = Validation::isSiteAdmin();
		$context = $request->getContext();
		$isManager = $context && Validation::isAuthorized(Role::ROLE_ID_MANAGER, $context->getId());

		if (!$isSiteAdmin && !$isManager) {
			return false;
		}

		$this->markRoleAssignmentsChecked();
		return parent::authorize($request, $args, $roleAssignments);
	}

	public function index($args, $request)
	{
		$templateMgr = TemplateManager::getManager($request);
		$this->setupTemplate($request);

		$context = $request->getContext();
		$contextId = $context ? $context->getId() : PKPApplication::CONTEXT_SITE;

		$form = new SmtpSettingsForm($this->plugin, $contextId);
		$form->initData();

		$templateMgr->assign([
			'pageTitle' => $this->plugin->getDisplayName(),
			'formHtml' => $form->fetch($request),
		]);

		return $templateMgr->display($this->plugin->getTemplateResource('index.tpl'));
	}

	public function save($args, $request)
	{
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : PKPApplication::CONTEXT_SITE;

		$form = new SmtpSettingsForm($this->plugin, $contextId);
		$form->readInputData();
		if ($form->validate()) {
			$form->execute();
			$notificationManager = new NotificationManager();
			$notificationManager->createTrivialNotification(
				$request->getUser()->getId(),
				PKPNotification::NOTIFICATION_TYPE_SUCCESS,
				['contents' => __('common.changesSaved')]
			);
			return new JSONMessage(true);
		}

		return new JSONMessage(false, $form->fetch($request));
	}

	public function testMail($args, $request)
	{
		if (!$request->checkCSRF()) {
			return new JSONMessage(false, __('common.permissionDenied'));
		}

		$recipient = trim((string) $request->getUserVar('testRecipient'));
		if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
			return new JSONMessage(false, __('email.invalid'));
		}

		$context = $request->getContext();
		$contextId = $context ? $context->getId() : PKPApplication::CONTEXT_SITE;

		return $this->plugin->sendDiagnosticEmail($contextId, $recipient);
	}

	public function getLogs($args, $request)
	{
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : PKPApplication::CONTEXT_SITE;

		$logs = DB::table('smtp_delivery_logs')
			->where('context_id', $contextId)
			->orderBy('created_at', 'desc')
			->limit(50)
			->get();

		return new JSONMessage(true, $logs);
	}

	public function clearLogs($args, $request)
	{
		if (!$request->checkCSRF()) {
			return new JSONMessage(false, __('common.permissionDenied'));
		}

		$context = $request->getContext();
		$contextId = $context ? $context->getId() : PKPApplication::CONTEXT_SITE;

		DB::table('smtp_delivery_logs')
			->where('context_id', $contextId)
			->delete();

		return new JSONMessage(true);
	}
}
