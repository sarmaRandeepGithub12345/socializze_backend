<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            // $table->uuid('id')->primary();
            $table->string('id')->primary();
            $table->foreignUuid('customer_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('seller_id')->constrained('users')->onDelete('cascade');
            //UPI,Credit,Debit
            $table->text('currency'); //INR,USD,EURO,REM
            // $table->text('order_id'); //inv_2948475
            $table->text('amount'); //3400.00
            $table->string('expire_at')->nullable();
            $table->text('session_id');

            //after payment expired ,rejected,failed ,success
            //edrrforfjk12346
            $table->text('status'); //ACTIVE->SUCCESS, ACTIVE->FAILED,ACTIVE->REJECTED,

            $table->text('payment_message')->nullable();
            $table->text('type')->nullable();
            $table->text('transaction_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
