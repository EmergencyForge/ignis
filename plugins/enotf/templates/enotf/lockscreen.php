<?php
/**
 * View: eNOTF PIN-Lockscreen
 *
 * @var bool $error
 * @var int  $pinLength
 * @var \PDO $pdo
 */

$prot_url = "https://" . SYSTEM_URL . "/enotf/index.php";
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = "Gesperrt &rsaquo; eNOTF";
    include dirname(__DIR__, 4) . '/assets/components/enotf/_head.php';
    ?>
    <style>
        .lockscreen-container {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .lockscreen-box {
            background: #333333;
            padding: 40px;
            border-radius: 0;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.5);
            max-width: 400px;
            width: 100%;
        }

        .lockscreen-icon {
            text-align: center;
            font-size: 4rem;
            color: var(--main-color);
            margin-bottom: 20px;
        }

        .lockscreen-title {
            text-align: center;
            color: #fff;
            margin-bottom: 30px;
            font-size: 1.5rem;
        }

        .pin-display {
            background: transparent;
            border: 2px solid #5f5f5f;
            color: #fff;
            font-size: 2rem;
            text-align: center;
            padding: 15px;
            margin-bottom: 20px;
            letter-spacing: 10px;
            font-family: monospace;
            min-height: 70px;
        }

        .pin-display.error {
            border-color: #d91425;
            animation: shake 0.5s;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-10px);
            }

            75% {
                transform: translateX(10px);
            }
        }

        .keypad-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .keypad-btn {
            background: #474747;
            color: #fff;
            border: none;
            border-radius: 0;
            padding: 20px;
            font-size: 1.8rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
        }

        .keypad-btn:hover {
            background: #5f5f5f;
        }

        .keypad-btn:active {
            transform: scale(0.95);
        }

        .keypad-btn.wide {
            grid-column: span 1;
        }

        .error-message {
            color: #d91425;
            text-align: center;
            margin-top: 15px;
            font-size: 1rem;
        }
    </style>
</head>

<body data-bs-theme="dark" style="overflow-x:hidden">
    <div class="container-fluid" id="edivi__container">
        <div class="lockscreen-container">
            <div class="lockscreen-box">
                <div class="lockscreen-icon">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <h2 class="lockscreen-title">System gesperrt</h2>
                <p style="text-align: center; color: #a2a2a2; margin-bottom: 20px;">
                    Bitte PIN eingeben
                </p>

                <form method="post" id="pinForm">
                    <div class="pin-display <?= !empty($error) ? 'error' : '' ?>" id="pinDisplay">

                    </div>
                    <input type="hidden" name="pin" id="pinInput" value="">

                    <div class="keypad-grid">
                        <button type="button" class="keypad-btn" onclick="addDigit('1')">1</button>
                        <button type="button" class="keypad-btn" onclick="addDigit('2')">2</button>
                        <button type="button" class="keypad-btn" onclick="addDigit('3')">3</button>

                        <button type="button" class="keypad-btn" onclick="addDigit('4')">4</button>
                        <button type="button" class="keypad-btn" onclick="addDigit('5')">5</button>
                        <button type="button" class="keypad-btn" onclick="addDigit('6')">6</button>

                        <button type="button" class="keypad-btn" onclick="addDigit('7')">7</button>
                        <button type="button" class="keypad-btn" onclick="addDigit('8')">8</button>
                        <button type="button" class="keypad-btn" onclick="addDigit('9')">9</button>

                        <button type="button" class="keypad-btn" onclick="deleteDigit()">
                            <i class="fa-solid fa-backspace"></i>
                        </button>
                        <button type="button" class="keypad-btn" onclick="addDigit('0')">0</button>
                        <button type="button" class="keypad-btn" style="background: var(--main-color);" onclick="submitPin()">
                            <i class="fa-solid fa-check"></i>
                        </button>
                    </div>
                </form>

                <?php if (!empty($error)): ?>
                    <div class="error-message">
                        <i class="fa-solid fa-exclamation-triangle"></i> Falsche PIN
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        let pinValue = '';
        const maxLength = <?= $pinLength ?>;

        function updateDisplay() {
            const display = document.getElementById('pinDisplay');
            if (pinValue.length === 0) {
                display.textContent = '';
            } else {
                display.textContent = '•'.repeat(pinValue.length);
            }
            document.getElementById('pinInput').value = pinValue;
        }

        function addDigit(digit) {
            if (pinValue.length < maxLength) {
                pinValue += digit;
                updateDisplay();

                // Automatisch absenden wenn PIN vollständig
                if (pinValue.length === maxLength) {
                    setTimeout(() => submitPin(), 300);
                }
            }
        }

        function deleteDigit() {
            if (pinValue.length > 0) {
                pinValue = pinValue.slice(0, -1);
                updateDisplay();
            }
        }

        function submitPin() {
            if (pinValue.length === maxLength) {
                const display = document.getElementById('pinDisplay');
                display.style.opacity = '0.5';
                document.getElementById('pinForm').submit();
            }
        }

        document.addEventListener('keydown', function(e) {
            if (e.key >= '0' && e.key <= '9') {
                addDigit(e.key);
            } else if (e.key === 'Backspace') {
                e.preventDefault();
                deleteDigit();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                submitPin();
            }
        });

        <?php if (!empty($error)): ?>
            setTimeout(() => {
                document.getElementById('pinDisplay').classList.remove('error');
                pinValue = '';
                updateDisplay();
            }, 1000);
        <?php endif; ?>
    </script>
</body>

</html>