import { createApp, defineAsyncComponent } from "vue";
import { createPinia } from "pinia";

import "./assets/css/main.css";

import App from "./App.vue";
import router from "./router";

const app = createApp(App);
const VueApexCharts = defineAsyncComponent(() => import("vue3-apexcharts"));

app.use(createPinia());
app.use(router);
app.component("VueApexCharts", VueApexCharts);

// Global error handler — catches Vue rendering errors
app.config.errorHandler = (err, _instance, _info) => {
    console.error("[Vue Error]", err);
    // Future: send to Sentry / Datadog
};

app.mount("#app");
