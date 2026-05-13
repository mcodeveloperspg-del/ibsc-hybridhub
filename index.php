<?php

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect(role_dashboard_path((string) user_role()));
}

$flash = flash_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e(APP_NAME); ?> - Welcome</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo e(base_url('assets/css/app.css')); ?>" rel="stylesheet">
    <style>
        .welcome-shell {
            min-height: 100vh;
            padding: 3rem 0;
            display: flex;
            align-items: center;
        }
        .welcome-card {
            border: 0;
            border-radius: 2rem;
            overflow: hidden;
            box-shadow: 0 30px 70px rgba(30, 47, 40, 0.12);
            background: rgba(255, 255, 255, 0.9);
        }
        .welcome-hero {
            background:
                radial-gradient(circle at top left, rgba(255, 255, 255, 0.2), transparent 34%),
                linear-gradient(155deg, #1f6f5f 0%, #295847 55%, #8c5a30 100%);
            color: #fffdf8;
        }
        .welcome-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.45rem 0.85rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.14);
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-size: 0.78rem;
        }
        .feature-tile,
        .path-tile {
            border-radius: 1.2rem;
            border: 1px solid rgba(92, 116, 108, 0.12);
            background: rgba(250, 247, 241, 0.9);
        }
        .feature-tile {
            padding: 1.15rem;
            height: 100%;
        }
        .path-tile {
            padding: 1rem;
            height: 100%;
        }
    </style>
</head>
<body>
    <div class="welcome-shell">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xxl-11 col-xl-11">
                    <div class="card welcome-card">
                        <div class="row g-0">
                            <div class="col-lg-5 welcome-hero p-4 p-md-5 d-flex flex-column justify-content-between">
                                <div>
                                    <span class="welcome-badge mb-3"><i class="bi bi-mortarboard-fill"></i> Learning hub</span>
                                    <h1 class="display-6 fw-semibold mb-3"><?php echo e(APP_NAME); ?></h1>
                                    <p class="fs-5 mb-4">A friendlier campus space for learning, teaching, release control, and academic setup.</p>
                                    <p class="mb-0 text-white-50">Designed so students, teachers, operations staff, and admins can quickly tell where to go next without feeling lost.</p>
                                </div>

                                <div class="mt-4">
                                    <div class="small text-uppercase text-white-50 mb-2">Why it feels easier</div>
                                    <div class="d-grid gap-3">
                                        <div class="p-3 rounded-4" style="background: rgba(255,255,255,0.10); border: 1px solid rgba(255,255,255,0.12);">
                                            <div class="fw-semibold mb-1">Guided by role</div>
                                            <div class="small text-white-50">Each person sees only the tools and learning flow that matter to them.</div>
                                        </div>
                                        <div class="p-3 rounded-4" style="background: rgba(255,255,255,0.10); border: 1px solid rgba(255,255,255,0.12);">
                                            <div class="fw-semibold mb-1">Clear learning pathways</div>
                                            <div class="small text-white-50">Unlocked content, teacher materials, and admin setup all stay organized and easy to scan.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-7 p-4 p-md-5">
                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                                    <div>
                                        <p class="eyebrow mb-2">Welcome</p>
                                        <h2 class="h2 mb-2">Start from the right doorway</h2>
                                        <p class="text-muted mb-0">Choose login to enter your workspace, or test the database if you are setting the system up for the first time.</p>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a href="<?php echo e(base_url('auth/login.php')); ?>" class="btn btn-primary px-4">Open Login</a>
                                        <a href="<?php echo e(base_url('database/test_connection.php')); ?>" class="btn btn-outline-primary px-4">Test Database</a>
                                    </div>
                                </div>

                                <?php if ($flash !== null): ?>
                                    <div class="alert alert-<?php echo e($flash['type']); ?> rounded-4 border-0 shadow-sm">
                                        <?php echo e($flash['message']); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="row g-3 mb-4">
                                    <div class="col-md-4">
                                        <div class="feature-tile">
                                            <div class="fw-semibold mb-1">Students</div>
                                            <div class="small text-muted">See enrolled courses, unlocked sessions, and progress in one place.</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="feature-tile">
                                            <div class="fw-semibold mb-1">Lecturers</div>
                                            <div class="small text-muted">Manage assigned units and upload learning materials without extra clutter.</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="feature-tile">
                                            <div class="fw-semibold mb-1">Operations</div>
                                            <div class="small text-muted">Handle release readiness, video links, and controlled access clearly.</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="surface-card soft-tint p-4 mb-4">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                                        <div>
                                            <h3 class="section-title mb-1">New here?</h3>
                                            <p class="section-note mb-0">This platform is arranged to support the real learning journey, not just back-office records.</p>
                                        </div>
                                        <span class="status-chip info"><i class="bi bi-lightbulb"></i> Beginner friendly</span>
                                    </div>
                                    <div class="intro-list">
                                        <div class="intro-item">
                                            <div class="intro-item-icon"><i class="bi bi-1-circle"></i></div>
                                            <div>
                                                <div class="fw-semibold">Sign in with your role account</div>
                                                <div class="small text-muted">The system will take you directly to the right workspace after login.</div>
                                            </div>
                                        </div>
                                        <div class="intro-item">
                                            <div class="intro-item-icon"><i class="bi bi-2-circle"></i></div>
                                            <div>
                                                <div class="fw-semibold">Use the dashboard as your guide</div>
                                                <div class="small text-muted">The first action cards on each dashboard are designed to be the safest next step for new users.</div>
                                            </div>
                                        </div>
                                        <div class="intro-item">
                                            <div class="intro-item-icon"><i class="bi bi-3-circle"></i></div>
                                            <div>
                                                <div class="fw-semibold">Move into pages only when ready</div>
                                                <div class="small text-muted">Tables and status chips help you see what is active, locked, published, or waiting.</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="path-tile">
                                            <div class="fw-semibold mb-1">Demo access</div>
                                            <div class="small text-muted">Seeded users share the password <code>Password123!</code>, making it easy to explore the platform during setup or demos.</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="path-tile">
                                            <div class="fw-semibold mb-1">Protected by role</div>
                                            <div class="small text-muted">Students, lecturers, technical officers, and admins each land in a workspace tailored to their tasks.</div>
                                        </div>
                                    </div>
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
