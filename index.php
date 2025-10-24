<?php
// ===============================
// بوت تليجرام مبسط لـ Render.com
// ===============================

class SimpleTelegramBot {
    private $token = '7312346563:AAG4gyyu72Y4_UeTQVuqqZBkKYGdCkjvyjg';
    private $apiUrl;
    
    public function __construct() {
        $this->apiUrl = "https://api.telegram.org/bot{$this->token}/";
        $this->initStorage();
    }
    
    private function initStorage() {
        if (!is_dir('data')) mkdir('data', 0755, true);
    }
    
    public function handleUpdate() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            $this->showWelcomePage();
            return;
        }
        
        $message = $input['message'] ?? null;
        if ($message) {
            $this->handleMessage($message);
        }
        
        echo "OK";
    }
    
    private function handleMessage($message) {
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $firstName = $message['from']['first_name'] ?? '';
        
        switch($text) {
            case '/start':
                $this->sendWelcomeMessage($chatId, $firstName);
                break;
            case '/menu':
                $this->showMainMenu($chatId);
                break;
            default:
                $this->sendMessage($chatId, "👋 أهلاً! استخدم /start للبدء");
        }
    }
    
    private function sendWelcomeMessage($chatId, $firstName) {
        $text = "🎉 أهلاً وسهلاً بك *{$firstName}*!\n\n" .
               "🤖 أنا *بوت تليجرام*\n" .
               "✅ أعمل بنجاح على Render.com\n\n" .
               "⚡ استخدم /menu للقائمة";
        
        $this->sendMessage($chatId, $text);
    }
    
    private function showMainMenu($chatId) {
        $text = "🏠 *القائمة الرئيسية*\n\n" .
               "📊 خدماتنا\n" .
               "🛒 طلب خدمة\n" .
               "📞 اتصل بنا\n\n" .
               "اختر من الأزرار أدناه:";
        
        $keyboard = [
            [['text' => '📊 خدماتنا', 'callback_data' => 'services']],
            [['text' => '🛒 طلب خدمة', 'callback_data' => 'order']],
            [['text' => '📞 اتصل بنا', 'callback_data' => 'contact']]
        ];
        
        $this->sendMessage($chatId, $text, $keyboard);
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
                'content' => http_build_query($data),
                'timeout' => 5
            ]
        ];
        
        $context = stream_context_create($options);
        @file_get_contents($url, false, $context);
    }
    
    private function showWelcomePage() {
        header('Content-Type: text/html; charset=utf-8');
        echo "
        <!DOCTYPE html>
        <html dir='rtl'>
        <head>
            <title>بوت تليجرام</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f0f8ff; }
                .container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
                .success { color: #28a745; font-size: 28px; margin-bottom: 20px; }
                .info { text-align: center; margin: 25px 0; line-height: 1.6; }
                .status { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='success'>✅ البوت يعمل بنجاح!</div>
                <div class='info'>
                    <h2>🤖 بوت تليجرام</h2>
                    <div class='status'>⚡ الحالة: نشط وجاهز</div>
                    <p><strong>🌐 الرابط:</strong> https://bot-mv7h.onrender.com</p>
                    <p><strong>🔧 التوكن:</strong> 7312346563:...vyjg</p>
                    <p><strong>📞 Webhook:</strong> جاهز للتشغيل</p>
                </div>
                <p>لتفعيل البوت، أرسل هذا الأمر في Terminal:</p>
                <code style='background: #f8f9fa; padding: 10px; display: block; margin: 10px 0; border-radius: 5px;'>
                curl \"https://api.telegram.org/bot7312346563:AAG4gyyu72Y4_UeTQVuqqZBkKYGdCkjvyjg/setWebhook?url=https://bot-mv7h.onrender.com\"
                </code>
            </div>
        </body>
        </html>
        ";
    }
}

// ========================
// التشغيل الرئيسي
// ========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bot = new SimpleTelegramBot();
    $bot->handleUpdate();
} else {
    $bot = new SimpleTelegramBot();
    $bot->showWelcomePage();
}
?>