<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateActivityTrackingTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Create visitors table
        Schema::create('activity_tracking_visitors', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_id')->unique();
            $table->timestamp('first_visit');
            $table->timestamp('last_activity')->nullable();
            $table->integer('total_visits')->default(0);
            $table->integer('total_page_views')->default(0);
            $table->string('ip_address')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('browser')->nullable();
            $table->string('os')->nullable();
            $table->string('device_type')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->boolean('is_bot')->default(false);
            $table->timestamps();
            
            $table->index('visitor_id');
            $table->index('last_activity');
        });

        // Create sessions table
        Schema::create('activity_tracking_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->string('visitor_id');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration')->nullable(); // in seconds
            $table->integer('page_views')->default(0);
            $table->integer('events')->default(0);
            $table->string('landing_page')->nullable();
            $table->string('exit_page')->nullable();
            $table->string('referrer')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            
            $table->index('session_id');
            $table->index('visitor_id');
            $table->index('started_at');
        });

        // Create page views table
        Schema::create('activity_tracking_page_views', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_id');
            $table->string('session_id');
            $table->string('page_url');
            $table->string('page_title')->nullable();
            $table->timestamp('viewed_at');
            $table->integer('time_on_page')->nullable(); // in seconds
            $table->integer('scroll_depth')->nullable(); // percentage
            $table->integer('click_count')->default(0);
            $table->string('referrer')->nullable();
            $table->string('screen_resolution')->nullable();
            $table->string('viewport_size')->nullable();
            $table->boolean('is_bounce')->default(false);
            $table->json('custom_data')->nullable();
            $table->timestamps();
            
            $table->index('visitor_id');
            $table->index('session_id');
            $table->index('page_url');
            $table->index('viewed_at');
        });

        // Create events table
        Schema::create('activity_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_id');
            $table->string('session_id');
            $table->string('event_type');
            $table->string('event_name');
            $table->string('event_category')->nullable();
            $table->json('event_data')->nullable();
            $table->timestamp('occurred_at');
            $table->string('page_url')->nullable();
            $table->timestamps();
            
            $table->index('visitor_id');
            $table->index('session_id');
            $table->index('event_type');
            $table->index('occurred_at');
        });

        // Create conversions table
        Schema::create('activity_tracking_conversions', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_id');
            $table->string('session_id');
            $table->string('conversion_type');
            $table->decimal('conversion_value', 10, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->json('metadata')->nullable();
            $table->timestamp('converted_at');
            $table->timestamps();
            
            $table->index('visitor_id');
            $table->index('session_id');
            $table->index('conversion_type');
            $table->index('converted_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('activity_tracking_conversions');
        Schema::dropIfExists('activity_tracking_events');
        Schema::dropIfExists('activity_tracking_page_views');
        Schema::dropIfExists('activity_tracking_sessions');
        Schema::dropIfExists('activity_tracking_visitors');
    }
}