<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50">
        <div class="text-center space-y-6 p-8">
            <h1 class="text-6xl font-bold text-gray-300">404</h1>
            <h2 class="text-xl font-semibold text-gray-700">Page Not Found</h2>
            <p class="text-gray-500 max-w-md">The page you are looking for does not exist or has been moved.</p>
            <router-link
                to="/admin/dashboard"
                class="inline-flex items-center gap-2 px-6 py-3 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200"
            >
                Back to Dashboard
            </router-link>
        </div>
    </div>
</template>
