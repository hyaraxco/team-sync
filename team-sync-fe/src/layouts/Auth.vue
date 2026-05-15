<template>
    <div class="auth-shell min-h-screen bg-gradient-to-br from-slate-50 via-white to-blue-50">
        <a
            href="#auth-main"
            class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-[#0C51D9] focus:shadow-lg"
        >Skip to main content</a>

        <div class="flex min-h-screen w-full">
            <!-- Left Side - Visual (35%) -->
            <aside
                class="relative hidden w-1/2 overflow-hidden bg-gray-900 text-white lg:flex flex-col items-start justify-between"
            >
                <!-- Background Image with Overlay -->
                <img loading="eager"
                    src="https://images.unsplash.com/photo-1552581234-26160f608093?q=80&w=2940&auto=format&fit=crop"
                    alt="Team collaboration"
                    class="absolute inset-0 h-full w-full object-cover"
                    width="960"
                    height="1280"
                    fetchpriority="high"
                />
                <div
                    class="absolute inset-0 bg-gradient-to-br from-[#0C51D9]/90 via-[#1a3a6e]/85 to-[#0a1f44]/95"
                ></div>

                <!-- Decorative Elements -->
                <div
                    class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(255,255,255,0.1),transparent_50%)]"
                ></div>
                <div class="absolute bottom-0 left-0 right-0 h-1/2 bg-gradient-to-t from-black/30 to-transparent"></div>

                <!-- Content -->
                <div
                    class="relative z-10 flex h-full flex-col items-start justify-between gap-6 px-8 py-12 sm:px-16 lg:px-12 xl:px-16"
                >
                    <!-- Logo -->
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/20 backdrop-blur-sm">
                            <span class="text-lg font-bold text-white">TS</span>
                        </div>
                        <span class="text-lg font-semibold text-white">TeamSync</span>
                    </div>

                    <!-- Tagline -->
                    <div class="space-y-4">
                        <p
                            class="inline-flex items-center rounded-full bg-white/15 px-3 py-1.5 text-xs font-medium text-white/90 backdrop-blur-sm"
                        >
                            Internal HR Workspace
                        </p>
                        <h1 class="text-2xl font-bold leading-tight text-white xl:text-3xl">
                            Streamline your workforce operations
                        </h1>
                        <p class="text-sm leading-relaxed text-white/75 max-w-xs">
                            Payroll, attendance, and employee management in one secure platform.
                        </p>

                        <!-- Stats Row -->
                        <div class="flex gap-6 pt-4">
                            <div>
                                <p class="text-2xl font-bold text-white">6</p>
                                <p class="text-xs text-white/80">HR Modules</p>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-white">4</p>
                                <p class="text-xs text-white/80">User Roles</p>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-white">99%</p>
                                <p class="text-xs text-white/80">Uptime</p>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <p class="text-xs text-white/70">© 2026 TeamSync. All rights reserved.</p>
                </div>
            </aside>

            <!-- Right Side - Form (65%) -->
            <main
                id="auth-main"
                class="flex w-full flex-1 items-center justify-center px-6 py-12 lg:w-[65%] lg:px-16 xl:w-[60%] xl:px-24"
            >
                <RouterView />
            </main>
        </div>
    </div>
</template>

<style scoped>
.auth-view :deep(.router-link-active) {
    text-decoration: none;
}

@media (prefers-reduced-motion: reduce) {
    .auth-shell * {
        scroll-behavior: auto;
    }
}
</style>
