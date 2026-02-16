<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

$users_file = 'users.txt';
$messages_file = 'messages.txt';
$bans_file = 'bans.txt';
$avatar_dir = 'avatars/';

if (!file_exists($avatar_dir)) {
    mkdir($avatar_dir, 0777, true);
}

$MAIN_ADMIN = 'd0xmee';
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
        
        $user_line = $name . '|' . $login . '|' . $password . "|\n";
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
        echo json_encode(['success' => false, 'message' => 'Вы забанены на 1 минуту']);
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
                        'password' => trim($parts[2]),
                        'avatar' => isset($parts[3]) ? trim($parts[3]) : ''
                    ];
                }
            }
        }
    }
    
    $logged = false;
    $user_name = '';
    $user_avatar = '';
    
    foreach ($users as $user) {
        if ($user['login'] === $login && $user['password'] === $password) {
            $logged = true;
            $user_name = $user['name'];
            $user_avatar = $user['avatar'];
            break;
        }
    }
    
    if ($logged) {
        $_SESSION['user'] = $login;
        $_SESSION['name'] = $user_name;
        $_SESSION['avatar'] = $user_avatar;
        
        setcookie('remember_user', $login, time() + (86400 * 30), '/');
        
        echo json_encode(['success' => true, 'name' => $user_name, 'avatar' => $user_avatar]);
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
            'name' => $_SESSION['name'],
            'avatar' => isset($_SESSION['avatar']) ? $_SESSION['avatar'] : ''
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
                        $_SESSION['avatar'] = isset($parts[3]) ? trim($parts[3]) : '';
                        break;
                    }
                }
            }
        }
        
        echo json_encode([
            'logged' => true, 
            'user' => $_COOKIE['remember_user'],
            'name' => isset($_SESSION['name']) ? $_SESSION['name'] : $_COOKIE['remember_user'],
            'avatar' => isset($_SESSION['avatar']) ? $_SESSION['avatar'] : ''
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

if (isset($_FILES['avatar']) && isset($_POST['update_avatar'])) {
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Не авторизован']);
        exit;
    }
    
    $file = $_FILES['avatar'];
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!in_array($file['type'], $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Неподдерживаемый формат файла']);
        exit;
    }
    
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Файл слишком большой (макс 5MB)']);
        exit;
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $_SESSION['user'] . '_' . time() . '.' . $ext;
    $filepath = $avatar_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $_SESSION['avatar'] = $filepath;
        
        // Update users file
        if (file_exists($users_file)) {
            $content = file_get_contents($users_file);
            $lines = explode("\n", $content);
            $new_lines = [];
            
            foreach ($lines as $line) {
                if (!empty($line)) {
                    $parts = explode('|', $line);
                    if (trim($parts[1]) === $_SESSION['user']) {
                        $line = $parts[0] . '|' . $parts[1] . '|' . $parts[2] . '|' . $filepath;
                    }
                }
                $new_lines[] = $line;
            }
            
            file_put_contents($users_file, implode("\n", $new_lines));
        }
        
        echo json_encode(['success' => true, 'avatar' => $filepath]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ошибка загрузки файла']);
    }
    exit;
}

if (isset($_POST['send_message'])) {
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Не авторизован']);
        exit;
    }
    
    if (isBanned($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Вы забанены на 1 минуту']);
        exit;
    }
    
    $from = isset($_SESSION['name']) ? $_SESSION['name'] : $_SESSION['user'];
    $from_login = $_SESSION['user'];
    $avatar = isset($_SESSION['avatar']) ? $_SESSION['avatar'] : '';
    $message = trim($_POST['message']);
    $type = isset($_POST['type']) ? $_POST['type'] : 'text';
    
    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Пустое сообщение']);
        exit;
    }
    
    $time = date('H:i:s');
    $date = date('d.m.Y');
    $role = isAdmin($_SESSION['user']) ? 'admin' : 'user';
    
    $new_message = $from . '|' . $from_login . '|' . $avatar . '|' . $message . '|' . $time . '|' . $date . '|' . $type . '|' . $role . "\n";
    file_put_contents($messages_file, $new_message, FILE_APPEND | LOCK_EX);
    
    $messages = [];
    
    if (file_exists($messages_file)) {
        $content = file_get_contents($messages_file);
        $lines = explode("\n", trim($content));
        $lines = array_filter($lines);
        
        foreach ($lines as $line) {
            if (!empty($line)) {
                $parts = explode('|', $line);
                if (count($parts) >= 8) {
                    $messages[] = [
                        'from' => trim($parts[0]),
                        'from_login' => trim($parts[1]),
                        'avatar' => trim($parts[2]),
                        'message' => trim($parts[3]),
                        'time' => trim($parts[4]),
                        'date' => trim($parts[5]),
                        'type' => trim($parts[6]),
                        'role' => trim($parts[7])
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
                if (count($parts) >= 8) {
                    $messages[] = [
                        'from' => trim($parts[0]),
                        'from_login' => trim($parts[1]),
                        'avatar' => trim($parts[2]),
                        'message' => trim($parts[3]),
                        'time' => trim($parts[4]),
                        'date' => trim($parts[5]),
                        'type' => trim($parts[6]),
                        'role' => trim($parts[7])
                    ];
                }
            }
        }
    }
    
    echo json_encode($messages);
    exit;
}

if (isset($_POST['delete_message'])) {
    if (!isset($_SESSION['user']) || !isAdmin($_SESSION['user'])) {
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
    $minutes = 1;
    $ban_until = time() + ($minutes * 60);
    
    $ban_line = $ban_login . '|' . $minutes . '|' . $ban_until . "\n";
    file_put_contents($bans_file, $ban_line, FILE_APPEND | LOCK_EX);
    
    echo json_encode(['success' => true]);
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
            transition: background 1.5s ease-in-out;
            background-size: 300% 300% !important;
            animation: floatGradient 20s ease infinite;
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
            50% { background-position: 100% 100%; }
            100% { background-position: 0% 0%; }
        }
        
        body.purple {
            background: linear-gradient(135deg, #667eea, #764ba2, #9f7aea, #667eea);
            background-size: 400% 400%;
        }
        
        body.pink {
            background: linear-gradient(135deg, #ff9a9e, #fad0c4, #ffb6c1, #ff9a9e);
            background-size: 400% 400%;
        }
        
        body.gray {
            background: linear-gradient(135deg, #2c3e50, #3498db, #2c3e50, #34495e);
            background-size: 400% 400%;
        }
        
        body.green {
            background: linear-gradient(135deg, #134e5e, #71b280, #2ecc71, #134e5e);
            background-size: 400% 400%;
        }
        
        body.blue {
            background: linear-gradient(135deg, #2b5876, #4e4376, #3498db, #2b5876);
            background-size: 400% 400%;
        }
        
        body.orange {
            background: linear-gradient(135deg, #ff6b6b, #feca57, #ff8c42, #ff6b6b);
            background-size: 400% 400%;
        }
        
        body.black {
            background: linear-gradient(135deg, #000000, #434343, #000000, #1a1a1a);
            background-size: 400% 400%;
        }
        
        body.teal {
            background: linear-gradient(135deg, #11998e, #38ef7d, #20b2aa, #11998e);
            background-size: 400% 400%;
        }
        
        .auth-screen, .chat-app, .settings-panel {
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
            transform: translateY(0);
            animation: floatAuth 6s ease-in-out infinite;
        }
        
        @keyframes floatAuth {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        body.purple .auth-screen {
            background: rgba(102, 126, 234, 0.2);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
        }
        
        body.pink .auth-screen {
            background: rgba(255, 220, 220, 0.2);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255,150,150,0.3);
            color: white;
        }
        
        body.gray .auth-screen {
            background: rgba(44, 62, 80, 0.2);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255,255,255,0.1);
            color: white;
        }
        
        body.green .auth-screen {
            background: rgba(19, 78, 94, 0.2);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(120,255,120,0.2);
            color: white;
        }
        
        body.blue .auth-screen {
            background: rgba(43, 88, 118, 0.2);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(100,200,255,0.3);
            color: white;
        }
        
        body.orange .auth-screen {
            background: rgba(255, 107, 107, 0.2);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255,200,100,0.3);
            color: white;
        }
        
        body.black .auth-screen {
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255,215,0,0.3);
            color: white;
        }
        
        body.teal .auth-screen {
            background: rgba(17, 153, 142, 0.2);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(100,255,150,0.3);
            color: white;
        }
        
        .auth-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            background: rgba(0,0,0,0.2);
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
            color: rgba(255,255,255,0.8);
        }
        
        .auth-tab.active {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .auth-form {
            display: none;
        }
        
        .auth-form.active {
            display: block;
            animation: slideIn 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        @keyframes slideIn {
            0% {
                opacity: 0;
                transform: translateX(-50px) rotate(-5deg);
            }
            100% {
                opacity: 1;
                transform: translateX(0) rotate(0);
            }
        }
        
        .auth-form h2 {
            text-align: center;
            margin-bottom: 30px;
            background: linear-gradient(45deg, #667eea, #764ba2, #9f7aea);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 32px;
            font-weight: 700;
            animation: gradientText 3s ease infinite;
            background-size: 200% auto;
        }
        
        @keyframes gradientText {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .input-group {
            margin-bottom: 20px;
            animation: fadeInUp 0.5s ease backwards;
        }
        
        .input-group:nth-child(1) { animation-delay: 0.1s; }
        .input-group:nth-child(2) { animation-delay: 0.2s; }
        .input-group:nth-child(3) { animation-delay: 0.3s; }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
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
            color: rgba(255,255,255,0.9);
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .auth-form input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid transparent;
            border-radius: 15px;
            font-size: 16px;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            -webkit-appearance: none;
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .auth-form input::placeholder {
            color: rgba(255,255,255,0.5);
        }
        
        .auth-form input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 30px rgba(102, 126, 234, 0.4);
            transform: translateY(-3px) scale(1.02);
            background: rgba(255,255,255,0.25);
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
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            margin-top: 15px;
            -webkit-appearance: none;
            position: relative;
            overflow: hidden;
        }
        
        .auth-form button::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .auth-form button:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .auth-form button:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.5);
        }
        
        .error {
            color: #ff6b6b;
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
            animation: shake 0.5s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
            text-shadow: 0 0 10px rgba(255,107,107,0.3);
        }
        
        @keyframes shake {
            10%, 90% { transform: translateX(-1px); }
            20%, 80% { transform: translateX(2px); }
            30%, 50%, 70% { transform: translateX(-4px); }
            40%, 60% { transform: translateX(4px); }
        }
        
        .success {
            color: #00d68f;
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
            animation: popIn 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            text-shadow: 0 0 10px rgba(0,214,143,0.3);
        }
        
        @keyframes popIn {
            0% { 
                transform: scale(0) rotate(-180deg);
                opacity: 0;
            }
            100% { 
                transform: scale(1) rotate(0);
                opacity: 1;
            }
        }
        
        .chat-app {
            max-width: 1200px;
            width: 100%;
            height: 90vh;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border-radius: 30px;
            overflow: hidden;
            display: none;
            flex-direction: column;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.2);
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            transform: scale(1);
        }
        
        .chat-app.theme-change {
            animation: themeSpin 1.2s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        @keyframes themeSpin {
            0% {
                transform: rotate(0deg) scale(1);
            }
            50% {
                transform: rotate(180deg) scale(1.1);
                box-shadow: 0 50px 100px rgba(102, 126, 234, 0.5);
            }
            100% {
                transform: rotate(360deg) scale(1);
            }
        }
        
        .chat-app.show {
            display: flex;
            animation: appAppear 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        @keyframes appAppear {
            0% {
                opacity: 0;
                transform: scale(0.8) translateY(50px);
            }
            100% {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
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
            transition: all 0.5s ease;
        }
        
        .chat-header::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: rotate(45deg);
            animation: headerShine 6s ease-in-out infinite;
        }
        
        @keyframes headerShine {
            0% { transform: translateX(-100%) rotate(45deg); }
            20% { transform: translateX(100%) rotate(45deg); }
            100% { transform: translateX(100%) rotate(45deg); }
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
        
        body.black .chat-header {
            background: linear-gradient(45deg, #000000, #434343);
        }
        
        body.teal .chat-header {
            background: linear-gradient(45deg, #20b2aa, #11998e);
        }
        
        .chat-header h2 {
            font-size: 28px;
            font-weight: 700;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
            animation: glow 3s ease-in-out infinite;
        }
        
        @keyframes glow {
            0%, 100% { text-shadow: 0 0 20px rgba(255,255,255,0.5); }
            50% { text-shadow: 0 0 40px rgba(255,255,255,0.8); }
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
            gap: 10px;
            background: rgba(255,255,255,0.2);
            padding: 8px 20px 8px 10px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 16px;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255,255,255,0.2);
            transition: all 0.3s ease;
        }
        
        .current-user:hover {
            transform: scale(1.05);
            background: rgba(255,255,255,0.3);
        }
        
        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255,255,255,0.3);
        }
        
        .avatar-btn {
            width: 45px;
            height: 45px;
            border: none;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            color: white;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-appearance: none;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .avatar-btn:hover {
            transform: rotate(360deg) scale(1.1);
            background: rgba(255,255,255,0.3);
            box-shadow: 0 0 30px rgba(255,255,255,0.3);
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
            animation: goldGlow 2s ease-in-out infinite;
        }
        
        @keyframes goldGlow {
            0%, 100% { box-shadow: 0 0 10px rgba(255,215,0,0.5); }
            50% { box-shadow: 0 0 20px rgba(255,215,0,0.8); }
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
            animation: purpleGlow 2s ease-in-out infinite;
        }
        
        @keyframes purpleGlow {
            0%, 100% { box-shadow: 0 0 10px rgba(155,89,182,0.5); }
            50% { box-shadow: 0 0 20px rgba(155,89,182,0.8); }
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
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-appearance: none;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .settings-btn:hover {
            transform: rotate(360deg) scale(1.1);
            background: rgba(255,255,255,0.3);
            box-shadow: 0 0 30px rgba(255,255,255,0.3);
        }
        
        .logout-btn {
            padding: 10px 22px;
            background: rgba(255,68,68,0.3);
            color: white;
            border: 1px solid rgba(255,68,68,0.5);
            border-radius: 30px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            -webkit-appearance: none;
            backdrop-filter: blur(5px);
        }
        
        .logout-btn:hover {
            transform: translateY(-5px) scale(1.05);
            background: rgba(255,68,68,0.5);
            box-shadow: 0 10px 30px rgba(255,68,68,0.4);
            border-color: #ff4444;
        }
        
        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 25px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            scroll-behavior: smooth;
        }
        
        .message {
            max-width: 70%;
            padding: 15px 20px;
            border-radius: 20px;
            animation: messageAppear 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            font-size: 16px;
            word-wrap: break-word;
            position: relative;
            transition: all 0.3s ease;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex;
            flex-direction: column;
        }
        
        @keyframes messageAppear {
            0% {
                opacity: 0;
                transform: translateY(30px) scale(0.8);
            }
            70% {
                transform: translateY(-5px) scale(1.02);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .message:hover {
            transform: scale(1.02) translateY(-2px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.2);
            border-color: rgba(255,255,255,0.2);
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
        
        body.black .message.sent {
            background: linear-gradient(45deg, #434343, #000000);
        }
        
        body.teal .message.sent {
            background: linear-gradient(45deg, #20b2aa, #11998e);
        }
        
        .message.received {
            align-self: flex-start;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(5px);
            color: white;
            border-bottom-left-radius: 5px;
        }
        
        .message-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .message-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255,255,255,0.3);
        }
        
        .message-sender {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
            font-weight: 600;
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
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            z-index: 10;
        }
        
        .message:hover .delete-btn {
            display: flex;
            animation: deleteAppear 0.3s ease;
        }
        
        @keyframes deleteAppear {
            0% {
                transform: scale(0) rotate(-90deg);
            }
            100% {
                transform: scale(1) rotate(0);
            }
        }
        
        .delete-btn:hover {
            transform: scale(1.2) rotate(90deg);
            background: #ff6666;
            box-shadow: 0 0 20px rgba(255,68,68,0.5);
        }
        
        .message-content {
            margin-bottom: 8px;
            line-height: 1.5;
            font-size: 16px;
            word-break: break-word;
        }
        
        .message-content img {
            max-width: 100%;
            max-height: 300px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        .message-content img:hover {
            transform: scale(1.05) rotate(1deg);
            box-shadow: 0 15px 30px rgba(0,0,0,0.3);
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
            border-top: 1px solid rgba(255,255,255,0.1);
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            background: rgba(0,0,0,0.2);
            backdrop-filter: blur(5px);
        }
        
        .chat-input-area input[type="text"] {
            flex: 1;
            min-width: 200px;
            padding: 16px 22px;
            border: 2px solid transparent;
            border-radius: 30px;
            font-size: 16px;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            -webkit-appearance: none;
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .chat-input-area input[type="text"]::placeholder {
            color: rgba(255,255,255,0.5);
        }
        
        .chat-input-area input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 30px rgba(102, 126, 234, 0.3);
            transform: translateY(-3px) scale(1.02);
            background: rgba(255,255,255,0.25);
        }
        
        .chat-input-area input[type="file"] {
            display: none;
        }
        
        .file-upload-btn {
            width: 55px;
            height: 55px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-size: 24px;
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-appearance: none;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .file-upload-btn:hover {
            transform: rotate(360deg) scale(1.1);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.5);
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
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-appearance: none;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        body.purple .file-upload-btn,
        body.purple .action-btn {
            background: linear-gradient(45deg, #9b59b6, #8e44ad);
        }
        
        body.pink .file-upload-btn,
        body.pink .action-btn {
            background: linear-gradient(45deg, #ff7676, #ff9f9f);
        }
        
        body.gray .file-upload-btn,
        body.gray .action-btn {
            background: linear-gradient(45deg, #34495e, #2c3e50);
        }
        
        body.green .file-upload-btn,
        body.green .action-btn {
            background: linear-gradient(45deg, #27ae60, #229954);
        }
        
        body.blue .file-upload-btn,
        body.blue .action-btn {
            background: linear-gradient(45deg, #3498db, #2980b9);
        }
        
        body.orange .file-upload-btn,
        body.orange .action-btn {
            background: linear-gradient(45deg, #ff8c42, #ff6b6b);
        }
        
        body.black .file-upload-btn,
        body.black .action-btn {
            background: linear-gradient(45deg, #434343, #000000);
        }
        
        body.teal .file-upload-btn,
        body.teal .action-btn {
            background: linear-gradient(45deg, #20b2aa, #11998e);
        }
        
        .file-upload-btn:hover,
        .action-btn:hover {
            transform: rotate(360deg) scale(1.1);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.5);
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
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            display: none;
            grid-template-columns: repeat(12, 1fr);
            gap: 8px;
            z-index: 100;
            max-height: 350px;
            overflow-y: auto;
            animation: slideUp 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            background: rgba(0,0,0,0.3);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255,255,255,0.2);
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
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            color: white;
            background: rgba(255,255,255,0.05);
        }
        
        .emoji-item:hover {
            transform: scale(1.3) rotate(5deg);
            background: rgba(255,255,255,0.2);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .avatar-upload-panel {
            position: absolute;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%);
            border-radius: 30px;
            padding: 25px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
            display: none;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            z-index: 100;
            animation: slideUp 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            background: rgba(0,0,0,0.4);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.2);
            width: 300px;
        }
        
        .avatar-upload-panel.show {
            display: flex;
        }
        
        .avatar-upload-panel h3 {
            color: white;
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        .avatar-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #667eea;
            box-shadow: 0 0 30px rgba(102, 126, 234, 0.5);
        }
        
        .avatar-upload-btn {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 30px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .avatar-upload-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        
        .avatar-cancel-btn {
            background: rgba(255,68,68,0.3);
            color: white;
            border: 1px solid rgba(255,68,68,0.5);
            padding: 12px 25px;
            border-radius: 30px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .avatar-cancel-btn:hover {
            background: rgba(255,68,68,0.5);
            transform: scale(1.05);
        }
        
        .settings-panel {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            border-radius: 30px;
            padding: 35px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.4);
            z-index: 1000;
            display: none;
            width: 90%;
            max-width: 450px;
            animation: modalPop 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            background: rgba(0,0,0,0.4);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.2);
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
        
        .settings-panel.show {
            display: block;
        }
        
        .settings-panel h3 {
            margin-bottom: 25px;
            font-size: 32px;
            text-align: center;
            background: linear-gradient(45deg, #667eea, #764ba2, #9f7aea);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
            animation: gradientText 3s ease infinite;
            background-size: 200% auto;
        }
        
        .settings-option {
            margin: 25px 0;
        }
        
        .settings-option label {
            display: block;
            margin-bottom: 12px;
            font-size: 18px;
            font-weight: 600;
            color: white;
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
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            font-size: 14px;
            color: white;
        }
        
        .settings-theme-btn:hover {
            transform: scale(1.05) translateY(-2px);
            background: rgba(255,255,255,0.2);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            border-color: #667eea;
        }
        
        .settings-theme-btn.active {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-color: transparent;
            color: white;
            box-shadow: 0 0 30px rgba(102, 126, 234, 0.5);
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
        
        .settings-checkbox label {
            color: white;
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
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        .settings-close:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.5);
        }
        
        .ban-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(255,68,68,0.9);
            backdrop-filter: blur(10px);
            color: white;
            padding: 15px 25px;
            border-radius: 50px;
            font-weight: 600;
            animation: slideInRight 0.5s ease;
            z-index: 2000;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        ::-webkit-scrollbar {
            width: 10px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.05);
            border-radius: 5px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-radius: 5px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(45deg, #764ba2, #667eea);
        }
        
        @media (max-width: 768px) {
            .emoji-panel {
                grid-template-columns: repeat(8, 1fr);
                bottom: 90px;
            }
            
            .avatar-upload-panel {
                width: 250px;
                bottom: 90px;
            }
            
            .message {
                max-width: 85%;
            }
            
            .settings-theme-buttons {
                grid-template-columns: 1fr;
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
                font-size: 24px;
            }
            
            .current-user {
                padding: 5px 15px 5px 5px;
                font-size: 14px;
            }
            
            .user-avatar {
                width: 30px;
                height: 30px;
            }
            
            .avatar-btn, .settings-btn, .file-upload-btn, .action-btn {
                width: 45px;
                height: 45px;
                font-size: 22px;
            }
            
            .logout-btn {
                padding: 8px 15px;
                font-size: 14px;
            }
            
            .message {
                max-width: 90%;
                padding: 12px 16px;
                font-size: 14px;
            }
            
            .message-avatar {
                width: 30px;
                height: 30px;
            }
            
            .chat-input-area {
                padding: 15px;
            }
            
            .chat-input-area input[type="text"] {
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
            
            .avatar-upload-panel {
                width: 220px;
                padding: 15px;
            }
            
            .avatar-preview {
                width: 80px;
                height: 80px;
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
            <h2>✨ Чат ✨</h2>
            <div class="user-info">
                <span class="current-user" id="currentUser"></span>
                <button class="avatar-btn" id="avatarBtn">🖼️</button>
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
            </div>
            
            <div class="avatar-upload-panel" id="avatarUploadPanel">
                <h3>Загрузить аватар</h3>
                <img src="" class="avatar-preview" id="avatarPreview" style="display: none;">
                <input type="file" id="avatarFileInput" accept="image/*" style="display: none;">
                <button class="avatar-upload-btn" id="selectAvatarBtn">Выбрать фото</button>
                <button class="avatar-cancel-btn" id="cancelAvatarBtn">Отмена</button>
            </div>
            
            <div class="chat-input-area">
                <button class="action-btn" id="emojiBtn">😊</button>
                <input type="file" id="fileInput" accept="image/*">
                <button class="file-upload-btn" id="fileUploadBtn" onclick="document.getElementById('fileInput').click()">📷</button>
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
                <button class="settings-theme-btn" data-theme="black">🖤 Черная</button>
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

    <script>
        let currentUser = null;
        let currentName = null;
        let currentAvatar = null;
        let currentTheme = 'purple';
        let lastMessageCount = 0;
        let isLoading = false;
        let starsEnabled = true;
        
        const MAIN_ADMIN = 'd0xmee';
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
        const avatarBtn = document.getElementById('avatarBtn');
        const avatarUploadPanel = document.getElementById('avatarUploadPanel');
        const avatarFileInput = document.getElementById('avatarFileInput');
        const selectAvatarBtn = document.getElementById('selectAvatarBtn');
        const cancelAvatarBtn = document.getElementById('cancelAvatarBtn');
        const avatarPreview = document.getElementById('avatarPreview');
        const messagesContainer = document.getElementById('messagesContainer');
        const messageInput = document.getElementById('messageInput');
        const sendMessageBtn = document.getElementById('sendMessageBtn');
        const emojiBtn = document.getElementById('emojiBtn');
        const emojiPanel = document.getElementById('emojiPanel');
        const fileInput = document.getElementById('fileInput');
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
                return '<span class="creator-badge">👑 god</span>';
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
                    currentAvatar = data.avatar || '';
                    
                    updateUserDisplay();
                    
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
            let avatarHtml = currentAvatar ? 
                `<img src="${currentAvatar}?t=${Date.now()}" class="user-avatar">` : 
                '';
            let userDisplay = avatarHtml + currentName + ' ' + getUserBadge(currentUser);
            currentUserSpan.innerHTML = userDisplay;
        }
        
        function setTheme(theme) {
            body.className = theme + (starsEnabled ? ' stars-yes' : '');
            currentTheme = theme;
            
            chatApp.classList.add('theme-change');
            setTimeout(() => {
                chatApp.classList.remove('theme-change');
            }, 1200);
            
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
        
        function showBanNotification() {
            let notification = document.createElement('div');
            notification.className = 'ban-notification';
            notification.textContent = '⛔ Вы забанены на 1 минуту';
            document.body.appendChild(notification);
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
        
        async function uploadAvatar(file) {
            let formData = new FormData();
            formData.append('update_avatar', true);
            formData.append('avatar', file);
            
            try {
                let response = await fetch('', { method: 'POST', body: formData });
                let result = await response.json();
                if (result.success) {
                    currentAvatar = result.avatar;
                    updateUserDisplay();
                    avatarUploadPanel.classList.remove('show');
                    avatarPreview.style.display = 'none';
                    avatarPreview.src = '';
                } else {
                    alert(result.message || 'Ошибка загрузки');
                }
            } catch(e) {
                alert('Ошибка соединения');
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
                        currentAvatar = result.avatar || '';
                        
                        updateUserDisplay();
                        
                        authScreen.style.display = 'none';
                        chatApp.classList.add('show');
                        loadMessages();
                        setInterval(loadMessages, 2000);
                    } else {
                        loginError.textContent = result.message;
                        if (result.message.includes('забанены')) {
                            showBanNotification();
                        }
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
                } catch(e) {}
            };
        }
        
        if (avatarBtn) {
            avatarBtn.onclick = function() {
                avatarUploadPanel.classList.toggle('show');
                emojiPanel.classList.remove('show');
            };
        }
        
        if (selectAvatarBtn) {
            selectAvatarBtn.onclick = function() {
                avatarFileInput.click();
            };
        }
        
        if (avatarFileInput) {
            avatarFileInput.onchange = function(e) {
                let file = e.target.files[0];
                if (file) {
                    let reader = new FileReader();
                    reader.onload = function(event) {
                        avatarPreview.src = event.target.result;
                        avatarPreview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                }
            };
        }
        
        if (cancelAvatarBtn) {
            cancelAvatarBtn.onclick = function() {
                avatarUploadPanel.classList.remove('show');
                avatarPreview.style.display = 'none';
                avatarPreview.src = '';
                avatarFileInput.value = '';
            };
        }
        
        // Upload avatar when file is selected
        avatarFileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                uploadAvatar(this.files[0]);
            }
        });
        
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
                            
                            let header = document.createElement('div');
                            header.className = 'message-header';
                            
                            let avatarHtml = msg.avatar ? 
                                `<img src="${msg.avatar}?t=${Date.now()}" class="message-avatar">` : 
                                '';
                            
                            let sender = document.createElement('div');
                            sender.className = 'message-sender';
                            
                            let senderText = msg.from;
                            let badge = '';
                            
                            if (msg.role === 'admin') {
                                if (msg.from_login === MAIN_ADMIN) {
                                    badge = '<span class="creator-badge-small">👑 god</span>';
                                } else if (msg.from_login === SECOND_ADMIN) {
                                    badge = '<span class="admin-badge-small">xvii 👑</span>';
                                } else {
                                    badge = '<span class="admin-badge-small">👑 АДМИН</span>';
                                }
                            }
                            
                            sender.innerHTML = senderText + ' ' + badge;
                            
                            header.innerHTML = avatarHtml;
                            header.appendChild(sender);
                            
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
                            
                            if (msg.type === 'image') {
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
                            
                            div.appendChild(header);
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
                        emptyDiv.style.color = 'white';
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
                        
                        let header = document.createElement('div');
                        header.className = 'message-header';
                        
                        let avatarHtml = msg.avatar ? 
                            `<img src="${msg.avatar}?t=${Date.now()}" class="message-avatar">` : 
                            '';
                        
                        let sender = document.createElement('div');
                        sender.className = 'message-sender';
                        
                        let senderText = msg.from;
                        let badge = '';
                        
                        if (msg.role === 'admin') {
                            if (msg.from_login === MAIN_ADMIN) {
                                badge = '<span class="creator-badge-small">👑 god</span>';
                            } else if (msg.from_login === SECOND_ADMIN) {
                                badge = '<span class="admin-badge-small">xvii 👑</span>';
                            } else {
                                badge = '<span class="admin-badge-small">👑 АДМИН</span>';
                            }
                        }
                        
                        sender.innerHTML = senderText + ' ' + badge;
                        
                        header.innerHTML = avatarHtml;
                        header.appendChild(sender);
                        
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
                        
                        if (msg.type === 'image') {
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
                        
                        div.appendChild(header);
                        div.appendChild(contentDiv);
                        div.appendChild(time);
                        div.appendChild(date);
                        messagesContainer.appendChild(div);
                    });
                    
                    lastMessageCount = result.messages.length;
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    if (!content) messageInput.value = '';
                } else if (result.message && result.message.includes('забанены')) {
                    showBanNotification();
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
                avatarUploadPanel.classList.remove('show');
            };
        }
        
        document.querySelectorAll('.emoji-item').forEach(function(emoji) {
            emoji.onclick = function() {
                messageInput.value += emoji.textContent;
                messageInput.focus();
            };
        });
        
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
                fileInput.value = '';
            };
        }
        
        document.addEventListener('click', function(e) {
            if (avatarBtn && !avatarBtn.contains(e.target) && avatarUploadPanel && !avatarUploadPanel.contains(e.target) &&
                emojiBtn && !emojiBtn.contains(e.target) && emojiPanel && !emojiPanel.contains(e.target)) {
                avatarUploadPanel.classList.remove('show');
                emojiPanel.classList.remove('show');
            }
        });
        
        if (settingsBtn) {
            settingsBtn.onclick = function() {
                settingsPanel.classList.add('show');
                avatarUploadPanel.classList.remove('show');
                emojiPanel.classList.remove('show');
            };
        }
        
        if (settingsClose) {
            settingsClose.onclick = function() {
                settingsPanel.classList.remove('show');
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
        });
    </script>
</body>
</html>
