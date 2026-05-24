<?php 
require_once __DIR__ . "/pl-load.php";
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/session.php";
$err = isset($_GET['err']) && is_numeric($_GET['err']) ? (int)$_GET['err'] : 404;
$msg = isset($_GET['msg']) ? $_GET['msg'] : t('error.title');
$sitename = $msg;
?>

<!DOCTYPE html>
<html lang="<?= esc($GLOBALS['__pl_active_lang']) ?>">
<head>
    <?php include __DIR__ . "/includes/head.php";?>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Major+Mono+Display&display=swap');

        .text a {
            text-decoration: none;
            color: white;
            font-weight: 400;
            padding: 0.75%;
            transition: background-color 1.5s, color 1.5s;
        }

        .text a:hover {
            background-color: white;
            color: black;
        }

        p.error {
            font-size: 10rem;
            animation: back 2.5s infinite linear;
            text-align: center;
            color: var(--accent);
            line-height: 1;
            margin-bottom: 20px;
        }

        p.text {
            margin-bottom: 40px;
            justify-content: center;
            display: flex;
            align-items: center;
            text-align: center;
        }

        .text span {
            font-weight: 400;
            padding: 7px 12px;
            background-color: var(--accent); 
            color: var(--secondary);
        }

        @keyframes back {
            0% { transform: skewY(-3deg); }
            5% { transform: skewY(3deg); }
            10% { transform: skewY(-3deg); }
            15% { transform: skewY(3deg); }
            20% { transform: skewY(0deg); }
            100% { transform: skewY(0deg); }
        }

        #error-div {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . "/includes/sidebar.php";?>
    <main>
        <div style="padding: 0 10px; display: flex; align-items: center; height: calc(100vh - 200px);">
            <div id="error-div">
                <p class="error"><?= esc($err) ?></p>
                <p class="text"><span><?= esc($msg) ?></span></p>
                <p class="card-value" style="margin-bottom:10px; text-align: center;"><a href="index.php"><?= te('error.home') ?></a></p>
            </div>
        </div>
    </main>
    <?php include __DIR__ . "/includes/footer.php";?>
</body>
</html>
