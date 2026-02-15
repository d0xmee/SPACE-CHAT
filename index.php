<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

$users_file = 'users.txt';
$messages_file = 'messages.txt';

if (isset($_POST['register'])) {
    $name = trim($_POST['name']);
    $login = trim($_POST['login']);
    $password = trim($_POST['password']);

    $users = [];
    if (file_exists($users_file)) {
        $content = file_get_contents($users_file);
        $users = explode("\n", trim($content));
        $users = array_filter($users);
    }
    
    $exists = false;
    
    foreach ($users as $u) {
        if (!empty($u)) {
            $parts = explode('|', $u);
            if (count($parts) >= 2 && trim($parts[1]) === $login) {
                $exists = true;
                break;
            }
        }
    }
    
    if (!$exists) {
        $current_content = '';
        if (file_exists($users_file)) {
            $current_content = file_get_contents($users_file);
        }
        
        if (!empty($current_content) && substr($current_content, -1) !== "\n") {
            file_put_contents($users_file, "\n", FILE_APPEND);
        }
        
        $user_line = $name . '|' . $login . '|' . $password . '|light' . "\n";
        file_put_contents($users_file, $user_line, FILE_APPEND | LOCK_EX);
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Логин уже занят']);
    }
    exit;
}

if (isset($_POST['login'])) {
    $login = trim($_POST['login']);
    $password = trim($_POST['password']);
    
    $users = [];
    if (file_exists($users_file)) {
        $content = file_get_contents($users_file);
        $lines = explode("\n", trim($content));
        
        foreach ($lines as $line) {
            if (!empty($line)) {
                $parts = explode('|', $line);
                if (count($parts) >= 3) {
                    $users[] = [
                        'name' => trim($parts[0]),
                        'login' => trim($parts[1]),
                        'password' => trim($parts[2]),
                        'theme' => isset($parts[3]) ? trim($parts[3]) : 'light'
                    ];
                }
            }
        }
    }
    
    $logged = false;
    $user_name = '';
    $user_theme = 'light';
    
    foreach ($users as $user) {
        if ($user['login'] === $login && $user['password'] === $password) {
            $logged = true;
            $user_name = $user['name'];
            $user_theme = $user['theme'];
            break;
        }
    }
    
    if ($logged) {
        $_SESSION['user'] = $login;
        $_SESSION['name'] = $user_name;
        $_SESSION['theme'] = $user_theme;
        
        setcookie('remember_user', $login, time() + (86400 * 30), '/');
        setcookie('user_theme', $user_theme, time() + (86400 * 30), '/');
        
        echo json_encode(['success' => true, 'name' => $user_name, 'theme' => $user_theme]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Неверный логин или пароль']);
    }
    exit;
}

if (isset($_POST['update_theme'])) {
    if (!isset($_SESSION['user'])) exit;
    
    $new_theme = $_POST['theme'];
    $login = $_SESSION['user'];
    
    if (file_exists($users_file)) {
        $content = file_get_contents($users_file);
        $lines = explode("\n", trim($content));
        $new_lines = [];
        
        foreach ($lines as $line) {
            if (!empty($line)) {
                $parts = explode('|', $line);
                if (trim($parts[1]) === $login) {
                    if (count($parts) >= 4) {
                        $parts[3] = $new_theme;
                    } else {
                        $parts[] = $new_theme;
                    }
                    $line = implode('|', $parts);
                }
                $new_lines[] = $line;
            }
        }
        
        file_put_contents($users_file, implode("\n", $new_lines) . "\n");
        $_SESSION['theme'] = $new_theme;
        setcookie('user_theme', $new_theme, time() + (86400 * 30), '/');
        
        echo json_encode(['success' => true]);
    }
    exit;
}

if (isset($_GET['check_session'])) {
    if (isset($_SESSION['user'])) {
        echo json_encode([
            'logged' => true, 
            'user' => $_SESSION['user'], 
            'name' => $_SESSION['name'],
            'theme' => isset($_SESSION['theme']) ? $_SESSION['theme'] : 'light'
        ]);
    } elseif (isset($_COOKIE['remember_user'])) {
        $_SESSION['user'] = $_COOKIE['remember_user'];
        $_SESSION['theme'] = isset($_COOKIE['user_theme']) ? $_COOKIE['user_theme'] : 'light';
        echo json_encode([
            'logged' => true, 
            'user' => $_COOKIE['remember_user'],
            'theme' => isset($_COOKIE['user_theme']) ? $_COOKIE['user_theme'] : 'light'
        ]);
    } else {
        echo json_encode(['logged' => false]);
    }
    exit;
}

if (isset($_POST['logout'])) {
    session_destroy();
    setcookie('remember_user', '', time() - 3600, '/');
    setcookie('user_theme', '', time() - 3600, '/');
    exit;
}

if (isset($_POST['send_message'])) {
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Не авторизован']);
        exit;
    }
    
    $from = isset($_SESSION['name']) ? $_SESSION['name'] : $_SESSION['user'];
    $message = trim($_POST['message']);
    $type = isset($_POST['type']) ? $_POST['type'] : 'text';
    
    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Пустое сообщение']);
        exit;
    }
    
    $time = date('H:i:s');
    $date = date('d.m.Y');
    
    $new_message = $from . '|' . $message . '|' . $time . '|' . $date . '|' . $type . "\n";
    file_put_contents($messages_file, $new_message, FILE_APPEND | LOCK_EX);
    
    $messages = getMessages();
    
    echo json_encode(['success' => true, 'messages' => $messages]);
    exit;
}

function getMessages() {
    global $messages_file;
    $messages = [];
    
    if (file_exists($messages_file)) {
        $content = file_get_contents($messages_file);
        $lines = explode("\n", trim($content));
        $lines = array_filter($lines);
        
        foreach ($lines as $line) {
            if (!empty($line)) {
                $parts = explode('|', $line);
                if (count($parts) >= 5) {
                    $messages[] = [
                        'from' => trim($parts[0]),
                        'message' => trim($parts[1]),
                        'time' => trim($parts[2]),
                        'date' => trim($parts[3]),
                        'type' => trim($parts[4])
                    ];
                }
            }
        }
    }
    
    return $messages;
}

if (isset($_GET['get_messages'])) {
    if (!isset($_SESSION['user'])) {
        echo json_encode([]);
        exit;
    }
    
    echo json_encode(getMessages());
    exit;
}

if (isset($_GET['get_gifs'])) {
    $search = isset($_GET['search']) ? $_GET['search'] : 'funny';
    $api_key = 'YOUR_GIPHY_API_KEY'; // Замените на свой API ключ с Giphy
    
    // Для демо используем локальные гифки
    $gifs = [
        'funny' => [
            'https://media.giphy.com/media/3o7abB06u9bNzA8LC8/giphy.gif',
            'https://media.giphy.com/media/l0MYt5jH6gkTWm8qo/giphy.gif',
            'https://media.giphy.com/media/xT0xeJpnrWC4XWblEk/giphy.gif',
            'https://media.giphy.com/media/3ohzdIvnJNp2WYhMPm/giphy.gif',
            'https://media.giphy.com/media/l41YtZObRrI0W405m/giphy.gif',
            'https://media.giphy.com/media/26BRv0ThflsHCqDrG/giphy.gif'
        ],
        'cat' => [
            'https://media.giphy.com/media/JIX9t2j0ZTN9S/giphy.gif',
            'https://media.giphy.com/media/mlvseq9yvZhba/giphy.gif',
            'https://media.giphy.com/media/3oriO0OEd9QIDdllqo/giphy.gif'
        ],
        'dog' => [
            'https://media.giphy.com/media/8vQSQ3cNXuDGo/giphy.gif',
            'https://media.giphy.com/media/3o7abB06u9bNzA8LC8/giphy.gif',
            'https://media.giphy.com/media/l0MYt5jH6gkTWm8qo/giphy.gif'
        ]
    ];
    
    $search_lower = strtolower($search);
    $results = [];
    
    foreach ($gifs as $key => $gif_list) {
        if (strpos($key, $search_lower) !== false || $search_lower === 'funny') {
            $results = array_merge($results, $gif_list);
        }
    }
    
    if (empty($results)) {
        $results = $gifs['funny'];
    }
    
    echo json_encode(array_slice($results, 0, 12));
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>✨ Космический Чат ✨</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 12px;
            position: relative;
            overflow: hidden;
            transition: all 0.5s ease;
        }
        
        /* Светлая тема - космический закат */
        body.light-theme {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #ff6b6b 100%);
            background-size: 400% 400%;
            animation: galaxyMove 20s ease infinite;
        }
        
        /* Темная тема - глубокий космос */
        body.dark-theme {
            background: radial-gradient(ellipse at bottom, #1B2735 0%, #090A0F 100%);
            position: relative;
        }
        
        /* Анимация движения галактики */
        @keyframes galaxyMove {
            0% { background-position: 0% 0%; }
            50% { background-position: 100% 100%; }
            100% { background-position: 0% 0%; }
        }
        
        /* Звезды для темной темы */
        body.dark-theme::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(2px 2px at 10px 20px, #fff, rgba(0,0,0,0)),
                radial-gradient(2px 2px at 30px 70px, #fff, rgba(0,0,0,0)),
                radial-gradient(2px 2px at 50px 150px, #fff, rgba(0,0,0,0)),
                radial-gradient(2px 2px at 90px 40px, #fff, rgba(0,0,0,0)),
                radial-gradient(2px 2px at 130px 80px, #fff, rgba(0,0,0,0)),
                radial-gradient(2px 2px at 160px 120px, #fff, rgba(0,0,0,0)),
                radial-gradient(2px 2px at 200px 190px, #fff, rgba(0,0,0,0)),
                radial-gradient(2px 2px at 250px 50px, #fff, rgba(0,0,0,0)),
                radial-gradient(2px 2px at 280px 180px, #fff, rgba(0,0,0,0)),
                radial-gradient(2px 2px at 320px 120px, #fff, rgba(0,0,0,0)),
                radial-gradient(2px 2px at 380px 90px, #fff, rgba(0,0,0,0)),
                radial-gradient(2px 2px at 420px 160px, #fff, rgba(0,0,0,0)),
                radial-gradient(2px 2px at 460px 30px, #fff, rgba(0,0,0,0)),
                radial-gradient(2px 2px at 500px 140px, #fff, rgba(0,0,0,0)),
                radial-gradient(2px 2px at 550px 70px, #fff, rgba(0,0,0,0)),
                radial-gradient(2px 2px at 590px 110px, #fff, rgba(0,0,0,0));
            background-repeat: repeat;
            background-size: 600px 200px;
            opacity: 0.5;
            animation: stars 100s linear infinite;
            pointer-events: none;
        }
        
        @keyframes stars {
            from { transform: translateY(0); }
            to { transform: translateY(-200px); }
        }
        
        /* Падающие звезды для темной темы */
        body.dark-theme .shooting-star {
            position: absolute;
            width: 100px;
            height: 2px;
            background: linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.8) 50%, rgba(255,255,255,0) 100%);
            transform: rotate(-45deg);
            animation: shoot 4s linear infinite;
            opacity: 0;
            pointer-events: none;
        }
        
        @keyframes shoot {
            0% { transform: translateX(-200px) rotate(-45deg); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateX(2000px) rotate(-45deg); opacity: 0; }
        }
        
        /* Северное сияние для темной темы */
        body.dark-theme .aurora {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 200px;
            background: linear-gradient(135deg, 
                rgba(64, 224, 208, 0.1) 0%, 
                rgba(138, 43, 226, 0.1) 25%, 
                rgba(0, 255, 127, 0.1) 50%, 
                rgba(75, 0, 130, 0.1) 75%, 
                rgba(64, 224, 208, 0.1) 100%);
            filter: blur(40px);
            animation: auroraWave 15s ease infinite;
            pointer-events: none;
        }
        
        @keyframes auroraWave {
            0%, 100% { transform: translateY(-50px) scale(1); opacity: 0.3; }
            50% { transform: translateY(0) scale(1.2); opacity: 0.6; }
        }
        
        .auth-screen {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            padding: 40px 30px;
            max-width: 450px;
            width: 100%;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.3);
            position: relative;
            z-index: 10;
            transition: all 0.3s ease;
        }
        
        body.dark-theme .auth-screen {
            background: rgba(20, 15, 30, 0.95);
            border: 1px solid rgba(138, 43, 226, 0.3);
            box-shadow: 0 25px 50px rgba(138, 43, 226, 0.3);
        }
        
        .auth-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            background: rgba(0,0,0,0.05);
            padding: 5px;
            border-radius: 50px;
        }
        
        body.dark-theme .auth-tabs {
            background: rgba(138, 43, 226, 0.2);
        }
        
        .auth-tab {
            flex: 1;
            padding: 15px 10px;
            border: none;
            background: transparent;
            border-radius: 50px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            color: #666;
        }
        
        body.dark-theme .auth-tab {
            color: #aaa;
        }
        
        .auth-tab.active {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        body.dark-theme .auth-tab.active {
            background: linear-gradient(45deg, #8a2be2, #4b0082);
            box-shadow: 0 5px 15px rgba(138, 43, 226, 0.5);
        }
        
        .auth-form {
            display: none;
        }
        
        .auth-form.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .auth-form h2 {
            text-align: center;
            margin-bottom: 30px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 28px;
        }
        
        body.dark-theme .auth-form h2 {
            background: linear-gradient(45deg, #8a2be2, #ff69b4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .input-group {
            margin-bottom: 20px;
        }
        
        .input-group label {
            display: block;
            margin-bottom: 8px;
            color: #444;
            font-size: 15px;
            font-weight: 600;
        }
        
        body.dark-theme .input-group label {
            color: #ddd;
        }
        
        .auth-form input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #eaeaea;
            border-radius: 15px;
            font-size: 16px;
            transition: all 0.3s;
            background: rgba(255,255,255,0.9);
            -webkit-appearance: none;
        }
        
        body.dark-theme .auth-form input {
            background: rgba(40, 30, 50, 0.9);
            border-color: #8a2be2;
            color: white;
        }
        
        .auth-form input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.2);
            transform: translateY(-2px);
        }
        
        body.dark-theme .auth-form input:focus {
            border-color: #8a2be2;
            box-shadow: 0 0 0 4px rgba(138, 43, 226, 0.3);
        }
        
        .auth-form button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 15px;
            -webkit-appearance: none;
            position: relative;
            overflow: hidden;
        }
        
        body.dark-theme .auth-form button {
            background: linear-gradient(45deg, #8a2be2, #4b0082);
        }
        
        .auth-form button:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.5);
        }
        
        body.dark-theme .auth-form button:hover {
            box-shadow: 0 10px 25px rgba(138, 43, 226, 0.5);
        }
        
        .error {
            color: #ff4444;
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
            animation: shake 0.5s ease;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .success {
            color: #00c851;
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
            animation: popIn 0.5s ease;
        }
        
        @keyframes popIn {
            0% { transform: scale(0); }
            70% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .chat-app {
            max-width: 1200px;
            width: 100%;
            height: 90vh;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            overflow: hidden;
            display: none;
            flex-direction: column;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.3);
            position: relative;
            z-index: 10;
            transition: all 0.3s ease;
        }
        
        body.dark-theme .chat-app {
            background: rgba(20, 15, 30, 0.95);
            border: 1px solid rgba(138, 43, 226, 0.3);
            box-shadow: 0 25px 50px rgba(138, 43, 226, 0.3);
        }
        
        .chat-app.show {
            display: flex;
        }
        
        .chat-header {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        body.dark-theme .chat-header {
            background: linear-gradient(45deg, #8a2be2, #4b0082);
        }
        
        .chat-header h2 {
            font-size: 24px;
            font-weight: 600;
            text-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .current-user {
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 16px;
            backdrop-filter: blur(5px);
        }
        
        .icon-btn {
            width: 45px;
            height: 45px;
            border: none;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            color: white;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-appearance: none;
            backdrop-filter: blur(5px);
        }
        
        .icon-btn:hover {
            transform: scale(1.1);
            background: rgba(255,255,255,0.3);
        }
        
        .logout-btn {
            padding: 10px 22px;
            background: #ff4444;
            color: white;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            -webkit-appearance: none;
            box-shadow: 0 5px 15px rgba(255,68,68,0.3);
        }
        
        .logout-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(255,68,68,0.4);
        }
        
        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 25px;
            background: rgba(255,255,255,0.5);
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        body.dark-theme .messages-container {
            background: rgba(30, 20, 40, 0.5);
        }
        
        .message {
            max-width: 70%;
            padding: 15px 20px;
            border-radius: 20px;
            animation: messageAppear 0.3s ease;
            font-size: 15px;
            word-wrap: break-word;
            position: relative;
        }
        
        @keyframes messageAppear {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .message.sent {
            align-self: flex-end;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border-bottom-right-radius: 5px;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        body.dark-theme .message.sent {
            background: linear-gradient(45deg, #8a2be2, #4b0082);
            box-shadow: 0 5px 15px rgba(138, 43, 226, 0.3);
        }
        
        .message.received {
            align-self: flex-start;
            background: white;
            color: #333;
            border-bottom-left-radius: 5px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        body.dark-theme .message.received {
            background: #2a1a3a;
            color: #eee;
            box-shadow: 0 5px 15px rgba(138, 43, 226, 0.2);
        }
        
        .message-sender {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 5px;
            opacity: 0.8;
        }
        
        .message-content {
            margin-bottom: 5px;
            line-height: 1.4;
        }
        
        .message-image, .message-gif {
            max-width: 100%;
            max-height: 300px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .message-image:hover, .message-gif:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }
        
        .message-time {
            font-size: 11px;
            text-align: right;
            margin-top: 5px;
            opacity: 0.6;
        }
        
        .message-date {
            font-size: 10px;
            text-align: right;
            opacity: 0.4;
            margin-top: 2px;
        }
        
        .chat-input-area {
            padding: 20px 25px;
            background: white;
            border-top: 2px solid rgba(102, 126, 234, 0.2);
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        body.dark-theme .chat-input-area {
            background: #1a0f2a;
            border-top-color: rgba(138, 43, 226, 0.3);
        }
        
        .chat-input-area input {
            flex: 1;
            min-width: 200px;
            padding: 16px 22px;
            border: 2px solid #eaeaea;
            border-radius: 30px;
            font-size: 16px;
            transition: all 0.3s;
            -webkit-appearance: none;
        }
        
        body.dark-theme .chat-input-area input {
            background: #2a1a3a;
            border-color: #8a2be2;
            color: white;
        }
        
        .chat-input-area input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.2);
            transform: translateY(-2px);
        }
        
        body.dark-theme .chat-input-area input:focus {
            border-color: #8a2be2;
            box-shadow: 0 0 0 4px rgba(138, 43, 226, 0.3);
        }
        
        /* Только эти кнопки будут крутиться */
        .spin-btn {
            width: 55px;
            height: 55px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-size: 26px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-appearance: none;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        body.dark-theme .spin-btn {
            background: linear-gradient(45deg, #8a2be2, #4b0082);
            box-shadow: 0 5px 15px rgba(138, 43, 226, 0.4);
        }
        
        .spin-btn:hover {
            transform: rotate(360deg) scale(1.1);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.5);
        }
        
        .spin-btn:active {
            transform: rotate(360deg) scale(0.95);
        }
        
        /* Обычные кнопки без вращения */
        .normal-btn {
            width: 55px;
            height: 55px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-size: 26px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-appearance: none;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        body.dark-theme .normal-btn {
            background: linear-gradient(45deg, #8a2be2, #4b0082);
            box-shadow: 0 5px 15px rgba(138, 43, 226, 0.4);
        }
        
        .normal-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.5);
        }
        
        .normal-btn:active {
            transform: scale(0.95);
        }
        
        .file-input {
            display: none;
        }
        
        .file-label {
            width: 55px;
            height: 55px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        body.dark-theme .file-label {
            background: linear-gradient(45deg, #8a2be2, #4b0082);
            box-shadow: 0 5px 15px rgba(138, 43, 226, 0.4);
        }
        
        .file-label:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.5);
        }
        
        .emoji-panel {
            position: absolute;
            bottom: 100px;
            left: 25px;
            right: 25px;
            background: white;
            border-radius: 25px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            display: none;
            grid-template-columns: repeat(10, 1fr);
            gap: 10px;
            z-index: 100;
            max-height: 300px;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
        }
        
        body.dark-theme .emoji-panel {
            background: #2a1a3a;
            border: 1px solid #8a2be2;
            box-shadow: 0 10px 30px rgba(138, 43, 226, 0.3);
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .emoji-panel.show {
            display: grid;
        }
        
        .emoji-item {
            font-size: 32px;
            text-align: center;
            cursor: pointer;
            padding: 12px;
            border-radius: 15px;
            transition: all 0.4s ease;
        }
        
        .emoji-item:hover {
            transform: rotate(360deg) scale(1.2);
            background: rgba(102, 126, 234, 0.2);
        }
        
        body.dark-theme .emoji-item:hover {
            background: rgba(138, 43, 226, 0.3);
        }
        
        .gif-search {
            position: absolute;
            bottom: 100px;
            left: 25px;
            right: 25px;
            background: white;
            border-radius: 25px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            display: none;
            z-index: 100;
            max-height: 400px;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
        }
        
        body.dark-theme .gif-search {
            background: #2a1a3a;
            border: 1px solid #8a2be2;
            box-shadow: 0 10px 30px rgba(138, 43, 226, 0.3);
        }
        
        .gif-search.show {
            display: block;
        }
        
        .gif-search input {
            width: 100%;
            padding: 15px;
            border: 2px solid #eaeaea;
            border-radius: 20px;
            margin-bottom: 20px;
            font-size: 16px;
        }
        
        body.dark-theme .gif-search input {
            background: #1a0f2a;
            border-color: #8a2be2;
            color: white;
        }
        
        .gif-results {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        
        .gif-item {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .gif-item:hover {
            transform: scale(1.1) rotate(2deg);
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }
        
        .settings-panel {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 30px;
            padding: 35px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            z-index: 1000;
            display: none;
            width: 90%;
            max-width: 450px;
            animation: popIn 0.4s ease;
        }
        
        @keyframes popIn {
            0% {
                opacity: 0;
                transform: translate(-50%, -50%) scale(0.7);
            }
            100% {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
        }
        
        body.dark-theme .settings-panel {
            background: #2a1a3a;
            border: 1px solid #8a2be2;
            color: white;
        }
        
        .settings-panel.show {
            display: block;
        }
        
        .settings-panel h3 {
            margin-bottom: 25px;
            font-size: 28px;
            text-align: center;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        body.dark-theme .settings-panel h3 {
            background: linear-gradient(45deg, #8a2be2, #ff69b4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .settings-option {
            margin: 25px 0;
        }
        
        .settings-option label {
            display: block;
            margin-bottom: 12px;
            font-size: 18px;
            font-weight: 600;
        }
        
        .settings-theme-buttons {
            display: flex;
            gap: 15px;
        }
        
        .settings-theme-btn {
            flex: 1;
            padding: 15px;
            border: 2px solid #eaeaea;
            border-radius: 15px;
            background: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
            font-size: 16px;
        }
        
        body.dark-theme .settings-theme-btn {
            background: #1a0f2a;
            border-color: #8a2be2;
            color: white;
        }
        
        .settings-theme-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        .settings-theme-btn.active {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-color: transparent;
            color: white;
        }
        
        body.dark-theme .settings-theme-btn.active {
            background: linear-gradient(45deg, #8a2be2, #4b0082);
        }
        
        .settings-close {
            width: 100%;
            padding: 16px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 25px;
            transition: all 0.3s;
        }
        
        body.dark-theme .settings-close {
            background: linear-gradient(45deg, #8a2be2, #4b0082);
        }
        
        .settings-close:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .loading-gif {
            text-align: center;
            padding: 30px;
            color: #666;
        }
        
        .no-gifs {
            text-align: center;
            padding: 30px;
            color: #666;
            grid-column: span 3;
        }
        
        ::-webkit-scrollbar {
            width: 10px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(0,0,0,0.05);
            border-radius: 5px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-radius: 5px;
        }
        
        body.dark-theme ::-webkit-scrollbar-thumb {
            background: linear-gradient(45deg, #8a2be2, #4b0082);
        }
        
        @media (max-width: 768px) {
            .emoji-panel {
                grid-template-columns: repeat(8, 1fr);
                bottom: 90px;
            }
            
            .gif-results {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .message {
                max-width: 85%;
            }
        }
        
        @media (max-width: 480px) {
            .auth-screen {
                padding: 25px 20px;
            }
            
            .chat-header {
                padding: 15px 20px;
            }
            
            .chat-header h2 {
                font-size: 20px;
            }
            
            .current-user {
                padding: 8px 15px;
                font-size: 14px;
            }
            
            .icon-btn, .spin-btn, .normal-btn, .file-label {
                width: 50px;
                height: 50px;
                font-size: 24px;
            }
            
            .logout-btn {
                padding: 8px 18px;
                font-size: 14px;
            }
            
            .message {
                max-width: 90%;
                padding: 12px 16px;
                font-size: 14px;
            }
            
            .chat-input-area {
                padding: 15px;
            }
            
            .chat-input-area input {
                padding: 14px 18px;
                font-size: 15px;
            }
            
            .emoji-panel {
                grid-template-columns: repeat(6, 1fr);
                padding: 15px;
                gap: 8px;
            }
            
            .emoji-item {
                font-size: 28px;
                padding: 8px;
            }
            
            .gif-search {
                bottom: 90px;
                padding: 15px;
            }
            
            .gif-results {
                gap: 10px;
            }
        }
    </style>
</head>
<body class="light-theme" id="body">
    <!-- Элементы для темной темы -->
    <div class="shooting-star" style="top: 10%; left: -10%; animation-delay: 0s;"></div>
    <div class="shooting-star" style="top: 30%; left: -10%; animation-delay: 2s;"></div>
    <div class="shooting-star" style="top: 50%; left: -10%; animation-delay: 4s;"></div>
    <div class="shooting-star" style="top: 70%; left: -10%; animation-delay: 6s;"></div>
    <div class="shooting-star" style="top: 90%; left: -10%; animation-delay: 8s;"></div>
    <div class="aurora"></div>
    
    <div id="authScreen" class="auth-screen">
        <div class="auth-tabs">
            <button class="auth-tab active" id="loginTab">🚀 Вход</button>
            <button class="auth-tab" id="registerTab">✨ Регистрация</button>
        </div>
        
        <div id="loginForm" class="auth-form active">
            <h2>🌟 Добро пожаловать</h2>
            <div class="input-group">
                <label>🔑 Логин</label>
                <input type="text" id="loginLogin" placeholder="Введите логин">
            </div>
            <div class="input-group">
                <label>🔒 Пароль</label>
                <input type="password" id="loginPassword" placeholder="Введите пароль">
            </div>
            <button id="loginBtn">💫 Войти в чат</button>
            <div id="loginError" class="error"></div>
        </div>
        
        <div id="registerForm" class="auth-form">
            <h2>⭐ Создать аккаунт</h2>
            <div class="input-group">
                <label>👤 Имя</label>
                <input type="text" id="regName" placeholder="Введите ваше имя">
            </div>
            <div class="input-group">
                <label>🔑 Логин</label>
                <input type="text" id="regLogin" placeholder="Придумайте логин">
            </div>
            <div class="input-group">
                <label>🔒 Пароль</label>
                <input type="password" id="regPassword" placeholder="Придумайте пароль">
            </div>
            <div class="input-group">
                <label>✅ Подтверждение</label>
                <input type="password" id="regConfirm" placeholder="Повторите пароль">
            </div>
            <button id="registerBtn">🎉 Зарегистрироваться</button>
            <div id="regError" class="error"></div>
            <div id="regSuccess" class="success"></div>
        </div>
    </div>
    
    <div id="chatApp" class="chat-app">
        <div class="chat-header">
            <h2>✨ Космический Чат ✨</h2>
            <div class="user-info">
                <span class="current-user" id="currentUser"></span>
                <button class="icon-btn" id="settingsBtn">⚙️</button>
                <button class="logout-btn" id="logoutBtn">Выйти</button>
            </div>
        </div>
        
        <div class="messages-container" id="messagesContainer"></div>
        
        <div style="position: relative;">
            <div class="emoji-panel" id="emojiPanel">
                <span class="emoji-item">😊</span>
                <span class="emoji-item">😂</span>
                <span class="emoji-item">❤️</span>
                <span class="emoji-item">👍</span>
                <span class="emoji-item">🎉</span>
                <span class="emoji-item">✨</span>
                <span class="emoji-item">🔥</span>
                <span class="emoji-item">😎</span>
                <span class="emoji-item">🥺</span>
                <span class="emoji-item">😢</span>
                <span class="emoji-item">😭</span>
                <span class="emoji-item">😍</span>
                <span class="emoji-item">🥰</span>
                <span class="emoji-item">😘</span>
                <span class="emoji-item">🤔</span>
                <span class="emoji-item">🤯</span>
                <span class="emoji-item">😱</span>
                <span class="emoji-item">🥳</span>
                <span class="emoji-item">😴</span>
                <span class="emoji-item">🤪</span>
                <span class="emoji-item">😈</span>
                <span class="emoji-item">👻</span>
                <span class="emoji-item">💀</span>
                <span class="emoji-item">🎃</span>
                <span class="emoji-item">🤖</span>
                <span class="emoji-item">👽</span>
                <span class="emoji-item">🐱</span>
                <span class="emoji-item">🐶</span>
                <span class="emoji-item">🐼</span>
                <span class="emoji-item">🦊</span>
                <span class="emoji-item">🐨</span>
                <span class="emoji-item">🐸</span>
                <span class="emoji-item">🐧</span>
                <span class="emoji-item">🦁</span>
                <span class="emoji-item">🐰</span>
                <span class="emoji-item">🦝</span>
                <span class="emoji-item">🐮</span>
                <span class="emoji-item">🦄</span>
                <span class="emoji-item">🐲</span>
                <span class="emoji-item">🌈</span>
                <span class="emoji-item">⭐</span>
                <span class="emoji-item">🌙</span>
                <span class="emoji-item">☀️</span>
                <span class="emoji-item">🌟</span>
                <span class="emoji-item">💫</span>
            </div>
            
            <div class="gif-search" id="gifSearch">
                <input type="text" id="gifSearchInput" placeholder="🔍 Поиск гифок..." value="funny">
                <div class="gif-results" id="gifResults">
                    <div class="loading-gif">Загрузка гифок...</div>
                </div>
            </div>
            
            <div class="chat-input-area">
                <button class="spin-btn" id="emojiBtn">😊</button>
                <button class="normal-btn" id="gifBtn">GIF</button>
                <label for="fileInput" class="file-label">📷</label>
                <input type="file" id="fileInput" class="file-input" accept="image/*">
                <input type="text" id="messageInput" placeholder="💭 Напишите сообщение...">
                <button class="spin-btn" id="sendMessageBtn">➤</button>
            </div>
        </div>
    </div>
    
    <div class="settings-panel" id="settingsPanel">
        <h3>⚙️ Настройки</h3>
        <div class="settings-option">
            <label>🎨 Тема оформления</label>
            <div class="settings-theme-buttons">
                <button class="settings-theme-btn light active" id="settingsThemeLight">☀️ Светлая</button>
                <button class="settings-theme-btn dark" id="settingsThemeDark">🌙 Темная</button>
            </div>
        </div>
        <button class="settings-close" id="settingsClose">Закрыть</button>
    </div>

    <script>
        let currentUser = null;
        let currentName = null;
        let currentTheme = 'light';
        let lastMessageCount = 0;
        let isLoading = false;
        let gifLoadTimeout = null;
        
        const body = document.getElementById('body');
        const authScreen = document.getElementById('authScreen');
        const chatApp = document.getElementById('chatApp');
        
        const loginTab = document.getElementById('loginTab');
        const registerTab = document.getElementById('registerTab');
        const loginForm = document.getElementById('loginForm');
        const registerForm = document.getElementById('registerForm');
        
        const loginLogin = document.getElementById('loginLogin');
        const loginPassword = document.getElementById('loginPassword');
        const loginBtn = document.getElementById('loginBtn');
        const loginError = document.getElementById('loginError');
        
        const regName = document.getElementById('regName');
        const regLogin = document.getElementById('regLogin');
        const regPassword = document.getElementById('regPassword');
        const regConfirm = document.getElementById('regConfirm');
        const registerBtn = document.getElementById('registerBtn');
        const regError = document.getElementById('regError');
        const regSuccess = document.getElementById('regSuccess');
        
        const currentUserSpan = document.getElementById('currentUser');
        const logoutBtn = document.getElementById('logoutBtn');
        const settingsBtn = document.getElementById('settingsBtn');
        const messagesContainer = document.getElementById('messagesContainer');
        const messageInput = document.getElementById('messageInput');
        const sendMessageBtn = document.getElementById('sendMessageBtn');
        const emojiBtn = document.getElementById('emojiBtn');
        const emojiPanel = document.getElementById('emojiPanel');
        const gifBtn = document.getElementById('gifBtn');
        const gifSearch = document.getElementById('gifSearch');
        const gifSearchInput = document.getElementById('gifSearchInput');
        const gifResults = document.getElementById('gifResults');
        const fileInput = document.getElementById('fileInput');
        const settingsPanel = document.getElementById('settingsPanel');
        const settingsClose = document.getElementById('settingsClose');
        const settingsThemeLight = document.getElementById('settingsThemeLight');
        const settingsThemeDark = document.getElementById('settingsThemeDark');
        
        // Загружаем гифки при загрузке страницы
        loadGifs('funny');
        
        checkSession();
        
        async function checkSession() {
            try {
                let response = await fetch('?check_session=1');
                let data = await response.json();
                if (data.logged) {
                    currentUser = data.user;
                    currentName = data.name || data.user;
                    currentTheme = data.theme || 'light';
                    currentUserSpan.textContent = currentName;
                    authScreen.style.display = 'none';
                    chatApp.classList.add('show');
                    setTheme(currentTheme);
                    loadMessages();
                    setInterval(loadMessages, 2000);
                }
            } catch(e) {
                console.log('Session check error:', e);
            }
        }
        
        function setTheme(theme) {
            body.className = theme + '-theme';
            currentTheme = theme;
            
            if (settingsThemeLight && settingsThemeDark) {
                if (theme === 'light') {
                    settingsThemeLight.classList.add('active');
                    settingsThemeDark.classList.remove('active');
                } else {
                    settingsThemeDark.classList.add('active');
                    settingsThemeLight.classList.remove('active');
                }
            }
        }
        
        async function updateTheme(theme) {
            let formData = new FormData();
            formData.append('update_theme', true);
            formData.append('theme', theme);
            
            try {
                await fetch('', { method: 'POST', body: formData });
                setTheme(theme);
            } catch(e) {
                console.log('Theme update error:', e);
            }
        }
        
        async function loadGifs(search = 'funny') {
            try {
                gifResults.innerHTML = '<div class="loading-gif">Загрузка гифок...</div>';
                
                // Используем локальный API
                let response = await fetch('?get_gifs=1&search=' + encodeURIComponent(search));
                let gifs = await response.json();
                
                if (gifs && gifs.length > 0) {
                    gifResults.innerHTML = '';
                    gifs.forEach(url => {
                        let img = document.createElement('img');
                        img.src = url;
                        img.className = 'gif-item';
                        img.onclick = function() { selectGif(url); };
                        img.onerror = function() { this.style.display = 'none'; };
                        gifResults.appendChild(img);
                    });
                } else {
                    gifResults.innerHTML = '<div class="no-gifs">😕 Гифки не найдены</div>';
                }
            } catch(e) {
                console.log('Load gifs error:', e);
                gifResults.innerHTML = '<div class="no-gifs">❌ Ошибка загрузки гифок</div>';
            }
        }
        
        if (loginTab) {
            loginTab.onclick = function() {
                loginTab.classList.add('active');
                registerTab.classList.remove('active');
                loginForm.classList.add('active');
                registerForm.classList.remove('active');
                loginError.textContent = '';
            };
        }
        
        if (registerTab) {
            registerTab.onclick = function() {
                registerTab.classList.add('active');
                loginTab.classList.remove('active');
                registerForm.classList.add('active');
                loginForm.classList.remove('active');
                regError.textContent = '';
                regSuccess.textContent = '';
            };
        }
        
        if (registerBtn) {
            registerBtn.onclick = async function() {
                let name = regName.value.trim();
                let login = regLogin.value.trim();
                let password = regPassword.value.trim();
                let confirm = regConfirm.value.trim();
                
                if (!name || !login || !password) {
                    regError.textContent = '❌ Заполните все поля';
                    return;
                }
                if (password !== confirm) {
                    regError.textContent = '❌ Пароли не совпадают';
                    return;
                }
                
                let formData = new FormData();
                formData.append('register', true);
                formData.append('name', name);
                formData.append('login', login);
                formData.append('password', password);
                
                try {
                    let response = await fetch('', { method: 'POST', body: formData });
                    let result = await response.json();
                    
                    if (result.success) {
                        regSuccess.textContent = '✅ Регистрация успешна!';
                        regName.value = ''; 
                        regLogin.value = ''; 
                        regPassword.value = ''; 
                        regConfirm.value = '';
                        setTimeout(function() { 
                            if (loginTab) loginTab.click(); 
                        }, 2000);
                    } else {
                        regError.textContent = '❌ ' + result.message;
                    }
                } catch(e) {
                    regError.textContent = '❌ Ошибка соединения';
                }
            };
        }
        
        if (loginBtn) {
            loginBtn.onclick = async function() {
                let login = loginLogin.value.trim();
                let password = loginPassword.value.trim();
                
                if (!login || !password) {
                    loginError.textContent = '❌ Введите логин и пароль';
                    return;
                }
                
                let formData = new FormData();
                formData.append('login', true);
                formData.append('login', login);
                formData.append('password', password);
                
                try {
                    let response = await fetch('', { method: 'POST', body: formData });
                    let result = await response.json();
                    
                    if (result.success) {
                        currentUser = login;
                        currentName = result.name;
                        currentTheme = result.theme || 'light';
                        currentUserSpan.textContent = currentName;
                        authScreen.style.display = 'none';
                        chatApp.classList.add('show');
                        setTheme(currentTheme);
                        loadMessages();
                        setInterval(loadMessages, 2000);
                    } else {
                        loginError.textContent = '❌ ' + result.message;
                    }
                } catch(e) {
                    loginError.textContent = '❌ Ошибка соединения';
                }
            };
        }
        
        if (logoutBtn) {
            logoutBtn.onclick = async function() {
                let formData = new FormData();
                formData.append('logout', true);
                try {
                    await fetch('', { method: 'POST', body: formData });
                    chatApp.classList.remove('show');
                    authScreen.style.display = 'block';
                    lastMessageCount = 0;
                    setTheme('light');
                } catch(e) {
                    console.log('Logout error:', e);
                }
            };
        }
        
        async function loadMessages() {
            if (isLoading) return;
            
            try {
                isLoading = true;
                let response = await fetch('?get_messages=1');
                let messages = await response.json();
                
                if (messages && messages.length !== lastMessageCount) {
                    let shouldScroll = messagesContainer.scrollHeight - messagesContainer.scrollTop - messagesContainer.clientHeight < 100;
                    
                    messagesContainer.innerHTML = '';
                    
                    if (messages.length > 0) {
                        messages.forEach(function(msg) {
                            let div = document.createElement('div');
                            div.className = 'message ' + (msg.from === currentName ? 'sent' : 'received');
                            
                            let sender = document.createElement('div');
                            sender.className = 'message-sender';
                            sender.textContent = msg.from;
                            
                            let content = document.createElement('div');
                            content.className = 'message-content';
                            
                            if (msg.type === 'image' || msg.type === 'gif') {
                                let img = document.createElement('img');
                                img.src = msg.message;
                                img.className = msg.type === 'image' ? 'message-image' : 'message-gif';
                                img.onclick = function() { window.open(msg.message, '_blank'); };
                                img.onerror = function() { 
                                    this.onerror = null;
                                    this.src = 'https://via.placeholder.com/300x200?text=Ошибка+загрузки';
                                };
                                content.appendChild(img);
                            } else {
                                content.textContent = msg.message;
                            }
                            
                            let time = document.createElement('div');
                            time.className = 'message-time';
                            time.textContent = msg.time;
                            
                            let date = document.createElement('div');
                            date.className = 'message-date';
                            date.textContent = msg.date;
                            
                            div.appendChild(sender);
                            div.appendChild(content);
                            div.appendChild(time);
                            div.appendChild(date);
                            messagesContainer.appendChild(div);
                        });
                    } else {
                        let emptyDiv = document.createElement('div');
                        emptyDiv.style.textAlign = 'center';
                        emptyDiv.style.padding = '40px';
                        emptyDiv.style.color = '#999';
                        emptyDiv.textContent = '💫 Нет сообщений. Напишите что-нибудь!';
                        messagesContainer.appendChild(emptyDiv);
                    }
                    
                    lastMessageCount = messages.length;
                    
                    if (shouldScroll) {
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    }
                }
            } catch(e) {
                console.log('Load messages error:', e);
            } finally {
                isLoading = false;
            }
        }
        
        async function sendMessage(type = 'text', content = null) {
            let text = content || messageInput.value.trim();
            if (!text) return;
            
            let formData = new FormData();
            formData.append('send_message', true);
            formData.append('message', text);
            formData.append('type', type);
            
            try {
                let response = await fetch('', { method: 'POST', body: formData });
                let result = await response.json();
                
                if (result.success && result.messages) {
                    messagesContainer.innerHTML = '';
                    
                    result.messages.forEach(function(msg) {
                        let div = document.createElement('div');
                        div.className = 'message ' + (msg.from === currentName ? 'sent' : 'received');
                        
                        let sender = document.createElement('div');
                        sender.className = 'message-sender';
                        sender.textContent = msg.from;
                        
                        let content = document.createElement('div');
                        content.className = 'message-content';
                        
                        if (msg.type === 'image' || msg.type === 'gif') {
                            let img = document.createElement('img');
                            img.src = msg.message;
                            img.className = msg.type === 'image' ? 'message-image' : 'message-gif';
                            img.onclick = function() { window.open(msg.message, '_blank'); };
                            img.onerror = function() {
                                this.onerror = null;
                                this.src = 'https://via.placeholder.com/300x200?text=Ошибка+загрузки';
                            };
                            content.appendChild(img);
                        } else {
                            content.textContent = msg.message;
                        }
                        
                        let time = document.createElement('div');
                        time.className = 'message-time';
                        time.textContent = msg.time;
                        
                        let date = document.createElement('div');
                        date.className = 'message-date';
                        date.textContent = msg.date;
                        
                        div.appendChild(sender);
                        div.appendChild(content);
                        div.appendChild(time);
                        div.appendChild(date);
                        messagesContainer.appendChild(div);
                    });
                    
                    lastMessageCount = result.messages.length;
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    if (!content) messageInput.value = '';
                }
            } catch(e) {
                console.log('Send message error:', e);
            }
        }
        
        if (sendMessageBtn) {
            sendMessageBtn.onclick = function() {
                sendMessage('text');
            };
        }
        
        if (messageInput) {
            messageInput.onkeypress = function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    sendMessage('text');
                }
            };
        }
        
        if (emojiBtn) {
            emojiBtn.onclick = function() {
                emojiPanel.classList.toggle('show');
                gifSearch.classList.remove('show');
            };
        }
        
        if (gifBtn) {
            gifBtn.onclick = function() {
                gifSearch.classList.toggle('show');
                emojiPanel.classList.remove('show');
                if (gifSearch.classList.contains('show')) {
                    loadGifs(gifSearchInput.value || 'funny');
                }
            };
        }
        
        document.querySelectorAll('.emoji-item').forEach(function(emoji) {
            emoji.onclick = function() {
                messageInput.value += emoji.textContent;
                messageInput.focus();
                // Панель не закрывается
            };
        });
        
        if (gifSearchInput) {
            gifSearchInput.oninput = function() {
                if (gifLoadTimeout) {
                    clearTimeout(gifLoadTimeout);
                }
                gifLoadTimeout = setTimeout(() => {
                    let search = this.value.trim() || 'funny';
                    loadGifs(search);
                }, 500);
            };
        }
        
        function selectGif(url) {
            sendMessage('gif', url);
            gifSearch.classList.remove('show');
        }
        
        window.selectGif = selectGif;
        
        if (fileInput) {
            fileInput.onchange = function(e) {
                let file = e.target.files[0];
                if (file) {
                    let reader = new FileReader();
                    reader.onload = function(event) {
                        sendMessage('image', event.target.result);
                    };
                    reader.readAsDataURL(file);
                }
            };
        }
        
        document.addEventListener('click', function(e) {
            if (emojiBtn && !emojiBtn.contains(e.target) && emojiPanel && !emojiPanel.contains(e.target) && 
                gifBtn && !gifBtn.contains(e.target) && gifSearch && !gifSearch.contains(e.target)) {
                emojiPanel.classList.remove('show');
                gifSearch.classList.remove('show');
            }
        });
        
        if (settingsBtn) {
            settingsBtn.onclick = function() {
                settingsPanel.classList.add('show');
            };
        }
        
        if (settingsClose) {
            settingsClose.onclick = function() {
                settingsPanel.classList.remove('show');
            };
        }
        
        if (settingsThemeLight) {
            settingsThemeLight.onclick = function() {
                updateTheme('light');
            };
        }
        
        if (settingsThemeDark) {
            settingsThemeDark.onclick = function() {
                updateTheme('dark');
            };
        }
        
        document.addEventListener('click', function(e) {
            if (settingsPanel && e.target === settingsPanel) {
                settingsPanel.classList.remove('show');
            }
        });
    </script>
</body>
</html>
