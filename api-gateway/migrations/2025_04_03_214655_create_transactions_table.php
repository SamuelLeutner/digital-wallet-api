<?php

use App\Model\Transaction;
use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('tx_id')->unique();
            $table->foreignId('payer_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('payee_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('payer_wallet_id')->constrained('wallets')->onDelete('restrict');
            $table->foreignId('payee_wallet_id')->constrained('wallets')->onDelete('restrict');
            $table->enum('status', Transaction::TRANSACTION_STATUS)->default(Transaction::STATUS_PENDING);
            $table->decimal('amount', 15, 2)->nullable(false);
            $table->uuid('saga_id')->nullable();
            $table->unsignedTinyInteger('saga_step')->default(0);
            $table->json('saga_steps_completed')->nullable();
            $table->timestamp('compensated_at')->nullable();
            $table->json('compensation_data')->nullable();
            $table->datetimes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
