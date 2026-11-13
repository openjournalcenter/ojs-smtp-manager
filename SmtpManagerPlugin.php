<?php

namespace APP\plugins\generic\smtpManager;

use APP\core\Application;
use APP\notification\NotificationManager;
use APP\plugins\generic\smtpManager\classes\DeliveryLoggingTransport;
use APP\plugins\generic\smtpManager\classes\LoggingEsmtpTransport;
use APP\plugins\generic\smtpManager\classes\SmtpDebugLogger;
use APP\plugins\generic\smtpManager\classes\SmtpLogMigration;
use APP\plugins\generic\smtpManager\classes\SmtpSettingsForm;
use APP\plugins\generic\smtpManager\controllers\SmtpManagerHandler;
use Exception;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\core\JSONMessage;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\notification\PKPNotification;
use PKP\observers\events\MessageSendingFromContext;
use PKP\observers\events\MessageSendingFromSite;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\security\Role;
use PKP\security\Validation;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class SmtpManagerPlugin extends GenericPlugin
{
	public function register($category, $path, $mainContextId = null)
	{
		$success = parent::register($category, $path, $mainContextId);
		if ($success && $this->getEnabled($mainContextId)) {
			$this->ensureSchemaInstalled();
			$this->registerMailEvents();
			Hook::add('LoadHandler', [$this, 'setPageHandler']);
			Hook::add('TemplateManager::display', [$this, 'handleTemplateDisplay']);
		}
		return $success;
	}

	protected function ensureSchemaInstalled(): void
	{
		if (!$this->getSetting(0, 'schema_installed')) {
			if (!Schema::hasTable('smtp_delivery_logs')) {
				$migration = $this->getInstallMigration();
				$migration->up();
			}
			$this->updateSetting(0, 'schema_installed', true);
		}
	}

	public function getInstallMigration()
	{
		return new SmtpLogMigration();
	}

	public function isSitePlugin()
	{
		return false;
	}

	public function getHideManagement()
	{
		$request = Application::get()->getRequest();
		if (!$request->getContext()) {
			return true;
		}
		return parent::getHideManagement();
	}

	public function getDisplayName()
	{
		return __('plugins.generic.smtpManager.displayName');
	}

	public function getDescription()
	{
		return __('plugins.generic.smtpManager.description');
	}

	public function getActions($request, $verb)
	{
		$context = $request->getContext();
		if (!$context) {
			return [];
		}

		$router = $request->getRouter();
		$canManage = $this->getEnabled($context->getId());

		return array_merge(
			$canManage ? [
				new LinkAction(
					'settings',
					new AjaxModal(
						$router->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']),
						$this->getDisplayName()
					),
					__('manager.plugins.settings'),
					null
				),
			] : [],
			parent::getActions($request, $verb)
		);
	}

	public function manage($args, $request)
	{
		$context = $request->getContext();
		if (!$context) {
			return new JSONMessage(false, __('common.permissionDenied'));
		}

		$verb = $request->getUserVar('verb');
		switch ($verb) {
			case 'settings':
				$form = new SmtpSettingsForm($this, $context->getId());
				if ($request->getUserVar('save')) {
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
				}
				$form->initData();
				return new JSONMessage(true, $form->fetch($request));

			case 'testMail':
				if (!$request->checkCSRF()) {
					return new JSONMessage(false, __('common.permissionDenied'));
				}
				$recipient = trim((string) $request->getUserVar('testRecipient'));
				if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
					return new JSONMessage(false, __('email.invalid'));
				}

				return $this->sendDiagnosticEmail($context->getId(), $recipient);

			default:
				return parent::manage($args, $request);
		}
	}

	public function setPageHandler(string $hookName, array $params): bool
	{
		$page = &$params[0];
		$handler = &$params[3];
		$request = Application::get()->getRequest();

		if ($request->getContext() && $this->getEnabled() && $page === 'smtpManager') {
			$handler = new SmtpManagerHandler($this);
			return true;
		}

		return false;
	}

	public function handleTemplateDisplay(string $hookName, array $params): bool
	{
		$templateMgr = $params[0];
		$request = Application::get()->getRequest();
		$context = $request->getContext();

		if (!$context || !Validation::isLoggedIn()) {
			return false;
		}

		$contextId = $context->getId();
		$isAdmin = Validation::isSiteAdmin();
		$isManager = Validation::isAuthorized(Role::ROLE_ID_MANAGER, $contextId);

		if ($isAdmin || $isManager) {
			$menu = (array) $templateMgr->getState('menu');
			$url = $request->getDispatcher()->url($request, Application::ROUTE_PAGE, null, 'smtpManager');

			$smtpItem = [
				'name' => $this->getDisplayName(),
				'url' => $url,
				'isCurrent' => $request->getRequestedPage() === 'smtpManager',
			];

			$pos = false;
			$keys = array_keys($menu);
			foreach (['admin', 'tools'] as $targetKey) {
				$foundPos = array_search($targetKey, $keys, true);
				if ($foundPos !== false && ($pos === false || $foundPos > $pos)) {
					$pos = $foundPos;
				}
			}

			if ($pos !== false) {
				$menu = array_slice($menu, 0, $pos + 1, true)
					+ ['smtpManager' => $smtpItem]
					+ array_slice($menu, $pos + 1, null, true);
			} else {
				$menu['smtpManager'] = $smtpItem;
			}

			$templateMgr->setState(['menu' => $menu]);
		}

		return false;
	}

	public function createTransportForContext(int $contextId, ?SmtpDebugLogger $logger = null)
	{
		$host = trim((string) $this->getSetting($contextId, 'smtpHost'));
		$port = (int) ($this->getSetting($contextId, 'smtpPort') ?: 587);
		$auth = (string) $this->getSetting($contextId, 'smtpAuth');
		$user = trim((string) $this->getSetting($contextId, 'smtpUsername'));
		$pass = (string) $this->getSetting($contextId, 'smtpPassword');
		$suppressCert = (bool) $this->getSetting($contextId, 'suppressCertCheck');

		$tls = ($auth === 'ssl' || $port === 465) ? true : null;

		if ($logger) {
			$transport = new LoggingEsmtpTransport($host, $port, $tls, null, $logger);
			$transport->setDebugLogger($logger);
		} else {
			$transport = new EsmtpTransport($host, $port, $tls);
		}

		if ($user !== '') {
			$transport->setUsername($user);
		}
		if ($pass !== '') {
			$transport->setPassword($pass);
		}

		if ($suppressCert) {
			$stream = $transport->getStream();
			if ($stream instanceof SocketStream) {
				$stream->setStreamOptions([
					'ssl' => [
						'verify_peer' => false,
						'verify_peer_name' => false,
						'allow_self_signed' => true,
					],
				]);
			}
		}

		return $transport;
	}

	protected function registerMailEvents()
	{
		$events = app('events');
		if (!$events) {
			return;
		}

		$applyTransportAndWrap = function ($event, ?int $contextId) {
			$request = Application::get()->getRequest();
			if (!$contextId) {
				$context = $request ? $request->getContext() : null;
				$contextId = $context ? $context->getId() : 0;
			}

			$mailer = app('mailer');
			if (!$mailer || !method_exists($mailer, 'setSymfonyTransport')) {
				return;
			}

			$currentTransport = $mailer->getSymfonyTransport();
			if ($currentTransport instanceof DeliveryLoggingTransport) {
				$baseTransport = $currentTransport->getOriginalTransport();
			} else {
				$baseTransport = $currentTransport;
			}

			$customEnabled = $contextId ? (bool) $this->getSetting($contextId, 'customSmtpEnabled') : false;
			$host = $contextId ? trim((string) $this->getSetting($contextId, 'smtpHost')) : '';

			if ($customEnabled && $host !== '') {
				$transportToSend = $this->createTransportForContext($contextId);
				$port = (int) ($this->getSetting($contextId, 'smtpPort') ?: 587);

				$fromEmail = trim((string) $this->getSetting($contextId, 'fromEmail'));
				$fromName = trim((string) $this->getSetting($contextId, 'fromName'));
				if ($fromEmail !== '' && isset($event->message) && method_exists($event->message, 'from')) {
					$event->message->from(new Address($fromEmail, $fromName));
				}
			} else {
				$transportToSend = $baseTransport;
				$port = null;
				$host = null;
			}

			$loggingTransport = new DeliveryLoggingTransport($transportToSend, $contextId, $host, $port);
			$mailer->setSymfonyTransport($loggingTransport);
		};

		$events->listen(MessageSendingFromContext::class, function (MessageSendingFromContext $event) use ($applyTransportAndWrap) {
			$context = $event->context;
			$contextId = $context ? $context->getId() : 0;
			$applyTransportAndWrap($event, $contextId);
		});

		$events->listen(MessageSendingFromSite::class, function (MessageSendingFromSite $event) use ($applyTransportAndWrap) {
			$applyTransportAndWrap($event, null);
		});

		$events->listen(MessageSending::class, function (MessageSending $event) use ($applyTransportAndWrap) {
			if ($event instanceof MessageSendingFromContext || $event instanceof MessageSendingFromSite) {
				return;
			}
			$applyTransportAndWrap($event, null);
		});
	}

	public function recordDiagnosticLog(int $contextId, string $senderEmail, string $recipientEmail, string $subject, string $status, ?string $errorMessage = null)
	{
		try {
			$host = trim((string) $this->getSetting($contextId, 'smtpHost'));
			$port = (int) ($this->getSetting($contextId, 'smtpPort') ?: 587);

			DB::table('smtp_delivery_logs')->insert([
				'context_id' => $contextId,
				'sender_email' => $senderEmail,
				'recipient_email' => $recipientEmail,
				'subject' => mb_substr($subject, 0, 500),
				'status' => $status,
				'error_message' => $errorMessage,
				'smtp_host' => $host !== '' ? $host : null,
				'smtp_port' => $host !== '' ? $port : null,
				'created_at' => date('Y-m-d H:i:s'),
			]);
		} catch (Exception $e) {
			error_log('SmtpManager recordDiagnosticLog error: ' . $e->getMessage());
		}
	}

	public function sendDiagnosticEmail(int $contextId, string $recipient): JSONMessage
	{
		$host = trim((string) $this->getSetting($contextId, 'smtpHost'));
		if ($host === '') {
			return new JSONMessage(false, __('plugins.generic.smtpManager.test.savedRequired'));
		}

		$fromEmail = trim((string) $this->getSetting($contextId, 'fromEmail'));
		if ($fromEmail === '') {
			$fromEmail = trim((string) $this->getSetting($contextId, 'smtpUsername'));
		}
		if ($fromEmail === '') {
			$fromEmail = 'noreply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
		}

		$fromName = trim((string) $this->getSetting($contextId, 'fromName'));
		if ($fromName === '') {
			$fromName = __('plugins.generic.smtpManager.test.senderName');
		}

		$port = $this->getSetting($contextId, 'smtpPort') ?: '587';
		$logger = new SmtpDebugLogger();
		$currentDate = date('Y-m-d H:i:s');
		$subject = __('plugins.generic.smtpManager.test.emailSubject', ['date' => $currentDate]);
		$body = __('plugins.generic.smtpManager.test.emailBody', [
			'host' => $host,
			'port' => $port,
			'date' => $currentDate,
		]);

		try {
			$transport = $this->createTransportForContext($contextId, $logger);

			$email = (new Email())
				->from(new Address($fromEmail, $fromName))
				->to(new Address($recipient))
				->subject($subject)
				->text($body);

			$transport->send($email);

			$this->recordDiagnosticLog($contextId, $fromEmail, $recipient, $subject, 'sent', null);

			$response = new JSONMessage(true, __('plugins.generic.smtpManager.test.success', ['recipient' => htmlspecialchars($recipient)]));
			$response->setAdditionalAttributes(['log' => $logger->getFormattedLogs()]);
			return $response;
		} catch (Exception $e) {
			$this->recordDiagnosticLog($contextId, $fromEmail, $recipient, $subject, 'failed', $e->getMessage());

			$response = new JSONMessage(false, __('plugins.generic.smtpManager.test.failed', ['error' => htmlspecialchars($e->getMessage())]));
			$response->setAdditionalAttributes(['log' => $logger->getFormattedLogs()]);
			return $response;
		}
	}
}
