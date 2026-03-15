<?php
include "db.php";

if (isset($_POST['createTenant'])) {

    $shopName = $_POST['shopName'];
    $shopAddress = $_POST['shopAddress'];
    $ownerName = $_POST['ownerName'];
    $email = $_POST['email'];
    $contactNumber = $_POST['contactNumber'];

    // GET LAST TENANT ID
    $getID = mysqli_query($conn, "SELECT tenantID FROM owners ORDER BY tenantID DESC LIMIT 1");

    if (mysqli_num_rows($getID) > 0) {
        $row = mysqli_fetch_assoc($getID);
        $lastID = (int) $row['tenantID'];
        $newID = $lastID + 1;
    } else {
        $newID = 1;
    }

    // FORMAT TO 001,002,003
    $tenantID = str_pad($newID, 3, "0", STR_PAD_LEFT);

    $query = "INSERT INTO owners 
    (tenantID, ownerName, shopName, email, contactNumber, shopAddress)
    VALUES 
    ('$tenantID','$ownerName','$shopName','$email','$contactNumber','$shopAddress')";

    mysqli_query($conn, $query);

    header("Location: superadd.php");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>Superadmin Tenant Dashboard</title>

    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap"
        rel="stylesheet" />

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: "#1152d4"
                    }
                }
            }
        }
    </script>

    <style>
        body {
            font-family: Inter
        }
    </style>

</head>

<body class="bg-slate-100">

    <div class="flex min-h-screen">

        <!-- SIDEBAR -->

        <aside class="w-64 bg-white border-r p-6">

            <div class="flex items-center gap-3 mb-8">
                <span class="material-symbols-outlined text-primary text-3xl">build</span>
                <h1 class="text-xl font-bold">Rapid Repair</h1>
            </div>

            <nav class="space-y-2">

                <a class="flex items-center gap-3 px-3 py-2 bg-primary/10 text-primary rounded-lg font-semibold">
                    <span class="material-symbols-outlined">groups</span>
                    Tenants
                </a>

                <a class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">
                    <span class="material-symbols-outlined">monitoring</span>
                    System Health
                </a>

                <a class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">
                    <span class="material-symbols-outlined">credit_card</span>
                    Subscriptions
                </a>

                <a class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">
                    <span class="material-symbols-outlined">settings</span>
                    Settings
                </a>

            </nav>

        </aside>

        <!-- MAIN -->

        <main class="flex-1 p-8">

            <div class="flex justify-between items-center mb-8">

                <h1 class="text-3xl font-bold">Tenant Directory</h1>

                <button onclick="openModal()"
                    class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg">

                    <span class="material-symbols-outlined">add</span>
                    Add New Tenant

                </button>

            </div>

            <!-- TENANT TABLE -->

            <div class="bg-white rounded-xl shadow overflow-hidden">

                <table class="w-full text-left">

                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">

                        <tr>

                            <th class="px-6 py-4">Shop</th>
                            <th class="px-6 py-4">Owner</th>
                            <th class="px-6 py-4">Plan</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Created</th>
                            <th class="px-6 py-4 text-right">Actions</th>

                        </tr>

                    </thead>

                    <tbody class="divide-y">

                        <?php

                        $query = "SELECT * FROM owners ORDER BY tenantID DESC";
                        $result = mysqli_query($conn, $query);

                        while ($row = mysqli_fetch_assoc($result)) {

                            ?>

                            <tr class="hover:bg-slate-50">

                                <td class="px-6 py-4">

                                    <div class="flex items-center gap-3">

                                        <div
                                            class="size-9 rounded-lg bg-slate-100 flex items-center justify-center font-bold text-primary">
                                            <?php echo strtoupper(substr($row['shopName'], 0, 2)); ?>
                                        </div>

                                        <div>
                                            <p class="font-bold text-sm"><?php echo $row['shopName']; ?></p>
                                            <p class="text-xs text-gray-400">ID: <?php echo $row['tenantID']; ?></p>
                                        </div>

                                    </div>

                                </td>

                                <td class="px-6 py-4">

                                    <p class="text-sm"><?php echo $row['ownerName']; ?></p>
                                    <p class="text-xs text-gray-400"><?php echo $row['email']; ?></p>

                                </td>

                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded-full bg-primary/10 text-primary font-bold">
                                        Starter
                                    </span>
                                </td>

                                <td class="px-6 py-4">

                                    <div class="flex items-center gap-1">

                                        <span class="size-2 rounded-full bg-emerald-500"></span>
                                        <span class="text-sm">Active</span>

                                    </div>

                                </td>

                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?php echo date("M d, Y", strtotime($row['created_at'])); ?>
                                </td>

                                <td class="px-6 py-4 text-right">

                                    <button class="text-gray-400 hover:text-primary">
                                        <span class="material-symbols-outlined">more_vert</span>
                                    </button>

                                </td>

                            </tr>

                        <?php } ?>

                    </tbody>

                </table>

            </div>

        </main>

    </div>

    <!-- CREATE TENANT MODAL -->

    <!-- CREATE TENANT MODAL -->

    <div id="tenantModal"
        class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">

        <div class="bg-white w-full max-w-xl rounded-xl shadow-2xl border flex flex-col overflow-hidden">

            <!-- HEADER -->

            <div class="px-8 py-6 border-b flex justify-between items-center">

                <div>

                    <h2 class="text-xl font-bold">Create New Tenant</h2>

                    <p class="text-sm text-gray-500">
                        Onboard a new vendor to your platform
                    </p>

                </div>

                <button onclick="closeModal()" class="text-gray-400 hover:text-black">
                    <span class="material-symbols-outlined">close</span>
                </button>

            </div>

            <!-- FORM -->

            <form method="POST" class="p-8 flex flex-col gap-6">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <div class="flex flex-col gap-2">

                        <label class="text-xs font-bold uppercase text-gray-500">
                            Shop Name
                        </label>

                        <input name="shopName" class="border rounded-lg p-3" placeholder="Modern Boutique" required>

                    </div>

                    <div class="flex flex-col gap-2">

                        <label class="text-xs font-bold uppercase text-gray-500">
                            Shop Address
                        </label>

                        <input name="shopAddress" class="border rounded-lg p-3" placeholder="123 Main Street" required>

                    </div>

                    <div class="flex flex-col gap-2">

                        <label class="text-xs font-bold uppercase text-gray-500">
                            Owner Name
                        </label>

                        <input name="ownerName" class="border rounded-lg p-3" placeholder="Juan Dela Cruz" required>

                    </div>

                    <div class="flex flex-col gap-2">

                        <label class="text-xs font-bold uppercase text-gray-500">
                            Email
                        </label>

                        <input name="email" type="email" class="border rounded-lg p-3" placeholder="owner@email.com"
                            required>

                    </div>

                    <div class="flex flex-col gap-2 md:col-span-2">

                        <label class="text-xs font-bold uppercase text-gray-500">
                            Contact Number
                        </label>

                        <input name="contactNumber" class="border rounded-lg p-3" placeholder="09123456789">

                    </div>

                </div>

                <!-- LOGIN URL PREVIEW -->

                <div class="bg-slate-50 border rounded-lg p-4 flex items-center gap-2">

                    <span class="material-symbols-outlined text-primary">
                        link
                    </span>

                    <span class="text-sm text-gray-500 italic">
                        Login URL will be generated automatically
                    </span>

                </div>

                <!-- BUTTONS -->

                <div class="flex gap-4">

                    <button type="button" onclick="closeModal()" class="flex-1 border rounded-lg py-3">

                        Cancel

                    </button>

                    <button name="createTenant" class="flex-1 bg-primary text-white rounded-lg py-3">

                        Create Tenant

                    </button>

                </div>

            </form>

        </div>

    </div>

    <script>

        function openModal() {
            document.getElementById("tenantModal").classList.remove("hidden");
        }

        function closeModal() {
            document.getElementById("tenantModal").classList.add("hidden");
        }

    </script>

</body>

</html>