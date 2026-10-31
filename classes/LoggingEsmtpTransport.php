<?php

namespace APP\plugins\generic\smtpManager\classes;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class LoggingEsmtpTransport extends EsmtpTransport
{
	protected ?SmtpDebugLogger $debugLogger = null;

	public function setDebugLogger(SmtpDebugLogger $logger): void
	{
		$this->debugLogger = $logger;
	}

	public function executeCommand(string $command, array $codes): string
	{
		if ($this->debugLogger) {
			$trimmed = trim($command);
			$this->debugLogger->recordClient($trimmed);
		}

		$response = parent::executeCommand($command, $codes);

		if ($this->debugLogger) {
			$this->debugLogger->recordServer($response);
		}

		return $response;
	}

	protected function doSend(SentMessage $message): void
	{
		if ($this->debugLogger) {
			$stream = $this->getStream();
			if ($stream instanceof \Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream) {
				$scheme = $stream->isTLS() ? 'smtps' : 'smtp';
				$this->debugLogger->recordInfo(sprintf('Connected to %s://%s:%d/', $scheme, $stream->getHost(), $stream->getPort()));
			}
		}

		parent::doSend($message);

		if ($this->debugLogger) {
			$rawMessage = $message->getMessage()->toString();
			$headers = substr($rawMessage, 0, strpos($rawMessage, "\r\n\r\n") ?: strlen($rawMessage));
			$headerLines = explode("\r\n", $headers);
			foreach ($headerLines as $hl) {
				if (preg_match('/^(From|To|Date|Subject|Message-ID|MIME-Version|Content-Type)/i', $hl)) {
					$this->debugLogger->recordClient($hl);
				}
			}
		}
	}
}
