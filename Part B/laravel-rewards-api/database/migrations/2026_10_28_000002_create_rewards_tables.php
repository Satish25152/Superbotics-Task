<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('standard');
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('category_limits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('monthly_limit')->default(2);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('points_cost');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['category_id', 'is_active']);
        });

        Schema::create('redemptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('points_spent');
            $table->timestamp('redeemed_at');
            $table->timestamps();
            $table->index(['user_id', 'category_id', 'redeemed_at'], 'redemptions_user_category_month');
            $table->index(['redeemed_at', 'category_id'], 'redemptions_date_category');
        });

        Schema::create('points_ledger', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type', 10); // earn or spend
            $table->unsignedInteger('points'); // positive magnitude
            $table->foreignId('redemption_id')->nullable()->unique()->constrained('redemptions')->restrictOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('points_ledger');
        Schema::dropIfExists('redemptions');
        Schema::dropIfExists('products');
        Schema::dropIfExists('category_limits');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('profiles');
    }
};
