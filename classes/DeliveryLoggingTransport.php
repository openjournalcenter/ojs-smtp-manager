<?php

namespace APP\plugins\generic\smtpManager\classes;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Throwable;

class DeliveryLoggingTransport implements TransportInterface
{
	protected TransportInterface $inner;
	protected int $contextId;
	protected ?string $smtpHost;
	protected ?int $smtpPort;

	public function __construct(TransportInterface $inner, int $contextId, ?string $smtpHost = null, ?int $smtpPort = null)
	{
		$this->inner = $inner;
		$this->contextId = $contextId;
		$this->smtpHost = $smtpHost;
		$this->smtpPort = $smtpPort;
	}

	public function getOriginalTransport(): TransportInterface
	{
		$current = $this->inner;
		while ($current instanceof self) {
			$current = $current->inner;
		}
		return $current;
	}

	public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
	{
		$senderEmail = '';
		$recipientEmail = '';
		$subject = '';

		if ($message instanceof Email) {
			$froms = $message->getFrom();
			if (!empty($froms)) {
				$senderEmail = $froms[0]->getAddress();
			}
			$tos = [];
			foreach ($message->getTo() as $to) {
				$tos[] = $to->getAddress();
			}
			$recipientEmail = implode(', ', $tos);
			$subject = (string) $message->getSubject();
		}

		if ($envelope) {
			if ($senderEmail === '' && $envelope->getSender()) {
				$senderEmail = $envelope->getSender()->getAddress();
			}
			if ($recipientEmail === '') {
				$recipients = [];
				foreach ($envelope->getRecipients() as $rec) {
					$recipients[] = $rec->getAddress();
				}
				$recipientEmail = implode(', ', $recipients);
			}
		}

		try {
			$sentMessage = $this->inner->send($message, $envelope);

			$this->logDelivery($senderEmail, $recipientEmail, $subject, 'sent', null);

			return $sentMessage;
		} catch (Throwable $e) {
			$this->logDelivery($senderEmail, $recipientEmail, $subject, 'failed', $e->getMessage());
			throw $e;
		}
	}

	public function __toString(): string
	{
		return (string) $this->inner;
	}

	protected function logDelivery(string $sender, string $recipient, string $subject, string $status, ?string $errorMessage): void
	{
		try {
			DB::table('smtp_delivery_logs')->insert([
				'context_id' => $this->contextId,
				'sender_email' => $sender,
				'recipient_email' => $recipient,
				'subject' => mb_substr($subject, 0, 500),
				'status' => $status,
				'error_message' => $errorMessage,
				'smtp_host' => $this->smtpHost,
				'smtp_port' => $this->smtpPort,
				'created_at' => date('Y-m-d H:i:s'),
			]);
		} catch (Throwable $dbEx) {
			error_log('DeliveryLoggingTransport logDelivery error: ' . $dbEx->getMessage());
		}
	}
}
