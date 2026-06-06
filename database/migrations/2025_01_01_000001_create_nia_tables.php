<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add Nia columns to existing users table
        Schema::table('users', function (Blueprint $table) {
            $table->integer('age')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('occupation')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->enum('plan', ['free', 'pro'])->default('free');
            $table->boolean('is_admin')->default(false);
            $table->boolean('onboarded')->default(false);
            $table->integer('daily_message_count')->default(0);
            $table->date('message_count_date')->nullable();
            $table->timestamp('pro_expires_at')->nullable();
        });

        // Memories table
        Schema::create('memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('goals')->default('[]');
            $table->json('habits')->default('[]');
            $table->json('reminders')->default('[]');
            $table->json('notes')->default('[]');
            $table->json('habit_logs')->default('{}');
            $table->json('health')->nullable();
            $table->json('whatsapp_logs')->default('[]');
            $table->timestamps();
        });

        // Conversations table
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant']);
            $table->text('content');
            $table->timestamps();
        });

        // Subscriptions table
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('paystack_reference')->unique();
            $table->string('paystack_subscription_code')->nullable();
            $table->enum('plan', ['free', 'pro'])->default('pro');
            $table->enum('status', ['active', 'cancelled', 'expired'])->default('active');
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('NGN');
            $table->enum('interval', ['monthly', 'annually'])->default('monthly');
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // WhatsApp queue table
        Schema::create('whatsapp_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('phone');
            $table->text('message');
            $table->string('reminder_title')->nullable();
            $table->timestamp('send_at');
            $table->boolean('sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->boolean('recurring')->default(false);
            $table->string('recurrence_rule')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_queue');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('memories');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['age','marital_status','occupation','whatsapp_number','plan','is_admin','onboarded','daily_message_count','message_count_date','pro_expires_at']);
        });
    }
};
