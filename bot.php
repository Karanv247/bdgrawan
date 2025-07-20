<?php
// Telegram Earning Bot with Polling Method
// Config Section
define('BOT_TOKEN', '7365095438:AAHqE8zIXSMDP6xze-XtsRgpnrUNYIBKCWk');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('USERS_FILE', __DIR__ . '/users.json');
define('ERROR_LOG_FILE', __DIR__ . '/errors.json');
define('COUPON_FILE', __DIR__ . '/coupons.json');
define('POLLING_TIMEOUT', 30); // Seconds
define('PREDICTION_SERIES_LENGTH', 5); // Results in a prediction series
define('COUPON_CODE', 'KARAN24');
define('COUPON_MAX_REDEMPTIONS', 3);

// Initialize Files
if (!file_exists(USERS_FILE)) file_put_contents(USERS_FILE, '{}');
if (!file_exists(ERROR_LOG_FILE)) file_put_contents(ERROR_LOG_FILE, '[]');
if (!file_exists(COUPON_FILE)) {
    file_put_contents(COUPON_FILE, json_encode([
        'redemptions' => 0,
        'active' => true
    ]));
}

// Main Polling Loop
$last_update_id = 0;
while (true) {
    try {
        $updates = getUpdates($last_update_id); /var/www/html/bot.php 
        
        foreach ($updates as $update) {
            processUpdate($update);
            $last_update_id = max($last_update_id, $update->update_id + 1);
        }
        
        sleep(1);
    } catch (Exception $e) {
        logError($e->getMessage());
        sleep(5); // Wait before retrying after error
    }
}

// Core Functions (Previous functions remain the same until showRewards)

function showRewards($chat_id, $user_id) {
    $user = getUser($user_id);
    $coupon_data = json_decode(file_get_contents(COUPON_FILE));
    
    $message = "🏆 *Rewards Center*\n\n"
        . "💰 Available credits: {$user->credits}\n"
        . "👥 Total referrals: {$user->referrals}\n\n"
        . "🎁 Rewards:\n"
        . "• 10 credits per successful referral\n"
        . "• 5% discount on next premium purchase for every 5 referrals\n\n";
    
    // Add coupon section only if coupon is still active
    if ($coupon_data->active) {
        $remaining = COUPON_MAX_REDEMPTIONS - $coupon_data->redemptions;
        $message .= "🎉 *SPECIAL OFFER!*\n"
            . "Use coupon code `".COUPON_CODE."` to get Basic Plan FREE!\n"
            . "Only {$remaining} redemptions remaining!\n\n"
            . "Reply with /redeem to claim this offer!";
    }
    
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '🔙 Main Menu', 'callback_data' => 'main_menu']]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard);
}

// Add new function to handle coupon redemption
function handleRedeemCommand($chat_id, $user_id) {
    $coupon_data = json_decode(file_get_contents(COUPON_FILE));
    $user = getUser($user_id);
    
    // Check if coupon is still active
    if (!$coupon_data->active) {
        sendMessage($chat_id, "❌ This coupon has expired. Check our current offers in /plan");
        return;
    }
    
    // Check if user already has premium
    if ($user->premium->active) {
        sendMessage($chat_id, "ℹ️ You already have an active premium subscription.");
        return;
    }
    
    // Check redemption limit
    if ($coupon_data->redemptions >= COUPON_MAX_REDEMPTIONS) {
        $coupon_data->active = false;
        file_put_contents(COUPON_FILE, json_encode($coupon_data));
        sendMessage($chat_id, "❌ This coupon has reached its redemption limit.");
        return;
    }
    
    // Apply coupon
    $expiry = time() + (30 * 24 * 60 * 60); // 30 days
    
    $user->premium = [
        'active' => true,
        'plan' => 'basic',
        'purchase_date' => time(),
        'expiry' => $expiry,
        'coupon_used' => COUPON_CODE
    ];
    
    saveUser($user);
    
    // Update coupon data
    $coupon_data->redemptions++;
    if ($coupon_data->redemptions >= COUPON_MAX_REDEMPTIONS) {
        $coupon_data->active = false;
    }
    file_put_contents(COUPON_FILE, json_encode($coupon_data));
    
    $message = "🎉 *Coupon Applied Successfully!*\n\n"
        . "You've received FREE Basic Plan access!\n"
        . "Coupon Code: `".COUPON_CODE."`\n"
        . "📅 Expiry: " . date('Y-m-d', $expiry) . "\n\n"
        . "Premium features are now unlocked!";
    
    sendMessage($chat_id, $message);
}

// Update handleMessage function to include /redeem command
function handleMessage($message) {
    $user_id = $message->from->id;
    $chat_id = $message->chat->id;
    $text = $message->text ?? '';

    saveUser($message->from); // Ensure user exists in DB

    if (strpos($text, '/start') === 0) {
        showMainMenu($chat_id);
    } elseif ($text === '/premium') {
        handlePremiumCommand($chat_id, $user_id);
    } elseif ($text === '/userdata') {
        handleUserData($chat_id, $user_id);
    } elseif ($text === '/plan') {
        showPremiumPlans($chat_id);
    } elseif ($text === '/redeem') {
        handleRedeemCommand($chat_id, $user_id);
    } else {
        sendMessage($chat_id, "⚠️ Unrecognized command. Use /start to see available options.");
    }
}

// Update showPremiumPlans to show prices in INR
function showPremiumPlans($chat_id) {
    $plans = [
        ['id' => 'basic', 'name' => 'Basic Plan', 'price' => '₹29', 'duration' => 30],
        ['id' => 'pro', 'name' => 'Pro Plan', 'price' => '₹59', 'duration' => 60],
        ['id' => 'vip', 'name' => 'VIP Plan', 'price' => '₹89', 'duration' => 90]
    ];
    
    $keyboard = ['inline_keyboard' => []];
    foreach ($plans as $plan) {
        $keyboard['inline_keyboard'][] = [
            [
                'text' => "{$plan['name']} ({$plan['price']})",
                'callback_data' => 'purchase_' . $plan['id']
            ]
        ];
    }
    $keyboard['inline_keyboard'][] = [['text' => '🔙 Main Menu', 'callback_data' => 'main_menu']];
    
    $message = "💎 *Premium Membership Plans*\n\n"
        . "Choose a plan to unlock premium features:\n"
        . "• Basic: {$plans[0]['price']} for {$plans[0]['duration']} days\n"
        . "• Pro: {$plans[1]['price']} for {$plans[1]['duration']} days\n"
        . "• VIP: {$plans[2]['price']} for {$plans[2]['duration']} days\n\n"
        . "Payment methods: UPI, Credit Card, Net Banking";
    
    sendMessage($chat_id, $message, $keyboard);
}

// Update handlePurchase to use INR values
function handlePurchase($chat_id, $user_id, $plan_id) {
    $plans = [
        'basic' => ['duration' => 30, 'price' => 499],
        'pro' => ['duration' => 60, 'price' => 999],
        'vip' => ['duration' => 90, 'price' => 1499]
    ];
    
    if (!isset($plans[$plan_id])) {
        sendMessage($chat_id, "❌ Invalid plan selection");
        return;
    }
    
    $plan = $plans[$plan_id];
    $expiry = time() + ($plan['duration'] * 24 * 60 * 60);
    
    $user = getUser($user_id);
    $user->premium = [
        'active' => true,
        'plan' => $plan_id,
        'purchase_date' => time(),
        'expiry' => $expiry
    ];
    saveUser($user);
    
    $message = "🎉 *Payment Successful!*\n\n"
        . "You've upgraded to " . ucfirst($plan_id) . " plan!\n"
        . "💰 Amount: ₹{$plan['price']}\n"
        . "📅 Expiry: " . date('Y-m-d', $expiry) . "\n\n"
        . "Premium features are now unlocked!";

    function getUpdates($offset) {
    $url = API_URL . "getUpdates?timeout=" . POLLING_TIMEOUT . "&offset=$offset";
    $response = file_get_contents($url);
    $result = json_decode($response);
    return $result->result ?? [];
}

function sendMessage($chat_id, $text, $keyboard = null) {
    $post = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ];
    if ($keyboard) {
        $post['reply_markup'] = json_encode($keyboard);
    }
    file_get_contents(API_URL . "sendMessage?" . http_build_query($post));
}

function getUser($user_id) {
    $users = json_decode(file_get_contents(USERS_FILE), true);
    if (!isset($users[$user_id])) {
        $users[$user_id] = [
            'id' => $user_id,
            'credits' => 0,
            'referrals' => 0,
            'premium' => ['active' => false]
        ];
        file_put_contents(USERS_FILE, json_encode($users));
    }
    return json_decode(json_encode($users[$user_id]));
}

function saveUser($user) {
    $users = json_decode(file_get_contents(USERS_FILE), true);
    $users[$user->id] = $user;
    file_put_contents(USERS_FILE, json_encode($users));
}

function logError($message) {
    $errors = json_decode(file_get_contents(ERROR_LOG_FILE));
    $errors[] = ['time' => time(), 'error' => $message];
    file_put_contents(ERROR_LOG_FILE, json_encode($errors));
}

function processUpdate($update) {
    if (isset($update->message)) {
        handleMessage($update->message);
    } elseif (isset($update->callback_query)) {
        handleCallbackQuery($update->callback_query);
    } // Add more elseif blocks as needed for other update types.
    else {
        // Optional: Log unknown update type for debugging
        // logError('Unknown update type received: ' . json_encode($update));
    }
}

    sendMessage($chat_id, $message);
}
