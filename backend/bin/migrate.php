#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

echo "Running activity tracking migration...\n";

try {
    $schema = Capsule::schema();
    
    // Create visitors table
    if (!$schema->hasTable('activity_tracking_visitors')) {
        $schema->create('activity_tracking_visitors', function (Blueprint $table) {
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
        echo "✓ Created activity_tracking_visitors table\n";
    } else {
        echo "⚠ Table activity_tracking_visitors already exists\n";
    }

    // Create sessions table
    if (!$schema->hasTable('activity_tracking_sessions')) {
        $schema->create('activity_tracking_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->string('visitor_id');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration')->nullable();
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
        echo "✓ Created activity_tracking_sessions table\n";
    } else {
        echo "⚠ Table activity_tracking_sessions already exists\n";
    }

    // Create page views table
    if (!$schema->hasTable('activity_tracking_page_views')) {
        $schema->create('activity_tracking_page_views', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_id');
            $table->string('session_id');
            $table->string('page_url');
            $table->string('page_title')->nullable();
            $table->timestamp('viewed_at');
            $table->integer('time_on_page')->nullable();
            $table->integer('scroll_depth')->nullable();
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
        echo "✓ Created activity_tracking_page_views table\n";
    } else {
        echo "⚠ Table activity_tracking_page_views already exists\n";
    }

    // Create events table
    if (!$schema->hasTable('activity_tracking_events')) {
        $schema->create('activity_tracking_events', function (Blueprint $table) {
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
        echo "✓ Created activity_tracking_events table\n";
    } else {
        echo "⚠ Table activity_tracking_events already exists\n";
    }

    echo "\n✅ Migration completed successfully!\n";
    
} catch (\Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}