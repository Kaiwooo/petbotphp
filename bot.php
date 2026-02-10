 (cd "$(git rev-parse --show-toplevel)" && git apply --3way <<'EOF' 
diff --git a/bot.php b/bot.php
index ebabcda1556572808f4a7251465cf6e522ee8e24..4adff96b6b472dac66bddd741c0caad559ef850a 100644
--- a/bot.php
+++ b/bot.php
@@ -1,76 +1,106 @@
 <?php
 // ======================
 // Bitrix OpenLine Bot
 // Minimal version
 // ======================
 
 declare(strict_types=1);
 
-// Отображаем ошибки (для отладки, потом убрать)
-ini_set('display_errors', '1');
-error_reporting(E_ALL);
+$debugEnabled = filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOLEAN);
 
-// Логируем все входящие запросы
-file_put_contents(__DIR__ . '/log.txt', date('c') . " RAW: " . json_encode($_REQUEST) . "\n", FILE_APPEND);
+if ($debugEnabled) {
+    ini_set('display_errors', '1');
+    error_reporting(E_ALL);
+}
 
-// Получаем входящий массив
-$input = json_decode(file_get_contents('php://input'), true) ?? $_REQUEST;
-$event = $input['event'] ?? null;
+/**
+ * @return array<string, mixed>
+ */
+function parseInput(): array
+{
+    $rawBody = file_get_contents('php://input');
+    $decoded = is_string($rawBody) && $rawBody !== '' ? json_decode($rawBody, true) : null;
+
+    if (is_array($decoded)) {
+        return $decoded;
+    }
+
+    return $_REQUEST;
+}
+
+function appendLog(string $message): void
+{
+    file_put_contents(__DIR__ . '/log.txt', date('c') . " {$message}\n", FILE_APPEND);
+}
 
-// Функция отправки REST-запроса в Bitrix
-function rest(string $method, array $params = [])
+/**
+ * @param array<string, mixed> $input
+ * @param array<string, mixed> $params
+ * @return array<string, mixed>|false
+ */
+function rest(array $input, string $method, array $params = [])
 {
-    $domain = $_REQUEST['auth']['domain'] ?? '';
-    $access_token = $_REQUEST['auth']['access_token'] ?? '';
-    if (!$domain || !$access_token) return false;
+    $auth = $input['auth'] ?? [];
+    $domain = is_array($auth) ? ($auth['domain'] ?? '') : '';
+    $accessToken = is_array($auth) ? ($auth['access_token'] ?? '') : '';
 
-    $url = "https://{$domain}/rest/{$method}?auth={$access_token}";
+    if (!$domain || !$accessToken) {
+        appendLog("REST_SKIP method={$method} reason=missing_auth");
+        return false;
+    }
+
+    $url = "https://{$domain}/rest/{$method}?auth={$accessToken}";
     $url .= '&' . http_build_query($params);
 
-    $result = file_get_contents($url);
-    return json_decode($result, true);
+    $result = @file_get_contents($url);
+    if ($result === false) {
+        appendLog("REST_ERROR method={$method} url={$url}");
+        return false;
+    }
+
+    $decoded = json_decode($result, true);
+    return is_array($decoded) ? $decoded : ['raw' => $result];
 }
 
-// ======================
-// Основная логика
-// ======================
-switch ($event) {
+$input = parseInput();
+$event = $input['event'] ?? null;
+appendLog('RAW: ' . json_encode($input, JSON_UNESCAPED_UNICODE));
 
-    // Бот добавлен в чат/линию
+switch ($event) {
     case 'ONIMBOTJOINCHAT':
         $dialogId = $input['data']['PARAMS']['DIALOG_ID'] ?? '';
         if ($dialogId) {
-            rest('imbot.message.add', [
+            rest($input, 'imbot.message.add', [
                 'DIALOG_ID' => $dialogId,
-                'MESSAGE'   => 'Здравствуйте! Напишите ваш вопрос, я передам оператору.'
+                'MESSAGE' => 'Здравствуйте! Напишите ваш вопрос, я передам оператору.',
             ]);
         }
         break;
 
-    // Новое сообщение от пользователя/оператора
     case 'ONIMBOTMESSAGEADD':
         $message = $input['data']['PARAMS']['MESSAGE'] ?? '';
-        $chatId  = $input['data']['PARAMS']['CHAT_ID'] ?? '';
+        $chatId = $input['data']['PARAMS']['CHAT_ID'] ?? '';
 
-        // Здесь можно проксировать в Telegram
-        file_put_contents(__DIR__ . '/log.txt', date('c') . " CHAT {$chatId}: {$message}\n", FILE_APPEND);
+        appendLog("CHAT {$chatId}: {$message}");
         break;
 
-    // Установка приложения
     case 'ONAPPINSTALL':
-        $url = ($_SERVER['HTTPS'] ?? 'off') === 'on' ? 'https' : 'http';
+        $url = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
         $url .= "://{$_SERVER['HTTP_HOST']}{$_SERVER['SCRIPT_NAME']}";
 
-        rest('imbot.register', [
+        rest($input, 'imbot.register', [
             'CODE' => 'PETPRO_OPENLINE',
             'TYPE' => 'O',
             'OPENLINE' => 'Y',
             'EVENT_MESSAGE_ADD' => $url,
             'EVENT_WELCOME_MESSAGE' => $url,
             'EVENT_JOIN_CHAT' => $url,
         ]);
         break;
+
+    default:
+        appendLog('EVENT_SKIP reason=unknown_event');
+        break;
 }
 
-// Ответ для Bitrix
 echo 'OK';
 
EOF
)
