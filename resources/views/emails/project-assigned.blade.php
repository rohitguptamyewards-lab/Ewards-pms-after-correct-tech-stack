<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;">
    <div style="max-width: 500px; margin: 0 auto; background: white; border-radius: 8px; padding: 24px; border: 1px solid #e5e5e5;">
        <h2 style="color: #4e1a77; margin-top: 0;">Project Assignment</h2>
        <p>You have been assigned as <strong>{{ $assignedRole }}</strong> on:</p>
        <div style="background: #f8f4fa; border-left: 4px solid #4e1a77; padding: 12px 16px; margin: 16px 0; border-radius: 4px;">
            <strong style="font-size: 16px;">{{ $projectName }}</strong>
        </div>
        <p style="color: #666; font-size: 14px;">Assigned by: {{ $assignedBy }}</p>
        <p style="margin-top: 20px;">
            <a href="{{ url('/projects/' . $projectId) }}" style="display: inline-block; background: #4e1a77; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: bold;">View Project</a>
        </p>
    </div>
</body>
</html>
