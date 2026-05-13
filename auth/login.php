<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_guest();

$flash = flash_message();
$emailValue = $_SESSION['old_email'] ?? '';
unset($_SESSION['old_email']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo e(APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo e(base_url('assets/css/app.css')); ?>" rel="stylesheet">
    <style>
        .login-shell {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 3rem 0;
        }
        .login-card {
            border: 0;
            border-radius: 2rem;
            overflow: hidden;
            box-shadow: 0 28px 70px rgba(30, 47, 40, 0.14);
            background: rgba(255, 255, 255, 0.9);
        }
        .brand-panel {
            background: linear-gradient(180deg, #1d4f63 0%, #1f6f5f 55%, #8d5a2e 100%);
            color: #fffdf9;
        }
        .role-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.45rem 0.8rem;
            border-radius: 999px;
            background: rgba(255,255,255,0.12);
            font-size: 0.8rem;
        }
        @media (max-width: 991.98px) {
            .brand-panel {
                border-radius: 2rem 2rem 0 0;
            }
        }
    </style>
</head>
<body>
    <div class="login-shell">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-10">
                    <div class="card login-card">
                        <div class="row g-0">
                            <div class="col-lg-5 brand-panel p-4 p-md-5 d-flex flex-column justify-content-center">
                                <span class="role-pill mb-3"><i class="bi bi-door-open"></i> Secure access</span>
                                <h1 class="display-6 fw-semibold mb-3"><?php echo e(APP_NAME); ?></h1>
                                <p class="mb-4">Sign in to open your role dashboard.</p>
                                <div class="small text-white-50">Demo password for seeded accounts: <code class="text-white">Password123!</code></div>
                            </div>

                            <div class="col-lg-7 p-4 p-md-5">
                                <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start mb-4">
                                    <div>
                                        <p class="eyebrow mb-2">Login</p>
                                        <h2 class="h2 mb-2">Welcome back</h2>
                                        <p class="text-muted mb-0">Enter your account details to continue.</p>
                                    </div>
                                    <a href="<?php echo e(base_url('index.php')); ?>" class="btn btn-outline-primary">Back</a>
                                </div>

                                <?php if ($flash !== null): ?>
                                    <div class="alert alert-<?php echo e($flash['type']); ?> rounded-4 border-0 shadow-sm">
                                        <?php echo e($flash['message']); ?>
                                    </div>
                                <?php endif; ?>

                                <form action="<?php echo e(base_url('auth/process_login.php')); ?>" method="POST" novalidate class="surface-card soft-tint p-4 mb-4">
                                    <div class="mb-3">
                                        <label for="email" class="form-label fw-semibold">Email address</label>
                                        <input
                                            type="email"
                                            class="form-control"
                                            id="email"
                                            name="email"
                                            value="<?php echo e($emailValue); ?>"
                                            placeholder="name@example.com"
                                            required
                                        >
                                    </div>

                                    <div class="mb-3">
                                        <label for="password" class="form-label fw-semibold">Password</label>
                                        <input
                                            type="password"
                                            class="form-control"
                                            id="password"
                                            name="password"
                                            placeholder="Enter your password"
                                            required
                                        >
                                    </div>

                                    <button type="submit" class="btn btn-primary px-4">Sign in</button>
                                </form>

                                <div class="small text-muted">
                                    Demo accounts: admin@hybridhub.local, tech@hybridhub.local, teacher@hybridhub.local, student1@hybridhub.local
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
