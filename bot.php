<?php
// ======================
// Bitrix OpenLine Bot
// Minimal version
// ======================

declare(strict_types=1);

// Отображаем ошибки (для отладки, потом убрать)
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Логируем все входящие запросы
file_put_contents(__DIR__ . '/log.txt', date('c') . " RAW: " . json_encode($_REQUEST) . "\n", FILE_APPEND);

// Получаем входящий массив
$input = json_decode(file_get_contents('php://input'), true) ?? $_REQUEST;
$event = $input['event'] ?? null;

// Функция отправки REST-запроса в Bitrix
function rest(string $method, array $params = [])
{
    $domain = $_REQUEST['auth']['domain'] ?? '';
    $access_token = $_REQUEST['auth']['access_token'] ?? '';
    if (!$domain || !$access_token) return false;

    $url = "https://{$domain}/rest/{$method}?auth={$access_token}";
    $url .= '&' . http_build_query($params);

    $result = file_get_contents($url);
    return json_decode($result, true);
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
                'MESSAGE'   => 'Здравствуйте! Напишите ваш вопрос, я передам оператору.'
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
        $url = ($_SERVER['HTTPS'] ?? 'off') === 'on' ? 'https' : 'http';
        $url .= "://{$_SERVER['HTTP_HOST']}{$_SERVER['SCRIPT_NAME']}";

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
