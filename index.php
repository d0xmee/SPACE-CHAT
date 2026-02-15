<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

$users_file = 'users.txt';
$messages_file = 'messages.txt';
$bans_file = 'bans.txt';

$MAIN_ADMIN = 'd0xmee_admin';
$SECOND_ADMIN = 'xvii';

function isAdmin($login) {
    global $MAIN_ADMIN, $SECOND_ADMIN;
    return ($login === $MAIN_ADMIN || $login === $SECOND_ADMIN);
}

function isBanned($login) {
    global $bans_file;
    
    if (file_exists($bans_file)) {
        $content = file_get_contents($bans_file);
        $lines = explode("\n", trim($content));
        
        foreach ($lines as $line) {
            if (!empty($line)) {
                $parts = explode('|', $line);
                if (count($parts) >= 3 && trim($parts[0]) === $login) {
                    $ban_time = intval($parts[2]);
                    if (time() < $ban_time) {
                        return true;
                    }
                }
            }
        }
    }
    return false;
}

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
        
        $user_line = $name . '|' . $login . '|' . $password . "\n";
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
    
    if (isBanned($login)) {
        echo json_encode(['success' => false, 'message' => 'Вы забанены']);
        exit;
    }
    
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
                        'password' => trim($parts[2])
                    ];
                }
            }
        }
    }
    
    $logged = false;
    $user_name = '';
    
    foreach ($users as $user) {
        if ($user['login'] === $login && $user['password'] === $password) {
            $logged = true;
            $user_name = $user['name'];
            break;
        }
    }
    
    if ($logged) {
        $_SESSION['user'] = $login;
        $_SESSION['name'] = $user_name;
        
        setcookie('remember_user', $login, time() + (86400 * 30), '/');
        
        echo json_encode(['success' => true, 'name' => $user_name]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Неверный логин или пароль']);
    }
    exit;
}

if (isset($_GET['check_session'])) {
    if (isset($_SESSION['user'])) {
        echo json_encode([
            'logged' => true, 
            'user' => $_SESSION['user'], 
            'name' => $_SESSION['name']
        ]);
    } elseif (isset($_COOKIE['remember_user'])) {
        $_SESSION['user'] = $_COOKIE['remember_user'];
        
        if (file_exists($users_file)) {
            $content = file_get_contents($users_file);
            $lines = explode("\n", trim($content));
            foreach ($lines as $line) {
                if (!empty($line)) {
                    $parts = explode('|', $line);
                    if (trim($parts[1]) === $_COOKIE['remember_user']) {
                        $_SESSION['name'] = trim($parts[0]);
                        break;
                    }
                }
            }
        }
        
        echo json_encode([
            'logged' => true, 
            'user' => $_COOKIE['remember_user'],
            'name' => isset($_SESSION['name']) ? $_SESSION['name'] : $_COOKIE['remember_user']
        ]);
    } else {
        echo json_encode(['logged' => false]);
    }
    exit;
}

if (isset($_POST['logout'])) {
    session_destroy();
    setcookie('remember_user', '', time() - 3600, '/');
    exit;
}

if (isset($_POST['send_message'])) {
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Не авторизован']);
        exit;
    }
    
    if (isBanned($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Вы забанены']);
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
    $role = isAdmin($_SESSION['user']) ? 'admin' : 'user';
    
    $new_message = $from . '|' . $message . '|' . $time . '|' . $date . '|' . $type . '|' . $role . "\n";
    file_put_contents($messages_file, $new_message, FILE_APPEND | LOCK_EX);
    
    $messages = [];
    
    if (file_exists($messages_file)) {
        $content = file_get_contents($messages_file);
        $lines = explode("\n", trim($content));
        $lines = array_filter($lines);
        
        foreach ($lines as $line) {
            if (!empty($line)) {
                $parts = explode('|', $line);
                if (count($parts) >= 6) {
                    $messages[] = [
                        'from' => trim($parts[0]),
                        'message' => trim($parts[1]),
                        'time' => trim($parts[2]),
                        'date' => trim($parts[3]),
                        'type' => trim($parts[4]),
                        'role' => trim($parts[5])
                    ];
                }
            }
        }
    }
    
    echo json_encode(['success' => true, 'messages' => $messages]);
    exit;
}

if (isset($_GET['get_messages'])) {
    if (!isset($_SESSION['user'])) {
        echo json_encode([]);
        exit;
    }
    
    $messages = [];
    
    if (file_exists($messages_file)) {
        $content = file_get_contents($messages_file);
        $lines = explode("\n", trim($content));
        $lines = array_filter($lines);
        
        foreach ($lines as $line) {
            if (!empty($line)) {
                $parts = explode('|', $line);
                if (count($parts) >= 6) {
                    $messages[] = [
                        'from' => trim($parts[0]),
                        'message' => trim($parts[1]),
                        'time' => trim($parts[2]),
                        'date' => trim($parts[3]),
                        'type' => trim($parts[4]),
                        'role' => trim($parts[5])
                    ];
                }
            }
        }
    }
    
    echo json_encode($messages);
    exit;
}

if (isset($_POST['delete_message'])) {
    if (!isset($_SESSION['user'])) exit;
    
    $is_admin = isAdmin($_SESSION['user']);
    
    if (!$is_admin) {
        echo json_encode(['success' => false, 'message' => 'Недостаточно прав']);
        exit;
    }
    
    $message_index = intval($_POST['index']);
    
    if (file_exists($messages_file)) {
        $content = file_get_contents($messages_file);
        $lines = explode("\n", trim($content));
        
        if (isset($lines[$message_index])) {
            unset($lines[$message_index]);
            file_put_contents($messages_file, implode("\n", array_values($lines)) . "\n");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Сообщение не найдено']);
        }
    }
    exit;
}

if (isset($_POST['ban_user'])) {
    if (!isset($_SESSION['user']) || !isAdmin($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Недостаточно прав']);
        exit;
    }
    
    $ban_login = $_POST['login'];
    $minutes = intval($_POST['minutes']);
    $ban_until = time() + ($minutes * 60);
    
    $ban_line = $ban_login . '|' . $minutes . '|' . $ban_until . "\n";
    file_put_contents($bans_file, $ban_line, FILE_APPEND | LOCK_EX);
    
    echo json_encode(['success' => true]);
    exit;
}

if (isset($_GET['get_users'])) {
    if (!isset($_SESSION['user']) || !isAdmin($_SESSION['user'])) {
        echo json_encode([]);
        exit;
    }
    
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
                        'login' => trim($parts[1])
                    ];
                }
            }
        }
    }
    
    echo json_encode($users);
    exit;
}

if (isset($_GET['get_gifs'])) {
    $search = isset($_GET['search']) ? $_GET['search'] : 'funny';
    
    $gifs = [
        'funny' => [
            'https://media.giphy.com/media/3o7abB06u9bNzA8LC8/giphy.gif',
            'https://media.giphy.com/media/l0MYt5jH6gkTWm8qo/giphy.gif',
            'https://media.giphy.com/media/xT0xeJpnrWC4XWblEk/giphy.gif',
            'https://media.giphy.com/media/3ohzdIvnJNp2WYhMPm/giphy.gif',
            'https://media.giphy.com/media/l41YtZObRrI0W405m/giphy.gif',
            'https://media.giphy.com/media/3oriO0OEd9QIDdllqo/giphy.gif',
            'https://media.giphy.com/media/26ufdipQqU2lhNA4g/giphy.gif',
            'https://media.giphy.com/media/l0HlNaQ6gWfllcjDO/giphy.gif'
        ],
        'cat' => [
            'https://media.giphy.com/media/JIX9t2j0ZTN9S/giphy.gif',
            'https://media.giphy.com/media/mlvseq9yvZhba/giphy.gif',
            'https://media.giphy.com/media/3oriO0OEd9QIDdllqo/giphy.gif',
            'https://media.giphy.com/media/13borq7Zo4B1HO/giphy.gif',
            'https://media.giphy.com/media/nNxT5qXRvF34M/giphy.gif'
        ],
        'dog' => [
            'https://media.giphy.com/media/8vQSQ3cNXuDGo/giphy.gif',
            'https://media.giphy.com/media/3o7abB06u9bNzA8LC8/giphy.gif',
            'https://media.giphy.com/media/l3V0dy1zzyjbYTQQM/giphy.gif',
            'https://media.giphy.com/media/5Vy3WpDbXXMze/giphy.gif'
        ],
        'dance' => [
            'https://media.giphy.com/media/l0MYt5jH6gkTWm8qo/giphy.gif',
            'https://media.giphy.com/media/xT0xeJpnrWC4XWblEk/giphy.gif',
            'https://media.giphy.com/media/l41YtZObRrI0W405m/giphy.gif',
            'https://media.giphy.com/media/3o7abB06u9bNzA8LC8/giphy.gif'
        ],
        'anime' => [
            'https://media.giphy.com/media/26ufdipQqU2lhNA4g/giphy.gif',
            'https://media.giphy.com/media/l0HlNaQ6gWfllcjDO/giphy.gif',
            'https://media.giphy.com/media/l0MYt5jH6gkTWm8qo/giphy.gif',
            'https://media.giphy.com/media/xT0xeJpnrWC4XWblEk/giphy.gif'
        ]
    ];
    
    $search_lower = strtolower($search);
    $results = [];
    
    foreach ($gifs as $key => $gif_list) {
        if (strpos($key, $search_lower) !== false || $search_lower === 'funny' || $search_lower === '') {
            $results = array_merge($results, $gif_list);
        }
    }
    
    if (empty($results)) {
        $results = $gifs['funny'];
    }
    
    $results = array_unique($results);
    shuffle($results);
    echo json_encode(array_slice($results, 0, 15));
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Чат</title>
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
            overflow-x: hidden;
            transition: background 8s ease-in-out;
            background-size: 300% 300% !important;
            animation: floatGradient 15s ease infinite;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
            z-index: 0;
        }
        
        body.stars-yes::before {
            background: radial-gradient(2px 2px at 20px 30px, #fff, rgba(0,0,0,0)),
                        radial-gradient(2px 2px at 40px 70px, #fff, rgba(0,0,0,0)),
                        radial-gradient(2px 2px at 80px 120px, #fff, rgba(0,0,0,0)),
                        radial-gradient(2px 2px at 160px 50px, #fff, rgba(0,0,0,0)),
                        radial-gradient(2px 2px at 250px 180px, #ffd700, rgba(0,0,0,0)),
                        radial-gradient(3px 3px at 350px 90px, #fff, rgba(0,0,0,0)),
                        radial-gradient(2px 2px at 450px 220px, #fff, rgba(0,0,0,0)),
                        radial-gradient(2px 2px at 550px 140px, #ffd700, rgba(0,0,0,0)),
                        radial-gradient(3px 3px at 650px 70px, #fff, rgba(0,0,0,0)),
                        radial-gradient(2px 2px at 750px 200px, #fff, rgba(0,0,0,0)),
                        radial-gradient(2px 2px at 850px 120px, #ffd700, rgba(0,0,0,0)),
                        radial-gradient(3px 3px at 950px 50px, #fff, rgba(0,0,0,0)),
                        radial-gradient(2px 2px at 1050px 180px, #fff, rgba(0,0,0,0)),
                        radial-gradient(2px 2px at 1150px 90px, #ffd700, rgba(0,0,0,0)),
                        radial-gradient(3px 3px at 1250px 220px, #fff, rgba(0,0,0,0)),
                        radial-gradient(2px 2px at 1350px 140px, #fff, rgba(0,0,0,0)),
                        radial-gradient(2px 2px at 1450px 70px, #ffd700, rgba(0,0,0,0)),
                        radial-gradient(3px 3px at 1550px 200px, #fff, rgba(0,0,0,0)),
                        radial-gradient(2px 2px at 1650px 120px, #fff, rgba(0,0,0,0)),
                        radial-gradient(2px 2px at 1750px 50px, #ffd700, rgba(0,0,0,0));
            background-size: 200px 200px;
            background-repeat: repeat;
            animation: starsFloat 30s linear infinite;
            opacity: 0.6;
        }
        
        @keyframes starsFloat {
            0% { background-position: 0 0; }
            100% { background-position: 200px 200px; }
        }
        
        @keyframes floatGradient {
            0% { background-position: 0% 0%; }
            25% { background-position: 100% 0%; }
            50% { background-position: 100% 100%; }
            75% { background-position: 0% 100%; }
            100% { background-position: 0% 0%; }
        }
        
        body.purple {
            background: linear-gradient(125deg, #2d1b3a, #1a0b2e, #4a2b5a, #2d1b3a);
            background-size: 300% 300%;
        }
        
        body.pink {
            background: linear-gradient(125deg, #ff9a9e, #fad0c4, #ffb6c1, #ff9a9e);
            background-size: 300% 300%;
        }
        
        body.gray {
            background: linear-gradient(125deg, #2c3e50, #34495e, #4a5a6a, #2c3e50);
            background-size: 300% 300%;
        }
        
        body.green {
            background: linear-gradient(125deg, #134e5e, #71b280, #2e8b57, #134e5e);
            background-size: 300% 300%;
        }
        
        body.blue {
            background: linear-gradient(125deg, #2b5876, #4e4376, #3498db, #2b5876);
            background-size: 300% 300%;
        }
        
        body.orange {
            background: linear-gradient(125deg, #ff6b6b, #feca57, #ff8c42, #ff6b6b);
            background-size: 300% 300%;
        }
        
        body.red {
            background: linear-gradient(125deg, #cb2d3e, #ef473a, #dc143c, #cb2d3e);
            background-size: 300% 300%;
        }
        
        body.teal {
            background: linear-gradient(125deg, #11998e, #38ef7d, #20b2aa, #11998e);
            background-size: 300% 300%;
        }
        
        .auth-screen, .chat-app, .settings-panel, .admin-panel {
            position: relative;
            z-index: 10;
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
            transition: all 0.5s ease;
        }
        
        body.purple .auth-screen {
            background: rgba(26, 11, 46, 0.9);
            border: 1px solid rgba(200,150,255,0.3);
            color: white;
        }
        
        body.pink .auth-screen {
            background: rgba(255, 220, 220, 0.9);
            border: 1px solid rgba(255,150,150,0.3);
        }
        
        body.gray .auth-screen {
            background: rgba(44, 62, 80, 0.9);
            border: 1px solid rgba(255,255,255,0.1);
            color: white;
        }
        
        body.green .auth-screen {
            background: rgba(19, 78, 94, 0.9);
            border: 1px solid rgba(120,255,120,0.2);
            color: white;
        }
        
        body.blue .auth-screen {
            background: rgba(43, 88, 118, 0.9);
            border: 1px solid rgba(100,200,255,0.3);
            color: white;
        }
        
        body.orange .auth-screen {
            background: rgba(255, 107, 107, 0.9);
            border: 1px solid rgba(255,200,100,0.3);
            color: white;
        }
        
        body.red .auth-screen {
            background: rgba(203, 45, 62, 0.9);
            border: 1px solid rgba(255,100,100,0.3);
            color: white;
        }
        
        body.teal .auth-screen {
            background: rgba(17, 153, 142, 0.9);
            border: 1px solid rgba(100,255,150,0.3);
            color: white;
        }
        
        .auth-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            background: rgba(0,0,0,0.1);
            padding: 5px;
            border-radius: 50px;
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
            transition: all 0.3s ease;
            color: inherit;
        }
        
        body.purple .auth-tab,
        body.gray .auth-tab,
        body.green .auth-tab,
        body.blue .auth-tab,
        body.orange .auth-tab,
        body.red .auth-tab,
        body.teal .auth-tab {
            color: rgba(255,255,255,0.7);
        }
        
        .auth-tab.active {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            transform: scale(1.05);
        }
        
        .auth-form {
            display: none;
        }
        
        .auth-form.active {
            display: block;
            animation: slideIn 0.6s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .auth-form h2 {
            text-align: center;
            margin-bottom: 30px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 28px;
            background-size: 200% auto;
        }
        
        .input-group {
            margin-bottom: 20px;
            animation: fadeInUp 0.5s ease backwards;
        }
        
        .input-group:nth-child(1) { animation-delay: 0.1s; }
        .input-group:nth-child(2) { animation-delay: 0.2s; }
        .input-group:nth-child(3) { animation-delay: 0.3s; }
        .input-group:nth-child(4) { animation-delay: 0.4s; }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        body.purple .input-group label,
        body.gray .input-group label,
        body.green .input-group label,
        body.blue .input-group label,
        body.orange .input-group label,
        body.red .input-group label,
        body.teal .input-group label {
            color: rgba(255,255,255,0.8);
        }
        
        .auth-form input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid transparent;
            border-radius: 15px;
            font-size: 16px;
            transition: all 0.4s ease;
            -webkit-appearance: none;
        }
        
        body.purple .auth-form input {
            background: rgba(75, 40, 90, 0.8);
            border-color: #9b59b6;
            color: white;
        }
        
        body.pink .auth-form input {
            background: rgba(255, 200, 200, 0.8);
            border-color: #ff9f9f;
            color: #333;
        }
        
        body.gray .auth-form input {
            background: rgba(70, 80, 90, 0.8);
            border-color: #7f8c8d;
            color: white;
        }
        
        body.green .auth-form input {
            background: rgba(30, 100, 70, 0.8);
            border-color: #27ae60;
            color: white;
        }
        
        body.blue .auth-form input {
            background: rgba(40, 80, 120, 0.8);
            border-color: #3498db;
            color: white;
        }
        
        body.orange .auth-form input {
            background: rgba(255, 140, 66, 0.8);
            border-color: #ff8c42;
            color: white;
        }
        
        body.red .auth-form input {
            background: rgba(200, 50, 50, 0.8);
            border-color: #dc143c;
            color: white;
        }
        
        body.teal .auth-form input {
            background: rgba(30, 150, 140, 0.8);
            border-color: #20b2aa;
            color: white;
        }
        
        .auth-form input::placeholder {
            color: rgba(255,255,255,0.5);
        }
        
        .auth-form input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.2);
            transform: translateY(-3px) scale(1.02);
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
            transition: all 0.5s ease;
            margin-top: 15px;
            -webkit-appearance: none;
            position: relative;
            overflow: hidden;
        }
        
        .auth-form button:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.5);
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
            70% { transform: scale(1.2); }
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
            transition: transform 1s ease, background 2s ease;
        }
        
        .chat-app.theme-change {
            animation: themeSpin 1s ease;
        }
        
        @keyframes themeSpin {
            0% {
                transform: rotate(0deg) scale(1);
            }
            50% {
                transform: rotate(180deg) scale(1.05);
            }
            100% {
                transform: rotate(360deg) scale(1);
            }
        }
        
        .chat-app.show {
            display: flex;
        }
        
        body.purple .chat-app {
            background: rgba(26, 11, 46, 0.9);
            border: 1px solid rgba(200,150,255,0.3);
        }
        
        body.pink .chat-app {
            background: rgba(255, 220, 220, 0.9);
            border: 1px solid rgba(255,150,150,0.3);
        }
        
        body.gray .chat-app {
            background: rgba(44, 62, 80, 0.9);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        body.green .chat-app {
            background: rgba(19, 78, 94, 0.9);
            border: 1px solid rgba(120,255,120,0.2);
        }
        
        body.blue .chat-app {
            background: rgba(43, 88, 118, 0.9);
            border: 1px solid rgba(100,200,255,0.3);
        }
        
        body.orange .chat-app {
            background: rgba(255, 107, 107, 0.9);
            border: 1px solid rgba(255,200,100,0.3);
        }
        
        body.red .chat-app {
            background: rgba(203, 45, 62, 0.9);
            border: 1px solid rgba(255,100,100,0.3);
        }
        
        body.teal .chat-app {
            background: rgba(17, 153, 142, 0.9);
            border: 1px solid rgba(100,255,150,0.3);
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
            position: relative;
            overflow: hidden;
            transition: background 2s ease;
        }
        
        body.purple .chat-header {
            background: linear-gradient(45deg, #9b59b6, #8e44ad);
        }
        
        body.pink .chat-header {
            background: linear-gradient(45deg, #ff7676, #ff9f9f);
        }
        
        body.gray .chat-header {
            background: linear-gradient(45deg, #34495e, #2c3e50);
        }
        
        body.green .chat-header {
            background: linear-gradient(45deg, #27ae60, #229954);
        }
        
        body.blue .chat-header {
            background: linear-gradient(45deg, #3498db, #2980b9);
        }
        
        body.orange .chat-header {
            background: linear-gradient(45deg, #ff8c42, #ff6b6b);
        }
        
        body.red .chat-header {
            background: linear-gradient(45deg, #dc143c, #cb2d3e);
        }
        
        body.teal .chat-header {
            background: linear-gradient(45deg, #20b2aa, #11998e);
        }
        
        .chat-header h2 {
            font-size: 24px;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .current-user {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 16px;
        }
        
        .creator-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            margin-left: 8px;
            background: linear-gradient(45deg, #ffd700, #ffa500);
            color: #000;
            font-weight: bold;
        }
        
        .admin-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            margin-left: 8px;
            background: linear-gradient(45deg, #9b59b6, #8e44ad);
            color: white;
            font-weight: bold;
        }
        
        .settings-btn {
            width: 45px;
            height: 45px;
            border: none;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            color: white;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-appearance: none;
        }
        
        .settings-btn:hover {
            transform: rotate(180deg) scale(1.1);
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
            transition: all 0.5s ease;
            -webkit-appearance: none;
        }
        
        .logout-btn:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 10px 20px rgba(255,68,68,0.4);
        }
        
        .admin-panel-btn {
            padding: 10px 22px;
            background: linear-gradient(45deg, #ffd700, #ffa500);
            color: #000;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.5s ease;
            -webkit-appearance: none;
            display: none;
        }
        
        .admin-panel-btn:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 10px 20px rgba(255,215,0,0.4);
        }
        
        .admin-panel-btn.show {
            display: block;
        }
        
        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 25px;
            background: rgba(255,255,255,0.5);
            display: flex;
            flex-direction: column;
            gap: 15px;
            scroll-behavior: smooth;
            transition: background 2s ease;
        }
        
        body.purple .messages-container {
            background: rgba(75, 40, 90, 0.4);
        }
        
        body.pink .messages-container {
            background: rgba(255, 200, 200, 0.4);
        }
        
        body.gray .messages-container {
            background: rgba(70, 80, 90, 0.4);
        }
        
        body.green .messages-container {
            background: rgba(30, 100, 70, 0.4);
        }
        
        body.blue .messages-container {
            background: rgba(40, 80, 120, 0.4);
        }
        
        body.orange .messages-container {
            background: rgba(255, 140, 66, 0.3);
        }
        
        body.red .messages-container {
            background: rgba(200, 50, 50, 0.3);
        }
        
        body.teal .messages-container {
            background: rgba(30, 150, 140, 0.3);
        }
        
        .message {
            max-width: 70%;
            padding: 15px 20px;
            border-radius: 20px;
            animation: messagePop 0.5s ease;
            font-size: 16px;
            word-wrap: break-word;
            position: relative;
            transition: all 2s ease;
        }
        
        @keyframes messagePop {
            0% {
                opacity: 0;
                transform: scale(0.3) translateY(30px);
            }
            70% {
                transform: scale(1.05) translateY(-5px);
            }
            100% {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
        
        .message:hover {
            transform: scale(1.02) translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .message.sent {
            align-self: flex-end;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border-bottom-right-radius: 5px;
        }
        
        body.purple .message.sent {
            background: linear-gradient(45deg, #9b59b6, #8e44ad);
        }
        
        body.pink .message.sent {
            background: linear-gradient(45deg, #ff7676, #ff9f9f);
        }
        
        body.gray .message.sent {
            background: linear-gradient(45deg, #34495e, #2c3e50);
        }
        
        body.green .message.sent {
            background: linear-gradient(45deg, #27ae60, #229954);
        }
        
        body.blue .message.sent {
            background: linear-gradient(45deg, #3498db, #2980b9);
        }
        
        body.orange .message.sent {
            background: linear-gradient(45deg, #ff8c42, #ff6b6b);
        }
        
        body.red .message.sent {
            background: linear-gradient(45deg, #dc143c, #cb2d3e);
        }
        
        body.teal .message.sent {
            background: linear-gradient(45deg, #20b2aa, #11998e);
        }
        
        .message.received {
            align-self: flex-start;
            color: #333;
            border-bottom-left-radius: 5px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        body.purple .message.received {
            background: rgba(75, 40, 90, 0.8);
            color: #eee;
        }
        
        body.pink .message.received {
            background: rgba(255, 200, 200, 0.8);
            color: #333;
        }
        
        body.gray .message.received {
            background: rgba(70, 80, 90, 0.8);
            color: #eee;
        }
        
        body.green .message.received {
            background: rgba(30, 100, 70, 0.8);
            color: #eee;
        }
        
        body.blue .message.received {
            background: rgba(40, 80, 120, 0.8);
            color: #eee;
        }
        
        body.orange .message.received {
            background: rgba(255, 140, 66, 0.8);
            color: #333;
        }
        
        body.red .message.received {
            background: rgba(200, 50, 50, 0.8);
            color: #333;
        }
        
        body.teal .message.received {
            background: rgba(30, 150, 140, 0.8);
            color: #333;
        }
        
        .message-sender {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 5px;
            opacity: 0.9;
            flex-wrap: wrap;
        }
        
        .creator-badge-small {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            background: linear-gradient(45deg, #ffd700, #ffa500);
            color: #000;
            font-weight: bold;
        }
        
        .admin-badge-small {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            background: linear-gradient(45deg, #9b59b6, #8e44ad);
            color: white;
            font-weight: bold;
        }
        
        .delete-btn {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #ff4444;
            color: white;
            border: none;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .message:hover .delete-btn {
            display: flex;
        }
        
        .delete-btn:hover {
            transform: scale(1.2);
            background: #ff6666;
        }
        
        .message-content {
            margin-bottom: 5px;
            line-height: 1.4;
            font-size: 16px;
        }
        
        .message-content img {
            max-width: 100%;
            max-height: 300px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.5s;
        }
        
        .message-content img:hover {
            transform: scale(1.05) rotate(2deg);
            box-shadow: 0 10px 20px rgba(0,0,0,0.3);
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
            border-top: 2px solid rgba(102, 126, 234, 0.2);
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            transition: background 2s ease;
        }
        
        body.purple .chat-input-area {
            background: rgba(26, 11, 46, 0.8);
            border-top-color: rgba(155, 89, 182, 0.3);
        }
        
        body.pink .chat-input-area {
            background: rgba(255, 220, 220, 0.8);
            border-top-color: rgba(255, 150, 150, 0.3);
        }
        
        body.gray .chat-input-area {
            background: rgba(44, 62, 80, 0.8);
            border-top-color: rgba(255,255,255,0.1);
        }
        
        body.green .chat-input-area {
            background: rgba(19, 78, 94, 0.8);
            border-top-color: rgba(39, 174, 96, 0.3);
        }
        
        body.blue .chat-input-area {
            background: rgba(43, 88, 118, 0.8);
            border-top-color: rgba(52, 152, 219, 0.3);
        }
        
        body.orange .chat-input-area {
            background: rgba(255, 107, 107, 0.8);
            border-top-color: rgba(255, 140, 66, 0.3);
        }
        
        body.red .chat-input-area {
            background: rgba(203, 45, 62, 0.8);
            border-top-color: rgba(220, 20, 60, 0.3);
        }
        
        body.teal .chat-input-area {
            background: rgba(17, 153, 142, 0.8);
            border-top-color: rgba(32, 178, 170, 0.3);
        }
        
        .chat-input-area input {
            flex: 1;
            min-width: 200px;
            padding: 16px 22px;
            border: 2px solid transparent;
            border-radius: 30px;
            font-size: 16px;
            transition: all 0.4s ease;
            -webkit-appearance: none;
        }
        
        body.purple .chat-input-area input {
            background: rgba(75, 40, 90, 0.8);
            border-color: #9b59b6;
            color: white;
        }
        
        body.pink .chat-input-area input {
            background: rgba(255, 200, 200, 0.8);
            border-color: #ff9f9f;
            color: #333;
        }
        
        body.gray .chat-input-area input {
            background: rgba(70, 80, 90, 0.8);
            border-color: #7f8c8d;
            color: white;
        }
        
        body.green .chat-input-area input {
            background: rgba(30, 100, 70, 0.8);
            border-color: #27ae60;
            color: white;
        }
        
        body.blue .chat-input-area input {
            background: rgba(40, 80, 120, 0.8);
            border-color: #3498db;
            color: white;
        }
        
        body.orange .chat-input-area input {
            background: rgba(255, 140, 66, 0.8);
            border-color: #ff8c42;
            color: white;
        }
        
        body.red .chat-input-area input {
            background: rgba(200, 50, 50, 0.8);
            border-color: #dc143c;
            color: white;
        }
        
        body.teal .chat-input-area input {
            background: rgba(30, 150, 140, 0.8);
            border-color: #20b2aa;
            color: white;
        }
        
        .chat-input-area input::placeholder {
            color: rgba(255,255,255,0.5);
        }
        
        .chat-input-area input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.2);
            transform: translateY(-3px) scale(1.02);
        }
        
        .action-btn {
            width: 55px;
            height: 55px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-size: 26px;
            transition: all 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-appearance: none;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        body.purple .action-btn {
            background: linear-gradient(45deg, #9b59b6, #8e44ad);
        }
        
        body.pink .action-btn {
            background: linear-gradient(45deg, #ff7676, #ff9f9f);
        }
        
        body.gray .action-btn {
            background: linear-gradient(45deg, #34495e, #2c3e50);
        }
        
        body.green .action-btn {
            background: linear-gradient(45deg, #27ae60, #229954);
        }
        
        body.blue .action-btn {
            background: linear-gradient(45deg, #3498db, #2980b9);
        }
        
        body.orange .action-btn {
            background: linear-gradient(45deg, #ff8c42, #ff6b6b);
        }
        
        body.red .action-btn {
            background: linear-gradient(45deg, #dc143c, #cb2d3e);
        }
        
        body.teal .action-btn {
            background: linear-gradient(45deg, #20b2aa, #11998e);
        }
        
        .action-btn:hover {
            transform: rotate(360deg) scale(1.1);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.5);
        }
        
        .action-btn:active {
            transform: rotate(360deg) scale(0.95);
        }
        
        .emoji-panel {
            position: absolute;
            bottom: 100px;
            left: 25px;
            right: 25px;
            border-radius: 25px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            display: none;
            grid-template-columns: repeat(12, 1fr);
            gap: 8px;
            z-index: 100;
            max-height: 350px;
            overflow-y: auto;
            animation: slideUp 0.5s ease;
            transition: background 2s ease;
        }
        
        body.purple .emoji-panel {
            background: rgba(26, 11, 46, 0.95);
            border: 1px solid #9b59b6;
        }
        
        body.pink .emoji-panel {
            background: rgba(255, 220, 220, 0.95);
            border: 1px solid #ff9f9f;
        }
        
        body.gray .emoji-panel {
            background: rgba(44, 62, 80, 0.95);
            border: 1px solid #7f8c8d;
        }
        
        body.green .emoji-panel {
            background: rgba(19, 78, 94, 0.95);
            border: 1px solid #27ae60;
        }
        
        body.blue .emoji-panel {
            background: rgba(43, 88, 118, 0.95);
            border: 1px solid #3498db;
        }
        
        body.orange .emoji-panel {
            background: rgba(255, 107, 107, 0.95);
            border: 1px solid #ff8c42;
        }
        
        body.red .emoji-panel {
            background: rgba(203, 45, 62, 0.95);
            border: 1px solid #dc143c;
        }
        
        body.teal .emoji-panel {
            background: rgba(17, 153, 142, 0.95);
            border: 1px solid #20b2aa;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
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
            font-size: 28px;
            text-align: center;
            cursor: pointer;
            padding: 8px;
            border-radius: 12px;
            transition: all 0.3s ease;
            color: inherit;
        }
        
        .emoji-item:hover {
            transform: scale(1.3) rotate(5deg);
            background: rgba(255,255,255,0.2);
        }
        
        .gif-search {
            position: absolute;
            bottom: 100px;
            left: 25px;
            right: 25px;
            border-radius: 25px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            display: none;
            z-index: 100;
            max-height: 400px;
            overflow-y: auto;
            animation: slideUp 0.5s ease;
            transition: background 2s ease;
        }
        
        .gif-search.show {
            display: block;
        }
        
        body.purple .gif-search {
            background: rgba(26, 11, 46, 0.95);
            border: 1px solid #9b59b6;
        }
        
        body.pink .gif-search {
            background: rgba(255, 220, 220, 0.95);
            border: 1px solid #ff9f9f;
        }
        
        body.gray .gif-search {
            background: rgba(44, 62, 80, 0.95);
            border: 1px solid #7f8c8d;
        }
        
        body.green .gif-search {
            background: rgba(19, 78, 94, 0.95);
            border: 1px solid #27ae60;
        }
        
        body.blue .gif-search {
            background: rgba(43, 88, 118, 0.95);
            border: 1px solid #3498db;
        }
        
        body.orange .gif-search {
            background: rgba(255, 107, 107, 0.95);
            border: 1px solid #ff8c42;
        }
        
        body.red .gif-search {
            background: rgba(203, 45, 62, 0.95);
            border: 1px solid #dc143c;
        }
        
        body.teal .gif-search {
            background: rgba(17, 153, 142, 0.95);
            border: 1px solid #20b2aa;
        }
        
        .gif-search input {
            width: 100%;
            padding: 15px;
            border: 2px solid transparent;
            border-radius: 20px;
            margin-bottom: 20px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        body.purple .gif-search input,
        body.gray .gif-search input,
        body.green .gif-search input,
        body.blue .gif-search input,
        body.orange .gif-search input,
        body.red .gif-search input,
        body.teal .gif-search input {
            background: rgba(255,255,255,0.15);
            border-color: rgba(255,255,255,0.2);
            color: white;
        }
        
        .gif-search input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.2);
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
            transition: all 0.5s;
        }
        
        .gif-item:hover {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 10px 20px rgba(0,0,0,0.3);
        }
        
        .settings-panel, .admin-panel {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            border-radius: 30px;
            padding: 35px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            z-index: 1000;
            display: none;
            width: 90%;
            max-width: 450px;
            animation: modalPop 0.5s ease;
            transition: background 2s ease;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .admin-panel {
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        body.purple .settings-panel,
        body.purple .admin-panel {
            background: rgba(26, 11, 46, 0.95);
            border: 1px solid #9b59b6;
            color: white;
        }
        
        body.pink .settings-panel,
        body.pink .admin-panel {
            background: rgba(255, 220, 220, 0.95);
            border: 1px solid #ff9f9f;
            color: #333;
        }
        
        body.gray .settings-panel,
        body.gray .admin-panel {
            background: rgba(44, 62, 80, 0.95);
            border: 1px solid #7f8c8d;
            color: white;
        }
        
        body.green .settings-panel,
        body.green .admin-panel {
            background: rgba(19, 78, 94, 0.95);
            border: 1px solid #27ae60;
            color: white;
        }
        
        body.blue .settings-panel,
        body.blue .admin-panel {
            background: rgba(43, 88, 118, 0.95);
            border: 1px solid #3498db;
            color: white;
        }
        
        body.orange .settings-panel,
        body.orange .admin-panel {
            background: rgba(255, 107, 107, 0.95);
            border: 1px solid #ff8c42;
            color: white;
        }
        
        body.red .settings-panel,
        body.red .admin-panel {
            background: rgba(203, 45, 62, 0.95);
            border: 1px solid #dc143c;
            color: white;
        }
        
        body.teal .settings-panel,
        body.teal .admin-panel {
            background: rgba(17, 153, 142, 0.95);
            border: 1px solid #20b2aa;
            color: white;
        }
        
        @keyframes modalPop {
            0% {
                opacity: 0;
                transform: translate(-50%, -50%) scale(0.3) rotate(-10deg);
            }
            70% {
                transform: translate(-50%, -50%) scale(1.1) rotate(2deg);
            }
            100% {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1) rotate(0);
            }
        }
        
        .settings-panel.show,
        .admin-panel.show {
            display: block;
        }
        
        .settings-panel h3,
        .admin-panel h3 {
            margin-bottom: 25px;
            font-size: 28px;
            text-align: center;
            background: linear-gradient(45deg, #667eea, #764ba2);
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
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        
        .settings-theme-btn {
            padding: 15px;
            border: 2px solid rgba(255,255,255,0.2);
            border-radius: 15px;
            background: rgba(255,255,255,0.1);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.4s;
            font-size: 14px;
            color: inherit;
        }
        
        .settings-theme-btn:hover {
            transform: scale(1.05) translateY(-2px);
            background: rgba(255,255,255,0.2);
            box-shadow: 0 8px 15px rgba(102, 126, 234, 0.3);
        }
        
        .settings-theme-btn.active {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-color: transparent;
            color: white;
        }
        
        .settings-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
        }
        
        .settings-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #667eea;
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
            transition: all 0.5s;
        }
        
        .settings-close:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.4);
        }
        
        .user-list {
            margin: 20px 0;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .user-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s;
        }
        
        .user-item:hover {
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        
        .user-info-admin {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-name {
            font-weight: 600;
        }
        
        .user-login {
            font-size: 12px;
            opacity: 0.7;
        }
        
        .admin-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .ban-input {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        
        .ban-input input {
            width: 60px;
            padding: 8px;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            background: rgba(255,255,255,0.1);
            color: inherit;
        }
        
        .ban-input button {
            padding: 8px 12px;
            border: none;
            border-radius: 8px;
            background: #ff4444;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .ban-input button:hover {
            transform: scale(1.05);
            background: #ff6666;
        }
        
        .loading-gif {
            text-align: center;
            padding: 30px;
            opacity: 0.7;
        }
        
        .no-gifs {
            text-align: center;
            padding: 30px;
            opacity: 0.7;
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
            
            .settings-theme-buttons {
                grid-template-columns: 1fr;
            }
            
            .user-item {
                flex-direction: column;
                gap: 10px;
            }
            
            .admin-actions {
                flex-wrap: wrap;
                justify-content: center;
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
            
            .settings-btn, .action-btn {
                width: 50px;
                height: 50px;
                font-size: 24px;
            }
            
            .logout-btn {
                padding: 8px 18px;
                font-size: 14px;
            }
            
            .admin-panel-btn {
                padding: 8px 15px;
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
                gap: 5px;
            }
            
            .emoji-item {
                font-size: 24px;
                padding: 6px;
            }
        }
    </style>
</head>
<body class="purple stars-yes" id="body">
    <div id="authScreen" class="auth-screen">
        <div class="auth-tabs">
            <button class="auth-tab active" id="loginTab">Вход</button>
            <button class="auth-tab" id="registerTab">Регистрация</button>
        </div>
        
        <div id="loginForm" class="auth-form active">
            <h2>Добро пожаловать</h2>
            <div class="input-group">
                <label>Логин</label>
                <input type="text" id="loginLogin" placeholder="Введите логин">
            </div>
            <div class="input-group">
                <label>Пароль</label>
                <input type="password" id="loginPassword" placeholder="Введите пароль">
            </div>
            <button id="loginBtn">Войти</button>
            <div id="loginError" class="error"></div>
        </div>
        
        <div id="registerForm" class="auth-form">
            <h2>Создать аккаунт</h2>
            <div class="input-group">
                <label>Имя</label>
                <input type="text" id="regName" placeholder="Введите ваше имя">
            </div>
            <div class="input-group">
                <label>Логин</label>
                <input type="text" id="regLogin" placeholder="Придумайте логин">
            </div>
            <div class="input-group">
                <label>Пароль</label>
                <input type="password" id="regPassword" placeholder="Придумайте пароль">
            </div>
            <div class="input-group">
                <label>Подтверждение</label>
                <input type="password" id="regConfirm" placeholder="Повторите пароль">
            </div>
            <button id="registerBtn">Зарегистрироваться</button>
            <div id="regError" class="error"></div>
            <div id="regSuccess" class="success"></div>
        </div>
    </div>
    
    <div id="chatApp" class="chat-app">
        <div class="chat-header">
            <h2>Чат</h2>
            <div class="user-info">
                <span class="current-user" id="currentUser"></span>
                <button class="admin-panel-btn" id="adminPanelBtn">👑 Управление</button>
                <button class="settings-btn" id="settingsBtn">⚙️</button>
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
                <span class="emoji-item">⚡️</span>
                <span class="emoji-item">💥</span>
                <span class="emoji-item">💯</span>
                <span class="emoji-item">✅</span>
                <span class="emoji-item">❌</span>
                <span class="emoji-item">💔</span>
                <span class="emoji-item">💖</span>
                <span class="emoji-item">💗</span>
                <span class="emoji-item">💓</span>
                <span class="emoji-item">💕</span>
                <span class="emoji-item">💞</span>
                <span class="emoji-item">💘</span>
                <span class="emoji-item">💝</span>
                <span class="emoji-item">😁</span>
                <span class="emoji-item">😆</span>
                <span class="emoji-item">😅</span>
                <span class="emoji-item">🤣</span>
                <span class="emoji-item">😋</span>
                <span class="emoji-item">😜</span>
                <span class="emoji-item">🤨</span>
                <span class="emoji-item">🧐</span>
                <span class="emoji-item">😏</span>
                <span class="emoji-item">😒</span>
                <span class="emoji-item">😞</span>
                <span class="emoji-item">😔</span>
                <span class="emoji-item">😟</span>
                <span class="emoji-item">😕</span>
                <span class="emoji-item">🙃</span>
                <span class="emoji-item">😶</span>
                <span class="emoji-item">😐</span>
                <span class="emoji-item">😑</span>
                <span class="emoji-item">😬</span>
                <span class="emoji-item">🙄</span>
                <span class="emoji-item">😮</span>
                <span class="emoji-item">😲</span>
                <span class="emoji-item">😴</span>
                <span class="emoji-item">🤤</span>
                <span class="emoji-item">😪</span>
                <span class="emoji-item">😵</span>
                <span class="emoji-item">🤐</span>
                <span class="emoji-item">🥴</span>
                <span class="emoji-item">🤢</span>
                <span class="emoji-item">🤮</span>
                <span class="emoji-item">🤧</span>
                <span class="emoji-item">🥵</span>
                <span class="emoji-item">🥶</span>
                <span class="emoji-item">🥳</span>
                <span class="emoji-item">🥸</span>
                <span class="emoji-item">🤠</span>
                <span class="emoji-item">👽</span>
                <span class="emoji-item">👾</span>
                <span class="emoji-item">🤖</span>
                <span class="emoji-item">🎃</span>
                <span class="emoji-item">😺</span>
                <span class="emoji-item">😸</span>
                <span class="emoji-item">😹</span>
                <span class="emoji-item">😻</span>
                <span class="emoji-item">😼</span>
                <span class="emoji-item">😽</span>
                <span class="emoji-item">🙀</span>
                <span class="emoji-item">😿</span>
                <span class="emoji-item">😾</span>
                <span class="emoji-item">🙈</span>
                <span class="emoji-item">🙉</span>
                <span class="emoji-item">🙊</span>
                <span class="emoji-item">🐒</span>
                <span class="emoji-item">🐔</span>
                <span class="emoji-item">🐧</span>
                <span class="emoji-item">🐦</span>
                <span class="emoji-item">🐤</span>
                <span class="emoji-item">🐣</span>
                <span class="emoji-item">🐥</span>
                <span class="emoji-item">🐺</span>
                <span class="emoji-item">🐗</span>
                <span class="emoji-item">🐴</span>
                <span class="emoji-item">🦄</span>
                <span class="emoji-item">🐝</span>
                <span class="emoji-item">🐛</span>
                <span class="emoji-item">🦋</span>
                <span class="emoji-item">🐌</span>
                <span class="emoji-item">🐞</span>
                <span class="emoji-item">🐜</span>
                <span class="emoji-item">🦟</span>
                <span class="emoji-item">🦗</span>
                <span class="emoji-item">🕷️</span>
                <span class="emoji-item">🦂</span>
                <span class="emoji-item">🐢</span>
                <span class="emoji-item">🐍</span>
                <span class="emoji-item">🦎</span>
                <span class="emoji-item">🐙</span>
                <span class="emoji-item">🦑</span>
                <span class="emoji-item">🦐</span>
                <span class="emoji-item">🦞</span>
                <span class="emoji-item">🐠</span>
                <span class="emoji-item">🐟</span>
                <span class="emoji-item">🐡</span>
                <span class="emoji-item">🐬</span>
                <span class="emoji-item">🐳</span>
                <span class="emoji-item">🐋</span>
                <span class="emoji-item">🦈</span>
            </div>
            
            <div class="gif-search" id="gifSearch">
                <input type="text" id="gifSearchInput" placeholder="Поиск гифок..." value="funny">
                <div class="gif-results" id="gifResults">
                    <div class="loading-gif">Загрузка гифок...</div>
                </div>
            </div>
            
            <div class="chat-input-area">
                <button class="action-btn" id="emojiBtn">😊</button>
                <button class="action-btn" id="gifBtn">GIF</button>
                <input type="text" id="messageInput" placeholder="Напишите сообщение...">
                <button class="action-btn" id="sendMessageBtn">➤</button>
            </div>
        </div>
    </div>
    
    <div class="settings-panel" id="settingsPanel">
        <h3>Настройки</h3>
        <div class="settings-option">
            <label>Тема</label>
            <div class="settings-theme-buttons">
                <button class="settings-theme-btn" data-theme="purple">💜 Фиолетовая</button>
                <button class="settings-theme-btn" data-theme="pink">🌸 Розовая</button>
                <button class="settings-theme-btn" data-theme="gray">🩶 Серая</button>
                <button class="settings-theme-btn" data-theme="green">🌿 Зеленая</button>
                <button class="settings-theme-btn" data-theme="blue">💙 Синяя</button>
                <button class="settings-theme-btn" data-theme="orange">🧡 Оранжевая</button>
                <button class="settings-theme-btn" data-theme="red">❤️ Красная</button>
                <button class="settings-theme-btn" data-theme="teal">💚 Бирюзовая</button>
            </div>
        </div>
        <div class="settings-option">
            <label>Фон</label>
            <div class="settings-checkbox">
                <input type="checkbox" id="starsCheckbox" checked>
                <label for="starsCheckbox">✨ Звезды на фоне</label>
            </div>
        </div>
        <button class="settings-close" id="settingsClose">Закрыть</button>
    </div>
    
    <div class="admin-panel" id="adminPanel">
        <h3>👑 Админ панель</h3>
        <div class="user-list" id="userList">
            <div class="loading-gif">Загрузка пользователей...</div>
        </div>
        <button class="settings-close" id="adminPanelClose">Закрыть</button>
    </div>

    <script>
        let currentUser = null;
        let currentName = null;
        let currentTheme = 'purple';
        let lastMessageCount = 0;
        let isLoading = false;
        let gifLoadTimeout = null;
        let starsEnabled = true;
        
        const MAIN_ADMIN = 'd0xmee_admin';
        const SECOND_ADMIN = 'xvii';
        
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
        const adminPanelBtn = document.getElementById('adminPanelBtn');
        const adminPanel = document.getElementById('adminPanel');
        const adminPanelClose = document.getElementById('adminPanelClose');
        const userList = document.getElementById('userList');
        const messagesContainer = document.getElementById('messagesContainer');
        const messageInput = document.getElementById('messageInput');
        const sendMessageBtn = document.getElementById('sendMessageBtn');
        const emojiBtn = document.getElementById('emojiBtn');
        const emojiPanel = document.getElementById('emojiPanel');
        const gifBtn = document.getElementById('gifBtn');
        const gifSearch = document.getElementById('gifSearch');
        const gifSearchInput = document.getElementById('gifSearchInput');
        const gifResults = document.getElementById('gifResults');
        const settingsPanel = document.getElementById('settingsPanel');
        const settingsClose = document.getElementById('settingsClose');
        const themeButtons = document.querySelectorAll('.settings-theme-btn');
        const starsCheckbox = document.getElementById('starsCheckbox');
        
        checkSession();
        
        function isAdmin(login) {
            return login === MAIN_ADMIN || login === SECOND_ADMIN;
        }
        
        function getUserBadge(login) {
            if (login === MAIN_ADMIN) {
                return '<span class="creator-badge">👑 СОЗДАТЕЛЬ</span>';
            } else if (login === SECOND_ADMIN) {
                return '<span class="admin-badge">xvii 👑</span>';
            } else if (isAdmin(login)) {
                return '<span class="admin-badge">👑 АДМИН</span>';
            }
            return '';
        }
        
        async function checkSession() {
            try {
                let response = await fetch('?check_session=1');
                let data = await response.json();
                if (data.logged) {
                    currentUser = data.user;
                    currentName = data.name || data.user;
                    
                    updateUserDisplay();
                    
                    if (isAdmin(currentUser)) {
                        adminPanelBtn.classList.add('show');
                    }
                    
                    authScreen.style.display = 'none';
                    chatApp.classList.add('show');
                    loadMessages();
                    setInterval(loadMessages, 2000);
                }
            } catch(e) {
                console.log('Session check error:', e);
            }
        }
        
        function updateUserDisplay() {
            let userDisplay = currentName;
            userDisplay += ' ' + getUserBadge(currentUser);
            currentUserSpan.innerHTML = userDisplay;
        }
        
        function setTheme(theme) {
            body.className = theme + (starsEnabled ? ' stars-yes' : '');
            currentTheme = theme;
            
            chatApp.classList.add('theme-change');
            setTimeout(() => {
                chatApp.classList.remove('theme-change');
            }, 1000);
            
            themeButtons.forEach(btn => {
                if (btn.dataset.theme === theme) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
        }
        
        function toggleStars(enabled) {
            starsEnabled = enabled;
            if (enabled) {
                body.classList.add('stars-yes');
            } else {
                body.classList.remove('stars-yes');
            }
        }
        
        async function loadGifs(search = 'funny') {
            try {
                gifResults.innerHTML = '<div class="loading-gif">Загрузка гифок...</div>';
                
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
                gifResults.innerHTML = '<div class="no-gifs">❌ Ошибка загрузки</div>';
            }
        }
        
        async function loadUsers() {
            try {
                userList.innerHTML = '<div class="loading-gif">Загрузка пользователей...</div>';
                
                let response = await fetch('?get_users=1');
                let users = await response.json();
                
                if (users && users.length > 0) {
                    userList.innerHTML = '';
                    users.forEach(user => {
                        if (user.login === currentUser) return;
                        
                        let userDiv = document.createElement('div');
                        userDiv.className = 'user-item';
                        
                        userDiv.innerHTML = `
                            <div class="user-info-admin">
                                <div>
                                    <div class="user-name">${user.name}</div>
                                    <div class="user-login">${user.login}</div>
                                </div>
                            </div>
                            <div class="admin-actions">
                                <div class="ban-input">
                                    <input type="number" id="ban-${user.login}" min="1" max="1440" value="60" placeholder="мин">
                                    <button onclick="banUser('${user.login}')">⛔ Бан</button>
                                </div>
                            </div>
                        `;
                        
                        userList.appendChild(userDiv);
                    });
                } else {
                    userList.innerHTML = '<div class="no-gifs">Нет пользователей</div>';
                }
            } catch(e) {
                userList.innerHTML = '<div class="no-gifs">Ошибка загрузки</div>';
            }
        }
        
        window.banUser = async function(login) {
            let minutes = document.getElementById('ban-' + login).value;
            if (!minutes || minutes < 1) {
                alert('Введите корректное количество минут');
                return;
            }
            
            let formData = new FormData();
            formData.append('ban_user', true);
            formData.append('login', login);
            formData.append('minutes', minutes);
            
            try {
                let response = await fetch('', { method: 'POST', body: formData });
                let result = await response.json();
                if (result.success) {
                    alert(`Пользователь ${login} забанен на ${minutes} минут`);
                }
            } catch(e) {}
        };
        
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
                    regError.textContent = 'Заполните все поля';
                    return;
                }
                if (password !== confirm) {
                    regError.textContent = 'Пароли не совпадают';
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
                        regSuccess.textContent = 'Регистрация успешна!';
                        regName.value = ''; 
                        regLogin.value = ''; 
                        regPassword.value = ''; 
                        regConfirm.value = '';
                        setTimeout(function() { 
                            if (loginTab) loginTab.click(); 
                        }, 2000);
                    } else {
                        regError.textContent = result.message;
                    }
                } catch(e) {
                    regError.textContent = 'Ошибка соединения';
                }
            };
        }
        
        if (loginBtn) {
            loginBtn.onclick = async function() {
                let login = loginLogin.value.trim();
                let password = loginPassword.value.trim();
                
                if (!login || !password) {
                    loginError.textContent = 'Введите логин и пароль';
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
                        
                        updateUserDisplay();
                        
                        if (isAdmin(currentUser)) {
                            adminPanelBtn.classList.add('show');
                        }
                        
                        authScreen.style.display = 'none';
                        chatApp.classList.add('show');
                        loadMessages();
                        setInterval(loadMessages, 2000);
                    } else {
                        loginError.textContent = result.message;
                    }
                } catch(e) {
                    loginError.textContent = 'Ошибка соединения';
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
                    adminPanelBtn.classList.remove('show');
                } catch(e) {}
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
                        messages.forEach(function(msg, index) {
                            let div = document.createElement('div');
                            div.className = 'message ' + (msg.from === currentName ? 'sent' : 'received');
                            div.dataset.index = index;
                            
                            let sender = document.createElement('div');
                            sender.className = 'message-sender';
                            
                            let senderText = msg.from;
                            let badge = '';
                            
                            if (msg.role === 'admin') {
                                if (msg.from === MAIN_ADMIN || msg.from === 'd0xmee_admin') {
                                    badge = '<span class="creator-badge-small">👑 СОЗДАТЕЛЬ</span>';
                                } else if (msg.from === SECOND_ADMIN || msg.from === 'xvii' || msg.from === 'Королевский') {
                                    badge = '<span class="admin-badge-small">xvii 👑</span>';
                                } else {
                                    badge = '<span class="admin-badge-small">👑 АДМИН</span>';
                                }
                            }
                            
                            sender.innerHTML = senderText + ' ' + badge;
                            
                            if (isAdmin(currentUser)) {
                                let deleteBtn = document.createElement('button');
                                deleteBtn.className = 'delete-btn';
                                deleteBtn.innerHTML = '✕';
                                deleteBtn.onclick = function(e) {
                                    e.stopPropagation();
                                    deleteMessage(index);
                                };
                                div.appendChild(deleteBtn);
                            }
                            
                            let content = document.createElement('div');
                            content.className = 'message-content';
                            
                            if (msg.type === 'gif' || (msg.message && msg.message.includes('giphy.com'))) {
                                let img = document.createElement('img');
                                img.src = msg.message;
                                img.style.maxWidth = '100%';
                                img.style.maxHeight = '300px';
                                img.style.borderRadius = '12px';
                                img.onclick = function() { window.open(msg.message, '_blank'); };
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
                        emptyDiv.style.opacity = '0.7';
                        emptyDiv.textContent = 'Нет сообщений. Напишите что-нибудь!';
                        messagesContainer.appendChild(emptyDiv);
                    }
                    
                    lastMessageCount = messages.length;
                    
                    if (shouldScroll) {
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    }
                }
            } catch(e) {} finally {
                isLoading = false;
            }
        }
        
        async function deleteMessage(index) {
            if (!confirm('Удалить сообщение?')) return;
            
            let formData = new FormData();
            formData.append('delete_message', true);
            formData.append('index', index);
            
            try {
                let response = await fetch('', { method: 'POST', body: formData });
                let result = await response.json();
                if (result.success) {
                    loadMessages();
                } else {
                    alert(result.message || 'Ошибка удаления');
                }
            } catch(e) {}
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
                    
                    result.messages.forEach(function(msg, index) {
                        let div = document.createElement('div');
                        div.className = 'message ' + (msg.from === currentName ? 'sent' : 'received');
                        div.dataset.index = index;
                        
                        let sender = document.createElement('div');
                        sender.className = 'message-sender';
                        
                        let senderText = msg.from;
                        let badge = '';
                        
                        if (msg.role === 'admin') {
                            if (msg.from === MAIN_ADMIN || msg.from === 'd0xmee_admin') {
                                badge = '<span class="creator-badge-small">👑 СОЗДАТЕЛЬ</span>';
                            } else if (msg.from === SECOND_ADMIN || msg.from === 'xvii' || msg.from === 'Королевский') {
                                badge = '<span class="admin-badge-small">xvii 👑</span>';
                            } else {
                                badge = '<span class="admin-badge-small">👑 АДМИН</span>';
                            }
                        }
                        
                        sender.innerHTML = senderText + ' ' + badge;
                        
                        if (isAdmin(currentUser)) {
                            let deleteBtn = document.createElement('button');
                            deleteBtn.className = 'delete-btn';
                            deleteBtn.innerHTML = '✕';
                            deleteBtn.onclick = function(e) {
                                e.stopPropagation();
                                deleteMessage(index);
                            };
                            div.appendChild(deleteBtn);
                        }
                        
                        let contentDiv = document.createElement('div');
                        contentDiv.className = 'message-content';
                        
                        if (msg.type === 'gif' || (msg.message && msg.message.includes('giphy.com'))) {
                            let img = document.createElement('img');
                            img.src = msg.message;
                            img.style.maxWidth = '100%';
                            img.style.maxHeight = '300px';
                            img.style.borderRadius = '12px';
                            img.onclick = function() { window.open(msg.message, '_blank'); };
                            contentDiv.appendChild(img);
                        } else {
                            contentDiv.textContent = msg.message;
                        }
                        
                        let time = document.createElement('div');
                        time.className = 'message-time';
                        time.textContent = msg.time;
                        
                        let date = document.createElement('div');
                        date.className = 'message-date';
                        date.textContent = msg.date;
                        
                        div.appendChild(sender);
                        div.appendChild(contentDiv);
                        div.appendChild(time);
                        div.appendChild(date);
                        messagesContainer.appendChild(div);
                    });
                    
                    lastMessageCount = result.messages.length;
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    if (!content) messageInput.value = '';
                }
            } catch(e) {}
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
        
        if (adminPanelBtn) {
            adminPanelBtn.onclick = function() {
                adminPanel.classList.add('show');
                loadUsers();
            };
        }
        
        if (adminPanelClose) {
            adminPanelClose.onclick = function() {
                adminPanel.classList.remove('show');
            };
        }
        
        themeButtons.forEach(btn => {
            btn.onclick = function() {
                let theme = this.dataset.theme;
                setTheme(theme);
            };
        });
        
        if (starsCheckbox) {
            starsCheckbox.onchange = function() {
                toggleStars(this.checked);
            };
        }
        
        document.addEventListener('click', function(e) {
            if (settingsPanel && e.target === settingsPanel) {
                settingsPanel.classList.remove('show');
            }
            if (adminPanel && e.target === adminPanel) {
                adminPanel.classList.remove('show');
            }
        });
    </script>
</body>
</html>
