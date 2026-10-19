/**
 * Node.js SMS Sender
 * Sends SMS to: 09511874805
 */

const twilio = require('twilio');

// Twilio Configuration (from sms_config.php)
const TWILIO_ACCOUNT_SID = 'AC8fac59142c668676e5f670d509c3aef0';
const TWILIO_AUTH_TOKEN = 'cf8df7190a4b793710ad69e7a28af5aa';
const TWILIO_PHONE_NUMBER = '+16187324435'; // Your Twilio number

// Recipient phone number
const RECIPIENT_NUMBER = '09511874805';

/**
 * Format phone number to E.164 format
 */
function formatPhoneNumber(phoneNumber) {
    // Remove all non-numeric characters
    let cleaned = phoneNumber.replace(/[^0-9]/g, '');
    
    // If it doesn't start with +, add country code (Philippines = +63)
    if (!phoneNumber.startsWith('+')) {
        // If it starts with 0, replace with +63
        if (cleaned.startsWith('0')) {
            cleaned = '63' + cleaned.substring(1);
        } else if (!cleaned.startsWith('63')) {
            cleaned = '63' + cleaned;
        }
        return '+' + cleaned;
    }
    
    return phoneNumber;
}

/**
 * Send SMS using Twilio
 */
async function sendSMS() {
    try {
        // Initialize Twilio client
        const client = twilio(TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN);
        
        // Format recipient number
        const formattedNumber = formatPhoneNumber(RECIPIENT_NUMBER);
        
        // Message content
        const message = `Hello! This is a test message from Daet Public Market Office.

Your SMS system is working correctly!

Thank you!`;
        
        console.log('📱 Sending SMS...');
        console.log('To:', formattedNumber);
        console.log('From:', TWILIO_PHONE_NUMBER);
        console.log('Message:', message);
        console.log('');
        
        // Send SMS
        const messageResult = await client.messages.create({
            body: message,
            from: TWILIO_PHONE_NUMBER,
            to: formattedNumber
        });
        
        console.log('✅ SMS Sent Successfully!');
        console.log('Message SID:', messageResult.sid);
        console.log('Status:', messageResult.status);
        console.log('To:', messageResult.to);
        console.log('From:', messageResult.from);
        console.log('');
        console.log('Check your phone (' + RECIPIENT_NUMBER + ') for the message!');
        
    } catch (error) {
        console.error('❌ Failed to Send SMS');
        console.error('Error:', error.message);
        console.error('');
        
        // Provide helpful error messages
        if (error.message.includes('Authentication Error') || error.message.includes('401')) {
            console.error('💡 Authentication Error:');
            console.error('   Please check your TWILIO_ACCOUNT_SID and TWILIO_AUTH_TOKEN are correct.');
        } else if (error.message.includes('cannot be sent') || error.message.includes('combination')) {
            console.error('💡 Trial Account Limitation:');
            console.error('   The recipient number must be verified in Twilio Console.');
            console.error('   Go to: https://console.twilio.com/us1/develop/phone-numbers/manage/verified');
            console.error('   Add ' + formatPhoneNumber(RECIPIENT_NUMBER) + ' as a verified number.');
            console.error('   Select SMS verification method and enter the code you receive.');
        } else if (error.message.includes('invalid') && error.message.includes('phone')) {
            console.error('💡 Invalid Phone Number:');
            console.error('   Please check your TWILIO_PHONE_NUMBER is correct and verified in Twilio console.');
        }
        
        process.exit(1);
    }
}

// Run the script
sendSMS();


