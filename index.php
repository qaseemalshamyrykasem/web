<?php
// ===============================
// بوت تليجرام معدل لـ Render.com
// ===============================

class RenderTelegramBot {
    private $token;
    private $apiUrl;
    private $db;
    private $adminId = 7492270480; // ضع آيديك هنا
    
    public function __construct($botToken) {
        $this->token = $botToken;
        $this->apiUrl = "https://api.telegram.org/bot{$this->token}/";
        $this->initDatabase();
    }
    
    // ========================
    // قاعدة بيانات PostgreSQL على Render
    // ========================
    private function initDatabase() {
        try {
            // على Render، استخدم متغير البيئة
            $databaseUrl = getenv('DATABASE_URL');
            
            if ($databaseUrl) {
                // لـ PostgreSQL على Render
                $this->db = new PDO($databaseUrl);
            } else {
                // وضع التطوير - SQLite
                $this->db = new PDO("sqlite:bot.db");
            }
            
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->createTables();
            
        } catch(PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            // استخدم نظام الملفات كبديل
            $this->db = null;
        }
    }
    
    private function createTables() {
        if (!$this->db) return;
        
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id SERIAL PRIMARY KEY,
                user_id BIGINT UNIQUE,
                username VARCHAR(255),
                first_name VARCHAR(255),
                last_name VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS orders (
                id SERIAL PRIMARY KEY,
                user_id BIGINT,
                service_type VARCHAR(255),
                description TEXT,
                status VARCHAR(50) DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS messages (
                id SERIAL PRIMARY KEY,
                user_id BIGINT,
                message_text TEXT,
                bot_response TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }
    
    // ========================
    // دوال قاعدة البيانات
    // ========================
    private function saveUser($userId, $username, $firstName, $lastName) {
        if (!$this->db) return $this->saveUserToFile($userId, $username, $firstName, $lastName);
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO users (user_id, username, first_name, last_name) 
                VALUES (?, ?, ?, ?)
                ON CONFLICT (user_id) DO UPDATE SET
                username = EXCLUDED.username,
                first_name = EXCLUDED.first_name,
                last_name = EXCLUDED.last_name
            ");
            $stmt->execute([$userId, $username, $firstName, $lastName]);
        } catch (PDOException $e) {
            error_log("Save user error: " . $e->getMessage());
            $this->saveUserToFile($userId, $username, $firstName, $lastName);
        }
    }
    
    private function saveUserToFile($userId, $username, $firstName, $lastName) {
        $userData = [
            'user_id' => $userId,
            'username' => $username,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'last_seen' => date('Y-m-d H:i:s')
        ];
        
        $filename = "data/users/{$userId}.json";
        if (!is_dir('data/users')) mkdir('data/users', 0755, true);
        file_put_contents($filename, json_encode($userData));
    }
    
    private function saveOrder($userId, $serviceType) {
        if (!$this->db) return $this->saveOrderToFile($userId, $serviceType);
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO orders (user_id, service_type) 
                VALUES (?, ?)
            ");
            $stmt->execute([$userId, $serviceType]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Save order error: " . $e->getMessage());
            return $this->saveOrderToFile($userId, $serviceType);
        }
    }
    
    private function saveOrderToFile($userId, $serviceType) {
        $orderId = time() . rand(100, 999);
        $order = [
            'id' => $orderId,
            'user_id' => $userId,
            'service_type' => $serviceType,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $filename = "data/orders/{$orderId}.json";
        if (!is_dir('data/orders')) mkdir('data/orders', 0755, true);
        file_put_contents($filename, json_encode($order));
        
        return $orderId;
    }
    
    private function getUserOrders($userId) {
        if (!$this->db) return $this->getUserOrdersFromFile($userId);
        
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM orders 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get orders error: " . $e->getMessage());
            return $this->getUserOrdersFromFile($userId);
        }
    }
    
    private function getUserOrdersFromFile($userId) {
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
    // دوال البوت الأساسية
    // ========================
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
        $commands = [
            '/start' => 'sendWelcomeMessage',
            '/menu' => 'showMainMenu',
            'القائمة الرئيسية' => 'showMainMenu',
            '📊 خدماتنا' => 'showServices',
            '🛒 طلب خدمة' => 'requestService',
            '📞 اتصل بنا' => 'showContactInfo',
            'ℹ️ عن البوت' => 'showAbout',
            '👤 حسابي' => 'showUserProfile',
            '👀 طلباتي' => 'showUserOrders'
        ];
        
        if (isset($commands[$text])) {
            $method = $commands[$text];
            $this->$method($chatId, $userId, $firstName);
        } else {
            $this->handleDefaultMessage($chatId, $text, $userId);
        }
    }
    
    private function handleCallbackQuery($callbackQuery) {
        $chatId = $callbackQuery['message']['chat']['id'];
        $userId = $callbackQuery['from']['id'];
        $data = $callbackQuery['data'];
        
        $actions = [
            'service_web' => ['تطوير موقع ويب', $userId],
            'service_bot' => ['برمجة بوتات', $userId],
            'service_mobile' => ['تطبيق جوال', $userId],
            'service_seo' => ['تحسين محركات البحث', $userId],
            'show_services' => [$chatId],
            'request_service' => [$chatId],
            'contact_us' => [$chatId],
            'about_bot' => [$chatId],
            'my_profile' => [$chatId, $userId],
            'view_orders' => [$chatId, $userId],
            'back_to_menu' => [$chatId]
        ];
        
        if (isset($actions[$data])) {
            if (strpos($data, 'service_') === 0) {
                $this->handleServiceOrder($chatId, $userId, $actions[$data][0]);
            } else {
                $method = str_replace(' ', '', ucwords(str_replace('_', ' ', $data)));
                $method = 'handle' . $method;
                if (method_exists($this, $method)) {
                    call_user_func_array([$this, $method], $actions[$data]);
                }
            }
        }
        
        $this->apiRequest('answerCallbackQuery', [
            'callback_query_id' => $callbackQuery['id']
        ]);
    }
    
    // ========================
    // دوال الرسائل (مشابهة للكود السابق)
    // ========================
    private function sendWelcomeMessage($chatId, $userId = null, $firstName = '') {
        $welcomeText = "🎉 أهلاً وسهلاً بك *{$firstName}*!\n\n" .
                      "🤖 أنا *بوت الدعم الفني على Render*\n" .
                      "⚡ يعمل على استضافة Render المجانية\n\n" .
                      "اختر من القائمة:";
        
        $keyboard = [
            [['text' => '📊 خدماتنا', 'callback_data' => 'show_services']],
            [['text' => '🛒 طلب خدمة', 'callback_data' => 'request_service']],
            [['text' => '👀 طلباتي', 'callback_data' => 'view_orders']],
            [['text' => '📞 اتصل بنا', 'callback_data' => 'contact_us']],
            [['text' => '⚙️ عن البوت', 'callback_data' => 'about_bot']]
        ];
        
        $this->sendMessage($chatId, $welcomeText, $keyboard);
    }
    
    private function showMainMenu($chatId, $userId = null, $firstName = null) {
        $menuText = "🏠 *القائمة الرئيسية*\n\nاختر ما تريد:";
        
        $keyboard = [
            [['text' => '📊 خدماتنا', 'callback_data' => 'show_services']],
            [['text' => '🛒 طلب خدمة', 'callback_data' => 'request_service']],
            [['text' => '👀 طلباتي', 'callback_data' => 'view_orders']],
            [['text' => '📞 اتصل بنا', 'callback_data' => 'contact_us']],
            [['text' => '⚙️ عن البوت', 'callback_data' => 'about_bot']]
        ];
        
        $this->sendMessage($chatId, $menuText, $keyboard);
    }
    
    private function handleServiceOrder($chatId, $userId, $serviceType) {
        $orderId = $this->saveOrder($userId, $serviceType);
        
        $responseText = "✅ *تم استلام طلبك بنجاح!*\n\n" .
                       "📋 *تفاصيل الطلب:*\n" .
                       "• رقم الطلب: #{$orderId}\n" .
                       "• الخدمة: {$serviceType}\n" .
                       "• الحالة: ⏳ قيد المراجعة\n\n" .
                       "📞 سيتواصل معك فريقنا قريباً";
        
        $keyboard = [
            [['text' => '👀 عرض طلباتي', 'callback_data' => 'view_orders']],
            [['text' => '🛒 طلب جديد', 'callback_data' => 'request_service']],
            [['text' => '🏠 القائمة', 'callback_data' => 'back_to_menu']]
        ];
        
        $this->sendMessage($chatId, $responseText, $keyboard);
        $this->notifyAdmin("🆕 طلب جديد #{$orderId}\nالخدمة: {$serviceType}");
    }
    
    private function showUserOrders($chatId, $userId) {
        $orders = $this->getUserOrders($userId);
        
        if (empty($orders)) {
            $this->sendMessage($chatId, "📭 لا توجد طلبات سابقة.");
            return;
        }
        
        $ordersText = "📋 *آخر طلباتك:*\n\n";
        foreach ($orders as $order) {
            $statusIcon = $order['status'] == 'completed' ? '✅' : '⏳';
            $ordersText .= "{$statusIcon} *طلب #{$order['id']}*\n";
            $ordersText .= "• الخدمة: {$order['service_type']}\n";
            $ordersText .= "• الحالة: {$order['status']}\n";
            $ordersText .= "• التاريخ: " . date('Y-m-d', strtotime($order['created_at'])) . "\n\n";
        }
        
        $this->sendMessage($chatId, $ordersText);
    }
    
    private function showUserProfile($chatId, $userId) {
        $orders = $this->getUserOrders($userId);
        $ordersCount = count($orders);
        
        $profileText = "👤 *معلومات حسابك:*\n\n" .
                      "🆔 رقم العضوية: #{$userId}\n" .
                      "📦 عدد الطلبات: {$ordersCount}\n" .
                      "🎯 الحالة: ✅ نشط\n\n" .
                      "⚡ يعمل على: Render.com";
        
        $this->sendMessage($chatId, $profileText);
    }
    
    private function showServices($chatId) {
        $servicesText = "📊 *خدماتنا المتاحة:*\n\n" .
                       "1. 🌐 *تطوير موقع ويب*\n" .
                       "2. 🤖 *برمجة بوتات*\n" .
                       "3. 📱 *تطبيقات جوال*\n" .
                       "4. 🔍 *تحسين SEO*";
        
        $keyboard = [
            [['text' => '🌐 موقع ويب', 'callback_data' => 'service_web']],
            [['text' => '🤖 بوت تليجرام', 'callback_data' => 'service_bot']],
            [['text' => '📱 تطبيق جوال', 'callback_data' => 'service_mobile']],
            [['text' => '🔍 تحسين SEO', 'callback_data' => 'service_seo']],
            [['text' => '🔙 رجوع', 'callback_data' => 'back_to_menu']]
        ];
        
        $this->sendMessage($chatId, $servicesText, $keyboard);
    }
    
    private function handleRequestService($chatId) {
        $this->showServices($chatId);
    }
    
    private function handleContactUs($chatId) {
        $contactText = "📞 *اتصل بنا:*\n\n" .
                      "📧 Email: support@example.com\n" .
                      "🌐 Website: example.com\n\n" .
                      "🕒 ساعات العمل: 9am-5pm";
        
        $this->sendMessage($chatId, $contactText);
    }
    
    private function handleAboutBot($chatId) {
        $aboutText = "🤖 *عن البوت:*\n\n" .
                    "⚡ يعمل على استضافة Render.com\n" .
                    "📊 قاعدة بيانات PostgreSQL\n" .
                    "🔧 Webhooks مدعوم\n\n" .
                    "📈 الإصدار: 2.0.0";
        
        $this->sendMessage($chatId, $aboutText);
    }
    
    private function handleDefaultMessage($chatId, $text, $userId) {
        $response = "🤔 لم أفهم طلبك.\n\nاستخدم الأزرار أو /menu للقائمة.";
        
        $keyboard = [
            [['text' => '🏠 القائمة', 'callback_data' => 'back_to_menu']],
            [['text' => '📞 المساعدة', 'callback_data' => 'contact_us']]
        ];
        
        $this->sendMessage($chatId, $response, $keyboard);
    }
    
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
                'content' => http_build_query($data)
            ]
        ];
        
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        
        return $result;
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
            <title>بوت تليجرام على Render</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                .success { color: green; font-size: 24px; }
                .info { background: #f0f0f0; padding: 20px; border-radius: 10px; margin: 20px auto; max-width: 600px; }
            </style>
        </head>
        <body>
            <div class='success'>✅ البوت يعمل بنجاح!</div>
            <div class='info'>
                <h3>🤖 بوت تليجرام على Render.com</h3>
                <p>⚡ Webhooks: مدعوم</p>
                <p>📊 Database: PostgreSQL</p>
                <p>🔧 PHP: 8.x</p>
                <p>🌐 الاستضافة: Render.com</p>
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
    $botToken = getenv('BOT_TOKEN') ?: 'YOUR_BOT_TOKEN_HERE';
    $bot = new RenderTelegramBot($botToken);
    $bot->handleUpdate();
    echo "OK";
} else {
    $bot = new RenderTelegramBot('test');
    $bot->showWelcomePage();
}
?>