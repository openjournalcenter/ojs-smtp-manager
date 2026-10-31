<?php

namespace APP\plugins\generic\smtpManager\classes;

use APP\template\TemplateManager;
use Illuminate\Support\Facades\DB;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;

class SmtpSettingsForm extends Form
{
	protected object $plugin;
	protected int $contextId;

	public function __construct(object $plugin, int $contextId)
	{
		parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

		$this->plugin = $plugin;
		$this->contextId = $contextId;

		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	public function initData()
	{
		$this->setData('customSmtpEnabled', (bool) $this->plugin->getSetting($this->contextId, 'customSmtpEnabled'));
		$this->setData('smtpHost', $this->plugin->getSetting($this->contextId, 'smtpHost') ?? '');
		$this->setData('smtpPort', $this->plugin->getSetting($this->contextId, 'smtpPort') ?? '587');
		$this->setData('smtpAuth', $this->plugin->getSetting($this->contextId, 'smtpAuth') ?? 'tls');
		$this->setData('smtpUsername', $this->plugin->getSetting($this->contextId, 'smtpUsername') ?? '');
		$this->setData('smtpPassword', $this->plugin->getSetting($this->contextId, 'smtpPassword') ?? '');
		$this->setData('fromEmail', $this->plugin->getSetting($this->contextId, 'fromEmail') ?? '');
		$this->setData('fromName', $this->plugin->getSetting($this->contextId, 'fromName') ?? '');
		$this->setData('suppressCertCheck', (bool) $this->plugin->getSetting($this->contextId, 'suppressCertCheck'));
	}

	public function readInputData()
	{
		$this->readUserVars([
			'customSmtpEnabled',
			'smtpHost',
			'smtpPort',
			'smtpAuth',
			'smtpUsername',
			'smtpPassword',
			'fromEmail',
			'fromName',
			'suppressCertCheck',
		]);
	}

	public function fetch($request, $template = null, $display = false)
	{
		$templateMgr = TemplateManager::getManager($request);
		$logs = DB::table('smtp_delivery_logs')
			->where('context_id', $this->contextId)
			->orderBy('created_at', 'desc')
			->limit(50)
			->get();

		$templateMgr->assign([
			'pluginName' => $this->plugin->getName(),
			'authOptions' => [
				'tls' => __('plugins.generic.smtpManager.settings.smtpAuth.tls'),
				'ssl' => __('plugins.generic.smtpManager.settings.smtpAuth.ssl'),
				'none' => __('plugins.generic.smtpManager.settings.smtpAuth.none'),
			],
			'deliveryLogs' => $logs,
		]);

		return parent::fetch($request, $template, $display);
	}

	public function execute(...$functionArgs)
	{
		$this->plugin->updateSetting($this->contextId, 'customSmtpEnabled', (bool) $this->getData('customSmtpEnabled'));
		$this->plugin->updateSetting($this->contextId, 'smtpHost', trim((string) $this->getData('smtpHost')));
		$this->plugin->updateSetting($this->contextId, 'smtpPort', trim((string) $this->getData('smtpPort')));
		$this->plugin->updateSetting($this->contextId, 'smtpAuth', (string) $this->getData('smtpAuth'));
		$this->plugin->updateSetting($this->contextId, 'smtpUsername', trim((string) $this->getData('smtpUsername')));

		$password = (string) $this->getData('smtpPassword');
		if ($password !== '') {
			$this->plugin->updateSetting($this->contextId, 'smtpPassword', $password);
		}

		$this->plugin->updateSetting($this->contextId, 'fromEmail', trim((string) $this->getData('fromEmail')));
		$this->plugin->updateSetting($this->contextId, 'fromName', trim((string) $this->getData('fromName')));
		$this->plugin->updateSetting($this->contextId, 'suppressCertCheck', (bool) $this->getData('suppressCertCheck'));

		parent::execute(...$functionArgs);
	}
}
