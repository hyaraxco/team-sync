<template>
  <div class="flex" style="min-height: calc(100vh - 80px)">
    <!-- Left Side - Image -->
    <div class="hidden lg:flex lg:w-1/2 relative">
      <img
        src="https://images.unsplash.com/photo-1552581234-26160f608093?q=80&w=2940&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D"
        alt="Modern office workspace"
        class="object-cover w-full h-screen"
      />
    </div>

    <RouterView />
  </div>
</template>
