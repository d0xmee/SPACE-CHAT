<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

$users_file = 'users.txt';
$messages_file = 'messages.txt';

if (isset($_POST['register'])) {
    $name = $_POST['name'];
    $login = $_POST['login'];
    $password = $_POST['password'];
    
    $users = file_exists($users_file) ? file($users_file, FILE_IGNORE_NEW_LINES) : [];
    $exists = false;
    
    foreach ($users as $u) {
        if (!empty($u)) {
            $parts = explode('|', $u);
            if (count($parts) >= 2 && $parts[1] === $login) {
                $exists = true;
                break;
            }
        }
    }
    
    if (!$exists) {
        file_put_contents($users_file, "$name|$login|$password\n", FILE_APPEND);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Логин уже занят']);
    }
    exit;
}

if (isset($_POST['login'])) {
    $login = $_POST['login'];
    $password = $_POST['password'];
    
    $users = file_exists($users_file) ? file($users_file, FILE_IGNORE_NEW_LINES) : [];
    $logged = false;
    $user_name = '';
    
    foreach ($users as $u) {
        if (!empty($u)) {
            $parts = explode('|', $u);
            if (count($parts) >= 3 && $parts[1] === $login && $parts[2] === $password) {
                $logged = true;
                $user_name = $parts[0];
                break;
            }
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
        echo json_encode(['logged' => true, 'user' => $_SESSION['user'], 'name' => $_SESSION['name']]);
    } elseif (isset($_COOKIE['remember_user'])) {
        $_SESSION['user'] = $_COOKIE['remember_user'];
        echo json_encode(['logged' => true, 'user' => $_COOKIE['remember_user']]);
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
    if (!isset($_SESSION['user'])) exit;
    
    $from = isset($_SESSION['name']) ? $_SESSION['name'] : $_SESSION['user'];
    $message = $_POST['message'];
    $time = date('H:i:s');
    
    $new_message = "$from|$message|$time\n";
    file_put_contents($messages_file, $new_message, FILE_APPEND);
    
    $messages = [];
    if (file_exists($messages_file)) {
        $lines = file($messages_file, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $line) {
            if (!empty($line)) {
                $parts = explode('|', $line);
                if (count($parts) >= 3) {
                    $messages[] = [
                        'from' => $parts[0],
                        'message' => $parts[1],
                        'time' => $parts[2]
                    ];
                }
            }
        }
    }
    echo json_encode(['success' => true, 'messages' => $messages]);
    exit;
}

if (isset($_GET['get_messages'])) {
    if (!isset($_SESSION['user'])) exit;
    
    $messages = [];
    
    if (file_exists($messages_file)) {
        $lines = file($messages_file, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $line) {
            if (!empty($line)) {
                $parts = explode('|', $line);
                if (count($parts) >= 3) {
                    $messages[] = [
                        'from' => $parts[0],
                        'message' => $parts[1],
                        'time' => $parts[2]
                    ];
                }
            }
        }
    }
    
    echo json_encode($messages);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>💫 ЧАТ 💫</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 16px;
            background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            position: relative;
            overflow: hidden;
        }
        
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .star {
            position: absolute;
            width: 2px;
            height: 2px;
            background: white;
            border-radius: 50%;
            animation: twinkle 3s infinite;
        }
        
        @keyframes twinkle {
            0%, 100% { opacity: 0.2; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.5); }
        }
        
        .shooting-star {
            position: absolute;
            width: 100px;
            height: 2px;
            background: linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.8) 50%, rgba(255,255,255,0) 100%);
            transform: rotate(-45deg);
            animation: shoot 4s linear infinite;
            opacity: 0;
        }
        
        @keyframes shoot {
            0% { transform: translateX(-200px) rotate(-45deg); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateX(2000px) rotate(-45deg); opacity: 0; }
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-10px) scale(1.02); }
        }
        
        @keyframes glow {
            0%, 100% { box-shadow: 0 0 20px rgba(102, 126, 234, 0.3); }
            50% { box-shadow: 0 0 50px rgba(102, 126, 234, 0.8); }
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-50px) rotate(-5deg);
            }
            to {
                opacity: 1;
                transform: translateX(0) rotate(0);
            }
        }
        
        @keyframes popIn {
            0% {
                opacity: 0;
                transform: scale(0.3) rotate(-180deg);
            }
            70% {
                transform: scale(1.1) rotate(10deg);
            }
            100% {
                opacity: 1;
                transform: scale(1) rotate(0);
            }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px) rotate(-5deg); }
            75% { transform: translateX(10px) rotate(5deg); }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .auth-screen {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            padding: 40px;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: float 3s ease infinite, glow 3s infinite;
            border: 1px solid rgba(255,255,255,0.3);
            position: relative;
            z-index: 10;
        }
        
        .auth-tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 30px;
            background: rgba(0,0,0,0.05);
            padding: 5px;
            border-radius: 50px;
        }
        
        .auth-tab {
            flex: 1;
            padding: 12px;
            border: none;
            background: transparent;
            border-radius: 50px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            color: #666;
            position: relative;
            overflow: hidden;
        }
        
        .auth-tab::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .auth-tab:hover::before {
            width: 150px;
            height: 150px;
        }
        
        .auth-tab:hover {
            background: rgba(102, 126, 234, 0.1);
        }
        
        .auth-tab.active {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            animation: pulse 2s infinite;
        }
        
        .auth-form {
            display: none;
            animation: slideIn 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        .auth-form.active {
            display: block;
        }
        
        .auth-form h2 {
            text-align: center;
            margin-bottom: 25px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 28px;
            animation: glow 2s infinite;
        }
        
        .input-group {
            margin-bottom: 20px;
            animation: slideIn 0.5s ease;
            animation-fill-mode: both;
        }
        
        .input-group:nth-child(1) { animation-delay: 0.1s; }
        .input-group:nth-child(2) { animation-delay: 0.2s; }
        .input-group:nth-child(3) { animation-delay: 0.3s; }
        .input-group:nth-child(4) { animation-delay: 0.4s; }
        
        .input-group label {
            display: block;
            margin-bottom: 8px;
            color: #444;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .input-group:focus-within label {
            transform: translateX(10px) scale(1.1);
            color: #667eea;
        }
        
        .auth-form input {
            width: 100%;
            padding: 15px;
            border: 2px solid #eaeaea;
            border-radius: 15px;
            font-size: 15px;
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            background: rgba(255,255,255,0.9);
        }
        
        .auth-form input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 5px rgba(102, 126, 234, 0.2);
            transform: scale(1.02) translateY(-2px);
        }
        
        .auth-form button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            margin-top: 15px;
            position: relative;
            overflow: hidden;
            animation: pulse 2s infinite;
        }
        
        .auth-form button::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .auth-form button:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .auth-form button:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.6);
        }
        
        .error {
            color: #ff4444;
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
            animation: shake 0.5s ease;
        }
        
        .success {
            color: #00c851;
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
            animation: popIn 0.5s ease;
        }
        
        .chat-app {
            max-width: 900px;
            width: 100%;
            height: 85vh;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            overflow: hidden;
            display: none;
            flex-direction: column;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: popIn 0.6s ease, glow 3s infinite;
            border: 1px solid rgba(255,255,255,0.3);
            position: relative;
            z-index: 10;
        }
        
        .chat-app.show {
            display: flex;
        }
        
        .chat-header {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
            animation: slideIn 0.5s ease;
        }
        
        .chat-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 70%);
            animation: rotate 10s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .chat-header h2 {
            font-size: 22px;
            font-weight: 600;
            position: relative;
            animation: pulse 2s infinite;
        }
        
        .chat-header h2::before {
            content: '✨';
            animation: sparkle 1s ease infinite;
            margin-right: 8px;
        }
        
        @keyframes sparkle {
            0%, 100% { opacity: 1; transform: scale(1) rotate(0); }
            50% { opacity: 0.5; transform: scale(1.3) rotate(10deg); }
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
        }
        
        .current-user {
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 30px;
            font-weight: 500;
            animation: pulse 2s infinite;
        }
        
        .logout-btn {
            padding: 8px 20px;
            background: #ff4444;
            color: white;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            position: relative;
            overflow: hidden;
        }
        
        .logout-btn:hover {
            transform: translateY(-5px) scale(1.1) rotate(5deg);
            box-shadow: 0 15px 30px rgba(255,68,68,0.5);
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
        
        .message {
            max-width: 70%;
            padding: 12px 18px;
            border-radius: 18px;
            animation: slideIn 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            transition: all 0.3s;
            transform: translateZ(0);
            backface-visibility: hidden;
        }
        
        .message:hover {
            transform: scale(1.05) translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .message.sent {
            align-self: flex-end;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border-bottom-right-radius: 5px;
        }
        
        .message.received {
            align-self: flex-start;
            background: white;
            color: #333;
            border-bottom-left-radius: 5px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .message-sender {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 5px;
            opacity: 0.8;
            animation: slideIn 0.3s ease;
        }
        
        .message-time {
            font-size: 10px;
            text-align: right;
            margin-top: 5px;
            opacity: 0.6;
        }
        
        .chat-input-area {
            padding: 20px 30px;
            background: white;
            border-top: 2px solid rgba(102, 126, 234, 0.2);
            display: flex;
            gap: 15px;
            animation: slideIn 0.5s ease;
        }
        
        .chat-input-area input {
            flex: 1;
            padding: 15px 25px;
            border: 2px solid #eaeaea;
            border-radius: 30px;
            font-size: 15px;
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        .chat-input-area input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 5px rgba(102, 126, 234, 0.2);
            transform: scale(1.02);
        }
        
        .chat-input-area button {
            width: 55px;
            height: 55px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-size: 22px;
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            animation: pulse 2s infinite;
        }
        
        .chat-input-area button:hover {
            transform: rotate(360deg) scale(1.2);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.5);
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
            animation: pulse 2s infinite;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(45deg, #764ba2, #667eea);
        }
        
        @media (max-width: 600px) {
            .chat-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .user-info {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .message {
                max-width: 85%;
            }
        }
    </style>
</head>
<body>
    <script>
        // Создаем звезды на фоне
        for (let i = 0; i < 50; i++) {
            let star = document.createElement('div');
            star.className = 'star';
            star.style.left = Math.random() * 100 + '%';
            star.style.top = Math.random() * 100 + '%';
            star.style.animationDelay = Math.random() * 3 + 's';
            document.body.appendChild(star);
        }
        
        // Создаем падающие звезды
        for (let i = 0; i < 5; i++) {
            let shootingStar = document.createElement('div');
            shootingStar.className = 'shooting-star';
            shootingStar.style.top = (10 + i * 20) + '%';
            shootingStar.style.left = '-10%';
            shootingStar.style.animationDelay = i * 1.5 + 's';
            document.body.appendChild(shootingStar);
        }
    </script>
    
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
            <h2>КОСМИЧЕСКИЙ ЧАТ</h2>
            <div class="user-info">
                <span class="current-user" id="currentUser"></span>
                <button class="logout-btn" id="logoutBtn">👋 Выйти</button>
            </div>
        </div>
        
        <div class="messages-container" id="messagesContainer"></div>
        
        <div class="chat-input-area">
            <input type="text" id="messageInput" placeholder="💭 Напишите сообщение...">
            <button id="sendMessageBtn">✨</button>
        </div>
    </div>

    <script>
        let currentUser = null;
        let currentName = null;
        let lastMessageCount = 0;
        let isLoading = false;
        
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
        const messagesContainer = document.getElementById('messagesContainer');
        const messageInput = document.getElementById('messageInput');
        const sendMessageBtn = document.getElementById('sendMessageBtn');
        
        checkSession();
        
        async function checkSession() {
            try {
                let response = await fetch('?check_session=1');
                let data = await response.json();
                if (data.logged) {
                    currentUser = data.user;
                    currentName = data.name || data.user;
                    currentUserSpan.textContent = currentName;
                    authScreen.style.display = 'none';
                    chatApp.classList.add('show');
                    loadMessages();
                    setInterval(loadMessages, 2000);
                }
            } catch(e) {}
        }
        
        loginTab.onclick = () => {
            loginTab.classList.add('active');
            registerTab.classList.remove('active');
            loginForm.classList.add('active');
            registerForm.classList.remove('active');
            loginError.textContent = '';
        };
        
        registerTab.onclick = () => {
            registerTab.classList.add('active');
            loginTab.classList.remove('active');
            registerForm.classList.add('active');
            loginForm.classList.remove('active');
            regError.textContent = '';
            regSuccess.textContent = '';
        };
        
        registerBtn.onclick = async () => {
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
            
            let response = await fetch('', { method: 'POST', body: formData });
            let result = await response.json();
            
            if (result.success) {
                regSuccess.textContent = '✅ Регистрация успешна!';
                regName.value = ''; regLogin.value = ''; regPassword.value = ''; regConfirm.value = '';
                setTimeout(() => loginTab.click(), 2000);
            } else {
                regError.textContent = '❌ ' + result.message;
            }
        };
        
        loginBtn.onclick = async () => {
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
            
            let response = await fetch('', { method: 'POST', body: formData });
            let result = await response.json();
            
            if (result.success) {
                currentUser = login;
                currentName = result.name;
                currentUserSpan.textContent = currentName;
                authScreen.style.display = 'none';
                chatApp.classList.add('show');
                loadMessages();
                setInterval(loadMessages, 2000);
            } else {
                loginError.textContent = '❌ ' + result.message;
            }
        };
        
        logoutBtn.onclick = async () => {
            let formData = new FormData();
            formData.append('logout', true);
            await fetch('', { method: 'POST', body: formData });
            chatApp.classList.remove('show');
            authScreen.style.display = 'block';
            lastMessageCount = 0;
        };
        
        async function loadMessages() {
            if (isLoading) return;
            
            try {
                isLoading = true;
                let response = await fetch('?get_messages=1');
                let messages = await response.json();
                
                if (messages.length !== lastMessageCount) {
                    let shouldScroll = messagesContainer.scrollHeight - messagesContainer.scrollTop - messagesContainer.clientHeight < 100;
                    
                    messagesContainer.innerHTML = '';
                    messages.forEach(msg => {
                        let div = document.createElement('div');
                        div.className = 'message ' + (msg.from === currentName ? 'sent' : 'received');
                        
                        let sender = document.createElement('div');
                        sender.className = 'message-sender';
                        sender.textContent = msg.from;
                        
                        let text = document.createElement('div');
                        text.textContent = msg.message;
                        
                        let time = document.createElement('div');
                        time.className = 'message-time';
                        time.textContent = msg.time;
                        
                        div.appendChild(sender);
                        div.appendChild(text);
                        div.appendChild(time);
                        messagesContainer.appendChild(div);
                    });
                    
                    lastMessageCount = messages.length;
                    
                    if (shouldScroll) {
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    }
                }
            } catch(e) {}
            
            isLoading = false;
        }
        
        async function sendMessage() {
            let text = messageInput.value.trim();
            if (!text) return;
            
            let formData = new FormData();
            formData.append('send_message', true);
            formData.append('message', text);
            
            let response = await fetch('', { method: 'POST', body: formData });
            let result = await response.json();
            
            if (result.messages) {
                messagesContainer.innerHTML = '';
                result.messages.forEach(msg => {
                    let div = document.createElement('div');
                    div.className = 'message ' + (msg.from === currentName ? 'sent' : 'received');
                    
                    let sender = document.createElement('div');
                    sender.className = 'message-sender';
                    sender.textContent = msg.from;
                    
                    let text = document.createElement('div');
                    text.textContent = msg.message;
                    
                    let time = document.createElement('div');
                    time.className = 'message-time';
                    time.textContent = msg.time;
                    
                    div.appendChild(sender);
                    div.appendChild(text);
                    div.appendChild(time);
                    messagesContainer.appendChild(div);
                });
                
                lastMessageCount = result.messages.length;
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
            
            messageInput.value = '';
        }
        
        sendMessageBtn.onclick = sendMessage;
        messageInput.onkeypress = (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendMessage();
            }
        };
    </script>
</body>
</html>
