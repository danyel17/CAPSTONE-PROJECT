/**
 * Node.js SMS Sender for Market Clearance System
 * Sends SMS to all users with phone numbers from the database
 */

const twilio = require('twilio');
const mysql = require('mysql2/promise');

// Twilio Configuration (from sms_config.php)
const TWILIO_ACCOUNT_SID = 'AC8fac59142c668676e5f670d509c3aef0';
const TWILIO_AUTH_TOKEN = 'cf8df7190a4b793710ad69e7a28af5aa';
const TWILIO_PHONE_NUMBER = '+16187324435'; // Your Twilio number

// Database Configuration (from config.php)
const DB_CONFIG = {
    host: 'localhost',
    user: 'root',
    password: '',
    database: 'daet_market_db'
};

/**
 * Format phone number to E.164 format
 */
function formatPhoneNumber(phoneNumber) {
    if (!phoneNumber) return null;
    
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
 * Get all users with phone numbers from database
 */
async function getUsersWithPhoneNumbers() {
    let connection;
    try {
        connection = await mysql.createConnection(DB_CONFIG);
        
        const [rows] = await connection.execute(
            `SELECT user_id, full_name, phone_number, email, user_type 
             FROM users 
             WHERE phone_number IS NOT NULL 
             AND phone_number != '' 
             AND status = 'active'
             ORDER BY user_id`
        );
        
        return rows;
    } catch (error) {
        console.error('❌ Database Error:', error.message);
        throw error;
    } finally {
        if (connection) {
            await connection.end();
        }
    }
}

/**
 * Send SMS to a single user
 */
async function sendSMSToUser(client, user, message) {
    try {
        const formattedNumber = formatPhoneNumber(user.phone_number);
        
        if (!formattedNumber) {
            return {
                success: false,
                error: 'Invalid phone number format'
            };
        }
        
        console.log(`📱 Sending SMS to ${user.full_name} (${formattedNumber})...`);
        
        const messageResult = await client.messages.create({
            body: message,
            from: TWILIO_PHONE_NUMBER,
            to: formattedNumber
        });
        
        console.log(`✅ Sent to ${user.full_name} - SID: ${messageResult.sid}`);
        
        return {
            success: true,
            sid: messageResult.sid,
            status: messageResult.status
        };
        
    } catch (error) {
        console.error(`❌ Failed to send to ${user.full_name}:`, error.message);
        return {
            success: false,
            error: error.message
        };
    }
}

/**
 * Main function to send SMS to all users
 */
async function sendSMSToAllUsers() {
    try {
        console.log('🚀 Starting SMS Sender for Market Clearance System\n');
        console.log('='.repeat(60));
        
        // Initialize Twilio client
        console.log('\n📡 Initializing Twilio client...');
        const client = twilio(TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN);
        console.log('✅ Twilio client initialized\n');
        
        // Get users from database
        console.log('📊 Fetching users from database...');
        const users = await getUsersWithPhoneNumbers();
        console.log(`✅ Found ${users.length} users with phone numbers\n`);
        
        if (users.length === 0) {
            console.log('⚠️  No users found with phone numbers. Exiting...');
            return;
        }
        
        // Display users
        console.log('👥 Users to send SMS to:');
        console.log('-'.repeat(60));
        users.forEach((user, index) => {
            console.log(`${index + 1}. ${user.full_name} - ${formatPhoneNumber(user.phone_number)} (${user.user_type})`);
        });
        console.log('-'.repeat(60));
        console.log('');
        
        // Message content
        const message = `Hello from Daet Public Market Office!

This is a reminder to complete your payment before the due date.

Thank you for your cooperation!`;
        
        console.log('📝 Message to send:');
        console.log('─'.repeat(60));
        console.log(message);
        console.log('─'.repeat(60));
        console.log('');
        
        // Send SMS to each user
        console.log('📤 Sending SMS messages...\n');
        const results = [];
        
        for (let i = 0; i < users.length; i++) {
            const user = users[i];
            const result = await sendSMSToUser(client, user, message);
            
            results.push({
                user: user.full_name,
                phone: formatPhoneNumber(user.phone_number),
                ...result
            });
            
            // Add delay between messages to avoid rate limiting (1 second)
            if (i < users.length - 1) {
                await new Promise(resolve => setTimeout(resolve, 1000));
            }
        }
        
        // Summary
        console.log('\n' + '='.repeat(60));
        console.log('📊 SUMMARY');
        console.log('='.repeat(60));
        
        const successful = results.filter(r => r.success).length;
        const failed = results.filter(r => !r.success).length;
        
        console.log(`✅ Successful: ${successful}`);
        console.log(`❌ Failed: ${failed}`);
        console.log(`📊 Total: ${results.length}`);
        
        if (failed > 0) {
            console.log('\n❌ Failed Messages:');
            results.filter(r => !r.success).forEach(r => {
                console.log(`   - ${r.user} (${r.phone}): ${r.error}`);
            });
        }
        
        console.log('\n✅ SMS sending process completed!');
        
    } catch (error) {
        console.error('\n❌ Fatal Error:', error.message);
        console.error('');
        
        // Provide helpful error messages
        if (error.message.includes('Authentication Error') || error.message.includes('401')) {
            console.error('💡 Authentication Error:');
            console.error('   Please check your TWILIO_ACCOUNT_SID and TWILIO_AUTH_TOKEN are correct.');
        } else if (error.message.includes('cannot be sent') || error.message.includes('combination')) {
            console.error('💡 Trial Account Limitation:');
            console.error('   The recipient numbers must be verified in Twilio Console.');
            console.error('   Go to: https://console.twilio.com/us1/develop/phone-numbers/manage/verified');
            console.error('   Add recipient numbers as verified numbers.');
        } else if (error.message.includes('ECONNREFUSED') || error.message.includes('connect')) {
            console.error('💡 Database Connection Error:');
            console.error('   Please make sure MySQL is running.');
            console.error('   - XAMPP: Start MySQL from XAMPP Control Panel');
            console.error('   - WAMP: Start MySQL service');
        }
        
        process.exit(1);
    }
}

// Run the script
sendSMSToAllUsers();

