<script>
	$(function() {ldelim}
		$('#smtpManagerTabs').pkpHandler('$.pkp.controllers.TabHandler');
		$('#smtpSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');

		$('#smtpManagerTabs').on('tabsactivate', function(event, ui) {ldelim}
			if (ui.newPanel && ui.newPanel.attr('id') === 'smtpLogsTab') {ldelim}
				reloadLogs();
			{rdelim}
		{rdelim});

		$('#sendTestMailBtn').on('click', function(e) {ldelim}
			e.preventDefault();
			var $btn = $(this);
			var recipient = $('#testRecipient').val().trim();
			var $result = $('#testMailResult');

			if (!recipient) {ldelim}
				$result.html('<div class="pkp_notification" style="margin-top:1.25rem;"><div class="notifyWarning">' +
					'{translate key="plugins.generic.smtpManager.test.recipient.description"}' +
				'</div></div>');
				return;
			{rdelim}

			$btn.prop('disabled', true).text('{translate key="plugins.generic.smtpManager.test.sending"}');
			$result.html('<p class="pkp_help" style="margin-top:1rem;margin-bottom:0;"><span class="pkp_spinner" style="margin-right:8px;"></span> {translate key="plugins.generic.smtpManager.test.connecting"}</p>');

			var csrfToken = $('input[name="csrfToken"]').val();

			$.ajax({ldelim}
				url: '{url page="smtpManager" op="testMail"}',
				type: 'POST',
				data: {ldelim}
					testRecipient: recipient,
					csrfToken: csrfToken
				{rdelim},
				success: function(response) {ldelim}
					$btn.prop('disabled', false).text('{translate key="plugins.generic.smtpManager.test.button"}');
					var logHtml = '';
					if (response && response.log) {ldelim}
						var escapedLog = $('<div>').text(response.log).html();
						logHtml = '<div style="margin-top:1.25rem;">' +
							'<div class="label" style="font-weight:bold; margin-bottom:0.5rem; text-transform:uppercase;">{translate key="plugins.generic.smtpManager.test.logTitle"}:</div>' +
							'<pre style="background:#0b1120; color:#38bdf8; font-family:Consolas, Monaco, monospace; font-size:0.8125rem; line-height:1.5; padding:14px; border-radius:4px; overflow-x:auto; white-space:pre-wrap; max-height:300px; width:100%; box-sizing:border-box;">' +
							escapedLog +
							'</pre>' +
						'</div>';
					{rdelim}

					if (response && response.status === true) {ldelim}
						$result.html('<div class="pkp_notification" style="margin-top:1.25rem;"><div class="notifySuccess">' +
							'<strong>{translate key="plugins.generic.smtpManager.test.statusSuccess"}</strong> ' + (response.content || '{translate key="plugins.generic.smtpManager.test.success"}') +
						'</div></div>' + logHtml);
						reloadLogs();
					{rdelim} else {ldelim}
						var errMsg = (response && response.content) ? response.content : '{translate key="plugins.generic.smtpManager.test.failedUnknown"}';
						$result.html('<div class="pkp_notification" style="margin-top:1.25rem;"><div class="notifyError">' +
							'<strong>{translate key="plugins.generic.smtpManager.test.statusFailed"}</strong> ' + errMsg +
						'</div></div>' + logHtml);
						reloadLogs();
					{rdelim}
				{rdelim},
				error: function(xhr, status, error) {ldelim}
					$btn.prop('disabled', false).text('{translate key="plugins.generic.smtpManager.test.button"}');
					$result.html('<div class="pkp_notification" style="margin-top:1.25rem;"><div class="notifyError">' +
						'<strong>{translate key="plugins.generic.smtpManager.test.serverError"}</strong> ' + error +
					'</div></div>');
				{rdelim}
			{rdelim});
		{rdelim});

		function reloadLogs() {ldelim}
			$.ajax({ldelim}
				url: '{url page="smtpManager" op="getLogs"}',
				type: 'GET',
				success: function(response) {ldelim}
					if (response && response.status === true && Array.isArray(response.content)) {ldelim}
						renderLogs(response.content);
					{rdelim}
				{rdelim}
			{rdelim});
		{rdelim}

		function renderLogs(logs) {ldelim}
			var $tbody = $('#smtpDeliveryLogsTable tbody');
			$tbody.empty();
			if (!logs.length) {ldelim}
				$tbody.html('<tr class="empty"><td colspan="5">{translate key="plugins.generic.smtpManager.logs.empty"}</td></tr>');
				return;
			{rdelim}
			$.each(logs, function(i, log) {ldelim}
				var badge = log.status === 'sent'
					? '<span style="display:inline-block; padding:2px 8px; font-size:0.75rem; font-weight:600; border-radius:12px; background:#def7ec; color:#03543f;">{translate key="plugins.generic.smtpManager.logs.status.sent"}</span>'
					: '<span style="display:inline-block; padding:2px 8px; font-size:0.75rem; font-weight:600; border-radius:12px; background:#fde8e8; color:#9b1c1c;">{translate key="plugins.generic.smtpManager.logs.status.failed"}</span>';
				var err = log.error_message ? '<div style="font-size:0.75rem; color:#d00a0a; margin-top:2px;">' + $('<div>').text(log.error_message).html() + '</div>' : '';
				var tr = '<tr class="gridRow">' +
					'<td class="label" style="white-space:nowrap;">' + $('<div>').text(log.created_at).html() + '</td>' +
					'<td class="label">' + $('<div>').text(log.recipient_email).html() + '</td>' +
					'<td class="label">' + $('<div>').text(log.subject || '-').html() + err + '</td>' +
					'<td class="label" style="white-space:nowrap;">' + $('<div>').text((log.smtp_host || 'default') + (log.smtp_port ? (':' + log.smtp_port) : '')).html() + '</td>' +
					'<td class="label" style="text-align:center;">' + badge + '</td>' +
				'</tr>';
				$tbody.append(tr);
			{rdelim});
		{rdelim}

		$('#refreshLogsBtn').on('click', function(e) {ldelim}
			e.preventDefault();
			reloadLogs();
		{rdelim});

		$('#clearLogsBtn').on('click', function(e) {ldelim}
			e.preventDefault();
			if (!confirm('{translate key="plugins.generic.smtpManager.logs.clearConfirm"|escape:"javascript"}')) {ldelim}
				return;
			{rdelim}
			$.ajax({ldelim}
				url: '{url page="smtpManager" op="clearLogs"}',
				type: 'POST',
				data: {ldelim}
					csrfToken: $('input[name="csrfToken"]').val()
				{rdelim},
				success: function() {ldelim}
					reloadLogs();
				{rdelim}
			{rdelim});
		{rdelim});
	{rdelim});
</script>

<div id="smtpManagerTabs" class="pkp_controllers_tab ui-tabs ui-widget ui-widget-content">
	<ul class="ui-tabs-nav pkp_unstyled_list ui-helper-reset ui-helper-clearfix ui-widget-header">
		<li class="ui-state-default ui-corner-top ui-tabs-active ui-state-active"><a name="smtpSettings" href="#smtpSettingsTab">{translate key="plugins.generic.smtpManager.tabs.settings"}</a></li>
		<li class="ui-state-default ui-corner-top"><a name="smtpTesting" href="#smtpTestingTab">{translate key="plugins.generic.smtpManager.tabs.testing"}</a></li>
		<li class="ui-state-default ui-corner-top"><a name="smtpLogs" href="#smtpLogsTab">{translate key="plugins.generic.smtpManager.tabs.logs"}</a></li>
	</ul>

	<div id="smtpSettingsTab" class="ui-tabs-panel ui-widget-content ui-corner-bottom">
		<form class="pkp_form" id="smtpSettingsForm" method="post" action="{url page="smtpManager" op="save"}">
			{csrf}
			{include file="controllers/notification/inPlaceNotification.tpl" notificationId="smtpSettingsFormNotification"}

			<p class="pkp_help" style="margin-bottom: 20px;">
				{translate key="plugins.generic.smtpManager.description"}
			</p>

			{fbvFormArea id="smtpSettingsArea"}
				<div>
					{fbvFormSection list="true"}
						{fbvElement type="checkbox" id="customSmtpEnabled" checked=$customSmtpEnabled label="plugins.generic.smtpManager.settings.enabled"}
						<span class="sub_label">{translate key="plugins.generic.smtpManager.settings.enabled.description"}</span>
					{/fbvFormSection}
				</div>

				<div>
					{fbvFormSection title="plugins.generic.smtpManager.settings.smtpHost" required="true"}
						{fbvElement type="text" id="smtpHost" value=$smtpHost label="plugins.generic.smtpManager.settings.smtpHost.description" subLabelTranslate=true maxlength="150"}
					{/fbvFormSection}
				</div>

				<div class="pkp_helpers_clear">
					<div style="float:left; width:48%;">
						{fbvFormSection title="plugins.generic.smtpManager.settings.smtpPort" required="true"}
							{fbvElement type="text" id="smtpPort" value=$smtpPort label="plugins.generic.smtpManager.settings.smtpPort.description" subLabelTranslate=true maxlength="10"}
						{/fbvFormSection}
					</div>
					<div style="float:right; width:48%;">
						{fbvFormSection title="plugins.generic.smtpManager.settings.smtpAuth"}
							{fbvElement type="select" id="smtpAuth" from=$authOptions selected=$smtpAuth translate=false}
						{/fbvFormSection}
					</div>
				</div>

				<div class="pkp_helpers_clear">
					<div style="float:left; width:48%;">
						{fbvFormSection title="plugins.generic.smtpManager.settings.smtpUsername"}
							{fbvElement type="text" id="smtpUsername" value=$smtpUsername label="plugins.generic.smtpManager.settings.smtpUsername.description" subLabelTranslate=true maxlength="150"}
						{/fbvFormSection}
					</div>
					<div style="float:right; width:48%;">
						{fbvFormSection title="plugins.generic.smtpManager.settings.smtpPassword"}
							{fbvElement type="text" password=true id="smtpPassword" value=$smtpPassword label="plugins.generic.smtpManager.settings.smtpPassword.description" subLabelTranslate=true maxlength="255"}
						{/fbvFormSection}
					</div>
				</div>

				<div class="pkp_helpers_clear">
					<div style="float:left; width:48%;">
						{fbvFormSection title="plugins.generic.smtpManager.settings.fromEmail"}
							{fbvElement type="text" id="fromEmail" value=$fromEmail label="plugins.generic.smtpManager.settings.fromEmail.description" subLabelTranslate=true maxlength="150"}
						{/fbvFormSection}
					</div>
					<div style="float:right; width:48%;">
						{fbvFormSection title="plugins.generic.smtpManager.settings.fromName"}
							{fbvElement type="text" id="fromName" value=$fromName label="plugins.generic.smtpManager.settings.fromName.description" subLabelTranslate=true maxlength="150"}
						{/fbvFormSection}
					</div>
				</div>

				{fbvFormSection list="true"}
					{fbvElement type="checkbox" id="suppressCertCheck" checked=$suppressCertCheck label="plugins.generic.smtpManager.settings.suppressCertCheck"}
					<span class="sub_label">{translate key="plugins.generic.smtpManager.settings.suppressCertCheck.description"}</span>
				{/fbvFormSection}
			{/fbvFormArea}

			{fbvFormButtons id="smtpSettingsFormSubmit" submitText="common.save"}
		</form>
	</div>

	<div id="smtpTestingTab" class="pkp_form ui-tabs-panel ui-widget-content ui-corner-bottom" style="display: none;">
		<p class="pkp_help" style="margin-bottom: 20px;">
			{translate key="plugins.generic.smtpManager.test.description"}
		</p>

		<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 1.5rem; width: 100%; box-sizing: border-box;">
			<div class="section" style="margin-bottom: 0;">
				<label for="testRecipient">
					<span class="label">{translate key="plugins.generic.smtpManager.test.recipient"}</span>
					<span class="sub_label">{translate key="plugins.generic.smtpManager.test.recipient.description"}</span>
				</label>
				<div style="display: flex; gap: 10px; align-items: stretch; margin-top: 0.5rem;">
					<input type="email" id="testRecipient" style="flex: 1; height: 2rem; padding: 0 0.5rem; border: 1px solid #ddd; border-radius: 2px; font-size: 0.875rem; box-sizing: border-box;" placeholder="nama@domain.com" />
					<button type="button" id="sendTestMailBtn" class="pkp_button pkp_button_primary" style="white-space: nowrap;">
						{translate key="plugins.generic.smtpManager.test.button"}
					</button>
				</div>
			</div>
			<div id="testMailResult"></div>
		</div>
	</div>

	<div id="smtpLogsTab" class="pkp_form ui-tabs-panel ui-widget-content ui-corner-bottom" style="display: none;">
		<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
			<div class="label" style="font-size: 1rem; font-weight: bold; margin-bottom: 0;">
				{translate key="plugins.generic.smtpManager.logs.heading"}
			</div>
			<div style="display: flex; gap: 8px; flex-shrink: 0;">
				<button type="button" id="refreshLogsBtn" class="pkp_button">
					{translate key="plugins.generic.smtpManager.logs.refresh"}
				</button>
				<button type="button" id="clearLogsBtn" class="pkp_button pkp_button_offset">
					{translate key="plugins.generic.smtpManager.logs.clear"}
				</button>
			</div>
		</div>

		<div class="pkp_controllers_grid" style="border: 1px solid #e2e8f0; border-radius: 4px; box-shadow: none;">
			<table id="smtpDeliveryLogsTable">
				<thead>
					<tr>
						<th>{translate key="plugins.generic.smtpManager.logs.col.date"}</th>
						<th>{translate key="plugins.generic.smtpManager.logs.col.recipient"}</th>
						<th>{translate key="plugins.generic.smtpManager.logs.col.subject"}</th>
						<th>{translate key="plugins.generic.smtpManager.logs.col.host"}</th>
						<th style="text-align: center;">{translate key="plugins.generic.smtpManager.logs.col.status"}</th>
					</tr>
				</thead>
				<tbody>
					{if $deliveryLogs && $deliveryLogs->count()}
						{foreach from=$deliveryLogs item=log}
							<tr class="gridRow">
								<td class="label" style="white-space: nowrap;">{$log->created_at|escape}</td>
								<td class="label">{$log->recipient_email|escape}</td>
								<td class="label">
									{$log->subject|default:'-'|escape}
									{if $log->error_message}
										<div style="font-size: 0.75rem; color: #d00a0a; margin-top: 2px;">{$log->error_message|escape}</div>
									{/if}
								</td>
								<td class="label" style="white-space: nowrap;">
									{$log->smtp_host|default:'default'|escape}{if $log->smtp_port}:{$log->smtp_port|escape}{/if}
								</td>
								<td class="label" style="text-align: center;">
									{if $log->status == 'sent'}
										<span style="display: inline-block; padding: 2px 8px; font-size: 0.75rem; font-weight: 600; border-radius: 12px; background: #def7ec; color: #03543f;">{translate key="plugins.generic.smtpManager.logs.status.sent"}</span>
									{else}
										<span style="display: inline-block; padding: 2px 8px; font-size: 0.75rem; font-weight: 600; border-radius: 12px; background: #fde8e8; color: #9b1c1c;">{translate key="plugins.generic.smtpManager.logs.status.failed"}</span>
									{/if}
								</td>
							</tr>
						{/foreach}
					{else}
						<tr class="empty">
							<td colspan="5">{translate key="plugins.generic.smtpManager.logs.empty"}</td>
						</tr>
					{/if}
				</tbody>
			</table>
		</div>
	</div>
</div>
