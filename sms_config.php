<?php
/**
 * SMS Configuration
 * Configuration file for SMS service providers (Twilio/Infobip)
 */

// Choose SMS Provider: 'twilio' or 'infobip'
define('SMS_PROVIDER', 'twilio');

// Twilio Configuration
define('TWILIO_ACCOUNT_SID', 'AC8fac59142c668676e5f670d509c3aef0');
define('TWILIO_AUTH_TOKEN', 'cf8df7190a4b793710ad69e7a28af5aa');
// IMPORTANT: Get your FREE Twilio trial number:
// 1. Go to: https://console.twilio.com/us1/develop/phone-numbers/manage/incoming
// 2. Click "Get a number" (FREE for trial accounts)
// 3. Choose any country (US numbers are free, Philippines may cost)
// 4. Copy the number here (format: +15551234567)
define('TWILIO_PHONE_NUMBER', '+16187324435'); // Your Twilio number (US number can send to PH numbers)

// Infobip Configuration
define('INFOBIP_BASE_URL', 'https://your-base-url.infobip.com');
define('INFOBIP_API_KEY', 'YOUR_INFOBIP_API_KEY');
define('INFOBIP_SENDER_ID', 'DaetMarket'); // Your sender ID

// Test phone number (optional - if set, will only send to this number for testing)
define('TEST_PHONE_NUMBER', '+639511874805'); // Your personal number for testing (leave empty to send to all users)

// Payment reminder settings
define('REMINDER_DAYS_BEFORE', 7); // Days before due date to send reminder

?>

