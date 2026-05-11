export default [
    {
        path: "performance",
        name: "admin.performance",
        redirect: { name: "admin.performance.my-reviews" },
        meta: {
            requiredPermission: "performance-menu",
        },
        children: [
            // Review Cycles (HR only)
            {
                path: "cycles",
                name: "admin.performance.cycles",
                component: () => import("@/views/admin/performance/ReviewCycleList.vue"),
                meta: {
                    requiredPermission: "review-cycle-manage",
                },
            },
            {
                path: "cycles/create",
                name: "admin.performance.cycles.create",
                component: () => import("@/views/admin/performance/ReviewCycleCreate.vue"),
                meta: {
                    requiredPermission: "review-cycle-manage",
                },
            },
            {
                path: "cycles/:id",
                name: "admin.performance.cycles.detail",
                component: () => import("@/views/admin/performance/ReviewCycleDetail.vue"),
                meta: {
                    requiredPermission: "review-cycle-manage",
                },
            },

            // Outcome Rules (HR only)
            {
                path: "outcome-rules",
                name: "admin.performance.outcome-rules",
                component: () => import("@/views/admin/performance/OutcomeRulesSettings.vue"),
                meta: {
                    requiredPermission: "review-cycle-manage",
                },
            },

            // Review Templates (HR only)
            {
                path: "templates",
                name: "admin.performance.templates",
                component: () => import("@/views/admin/performance/TemplateManagement.vue"),
                meta: {
                    requiredPermission: "review-cycle-manage",
                },
            },

            // Reviews
            {
                path: "reviews/my-reviews",
                name: "admin.performance.my-reviews",
                component: () => import("@/views/admin/performance/MyReviews.vue"),
                meta: {
                    requiredPermission: "performance-menu",
                },
            },
            {
                path: "reviews/team-reviews",
                name: "admin.performance.team-reviews",
                component: () => import("@/views/admin/performance/TeamReviews.vue"),
                meta: {
                    requiredPermission: "review-manager-submit",
                },
            },
            {
                path: "reviews/pending-calibration",
                name: "admin.performance.pending-calibration",
                component: () => import("@/views/admin/performance/PendingCalibration.vue"),
                meta: {
                    requiredPermission: "review-calibrate",
                },
            },
            {
                path: "reviews/:id",
                name: "admin.performance.review.detail",
                component: () => import("@/views/admin/performance/ReviewDetail.vue"),
                meta: {
                    requiredPermission: "performance-menu",
                },
            },

            // Goals
            {
                path: "goals/my-goals",
                name: "admin.performance.my-goals",
                component: () => import("@/views/admin/performance/MyGoals.vue"),
                meta: {
                    requiredPermission: "performance-menu",
                },
            },
            {
                path: "goals/team-goals",
                name: "admin.performance.team-goals",
                component: () => import("@/views/admin/performance/TeamGoals.vue"),
                meta: {
                    requiredPermission: "goal-assign-team",
                },
            },
            {
                path: "goals/:id",
                name: "admin.performance.goal.detail",
                component: () => import("@/views/admin/performance/GoalDetail.vue"),
                meta: {
                    requiredPermission: "performance-menu",
                },
            },

            // Feedback
            {
                path: "feedback/received",
                name: "admin.performance.feedback.received",
                component: () => import("@/views/admin/performance/FeedbackReceived.vue"),
                meta: {
                    requiredPermission: "performance-menu",
                },
            },
            {
                path: "feedback/given",
                name: "admin.performance.feedback.given",
                component: () => import("@/views/admin/performance/FeedbackGiven.vue"),
                meta: {
                    requiredPermission: "feedback-give",
                },
            },
            {
                path: "feedback/give",
                name: "admin.performance.feedback.give",
                component: () => import("@/views/admin/performance/GiveFeedback.vue"),
                meta: {
                    requiredPermission: "feedback-give",
                },
            },
        ],
    },
];
