<?php
session_start();
require_once 'config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $password = trim($_POST['password']);

    if (empty($nama_lengkap) || empty($password)) {
        $error = "Email dan password harus diisi";
    } else {
        $stmt = $conn->prepare("SELECT id, nama_lengkap, email, password FROM users WHERE nama_lengkap = ?");
        $stmt->bind_param("s", $nama_lengkap);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['nama_lengkap'];
                $_SESSION['email'] = $user['email'];
                
                header('Location: dashboard.php');
                exit;
            } else {
                $error = "Email atau password salah";
            }
        } else {
            $error = "Email atau password salah";
        }
    }
}

// Handle register
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (empty($nama_lengkap) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Semua field harus diisi";
    } elseif ($password !== $confirm_password) {
        $error = "Password dan konfirmasi password tidak sama";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Email sudah terdaftar";
        } else {
            // Insert new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (nama_lengkap, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $nama_lengkap, $email, $hashed_password);
            
            if ($stmt->execute()) {
                $success = "Registrasi berhasil! Silakan login dengan akun Anda.";
            } else {
                $error = "Terjadi kesalahan saat registrasi";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RVS - Authentication</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    <style>
        /* Base styles */
        * {
            font-family: sans-serif;
        }
         * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
  .glass-effect {
      background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        color: white;
        }
    .video-bg {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      z-index: -1;
    }
   .hero-video {
    position: fixed;
    right: 0;
    bottom: 0;
    min-width: 100%;
    min-height: 100%;
    width: auto;
    height: auto;
    z-index: -1;
    object-fit: cover;
}

    /* Add these new styles for centering */
    .auth-container {
        min-height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 20px;
    }

        /* Form elements */
        .input-style {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            transition: all 0.3s ease;
            font-size: 1rem;
            border-radius: 8px;
            min-width: 240px;
            width: 100%;
            padding: 8px 40px 8px 16px;
            height: 42px;
        }

        .input-style:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.5);
            outline: none;
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.2);
        }

        .input-style::placeholder {
            color: rgba(255, 255, 255, 0.6);
            line-height: normal;
        }

        /* Button styles */
        .auth-btn {
            background: linear-gradient(135deg, #4A90E2 0%, #357ABD 100%);
            transition: all 0.3s ease;
            border: none;
            color: white;
            font-weight: 500;
            cursor: pointer;
            border-radius: 8px;
        }

        .auth-btn:hover {
            background: linear-gradient(135deg, #357ABD 0%, #4A90E2 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(74, 144, 226, 0.4);
        }

        .social-btn {
            background: rgba(255, 255, 255, 0.08);
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            font-weight: 500;
            cursor: pointer;
            border-radius: 8px;
        }

        .social-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }

        /* Form containers */
        .form-container {
            animation: slideUp 0.6s ease forwards;
            max-width: 400px;
            margin: 0 auto;
            width: 100%;
        }

        .register-width {
            max-width: 600px !important;
            width: 100% !important;
            margin: 0 auto;
            padding: 1.75rem !important;
        }

        /* Password field */
        .password-container {
            position: relative;
            width: 100%;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: rgba(255, 255, 255, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            height: 42px;
            padding-right: 4px;
        }

        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 20px 0;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        }

        .divider span {
            padding: 0 10px;
            color: white;
            font-size: 14px;
        }

        /* Animations */
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Register form specific */
        #registerForm .input-style {
            min-width: 240px;
            width: 100%;
            padding: 8px 40px 8px 16px;
            height: 42px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1rem;
        }

        /* Tab buttons */
        .tab-btn {
            background: transparent;
            border: none;
            cursor: pointer;
            font-weight: 500;
            position: relative;
        }
        
        .tab-btn.active {
            opacity: 1 !important;
        }
        
        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: white;
            border-radius: 2px;
        }

        /* Logo */
        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            margin-bottom: 2rem;
        }

        .logo-container img {
            width: 100px;
            height: 60px;
            filter: drop-shadow(0 0 7px  #38b6ff);
        }

        /* Alert styles */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #FCA5A5;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #86EFAC;
        }

        /* Responsive design */
        @media (max-width: 640px) {
            .register-width {
                width: 100% !important;
                min-width: 280px !important;
                padding: 1.25rem !important;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .glass-effect {
                padding: 1.5rem;
            }
        }

        @media (min-width: 641px) and (max-width: 1024px) {
            .register-width {
                width: 90% !important;
                min-width: 520px !important;
            }
        }

        @media (min-width: 1025px) {
            .register-width {
                width: 90% !important;
                min-width: 580px !important;
            }
        }
        
        /* Terms checkbox */
        .terms-container {
            display: flex;
            align-items: flex-start;
            margin-top: 1rem;
        }
        
        .terms-container input {
            margin-top: 3px;
        }
    </style>
</head>
<body>
 <video class="hero-video" autoplay loop muted playsinline>
        <source src="VIDEO.mp4" type="video/mp4">
    </video>
    <div class="auth-container">
        <div id="authContainer" class="glass-effect max-w-xs w-full space-y-4 p-6 rounded-2xl">
            <!-- Logo dan Judul -->
            <div class="logo-container">
                <div class="logo-placeholder">
                    <img src="RVS_LOGO.png" alt="Logo">
                </div>
                <div class="space-y-1 text-center">
                    <h1 class="text-white text-2xl font-bold">Reli Vault Store</h1>
                </div>
            </div>

            <!-- Auth Tabs -->
            <div class="flex justify-center space-x-4 mb-6 border-b border-white/20 pb-2">
                <button onclick="showLogin()" class="tab-btn px-6 py-2 text-white opacity-60 hover:opacity-100 transition-opacity active" id="loginTab">Login</button>
                <button onclick="showRegister()" class="tab-btn px-6 py-2 text-white opacity-60 hover:opacity-100 transition-opacity" id="registerTab">Register</button>
            </div>

            <!-- Alert Messages -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <div id="loginForm" class="form-container space-y-4">
                <form method="POST" action="">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-white text-sm font-medium mb-2">Nama</label>
                            <input type="nama_lengkap" name="nama_lengkap" class="input-style w-full" placeholder="Masukkan nama anda" required>
                        </div>
                        <div class="relative">
                            <label class="block text-white text-sm font-medium mb-2">Password</label>
                            <div class="password-container">
                                <input type="password" id="loginPassword" name="password" class="input-style w-full" placeholder="Masukkan kata sandi" required>
                                 
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                            </div>
                        </div>
                        <button type="submit" name="login" class="auth-btn w-full py-3 text-white font-medium">
                            Masuk
                        </button>
                    </div>
                </form>
                
                <div class="divider">
                    <span>atau lanjutkan dengan</span>
                </div>
                
                <button class="social-btn w-full flex justify-center items-center gap-2 py-2.5">
                    <img src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google" class="h-5 w-5">
                    <span class="text-white">Lanjutkan dengan Google</span>
                </button>
            </div>

            <!-- Register Form -->
            <div id="registerForm" class="form-container hidden space-y-4">
                <form method="POST" action="">
                    <div class="space-y-4">
                        <div class="form-grid">
                            <div>
                                <label class="block text-white text-sm font-medium mb-2">Nama Lengkap</label>
                                <input type="text" name="nama_lengkap" class="input-style" placeholder="Masukkan nama lengkap" required>
                            </div>
                            <div>
                                <label class="block text-white text-sm font-medium mb-2">Email</label>
                                <input type="email" name="email" class="input-style" placeholder="Masukkan email" required>
                            </div>
                            <div>
                                <label class="block text-white text-sm font-medium mb-2">Password</label>
                                <div class="password-container">
                                    <input type="password" id="registerPassword" name="password" class="input-style" placeholder="Buat kata sandi" required>
                                   
                                   
                                </div>
                            </div>
                            <div>
                                <label class="block text-white text-sm font-medium mb-2">Konfirmasi Password</label>
                                <div class="password-container">
                                    <input type="password" id="confirmPassword" name="confirm_password" class="input-style" placeholder="Konfirmasi kata sandi" required>
                                    
                                </div>
                            </div>
                        </div>

                       
                        
                        <button type="submit" name="register" class="auth-btn w-full py-3 text-white font-medium">
                            Daftar
                        </button>
                    </div>
                </form>
                
                <div class="divider">
                    <span>atau daftar dengan</span>
                </div>
                
                <button class="social-btn w-full flex justify-center items-center gap-2 py-2.5">
                    <img src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google" class="h-5 w-5">
                    <span class="text-white">Daftar dengan Google</span>
                </button>
            </div>
        </div>
    </div>

    <script>
        function showLogin() {
            document.getElementById('loginForm').classList.remove('hidden');
            document.getElementById('registerForm').classList.add('hidden');
            document.getElementById('loginTab').classList.add('active');
            document.getElementById('loginTab').classList.remove('opacity-60');
            document.getElementById('registerTab').classList.remove('active');
            document.getElementById('registerTab').classList.add('opacity-60');
            document.getElementById('authContainer').classList.remove('register-width');
            window.history.pushState({}, '', '?form=login');
        }

        function showRegister() {
            document.getElementById('loginForm').classList.add('hidden');
            document.getElementById('registerForm').classList.remove('hidden');
            document.getElementById('registerTab').classList.add('active');
            document.getElementById('registerTab').classList.remove('opacity-60');
            document.getElementById('loginTab').classList.remove('active');
            document.getElementById('loginTab').classList.add('opacity-60');
            document.getElementById('authContainer').classList.add('register-width');
            window.history.pushState({}, '', '?form=register');
        }

        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('svg');
            
            if (input.type === "password") {
                input.type = "text";
                icon.setAttribute('data-state', 'visible');
            } else {
                input.type = "password";
                icon.setAttribute('data-state', 'hidden');
            }
        }

        function getUrlParam(param) {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get(param);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const formType = getUrlParam('form');
            
            <?php if (!empty($success)): ?>
                showLogin();
            <?php elseif (isset($_POST['register'])): ?>
                showRegister();
            <?php else: ?>
                if (formType === 'register') {
                    showRegister();
                } else {
                    showLogin();
                }
            <?php endif; ?>
            
            if (typeof AOS !== 'undefined') {
                AOS.init({
                    duration: 1000,
                    once: true
                });
            }
        });
    </script>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
</body>
</html>
