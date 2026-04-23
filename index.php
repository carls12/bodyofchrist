<?php
require_once __DIR__ . '/app/middleware.php';
bootstrap_app();

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/helpers.php';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$cfg = require __DIR__ . '/app/config.php';

$base = rtrim($cfg['base_url'], '/');
if ($base !== '' && str_starts_with($path, $base)) {
  $path = substr($path, strlen($base));
}
$path = '/' . ltrim($path, '/');

switch ($path) {
  case '/': redirect(base_url('dashboard')); break;
  case '/login': require __DIR__ . '/pages/login.php'; break;
  case '/register': require __DIR__ . '/pages/register.php'; break;
  case '/dashboard': require __DIR__ . '/pages/dashboard.php'; break;
  case '/goals': require __DIR__ . '/pages/goals.php'; break;
  case '/progress': require __DIR__ . '/pages/progress.php'; break;
  case '/assemblies': require __DIR__ . '/pages/assemblies.php'; break;
  case '/assemblies/show': require __DIR__ . '/pages/assembly_show.php'; break;
  case '/assemblies/summary': require __DIR__ . '/pages/assembly_summary.php'; break;
  case '/admin/users': require __DIR__ . '/pages/admin_users.php'; break;
  case '/admin/assemblies': require __DIR__ . '/pages/admin_assemblies.php'; break;
  case '/admin/national': require __DIR__ . '/pages/admin_national.php'; break;
  case '/profile': require __DIR__ . '/pages/profile.php'; break;
  case '/forgot-password': require __DIR__ . '/pages/forgot_password.php'; break;
  case '/reset-password': require __DIR__ . '/pages/reset_password.php'; break;
  case '/bible': require __DIR__ . '/pages/bible.php'; break;
  case '/prayer': require __DIR__ . '/pages/prayer.php'; break;
  case '/calendar': require __DIR__ . '/pages/calendar.php'; break;
  case '/daily-planner': require __DIR__ . '/pages/daily_planner.php'; break;
  case '/my-reports': require __DIR__ . '/pages/my_reports.php'; break;
  case '/chat': require __DIR__ . '/pages/chat.php'; break;
  case '/meet': require __DIR__ . '/pages/meet.php'; break;
  case '/reports': require __DIR__ . '/pages/reports.php'; break;
  case '/quizzes': require __DIR__ . '/pages/quizzes.php'; break;
  case '/quiz/take': require __DIR__ . '/pages/quiz_take.php'; break;
  case '/quiz/review': require __DIR__ . '/pages/quiz_review.php'; break;
  case '/two-factor': require __DIR__ . '/pages/two_factor.php'; break;
  case '/two-factor-setup': require __DIR__ . '/pages/two_factor_setup.php'; break;

  case '/action/login': require __DIR__ . '/actions/login_action.php'; break;
  case '/action/register': require __DIR__ . '/actions/register_action.php'; break;
  case '/action/logout': require __DIR__ . '/actions/logout.php'; break;
  case '/action/save-goal': require __DIR__ . '/actions/save_goal.php'; break;
  case '/action/save-report-note': require __DIR__ . '/actions/save_report_note.php'; break;
  case '/action/delete-goal': require __DIR__ . '/actions/delete_goal.php'; break;
  case '/action/save-progress': require __DIR__ . '/actions/save_progress.php'; break;
  case '/action/save-goal-progress-batch': require __DIR__ . '/actions/save_goal_progress_batch.php'; break;
  case '/action/create-assembly': require __DIR__ . '/actions/create_assembly.php'; break;
  case '/action/join-assembly': require __DIR__ . '/actions/join_assembly.php'; break;
  case '/action/regenerate-code': require __DIR__ . '/actions/regenerate_code.php'; break;
  case '/action/toggle-leader': require __DIR__ . '/actions/toggle_leader.php'; break;
  case '/action/set-regional-leader': require __DIR__ . '/actions/set_regional_leader.php'; break;
  case '/action/set-national-leader': require __DIR__ . '/actions/set_national_leader.php'; break;
  case '/action/set-locale': require __DIR__ . '/actions/set_locale.php'; break;
  case '/action/update-profile': require __DIR__ . '/actions/update_profile.php'; break;
  case '/action/request-password-reset': require __DIR__ . '/actions/request_password_reset.php'; break;
  case '/action/confirm-password-reset': require __DIR__ . '/actions/confirm_password_reset.php'; break;
  case '/action/save-bible-reading': require __DIR__ . '/actions/save_bible_reading.php'; break;
  case '/action/send-message': require __DIR__ . '/actions/send_message.php'; break;
  case '/action/send-group-message': require __DIR__ . '/actions/send_group_message.php'; break;
  case '/action/activate-assembly': require __DIR__ . '/actions/activate_assembly.php'; break;
  case '/action/approve-member': require __DIR__ . '/actions/approve_member.php'; break;
  case '/action/invite-member': require __DIR__ . '/actions/invite_member.php'; break;
  case '/action/accept-invite': require __DIR__ . '/actions/accept_invite.php'; break;
  case '/action/search-group-users': require __DIR__ . '/actions/search_group_users.php'; break;
  case '/action/set-global-goal': require __DIR__ . '/actions/set_global_goal.php'; break;
  case '/action/update-assembly-admin': require __DIR__ . '/actions/update_assembly_admin.php'; break;
  case '/action/search-users-admin': require __DIR__ . '/actions/search_users_admin.php'; break;
  case '/action/admin-download-totals': require __DIR__ . '/actions/admin_download_totals.php'; break;
  case '/action/add-member-progress': require __DIR__ . '/actions/add_member_progress.php'; break;
  case '/action/toggle-chat': require __DIR__ . '/actions/toggle_chat.php'; break;
  case '/action/save-quiz': require __DIR__ . '/actions/save_quiz.php'; break;
  case '/action/submit-quiz': require __DIR__ . '/actions/submit_quiz.php'; break;
  case '/action/upload-bible': require __DIR__ . '/actions/upload_bible.php'; break;
  case '/action/set-bible-version': require __DIR__ . '/actions/set_bible_version.php'; break;
  case '/action/update-bible-version': require __DIR__ . '/actions/update_bible_version.php'; break;
  case '/action/delete-bible-version': require __DIR__ . '/actions/delete_bible_version.php'; break;
  case '/action/fetch-messages': require __DIR__ . '/actions/fetch_messages.php'; break;
  case '/action/fetch-group-messages': require __DIR__ . '/actions/fetch_group_messages.php'; break;
  case '/action/search-users': require __DIR__ . '/actions/search_users.php'; break;
  case '/action/download-report': require __DIR__ . '/actions/download_report.php'; break;
  case '/action/save-prayer': require __DIR__ . '/actions/save_prayer.php'; break;
  case '/action/save-event': require __DIR__ . '/actions/save_event.php'; break;
  case '/action/delete-event': require __DIR__ . '/actions/delete_event.php'; break;
  case '/action/save-daily-planner': require __DIR__ . '/actions/save_daily_planner.php'; break;
  case '/action/delete-daily-planner-template': require __DIR__ . '/actions/delete_daily_planner_template.php'; break;
  case '/action/download-daily-planner': require __DIR__ . '/actions/download_daily_planner.php'; break;
  case '/action/download-my-report': require __DIR__ . '/actions/download_my_report.php'; break;
  case '/action/fetch-goal-progress': require __DIR__ . '/actions/fetch_goal_progress.php'; break;
  case '/action/generate-quiz': require __DIR__ . '/actions/generate_quiz.php'; break;
  case '/action/grade-quiz-response': require __DIR__ . '/actions/grade_quiz_response.php'; break;
  case '/action/verify-two-factor': require __DIR__ . '/actions/verify_two_factor.php'; break;
  case '/action/setup-two-factor': require __DIR__ . '/actions/setup_two_factor.php'; break;

  default:
    http_response_code(404);
    echo '404 Not Found';
}
