<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;">
    <div style="max-width: 500px; margin: 0 auto; background: white; border-radius: 8px; padding: 24px; border: 1px solid #e5e5e5;">
        <h2 style="color: #4e1a77; margin-top: 0;">Welcome to eWards PMS!</h2>
        <p>Hi <strong>{{ $memberName }}</strong>,</p>
        <p>Your account has been created on eWards Project Management System. Here are your login details:</p>
        <div style="background: #f8f4fa; border-left: 4px solid #4e1a77; padding: 12px 16px; margin: 16px 0; border-radius: 4px;">
            <p style="margin: 4px 0;"><strong>Email:</strong> {{ $memberEmail }}</p>
            <p style="margin: 4px 0;"><strong>Password:</strong> {{ $tempPassword }}</p>
            <p style="margin: 4px 0;"><strong>Role:</strong> {{ $roleName }}</p>
        </div>
        <p style="color: #666; font-size: 14px;">Added by: {{ $addedBy }}</p>
        <p style="color: #c0392b; font-size: 13px;">Please change your password after first login.</p>
        <p style="margin-top: 20px;">
            <a href="{{ url('/login') }}" style="display: inline-block; background: #4e1a77; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: bold;">Login Now</a>
        </p>
    </div>
</body>
</html>
