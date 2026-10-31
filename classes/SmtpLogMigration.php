<?php

namespace APP\plugins\generic\smtpManager\classes;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SmtpLogMigration extends Migration
{
	public function up(): void
	{
		if (!Schema::hasTable('smtp_delivery_logs')) {
			Schema::create('smtp_delivery_logs', function (Blueprint $table) {
				$table->bigIncrements('log_id');
				$table->bigInteger('context_id')->default(0)->index('smtp_log_context_id');
				$table->string('sender_email', 255)->nullable();
				$table->text('recipient_email');
				$table->string('subject', 500)->nullable();
				$table->string('status', 30)->default('sent');
				$table->text('error_message')->nullable();
				$table->string('smtp_host', 255)->nullable();
				$table->integer('smtp_port')->nullable();
				$table->dateTime('created_at')->useCurrent()->index('smtp_log_created_at');
			});
		}
	}

	public function down(): void
	{
		Schema::dropIfExists('smtp_delivery_logs');
	}
}
