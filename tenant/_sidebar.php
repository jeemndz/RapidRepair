<?php
/**
 * Reusable Sidebar Component
 * Include this file in all tenant admin pages
 * Required variables: $shopName, $shopQuery, $loginSlug, $accessibleModules, $loggedInUserName, $loggedInUserRole
 */

// Define primary color (can be customized per tenant)
$primaryColor = '#1152d4'; // Default primary blue
?>

<aside class="w-64 flex-shrink-0 border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 h-screen sticky top-0 flex flex-col overflow-y-auto">
    <div class="p-6 flex-1">
        <div class="flex items-center gap-3 mb-8">
            <div class="rounded-lg p-2 text-white" style="background-color: <?php echo $primaryColor; ?>">
                <span class="material-symbols-outlined">directions_car</span>
            </div>
            <div>
                <h1 class="text-lg font-bold leading-none"><?php echo h($shopName); ?></h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1"></p>
            </div>
        </div>
        <nav class="space-y-1">
            <?php if (canAccessModule('dashboardadmin.php', $accessibleModules)): ?>
            <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors font-medium" href="dashboardadmin.php?shop=<?php echo $shopQuery; ?>"><span class="material-symbols-outlined text-[22px]">dashboard</span>Dashboard</a>
            <?php endif; ?>
            <?php if (canAccessModule('repairjobsadmin.php', $accessibleModules)): ?>
            <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors font-medium <?php echo basename($_SERVER['PHP_SELF']) === 'repairjobsadmin.php' ? 'bg-primary/10 text-primary dark:bg-primary/20 dark:text-primary' : ''; ?>" href="repairjobsadmin.php?shop=<?php echo $shopQuery; ?>"><span class="material-symbols-outlined text-[22px]">build</span>Repair Jobs</a>
            <?php endif; ?>
            <?php if (canAccessModule('vehicleadmin.php', $accessibleModules)): ?>
            <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors <?php echo basename($_SERVER['PHP_SELF']) === 'vehicleadmin.php' ? 'bg-primary/10 text-primary dark:bg-primary/20 dark:text-primary' : ''; ?>" href="vehicleadmin.php?shop=<?php echo $shopQuery; ?>"><span class="material-symbols-outlined text-[22px]">directions_car</span>Vehicles</a>
            <?php endif; ?>
            <?php if (canAccessModule('appointmentadmin.php', $accessibleModules)): ?>
            <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors <?php echo basename($_SERVER['PHP_SELF']) === 'appointmentadmin.php' ? 'bg-primary/10 text-primary dark:bg-primary/20 dark:text-primary' : ''; ?>" href="appointmentadmin.php?shop=<?php echo $shopQuery; ?>"><span class="material-symbols-outlined text-[22px]">event</span>Appointments</a>
            <?php endif; ?>
            <?php if (canAccessModule('reportsadmin.php', $accessibleModules)): ?>
            <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" href="reportsadmin.php?shop=<?php echo $shopQuery; ?>"><span class="material-symbols-outlined text-[22px]">description</span>Reports</a>
            <?php endif; ?>
            <?php if (canAccessModule('inventoryadmin.php', $accessibleModules)): ?>
            <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" href="inventoryadmin.php?shop=<?php echo $shopQuery; ?>"><span class="material-symbols-outlined text-[22px]">inventory_2</span>Inventory</a>
            <?php endif; ?>
            <?php if (canAccessModule('customeradmin.php', $accessibleModules)): ?>
            <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors <?php echo basename($_SERVER['PHP_SELF']) === 'customeradmin.php' ? 'bg-primary/10 text-primary dark:bg-primary/20 dark:text-primary' : ''; ?>" href="customeradmin.php?shop=<?php echo $shopQuery; ?>"><span class="material-symbols-outlined text-[22px]">group</span>Customers</a>
            <?php endif; ?>
            <?php if (canAccessModule('paymentsadmin.php', $accessibleModules)): ?>
            <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" href="paymentsadmin.php?shop=<?php echo $shopQuery; ?>"><span class="material-symbols-outlined text-[22px]">payments</span>Payments</a>
            <?php endif; ?>
            <div class="pt-4 mt-4 border-t border-slate-100 dark:border-slate-800">
                <?php if (canAccessModule('accountbillingadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" href="accountbillingadmin.php?shop=<?php echo $shopQuery; ?>"><span class="material-symbols-outlined text-[22px]">receipt_long</span>Account Billing</a>
                <?php endif; ?>
                <?php if (canAccessModule('settingsadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors <?php echo basename($_SERVER['PHP_SELF']) === 'settingsadmin.php' ? 'bg-primary/10 text-primary dark:bg-primary/20 dark:text-primary' : ''; ?>" href="settingsadmin.php?shop=<?php echo $shopQuery; ?>"><span class="material-symbols-outlined text-[22px]">settings</span>Settings</a>
                <?php endif; ?>
            </div>
        </nav>
    </div>
    <div class="p-4 border-t border-slate-200 dark:border-slate-800">
        <div class="flex items-center gap-3">
            <div class="size-10 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center overflow-hidden">
                <span class="material-symbols-outlined text-slate-500 dark:text-slate-400">person</span>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold truncate text-slate-900 dark:text-white"><?php echo h($loggedInUserName); ?></p>
                <p class="text-xs text-slate-500 dark:text-slate-400 truncate"><?php echo h($loggedInUserRole); ?></p>
            </div>
            <form id="logoutForm" method="post" action="../logout/logout.php" class="inline">
                <input type="hidden" name="action" value="confirm" />
                <input type="hidden" name="shop" value="<?php echo h($loginSlug); ?>" />
                <button type="submit" class="text-slate-400 hover:text-red-600 dark:hover:text-red-400 transition-colors" title="Logout">
                    <span class="material-symbols-outlined text-xl">logout</span>
                </button>
            </form>
        </div>
    </div>
</aside>
