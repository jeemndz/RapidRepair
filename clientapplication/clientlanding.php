<!DOCTYPE html>

<html class="scroll-smooth" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Cobalt Precision | Operational Excellence</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
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
                        "tertiary-container": "#fef3c7",
                        "on-primary-container": "#1152d4",
                        "inverse-on-surface": "#f8fafc",
                        "on-error": "#ffffff",
                        "on-tertiary": "#ffffff",
                        "primary-fixed-dim": "#bfdbfe",
                        "primary-container": "#eef2ff",
                        "secondary-fixed": "#e2e8f0",
                        "error-container": "#fee2e2",
                        "surface-variant": "#f1f5f9",
                        "surface-container-low": "#ffffff",
                        "on-secondary-fixed": "#0f172a",
                        "inverse-primary": "#b4c5ff",
                        "on-surface": "#0f172a",
                        "on-background": "#0f172a",
                        "surface-dim": "#d9d9e4",
                        "on-secondary": "#ffffff",
                        "secondary-container": "#f1f5f9",
                        "on-tertiary-fixed": "#7c2d12",
                        "on-secondary-fixed-variant": "#334155",
                        "outline-variant": "#cbd5e1",
                        "surface-container-lowest": "#ffffff",
                        "error": "#ef4444",
                        "surface-tint": "#1152d4",
                        "secondary-fixed-dim": "#cbd5e1",
                        "on-error-container": "#991b1b",
                        "outline": "#e2e8f0",
                        "on-primary-fixed": "#1e3a8a",
                        "surface": "#f6f6f8",
                        "primary-fixed": "#dbeafe",
                        "primary": "#1152d4",
                        "tertiary-fixed-dim": "#fed7aa",
                        "on-surface-variant": "#64748b",
                        "surface-container": "#ffffff",
                        "secondary": "#475569",
                        "surface-bright": "#ffffff",
                        "on-tertiary-container": "#92400e",
                        "inverse-surface": "#1e293b",
                        "surface-container-high": "#ffffff",
                        "on-secondary-container": "#1e293b",
                        "on-primary-fixed-variant": "#1d4ed8",
                        "on-primary": "#ffffff",
                        "tertiary-fixed": "#ffedd5",
                        "tertiary": "#f59e0b",
                        "on-tertiary-fixed-variant": "#9a3412",
                        "background": "#f6f6f8",
                        "surface-container-highest": "#ffffff"
                    },
                    fontFamily: {
                        "headline": ["Inter"],
                        "body": ["Inter"],
                        "label": ["Inter"]
                    },
                    borderRadius: { "DEFAULT": "0.125rem", "lg": "0.25rem", "xl": "0.5rem", "full": "0.75rem" },
                },
            },
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f6f6f8;
            color: #0f172a;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        .clinical-shadow {
            shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }

        @keyframes point-right {

            0%,
            100% {
                transform: translateX(0);
            }

            50% {
                transform: translateX(-10px);
            }
        }

        .animate-point-right {
            animation: point-right 1.5s ease-in-out infinite;
        }
    </style>
</head>

<body class="bg-surface text-on-surface">
    <!-- TopNavBar Component -->
    <nav
        class="fixed top-0 w-full z-50 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-none">
        <div class="max-w-7xl mx-auto flex justify-between items-center px-6 py-3">
            <div class="text-xl font-black tracking-tighter text-[#1152d4] dark:text-[#3b82f6]">Cobalt Precision</div>
            <div class="hidden md:flex items-center gap-8">
                <a class="font-medium text-sm tracking-tight text-slate-600 dark:text-slate-400 hover:text-[#1152d4] transition-colors"
                    href="#features">Features</a>
                <a class="font-medium text-sm tracking-tight text-slate-600 dark:text-slate-400 hover:text-[#1152d4] transition-colors"
                    href="#pricing">Pricing</a>
                <a class="font-medium text-sm tracking-tight text-slate-600 dark:text-slate-400 hover:text-[#1152d4] transition-colors"
                    href="#about">About</a>
                <a class="font-medium text-sm tracking-tight text-slate-600 dark:text-slate-400 hover:text-[#1152d4] transition-colors"
                    href="#support">Support</a>
            </div>
            <button
                class="bg-primary text-on-primary px-5 py-2 rounded-lg text-sm font-bold tracking-tight hover:opacity-90 transition-all active:scale-95">Get
                Started</button>
        </div>
    </nav>
    <main class="pt-16">
        <!-- Hero Section & Shop Onboarding Form -->
        <section class="relative min-h-[921px] flex items-center overflow-hidden py-20 px-6">
            <div class="absolute inset-0 z-0 bg-gradient-to-br from-primary/5 to-transparent"></div>
            <div class="max-w-7xl mx-auto w-full grid grid-cols-1 lg:grid-cols-2 gap-16 items-center relative z-10">
                <!-- Left: Brand Message -->
                <div class="space-y-8">
                    <span
                        class="inline-block px-3 py-1 bg-primary-container text-primary text-[10px] font-bold tracking-widest uppercase rounded">Operational
                        Excellence</span>
                    <h1 class="text-5xl md:text-6xl font-black tracking-tighter leading-[1.1] text-on-background">
                        The Clinical Standard for <span class="text-primary">Modern Repair.</span>
                    </h1>
                    <p class="text-lg text-on-surface-variant max-w-lg leading-relaxed">
                        High-fidelity operational tools designed for professional shop owners. Move beyond legacy
                        systems with Cobalt's architectural rigor and real-time fleet management.
                    </p>
                    <div class="flex items-center gap-4 text-sm font-semibold text-on-surface">
                        <div class="flex -space-x-2">
                            <div class="w-8 h-8 rounded-full border-2 border-white bg-slate-200"
                                data-alt="User avatar 1"></div>
                            <div class="w-8 h-8 rounded-full border-2 border-white bg-slate-300"
                                data-alt="User avatar 2"></div>
                            <div class="w-8 h-8 rounded-full border-2 border-white bg-slate-400"
                                data-alt="User avatar 3"></div>
                        </div>
                        <span>Trusted by 500+ premium auto shops nationwide.</span>
                    </div>
                </div>
                <!-- Right: Onboarding Form (Screen 60) -->
                <div
                    class="bg-surface-container border border-outline rounded-xl p-8 shadow-sm relative ring-4 ring-primary/5">
                    <div
                        class="absolute -left-12 top-1/2 -translate-y-1/2 hidden xl:flex flex-col items-center gap-2 text-primary animate-point-right">
                        <span
                            class="text-[10px] font-black uppercase tracking-tighter rotate-90 whitespace-nowrap mb-4">Start
                            Here</span>
                        <span class="material-symbols-outlined text-4xl">arrow_forward</span>
                    </div>
                    <div class="mb-8">
                        <h2 class="text-2xl font-bold tracking-tight mb-2">Application Form</h2>
                        <p class="text-sm text-on-surface-variant">Initialize your digital operational environment.</p>
                    </div>
                    <form class="space-y-5">
                        <div class="grid grid-cols-1 gap-5">
                            <div class="space-y-1.5">
                                <label
                                    class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Shop
                                    Name</label>
                                <input
                                    class="w-full bg-surface-variant border-transparent rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm py-3 px-4"
                                    placeholder="e.g. Precision Euro Works" type="text" />
                            </div>
                            <div class="space-y-1.5">
                                <label
                                    class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Business
                                    Address</label>
                                <input
                                    class="w-full bg-surface-variant border-transparent rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm py-3 px-4"
                                    placeholder="Street, City, State, ZIP" type="text" />
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div class="space-y-1.5">
                                    <label
                                        class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Owner
                                        Name</label>
                                    <input
                                        class="w-full bg-surface-variant border-transparent rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm py-3 px-4"
                                        placeholder="Full Name" type="text" />
                                </div>
                                <div class="space-y-1.5">
                                    <label
                                        class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Phone
                                        Number</label>
                                    <input
                                        class="w-full bg-surface-variant border-transparent rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm py-3 px-4"
                                        placeholder="+1 (555) 000-0000" type="tel" />
                                </div>
                            </div>
                            <div class="space-y-1.5">
                                <label
                                    class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Email
                                    Address</label>
                                <input
                                    class="w-full bg-surface-variant border-transparent rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm py-3 px-4"
                                    placeholder="admin@shop.com" type="email" />
                            </div>
                        </div>
                        <button
                            class="w-full bg-primary text-white font-bold py-4 rounded-lg tracking-tight hover:bg-primary/90 transition-all mt-4"
                            type="submit">Complete Registration</button>
                    </form>
                </div>
            </div>
        </section>
        <!-- Platform Features -->
        <section class="py-24 bg-white" id="features">
            <div class="max-w-7xl mx-auto px-6">
                <div class="text-center max-w-2xl mx-auto mb-20">
                    <h2 class="text-3xl font-black tracking-tighter mb-4">Engineered for Precision</h2>
                    <p class="text-on-surface-variant">Every module is built with a focus on data integrity and operator
                        efficiency.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="p-8 border border-outline rounded-xl hover:border-primary/30 transition-all">
                        <div class="w-12 h-12 bg-primary-container flex items-center justify-center rounded-lg mb-6">
                            <span class="material-symbols-outlined text-primary"
                                data-icon="monitoring">monitoring</span>
                        </div>
                        <h3 class="text-xl font-bold mb-3 tracking-tight">Real-time Analytics</h3>
                        <p class="text-sm text-on-surface-variant leading-relaxed">Continuous data streaming provides
                            instant visibility into shop throughput, technician efficiency, and margin performance.</p>
                    </div>
                    <div class="p-8 border border-outline rounded-xl hover:border-primary/30 transition-all">
                        <div class="w-12 h-12 bg-primary-container flex items-center justify-center rounded-lg mb-6">
                            <span class="material-symbols-outlined text-primary"
                                data-icon="account_tree">account_tree</span>
                        </div>
                        <h3 class="text-xl font-bold mb-3 tracking-tight">Unified Fleet Management</h3>
                        <p class="text-sm text-on-surface-variant leading-relaxed">Architectural control over
                            multi-location operations. Sync inventory, staff, and billing across your entire network
                            effortlessly.</p>
                    </div>
                    <div class="p-8 border border-outline rounded-xl hover:border-primary/30 transition-all">
                        <div class="w-12 h-12 bg-primary-container flex items-center justify-center rounded-lg mb-6">
                            <span class="material-symbols-outlined text-primary"
                                data-icon="architecture">architecture</span>
                        </div>
                        <h3 class="text-xl font-bold mb-3 tracking-tight">Clinical Interface Design</h3>
                        <p class="text-sm text-on-surface-variant leading-relaxed">No clutter. No noise. A high-density
                            professional UI that prioritizes critical information for high-stakes decision making.</p>
                    </div>
                </div>
            </div>
        </section>
        <!-- Transparent Pricing -->
        <section class="py-24 bg-surface" id="pricing">
            <div class="max-w-7xl mx-auto px-6">
                <div class="text-center mb-16">
                    <h2 class="text-3xl font-black tracking-tighter mb-4">Scalable Architectures</h2>
                    <p class="text-on-surface-variant">Pricing tiers designed to grow with your operation.</p>
                </div>
                <div
                    class="grid grid-cols-1 md:grid-cols-3 gap-0 border border-outline rounded-xl overflow-hidden shadow-sm">
                    <!-- Starter -->
                    <div class="bg-white p-10 border-r border-outline flex flex-col">
                        <div class="mb-8">
                            <span
                                class="text-[10px] font-bold text-on-surface-variant tracking-widest uppercase">Starter</span>
                            <div class="mt-4 flex items-baseline gap-1">
                                <span class="text-4xl font-black tracking-tighter">$149</span>
                                <span class="text-on-surface-variant text-sm">/mo</span>
                            </div>
                        </div>
                        <ul class="space-y-4 mb-10 flex-grow">
                            <li class="flex items-center gap-3 text-sm"><span
                                    class="material-symbols-outlined text-primary text-lg" data-icon="check_circle"
                                    data-weight="fill" style="font-variation-settings: 'FILL' 1;">check_circle</span> 1
                                Location</li>
                            <li class="flex items-center gap-3 text-sm"><span
                                    class="material-symbols-outlined text-primary text-lg" data-icon="check_circle"
                                    data-weight="fill" style="font-variation-settings: 'FILL' 1;">check_circle</span> Up
                                to 5 Technicians</li>
                            <li class="flex items-center gap-3 text-sm"><span
                                    class="material-symbols-outlined text-primary text-lg" data-icon="check_circle"
                                    data-weight="fill" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                Basic Analytics</li>
                            <li class="flex items-center gap-3 text-sm text-on-surface-variant/50"><span
                                    class="material-symbols-outlined text-slate-300 text-lg"
                                    data-icon="cancel">cancel</span> Custom API</li>
                        </ul>
                        <button
                            class="w-full py-3 border-2 border-primary text-primary font-bold rounded-lg hover:bg-primary/5 transition-colors">Start
                            Trial</button>
                    </div>
                    <!-- Professional -->
                    <div class="bg-primary-container p-10 border-r border-outline flex flex-col relative">
                        <div
                            class="absolute top-4 right-4 bg-primary text-white text-[8px] font-black uppercase px-2 py-1 rounded">
                            Recommended</div>
                        <div class="mb-8">
                            <span
                                class="text-[10px] font-bold text-primary tracking-widest uppercase">Professional</span>
                            <div class="mt-4 flex items-baseline gap-1">
                                <span class="text-4xl font-black tracking-tighter">$399</span>
                                <span class="text-on-surface-variant text-sm">/mo</span>
                            </div>
                        </div>
                        <ul class="space-y-4 mb-10 flex-grow">
                            <li class="flex items-center gap-3 text-sm"><span
                                    class="material-symbols-outlined text-primary text-lg" data-icon="check_circle"
                                    data-weight="fill" style="font-variation-settings: 'FILL' 1;">check_circle</span> Up
                                to 3 Locations</li>
                            <li class="flex items-center gap-3 text-sm"><span
                                    class="material-symbols-outlined text-primary text-lg" data-icon="check_circle"
                                    data-weight="fill" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                Unlimited Technicians</li>
                            <li class="flex items-center gap-3 text-sm"><span
                                    class="material-symbols-outlined text-primary text-lg" data-icon="check_circle"
                                    data-weight="fill" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                Full Analytics Suite</li>
                            <li class="flex items-center gap-3 text-sm"><span
                                    class="material-symbols-outlined text-primary text-lg" data-icon="check_circle"
                                    data-weight="fill" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                SMS Notifications</li>
                        </ul>
                        <button
                            class="w-full py-3 bg-primary text-white font-bold rounded-lg shadow-md hover:opacity-90 transition-all">Go
                            Professional</button>
                    </div>
                    <!-- Enterprise -->
                    <div class="bg-white p-10 flex flex-col">
                        <div class="mb-8">
                            <span
                                class="text-[10px] font-bold text-on-surface-variant tracking-widest uppercase">Enterprise</span>
                            <div class="mt-4 flex items-baseline gap-1">
                                <span class="text-4xl font-black tracking-tighter">Custom</span>
                            </div>
                        </div>
                        <ul class="space-y-4 mb-10 flex-grow">
                            <li class="flex items-center gap-3 text-sm"><span
                                    class="material-symbols-outlined text-primary text-lg" data-icon="check_circle"
                                    data-weight="fill" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                Unlimited Locations</li>
                            <li class="flex items-center gap-3 text-sm"><span
                                    class="material-symbols-outlined text-primary text-lg" data-icon="check_circle"
                                    data-weight="fill" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                Custom API Access</li>
                            <li class="flex items-center gap-3 text-sm"><span
                                    class="material-symbols-outlined text-primary text-lg" data-icon="check_circle"
                                    data-weight="fill" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                Dedicated Success Manager</li>
                            <li class="flex items-center gap-3 text-sm"><span
                                    class="material-symbols-outlined text-primary text-lg" data-icon="check_circle"
                                    data-weight="fill" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                24/7 Priority Support</li>
                        </ul>
                        <button
                            class="w-full py-3 border-2 border-slate-900 text-slate-900 font-bold rounded-lg hover:bg-slate-50 transition-colors">Contact
                            Sales</button>
                    </div>
                </div>
            </div>
        </section>
        <!-- Company Information -->
        <section class="py-24 overflow-hidden" id="about">
            <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 lg:grid-cols-2 gap-20 items-center">
                <div class="relative">
                    <div class="aspect-square bg-slate-200 rounded-xl overflow-hidden shadow-xl"
                        data-alt="Modern minimalist architectural office interior"></div>
                    <div class="absolute -bottom-10 -right-10 w-64 h-64 bg-primary rounded-xl -z-10 opacity-10"></div>
                </div>
                <div class="space-y-6">
                    <h2 class="text-3xl font-black tracking-tighter leading-tight">Modernizing the Foundation of
                        Automotive Repair.</h2>
                    <p class="text-on-surface-variant leading-relaxed">
                        Cobalt Precision was born from the realization that while cars have become sophisticated
                        computers on wheels, the tools used to manage their repair remained stuck in the 20th century.
                    </p>
                    <p class="text-on-surface-variant leading-relaxed">
                        Our mission is to provide shop owners with high-fidelity operational tools that match the
                        engineering excellence of the vehicles they service. We believe in architectural rigor, data
                        transparency, and clinical efficiency.
                    </p>
                    <div class="grid grid-cols-2 gap-8 pt-6">
                        <div>
                            <div class="text-3xl font-black text-primary">99.9%</div>
                            <div class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Uptime SLA
                            </div>
                        </div>
                        <div>
                            <div class="text-3xl font-black text-primary">24ms</div>
                            <div class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Sync Latency
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- Support & Contact -->
        <section class="py-24 bg-white border-t border-outline" id="support">
            <div class="max-w-7xl mx-auto px-6">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-16">
                    <div class="lg:col-span-1">
                        <h2 class="text-3xl font-black tracking-tighter mb-6">Expert Support</h2>
                        <p class="text-on-surface-variant mb-8">Our engineering team is standing by to help you
                            integrate Cobalt into your existing workflow.</p>
                        <div class="space-y-4">
                            <div class="flex items-center gap-4 p-4 border border-outline rounded-lg">
                                <span class="material-symbols-outlined text-primary" data-icon="mail">mail</span>
                                <div>
                                    <div class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">
                                        Email Us</div>
                                    <div class="text-sm font-semibold">support@cobaltprecision.com</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 p-4 border border-outline rounded-lg">
                                <span class="material-symbols-outlined text-primary" data-icon="forum">forum</span>
                                <div>
                                    <div class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Live
                                        Chat</div>
                                    <div class="text-sm font-semibold">Average wait: 2 mins</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="lg:col-span-2 space-y-4">
                        <div class="p-6 bg-surface rounded-xl border border-outline">
                            <h3 class="font-bold mb-2">How long does migration take?</h3>
                            <p class="text-sm text-on-surface-variant">Most shops complete their data migration from
                                legacy systems within 48 hours with our automated onboarding tool.</p>
                        </div>
                        <div class="p-6 bg-surface rounded-xl border border-outline">
                            <h3 class="font-bold mb-2">Can I manage multiple franchises?</h3>
                            <p class="text-sm text-on-surface-variant">Yes, our Unified Fleet Management module is built
                                specifically for multi-location operations with hierarchical access controls.</p>
                        </div>
                        <div class="p-6 bg-surface rounded-xl border border-outline">
                            <h3 class="font-bold mb-2">Is my data secure?</h3>
                            <p class="text-sm text-on-surface-variant">We use bank-level AES-256 encryption for all data
                                at rest and TLS 1.3 for all data in transit.</p>
                        </div>
                        <a class="inline-flex items-center gap-2 text-primary text-sm font-bold hover:underline"
                            href="#">
                            View full documentation and FAQ
                            <span class="material-symbols-outlined text-sm"
                                data-icon="arrow_forward">arrow_forward</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <!-- Footer Component -->
    <footer class="w-full bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800">
        <div class="max-w-7xl mx-auto px-6 py-12 flex flex-col md:flex-row justify-between items-center gap-8">
            <div class="flex flex-col gap-2">
                <div class="text-lg font-black text-slate-900 dark:text-white">Cobalt Precision</div>
                <p class="font-['Inter'] text-xs text-slate-500 dark:text-slate-400">© 2024 Cobalt Precision. All rights
                    reserved.</p>
            </div>
            <div class="flex flex-wrap justify-center gap-6">
                <a class="font-['Inter'] text-xs text-slate-500 hover:text-[#1152d4] hover:underline transition-all"
                    href="#">Privacy Policy</a>
                <a class="font-['Inter'] text-xs text-slate-500 hover:text-[#1152d4] hover:underline transition-all"
                    href="#">Terms of Service</a>
                <a class="font-['Inter'] text-xs text-slate-500 hover:text-[#1152d4] hover:underline transition-all"
                    href="#">Cookie Policy</a>
                <a class="font-['Inter'] text-xs text-slate-500 hover:text-[#1152d4] hover:underline transition-all"
                    href="#">Contact Support</a>
            </div>
            <div class="flex gap-4">
                <span
                    class="material-symbols-outlined text-slate-400 hover:text-primary cursor-pointer transition-colors"
                    data-icon="language">language</span>
                <span
                    class="material-symbols-outlined text-slate-400 hover:text-primary cursor-pointer transition-colors"
                    data-icon="share">share</span>
            </div>
        </div>
    </footer>
</body>

</html>