<!DOCTYPE html>

<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Theme Settings - Shop Admin</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&amp;display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#1152d4",
                        "background-light": "#f6f6f8",
                        "background-dark": "#101622",
                    },
                    fontFamily: {
                        "display": ["Inter"]
                    },
                    borderRadius: { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px" },
                },
            },
        }
    </script>
    <style>
        .primary-glow {
            box-shadow: 0 0 15px rgba(37, 99, 235, 0.4);
        }

        body {
            font-family: 'Inter', sans-serif;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            display: none;
        }

        .alert.success {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
            display: block;
        }

        .alert.error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
            display: block;
        }

        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>
    <script>
        // Load customization data on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadCustomizationData();
        });

        function loadCustomizationData() {
            fetch('save_customization.php', {
                method: 'GET'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    populateForm(data.data);
                }
            })
            .catch(error => console.error('Error loading data:', error));
        }

        function populateForm(data) {
            document.getElementById('shop_name').value = data.shop_name || '';
            document.getElementById('shop_address').value = data.shop_address || '';
            document.getElementById('corner_radius').value = data.corner_radius || 'rounded';
            document.getElementById('primary_color_input').value = data.primary_color || '#1152d4';
            document.getElementById('primary_color_hex').value = data.primary_color || '#1152d4';
            document.getElementById('accent_color_input').value = data.accent_color || '#1152d4';
            document.getElementById('accent_color_hex').value = data.accent_color || '#1152d4';
            document.getElementById('welcome_heading').value = data.welcome_heading || '';
            document.getElementById('welcome_subtext').value = data.welcome_subtext || '';
        }

        function syncColorInput(inputId, hexId) {
            document.getElementById(inputId).addEventListener('input', function() {
                document.getElementById(hexId).value = this.value;
            });
            document.getElementById(hexId).addEventListener('input', function() {
                if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                    document.getElementById(inputId).value = this.value;
                }
            });
        }

        function submitForm(event) {
            event.preventDefault();
            
            const form = event.target;
            const submitBtn = form.querySelector('button[type="submit"]');
            const alertBox = document.getElementById('alert_box');
            
            // Disable submit button
            submitBtn.disabled = true;
            submitBtn.classList.add('loading');

            const formData = new FormData(form);

            fetch('save_customization.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alertBox.classList.remove('error', 'success');
                
                if (data.success) {
                    alertBox.classList.add('success');
                    alertBox.textContent = data.message;
                    alertBox.style.display = 'block';
                    // Reload data after successful save
                    setTimeout(() => loadCustomizationData(), 1000);
                } else {
                    alertBox.classList.add('error');
                    alertBox.textContent = data.message || 'An error occurred';
                    alertBox.style.display = 'block';
                }
                
                // Re-enable submit button
                submitBtn.disabled = false;
                submitBtn.classList.remove('loading');
            })
            .catch(error => {
                console.error('Error:', error);
                alertBox.classList.remove('success');
                alertBox.classList.add('error');
                alertBox.textContent = 'An error occurred while saving';
                alertBox.style.display = 'block';
                
                submitBtn.disabled = false;
                submitBtn.classList.remove('loading');
            });
        }

        // Initialize color sync on page load
        window.addEventListener('load', function() {
            syncColorInput('primary_color_input', 'primary_color_hex');
            syncColorInput('accent_color_input', 'accent_color_hex');
        });
    </script>
</head>

<body class="font-display text-slate-900 antialiased bg-slate-50">
    <div class="relative flex h-auto min-h-screen w-full flex-col group/design-root overflow-x-hidden">
        <div class="layout-container flex h-full grow flex-col">
            <!-- Top Navigation -->
            <header
                class="flex items-center justify-between whitespace-nowrap border-b border-solid border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-6 py-3 lg:px-10">

                <div class="flex items-center gap-4 text-primary">

                    <!-- BACK BUTTON -->
                    <a href="tenantlogin.php"
                        class="flex items-center justify-center w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 transition">
                        <span class="material-symbols-outlined text-slate-700">arrow_back</span>
                    </a>

                    <div class="size-8 bg-primary rounded-lg flex items-center justify-center text-white">
                        <span class="material-symbols-outlined">build</span>
                    </div>

                    <h2 class="text-slate-900 dark:text-slate-100 text-lg font-bold leading-tight tracking-[-0.015em]">
                        ShopAdmin Portal
                    </h2>

                </div>

                <div class="flex flex-1 justify-end gap-8 items-center">
                </div>

            </header>
            <main class="flex flex-1 overflow-hidden bg-slate-50">
                <!-- Left Sidebar Settings -->
                <aside
                    class="w-full flex flex-col border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-y-auto">
                    <div class="p-6 space-y-8 max-w-2xl mx-auto w-full">
                        <div>
                            <h1 class="text-2xl font-black text-slate-900 dark:text-slate-100 tracking-tight">Branding
                                Customizer</h1>
                            <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Configure how your customers see
                                your shop's portal.</p>
                        </div>

                        <!-- Alert Box -->
                        <div id="alert_box" class="alert"></div>

                        <!-- Form Sections -->
                        <form id="customization_form" onsubmit="submitForm(event)" enctype="multipart/form-data" class="space-y-6">
                            <!-- Shop Identity -->
                            <section class="space-y-4">
                                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400">Shop Identity</h3>
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Shop
                                        Name</label>
                                    <input
                                        id="shop_name"
                                        name="shop_name"
                                        class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:border-primary focus:ring-primary text-sm"
                                        type="text" value="" />
                                </div>
                                <div class="space-y-2 mt-2">
                                    <label class="text-sm font-semibold text-slate-700">Corner Radius</label>
                                    <select
                                        id="corner_radius"
                                        name="corner_radius"
                                        class="w-full rounded-xl border-slate-200 bg-white focus:border-primary focus:ring-0 text-sm py-3 px-4">
                                        <option value="sharp">Sharp</option>
                                        <option value="rounded" selected="">Rounded (8px)</option>
                                        <option value="pill">Full (Pill)</option>
                                    </select>
                                </div>
                                <div class="space-y-2 mt-4">
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Primary Color</label>
                                    <div class="flex items-stretch rounded-xl overflow-hidden border border-slate-200 focus-within:border-primary transition-colors bg-white">
                                        <input
                                            id="primary_color_input"
                                            type="color"
                                            class="w-16 h-12 border-none cursor-pointer"
                                            value="#1152d4" />
                                        <input
                                            id="primary_color_hex"
                                            name="primary_color"
                                            class="flex-1 border-none text-slate-900 px-4 py-3 focus:ring-0 text-sm bg-transparent"
                                            type="text"
                                            value="#1152d4"
                                            placeholder="Hex color code" />
                                    </div>
                                </div>
                                <div class="space-y-2 mt-4">
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Accent Color</label>
                                    <div class="flex items-stretch rounded-xl overflow-hidden border border-slate-200 focus-within:border-primary transition-colors bg-white">
                                        <input
                                            id="accent_color_input"
                                            type="color"
                                            class="w-16 h-12 border-none cursor-pointer"
                                            value="#1152d4" />
                                        <input
                                            id="accent_color_hex"
                                            name="accent_color"
                                            class="flex-1 border-none text-slate-900 px-4 py-3 focus:ring-0 text-sm bg-transparent"
                                            type="text"
                                            value="#1152d4"
                                            placeholder="Hex color code" />
                                    </div>
                                </div>
                                    <div
                                        class="flex items-stretch rounded-xl overflow-hidden border border-slate-200 focus-within:border-primary transition-colors bg-white">
                                        <div
                                            class="flex items-center justify-center bg-slate-100 px-3 border-r border-slate-200">
                                            <span
                                                class="material-symbols-outlined text-slate-400 text-xl">location_on</span>
                                        </div>
                                        <input
                                            id="shop_address"
                                            name="shop_address"
                                            class="w-full border-none text-slate-900 px-4 py-3 focus:ring-0 text-sm bg-transparent"
                                            placeholder="Enter your shop's address" type="text"
                                            value="" />
                                    </div>
                                </div>
                            </section>
                            <!-- Messaging -->
                            <section class="space-y-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400">Portal Messaging
                                </h3>
                                <div class="space-y-2"><label class="text-sm font-semibold text-slate-700">Welcome
                                        Heading</label>
                                    <div
                                        class="flex items-stretch rounded-xl overflow-hidden border border-slate-200 focus-within:border-primary transition-colors bg-white">
                                        <div
                                            class="flex items-center justify-center bg-slate-100 px-3 border-r border-slate-200">
                                            <span class="material-symbols-outlined text-slate-400 text-xl">title</span>
                                        </div>
                                        <input
                                            id="welcome_heading"
                                            name="welcome_heading"
                                            class="w-full border-none text-slate-900 px-4 py-3 focus:ring-0 text-sm bg-transparent"
                                            type="text" value="" />
                                    </div>
                                </div>
                                <div class="space-y-2"><label class="text-sm font-semibold text-slate-700">Welcome
                                        Subtext</label>
                                    <textarea
                                        id="welcome_subtext"
                                        name="welcome_subtext"
                                        class="w-full rounded-xl border-slate-200 bg-white focus:border-primary focus:ring-0 text-sm p-4"
                                        rows="3"></textarea>
                                </div>
                            </section>
                            <!-- Assets -->
                            <section class="space-y-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400">Visual Assets</h3>
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Shop
                                        Logo</label>
                                    <div class="flex items-center justify-center w-full">
                                        <label
                                            class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-slate-300 dark:border-slate-700 rounded-lg cursor-pointer bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 transition-colors rounded-xl border-slate-200 bg-white">
                                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                                <span
                                                    class="material-symbols-outlined text-slate-400 mb-2">upload_file</span>
                                                <p class="text-xs text-slate-500">PNG or SVG (Max 2MB)</p>
                                            </div>
                                            <input id="logo" name="logo" class="hidden" type="file" accept="image/*" />
                                        </label>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Portal
                                        Hero Image</label>
                                    <div class="relative group rounded-lg overflow-hidden h-40">
                                        <div
                                            class="absolute inset-0 bg-slate-900/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity z-10">
                                            <label
                                                class="flex items-center gap-2 bg-primary text-white px-6 py-2.5 rounded-xl font-bold text-sm shadow-lg primary-glow hover:scale-105 transition-transform cursor-pointer">
                                                <span class="material-symbols-outlined text-xl">upload</span>
                                                Change Hero Image
                                                <input id="hero_image" name="hero_image" class="hidden" type="file" accept="image/*" />
                                            </label>
                                        </div>
                                        <div class="absolute top-3 right-3 z-20">
                                            <div
                                                class="bg-white/90 backdrop-blur p-2 rounded-lg text-slate-700 shadow-sm opacity-100 group-hover:opacity-0 transition-opacity">
                                                <span class="material-symbols-outlined block">edit</span>
                                            </div>
                                        </div>
                                        <img id="hero_preview" class="w-full h-full object-cover"
                                            data-alt="Modern car repair shop interior with clean tools"
                                            src="https://lh3.googleusercontent.com/aida-public/AB6AXuDSegrbShYyM64WxJ8okS0ff5yA9nm2lb7E8Ww8uOx-WxDfuCqO14ve2yA2fIhY-pJZcCRwoe5QwqcsH_RCKkRCl8HGMPCrv_-OmHh75QNnnOyVe2ArnbPLsS-j-6sAHidVirdVW7A_wJd9jfpympPMjpD6XwAqJPxQ7Qz7s4jWchmvJpt0vQsQFHRMNJL0eX17tJBgHbD098yAUFGDDD1ImCQ5HNdiaFH0F-8ITNDOYv7V3a4Fq0sheXWovPJc9I07FJhMLT13LL6J" />
                                    </div>
                                </div>
                            </section>
                        </form>
                    <!-- Actions Footer -->
                    <div
                        class="sticky bottom-0 mt-auto p-6 bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 flex gap-3 max-w-2xl mx-auto w-full justify-center max-w-2xl mx-auto w-full border-x lg:border-t-0">
                        <button
                            type="submit"
                            form="customization_form"
                            class="flex-1 bg-primary text-white font-bold py-2.5 rounded-lg hover:brightness-110 transition-all shadow-lg shadow-primary/20 h-12 rounded-xl primary-glow text-lg">Save
                            Changes</button>
                        <button
                            type="reset"
                            form="customization_form"
                            class="px-4 py-2.5 border border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-400 font-medium rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors h-12 rounded-xl">Reset</button>
                    </div>
                </aside>
                <!-- Right Side Preview -->
            </main>
        </div>
    </div>
</body>

</html>