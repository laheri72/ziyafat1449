<?php
require_once __DIR__ . '/mailer_helper.php';

/**
 * Generate a professional HTML email template wrapper specifically for Ziyarat Raudat Tahera broadcasts
 * 
 * @param string $campaignType - 'standard', 'mumbai_prompt', 'mumbai_alert'
 */
function get_ziyarat_email_template($eventTag, $userName, $userId, $trNumber, $eventStats, $overallStats, $targetCount = 30, $customNote = '', $campaignType = 'standard') {
    $config = require __DIR__ . '/../config/mail.php';
    $baseUrl = rtrim($config['base_url'], '/');
    $ziyaratPortalUrl = "https://ziyarat1449.web.app/?tr=" . urlencode($trNumber);
    $dashboardUrl = $baseUrl . "/user/index.php";

    $unsubscribeLink = '';
    if ($userId > 0) {
        $token = md5($userId . 'ziyafat1449_bulk_mail_secret');
        $unsubscribeUrl = $baseUrl . "/unsubscribe.php?u=$userId&t=$token";
        $unsubscribeLink = "<p style='margin-top: 15px;'><a href='$unsubscribeUrl' style='color: #94a3b8; text-decoration: underline; font-size: 11px;'>Unsubscribe from Ziyarat reminders</a></p>";
    }

    // Event specific stats
    $assignedCount = intval($eventStats['assigned'] ?? 0);
    $completedCount = intval($eventStats['completed'] ?? 0);
    $pendingCount = intval($eventStats['pending'] ?? 0);
    $eventPct = ($assignedCount > 0) ? min(100, round(($completedCount / $assignedCount) * 100)) : 0;

    // Overall stats
    $overallAssigned = intval($overallStats['assigned'] ?? 0);
    $overallCompleted = intval($overallStats['completed'] ?? 0);
    $overallPct = ($overallAssigned > 0) ? min(100, round(($overallCompleted / $overallAssigned) * 100)) : 0;

    $customNoteHtml = '';
    if (!empty($customNote)) {
        $customNoteHtml = "
        <div style='background: #eff6ff; border-left: 4px solid #2563eb; padding: 14px; border-radius: 6px; margin: 15px 0; color: #1e40af;'>
            <strong>Note from Management:</strong><br>
            " . nl2br(htmlspecialchars($customNote)) . "
        </div>";
    }

    $headerTitle = "Ziyarat Raudat Tahera";
    $headerSub = "Event: " . htmlspecialchars($eventTag);
    $headerBg = "linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%)";

    if ($campaignType === 'mumbai_prompt') {
        $headerTitle = "Mumbai Presence Check";
        $headerSub = "Action Required for " . htmlspecialchars($eventTag);
        $headerBg = "linear-gradient(135deg, #d97706 0%, #78350f 100%)";

        $content = "
        <p>Afzal us Salam <strong>" . htmlspecialchars($userName) . "</strong>,</p>

        <p>We are currently organizing beneficiary assignments for <strong>" . htmlspecialchars($eventTag) . "</strong> at Raudat Tahera.</p>

        <div style='background: #fffbeb; border: 2px solid #f59e0b; border-radius: 8px; padding: 18px; margin: 20px 0; color: #78350f;'>
            <h3 style='margin-top: 0; color: #b45309; font-size: 17px;'>Are you currently present in Mumbai?</h3>
            <p style='margin-bottom: 0; font-size: 14px; line-height: 1.5;'>
                Please confirm your availability status so we can allocate Karachi HOF Mumineen for your Ziyarat Khidmat list.
            </p>
        </div>

        $customNoteHtml

        <div style='background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 15px; margin: 15px 0; font-size: 13px;'>
            <strong>Current Event Status ($eventTag):</strong><br>
            • Assigned to You: <strong>{$assignedCount} Mumineen</strong><br>
            • Pending Ziyarats: <strong>{$pendingCount} Mumineen</strong>
        </div>

        <p style='text-align: center; margin-top: 25px; margin-bottom: 15px;'>
            <a href='{$ziyaratPortalUrl}' style='display: inline-block; padding: 14px 28px; background-color: #d97706; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 15px;'>
                Update Mumbai Availability Status
            </a>
        </p>

        <p style='text-align: center; font-size: 12px; color: #64748b;'>
            Or toggle directly from your dashboard: <a href='{$dashboardUrl}' style='color: #d97706; text-decoration: underline;'>Open Ziyafat Dashboard</a>
        </p>
        ";
    } elseif ($campaignType === 'mumbai_alert') {
        $headerTitle = "Urgent: Ziyarat Khidmat Alert";
        $headerSub = "You are in Mumbai for " . htmlspecialchars($eventTag);
        $headerBg = "linear-gradient(135deg, #dc2626 0%, #7f1d1d 100%)";

        $content = "
        <p>Afzal us Salam <strong>" . htmlspecialchars($userName) . "</strong>,</p>

        <p>According to your status, you are currently <strong>present in Mumbai</strong> for <strong>" . htmlspecialchars($eventTag) . "</strong>.</p>

        <div style='background: #fef2f2; border: 2px solid #ef4444; border-radius: 8px; padding: 18px; margin: 20px 0;'>
            <h3 style='margin-top: 0; color: #b91c1c; font-size: 17px;'>Urgent Reminder for Raudat Tahera Ziyarat</h3>
            <p style='margin-bottom: 10px; font-size: 14px; color: #7f1d1d;'>
                You have <strong>{$pendingCount} pending Ziyarat assignments</strong> waiting to be completed for this event.
            </p>
            <div style='height: 10px; background: #fee2e2; border-radius: 5px; overflow: hidden;'>
                <div style='height: 100%; width: {$eventPct}%; background: #dc2626;'></div>
            </div>
            <div style='display: flex; justify-content: space-between; font-size: 12px; color: #991b1b; margin-top: 6px;'>
                <span>Completed: {$completedCount} / {$assignedCount}</span>
                <span>Remaining: {$pendingCount}</span>
            </div>
        </div>

        $customNoteHtml

        <p style='text-align: center; margin-top: 25px; margin-bottom: 15px;'>
            <a href='{$ziyaratPortalUrl}' style='display: inline-block; padding: 14px 28px; background-color: #dc2626; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 15px;'>
                Open Ziyarat List & Mark Completed
            </a>
        </p>

        <p style='text-align: center; font-size: 12px; color: #64748b;'>
            Or access via your student dashboard: <a href='{$dashboardUrl}' style='color: #dc2626; text-decoration: underline;'>Open Ziyafat Dashboard</a>
        </p>
        ";
    } else {
        // Standard Ziyarat Progress Campaign
        if ($assignedCount > 0 && $pendingCount === 0) {
            $eventStatusBg = '#dcfce7'; $eventStatusText = '#166534'; $eventStatusLabel = 'Completed for Event!';
        } elseif ($assignedCount > 0) {
            $eventStatusBg = '#fef3c7'; $eventStatusText = '#92400e'; $eventStatusLabel = "$pendingCount Pending for Event";
        } else {
            $eventStatusBg = '#f1f5f9'; $eventStatusText = '#475569'; $eventStatusLabel = "No Assignments Yet";
        }

        $content = "
        <p>Afzal us Salam <strong>" . htmlspecialchars($userName) . "</strong>,</p>

        <p>This is a reminder regarding your <strong>Ziyarat Raudat Tahera Khidmat</strong> for <strong>" . htmlspecialchars($eventTag) . "</strong>.</p>
        
        $customNoteHtml

        <!-- Event Specific Card -->
        <div style='background: #ffffff; border: 2px solid #2563eb; border-radius: 8px; padding: 18px; margin: 20px 0;'>
            <div style='display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 12px;'>
                <strong style='font-size: 16px; color: #1e3a8a;'>Event: " . htmlspecialchars($eventTag) . "</strong>
                <span style='background: {$eventStatusBg}; color: {$eventStatusText}; font-size: 12px; font-weight: bold; padding: 4px 10px; border-radius: 12px;'>{$eventStatusLabel}</span>
            </div>

            <div style='margin-bottom: 12px;'>
                <div style='display: flex; justify-content: space-between; font-size: 13px; color: #475569; margin-bottom: 4px;'>
                    <span>Event Progress ($completedCount of $assignedCount Done)</span>
                    <span><strong>{$eventPct}%</strong></span>
                </div>
                <div style='height: 10px; background: #e2e8f0; border-radius: 5px; overflow: hidden;'>
                    <div style='height: 100%; width: {$eventPct}%; background: linear-gradient(90deg, #2563eb, #1d4ed8); border-radius: 5px;'></div>
                </div>
            </div>

            <table style='width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px;'>
                <tr style='border-bottom: 1px dashed #e2e8f0;'>
                    <td style='padding: 8px 0; color: #64748b;'>Target Mumineen for Event:</td>
                    <td style='padding: 8px 0; text-align: right; font-weight: bold; color: #1e293b;'>{$targetCount} Mumineen</td>
                </tr>
                <tr style='border-bottom: 1px dashed #e2e8f0;'>
                    <td style='padding: 8px 0; color: #64748b;'>Assigned to You for Event:</td>
                    <td style='padding: 8px 0; text-align: right; font-weight: bold; color: #2563eb;'>{$assignedCount} Mumineen</td>
                </tr>
                <tr style='border-bottom: 1px dashed #e2e8f0;'>
                    <td style='padding: 8px 0; color: #64748b;'>Completed Ziyarat for Event:</td>
                    <td style='padding: 8px 0; text-align: right; font-weight: bold; color: #16a34a;'>{$completedCount} Mumineen</td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; color: #64748b;'>Remaining / Pending for Event:</td>
                    <td style='padding: 8px 0; text-align: right; font-weight: bold; color: #dc2626;'>{$pendingCount} Mumineen</td>
                </tr>
            </table>
        </div>

        <!-- Lifetime Overall Dashboard Summary -->
        <div style='background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 15px; margin: 15px 0;'>
            <div style='display:flex; justify-content:space-between; align-items:center; margin-bottom: 8px;'>
                <strong style='font-size: 14px; color: #334155;'>Overall Ziyarat Portal Progress</strong>
                <span style='font-size: 13px; font-weight: bold; color: #0f172a;'>{$overallCompleted} / {$overallAssigned} Total ({$overallPct}%)</span>
            </div>
            <div style='height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;'>
                <div style='height: 100%; width: {$overallPct}%; background: #16a34a;'></div>
            </div>
        </div>

        <p style='text-align: center; margin-top: 25px; margin-bottom: 15px;'>
            <a href='{$ziyaratPortalUrl}' style='display: inline-block; padding: 12px 24px; background-color: #2563eb; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 14px; margin-right: 8px;'>
                Open Ziyarat Portal
            </a>
        </p>

        <p style='text-align: center; font-size: 12px; color: #64748b;'>
            Or access via your student dashboard: <a href='{$dashboardUrl}' style='color: #2563eb; text-decoration: underline;'>Open Ziyafat Dashboard</a>
        </p>
        ";
    }

    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 20px auto; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
            .header { background: {$headerBg}; color: white; padding: 25px 20px; text-align: center; }
            .content { padding: 25px 20px; background: #ffffff; }
            .footer { background: #f8fafc; padding: 20px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1 style='margin: 0; font-size: 22px;'>{$headerTitle}</h1>
                <p style='margin: 5px 0 0 0; font-size: 13px; opacity: 0.9;'>{$headerSub}</p>
            </div>
            <div class='content'>
                $content
            </div>
            <div class='footer'>
                <p>&copy; 1449 H &middot; Ziyarat Raudat Tahera Khidmat</p>
                <p>This is an automated reminder sent to assigned Talabat.</p>
                $unsubscribeLink
            </div>
        </div>
    </body>
    </html>";
}
