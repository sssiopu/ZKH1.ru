<?php
if (!isset($user)) {
    $user = getCurrentUser();
}

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<header>
    <div class="header-content">
        <a href="index.php" class="logo">
            <div class="logo-icon">
                <i class="fas fa-building"></i>
            </div>
            <span>ДомУчет</span>
        </a>
        
        <nav>
            <ul>
                <li>
                    <a href="index.php" class="<?= $currentPage === 'index' ? 'active' : '' ?>">
                        <i class="fas fa-home"></i> Главная
                    </a>
                </li>
                <li>
                    <a href="meters.php" class="<?= $currentPage === 'meters' ? 'active' : '' ?>">
                        <i class="fas fa-tachometer-alt"></i> Счетчики
                    </a>
                </li>
                <li>
                    <a href="invoices.php" class="<?= $currentPage === 'invoices' ? 'active' : '' ?>">
                        <i class="fas fa-file-invoice-dollar"></i> Квитанции
                    </a>
                </li>
                <li>
                    <a href="incidents.php" class="<?= $currentPage === 'incidents' ? 'active' : '' ?>">
                        <i class="fas fa-tools"></i> Заявки
                    </a>
                </li>
                <?php if (hasRole('admin')): ?>
                    <li>
                        <a href="admin/index.php" class="<?= strpos($_SERVER['PHP_SELF'], 'admin') !== false ? 'active' : '' ?>">
                            <i class="fas fa-cog"></i> Администрирование
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        
        <a href="profile.php" class="user-profile">
            <?php if ($user['avatar_path']): ?>
                <img src="<?= UPLOAD_URL . escape($user['avatar_path']) ?>" alt="Avatar" class="user-avatar">
            <?php else: ?>
                <div class="user-avatar" style="background: var(--gray-300); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-user" style="color: var(--gray-600);"></i>
                </div>
            <?php endif; ?>
            <div>
                <div style="font-weight: 600;"><?= escape($user['full_name']) ?></div>
                <div style="font-size: 0.85rem; opacity: 0.9;">
                    <?php
                    $roleNames = [
                        'admin' => 'Администратор',
                        'resident' => 'Жилец'
                    ];
                    echo $roleNames[$user['role_name']] ?? $user['role_name'];
                    ?>
                </div>
            </div>
        </a>
    </div>
</header>
