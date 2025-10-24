<?php
// ===============================
// بوت تليجرام على Render.com
// ===============================

class TelegramBot {
    private $token;
    private $apiUrl;
    private $adminId = 1944946835; // ضع آيديك هنا
    
    public function __construct() {
        // الحصول على التوكن من متغير البيئة أو استخدام القيمة الافتراضية
        $this->token = getenv('BOT_TOKEN') ?: '7312346563:AAG4gyyu72Y4_UeTQVuqqZBkKYGdCkjvyjg';
        $this->apiUrl = "https://api.telegram.org/bot{$this->token}/";
        $this->initStorage();
    }
    
    private function initStorage() {
        if (!is_dir('data')) mkdir('data', 0755, true);
        if (!is_dir('data/users')) mkdir('data/users', 0755, true);
        if (!is_dir('data/orders')) mkdir('data/orders', 0755, true);
    }
    
    public function handleUpdate() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            $this->showWelcomePage();
            return;
        }
        
        $this->processUpdate($input);
    }
    
    private function processUpdate($input) {
        $message = $input['message'] ?? $input['callback_query']['message'] ?? null;
        $callbackQuery = $input['callback_query'] ?? null;
        
        if ($callbackQuery) {
            $this->handleCallbackQuery($callbackQuery);
            return;
        }
        
        if ($message) {
            $this->handleMessage($message);
        }
    }
    
    private function handleMessage($message) {
        $chatId = $message['chat']['id'];
        $userId = $message['from']['id'];
        $text = $message['text'] ?? '';
        $firstName = $message['from']['first_name'] ?? '';
        
        // حفظ المستخدم
        $this->saveUser(
            $userId,
            $message['from']['username'] ?? '',
            $firstName,
            $message['from']['last_name'] ?? ''
        );
        
        // معالجة الأوامر
        switch($text) {
            case '/start':
                $this->sendWelcomeMessage($chatId, $firstName);
                break;
            case '/menu':
                $this->showMainMenu($chatId);
                break;
            case 'خدماتنا':
            case '📊 خدماتنا':
                $this->showServices($chatId);
                break;
            case 'طلب خدمة':
            case '🛒 طلب خدمة':
                $this->requestService($chatId);
                break;
            case 'اتصل بنا':
            case '📞 اتصل بنا':
                $this->showContactInfo($chatId);
                break;
            case 'عن البوت':
            case 'ℹ️ عن البوت':
                $this->showAbout($chatId);
                break;
            case 'حسابي':
            case '👤 حسابي':
                $this->showUserProfile($chatId, $userId);
                break;
            case 'طلباتي':
            case '👀 طلباتي':
                $this->showUserOrders($chatId, $userId);
                break;
            default:
                $this->handleDefaultMessage($chatId, $text, $userId);
        }
    }
    
    private function handleCallbackQuery($callbackQuery) {
        $chatId = $callbackQuery['message']['chat']['id'];
        $userId = $callbackQuery['from']['id'];
        $data = $callbackQuery['data'];
        
        switch($data) {
            case 'service_web':
                $this->handleServiceOrder($chatId, $userId, '🌐 موقع ويب');
                break;
            case 'service_bot':
                $this->handleServiceOrder($chatId, $userId, '🤖 بوت تليجرام');
                break;
            case 'service_mobile':
                $this->handleServiceOrder($chatId, $userId, '📱 تطبيق جوال');
                break;
            case 'service_seo':
                $this->handleServiceOrder($chatId, $userId, '🔍 تحسين SEO');
                break;
            case 'show_services':
                $this->showServices($chatId);
                break;
            case 'request_service':
                $this->requestService($chatId);
                break;
            case 'contact_us':
                $this->showContactInfo($chatId);
                break;
            case 'about_bot':
                $this->showAbout($chatId);
                break;
            case 'my_profile':
                $this->showUserProfile($chatId, $userId);
                break;
            case 'view_orders':
                $this->showUserOrders($chatId, $userId);
                break;
            case 'back_to_menu':
                $this->showMainMenu($chatId);
                break;
        }
        
        $this->apiRequest('answerCallbackQuery', [
            'callback_query_id' => $callbackQuery['id']
        ]);
    }
    
    private function sendWelcomeMessage($chatId, $firstName) {
        $text = "🎉 أهلاً وسهلاً بك *{$firstName}*!\n\n" .
               "🤖 أنا *بوت الدعم الفني*\n" .
               "⚡ يعمل على: Render.com\n\n" .
               "اختر من القائمة:";
        
        $keyboard = [
            [['text' => '📊 خدماتنا', 'callback_data' => 'show_services']],
            [['text' => '🛒 طلب خدمة', 'callback_data' => 'request_service']],
            [['text' => '👀 طلباتي', 'callback_data' => 'view_orders']],
            [['text' => '📞 اتصل بنا', 'callback_data' => 'contact_us']],
            [['text' => 'ℹ️ عن البوت', 'callback_data' => 'about_bot']]
        ];
        
        $this->sendMessage($chatId, $text, $keyboard);
    }
    
    private function showMainMenu($chatId) {
        $text = "🏠 *القائمة الرئيسية*\n\nاختر ما تريد:";
        
        $keyboard = [
            [['text' => '📊 خدماتنا', 'callback_data' => 'show_services']],
            [['text' => '🛒 طلب خدمة', 'callback_data' => 'request_service']],
            [['text' => '👀 طلباتي', 'callback_data' => 'view_orders']],
            [['text' => '📞 اتصل بنا', 'callback_data' => 'contact_us']],
            [['text' => 'ℹ️ عن البوت', 'callback_data' => 'about_bot']]
        ];
        
        $this->sendMessage($chatId, $text, $keyboard);
    }
    
    private function showServices($chatId) {
        $text = "📊 *خدماتنا المتاحة:*\n\n" .
               "• 🌐 تطوير موقع ويب\n" .
               "• 🤖 برمجة بوتات\n" .
               "• 📱 تطبيقات جوال\n" .
               "• 🔍 تحسين محركات البحث";
        
        $keyboard = [
            [['text' => '🌐 موقع ويب', 'callback_data' => 'service_web']],
            [['text' => '🤖 بوت تليجرام', 'callback_data' => 'service_bot']],
            [['text' => '📱 تطبيق جوال', 'callback_data' => 'service_mobile']],
            [['text' => '🔍 تحسين SEO', 'callback_data' => 'service_seo']],
            [['text' => '🔙 رجوع', 'callback_data' => 'back_to_menu']]
        ];
        
        $this->sendMessage($chatId, $text, $keyboard);
    }
    
    private function requestService($chatId) {
        $text = "🛒 *طلب خدمة جديدة*\n\nاختر نوع الخدمة:";
        
        $keyboard = [
            [['text' => '🌐 موقع ويب', 'callback_data' => 'service_web']],
            [['text' => '🤖 بوت تليجرام', 'callback_data' => 'service_bot']],
            [['text' => '📱 تطبيق جوال', 'callback_data' => 'service_mobile']],
            [['text' => '🔍 تحسين SEO', 'callback_data' => 'service_seo']],
            [['text' => '🔙 رجوع', 'callback_data' => 'back_to_menu']]
        ];
        
        $this->sendMessage($chatId, $text, $keyboard);
    }
    
    private function handleServiceOrder($chatId, $userId, $serviceType) {
        $orderId = $this->saveOrder($userId, $serviceType);
        
        $text = "✅ *تم استلام طلبك بنجاح!*\n\n" .
               "📋 *تفاصيل الطلب:*\n" .
               "• رقم الطلب: #{$orderId}\n" .
               "• الخدمة: {$serviceType}\n" .
               "• الحالة: ⏳ قيد المراجعة\n\n" .
               "📞 سيتواصل معك فريقنا خلال 24 ساعة";
        
        $keyboard = [
            [['text' => '👀 عرض طلباتي', 'callback_data' => 'view_orders']],
            [['text' => '🛒 طلب جديد', 'callback_data' => 'request_service']],
            [['text' => '🏠 القائمة', 'callback_data' => 'back_to_menu']]
        ];
        
        $this->sendMessage($chatId, $text, $keyboard);
        $this->notifyAdmin("🆕 طلب جديد #{$orderId}\nالخدمة: {$serviceType}");
    }
    
    private function showUserOrders($chatId, $userId) {
        $orders = $this->getUserOrders($userId);
        
        if (empty($orders)) {
            $this->sendMessage($chatId, "📭 لا توجد طلبات سابقة.");
            return;
        }
        
        $text = "📋 *آخر طلباتك:*\n\n";
        foreach ($orders as $order) {
            $statusIcon = $order['status'] == 'completed' ? '✅' : '⏳';
            $text .= "{$statusIcon} *طلب #{$order['id']}*\n";
            $text .= "• الخدمة: {$order['service_type']}\n";
            $text .= "• الحالة: {$order['status']}\n";
            $text .= "• التاريخ: " . date('Y-m-d', strtotime($order['created_at'])) . "\n\n";
        }
        
        $this->sendMessage($chatId, $text);
    }
    
    private function showUserProfile($chatId, $userId) {
        $orders = $this->getUserOrders($userId);
        $ordersCount = count($orders);
        
        $text = "👤 *معلومات حسابك:*\n\n" .
               "🆔 رقم العضوية: #{$userId}\n" .
               "📦 عدد الطلبات: {$ordersCount}\n" .
               "🎯 الحالة: ✅ نشط\n\n" .
               "⚡ يعمل على: Render.com";
        
        $this->sendMessage($chatId, $text);
    }
    
    private function showContactInfo($chatId) {
        $text = "📞 *اتصل بنا:*\n\n" .
               "📧 Email: support@example.com\n" .
               "📱 Phone: +966500000000\n\n" .
               "🕒 ساعات العمل: 9am-5pm";
        
        $this->sendMessage($chatId, $text);
    }
    
    private function showAbout($chatId) {
        $text = "🤖 *عن البوت:*\n\n" .
               "⚡ يعمل على: Render.com\n" .
               "📊 الإصدار: 2.0.0\n" .
               "🔧 المطور: أنت\n\n" .
               "✅ Webhook: مفعل";
        
        $this->sendMessage($chatId, $text);
    }
    
    private function handleDefaultMessage($chatId, $text, $userId) {
        $response = "🤔 لم أفهم طلبك.\n\nاستخدم /menu للقائمة الرئيسية.";
        
        $keyboard = [
            [['text' => '🏠 القائمة', 'callback_data' => 'back_to_menu']],
            [['text' => '📞 المساعدة', 'callback_data' => 'contact_us']]
        ];
        
        $this->sendMessage($chatId, $response, $keyboard);
    }
    
    // ========================
    // دوال التخزين
    // ========================
    private function saveUser($userId, $username, $firstName, $lastName) {
        $userData = [
            'user_id' => $userId,
            'username' => $username,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'last_seen' => date('Y-m-d H:i:s')
        ];
        
        file_put_contents("data/users/{$userId}.json", json_encode($userData));
    }
    
    private function saveOrder($userId, $serviceType) {
        $orderId = time() . rand(100, 999);
        $order = [
            'id' => $orderId,
            'user_id' => $userId,
            'service_type' => $serviceType,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        file_put_contents("data/orders/{$orderId}.json", json_encode($order));
        return $orderId;
    }
    
    private function getUserOrders($userId) {
        $orders = [];
        if (!is_dir('data/orders')) return $orders;
        
        $files = scandir('data/orders');
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                $orderData = json_decode(file_get_contents("data/orders/{$file}"), true);
                if ($orderData && $orderData['user_id'] == $userId) {
                    $orders[] = $orderData;
                }
            }
        }
        
        usort($orders, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return array_slice($orders, 0, 5);
    }
    
    // ========================
    // دوال مساعدة
    // ========================
    private function sendMessage($chatId, $text, $keyboard = null) {
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown'
        ];
        
        if ($keyboard) {
            $data['reply_markup'] = json_encode(['inline_keyboard' => $keyboard]);
        }
        
        $this->apiRequest('sendMessage', $data);
    }
    
    private function apiRequest($method, $data) {
        $url = $this->apiUrl . $method;
        
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data),
                'timeout' => 10
            ]
        ];
        
        $context = stream_context_create($options);
        @file_get_contents($url, false, $context);
    }
    
    private function notifyAdmin($message) {
        $this->sendMessage($this->adminId, "👨‍💼 إشعار مسؤول:\n{$message}");
    }
    
    private function showWelcomePage() {
        header('Content-Type: text/html; charset=utf-8');
        echo "
        <!DOCTYPE html>
        <html dir='rtl'>
        <head>
            <title>بوت تليجرام</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
                .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .success { color: #28a745; font-size: 24px; margin-bottom: 20px; }
                .info { text-align: right; margin: 20px 0; }
                .button { display: inline-block; background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 10px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='success'>✅ البوت يعمل بنجاح!</div>
                <div class='info'>
                    <h3>🤖 بوت تليجرام على Render.com</h3>
                    <p><strong>⚡ الرابط:</strong> https://bot-mv7h.onrender.com</p>
                    <p><strong>🔧 الحالة:</strong> Webhook جاهز</p>
                    <p><strong>📊 الإصدار:</strong> 2.0.0</p>
                </div>
                <a href='https://t.me/your_bot_username' class='button'>🔗 ابدأ المحادثة مع البوت</a>
            </div>
        </body>
        </html>
        ";
    }
}

// ========================
// التشغيل
// ========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bot = new TelegramBot();
    $bot->handleUpdate();
    echo "OK";
} else {
    $bot = new TelegramBot();
    $bot->showWelcomePage();
}
?>