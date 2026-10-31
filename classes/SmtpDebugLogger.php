<?php

namespace APP\plugins\generic\smtpManager\classes;

use Psr\Log\AbstractLogger;
use Stringable;

class SmtpDebugLogger extends AbstractLogger
{
	protected array $logs = [];

	public function log($level, string|Stringable $message, array $context = []): void
	{
		$this->logs[] = (string) $message;
	}

	public function recordClient(string $line): void
	{
		$this->logs[] = '>> ' . trim($line);
	}

	public function recordServer(string $line): void
	{
		$lines = explode("\n", trim($line));
		foreach ($lines as $l) {
			$l = trim($l);
			if ($l !== '') {
				$this->logs[] = '<< ' . $l;
			}
		}
	}

	public function recordInfo(string $info): void
	{
		$this->logs[] = $info;
	}

	public function getLogs(): array
	{
		return $this->logs;
	}

	public function getFormattedLogs(): string
	{
		return implode("\n", $this->logs);
	}
}
