<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; padding: 24px; border: 1px solid #e5e5e5;">
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
            <span style="background: #4e1a77; color: white; font-size: 10px; font-weight: bold; padding: 3px 8px; border-radius: 4px; text-transform: uppercase;">Automation</span>
            <span style="color: #999; font-size: 12px;">{{ $automationName }}</span>
        </div>
        <h2 style="color: #4e1a77; margin-top: 0;">{{ $emailSubject }}</h2>
        <div style="color: #333; font-size: 14px; line-height: 1.6;">
            {!! $emailBody !!}
        </div>
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="color: #999; font-size: 12px;">
            This email was sent automatically by eWards PMS.
            <a href="{{ url('/automations') }}" style="color: #4e1a77;">Manage automations</a>
        </p>
    </div>
</body>
</html>
