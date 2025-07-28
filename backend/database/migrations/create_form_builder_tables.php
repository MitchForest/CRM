<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFormBuilderTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Create form_builder_forms table
        Schema::create('form_builder_forms', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->json('fields');
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->char('created_by', 36)->nullable();
            $table->datetime('date_entered')->nullable();
            $table->datetime('date_modified')->nullable();
            $table->boolean('deleted')->default(false);
            
            $table->index('name');
            $table->index('is_active');
            $table->index('deleted');
        });

        // Create form_builder_submissions table
        Schema::create('form_builder_submissions', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('form_id', 36);
            $table->json('data');
            $table->char('lead_id', 36)->nullable();
            $table->char('contact_id', 36)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->datetime('date_entered')->nullable();
            $table->boolean('deleted')->default(false);
            
            $table->index('form_id');
            $table->index('lead_id');
            $table->index('contact_id');
            $table->index('date_entered');
            $table->index('deleted');
            
            $table->foreign('form_id')->references('id')->on('form_builder_forms');
            $table->foreign('lead_id')->references('id')->on('leads');
            $table->foreign('contact_id')->references('id')->on('contacts');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('form_builder_submissions');
        Schema::dropIfExists('form_builder_forms');
    }
}