<?php

require_once __DIR__ . '/inc/db.php';

if (!setup_complete()) {
    header('Location: setup.php');
    exit;
}

require_once __DIR__ . '/inc/auth.php';

if (current_user()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $now = time();
    $until = (int)($_SESSION['login_block_until'] ?? 0);

    if ($until > $now) {

        $error = 'Too many attempts. Please wait a few minutes.';

    } else {

        $username = trim($_POST['username'] ?? '');
        $pass = $_POST['password'] ?? '';

        $s = db()->prepare(
            'SELECT * FROM users WHERE username = ? LIMIT 1'
        );

        $s->execute([$username]);

        $u = $s->fetch();

        if (
            $u &&
            $u['active'] &&
            password_verify($pass, $u['password_hash'])
        ) {

            $_SESSION['login_failures'] = 0;
            unset($_SESSION['login_block_until']);

            login_user($u);

            db()->prepare(
                "INSERT INTO audit_log(user_id,action,detail)
                 VALUES(?,'login','Successful login')"
            )->execute([$u['id']]);

            header('Location: dashboard.php');
            exit;
        }

        $n = (int)($_SESSION['login_failures'] ?? 0) + 1;

        $_SESSION['login_failures'] = $n;

        if ($n >= 5) {

            $_SESSION['login_block_until'] = $now + 300;
            $_SESSION['login_failures'] = 0;
        }

        $error = 'Invalid username or password.';
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Login - <?= e(APP_NAME) ?></title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html,
body {
    width: 100%;
    min-height: 100%;
}

body {
    font-family: "Segoe UI", Arial, sans-serif;
    background: #062d50;
}


/* =====================================
   MAIN
===================================== */

.login-wrapper {
    width: 100%;
    min-height: 100vh;

    display: grid;
    grid-template-columns: 55% 45%;

    overflow: hidden;
}


/* =====================================
   LEFT SIDE
===================================== */

.login-left {
    min-height: 100vh;

    position: relative;

    color: #fff;

    background:
        linear-gradient(
            135deg,
            #05294a 0%,
            #073d6c 55%,
            #062a4a 100%
        );

    overflow: hidden;
}


/* Decorative building style */

.login-left::before {
    content: "";

    position: absolute;

    width: 60%;
    height: 82%;

    right: -5%;
    bottom: -8%;

    background:
        linear-gradient(
            145deg,
            rgba(13,71,115,.20),
            rgba(5,28,53,.80)
        );

    clip-path:
        polygon(
            42% 0,
            100% 14%,
            100% 100%,
            0 100%,
            0 32%
        );

    border-left:
        1px solid
        rgba(113,183,235,.14);
}


.login-left::after {
    content: "";

    position: absolute;

    inset: 0;

    background:
        linear-gradient(
            90deg,
            rgba(3,32,60,.12),
            rgba(2,24,46,.10)
        );

    pointer-events: none;
}


.left-content {
    min-height: 100vh;

    position: relative;
    z-index: 2;

    padding: 70px 65px;

    display: flex;
    flex-direction: column;
}


/* BASIK */

.company-name {
    font-size: 58px;

    font-weight: 900;

    letter-spacing: 4px;

    line-height: .95;
}


.company-name span {
    display: block;

    margin-top: 21px;

    font-size: 18px;

    font-weight: 500;

    letter-spacing: 8px;
}


.title-line {
    width: 88px;

    height: 2px;

    background: #65b9f5;

    margin-top: 30px;
}


.workspace-text {
    margin-top: 30px;

    color: #84a6ca;

    font-size: 18px;

    font-weight: 700;

    letter-spacing: 7px;
}


/* Main title */

.hero {
    margin-top: 125px;
}


.hero h1 {
    color: white;

    font-size: 48px;

    line-height: 1.08;

    font-weight: 750;
}


.hero h1 span {
    display: block;

    color: #62b4ff;
}


.hero p {
    margin-top: 25px;

    color: #bed0e4;

    font-size: 20px;
}


.hero p span {
    margin: 0 13px;

    color: #839db9;
}


/* Feature cards */

.features {
    margin-top: 48px;

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 34px;
}


.feature-card {
    color: white;

    font-size: 15px;

    font-weight: 600;

    line-height: 1.4;
}


.feature-icon {
    width: 75px;

    height: 72px;

    margin-bottom: 12px;

    display: flex;

    align-items: center;

    justify-content: center;

    border:
        1px solid
        rgba(100,185,245,.48);

    border-radius: 11px;

    background:
        rgba(42,109,159,.15);
}


.feature-icon svg {
    width: 36px;

    height: 36px;

    fill: none;

    stroke: #d7ecff;

    stroke-width: 1.8;

    stroke-linecap: round;

    stroke-linejoin: round;
}


/* left footer */

.left-footer {
    margin-top: auto;

    color: #6ebcff;

    font-size: 14px;

    font-weight: 600;

    letter-spacing: 5px;
}


.left-footer::before {
    content: "";

    display: block;

    width: 48px;

    height: 2px;

    margin-bottom: 27px;

    background: #71bcfa;
}


.left-footer span {
    display: block;

    margin-top: 20px;
}


/* =====================================
   RIGHT SIDE
===================================== */

.login-right {
    min-height: 100vh;

    padding: 10px 18px 10px 0;

    background:
        linear-gradient(
            90deg,
            #07375e,
            #e9eef4 42px
        );

    display: flex;

    align-items: center;

    justify-content: center;
}


.login-card {
    width: 100%;

    max-width: 690px;

    min-height:
        calc(100vh - 20px);

    padding:
        38px
        45px
        28px;

    background:
        linear-gradient(
            135deg,
            #ffffff,
            #fdfefe
        );

    border-radius: 18px;

    box-shadow:
        0 20px 50px
        rgba(2,35,63,.25);

    display: flex;

    flex-direction: column;
}


/* =====================================
   CSS BASIK LOGO
===================================== */

.basik-logo {
    width: 250px;

    margin:
        0 auto
        18px;

    text-align: center;
}


.login-brand-logo {
    display: block;
    width: 235px;
    max-width: 100%;
    height: auto;
    margin: 0 auto;
    object-fit: contain;
}

.logo-box {
    position: relative;

    height: 110px;

    display: flex;

    justify-content: center;

    align-items: center;
}


.logo-red {
    position: absolute;

    top: 0;

    width: 145px;

    height: 42px;

    border:
        10px solid #ec1c24;

    border-bottom: 0;

    border-radius:
        50px
        50px
        0
        0;
}


.logo-yellow {
    position: absolute;

    bottom: 0;

    width: 145px;

    height: 44px;

    border:
        10px solid #ffc400;

    border-top: 0;

    border-radius:
        0
        0
        50px
        50px;
}


.logo-name {
    position: relative;

    z-index: 3;

    font-size: 54px;

    font-weight: 900;

    color: #121212;

    letter-spacing: -3px;
}


.logo-homeware {
    position: absolute;

    z-index: 4;

    bottom: 19px;

    left: 0;
    right: 0;

    font-size: 14px;

    letter-spacing: 4px;

    color: #111;

    font-weight: 600;
}


.logo-tagline {
    margin-top: 8px;

    color: #171717;

    font-size: 15px;

    font-weight: 800;

    letter-spacing: 4px;
}


/* =====================================
   LOGIN TITLE
===================================== */

.login-title {
    margin-top: 17px;

    color: #082f59;

    text-align: center;

    font-size: 31px;

    font-weight: 800;
}


.login-subtitle {
    margin-top: 7px;

    margin-bottom: 29px;

    color: #516a89;

    text-align: center;

    font-size: 18px;
}


/* =====================================
   ERROR
===================================== */

.login-error {
    margin-bottom: 17px;

    padding: 12px 15px;

    border:
        1px solid
        #f5b8b8;

    border-radius: 8px;

    background: #fff2f2;

    color: #b91c1c;

    font-size: 14px;
}


/* =====================================
   INPUT
===================================== */

.input-box {
    position: relative;

    width: 100%;

    height: 66px;

    margin-bottom: 20px;

    display: flex;

    align-items: center;

    border:
        1px solid
        #d5dfeb;

    border-radius: 10px;

    background:
        linear-gradient(
            180deg,
            #fafcff,
            #f5f8fb
        );

    transition: .2s;
}


.input-box:focus-within {
    border-color: #1f74c7;

    background: white;

    box-shadow:
        0 0 0 3px
        rgba(31,116,199,.12);
}


.input-icon {
    width: 62px;

    flex-shrink: 0;

    display: flex;

    align-items: center;

    justify-content: center;
}


.input-icon svg {
    width: 26px;

    height: 26px;

    fill: none;

    stroke: #506a8c;

    stroke-width: 1.8;

    stroke-linecap: round;

    stroke-linejoin: round;
}


.input-box input {
    width: 100%;

    height: 100%;

    border: 0;

    outline: 0;

    background: transparent;

    color: #17395f;

    font-size: 18px;

    font-family: inherit;

    padding-right: 50px;
}


.input-box input::placeholder {
    color: #506a89;
}


.eye-button {
    position: absolute;

    right: 18px;

    top: 50%;

    transform:
        translateY(-50%);

    border: 0;

    background: transparent;

    cursor: pointer;

    padding: 4px;

    display: flex;
}


.eye-button svg {
    width: 26px;

    height: 26px;

    fill: none;

    stroke: #4f6684;

    stroke-width: 2;
}


/* =====================================
   OPTIONS
===================================== */

.login-options {
    margin:
        1px
        0
        27px;

    display: flex;

    align-items: center;

    justify-content: space-between;
}


.remember {
    display: flex;

    align-items: center;

    gap: 10px;

    cursor: pointer;

    color: #28496d;

    font-size: 16px;
}


.remember input {
    width: 20px;

    height: 20px;

    cursor: pointer;
}


.forgot {
    color: #0066ef;

    font-size: 16px;

    text-decoration: none;
}


.forgot:hover {
    text-decoration: underline;
}


/* =====================================
   LOGIN BUTTON
===================================== */

.login-button {
    width: 100%;

    height: 68px;

    border: 0;

    border-radius: 8px;

    background:
        linear-gradient(
            90deg,
            #073665,
            #07509a
        );

    color: #fff;

    font-family: inherit;

    font-size: 20px;

    font-weight: 700;

    cursor: pointer;

    display: flex;

    justify-content: center;

    align-items: center;

    gap: 20px;

    box-shadow:
        0 6px 14px
        rgba(4,65,120,.13);

    transition: .2s;
}


.login-button:hover {
    transform:
        translateY(-1px);

    box-shadow:
        0 9px 20px
        rgba(4,65,120,.22);
}


.login-button svg {
    width: 29px;

    height: 29px;

    fill: none;

    stroke: white;

    stroke-width: 2;
}


/* =====================================
   DIVIDER
===================================== */

.divider {
    margin:
        25px
        0
        20px;

    display: flex;

    align-items: center;

    gap: 16px;

    color: #536a87;
}


.divider span {
    height: 1px;

    flex: 1;

    background: #d7dfe8;
}


/* =====================================
   ROLES
===================================== */

.roles {
    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 17px;
}


.role {
    min-height: 67px;

    border:
        1px solid
        #d7e0eb;

    border-radius: 9px;

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 9px;

    background: #fafcff;

    font-size: 17px;

    font-weight: 500;
}


.role svg {
    width: 26px;

    height: 26px;
}


.role.admin {
    color: #0564ee;
}


.role.manager {
    color: #079572;
}


.role.employee {
    color: #5831e7;
}


/* =====================================
   FOOTER
===================================== */

.login-footer {
    margin-top: auto;

    padding-top: 44px;

    color: #486181;

    font-size: 14px;

    text-align: center;

    line-height: 1.8;
}


/* =====================================
   RESPONSIVE
===================================== */

@media (max-width: 1100px) {

    .login-wrapper {
        grid-template-columns:
            46% 54%;
    }

    .left-content {
        padding:
            50px
            35px;
    }

    .company-name {
        font-size: 44px;
    }

    .hero {
        margin-top: 90px;
    }

    .hero h1 {
        font-size: 37px;
    }

    .features {
        grid-template-columns:
            repeat(2,1fr);
    }

}


@media (max-width: 800px) {

    .login-wrapper {
        display: block;
    }

    .login-left {
        display: none;
    }

    .login-right {
        min-height: 100vh;

        padding: 15px;

        background:
            linear-gradient(
                135deg,
                #062e51,
                #075795
            );
    }

    .login-card {
        min-height:
            calc(100vh - 30px);

        padding:
            28px
            22px;

        border-radius: 16px;
    }

    .roles {
        grid-template-columns: 1fr;
    }

}


@media (max-width: 450px) {

    .basik-logo {
        transform: scale(.87);
    }

    .login-title {
        font-size: 27px;
    }

    .login-subtitle {
        font-size: 15px;
    }

}

</style>

</head>


<body>

<div class="login-wrapper">


    <!-- =====================================
         LEFT
    ====================================== -->

    <section class="login-left">

        <div class="left-content">

            <div class="company-name">

                BASIK

                <span>
                    INNOVATION LLP
                </span>

            </div>


            <div class="title-line"></div>


            <div class="workspace-text">
                FINANCE WORKSPACE
            </div>


            <div class="hero">

                <h1>

                    Receivables

                    <span>
                        Management System
                    </span>

                </h1>


                <p>

                    Track

                    <span>|</span>

                    Analyse

                    <span>|</span>

                    Manage

                    <span>|</span>

                    Grow

                </p>

            </div>



            <div class="features">


                <!-- DASHBOARD -->

                <div class="feature-card">

                    <div class="feature-icon">

                        <svg viewBox="0 0 24 24">

                            <path d="M4 20V10"></path>

                            <path d="M10 20V4"></path>

                            <path d="M16 20v-7"></path>

                            <path d="M22 20V7"></path>

                        </svg>

                    </div>

                    Real-Time<br>
                    Dashboard

                </div>



                <!-- CUSTOMER -->

                <div class="feature-card">

                    <div class="feature-icon">

                        <svg viewBox="0 0 24 24">

                            <path d="M6 2h9l4 4v16H6z"></path>

                            <path d="M14 2v5h5"></path>

                            <path d="M9 12h7"></path>

                            <path d="M9 16h7"></path>

                        </svg>

                    </div>

                    Customer<br>
                    Insights

                </div>



                <!-- COLLECTION -->

                <div class="feature-card">

                    <div class="feature-icon">

                        <svg viewBox="0 0 24 24">

                            <circle cx="12" cy="7" r="3"></circle>

                            <circle cx="5" cy="9" r="2"></circle>

                            <circle cx="19" cy="9" r="2"></circle>

                            <path d="M7 21v-2a5 5 0 0110 0v2"></path>

                            <path d="M1 20v-1a4 4 0 014-4"></path>

                            <path d="M23 20v-1a4 4 0 00-4-4"></path>

                        </svg>

                    </div>

                    Smart<br>
                    Collections

                </div>



                <!-- CLOUD -->

                <div class="feature-card">

                    <div class="feature-icon">

                        <svg viewBox="0 0 24 24">

                            <path
                                d="M17.5 19H6a4 4 0 01-.5-7.97A6.5 6.5 0 0118 9.5a4.5 4.5 0 01-.5 9.5z">
                            </path>

                        </svg>

                    </div>

                    Google Sheet<br>
                    Sync

                </div>


            </div>



            <div class="left-footer">

                BASIK INNOVATION LLP

                <span>
                    SIMPLY EFFICIENT FINANCE
                </span>

            </div>

        </div>

    </section>



    <!-- =====================================
         RIGHT
    ====================================== -->

    <section class="login-right">

        <div class="login-card">


            <!-- BASIK LOGO IMAGE -->

            <div class="basik-logo">

                <img
                    class="login-brand-logo"
                    src="assets/img/basik-login-logo.png"
                    alt="Basik Homeware"
                >

                <div class="logo-tagline">
                    SIMPLY EFFICIENT
                </div>

            </div>



            <h1 class="login-title">
                Welcome Back
            </h1>


            <div class="login-subtitle">

                Login to your Receivables Management System

            </div>



            <?php if ($error): ?>

                <div class="login-error">

                    <?= e($error) ?>

                </div>

            <?php endif; ?>



            <form
                method="post"
                id="loginForm"
            >


                <!-- USERNAME -->

                <div class="input-box">


                    <div class="input-icon">

                        <svg viewBox="0 0 24 24">

                            <circle
                                cx="12"
                                cy="7"
                                r="4">
                            </circle>

                            <path
                                d="M4 21a8 8 0 0116 0">
                            </path>

                        </svg>

                    </div>


                    <input
                        type="text"
                        name="username"
                        id="username"
                        placeholder="Username"
                        required
                        autocomplete="username"
                        autofocus
                    >

                </div>



                <!-- PASSWORD -->

                <div class="input-box">


                    <div class="input-icon">

                        <svg viewBox="0 0 24 24">

                            <rect
                                x="5"
                                y="11"
                                width="14"
                                height="10"
                                rx="2">
                            </rect>

                            <path
                                d="M8 11V7a4 4 0 018 0v4">
                            </path>

                        </svg>

                    </div>


                    <input
                        type="password"
                        name="password"
                        id="password"
                        placeholder="Password"
                        required
                        autocomplete="current-password"
                    >


                    <button
                        type="button"
                        class="eye-button"
                        id="togglePassword"
                        aria-label="Show Password"
                    >

                        <svg viewBox="0 0 24 24">

                            <path
                                d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z">
                            </path>

                            <circle
                                cx="12"
                                cy="12"
                                r="3">
                            </circle>

                        </svg>

                    </button>

                </div>



                <div class="login-options">


                    <label class="remember">

                        <input
                            type="checkbox"
                            id="rememberUsername"
                        >

                        Remember me

                    </label>


                    <a
                        class="forgot"
                        href="#"
                        onclick="forgotPassword(event)"
                    >
                        Forgot Password?
                    </a>


                </div>



                <button
                    type="submit"
                    class="login-button"
                >

                    <svg viewBox="0 0 24 24">

                        <path d="M5 12h14"></path>

                        <path d="M13 6l6 6-6 6"></path>

                    </svg>

                    Login

                </button>


            </form>



            <div class="divider">

                <span></span>

                or

                <span></span>

            </div>



            <div class="roles">


                <div class="role admin">

                    <svg
                        viewBox="0 0 24 24"
                        fill="currentColor"
                    >

                        <circle
                            cx="12"
                            cy="7"
                            r="4">
                        </circle>

                        <path
                            d="M4 21c0-5 3-8 8-8s8 3 8 8">
                        </path>

                    </svg>

                    Admin

                </div>



                <div class="role manager">

                    <svg
                        viewBox="0 0 24 24"
                        fill="currentColor"
                    >

                        <circle
                            cx="8"
                            cy="8"
                            r="3">
                        </circle>

                        <circle
                            cx="16"
                            cy="8"
                            r="3">
                        </circle>

                        <path
                            d="M1 21c0-5 3-8 7-8s7 3 7 8">
                        </path>

                        <path
                            d="M12 21c0-5 2-8 6-8s5 3 5 8">
                        </path>

                    </svg>

                    Manager

                </div>



                <div class="role employee">

                    <svg
                        viewBox="0 0 24 24"
                        fill="currentColor"
                    >

                        <circle
                            cx="12"
                            cy="7"
                            r="4">
                        </circle>

                        <path
                            d="M4 21c0-5 3-8 8-8s8 3 8 8">
                        </path>

                    </svg>

                    Employee

                </div>


            </div>



            <div class="login-footer">

                © <?= date('Y') ?>
                BASIK INNOVATION LLP.
                All rights reserved.

                <br>

                Finance &nbsp;|&nbsp;
                Operations &nbsp;|&nbsp;
                Growth

            </div>


        </div>

    </section>


</div>



<script>

/* PASSWORD SHOW / HIDE */

const password =
    document.getElementById('password');

const toggle =
    document.getElementById('togglePassword');


toggle.addEventListener(
    'click',
    function () {

        password.type =
            password.type === 'password'
                ? 'text'
                : 'password';

    }
);



/* REMEMBER USERNAME */

const username =
    document.getElementById('username');

const remember =
    document.getElementById('rememberUsername');


const saved =
    localStorage.getItem(
        'basik_receivable_username'
    );


if (saved) {

    username.value = saved;

    remember.checked = true;

}


document
    .getElementById('loginForm')
    .addEventListener(
        'submit',
        function () {

            if (remember.checked) {

                localStorage.setItem(
                    'basik_receivable_username',
                    username.value
                );

            } else {

                localStorage.removeItem(
                    'basik_receivable_username'
                );

            }

        }
    );



/* FORGOT PASSWORD */

function forgotPassword(e) {

    e.preventDefault();

    alert(
        'Please contact your Admin to reset your password.'
    );

}

</script>


</body>

</html>