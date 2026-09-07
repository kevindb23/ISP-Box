<?php

require_once BASE_PATH . '/app/Core/Branding/branding.php';

$branding = \App\Core\Branding::get();
$companyName = $branding['company_name'] ?? $branding['client_name'] ?? 'ISP-in-a-Box';
$logoText = trim((string)($branding['logo_text'] ?? $branding['portal_title'] ?? ''));
$logoPath = trim((string)($branding['logo_path'] ?? ''));
$logoFile = '';
if ($logoPath !== '' && str_starts_with($logoPath, '/')) {
    $logoFile = BASE_PATH . '/public' . parse_url($logoPath, PHP_URL_PATH);
    if (!is_file($logoFile)) {
        $logoPath = '';
    }
}
$primaryColor = $branding['primary_color'] ?? '#2563EB';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access the operations console · <?= htmlspecialchars($companyName) ?></title>
    <link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/icons/bootstrap-icons.css?v=<?= time() ?>">
    <script>
        // A login page is reached after logout or an expired session. Reset
        // client-side UI preferences so the next authenticated session starts
        // from a clean state.
        try {
            localStorage.clear();
        } catch (error) {
            // Ignore browsers that disable local storage.
        }
    </script>
    <?php require BASE_PATH . '/app/UI/Views/layouts/vite.php'; ?>
    <style>
        :root {
            --auth-ink: #101828;
            --auth-muted: #667085;
            --auth-faint: #98A2B3;
            --auth-line: #D9DEE7;
            --auth-blue: <?= htmlspecialchars($primaryColor) ?>;
            --auth-navy: #0B1320;
            --auth-panel: #FFFFFF;
            --auth-control: #FFFFFF;
            --auth-ring: color-mix(in srgb, var(--auth-blue) 18%, transparent);
        }

        * { box-sizing: border-box; }

        html, body { min-height: 100%; }

        body {
            margin: 0;
            color: var(--auth-ink);
            background: var(--auth-navy);
            font-family: "IBM Plex Sans", "Segoe UI", Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        button, input { font: inherit; }

        .auth-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(480px, 520px) minmax(0, 1fr);
        }

        .auth-panel {
            min-height: 100vh;
            padding: 54px 72px 48px;
            background: var(--auth-panel);
            border-right: 1px solid #162438;
            display: flex;
            flex-direction: column;
        }

        .auth-brand {
            display: flex;
            align-items: center;
            gap: 16px;
            min-height: 88px;
            color: var(--auth-ink);
            text-decoration: none;
        }

        .auth-brand-mark {
            width: 132px;
            height: 88px;
            flex: 0 0 132px;
            border: 0;
            border-radius: 0;
            display: grid;
            place-items: center;
            overflow: visible;
            color: var(--auth-ink);
            font-size: 10px;
            font-weight: 700;
        }

        .auth-brand-mark img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 0;
            transform: none;
        }

        .auth-brand-name {
            font-family: "Manrope", "IBM Plex Sans", sans-serif;
            font-size: 25px;
            font-weight: 700;
            letter-spacing: -.02em;
        }

        .auth-form-wrap {
            width: 100%;
            max-width: 400px;
            margin-top: 40px;
        }

        .auth-product {
            margin-bottom: 10px;
            color: var(--auth-blue);
            font-size: 14px;
            line-height: 1.3;
            font-weight: 700;
            letter-spacing: .055em;
            text-transform: uppercase;
        }

        .auth-title {
            font-family: "Manrope", "IBM Plex Sans", sans-serif;
            max-width: 380px;
            margin: 0;
            font-size: clamp(24px, 1.8vw, 27px);
            line-height: 1.18;
            font-weight: 600;
            letter-spacing: -.035em;
        }

        .auth-subtitle {
            margin: 12px 0 42px;
            color: var(--auth-muted);
            font-size: 14px;
            line-height: 1.55;
        }

        .auth-field { margin-bottom: 24px; }

        .auth-label {
            display: block;
            margin-bottom: 8px;
            color: var(--auth-ink);
            font-size: 13px;
            font-weight: 600;
        }

        .auth-input-wrap { position: relative; }

        .auth-input {
            width: 100%;
            height: 48px;
            padding: 0 14px;
            color: var(--auth-ink);
            background: var(--auth-control);
            border: 1px solid var(--auth-line);
            border-radius: 8px;
            outline: 0;
            font-size: 14px;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .auth-input::placeholder { color: var(--auth-faint); }
        .auth-input:focus {
            border-color: var(--auth-blue);
            box-shadow: 0 0 0 3px var(--auth-ring);
        }

        .auth-input--password { padding-right: 64px; }

        .password-toggle {
            position: absolute;
            top: 0;
            right: 0;
            height: 48px;
            padding: 0 15px;
            color: var(--auth-blue);
            background: transparent;
            border: 0;
            font-size: 12px;
            font-weight: 600;
            border-radius: 6px;
        }

        .password-toggle:hover,
        .password-toggle:focus-visible { background: color-mix(in srgb, var(--auth-blue) 8%, transparent); }

        .auth-assistance {
            min-height: 18px;
            margin: -4px 0 18px;
            display: flex;
            justify-content: flex-start;
        }

        .auth-assistance span {
            color: var(--auth-blue);
            font-size: 12px;
            line-height: 1.45;
        }

        .auth-link { padding: 0; color: var(--auth-blue); background: transparent; border: 0; font-size: 12px; cursor: pointer; }
        .auth-link:hover { text-decoration: underline; }
        .auth-mfa-step h2 { margin: 16px 0 8px; font-size: 20px; font-weight: 600; }
        .auth-mfa-step p { margin: 0 0 20px; color: var(--auth-muted); font-size: 13px; line-height: 1.5; }
        .auth-mfa-icon { width: 42px; height: 42px; display:grid; place-items:center; color: var(--auth-blue); background: var(--auth-ring); border-radius: 50%; font-size: 18px; }
        .auth-mfa-step > .auth-submit { margin-top: 16px; }
        .auth-back-link { display:block; margin: 16px auto 0; }

        .auth-submit {
            width: 100%;
            height: 46px;
            color: #fff;
            background: var(--auth-ink);
            border: 1px solid var(--auth-ink);
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            transition: transform .15s ease, background .15s ease, box-shadow .15s ease;
        }

        .auth-submit:hover { background: #17263A; border-color: #17263A; box-shadow: 0 8px 20px rgba(16, 24, 40, .18); transform: translateY(-1px); }
        .auth-submit:focus-visible { outline: 3px solid var(--auth-ring); outline-offset: 2px; }
        .auth-submit:disabled { cursor: wait; opacity: .72; }

        .auth-security {
            margin-top: 18px;
            padding-top: 24px;
            border-top: 1px solid #E9EDF2;
        }

        .auth-security strong {
            display: block;
            margin-bottom: 7px;
            font-size: 11px;
            font-weight: 600;
        }

        .auth-security p {
            max-width: 360px;
            margin: 0;
            color: var(--auth-muted);
            font-size: 11px;
            line-height: 1.45;
        }

        .auth-footer {
            margin-top: auto;
            color: var(--auth-faint);
            font-size: 10px;
        }

        .motion-panel {
            position: relative;
            min-height: 100vh;
            overflow: hidden;
            background: var(--auth-navy);
            isolation: isolate;
        }

        .motion-panel::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: 0;
            background:
                linear-gradient(90deg, rgba(5, 13, 24, .16), rgba(5, 13, 24, .28)),
                url("/assets/img/backdrop.png") center center / cover no-repeat;
            transform: scale(1.002);
            pointer-events: none;
            opacity: 30%;
        }

        #particles-js {
            position: absolute;
            top: 0;
            left: 0;
            z-index: 1;
            width: 100%;
            height: 100%;
            pointer-events: auto;
        }

        #particles-js canvas {
            display: block;
            vertical-align: bottom;
            width: 100% !important;
            height: 100% !important;
        }

        .motion-panel::after {
            content: "";
            position: absolute;
            inset: 0;
            z-index: 5;
            box-shadow:
                inset 32px 0 54px rgba(2, 8, 16, .22),
                inset 0 0 86px rgba(2, 8, 16, .22);
            pointer-events: none;
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        .toast-card {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            color: var(--auth-ink);
            background: #fff;
            border: 1px solid var(--auth-line);
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
            font-size: 12px;
        }

        .toast-icon { color: #16845B; }

        @media (max-width: 1023.98px) {
            .auth-shell { grid-template-columns: minmax(440px, 52%) 1fr; }
            .auth-panel { padding-inline: 52px; }
        }

        @media (max-width: 767.98px) {
            body { background: var(--auth-panel); }
            .auth-shell { display: block; }
            .auth-panel { min-height: 100vh; padding: max(24px, env(safe-area-inset-top)) 24px max(24px, env(safe-area-inset-bottom)); border: 0; }
            .auth-brand { min-height: 68px; }
            .auth-brand-mark { width: 104px; height: 68px; flex-basis: 104px; }
            .auth-brand-name { font-size: 21px; }
            .auth-form-wrap { max-width: 440px; margin: 32px auto 0; }
            .motion-panel { display: none; }
            .auth-footer { padding-top: 40px; }
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --auth-ink: #F3F6FA;
                --auth-muted: #AEB9C8;
                --auth-faint: #8290A3;
                --auth-line: #34445A;
                --auth-panel: #101A29;
                --auth-control: #152235;
            }
            .auth-panel { border-right-color: #26364B; }
            .auth-security { border-top-color: #28384E; }
            .auth-submit { color: #0B1320; background: #F3F6FA; border-color: #F3F6FA; }
            .auth-submit:hover { background: #DCE5EF; border-color: #DCE5EF; }
            .toast-card { color: var(--auth-ink); background: var(--auth-panel); }
        }

        @media (prefers-reduced-motion: reduce) {
            #particles-js { display: none; }
            .motion-panel::before { opacity: .42; }
            *, *::before, *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; animation-duration: .01ms !important; animation-iteration-count: 1 !important; }
        }
    </style>
</head>
<body>
<div class="toast-container" id="toastContainer" role="status" aria-live="polite"></div>

<main class="auth-shell">
    <section class="auth-panel" aria-labelledby="loginTitle">
        <div class="auth-brand">
            <span class="auth-brand-mark">
                <?php if ($logoPath !== ''): ?>
                    <img src="<?= htmlspecialchars($logoPath) ?>" alt="">
                <?php else: ?>
                    <span>1W</span>
                <?php endif; ?>
            </span>
            <?php if ($logoText !== ''): ?>
                <span class="auth-brand-name"><?= htmlspecialchars($logoText) ?></span>
            <?php endif; ?>
        </div>

        <div class="auth-form-wrap">
            <div class="auth-product">ISP-IN-A-BOX</div>
            <h1 class="auth-title" id="loginTitle">Access the<br>operations console</h1>
            <p class="auth-subtitle">Use your assigned credentials to continue.</p>

            <form id="loginForm" aria-describedby="loginAssistance">
                <input type="hidden" name="csrf_token" value="<?= \App\Core\Security\Csrf::token() ?>">

                <div class="auth-field">
                    <label class="auth-label" for="username">Username</label>
                    <input class="auth-input" id="username" type="text" name="username" placeholder="Enter your username" autocomplete="username" required>
                </div>

                <div class="auth-field">
                    <label class="auth-label" for="password">Password</label>
                    <div class="auth-input-wrap">
                        <input class="auth-input auth-input--password" id="password" type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                        <button class="password-toggle" id="togglePassword" type="button" aria-label="Show password">Show</button>
                    </div>
                </div>

                <div class="auth-assistance" id="loginAssistance"><button type="button" class="auth-link" id="forgotPasswordLink">Forgot your password?</button></div>
                <button class="auth-submit" id="loginBtn" type="submit">Sign in</button>
            </form>

            <form id="mfaForm" class="auth-mfa-step" hidden>
                <input type="hidden" name="csrf_token" value="<?= \App\Core\Security\Csrf::token() ?>">
                <input type="hidden" name="challenge_token" id="mfaChallengeToken">
                <div class="auth-mfa-icon"><i class="bi bi-shield-lock"></i></div>
                <h2>Verify your identity</h2>
                <p id="mfaPrompt">Enter the six-digit code from your authenticator app.</p>
                <input class="auth-input" id="mfaCode" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000" required>
                <button class="auth-submit" id="mfaBtn" type="submit">Verify and continue</button>
                <button class="auth-link auth-back-link" id="mfaBack" type="button">Back to sign in</button>
            </form>

            <form id="forgotPasswordForm" class="auth-mfa-step" hidden>
                <input type="hidden" name="csrf_token" value="<?= \App\Core\Security\Csrf::token() ?>">
                <input type="hidden" name="challenge_token" id="resetChallengeToken">
                <div class="auth-mfa-icon"><i class="bi bi-key"></i></div>
                <h2>Reset your password</h2>
                <p id="resetPrompt">Enter your username or email to request a verification code.</p>
                <input class="auth-input" id="resetIdentifier" placeholder="Username or email" autocomplete="username" required>
                <div id="resetVerificationFields" hidden><input class="auth-input mt-3" id="resetCode" inputmode="numeric" maxlength="6" placeholder="Verification code" autocomplete="one-time-code" required><input class="auth-input mt-3" id="resetPassword" type="password" minlength="12" placeholder="New password (12+ characters)" autocomplete="new-password" required></div>
                <button class="auth-submit" id="resetBtn" type="submit">Send verification code</button>
                <button class="auth-link auth-back-link" id="forgotBack" type="button">Back to sign in</button>
            </form>

            <div class="auth-security">
                <strong>Protected access</strong>
                <p>Role-based permissions, session monitoring and audit logging are enabled.</p>
            </div>
        </div>

        <div class="auth-footer">Powered by <?= htmlspecialchars(defined('POWERED_BY') ? POWERED_BY : '1WAN') ?></div>
    </section>

    <section class="motion-panel" aria-hidden="true">
        <div id="particles-js"></div>
    </section>
</main>

<script src="/assets/js/sweetalert2.all.min.js"></script>
<?php if (is_file(BASE_PATH . '/public/assets/vendor/particles/particles.min.js')): ?>
<script src="/assets/vendor/particles/particles.min.js"></script>
<?php endif; ?>
<script>
    const togglePassword = document.getElementById('togglePassword');
    const password = document.getElementById('password');
    const loginForm = document.getElementById('loginForm');
    const loginBtn = document.getElementById('loginBtn');

    if (typeof window.particlesJS === 'function') {
        window.particlesJS('particles-js', {
            particles: {
                number: {
                    value: 100,
                    density: {
                        enable: true,
                        value_area: 800
                    }
                },
                color: {
                    value: '#c8d2db'
                },
                shape: {
                    type: 'circle',
                    stroke: {
                        width: 0,
                        color: '#0B1320'
                    }
                },
                opacity: {
                    value: .5,
                    random: false,
                    anim: {
                        enable: false,
                        speed: 1,
                        opacity_min: .1,
                        sync: false
                    }
                },
                size: {
                    value: 3,
                    random: true,
                    anim: {
                        enable: false,
                        speed: 40,
                        size_min: .1,
                        sync: false
                    }
                },
                line_linked: {
                    enable: true,
                    distance: 150,
                    color: '#7891A8',
                    opacity: .38,
                    width: 1
                },
                move: {
                    enable: true,
                    speed: 5,
                    direction: 'none',
                    random: false,
                    straight: false,
                    out_mode: 'out',
                    bounce: false,
                    attract: {
                        enable: false,
                        rotateX: 600,
                        rotateY: 1200
                    }
                }
            },
            interactivity: {
                detect_on: 'canvas',
                events: {
                    onhover: {
                        enable: true,
                        mode: 'repulse'
                    },
                    onclick: {
                        enable: true,
                        mode: 'push'
                    },
                    resize: true
                },
                modes: {
                    grab: {
                        distance: 400,
                        line_linked: {
                            opacity: 1
                        }
                    },
                    repulse: {
                        distance: 200,
                        duration: .4
                    },
                    push: {
                        particles_nb: 4
                    },
                    remove: {
                        particles_nb: 2
                    }
                }
            },
            retina_detect: true
        });
    }

    togglePassword.addEventListener('click', () => {
        const showing = password.type === 'text';
        password.type = showing ? 'password' : 'text';
        togglePassword.textContent = showing ? 'Show' : 'Hide';
        togglePassword.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
    });

    function showToast(message) {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = 'toast-card';
        toast.innerHTML = '<div class="toast-icon"><i class="bi bi-check-circle-fill"></i></div><div></div>';
        toast.lastElementChild.textContent = message;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 4000);
    }

    const mfaForm = document.getElementById('mfaForm');
    const forgotForm = document.getElementById('forgotPasswordForm');
    const showStep = (step) => {
        loginForm.hidden = step !== 'login';
        mfaForm.hidden = step !== 'mfa';
        forgotForm.hidden = step !== 'forgot';
    };
    const jsonRequest = async (url, payload) => {
        const response = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        const data = await response.json();
        if (!response.ok || data.success === false) throw new Error(data.message || 'Request failed.');
        return data;
    };

    loginForm.addEventListener('submit', async (event) => {
        event.preventDefault(); loginBtn.disabled = true; loginBtn.textContent = 'Signing in…';
        const formData = new FormData(loginForm);
        try {
            const data = await jsonRequest('/login', { username: formData.get('username'), password: formData.get('password'), csrf_token: formData.get('csrf_token') });
            const result = data.data || {};
            if (result.mfa_required) {
                document.getElementById('mfaChallengeToken').value = result.challenge_token;
                document.getElementById('mfaPrompt').textContent = result.method === 'EMAIL' ? 'Enter the verification code sent to the account email.' : 'Enter the six-digit code from your authenticator app.';
                showStep('mfa'); document.getElementById('mfaCode').focus(); return;
            }
            showToast('Logged in successfully'); window.setTimeout(() => { window.location.href = result.redirect_url || '/dashboard'; }, 450); return;
        } catch (error) { await Swal.fire({ icon:'error', title:'Login failed', text:error.message, confirmButtonColor:'<?= htmlspecialchars($primaryColor) ?>' }); }
        loginBtn.disabled = false; loginBtn.textContent = 'Sign in';
    });

    mfaForm.addEventListener('submit', async (event) => {
        event.preventDefault(); const button = document.getElementById('mfaBtn'); button.disabled = true; button.textContent = 'Verifying…';
        try { const data = await jsonRequest('/login/mfa', { challenge_token: document.getElementById('mfaChallengeToken').value, code: document.getElementById('mfaCode').value, csrf_token: mfaForm.querySelector('[name="csrf_token"]').value }); window.location.href = data.data?.redirect_url || '/dashboard'; }
        catch (error) { await Swal.fire({ icon:'error', title:'Verification failed', text:error.message }); button.disabled = false; button.textContent = 'Verify and continue'; }
    });
    document.getElementById('mfaBack').addEventListener('click', () => { showStep('login'); loginBtn.disabled = false; loginBtn.textContent = 'Sign in'; });
    document.getElementById('forgotPasswordLink').addEventListener('click', () => showStep('forgot'));
    document.getElementById('forgotBack').addEventListener('click', () => showStep('login'));
    forgotForm.addEventListener('submit', async (event) => {
        event.preventDefault(); const button = document.getElementById('resetBtn'); button.disabled = true; button.textContent = 'Sending…';
        try {
            const tokenField = document.getElementById('resetChallengeToken');
            if (!tokenField.value) {
                const data = await jsonRequest('/forgot-password/request', { identifier: document.getElementById('resetIdentifier').value, csrf_token: forgotForm.querySelector('[name="csrf_token"]').value });
                tokenField.value = data.data?.challenge_token || ''; document.getElementById('resetVerificationFields').hidden = false; document.getElementById('resetPrompt').textContent = 'If the account is eligible, enter the code sent to its email and choose a new password.'; button.textContent = 'Reset password'; button.disabled = false; document.getElementById('resetCode').focus(); return;
            }
            await jsonRequest('/forgot-password/reset', { challenge_token: tokenField.value, code: document.getElementById('resetCode').value, password: document.getElementById('resetPassword').value, csrf_token: forgotForm.querySelector('[name="csrf_token"]').value });
            await Swal.fire({ icon:'success', title:'Password reset', text:'You can now sign in with your new password.' }); showStep('login');
        } catch (error) { await Swal.fire({ icon:'error', title:'Unable to reset password', text:error.message }); }
        button.disabled = false; button.textContent = document.getElementById('resetChallengeToken').value ? 'Reset password' : 'Send verification code';
    });
</script>
</body>
</html>
