<template>
    <main class="min-h-[100dvh] flex items-center justify-center bg-gray-50">
        <div class="text-center space-y-6 p-8">
            <h1 class="text-6xl font-bold text-gray-300">404</h1>
            <h2 class="text-xl font-semibold text-gray-700">Halaman Tidak Ditemukan</h2>
            <p class="text-gray-500 max-w-md">Halaman yang Anda cari tidak ditemukan atau telah dipindahkan.</p>
            <router-link
                to="/admin/dashboard"
                class="inline-flex items-center gap-2 px-6 py-3 bg-brand-primary text-white rounded-lg hover:bg-primary-800 transition-colors duration-200"
            >
                Kembali ke Dashboard
            </router-link>
        </div>
    </main>
</template>
