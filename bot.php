<?php
 // ======================
 // Bitrix OpenLine Bot
 // Minimal version
 // ======================
 
 declare(strict_types=1);
 
 // Отображаем ошибки (для отладки, потом убрать)
 ini_set('display_errors', '1');
 error_reporting(E_ALL);
 
+$rawInput = file_get_contents('php://input') ?: '';
+$decodedInput = json_decode($rawInput, true);
+$input = is_array($decodedInput) ? $decodedInput : $_REQUEST;
 $event = $input['event'] ?? null;
+$auth = $input['auth'] ?? $_REQUEST['auth'] ?? [];
+
+// Логируем все входящие запросы
+file_put_contents(__DIR__ . '/log.txt', date('c') . " RAW: " . json_encode($input, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
 
 // Функция отправки REST-запроса в Bitrix
+function rest(string $method, array $params = []): array|false
 {
+    global $auth;

+    $domain = $auth['domain'] ?? '';
+    $accessToken = $auth['access_token'] ?? '';
+
+    if (!$domain || !$accessToken) {
+        return false;
+    }
+
+    $url = "https://{$domain}/rest/{$method}?auth={$accessToken}";
     $url .= '&' . http_build_query($params);
 
     $result = file_get_contents($url);
+    if ($result === false) {
+        return false;
+    }
+
+    $decoded = json_decode($result, true);
+    return is_array($decoded) ? $decoded : false;
 }
 
 // ======================
 // Основная логика
 // ======================
 switch ($event) {
 
     // Бот добавлен в чат/линию
     case 'ONIMBOTJOINCHAT':
         $dialogId = $input['data']['PARAMS']['DIALOG_ID'] ?? '';
         if ($dialogId) {
             rest('imbot.message.add', [
                 'DIALOG_ID' => $dialogId,
+                'MESSAGE'   => 'Здравствуйте! Напишите ваш вопрос, я передам оператору.',
             ]);
         }
         break;
 
     // Новое сообщение от пользователя/оператора
     case 'ONIMBOTMESSAGEADD':
         $message = $input['data']['PARAMS']['MESSAGE'] ?? '';
         $chatId  = $input['data']['PARAMS']['CHAT_ID'] ?? '';
 
         // Здесь можно проксировать в Telegram
         file_put_contents(__DIR__ . '/log.txt', date('c') . " CHAT {$chatId}: {$message}\n", FILE_APPEND);
         break;
 
     // Установка приложения
     case 'ONAPPINSTALL':
+        $scheme = ($_SERVER['HTTPS'] ?? 'off') === 'on' ? 'https' : 'http';
+        $url = $scheme . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
 
         rest('imbot.register', [
             'CODE' => 'PETPRO_OPENLINE',
             'TYPE' => 'O',
             'OPENLINE' => 'Y',
             'EVENT_MESSAGE_ADD' => $url,
             'EVENT_WELCOME_MESSAGE' => $url,
             'EVENT_JOIN_CHAT' => $url,
         ]);
         break;
 }
 
 // Ответ для Bitrix
 echo 'OK';
