<?php

use App\Enums\MeetingParticipantStatus;
use App\Enums\MeetingStatus;
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
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('meeting_link')->nullable();
            $table->string('status', 30)->default(MeetingStatus::Scheduled->value);
            $table->timestamps();
        });

        Schema::create('meeting_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 30)->default(MeetingParticipantStatus::Invited->value);
            $table->timestamps();

            $table->unique(['meeting_id', 'user_id']);
        });

        Schema::create('meeting_transcripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('speaker_name');
            $table->string('timestamp_offset', 20)->nullable(); // e.g. "14:02"
            $table->text('transcript_text');
            $table->timestamp('spoken_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_meeting_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->unique()->constrained('meetings')->cascadeOnDelete();
            $table->text('summary');
            $table->json('key_points')->nullable(); // Figma key points
            $table->json('decisions_made')->nullable();
            $table->json('action_items')->nullable();
            $table->json('assigned_tasks')->nullable();
            $table->json('risks')->nullable();
            $table->json('next_steps')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_meeting_summaries');
        Schema::dropIfExists('meeting_transcripts');
        Schema::dropIfExists('meeting_user');
        Schema::dropIfExists('meetings');
    }
};
