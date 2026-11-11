<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Standardized units of measure (FR05)
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('abbreviation', 15)->unique();
            $table->timestamps();
        });

        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category', 30); // food, education, workshop, relief, community
            $table->text('description')->nullable();
            $table->foreignId('coordinator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('location')->nullable();
            $table->date('activity_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('volunteer_slots')->default(0);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('scheduled'); // scheduled, completed, cancelled
            $table->timestamps();
        });

        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->uuid('client_uuid')->nullable()->unique(); // offline de-duplication
            $table->string('first_name');
            $table->string('last_name');
            $table->date('birth_date')->nullable();
            $table->string('sex', 10)->nullable();
            $table->string('contact_number', 30)->nullable();
            $table->string('barangay');
            $table->string('address')->nullable();
            $table->unsignedSmallInteger('household_size')->default(1);
            $table->string('category', 30); // family, learner, senior, pwd, individual
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['last_name', 'first_name']);
        });

        Schema::create('donors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20); // individual, institution
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete(); // donor portal login
            $table->timestamps();
        });

        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 20)->unique();
            $table->foreignId('donor_id')->constrained()->restrictOnDelete();
            $table->string('type', 10); // cash, in_kind
            $table->string('item_name')->nullable(); // in-kind only
            $table->decimal('quantity', 14, 2); // pesos for cash, units for in-kind
            $table->foreignId('unit_id')->nullable()->constrained()->restrictOnDelete(); // in-kind only
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained()->nullOnDelete();
            $table->date('received_at');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('distributions', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 20)->unique();
            $table->uuid('client_uuid')->nullable()->unique();
            $table->foreignId('activity_id')->constrained()->restrictOnDelete();
            $table->string('item_name');
            $table->decimal('quantity', 14, 2);
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->date('distributed_at');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Which donations funded a distribution, and how much was drawn from each (FR03)
        Schema::create('distribution_funding', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distribution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('donation_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();
            $table->unique(['distribution_id', 'donation_id']);
        });

        Schema::create('beneficiary_distribution', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distribution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('beneficiary_id')->constrained()->cascadeOnDelete();
            $table->unique(['distribution_id', 'beneficiary_id']);
        });

        Schema::create('volunteers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('skills')->nullable();
            $table->string('availability')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('volunteer_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('volunteer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->string('task')->nullable();
            $table->string('status', 20)->default('assigned'); // assigned, completed, no_show, cancelled
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['volunteer_id', 'activity_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->nullable()->unique();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['recipient_id', 'read_at']);
        });

        // Audit trail for granted and denied actions (FR08)
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->string('description');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'messages', 'volunteer_assignments', 'volunteers', 'beneficiary_distribution',
            'distribution_funding', 'distributions', 'donations', 'donors', 'beneficiaries', 'activities',
            'programs', 'units'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
