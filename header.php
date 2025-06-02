
<body>
<header class="header">
    <div class="header-container">
        <a href="index.php" class="logo">World Travel</a>
        <nav class="header-nav">
            <ul class="nav-links">
                <li><a href="index.php" <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'class="active"' : ''; ?>>Главная</a></li>
                <li><a href="catalog.php" <?php echo basename($_SERVER['PHP_SELF']) == 'catalog.php' ? 'class="active"' : ''; ?>>Туры</a></li>
                <li><a href="about.php" <?php echo basename($_SERVER['PHP_SELF']) == 'about.php' ? 'class="active"' : ''; ?>>О нас</a></li>
                <li><a href="account.php" <?php echo basename($_SERVER['PHP_SELF']) == 'account.php' ? 'class="active"' : ''; ?>>Личный кабинет</a></li>
            </ul>
        </nav>
    </div>
</header>
