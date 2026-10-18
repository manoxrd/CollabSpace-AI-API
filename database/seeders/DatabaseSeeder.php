<?php

namespace Database\Seeders;

use App\Enums\AiMessageRole;
use App\Enums\ChatChannelType;
use App\Enums\MeetingParticipantStatus;
use App\Enums\MeetingStatus;
use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Enums\ReportStatus;
use App\Enums\TaskActivityType;
use App\Enums\TaskStatus;
use App\Enums\UserAvailability;
use App\Enums\UserRole;
use App\Models\AiConversation;
use App\Models\AiMeetingSummary;
use App\Models\AiMessage;
use App\Models\ChatChannel;
use App\Models\ChatMessage;
use App\Models\File;
use App\Models\Folder;
use App\Models\Meeting;
use App\Models\MeetingTranscript;
use App\Models\Project;
use App\Models\Report;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskComment;
use App\Models\TaskTimeLog;
use App\Models\Team;
use App\Models\User;
use App\Models\UserActivity;
use App\Notifications\MeetingInviteNotification;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with Figma-accurate demo data.
     */
    public function run(): void
    {
        // 1. Seed Users (Matching Figma Screens)
        $password = Hash::make('password');

        $admin = User::firstOrCreate(
            ['email' => 'admin@collabspace.ai'],
            [
                'name' => 'Admin User',
                'password' => $password,
                'role' => UserRole::Admin,
                'job_title' => 'System Administrator',
                'years_of_experience' => 10,
                'phone' => '+1 555-0199',
                'avatar_url' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150',
                'availability_status' => UserAvailability::Available,
                'about' => 'CollabSpace platform administrator overseeing infrastructure, permissions, and AI configurations.',
                'hours_worked' => 520,
                'status' => 'active',
                'joined_at' => '2021-01-10',
            ]
        );

        $mohamedSalah = User::firstOrCreate(
            ['email' => 'mohamed.salah@collabspace.ai'],
            [
                'name' => 'Mohamed Salah',
                'password' => $password,
                'role' => UserRole::TeamMember,
                'job_title' => 'UI/UX Designer',
                'years_of_experience' => 3,
                'phone' => '+20 102-456-7890',
                'avatar_url' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150',
                'availability_status' => UserAvailability::Available,
                'about' => 'Experienced UI/UX Designer with over 3 years of expertise in creating user-centered digital experiences. Passionate about design systems, accessibility, and leading teams to deliver exceptional products.',
                'hours_worked' => 345,
                'current_team_project' => 'CollabSpace - Web Platform',
                'status' => 'active',
                'joined_at' => '2022-04-01',
            ]
        );

        $mohanadWahib = User::firstOrCreate(
            ['email' => 'mohanad.wahib@collabspace.ai'],
            [
                'name' => 'Mohanad Wahib',
                'password' => $password,
                'role' => UserRole::ProjectManager,
                'job_title' => 'UI/UX Leader',
                'years_of_experience' => 6,
                'phone' => '+20 101-234-5678',
                'avatar_url' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150',
                'availability_status' => UserAvailability::Busy,
                'about' => 'Design team lead focusing on design architecture and cross-team alignment.',
                'hours_worked' => 480,
                'status' => 'active',
                'joined_at' => '2021-06-15',
            ]
        );

        $saraHassan = User::firstOrCreate(
            ['email' => 'sara.hassan@collabspace.ai'],
            [
                'name' => 'Sara Hassan',
                'password' => $password,
                'role' => UserRole::TeamMember,
                'job_title' => 'UI Designer',
                'years_of_experience' => 2,
                'phone' => '+20 109-876-5432',
                'avatar_url' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150',
                'availability_status' => UserAvailability::Available,
                'about' => 'Visual and UI designer dedicated to responsive layouts and micro-interactions.',
                'hours_worked' => 290,
                'status' => 'active',
                'joined_at' => '2023-01-15',
            ]
        );

        $wahibSalem = User::firstOrCreate(
            ['email' => 'wahib.salem@collabspace.ai'],
            [
                'name' => 'Wahib Salem',
                'password' => $password,
                'role' => UserRole::TeamMember,
                'job_title' => 'Frontend Developer',
                'years_of_experience' => 4,
                'phone' => '+20 105-123-9876',
                'avatar_url' => 'https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?w=150',
                'availability_status' => UserAvailability::Available,
                'about' => 'Frontend specialist working with React, Tailwind, and interactive widgets.',
                'hours_worked' => 380,
                'status' => 'active',
                'joined_at' => '2022-09-01',
            ]
        );

        $ahmedHassan = User::firstOrCreate(
            ['email' => 'ahmed.hassan@collabspace.ai'],
            [
                'name' => 'Ahmed Hassan',
                'password' => $password,
                'role' => UserRole::TeamMember,
                'job_title' => 'Backend Engineer',
                'years_of_experience' => 5,
                'phone' => '+20 106-555-4321',
                'avatar_url' => 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=150',
                'availability_status' => UserAvailability::Available,
                'about' => 'Senior Backend Engineer specialized in Laravel, MySQL, RESTful APIs, and cloud services.',
                'hours_worked' => 440,
                'status' => 'active',
                'joined_at' => '2022-02-10',
            ]
        );

        $aminaElSherif = User::firstOrCreate(
            ['email' => 'amina.elsherif@collabspace.ai'],
            [
                'name' => 'Amina El-Sherif',
                'password' => $password,
                'role' => UserRole::ProjectManager,
                'job_title' => 'Product Manager',
                'years_of_experience' => 5,
                'phone' => '+20 100-333-7777',
                'avatar_url' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=150',
                'availability_status' => UserAvailability::Available,
                'about' => 'Amina is a Product Manager from Cairo who values clear, organized team updates and centralizing collaboration into a single workspace.',
                'hours_worked' => 410,
                'status' => 'active',
                'joined_at' => '2022-08-01',
            ]
        );

        // 2. Seed Teams (Matching Figma)
        $uiuxTeam = Team::firstOrCreate(
            ['name' => 'UI/UX Team'],
            [
                'description' => 'User experience research, wireframing, design systems, and product prototyping.',
                'leader_id' => $mohanadWahib->id,
                'created_by' => $admin->id,
            ]
        );
        $uiuxTeam->members()->syncWithoutDetaching([
            $mohanadWahib->id => ['role_in_team' => 'leader', 'joined_at' => now()],
            $mohamedSalah->id => ['role_in_team' => 'member', 'joined_at' => now()],
            $saraHassan->id => ['role_in_team' => 'member', 'joined_at' => now()],
        ]);

        $frontendTeam = Team::firstOrCreate(
            ['name' => 'Frontend Team'],
            [
                'description' => 'Web and mobile user interface implementation and component development.',
                'leader_id' => $wahibSalem->id,
                'created_by' => $admin->id,
            ]
        );
        $frontendTeam->members()->syncWithoutDetaching([
            $wahibSalem->id => ['role_in_team' => 'leader', 'joined_at' => now()],
            $mohamedSalah->id => ['role_in_team' => 'member', 'joined_at' => now()],
        ]);

        $backendTeam = Team::firstOrCreate(
            ['name' => 'Backend Team'],
            [
                'description' => 'API development, database design, caching, business logic, and security.',
                'leader_id' => $ahmedHassan->id,
                'created_by' => $admin->id,
            ]
        );
        $backendTeam->members()->syncWithoutDetaching([
            $ahmedHassan->id => ['role_in_team' => 'leader', 'joined_at' => now()],
        ]);

        $marketingTeam = Team::firstOrCreate(
            ['name' => 'Marketing Team'],
            [
                'description' => 'Product positioning, user acquisition campaigns, and content strategy.',
                'leader_id' => $aminaElSherif->id,
                'created_by' => $admin->id,
            ]
        );

        // 3. Seed Projects (Strictly matching Figma Screenshot 2)
        $alphaProject = Project::firstOrCreate(
            ['name' => 'Alpha'],
            [
                'category' => 'SaaS',
                'description' => 'The project focuses on organizing tasks, tracking progress, and coordinating team efforts to ensure timely delivery and clear visibility across all project activities.',
                'start_date' => '2025-10-12',
                'deadline' => '2025-12-25',
                'priority' => Priority::High,
                'status' => ProjectStatus::Active, // Figma strictly has active and done
                'progress_percentage' => 45.00,
                'created_by' => $aminaElSherif->id,
            ]
        );
        $alphaProject->teams()->syncWithoutDetaching([$uiuxTeam->id, $frontendTeam->id, $backendTeam->id, $marketingTeam->id]);
        $alphaProject->members()->syncWithoutDetaching([
            $aminaElSherif->id => ['role' => 'manager', 'joined_at' => now()],
            $mohanadWahib->id => ['role' => 'manager', 'joined_at' => now()],
            $mohamedSalah->id => ['role' => 'contributor', 'joined_at' => now()],
            $saraHassan->id => ['role' => 'contributor', 'joined_at' => now()],
            $wahibSalem->id => ['role' => 'contributor', 'joined_at' => now()],
            $ahmedHassan->id => ['role' => 'contributor', 'joined_at' => now()],
        ]);

        $sepetGoProject = Project::firstOrCreate(
            ['name' => 'SepetGo'],
            [
                'category' => 'Web Platform',
                'description' => 'E-commerce platform optimization, checkout funnel redesign, and payment integration.',
                'start_date' => '2025-08-01',
                'deadline' => '2025-10-10',
                'priority' => Priority::Medium,
                'status' => ProjectStatus::Done, // Figma: Done
                'progress_percentage' => 100.00,
                'created_by' => $aminaElSherif->id,
            ]
        );
        $sepetGoProject->teams()->syncWithoutDetaching([$uiuxTeam->id, $frontendTeam->id]);
        $sepetGoProject->members()->syncWithoutDetaching([
            $mohamedSalah->id => ['role' => 'contributor', 'joined_at' => now()],
            $wahibSalem->id => ['role' => 'contributor', 'joined_at' => now()],
        ]);

        // 4. Seed Folders & Files (Matching Figma Recent Files)
        $designFolder = Folder::firstOrCreate(
            ['project_id' => $alphaProject->id, 'name' => 'Design Assets'],
            ['created_by' => $mohamedSalah->id]
        );

        $file1 = File::firstOrCreate(
            ['name' => 'UX_Research_Summary.pdf', 'project_id' => $alphaProject->id],
            [
                'folder_id' => $designFolder->id,
                'path' => 'files/UX_Research_Summary.pdf',
                'disk' => 'public',
                'mime_type' => 'application/pdf',
                'size' => 12582912, // 12 MB
                'downloads_count' => 18,
                'uploaded_by' => $mohanadWahib->id,
            ]
        );

        $file2 = File::firstOrCreate(
            ['name' => 'Dashboard_Layout_React.jsx', 'project_id' => $alphaProject->id],
            [
                'folder_id' => $designFolder->id,
                'path' => 'files/Dashboard_Layout_React.jsx',
                'disk' => 'public',
                'mime_type' => 'text/javascript',
                'size' => 24576,
                'downloads_count' => 7,
                'uploaded_by' => $wahibSalem->id,
            ]
        );

        $file3 = File::firstOrCreate(
            ['name' => 'Database_Schema_v2.sql', 'project_id' => $alphaProject->id],
            [
                'folder_id' => $designFolder->id,
                'path' => 'files/Database_Schema_v2.sql',
                'disk' => 'public',
                'mime_type' => 'application/sql',
                'size' => 81920,
                'downloads_count' => 12,
                'uploaded_by' => $ahmedHassan->id,
            ]
        );

        // 5. Seed Tasks (Matching Figma Kanban board: To do, In Progress, In Review, Completed Tasks)
        // Task 1 (Todo)
        $task1 = Task::firstOrCreate(
            ['name' => 'Creat Wireframes', 'project_id' => $alphaProject->id],
            [
                'team_id' => $uiuxTeam->id,
                'created_by' => $mohanadWahib->id,
                'description' => 'Draft low-fidelity wireframes for the main sign-in, onboarding, and dashboard flows.',
                'status' => TaskStatus::Todo,
                'priority' => Priority::High,
                'start_date' => now()->toDateString(),
                'due_date' => now()->toDateString(),
                'progress_percentage' => 82.00,
            ]
        );
        $task1->assignees()->syncWithoutDetaching([$mohamedSalah->id, $saraHassan->id]);

        // Task 2 (In Progress)
        $task2 = Task::firstOrCreate(
            ['name' => 'User Research', 'project_id' => $alphaProject->id],
            [
                'team_id' => $uiuxTeam->id,
                'created_by' => $mohamedSalah->id,
                'description' => 'Conduct 5 user interviews with product managers to validate team collaboration pain points.',
                'status' => TaskStatus::InProgress,
                'priority' => Priority::Medium,
                'start_date' => now()->subDays(5)->toDateString(),
                'due_date' => now()->addDays(2)->toDateString(),
                'progress_percentage' => 52.00,
            ]
        );
        $task2->assignees()->syncWithoutDetaching([$mohamedSalah->id]);

        // Task 3 (In Review)
        $task3 = Task::firstOrCreate(
            ['name' => 'Design System Updates', 'project_id' => $alphaProject->id],
            [
                'team_id' => $uiuxTeam->id,
                'created_by' => $saraHassan->id,
                'description' => 'Update color palette tokens, button styles, typography scales, and iconography set.',
                'status' => TaskStatus::InReview,
                'priority' => Priority::Low,
                'start_date' => now()->subDays(8)->toDateString(),
                'due_date' => now()->addDays(4)->toDateString(),
                'progress_percentage' => 43.00,
            ]
        );
        $task3->assignees()->syncWithoutDetaching([$saraHassan->id]);

        // Task 4 (Completed Tasks)
        $task4 = Task::firstOrCreate(
            ['name' => 'Accessibility Audit', 'project_id' => $alphaProject->id],
            [
                'team_id' => $uiuxTeam->id,
                'created_by' => $mohamedSalah->id,
                'description' => 'Ensure WCAG AA compliance for contrast ratios, aria-labels, and keyboard tab navigation.',
                'status' => TaskStatus::Completed,
                'priority' => Priority::Low,
                'start_date' => now()->subDays(15)->toDateString(),
                'due_date' => now()->subDays(2)->toDateString(),
                'completed_at' => now()->subDays(2),
                'progress_percentage' => 100.00,
            ]
        );
        $task4->assignees()->syncWithoutDetaching([$mohamedSalah->id]);

        // Task Activity Logs
        TaskActivity::firstOrCreate(
            ['task_id' => $task1->id, 'user_id' => $mohamedSalah->id, 'type' => TaskActivityType::Comment],
            ['comment' => 'Initial wireframes for the auth modal are uploaded to the design system.']
        );

        // Dedicated Task Comments (Figma task discussion)
        TaskComment::firstOrCreate(
            ['task_id' => $task1->id, 'user_id' => $mohanadWahib->id],
            ['comment' => 'Please make sure the Google OAuth button matches the brand guideline spacing.']
        );

        TaskComment::firstOrCreate(
            ['task_id' => $task1->id, 'user_id' => $mohamedSalah->id],
            ['comment' => 'Updated! Button height is 48px with 12px margin.']
        );

        // Task Time Logs (Backing the 345 hours worked in Figma profile)
        TaskTimeLog::firstOrCreate(
            ['task_id' => $task1->id, 'user_id' => $mohamedSalah->id, 'date' => now()->subDays(1)->toDateString()],
            ['hours' => 4.5, 'description' => 'Wireframing onboarding and auth flows']
        );

        TaskTimeLog::firstOrCreate(
            ['task_id' => $task2->id, 'user_id' => $mohamedSalah->id, 'date' => now()->subDays(2)->toDateString()],
            ['hours' => 6.0, 'description' => 'User interviews synthesis and persona refinement']
        );

        // 6. Seed Meetings, Transcripts & AI Summaries (Strictly matching Figma Screenshot 3)
        $meeting = Meeting::firstOrCreate(
            ['title' => 'Design Review', 'project_id' => $alphaProject->id],
            [
                'created_by' => $mohanadWahib->id,
                'description' => 'Review latest wireframe design iterations, auth flow improvements, and typography.',
                'date' => now()->toDateString(),
                'start_time' => '14:00:00',
                'end_time' => '15:00:00',
                'meeting_link' => 'https://meet.collabspace.ai/design-review-alpha',
                'status' => MeetingStatus::Scheduled,
            ]
        );
        $meeting->participants()->syncWithoutDetaching([
            $mohanadWahib->id => ['status' => MeetingParticipantStatus::Attended],
            $mohamedSalah->id => ['status' => MeetingParticipantStatus::Attended],
            $saraHassan->id => ['status' => MeetingParticipantStatus::Accepted],
            $aminaElSherif->id => ['status' => MeetingParticipantStatus::Accepted],
        ]);

        // Meeting Transcripts (Figma right panel)
        MeetingTranscript::firstOrCreate(
            ['meeting_id' => $meeting->id, 'speaker_name' => 'Mohanad Wahib', 'timestamp_offset' => '14:02'],
            [
                'user_id' => $mohanadWahib->id,
                'transcript_text' => 'Good afternoon, thanks for joining on time. Today we will focus on reviewing the latest wireframe designs for the sign-up flow.',
                'spoken_at' => now()->subMinutes(58),
            ]
        );

        MeetingTranscript::firstOrCreate(
            ['meeting_id' => $meeting->id, 'speaker_name' => 'Mohamed Salah', 'timestamp_offset' => '14:05'],
            [
                'user_id' => $mohamedSalah->id,
                'transcript_text' => 'Thanks Mohanad. I have updated the wireframe based on user feedback. The main change is the simplified input fields and Google OAuth option.',
                'spoken_at' => now()->subMinutes(55),
            ]
        );

        MeetingTranscript::firstOrCreate(
            ['meeting_id' => $meeting->id, 'speaker_name' => 'Mohanad Wahib', 'timestamp_offset' => '14:07'],
            [
                'user_id' => $mohanadWahib->id,
                'transcript_text' => 'From my perspective, the page layout looks great. Refine the typography and spacing before sending this to the frontend team.',
                'spoken_at' => now()->subMinutes(53),
            ]
        );

        // AI Meeting Summary (Figma bottom panel Key Points)
        AiMeetingSummary::firstOrCreate(
            ['meeting_id' => $meeting->id],
            [
                'summary' => 'Comprehensive review of the signup wireframes, resulting in design approval with minor typography refinements.',
                'key_points' => [
                    'The team reviewed the latest wireframe design and agreed on the primary layout.',
                    'Overall feedback is positive, with minor suggestions for the signup flow.',
                    'Final assets need to be completed before next Wednesday.',
                    'Next sync on Thursday morning was scheduled to review next steps.',
                ],
                'decisions_made' => [
                    'Approved simplified 2-step registration process.',
                    'Prioritize Google OAuth button placement at the top of the form.',
                ],
                'action_items' => [
                    'Mohamed Salah to export finalized SVGs and tokens.',
                    'Wahib Salem to prepare React form validation schema.',
                ],
                'assigned_tasks' => [
                    'Typography and spacing fine-tuning -> Mohamed Salah',
                    'Auth API endpoints integration -> Ahmed Hassan',
                ],
                'risks' => [
                    'Potential delay if mobile asset specifications change before Wednesday.',
                ],
                'next_steps' => [
                    'Sync on Thursday morning at 10:00 AM with the frontend squad.',
                ],
                'generated_at' => now(),
            ]
        );

        // Additional Upcoming Meetings from Figma
        Meeting::firstOrCreate(
            ['title' => 'Sprint Planning Meeting', 'project_id' => $alphaProject->id],
            [
                'created_by' => $aminaElSherif->id,
                'description' => 'Bi-weekly sprint backlog estimation and milestone mapping.',
                'date' => now()->addDays(3)->toDateString(),
                'start_time' => '13:00:00',
                'end_time' => '13:30:00',
                'meeting_link' => 'https://meet.collabspace.ai/sprint-planning',
                'status' => MeetingStatus::Scheduled,
            ]
        );

        Meeting::firstOrCreate(
            ['title' => 'UX Feedback Session', 'project_id' => $alphaProject->id],
            [
                'created_by' => $mohanadWahib->id,
                'description' => 'Live user testing playback session and blocker review.',
                'date' => now()->addDays(5)->toDateString(),
                'start_time' => '14:00:00',
                'end_time' => '15:00:00',
                'meeting_link' => 'https://meet.collabspace.ai/ux-feedback',
                'status' => MeetingStatus::Scheduled,
            ]
        );

        // 7. Seed Chat Channels & Messages (Figma Screenshot 3: Inbox direct + Project Channels)
        // A. Project Public Channel
        $publicChannel = ChatChannel::firstOrCreate(
            ['name' => 'Public Channel', 'project_id' => $alphaProject->id],
            [
                'type' => ChatChannelType::ProjectPublic,
                'created_by' => $aminaElSherif->id,
            ]
        );
        $publicChannel->members()->syncWithoutDetaching([$mohamedSalah->id, $mohanadWahib->id, $aminaElSherif->id, $wahibSalem->id]);

        $msg1 = ChatMessage::firstOrCreate(
            ['chat_channel_id' => $publicChannel->id, 'user_id' => $mohanadWahib->id],
            ['message' => 'Hey everyone, please check the new wireframes uploaded in the design assets folder.']
        );
        ChatMessage::firstOrCreate(
            ['chat_channel_id' => $publicChannel->id, 'user_id' => $mohamedSalah->id, 'parent_id' => $msg1->id],
            ['message' => 'Uploaded! Feel free to review the typography and give your feedback.']
        );

        // B. Inbox Direct 1-on-1 Chat (Figma Inbox: Mohamed Ahmed / Mohamed Salah)
        $directChannel = ChatChannel::firstOrCreate(
            ['name' => 'Direct: Mohamed Salah & Mohanad Wahib', 'project_id' => null],
            [
                'type' => ChatChannelType::Direct,
                'created_by' => $mohamedSalah->id,
            ]
        );
        $directChannel->members()->syncWithoutDetaching([$mohamedSalah->id, $mohanadWahib->id]);

        ChatMessage::firstOrCreate(
            ['chat_channel_id' => $directChannel->id, 'user_id' => $mohanadWahib->id],
            ['message' => 'Yes, we can do this and edit the screen layout accordingly.']
        );
        ChatMessage::firstOrCreate(
            ['chat_channel_id' => $directChannel->id, 'user_id' => $mohamedSalah->id],
            ['message' => 'Ok, I need you to double check the mobile breakpoint values.']
        );

        // 8. Seed Ask AI Conversation & Messages (Figma Screenshot 4)
        $aiConversation = AiConversation::firstOrCreate(
            ['title' => 'Project Alpha Delivery Blockers & Insights', 'user_id' => $mohamedSalah->id],
            ['project_id' => $alphaProject->id]
        );

        AiMessage::firstOrCreate(
            ['ai_conversation_id' => $aiConversation->id, 'role' => AiMessageRole::User],
            ['content' => 'What are the current blockers and recommendations for Project Alpha delivery?']
        );

        AiMessage::firstOrCreate(
            ['ai_conversation_id' => $aiConversation->id, 'role' => AiMessageRole::Assistant],
            [
                'content' => 'Based on current task velocities, **Project Alpha** is at 45% completion with 92 pending tasks. The primary blocker is waiting on the approved auth wireframes before the backend team can complete OAuth integration.',
                'feedback' => 'like',
                'insights' => [
                    'root_cause' => 'Inter-team dependency between UI/UX asset finalization and Backend token verification.',
                    'current_blockers' => ['Auth flow typography approval', 'Mobile responsive specs pending'],
                    'suggested_fixes' => [
                        'Approve signup wireframes during today’s Design Review sync.',
                        'Unblock frontend developers by using mock API payloads.',
                    ],
                ],
                'citations' => [
                    ['title' => 'UX_Research_Summary.pdf', 'url' => '/api/v1/files/1/download'],
                ],
            ]
        );

        // 9. Seed Recent Profile Activities for Mohamed Salah (Figma Screenshot 4 Profile)
        UserActivity::firstOrCreate(
            ['user_id' => $mohamedSalah->id, 'description' => 'Completed task User Research Analysis'],
            [
                'activity_type' => 'completed_task',
                'subject_type' => Task::class,
                'subject_id' => $task4->id,
                'project_id' => $alphaProject->id,
                'created_at' => now()->subHour(),
            ]
        );

        UserActivity::firstOrCreate(
            ['user_id' => $mohamedSalah->id, 'description' => 'Updated Design System Badges'],
            [
                'activity_type' => 'updated_design',
                'subject_type' => Project::class,
                'subject_id' => $alphaProject->id,
                'project_id' => $alphaProject->id,
                'created_at' => now()->subHours(3),
            ]
        );

        UserActivity::firstOrCreate(
            ['user_id' => $mohamedSalah->id, 'description' => 'Joined meeting Sprint Planning Meeting'],
            [
                'activity_type' => 'joined_meeting',
                'subject_type' => Meeting::class,
                'subject_id' => $meeting->id,
                'project_id' => $alphaProject->id,
                'created_at' => now()->subHours(5),
            ]
        );

        UserActivity::firstOrCreate(
            ['user_id' => $mohamedSalah->id, 'description' => 'Commented on Dashboard Layout Design'],
            [
                'activity_type' => 'commented',
                'subject_type' => Task::class,
                'subject_id' => $task1->id,
                'project_id' => $alphaProject->id,
                'created_at' => now()->subDay(),
            ]
        );

        // 10. Seed Generated Reports (Figma Reports screen)
        Report::firstOrCreate(
            ['generated_by' => $mohamedSalah->id, 'report_type' => 'task_completion'],
            [
                'project_id' => $alphaProject->id,
                'user_id' => $mohamedSalah->id,
                'start_date' => now()->subMonth()->toDateString(),
                'end_date' => now()->toDateString(),
                'file_path' => 'reports/sample_task_report.json',
                'metrics_data' => [
                    'type' => 'task_completion',
                    'total_tasks' => 12,
                    'completed_tasks' => 8,
                    'in_progress_tasks' => 2,
                    'completion_rate_percentage' => 66.7,
                ],
                'status' => ReportStatus::Completed,
            ]
        );

        Report::firstOrCreate(
            ['generated_by' => $mohamedSalah->id, 'report_type' => 'project_progress'],
            [
                'project_id' => $alphaProject->id,
                'start_date' => now()->subMonths(3)->toDateString(),
                'end_date' => now()->toDateString(),
                'file_path' => 'reports/sample_project_report.json',
                'metrics_data' => [
                    'type' => 'project_progress',
                    'total_projects' => 4,
                    'average_progress' => 85.0,
                ],
                'status' => ReportStatus::Completed,
            ]
        );

        // 11. Seed In-App & Email Notifications (Figma top navbar bell)
        $mohamedSalah->notify(new TaskAssignedNotification($task1, $mohanadWahib->name));
        $mohamedSalah->notify(new MeetingInviteNotification($meeting, $mohanadWahib->name));

        $readNotification = $mohamedSalah->notifications()->latest()->first();
        if ($readNotification) {
            $readNotification->markAsRead();
        }

        $mohamedSalah->notify(new TaskAssignedNotification($task2, $mohanadWahib->name));
        $mohanadWahib->notify(new MeetingInviteNotification($meeting, $aminaElSherif->name));
    }
}
