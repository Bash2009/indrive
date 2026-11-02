<?php
require_once 'config/db.php';
require_once 'config/session.php';

redirect_if_logged_in();

$active_tab = isset($_GET['tab']) && $_GET['tab'] === 'signup' ? 'signup' : 'login';
$error_code  = $_GET['error'] ?? '';
$success_msg = $_GET['success'] ?? '';

$error_messages = [
    'email_exists'          => 'An account with this email already exists.',
    'phone_exists'          => 'An account with this phone number already exists.',
    'invalid_email'         => 'Please enter a valid email address.',
    'invalid_phone'         => 'Please enter a valid phone number.',
    'password_short'        => 'Password must be at least 8 characters.',
    'passwords_mismatch'    => 'Passwords do not match.',
    'terms_required'        => 'You must agree to the Terms & Privacy Policy.',
    'invalid_role'          => 'Please select an account type.',
    'license_required'      => 'Driver license number is required.',
    'license_expiry_invalid'=> 'Please enter a valid future expiry date for your license.',
    'vehicle_required'      => 'Please fill in all vehicle details.',
    'invalid_vehicle_type'  => 'Please select a valid vehicle type.',
    'plate_exists'          => 'A vehicle with this license plate is already registered.',
    'invalid_credentials'   => 'Invalid email or password.',
    'account_disabled'      => 'Your account has been disabled. Contact support.',
    'signup_failed'         => 'Something went wrong. Please try again.',
    'login_failed'          => 'Something went wrong. Please try again.',
];

$error_msg = isset($error_messages[$error_code]) ? $error_messages[$error_code] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Indrive — Sign in</title>
    <meta name="description" content="Sign in to Indrive. Where you're going, we're going.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400&family=Inter:wght@400;500&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="css/bootstrap.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <a href="#auth-form" class="skip-link">Skip to sign in form</a>

    <main class="auth-wrapper">

        <!-- Form Panel -->
        <section id="auth-form" class="auth-form-panel">
            <div class="auth-card">

                <!-- Logo -->
                <a href="/" class="auth-logo" aria-label="Indrive home">in<span>d</span>rive</a>

                <!-- Tabs -->
                <nav class="auth-tabs" role="tablist" aria-label="Authentication method">
                    <a href="?tab=login"
                        class="auth-tab <?php echo $active_tab === 'login' ? 'active' : ''; ?>"
                        role="tab"
                        aria-selected="<?php echo $active_tab === 'login' ? 'true' : 'false'; ?>"
                        id="tab-login"
                        aria-controls="panel-login">
                        Sign in
                    </a>
                    <a href="?tab=signup"
                        class="auth-tab <?php echo $active_tab === 'signup' ? 'active' : ''; ?>"
                        role="tab"
                        aria-selected="<?php echo $active_tab === 'signup' ? 'true' : 'false'; ?>"
                        id="tab-signup"
                        aria-controls="panel-signup">
                        Create account
                    </a>
                </nav>

                <!-- Error / Success Alerts -->
                <?php if ($error_msg): ?>
                <div class="alert alert-error" role="alert">
                    <?php echo htmlspecialchars($error_msg); ?>
                </div>
                <?php endif; ?>
                <?php if ($success_msg): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo htmlspecialchars($success_msg); ?>
                </div>
                <?php endif; ?>

                <!-- ========================================
                     LOGIN FORM
                     ======================================== -->
                <?php if ($active_tab === 'login'): ?>
                <div id="panel-login" role="tabpanel" aria-labelledby="tab-login">
                    <p class="auth-greeting">Welcome back</p>
                    <p class="auth-subtext">Sign in to continue to Indrive.</p>

                    <form class="auth-form" action="handlers/login.php" method="post" novalidate>
                        <div class="form-group">
                            <label for="login-email">Email</label>
                            <input type="email"
                                    class="form-control"
                                    id="login-email"
                                    name="email"
                                    placeholder="you@example.com"
                                    required
                                    autocomplete="email">
                        </div>

                        <div class="form-group">
                            <label for="login-password">Password</label>
                            <input type="password"
                                    class="form-control"
                                    id="login-password"
                                    name="password"
                                    placeholder="Enter your password"
                                    required
                                    autocomplete="current-password"
                                    minlength="8">
                        </div>

                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>

                        <button type="submit" class="btn-primary">Sign in</button>
                    </form>

                    <p style="text-align:center; margin-top:1.25rem;">
                        <a href="#" style="font-size:0.8125rem; color:var(--id-indigo); text-decoration:none;">Forgot your password?</a>
                    </p>
                </div>
                
                    <!-- ========================================
                        SIGNUP FORM
                    ======================================== -->
                <?php else: ?>
                <div id="panel-signup" role="tabpanel" aria-labelledby="tab-signup">
                    <p class="auth-greeting">Create your account</p>
                    <p class="auth-subtext">Join Indrive and get where you're going.</p>

                    <form class="auth-form" action="handlers/signup.php" method="post" novalidate>
                        <!-- Role Toggle -->
                        <div class="role-toggle">
                            <button type="button" class="role-toggle-btn active" data-role="rider" onclick="setRole('rider')">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                                I need a ride
                            </button>
                            <button type="button" class="role-toggle-btn" data-role="driver" onclick="setRole('driver')">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 16H9m10 0h3v-3.15a1 1 0 0 0-.84-.99L16 11l-2.7-6A1 1 0 0 0 12.38 4H5.62a1 1 0 0 0-.99.88L3 12v3a1 1 0 0 0 1 1h2m8 0H9m5 0a2 2 0 1 0 4 0m-4 0h4"/></svg>
                                I want to drive
                            </button>
                            <input type="hidden" name="role" id="role-input" value="rider">
                        </div>

                        <!-- Basic Fields -->
                        <div class="form-row">
                            <div class="form-group" style="flex:1;">
                                <label for="signup-first-name">First name</label>
                                <input type="text"
                                        class="form-control"
                                        id="signup-first-name"
                                        name="first_name"
                                        placeholder="Jane"
                                        required
                                        autocomplete="given-name">
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label for="signup-last-name">Last name</label>
                                <input type="text"
                                        class="form-control"
                                        id="signup-last-name"
                                        name="last_name"
                                        placeholder="Doe"
                                        required
                                        autocomplete="family-name">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="signup-email">Email</label>
                            <input type="email"
                                    class="form-control"
                                    id="signup-email"
                                    name="email"
                                    placeholder="you@example.com"
                                    required
                                    autocomplete="email">
                        </div>

                        <div class="form-group">
                            <label for="signup-phone">Phone</label>
                            <input type="tel"
                                    class="form-control"
                                    id="signup-phone"
                                    name="phone"
                                    placeholder="+1234567890"
                                    required
                                    autocomplete="tel">
                        </div>

                        <div class="form-group">
                            <label for="signup-password">Password</label>
                            <input type="password"
                                    class="form-control"
                                    id="signup-password"
                                    name="password"
                                    placeholder="Min. 8 characters"
                                    required
                                    autocomplete="new-password"
                                    minlength="8">
                        </div>

                        <div class="form-group">
                            <label for="signup-confirm">Confirm password</label>
                            <input type="password"
                                    class="form-control"
                                    id="signup-confirm"
                                    name="confirm_password"
                                    placeholder="Repeat your password"
                                    required
                                    autocomplete="new-password"
                                    minlength="8">
                        </div>

                        <!-- Driver Fields (hidden by default) -->
                        <div id="driver-fields" class="driver-fields">
                            <div class="driver-fields-divider">
                                <span>Driver details</span>
                            </div>

                            <div class="form-group">
                                <label for="signup-license">License number</label>
                                <input type="text"
                                        class="form-control"
                                        id="signup-license"
                                        name="license_number"
                                        placeholder="DL-12345678">
                            </div>

                            <div class="form-group">
                                <label for="signup-license-expiry">License expiry</label>
                                <input type="date"
                                        class="form-control"
                                        id="signup-license-expiry"
                                        name="license_expiry">
                            </div>

                            <div class="driver-fields-divider">
                                <span>Vehicle</span>
                            </div>

                            <div class="form-row">
                                <div class="form-group" style="flex:1;">
                                    <label for="signup-make">Make</label>
                                    <input type="text"
                                            class="form-control"
                                            id="signup-make"
                                            name="vehicle_make"
                                            placeholder="Toyota">
                                </div>
                                <div class="form-group" style="flex:1;">
                                    <label for="signup-model">Model</label>
                                    <input type="text"
                                            class="form-control"
                                            id="signup-model"
                                            name="vehicle_model"
                                            placeholder="Camry">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group" style="flex:1;">
                                    <label for="signup-year">Year</label>
                                    <input type="number"
                                            class="form-control"
                                            id="signup-year"
                                            name="vehicle_year"
                                            placeholder="2022"
                                            min="1900"
                                            max="2030">
                                </div>
                                <div class="form-group" style="flex:1;">
                                    <label for="signup-color">Color</label>
                                    <input type="text"
                                            class="form-control"
                                            id="signup-color"
                                            name="vehicle_color"
                                            placeholder="White">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="signup-plate">License plate</label>
                                <input type="text"
                                        class="form-control"
                                        id="signup-plate"
                                        name="license_plate"
                                        placeholder="ABC-1234">
                            </div>

                            <div class="form-group">
                                <label for="signup-vehicle-type">Vehicle type</label>
                                <select class="form-control" id="signup-vehicle-type" name="vehicle_type">
                                    <option value="economy">Economy</option>
                                    <option value="comfort">Comfort</option>
                                    <option value="premium">Premium</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="agree-terms" name="agree_terms" value="1" required>
                            <label class="form-check-label" for="agree-terms">
                                I agree to the <a href="#">Terms</a> &amp; <a href="#">Privacy Policy</a>
                            </label>
                        </div>

                        <button type="submit" class="btn-primary" id="signup-submit">Create account</button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Footer -->
                <div class="auth-footer">
                    <p>By continuing, you agree to our <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>.</p>
                </div>

            </div>
        </section>

        <!-- Visual Panel -->
        <section class="auth-visual-panel" aria-hidden="true">
            <div class="route-lines">
                <div class="route-path"></div>
                <div class="route-path"></div>
                <div class="route-path"></div>
                <div class="route-path"></div>
                <div class="route-path"></div>
            </div>
            <div class="route-nodes">
                <span class="route-node"></span>
                <span class="route-node"></span>
                <span class="route-node"></span>
                <span class="route-node"></span>
                <span class="route-node"></span>
                <span class="route-node"></span>
            </div>
            <div class="auth-visual-tagline">
                <h2>Where you're going,<br>we're going</h2>
                <p>Affordable rides at the price you choose. Fast, fair, and always on your side.</p>
            </div>
        </section>

    </main>

    <script>
    function setRole(role) {
        document.getElementById('role-input').value = role;
        var btns = document.querySelectorAll('.role-toggle-btn');
        btns.forEach(function(btn) {
            btn.classList.toggle('active', btn.getAttribute('data-role') === role);
        });
        var df = document.getElementById('driver-fields');
        if (df) {
            df.classList.toggle('open', role === 'driver');
        }
        var submit = document.getElementById('signup-submit');
        if (submit) {
            submit.textContent = role === 'driver' ? 'Apply as driver' : 'Create account';
        }
    }
    </script>

</body>
</html>
